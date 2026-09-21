<?php
namespace App\Controllers;

use PDO;
use PDOException;
use Models\Dormitory;

class HallOfFameController extends BaseController
{
    public function index()
    {
        $role = $_SESSION['role'] ?? 0;
        $isLoggedIn = isset($_SESSION['admin_id']);
        $sessionDormId = !empty($_SESSION['dormitory_id']) ? (int)$_SESSION['dormitory_id'] : null;
        $roleName = getUserRoleTitle($role, $sessionDormId, $_SESSION['dormitory_name'] ?? null);

        // Фильтр по общежитию
        $dormitory_id = isset($_GET['dormitory_id']) && $_GET['dormitory_id'] !== '' ? (int)$_GET['dormitory_id'] : null;

        // Если админ привязан к общежитию, по умолчанию ставим его общежитие, но даём переключать при желании
        if ($sessionDormId !== null && !isset($_GET['dormitory_id'])) {
            $dormitory_id = $sessionDormId;
        }

        // Загрузка общежитий
        $dormitories = Dormitory::getAll($this->pdo);

        // Поиск пользователя для блока "Моё место"
        $myResidentId = isset($_GET['me']) ? (int)$_GET['me'] : null;
        $searchQuery  = isset($_GET['fio']) ? trim($_GET['fio']) : '';

        // 1. Топ 3 лидеров (максимум баллов и стриков)
        $top3 = $this->getTopLeaders($dormitory_id, 3);
        $top3Ids = array_column($top3, 'id');

        // 2. Антитоп удалён по требованию
        $bottom3 = [];

        // 3. Общее количество жителей и остальных участников
        $totalResidents = $this->getTotalResidentsCount($dormitory_id);
        $shownCount = count($top3);
        $middleCount = max(0, $totalResidents - $shownCount);

        // 4. Определение "Моё место"
        $myCard = null;
        if ($myResidentId) {
            $myCard = $this->getResidentRankCard($myResidentId, $dormitory_id);
        } elseif (!empty($searchQuery)) {
            $foundRes = $this->findResidentByQuery($searchQuery, $dormitory_id);
            if ($foundRes) {
                $myCard = $this->getResidentRankCard((int)$foundRes['id'], $dormitory_id);
            }
        }

        // Если запрошен AJAX для разворачивания промежуточного списка
        if (isset($_GET['ajax_middle'])) {
            header('Content-Type: application/json; charset=utf-8');
            $middleList = $this->getMiddleResidents($dormitory_id, $top3Ids);
            echo json_encode(['success' => true, 'residents' => $middleList], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Если запрошен AJAX для живого поиска "Найти своё место"
        if (isset($_GET['ajax_search'])) {
            header('Content-Type: application/json; charset=utf-8');
            $q = trim($_GET['ajax_search']);
            $results = $this->searchResidentsLive($q, $dormitory_id);
            echo json_encode(['success' => true, 'results' => $results], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $this->render('hall_of_fame', [
            'roleName'       => $roleName,
            'isLoggedIn'     => $isLoggedIn,
            'sessionDormId'  => $sessionDormId,
            'dormitory_id'   => $dormitory_id,
            'dormitories'    => $dormitories,
            'top3'           => $top3,
            'bottom3'        => $bottom3,
            'totalResidents' => $totalResidents,
            'middleCount'    => $middleCount,
            'myCard'         => $myCard,
            'searchQuery'    => $searchQuery,
            'controller'     => $this
        ], $isLoggedIn);
    }

    /**
     * Получить Топ-N лидеров по баллам
     */
    public function getTopLeaders($dormitoryId = null, $limit = 3)
    {
        try {
            $sql = "
                SELECT r.id, r.last_name, r.first_name, r.patronymic, r.inidroom, r.dormitory_id,
                       r.score, r.confirm_streak, r.miss_streak, r.tg_id, r.vk_id, r.max_id,
                       d.name AS dormitory_name
                FROM residents r
                LEFT JOIN dormitories d ON r.dormitory_id = d.id
                WHERE r.is_banned = false
            ";
            $params = [];
            if ($dormitoryId) {
                $sql .= " AND r.dormitory_id = ?";
                $params[] = $dormitoryId;
            }
            $sql .= " ORDER BY r.score DESC, r.confirm_streak DESC, r.id ASC LIMIT ?";
            $params[] = $limit;

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Прикрепляем ссылку на соцсеть по правилу приоритета (TG > VK > MAX)
            foreach ($rows as &$row) {
                $row['social'] = self::getSocialContact($row['tg_id'], $row['vk_id'], $row['max_id']);
            }
            return $rows;
        } catch (PDOException $e) {
            error_log("getTopLeaders error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Получить Антитоп-N (кто пропускал стирку / имеет наименьшие баллы)
     */
    public function getBottomMissed($dormitoryId = null, $limit = 3, array $excludeIds = [])
    {
        try {
            $sql = "
                SELECT r.id, r.last_name, r.first_name, r.patronymic, r.inidroom, r.dormitory_id,
                       r.score, r.confirm_streak, r.miss_streak, r.tg_id, r.vk_id, r.max_id,
                       d.name AS dormitory_name
                FROM residents r
                LEFT JOIN dormitories d ON r.dormitory_id = d.id
                WHERE 1=1
            ";
            $params = [];
            if ($dormitoryId) {
                $sql .= " AND r.dormitory_id = ?";
                $params[] = $dormitoryId;
            }
            if (!empty($excludeIds)) {
                $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));
                $sql .= " AND r.id NOT IN ($placeholders)";
                foreach ($excludeIds as $eid) {
                    $params[] = (int)$eid;
                }
            }
            $sql .= " ORDER BY r.score ASC, r.miss_streak DESC, r.id ASC LIMIT ?";
            $params[] = $limit;

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("getBottomMissed error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Общее число жителей для фильтра
     */
    public function getTotalResidentsCount($dormitoryId = null)
    {
        try {
            $sql = "SELECT COUNT(*) FROM residents WHERE is_banned = false";
            $params = [];
            if ($dormitoryId) {
                $sql .= " AND dormitory_id = ?";
                $params[] = $dormitoryId;
            }
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            return 0;
        }
    }

    /**
     * Вычислить позицию жителя и получить карточку "Моё место"
     */
    public function getResidentRankCard($residentId, $dormitoryId = null)
    {
        try {
            // Оконная функция для ранжирования
            $sql = "
                WITH ranked AS (
                    SELECT r.id, r.last_name, r.first_name, r.patronymic, r.inidroom, r.dormitory_id,
                           r.score, r.confirm_streak, r.miss_streak, r.tg_id, r.vk_id, r.max_id,
                           d.name AS dormitory_name,
                           ROW_NUMBER() OVER (ORDER BY r.score DESC, r.confirm_streak DESC, r.id ASC) AS rank_pos
                    FROM residents r
                    LEFT JOIN dormitories d ON r.dormitory_id = d.id
                    WHERE r.is_banned = false
            ";
            $params = [];
            if ($dormitoryId) {
                $sql .= " AND r.dormitory_id = ?";
                $params[] = $dormitoryId;
            }
            $sql .= "
                )
                SELECT * FROM ranked WHERE id = ?
            ";
            $params[] = $residentId;

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($res) {
                $res['social'] = self::getSocialContact($res['tg_id'], $res['vk_id'], $res['max_id']);
            }
            return $res;
        } catch (PDOException $e) {
            error_log("getResidentRankCard error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Найти жителя по ФИО или номеру зачётки
     */
    public function findResidentByQuery($query, $dormitoryId = null)
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return null;
        }
        try {
            $sql = "
                SELECT id FROM residents
                WHERE is_banned = false
                  AND (
                      CONCAT(last_name, ' ', first_name, ' ', COALESCE(patronymic, '')) ILIKE ?
                      OR idcards ILIKE ?
                      OR last_name ILIKE ?
                  )
            ";
            $escaped = addcslashes($query, '%_\\');
            $wild = '%' . $escaped . '%';
            $params = [$wild, $wild, $wild];
            if ($dormitoryId) {
                $sql .= " AND dormitory_id = ?";
                $params[] = $dormitoryId;
            }
            $sql .= " ORDER BY score DESC LIMIT 1";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return null;
        }
    }

    /**
     * Живой поиск жителей по ФИО/зачетке
     */
    public function searchResidentsLive($query, $dormitoryId = null)
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) return [];
        try {
            $sql = "
                SELECT r.id, r.last_name, r.first_name, r.inidroom, r.score, r.confirm_streak,
                       d.name AS dormitory_name
                FROM residents r
                LEFT JOIN dormitories d ON r.dormitory_id = d.id
                WHERE r.is_banned = false
                  AND (
                      CONCAT(r.last_name, ' ', r.first_name, ' ', COALESCE(r.patronymic, '')) ILIKE ?
                      OR r.idcards ILIKE ?
                      OR r.last_name ILIKE ?
                  )
            ";
            $escaped = addcslashes($query, '%_\\');
            $wild = '%' . $escaped . '%';
            $params = [$wild, $wild, $wild];
            if ($dormitoryId) {
                $sql .= " AND r.dormitory_id = ?";
                $params[] = $dormitoryId;
            }
            $sql .= " ORDER BY r.score DESC, r.last_name ASC LIMIT 8";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Получить список жителей между Топ-3 и Антитоп-3
     */
    public function getMiddleResidents($dormitoryId = null, array $excludeIds = [])
    {
        try {
            $sql = "
                WITH ranked AS (
                    SELECT r.id, r.last_name, r.first_name, r.patronymic, r.inidroom, r.dormitory_id,
                           r.score, r.confirm_streak, r.miss_streak,
                           d.name AS dormitory_name,
                           ROW_NUMBER() OVER (ORDER BY r.score DESC, r.confirm_streak DESC, r.id ASC) AS rank_pos
                    FROM residents r
                    LEFT JOIN dormitories d ON r.dormitory_id = d.id
                    WHERE r.is_banned = false
            ";
            $params = [];
            if ($dormitoryId) {
                $sql .= " AND r.dormitory_id = ?";
                $params[] = $dormitoryId;
            }
            $sql .= " ) SELECT * FROM ranked WHERE 1=1";
            if (!empty($excludeIds)) {
                $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));
                $sql .= " AND id NOT IN ($placeholders)";
                foreach ($excludeIds as $eid) {
                    $params[] = (int)$eid;
                }
            }
            $sql .= " ORDER BY rank_pos ASC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * ПРАВИЛО ПРИОРИТЕТА СОЦСЕТЕЙ:
     * - Если есть Telegram -> Telegram (tg://user?id=... или https://t.me/...)
     * - Если Telegram нет, но есть ВКонтакте -> ВКонтакте (https://vk.com/id...)
     * - MAX только в том случае, если он единственный (нет ни TG, ни VK)
     * - Иначе null
     */
    public static function getSocialContact($tg_id, $vk_id, $max_id)
    {
        $hasTg  = !empty($tg_id);
        $hasVk  = !empty($vk_id);
        $hasMax = !empty($max_id);

        // 1. Telegram в наивысшем приоритете
        if ($hasTg) {
            $tgVal = trim($tg_id);
            $isNum = ctype_digit($tgVal);
            $url = $isNum ? "tg://user?id={$tgVal}" : "https://t.me/" . ltrim($tgVal, '@');
            return [
                'type'     => 'tg',
                'title'    => 'Telegram',
                'url'      => $url,
                'icon'     => 'fa-brands fa-telegram',
                'color'    => '#0284c7',
                'bg'       => '#f0f9ff',
                'border'   => '#bae6fd',
                'badge'    => 'Telegram'
            ];
        }

        // 2. ВКонтакте (если нет TG)
        if ($hasVk) {
            $vkVal = trim($vk_id);
            return [
                'type'     => 'vk',
                'title'    => 'ВКонтакте',
                'url'      => "https://vk.com/id{$vkVal}",
                'icon'     => 'fa-brands fa-vk',
                'color'    => '#2563eb',
                'bg'       => '#eff6ff',
                'border'   => '#bfdbfe',
                'badge'    => 'ВКонтакте'
            ];
        }

        // 3. MAX ТОЛЬКО если он единственный (нет ни TG, ни VK)
        if ($hasMax) {
            $maxVal = trim($max_id);
            return [
                'type'     => 'max',
                'title'    => 'MAX Мессенджер',
                'url'      => "https://max.ru",
                'icon'     => 'fa-solid fa-comments',
                'color'    => '#c2410c',
                'bg'       => '#fff7ed',
                'border'   => '#fed7aa',
                'badge'    => "MAX (ID: {$maxVal})"
            ];
        }

        return null;
    }
}
