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
                        notify_unconfirmed = ?
                    WHERE id = ?
                ");
                return $stmt->execute([
                    $dormId,
                    $this->last_name,
                    $this->first_name,
                    $this->patronymic,
                    !empty($this->inidroom) ? (int)$this->inidroom : null,
                    !empty($this->idcards) ? (int)$this->idcards : null,
                    $this->notify_unconfirmed ? 1 : 0,
                    $this->id
                ]);
            } else {
                // INSERT
                $stmt = $this->db->prepare("
                    INSERT INTO residents (dormitory_id, last_name, first_name, patronymic, inidroom, idcards, language, notify_unconfirmed)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $result = $stmt->execute([
                    $dormId,
                    $this->last_name,
                    $this->first_name,
                    $this->patronymic,
                    !empty($this->inidroom) ? (int)$this->inidroom : null,
                    !empty($this->idcards) ? (int)$this->idcards : null,
                    $this->language ?? 'RU',
                    $this->notify_unconfirmed ? 1 : 0
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
     * Получить список всех жителей (с фильтрацией по общежитию)
     */
    public static function getAll(PDO $db, $dormitoryId = null)
    {
        try {
            $sql = "
                SELECT r.*, d.name AS dormitory_name 
                FROM residents r
                LEFT JOIN dormitories d ON r.dormitory_id = d.id
            ";
            $params = [];

            if (!empty($dormitoryId)) {
                $sql .= " WHERE r.dormitory_id = ?";
                $params[] = (int)$dormitoryId;
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
                $r->dormitory_name      = $row['dormitory_name'] ?? ('Общежитие №' . $r->dormitory_id);
                $residents[]            = $r;
            }
            return $residents;

        } catch (PDOException $e) {
            error_log("Resident getAll error: " . $e->getMessage());
            return [];
        }
    }
}