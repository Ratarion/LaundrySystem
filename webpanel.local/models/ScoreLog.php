<?php
namespace Models;

use PDO;
use PDOException;

/**
 * Модель логов дисциплины и баллов
 * Таблица: resident_score_logs
 */
class ScoreLog
{
    public $id;
    public $resident_id;
    public $booking_id;
    public $delta;
    public $score_after;
    public $reason;
    public $details;
    public $created_at;

    /**
     * Получить историю начислений жителя
     */
    public static function getByResident(PDO $db, $residentId, $limit = 20)
    {
        try {
            $stmt = $db->prepare("
                SELECT * FROM resident_score_logs 
                WHERE resident_id = ? 
                ORDER BY created_at DESC 
                LIMIT ?
            ");
            $stmt->bindValue(1, (int)$residentId, PDO::PARAM_INT);
            $stmt->bindValue(2, (int)$limit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("ScoreLog getByResident error: " . $e->getMessage());
            return [];
        }
    }
}
