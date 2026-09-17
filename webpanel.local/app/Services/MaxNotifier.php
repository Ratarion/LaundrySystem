<?php
namespace App\Services;

class MaxNotifier
{
    private $token;
    private $apiUrl = 'https://platform-api2.max.ru/';

    public function __construct()
    {
        $this->token = $_ENV['MAX_TOKEN'] ?? $_SERVER['MAX_TOKEN'] ?? getenv('MAX_TOKEN') ?? '';
    }

    /**
     * Отправка сообщения пользователю через платформу MAX (max.ru)
     * 
     * @param string|int $userId ID пользователя в MAX (max_id)
     * @param string $message Текст сообщения (поддерживает HTML разметку)
     * @param string $format Формат текста ('html' или 'markdown')
     * @return bool Успешна ли отправка
     */
    public function sendMessage($userId, $message, $format = 'html')
    {
        if (empty($this->token)) {
            error_log("MAX API Error: MAX_TOKEN is empty");
            return false;
        }

        if (empty($userId)) {
            error_log("MAX API Error: User ID is empty");
            return false;
        }

        $url = $this->apiUrl . 'messages?' . http_build_query([
            'user_id' => $userId,
            'disable_link_preview' => 'false',
        ]);

        $payload = [
            'text'   => $message,
            'format' => $format,
            'notify' => true,
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: ' . $this->token,
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            error_log("MAX API cURL Error: " . $error);
            return false;
        }

        if ($httpCode >= 400) {
            error_log("MAX API HTTP Error {$httpCode}: " . $response);
            return false;
        }

        $data = json_decode($response, true);
        if ($data && isset($data['success']) && $data['success'] === false) {
            error_log("MAX API Response Error: " . ($data['message'] ?? 'Unknown error'));
            return false;
        }

        return true;
    }
}
