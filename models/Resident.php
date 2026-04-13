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
    public $last_name;
    public $first_name;
    public $inidroom;

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
            $stmt = $this->db->prepare("SELECT * FROM residents WHERE id = ?");
            $stmt->execute([(int)$id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($data) {
                $this->id         = $data['id'];
                $this->last_name  = $data['last_name'];
                $this->first_name = $data['first_name'];
                $this->inidroom   = $data['inidroom'];
                return true;
            }
            return false;
        } catch (PDOException $e) {
            error_log("Resident load error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Сохранить (INSERT или UPDATE)
     */
    public function save()
    {
        try {
            if ($this->id) {
                // UPDATE
                $stmt = $this->db->prepare("
                    UPDATE residents 
                    SET last_name = ?, 
                        first_name = ?, 
                        inidroom = ? 
                    WHERE id = ?
                ");
                return $stmt->execute([
                    $this->last_name,
                    $this->first_name,
                    $this->inidroom,
                    $this->id
                ]);
            } else {
                // INSERT
                $stmt = $this->db->prepare("
                    INSERT INTO residents (last_name, first_name, inidroom)
                    VALUES (?, ?, ?)
                ");
                $result = $stmt->execute([
                    $this->last_name,
                    $this->first_name,
                    $this->inidroom
                ]);

                if ($result) {
                    $this->id = $this->db->lastInsertId();
                }
                return $result;
            }
        } catch (PDOException $e) {
            error_log("Resident save error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Удалить жителя
     */
    public function delete()
    {
        if (!$this->id) return false;

        try {
            $stmt = $this->db->prepare("DELETE FROM residents WHERE id = ?");
            return $stmt->execute([$this->id]);
        } catch (PDOException $e) {
            error_log("Resident delete error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Получить ВСЕХ жителей
     */
    public static function getAll(PDO $db)
    {
        try {
            $stmt = $db->query("
                SELECT * FROM residents 
                ORDER BY last_name, first_name
            ");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $residents = [];
            foreach ($rows as $row) {
                $r = new self($db);
                $r->id         = $row['id'];
                $r->last_name  = $row['last_name'];
                $r->first_name = $row['first_name'];
                $r->inidroom   = $row['inidroom'];
                $residents[] = $r;
            }
            return $residents;
        } catch (PDOException $e) {
            error_log("Resident getAll error: " . $e->getMessage());
            return [];
        }
    }
}