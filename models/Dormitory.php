<?php
namespace Models;

use PDO;
use PDOException;

/**
 * Модель общежития (Active Record)
 * Таблица: dormitories
 */
class Dormitory
{
    public $id;
    public $number;
    public $name;
    public $address;

    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Загрузить общежитие по ID
     */
    public function load($id)
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM dormitories WHERE id = ?");
            $stmt->execute([(int)$id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($data) {
                $this->id      = (int)$data['id'];
                $this->number  = (int)$data['number'];
                $this->name    = $data['name'];
                $this->address = $data['address'];
                return true;
            }
            return false;
        } catch (PDOException $e) {
            error_log("Dormitory load error: " . $e->getMessage());
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
                $stmt = $this->db->prepare("
                    UPDATE dormitories 
                    SET number = ?, name = ?, address = ?
                    WHERE id = ?
                ");
                return $stmt->execute([
                    (int)$this->number,
                    $this->name,
                    $this->address,
                    $this->id
                ]);
            } else {
                $stmt = $this->db->prepare("
                    INSERT INTO dormitories (number, name, address)
                    VALUES (?, ?, ?)
                ");
                $result = $stmt->execute([
                    (int)$this->number,
                    $this->name,
                    $this->address
                ]);

                if ($result) {
                    $this->id = (int)$this->db->lastInsertId();
                }
                return $result;
            }
        } catch (PDOException $e) {
            error_log("Dormitory save error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Удалить общежитие
     */
    public function delete()
    {
        if (!$this->id) return false;

        try {
            $stmt = $this->db->prepare("DELETE FROM dormitories WHERE id = ?");
            return $stmt->execute([$this->id]);
        } catch (PDOException $e) {
            error_log("Dormitory delete error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Получить все общежития, отсортированные по номеру
     */
    public static function getAll(PDO $db)
    {
        try {
            $stmt = $db->query("SELECT * FROM dormitories ORDER BY number ASC");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $list = [];
            foreach ($rows as $row) {
                $d = new self($db);
                $d->id      = (int)$row['id'];
                $d->number  = (int)$row['number'];
                $d->name    = $row['name'];
                $d->address = $row['address'];
                $list[]     = $d;
            }
            return $list;
        } catch (PDOException $e) {
            error_log("Dormitory getAll error: " . $e->getMessage());
            return [];
        }
    }
}
