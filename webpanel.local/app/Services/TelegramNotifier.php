<?php
namespace App\Services;

class TelegramNotifier
{
    private $token;
    private $apiUrl = 'https://api.telegram.org/bot';

    public function __construct()
    {
        $this->token = $_ENV['TG_BOT_TOKEN'] ?? $_SERVER['TG_BOT_TOKEN'] ?? getenv('TG_BOT_TOKEN') ?? '';
        $customApi = $_ENV['TG_API_URL'] ?? $_SERVER['TG_API_URL'] ?? getenv('TG_API_URL') ?? '';
        if (!empty($customApi)) {
            $this->apiUrl = rtrim($customApi, '/') . '/bot';
        }
    }

    /**
     * Отправка сообщения пользователю Telegram
     * 
     * @param string|int $chatId Telegram ID пользователя
     * @param string $message Текст сообщения (поддерживает HTML теги <b>, <i>, <code>)
     * @param string $parseMode Режим парсинга (HTML по умолчанию)
     * @return bool Успешна ли отправка
     */
    public function sendMessage($chatId, $message, $parseMode = 'HTML')
    {
        if (empty($this->token)) {
            error_log("Telegram API Error: TG_BOT_TOKEN is empty");
            return false;
        }

        if (empty($chatId)) {
            error_log("Telegram API Error: Chat ID is empty");
            return false;
        }

        $endpoints = [$this->apiUrl];
        // Если настроен forwarder-туннель внутри docker (172.18.0.1:8088), добавляем его в список попыток
        $forwarderUrl = 'http://172.18.0.1:8088/bot';
        if ($this->apiUrl !== $forwarderUrl) {
            $endpoints[] = $forwarderUrl;
        }

        $params = [
            'chat_id'    => $chatId,
            'text'       => $message,
            'parse_mode' => $parseMode,
        ];

        foreach ($endpoints as $baseEndpoint) {
            $url = $baseEndpoint . $this->token . '/sendMessage';

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 6);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                error_log("Telegram API cURL Error ({$baseEndpoint}): " . $error);
                continue;
            }

            $data = json_decode($response, true);
            if ($data && ($data['ok'] ?? false)) {
                return true;
            }

            $desc = $data['description'] ?? 'Unknown Telegram API Error';
            error_log("Telegram API Response Error ({$baseEndpoint}): " . $desc . " (chat_id: {$chatId})");
        }

        return false;
    }
}
