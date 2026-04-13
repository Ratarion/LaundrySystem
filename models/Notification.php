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
                       r.last_name, r.first_name, r.inidroom
                FROM notifications n
                JOIN residents r ON n.id_residents = r.id
                WHERE n.id = ?
            ");
            $stmt->execute([(int)$id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($data) {
                $this->id            = $data['id'];
                $this->id_residents  = $data['id_residents'];
                $this->description   = $data['description'];
                $this->create_date   = $data['create_date'];

                $this->resident_name = $data['last_name'] . ' ' . $data['first_name'];
                $this->inidroom      = $data['inidroom'];
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
     * Получить ВСЕ уведомления (с JOIN)
     */
    public static function getAll(PDO $db)
    {
        try {
            $stmt = $db->query("
                SELECT n.*, 
                       r.last_name, r.first_name, r.inidroom
                FROM notifications n
                JOIN residents r ON n.id_residents = r.id
                ORDER BY n.create_date DESC
            ");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $notifications = [];
            foreach ($rows as $row) {
                $n = new self($db);
                $n->id            = $row['id'];
                $n->id_residents  = $row['id_residents'];
                $n->description   = $row['description'];
                $n->create_date   = $row['create_date'];

                $n->resident_name = $row['last_name'] . ' ' . $row['first_name'];
                $n->inidroom      = $row['inidroom'];
                $notifications[] = $n;
            }
            return $notifications;
        } catch (PDOException $e) {
            error_log("Notification getAll error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Получить всех жителей для формы отправки уведомления
     * (вспомогательный статический метод)
     */
    public static function getAllResidents(PDO $db)
    {
        $stmt = $db->query("
            SELECT id, last_name, first_name, inidroom 
            FROM residents 
            ORDER BY last_name, first_name
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}