<?php
namespace Models;

use PDO;
use PDOException;

/**
 * Модель жителя (Active Record)
 * Таблица: residents
 */
class Resident
{
    // Поля таблицы
    public $id;
    public $dormitory_id;     // ID общежития
    public $last_name;
    public $first_name;
    public $patronymic;
    public $inidroom;
    public $idcards;
    public $tg_id;
    public $vk_id;
    public $max_id;
    public $language;
    public $notify_unconfirmed = true;
    public $is_banned = false;
    public $score = 100;
    public $confirm_streak = 0;
    public $miss_streak = 0;

    // Дополнительные поля из JOIN
    public $dormitory_name;

    private $db;              // Объект PDO

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Загрузить жителя по ID
     */
    public function load($id)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT r.*, d.name AS dormitory_name 
                FROM residents r
                LEFT JOIN dormitories d ON r.dormitory_id = d.id
                WHERE r.id = ?
            ");
            $stmt->execute([(int)$id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($data) {
                $this->id                  = (int)$data['id'];
                $this->dormitory_id        = !empty($data['dormitory_id']) ? (int)$data['dormitory_id'] : 1;
                $this->last_name           = $data['last_name'];
                $this->first_name          = $data['first_name'];
                $this->patronymic          = $data['patronymic'];
                $this->inidroom            = $data['inidroom'];
                $this->idcards             = $data['idcards'];
                $this->tg_id               = $data['tg_id'];
                $this->vk_id               = $data['vk_id'];
                $this->max_id              = $data['max_id'] ?? null;
                $this->language            = $data['language'] ?? 'RU';
                $this->notify_unconfirmed  = isset($data['notify_unconfirmed']) ? (bool)$data['notify_unconfirmed'] : true;
                $this->is_banned           = isset($data['is_banned']) ? (bool)$data['is_banned'] : false;
                $this->score               = isset($data['score']) ? (int)$data['score'] : 100;
                $this->confirm_streak      = isset($data['confirm_streak']) ? (int)$data['confirm_streak'] : 0;
                $this->miss_streak         = isset($data['miss_streak']) ? (int)$data['miss_streak'] : 0;
                $this->dormitory_name      = $data['dormitory_name'] ?? ('Общежитие №' . $this->dormitory_id);
                return true;
            }
            return false;
        } catch (PDOException $e) {
            error_log("Resident load error: " . $e->getMessage());
            return false;
        }
    }

    public $lastError = '';

    public function getLastError()
    {
        return $this->lastError;
    }

    /**
     * Сохранить жителя (INSERT или UPDATE)
     */
    public function save()
    {
        try {
            $dormId = !empty($this->dormitory_id) ? (int)$this->dormitory_id : 1;

            // Гарантируем, что комната существует в таблице rooms
            if (!empty($this->inidroom)) {
                $roomStmt = $this->db->prepare("INSERT INTO rooms (idroom) VALUES (?) ON CONFLICT (idroom) DO NOTHING");
                $roomStmt->execute([(int)$this->inidroom]);
            }

            if ($this->id) {
                // UPDATE
                $stmt = $this->db->prepare("
                    UPDATE residents 
                    SET dormitory_id = ?,
                        last_name = ?, 
                        first_name = ?, 
                        patronymic = ?, 
                        inidroom = ?,
                        idcards = ?,
                        notify_unconfirmed = ?,
                        is_banned = ?
                    WHERE id = ?
                ");
                return $stmt->execute([
                    $dormId,
                    $this->last_name,
                    $this->first_name,
                    $this->patronymic,
                    !empty($this->inidroom) ? (int)$this->inidroom : null,
                    !empty($this->idcards) ? trim((string)$this->idcards) : null,
                    $this->notify_unconfirmed ? 1 : 0,
                    $this->is_banned ? 1 : 0,
                    $this->id
                ]);
            } else {
                // INSERT
                $stmt = $this->db->prepare("
                    INSERT INTO residents (dormitory_id, last_name, first_name, patronymic, inidroom, idcards, language, notify_unconfirmed, is_banned)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $result = $stmt->execute([
                    $dormId,
                    $this->last_name,
                    $this->first_name,
                    $this->patronymic,
                    !empty($this->inidroom) ? (int)$this->inidroom : null,
                    !empty($this->idcards) ? trim((string)$this->idcards) : null,
                    $this->language ?? 'RU',
                    $this->notify_unconfirmed ? 1 : 0,
                    $this->is_banned ? 1 : 0
                ]);

                if ($result) {
                    $this->id = (int)$this->db->lastInsertId();
                }
                return $result;
            }
        } catch (PDOException $e) {
            error_log("Resident save error: " . $e->getMessage());
            if (strpos($e->getMessage(), 'residents_idcards_key') !== false) {
                $this->lastError = 'Номер зачётки / ID карты уже занят другим жителем!';
            } else {
                $this->lastError = 'Ошибка базы данных: ' . $e->getMessage();
            }
            return false;
        }
    }

    /**
     * Удалить жителя
     */
    public function delete()
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM residents WHERE id = ?");
            return $stmt->execute([(int)$this->id]);
        } catch (PDOException $e) {
            error_log("Resident delete error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Получить список всех жителей (с расширенной фильтрацией)
     */
    public static function getAll(PDO $db, $dormitoryId = null, array $filters = [])
    {
        try {
            $sql = "
                SELECT r.*, d.name AS dormitory_name 
                FROM residents r
                LEFT JOIN dormitories d ON r.dormitory_id = d.id
                WHERE 1=1
            ";
            $params = [];

            if (!empty($dormitoryId)) {
                $sql .= " AND r.dormitory_id = ?";
                $params[] = (int)$dormitoryId;
            }

            if (!empty($filters['fio'])) {
                $sql .= " AND (CONCAT_WS(' ', r.last_name, r.first_name, r.patronymic) ILIKE ? OR r.last_name ILIKE ? OR r.first_name ILIKE ?)";
                $search = '%' . $filters['fio'] . '%';
                $params[] = $search;
                $params[] = $search;
                $params[] = $search;
            }

            if (!empty($filters['room'])) {
                $sql .= " AND CAST(r.inidroom AS TEXT) ILIKE ?";
                $params[] = '%' . $filters['room'] . '%';
            }

            if (!empty($filters['idcard'])) {
                $sql .= " AND CAST(r.idcards AS TEXT) ILIKE ?";
                $params[] = '%' . $filters['idcard'] . '%';
            }

            if (!empty($filters['bot_status'])) {
                if ($filters['bot_status'] === 'connected') {
                    $sql .= " AND (r.tg_id IS NOT NULL OR r.vk_id IS NOT NULL OR r.max_id IS NOT NULL)";
                } elseif ($filters['bot_status'] === 'not_connected') {
                    $sql .= " AND (r.tg_id IS NULL AND r.vk_id IS NULL AND r.max_id IS NULL)";
                } elseif ($filters['bot_status'] === 'tg') {
                    $sql .= " AND r.tg_id IS NOT NULL";
                } elseif ($filters['bot_status'] === 'vk') {
                    $sql .= " AND r.vk_id IS NOT NULL";
                } elseif ($filters['bot_status'] === 'max') {
                    $sql .= " AND r.max_id IS NOT NULL";
                }
            }

            if (isset($filters['notify_status']) && $filters['notify_status'] !== '') {
                if ($filters['notify_status'] === '1') {
                    $sql .= " AND r.notify_unconfirmed IS TRUE";
                } elseif ($filters['notify_status'] === '0') {
                    $sql .= " AND r.notify_unconfirmed IS FALSE";
                }
            }

            if (!empty($filters['ban_status'])) {
                if ($filters['ban_status'] === 'banned') {
                    $sql .= " AND r.is_banned IS TRUE";
                } elseif ($filters['ban_status'] === 'active') {
                    $sql .= " AND (r.is_banned IS FALSE OR r.is_banned IS NULL)";
                }
            }

            if (!empty($filters['score_status'])) {
                if ($filters['score_status'] === 'master') {
                    $sql .= " AND r.score >= 140";
                } elseif ($filters['score_status'] === 'good') {
                    $sql .= " AND r.score >= 90 AND r.score < 140";
                } elseif ($filters['score_status'] === 'warning') {
                    $sql .= " AND r.score >= 50 AND r.score < 90";
                } elseif ($filters['score_status'] === 'critical') {
                    $sql .= " AND r.score < 50";
                }
            }

            $sql .= " ORDER BY d.number ASC, r.last_name, r.first_name";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $residents = [];
            foreach ($rows as $row) {
                $r = new self($db);
                $r->id                  = (int)$row['id'];
                $r->dormitory_id        = !empty($row['dormitory_id']) ? (int)$row['dormitory_id'] : 1;
                $r->last_name           = $row['last_name'];
                $r->first_name          = $row['first_name'];
                $r->patronymic          = $row['patronymic'];
                $r->inidroom            = $row['inidroom'];
                $r->idcards             = $row['idcards'];
                $r->tg_id               = $row['tg_id'];
                $r->vk_id               = $row['vk_id'];
                $r->max_id              = $row['max_id'] ?? null;
                $r->language            = $row['language'] ?? 'RU';
                $r->notify_unconfirmed  = isset($row['notify_unconfirmed']) ? (bool)$row['notify_unconfirmed'] : true;
                $r->is_banned           = isset($row['is_banned']) ? (bool)$row['is_banned'] : false;
                $r->score               = isset($row['score']) ? (int)$row['score'] : 100;
                $r->confirm_streak      = isset($row['confirm_streak']) ? (int)$row['confirm_streak'] : 0;
                $r->miss_streak         = isset($row['miss_streak']) ? (int)$row['miss_streak'] : 0;
                $r->dormitory_name      = $row['dormitory_name'] ?? ('Общежитие №' . $r->dormitory_id);
                $residents[]            = $r;
            }
            return $residents;

        } catch (PDOException $e) {
            error_log("Resident getAll error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Ручная корректировка баллов администратором
     */
    public static function adjustScore(PDO $db, $residentId, $delta, $reason, $details = null)
    {
        try {
            $stmt = $db->prepare("SELECT score, is_banned FROM residents WHERE id = ? FOR UPDATE");
            $stmt->execute([(int)$residentId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return false;
            }

            $currentScore = (int)$row['score'];
            $newScore = max(-1000, min(200, $currentScore + (int)$delta));
            $isBanned = $row['is_banned'];

            if ($newScore <= -1000) {
                $isBanned = true;
            }

            $upd = $db->prepare("UPDATE residents SET score = ?, is_banned = ? WHERE id = ?");
            $upd->execute([$newScore, $isBanned ? 1 : 0, (int)$residentId]);

            $log = $db->prepare("
                INSERT INTO resident_score_logs (resident_id, delta, score_after, reason, details)
                VALUES (?, ?, ?, ?, ?)
            ");
            $log->execute([
                (int)$residentId,
                (int)$delta,
                $newScore,
                $reason ?: 'ADMIN_ADJUSTMENT',
                $details ?: 'Ручная корректировка администратором'
            ]);

            return true;
        } catch (PDOException $e) {
            error_log("Resident adjustScore error: " . $e->getMessage());
            return false;
        }
    }
}