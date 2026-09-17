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
    public $dormitory_id;     // ID общежития
    public $type_machine;     // "Стиральная" или "Сушильная"
    public $number_machine;   // "#5", "3 этаж" и т.д.
    public $status;           // 1 = работает, 0 = отключена

    // Дополнительные поля из JOIN
    public $dormitory_name;

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
            $stmt = $this->db->prepare("
                SELECT m.*, d.name AS dormitory_name 
                FROM machines m
                LEFT JOIN dormitories d ON m.dormitory_id = d.id
                WHERE m.id = ?
            ");
            $stmt->execute([(int)$id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($data) {
                $this->id             = (int)$data['id'];
                $this->dormitory_id   = !empty($data['dormitory_id']) ? (int)$data['dormitory_id'] : 1;
                $this->type_machine   = $data['type_machine'];
                $this->number_machine = $data['number_machine'];
                $this->status         = (int)$data['status'];
                $this->dormitory_name = $data['dormitory_name'] ?? ('Общежитие №' . $this->dormitory_id);
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
            $dormId = !empty($this->dormitory_id) ? (int)$this->dormitory_id : 1;

            if ($this->id) {
                // UPDATE
                $stmt = $this->db->prepare("
                    UPDATE machines 
                    SET dormitory_id = ?,
                        type_machine = ?, 
                        number_machine = ?, 
                        status = ? 
                    WHERE id = ?
                ");
                return $stmt->execute([
                    $dormId,
                    $this->type_machine,
                    $this->number_machine,
                    (int)$this->status,
                    $this->id
                ]);
            } else {
                // INSERT
                $stmt = $this->db->prepare("
                    INSERT INTO machines (dormitory_id, type_machine, number_machine, status)
                    VALUES (?, ?, ?, ?)
                ");
                $result = $stmt->execute([
                    $dormId,
                    $this->type_machine,
                    $this->number_machine,
                    (int)$this->status
                ]);

                if ($result) {
                    $this->id = (int)$this->db->lastInsertId();
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
     * Получить машины (с опциональной фильтрацией по общежитию)
     */
    public static function getAll(PDO $db, $dormitoryId = null)
    {
        try {
            $sql = "
                SELECT m.*, d.name AS dormitory_name 
                FROM machines m
                LEFT JOIN dormitories d ON m.dormitory_id = d.id
            ";
            $params = [];

            if (!empty($dormitoryId)) {
                $sql .= " WHERE m.dormitory_id = ?";
                $params[] = (int)$dormitoryId;
            }

            $sql .= " ORDER BY d.number ASC, m.type_machine, m.number_machine";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $machines = [];
            foreach ($rows as $row) {
                $m = new self($db);
                $m->id             = (int)$row['id'];
                $m->dormitory_id   = !empty($row['dormitory_id']) ? (int)$row['dormitory_id'] : 1;
                $m->type_machine   = $row['type_machine'];
                $m->number_machine = $row['number_machine'];
                $m->status         = (int)$row['status'];
                $m->dormitory_name = $row['dormitory_name'] ?? ('Общежитие №' . $m->dormitory_id);
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