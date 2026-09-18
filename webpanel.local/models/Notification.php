<?php
namespace Models;

use PDO;
use PDOException;

/**
 * Модель уведомления (Active Record)
 * Таблица: notifications
 * Содержит JOIN с residents для удобного вывода
 */
class Notification
{
    // Поля таблицы notifications
    public $id;
    public $id_residents;
    public $description;
    public $create_date;

    // Дополнительные поля из JOIN
    public $resident_name;   // Фамилия Имя
    public $inidroom;
    public $dormitory_id;
    public $dormitory_name;

    private $db;              // Объект PDO

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Загрузить уведомление по ID
     */
    public function load($id)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT n.*, 
                       r.last_name, r.first_name, r.inidroom, r.dormitory_id,
                       d.name AS dormitory_name
                FROM notifications n
                JOIN residents r ON n.id_residents = r.id
                LEFT JOIN dormitories d ON r.dormitory_id = d.id
                WHERE n.id = ?
            ");
            $stmt->execute([(int)$id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($data) {
                $this->id             = $data['id'];
                $this->id_residents   = $data['id_residents'];
                $this->description    = $data['description'];
                $this->create_date    = $data['create_date'];

                $this->resident_name  = $data['last_name'] . ' ' . $data['first_name'];
                $this->inidroom       = $data['inidroom'];
                $this->dormitory_id   = $data['dormitory_id'];
                $this->dormitory_name = $data['dormitory_name'] ?? ('Общежитие №' . ($data['dormitory_id'] ?? 1));
                return true;
            }
            return false;
        } catch (PDOException $e) {
            error_log("Notification load error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Сохранить (только INSERT, create_date ставится автоматически через NOW())
     */
    public function save()
    {
        try {
            if ($this->id) {
                // UPDATE (редко нужно, но оставляем для полноты)
                $stmt = $this->db->prepare("
                    UPDATE notifications 
                    SET id_residents = ?, 
                        description = ?
                    WHERE id = ?
                ");
                return $stmt->execute([
                    $this->id_residents,
                    $this->description,
                    $this->id
                ]);
            } else {
                // INSERT
                $stmt = $this->db->prepare("
                    INSERT INTO notifications 
                    (id_residents, description, create_date)
                    VALUES (?, ?, NOW())
                ");
                $result = $stmt->execute([
                    $this->id_residents,
                    $this->description
                ]);

                if ($result) {
                    $this->id = $this->db->lastInsertId();
                    // Загружаем create_date обратно
                    $this->load($this->id);
                }
                return $result;
            }
        } catch (PDOException $e) {
            error_log("Notification save error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Удалить уведомление
     */
    public function delete()
    {
        if (!$this->id) return false;

        try {
            $stmt = $this->db->prepare("DELETE FROM notifications WHERE id = ?");
            return $stmt->execute([$this->id]);
        } catch (PDOException $e) {
            error_log("Notification delete error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Получить ВСЕ уведомления (с JOIN и опциональным фильтром по общежитию)
     */
    public static function getAll(PDO $db, $dormitoryId = null, $limit = 200)
    {
        try {
            $sql = "
                SELECT n.*, 
                       r.last_name, r.first_name, r.inidroom, r.dormitory_id,
                       d.name AS dormitory_name
                FROM notifications n
                JOIN residents r ON n.id_residents = r.id
                LEFT JOIN dormitories d ON r.dormitory_id = d.id
            ";
            $params = [];
            if (!empty($dormitoryId)) {
                $sql .= " WHERE r.dormitory_id = ?";
                $params[] = (int)$dormitoryId;
            }
            $sql .= " ORDER BY n.create_date DESC, n.id DESC LIMIT " . (int)$limit;

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $notifications = [];
            foreach ($rows as $row) {
                $n = new self($db);
                $n->id             = $row['id'];
                $n->id_residents   = $row['id_residents'];
                $n->description    = $row['description'];
                $n->create_date    = $row['create_date'];

                $n->resident_name  = $row['last_name'] . ' ' . $row['first_name'];
                $n->inidroom       = $row['inidroom'];
                $n->dormitory_id   = $row['dormitory_id'];
                $n->dormitory_name = $row['dormitory_name'] ?? ('Общежитие №' . ($row['dormitory_id'] ?? 1));
                $notifications[]   = $n;
            }
            return $notifications;
        } catch (PDOException $e) {
            error_log("Notification getAll error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Получить всех жителей для формы отправки уведомления
     * (с опциональной фильтрацией по общежитию)
     */
    public static function getAllResidents(PDO $db, $dormitoryId = null)
    {
        $sql = "
            SELECT r.id, r.last_name, r.first_name, r.patronymic, r.inidroom, r.dormitory_id, r.tg_id, r.vk_id, r.max_id,
                   d.name AS dormitory_name
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
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}