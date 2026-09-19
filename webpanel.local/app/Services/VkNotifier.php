<?php
namespace App\Services;

class VkNotifier
{
    private $token;
    private $groupId;
    private $apiUrl = 'https://api.vk.com/method/';
    private $version = '5.199';

    public function __construct()
    {
        $this->token = $_ENV['VK_TOKEN'] ?? $_SERVER['VK_TOKEN'] ?? getenv('VK_TOKEN') ?? '';
        $this->groupId = $_ENV['VK_GROUP_ID'] ?? $_SERVER['VK_GROUP_ID'] ?? getenv('VK_GROUP_ID') ?? '';
    }

    /**
     * Отправка сообщения пользователю
     * 
     * @param string|int $userId ID пользователя ВКонтакте
     * @param string $message Текст сообщения
     * @return bool Успешна ли отправка
     */
    public function sendMessage($userId, $message, $keyboard = null)
    {
        if (empty($this->token) || empty($userId)) {
            error_log("VK API Error: Token or UserID is empty");
            return false;
        }

        $params = [
            'user_id' => $userId,
            'message' => $message,
            'random_id' => random_int(1, 2147483647),
            'access_token' => $this->token,
            'v' => $this->version
        ];

        if (!empty($keyboard)) {
            $params['keyboard'] = is_array($keyboard) ? json_encode($keyboard, JSON_UNESCAPED_UNICODE) : $keyboard;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl . 'messages.send');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("VK API cURL Error: " . $error);
            return false;
        }

        $data = json_decode($response, true);
        
        if (isset($data['error'])) {
            error_log("VK API Response Error: " . json_encode($data['error'], JSON_UNESCAPED_UNICODE));
            return false;
        }

        return true;
    }
}
