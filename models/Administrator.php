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

    private $db;              // Объект PDO

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Загрузить администратора по ID
     */
    public function load($id)
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM administrators WHERE id = ?");
            $stmt->execute([(int)$id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($data) {
                $this->id            = $data['id'];
                $this->username      = $data['username'];
                $this->password_hash = $data['password_hash'];
                $this->role          = (int)$data['role'];
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
            if ($this->id) {
                // UPDATE
                $stmt = $this->db->prepare("
                    UPDATE administrators 
                    SET username = ?, 
                        password_hash = ?, 
                        role = ? 
                    WHERE id = ?
                ");
                return $stmt->execute([
                    $this->username,
                    $this->password_hash,
                    (int)$this->role,
                    $this->id
                ]);
            } else {
                // INSERT
                $stmt = $this->db->prepare("
                    INSERT INTO administrators 
                    (username, password_hash, role)
                    VALUES (?, ?, ?)
                ");
                $result = $stmt->execute([
                    $this->username,
                    $this->password_hash,
                    (int)$this->role
                ]);

                if ($result) {
                    $this->id = $this->db->lastInsertId();
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
                SELECT * FROM administrators 
                ORDER BY role DESC, username
            ");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $admins = [];
            foreach ($rows as $row) {
                $a = new self($db);
                $a->id            = $row['id'];
                $a->username      = $row['username'];
                $a->password_hash = $row['password_hash'];
                $a->role          = (int)$row['role'];
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
            $stmt = $db->prepare("SELECT * FROM administrators WHERE username = ?");
            $stmt->execute([$username]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($data) {
                $admin = new self($db);
                $admin->id            = $data['id'];
                $admin->username      = $data['username'];
                $admin->password_hash = $data['password_hash'];
                $admin->role          = (int)$data['role'];
                return $admin;
            }
            return false;
        } catch (PDOException $e) {
            error_log("Administrator findByUsername error: " . $e->getMessage());
            return false;
        }
    }
}