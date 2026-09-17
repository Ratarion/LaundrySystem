<?php
namespace Models;

use PDO;
use PDOException;

/**
 * Модель администратора / техника (Active Record)
 * Таблица: administrators
 * Используется для входа в систему
 */
class Administrator
{
    // Поля таблицы
    public $id;
    public $username;
    public $password_hash;
    public $role;             // 1 = Администратор, 2 = Техник
    public $dormitory_id;     // NULL = все общежития (Председатель), число = конкретное общежитие
    public $dormitory_name;   // Название общежития из JOIN

    private $db;              // Объект PDO

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
    * Установить и сразу захешировать пароль
    */
    public function setPassword($password) 
    {
        $this->password_hash = password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * Загрузить администратора по ID
     */
    public function load($id)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT a.*, d.name AS dormitory_name 
                FROM administrators a
                LEFT JOIN dormitories d ON a.dormitory_id = d.id
                WHERE a.id = ?
            ");
            $stmt->execute([(int)$id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($data) {
                $this->id             = $data['id'];
                $this->username       = $data['username'];
                $this->password_hash  = $data['password_hash'];
                $this->role           = (int)$data['role'];
                $this->dormitory_id   = !empty($data['dormitory_id']) ? (int)$data['dormitory_id'] : null;
                $this->dormitory_name = $data['dormitory_name'] ?? null;
                return true;
            }
            return false;
        } catch (PDOException $e) {
            error_log("Administrator load error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Сохранить (INSERT или UPDATE)
     * password_hash нужно передавать уже захешированным!
     */
    public function save()
    {
        try {
            $dormId = !empty($this->dormitory_id) ? (int)$this->dormitory_id : null;

            if ($this->id) {
                // UPDATE
                $stmt = $this->db->prepare("
                    UPDATE administrators 
                    SET username = ?, 
                        password_hash = ?, 
                        role = ?,
                        dormitory_id = ? 
                    WHERE id = ?
                ");
                return $stmt->execute([
                    $this->username,
                    $this->password_hash,
                    (int)$this->role,
                    $dormId,
                    $this->id
                ]);
            } else {
                // INSERT
                $stmt = $this->db->prepare("
                    INSERT INTO administrators 
                    (username, password_hash, role, dormitory_id)
                    VALUES (?, ?, ?, ?)
                ");
                $result = $stmt->execute([
                    $this->username,
                    $this->password_hash,
                    (int)$this->role,
                    $dormId
                ]);

                if ($result) {
                    $this->id = (int)$this->db->lastInsertId();
                }
                return $result;
            }
        } catch (PDOException $e) {
            error_log("Administrator save error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Удалить администратора
     */
    public function delete()
    {
        if (!$this->id) return false;

        try {
            $stmt = $this->db->prepare("DELETE FROM administrators WHERE id = ?");
            return $stmt->execute([$this->id]);
        } catch (PDOException $e) {
            error_log("Administrator delete error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Получить ВСЕХ администраторов / техников
     */
    public static function getAll(PDO $db)
    {
        try {
            $stmt = $db->query("
                SELECT a.*, d.name AS dormitory_name 
                FROM administrators a
                LEFT JOIN dormitories d ON a.dormitory_id = d.id
                ORDER BY a.role ASC, a.dormitory_id ASC NULLS FIRST, a.username ASC
            ");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $admins = [];
            foreach ($rows as $row) {
                $a = new self($db);
                $a->id             = (int)$row['id'];
                $a->username       = $row['username'];
                $a->password_hash  = $row['password_hash'];
                $a->role           = (int)$row['role'];
                $a->dormitory_id   = !empty($row['dormitory_id']) ? (int)$row['dormitory_id'] : null;
                $a->dormitory_name = $row['dormitory_name'] ?? null;
                $admins[] = $a;
            }
            return $admins;
        } catch (PDOException $e) {
            error_log("Administrator getAll error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Найти по логину (используется при входе)
     * Возвращает объект Administrator или false
     */
    public static function findByUsername(PDO $db, string $username)
    {
        try {
            $stmt = $db->prepare("
                SELECT a.*, d.name AS dormitory_name 
                FROM administrators a
                LEFT JOIN dormitories d ON a.dormitory_id = d.id
                WHERE a.username = ?
            ");
            $stmt->execute([$username]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($data) {
                $admin = new self($db);
                $admin->id             = (int)$data['id'];
                $admin->username       = $data['username'];
                $admin->password_hash  = $data['password_hash'];
                $admin->role           = (int)$data['role'];
                $admin->dormitory_id   = !empty($data['dormitory_id']) ? (int)$data['dormitory_id'] : null;
                $admin->dormitory_name = $data['dormitory_name'] ?? null;
                return $admin;
            }
            return false;
        } catch (PDOException $e) {
            error_log("Administrator findByUsername error: " . $e->getMessage());
            return false;
        }
    }
}