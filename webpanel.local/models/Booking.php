<?php
namespace Models;

use PDO;
use PDOException;

/**
 * Модель бронирования (Active Record)
 * Таблица: booking
 * Содержит JOIN с residents, machines и dormitories
 */
class Booking
{
    // Поля таблицы booking
    public $id;
    public $dormitory_id;
    public $start_time;
    public $end_time;
    public $status;          // 'Ожидание', 'Ожидание подтверждения', 'Подтверждено', 'Отменено'
    public $inidmachine;
    public $inidresidents;

    // Дополнительные поля из JOIN
    public $dormitory_name;
    public $resident_name;   // Фамилия Имя
    public $inidroom;
    public $type_machine;
    public $number_machine;
    public $vk_id;
    public $tg_id;
    public $max_id;

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
                       r.last_name, r.first_name, r.inidroom, r.vk_id, r.tg_id, r.max_id,
                       m.type_machine, m.number_machine,
                       d.name AS dormitory_name
                FROM booking b
                JOIN residents r ON b.inidresidents = r.id
                JOIN machines m ON b.inidmachine = m.id
                LEFT JOIN dormitories d ON b.dormitory_id = d.id
                WHERE b.id = ?
            ");
            $stmt->execute([(int)$id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($data) {
                $this->id              = (int)$data['id'];
                $this->dormitory_id    = !empty($data['dormitory_id']) ? (int)$data['dormitory_id'] : 1;
                $this->start_time      = $data['start_time'];
                $this->end_time        = $data['end_time'];
                $this->status          = $data['status'];
                $this->inidmachine     = $data['inidmachine'];
                $this->inidresidents   = $data['inidresidents'];

                $this->resident_name   = $data['last_name'] . ' ' . $data['first_name'];
                $this->inidroom        = $data['inidroom'];
                $this->type_machine    = $data['type_machine'];
                $this->number_machine  = $data['number_machine'];
                $this->vk_id           = $data['vk_id'];
                $this->tg_id           = $data['tg_id'];
                $this->max_id          = $data['max_id'];
                $this->dormitory_name  = $data['dormitory_name'] ?? ('Общежитие №' . $this->dormitory_id);
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
            $dormId = !empty($this->dormitory_id) ? (int)$this->dormitory_id : 1;

            if ($this->id) {
                // UPDATE
                $stmt = $this->db->prepare("
                    UPDATE booking 
                    SET dormitory_id = ?,
                        start_time = ?, 
                        end_time = ?, 
                        status = ?,
                        inidmachine = ?,
                        inidresidents = ?
                    WHERE id = ?
                ");
                return $stmt->execute([
                    $dormId,
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
                    INSERT INTO booking (dormitory_id, start_time, end_time, status, inidmachine, inidresidents)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $result = $stmt->execute([
                    $dormId,
                    $this->start_time,
                    $this->end_time,
                    $this->status ?? 'Ожидание',
                    $this->inidmachine,
                    $this->inidresidents
                ]);

                if ($result) {
                    $this->id = (int)$this->db->lastInsertId();
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
     * Получить ВСЕ бронирования с фильтрами (дата, статус, общежитие, машинка)
     */
    public static function getAll(PDO $db, $date_from = null, $date_to = null, $status = null, $dormitoryId = null, $machineId = null, $fio = null)
    {
        try {
            $sql = "
                SELECT b.id, b.dormitory_id, b.start_time, b.end_time, b.status,
                       r.id AS resident_id, r.last_name, r.first_name, r.patronymic, r.inidroom,
                       r.score, r.confirm_streak, r.miss_streak, r.tg_id, r.vk_id, r.max_id,
                       m.id AS machine_id, m.type_machine, m.number_machine,
                       d.name AS dormitory_name
                FROM booking b
                JOIN residents r ON b.inidresidents = r.id
                JOIN machines m ON b.inidmachine = m.id
                LEFT JOIN dormitories d ON b.dormitory_id = d.id
                WHERE 1=1
            ";
            $params = [];

            if ($date_from && $date_to) {
                if ($date_from === $date_to) {
                    $sql .= " AND DATE(b.start_time) = ?";
                    $params[] = $date_from;
                } else {
                    $sql .= " AND DATE(b.start_time) BETWEEN ? AND ?";
                    $params[] = $date_from;
                    $params[] = $date_to;
                }
            } elseif ($date_from) {
                $sql .= " AND DATE(b.start_time) >= ?";
                $params[] = $date_from;
            } elseif ($date_to) {
                $sql .= " AND DATE(b.start_time) <= ?";
                $params[] = $date_to;
            }

            if ($status) {
                $sql .= " AND b.status = ?";
                $params[] = $status;
            }

            if (!empty($dormitoryId)) {
                $sql .= " AND b.dormitory_id = ?";
                $params[] = (int)$dormitoryId;
            }

            if (!empty($machineId)) {
                $sql .= " AND b.inidmachine = ?";
                $params[] = (int)$machineId;
            }

            if (!empty($fio)) {
                $fioClean = trim($fio);
                $fioWild  = '%' . $fioClean . '%';
                $sql .= " AND (
                    CONCAT(r.last_name, ' ', r.first_name, ' ', COALESCE(r.patronymic, '')) ILIKE ?
                    OR CONCAT(r.first_name, ' ', r.last_name) ILIKE ?
                    OR r.last_name ILIKE ?
                    OR r.first_name ILIKE ?
                    OR r.patronymic ILIKE ?
                )";
                $params[] = $fioWild;
                $params[] = $fioWild;
                $params[] = $fioWild;
                $params[] = $fioWild;
                $params[] = $fioWild;
            }

            $sql .= " ORDER BY b.start_time ASC, b.id ASC";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Booking getAll error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Получить список бронирований, которые будут затронуты массовой отменой
     */
    public static function getAffectedByMassCancel(PDO $db, $date, $type_machine, $dormitoryId = null)
    {
        try {
            $sql = "
                SELECT b.id, b.start_time, b.end_time, b.inidresidents, b.inidmachine, m.number_machine, m.type_machine, r.vk_id, r.tg_id, r.max_id, r.first_name,
                       d.name AS dormitory_name
                FROM booking b
                JOIN machines m ON b.inidmachine = m.id
                JOIN residents r ON b.inidresidents = r.id
                LEFT JOIN dormitories d ON b.dormitory_id = d.id
                WHERE b.start_time::date = ?
                  AND m.type_machine = ?
                  AND b.status != 'Отменено'
            ";
            $params = [$date, $type_machine];

            if (!empty($dormitoryId)) {
                $sql .= " AND b.dormitory_id = ?";
                $params[] = (int)$dormitoryId;
            }

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Booking getAffectedByMassCancel error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Массовая отмена по дате, типу машины и общежитию
     */
    public static function massCancel(PDO $db, $date, $type_machine, $dormitoryId = null)
    {
        try {
            $sql = "
                UPDATE booking
                SET status = 'Отменено'
                FROM machines
                WHERE booking.inidmachine = machines.id
                  AND booking.start_time::date = ?
                  AND machines.type_machine = ?
            ";
            $params = [$date, $type_machine];

            if (!empty($dormitoryId)) {
                $sql .= " AND booking.dormitory_id = ?";
                $params[] = (int)$dormitoryId;
            }

            $stmt = $db->prepare($sql);
            return $stmt->execute($params);
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