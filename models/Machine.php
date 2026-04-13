<?php
namespace Models;

use PDO;
use PDOException;

/**
 * Модель машины (Active Record)
 * Таблица: machines
 */
class Machine
{
    // Поля таблицы
    public $id;
    public $type_machine;     // "Стиральная" или "Сушильная"
    public $number_machine;   // "#5", "3 этаж" и т.д.
    public $status;           // 1 = работает, 0 = отключена

    private $db;              // Объект PDO

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Загрузить машину по ID
     */
    public function load($id)
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM machines WHERE id = ?");
            $stmt->execute([(int)$id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($data) {
                $this->id             = $data['id'];
                $this->type_machine   = $data['type_machine'];
                $this->number_machine = $data['number_machine'];
                $this->status         = $data['status'];
                return true;
            }
            return false;
        } catch (PDOException $e) {
            error_log("Machine load error: " . $e->getMessage());
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
                    UPDATE machines 
                    SET type_machine = ?, 
                        number_machine = ?, 
                        status = ? 
                    WHERE id = ?
                ");
                return $stmt->execute([
                    $this->type_machine,
                    $this->number_machine,
                    (int)$this->status,
                    $this->id
                ]);
            } else {
                // INSERT
                $stmt = $this->db->prepare("
                    INSERT INTO machines (type_machine, number_machine, status)
                    VALUES (?, ?, ?)
                ");
                $result = $stmt->execute([
                    $this->type_machine,
                    $this->number_machine,
                    (int)$this->status
                ]);

                if ($result) {
                    $this->id = $this->db->lastInsertId();
                }
                return $result;
            }
        } catch (PDOException $e) {
            error_log("Machine save error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Удалить машину
     */
    public function delete()
    {
        if (!$this->id) return false;

        try {
            $stmt = $this->db->prepare("DELETE FROM machines WHERE id = ?");
            return $stmt->execute([$this->id]);
        } catch (PDOException $e) {
            error_log("Machine delete error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Получить ВСЕ машины
     */
    public static function getAll(PDO $db)
    {
        try {
            $stmt = $db->query("
                SELECT * FROM machines 
                ORDER BY type_machine, number_machine
            ");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $machines = [];
            foreach ($rows as $row) {
                $m = new self($db);
                $m->id             = $row['id'];
                $m->type_machine   = $row['type_machine'];
                $m->number_machine = $row['number_machine'];
                $m->status         = $row['status'];
                $machines[] = $m;
            }
            return $machines;
        } catch (PDOException $e) {
            error_log("Machine getAll error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Быстрое переключение статуса (Вкл ↔ Выкл)
     */
    public function toggleStatus()
    {
        if (!$this->id) return false;
        $this->status = 1 - $this->status;
        return $this->save();
    }
}