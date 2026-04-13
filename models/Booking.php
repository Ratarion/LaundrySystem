<?php
namespace Models;

use PDO;
use PDOException;

/**
 * Модель бронирования (Active Record)
 * Таблица: booking
 * Содержит JOIN с residents и machines для удобного вывода
 */
class Booking
{
    // Поля таблицы booking
    public $id;
    public $start_time;
    public $end_time;
    public $status;
    public $inidmachine;
    public $inidresidents;

    // Дополнительные поля из JOIN (для удобства)
    public $resident_name;   // Фамилия Имя
    public $inidroom;
    public $type_machine;
    public $number_machine;

    private $db;              // Объект PDO

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Загрузить одно бронирование по ID
     */
    public function load($id)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT b.*, 
                       r.last_name, r.first_name, r.inidroom,
                       m.type_machine, m.number_machine
                FROM booking b
                JOIN residents r ON b.inidresidents = r.id
                JOIN machines m ON b.inidmachine = m.id
                WHERE b.id = ?
            ");
            $stmt->execute([(int)$id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($data) {
                $this->id              = $data['id'];
                $this->start_time      = $data['start_time'];
                $this->end_time        = $data['end_time'];
                $this->status          = $data['status'];
                $this->inidmachine     = $data['inidmachine'];
                $this->inidresidents   = $data['inidresidents'];

                $this->resident_name   = $data['last_name'] . ' ' . $data['first_name'];
                $this->inidroom        = $data['inidroom'];
                $this->type_machine    = $data['type_machine'];
                $this->number_machine  = $data['number_machine'];
                return true;
            }
            return false;
        } catch (PDOException $e) {
            error_log("Booking load error: " . $e->getMessage());
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
                    UPDATE booking 
                    SET start_time = ?, 
                        end_time = ?, 
                        status = ?,
                        inidmachine = ?,
                        inidresidents = ?
                    WHERE id = ?
                ");
                return $stmt->execute([
                    $this->start_time,
                    $this->end_time,
                    $this->status,
                    $this->inidmachine,
                    $this->inidresidents,
                    $this->id
                ]);
            } else {
                // INSERT
                $stmt = $this->db->prepare("
                    INSERT INTO booking 
                    (start_time, end_time, status, inidmachine, inidresidents)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $result = $stmt->execute([
                    $this->start_time,
                    $this->end_time,
                    $this->status ?? 'Ожидание',
                    $this->inidmachine,
                    $this->inidresidents
                ]);

                if ($result) {
                    $this->id = $this->db->lastInsertId();
                }
                return $result;
            }
        } catch (PDOException $e) {
            error_log("Booking save error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Удалить бронирование
     */
    public function delete()
    {
        if (!$this->id) return false;

        try {
            $stmt = $this->db->prepare("DELETE FROM booking WHERE id = ?");
            return $stmt->execute([$this->id]);
        } catch (PDOException $e) {
            error_log("Booking delete error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Получить ВСЕ бронирования с фильтрами (используется на главной странице)
     */
    public static function getAll(PDO $db, $date_from = null, $date_to = null, $status = null)
    {
        try {
            $sql = "
                SELECT b.id, b.start_time, b.end_time, b.status,
                       r.last_name, r.first_name, r.inidroom,
                       m.type_machine, m.number_machine
                FROM booking b
                JOIN residents r ON b.inidresidents = r.id
                JOIN machines m ON b.inidmachine = m.id
                WHERE 1=1
            ";
            $params = [];

            if ($date_from && $date_to) {
                $sql .= " AND DATE(b.start_time) BETWEEN ? AND ?";
                $params[] = $date_from;
                $params[] = $date_to;
            }

            if ($status) {
                $sql .= " AND b.status = ?";
                $params[] = $status;
            }

            $sql .= " ORDER BY b.start_time DESC";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Booking getAll error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Массовая отмена (как в твоём booking.php)
     */
    public static function massCancel(PDO $db, $date, $type_machine)
    {
        try {
            $stmt = $db->prepare("
                UPDATE booking
                SET status = 'Отменено'
                FROM machines
                WHERE booking.inidmachine = machines.id
                  AND booking.start_time::date = ?
                  AND machines.type_machine = ?
            ");
            return $stmt->execute([$date, $type_machine]);
        } catch (PDOException $e) {
            error_log("Booking massCancel error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Отмена одной записи (для админа)
     */
    public static function cancelOne(PDO $db, $id)
    {
        try {
            $stmt = $db->prepare("UPDATE booking SET status = 'Отменено' WHERE id = ?");
            return $stmt->execute([(int)$id]);
        } catch (PDOException $e) {
            error_log("Booking cancelOne error: " . $e->getMessage());
            return false;
        }
    }
}