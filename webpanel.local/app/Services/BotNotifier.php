<?php
namespace App\Services;

use PDO;

class BotNotifier
{
    private $tgNotifier;
    private $vkNotifier;
    private $maxNotifier;

    public function __construct()
    {
        $this->tgNotifier = new TelegramNotifier();
        $this->vkNotifier = new VkNotifier();
        $this->maxNotifier = new MaxNotifier();
    }

    /**
     * Отправка уведомления жильцу во все подключённые каналы (Telegram, VK, MAX)
     * 
     * @param array|object $resident Данные жильца (должны содержать tg_id, vk_id и/или max_id)
     * @param string $message Текст сообщения (может содержать HTML-теги для Telegram и MAX)
     * @return array Результаты отправки: ['tg' => bool|null, 'vk' => bool|null, 'max' => bool|null]
     */
    public function notifyResident($resident, string $message, bool $withBackBtn = false): array
    {
        $results = [
            'tg'  => null,
            'vk'  => null,
            'max' => null,
        ];

        $tgId  = is_array($resident) ? ($resident['tg_id'] ?? null) : ($resident->tg_id ?? null);
        $vkId  = is_array($resident) ? ($resident['vk_id'] ?? null) : ($resident->vk_id ?? null);
        $maxId = is_array($resident) ? ($resident['max_id'] ?? null) : ($resident->max_id ?? null);

        // 1. Отправка в Telegram
        if (!empty($tgId)) {
            $tgMarkup = null;
            if ($withBackBtn) {
                $tgMarkup = [
                    'inline_keyboard' => [
                        [
                            ['text' => '◀️ Назад', 'callback_data' => 'back_to_sections']
                        ]
                    ]
                ];
            }
            $results['tg'] = $this->tgNotifier->sendMessage($tgId, $message, 'HTML', $tgMarkup);
        }

        // 2. Отправка во ВКонтакте (предварительно удалив HTML теги)
        if (!empty($vkId)) {
            $plainText = strip_tags($message);
            $vkKeyboard = null;
            if ($withBackBtn) {
                $vkKeyboard = [
                    'inline' => true,
                    'buttons' => [
                        [
                            [
                                'action' => [
                                    'type' => 'callback',
                                    'label' => '◀️ Назад',
                                    'payload' => json_encode(['cmd' => 'back_to_sections'], JSON_UNESCAPED_UNICODE)
                                ],
                                'color' => 'secondary'
                            ]
                        ]
                    ]
                ];
            }
            $results['vk'] = $this->vkNotifier->sendMessage($vkId, $plainText, $vkKeyboard);
        }

        // 3. Отправка в MAX (поддерживает HTML разметку)
        if (!empty($maxId)) {
            $maxAttachments = null;
            if ($withBackBtn) {
                $maxAttachments = [
                    [
                        'type' => 'inline_keyboard',
                        'payload' => [
                            'buttons' => [
                                [
                                    [
                                        'type' => 'callback',
                                        'text' => '◀️ Назад',
                                        'payload' => json_encode(['cmd' => 'back_to_sections'], JSON_UNESCAPED_UNICODE)
                                    ]
                                ]
                            ]
                        ]
                    ]
                ];
            }
            $results['max'] = $this->maxNotifier->sendMessage($maxId, $message, 'html', $maxAttachments);
        }

        return $results;
    }

    /**
     * Отправка уведомления жильцу по его ID в БД
     * 
     * @param PDO $db Подключение к БД
     * @param int $residentId ID жильца в таблице residents
     * @param string $message Текст сообщения
     * @param bool $withBackBtn Добавлять ли кнопку 'Назад'
     * @return array Результаты отправки
     */
    public function notifyResidentById(PDO $db, int $residentId, string $message, bool $withBackBtn = false): array
    {
        try {
            $stmt = $db->prepare("SELECT id, tg_id, vk_id, max_id, first_name, last_name FROM residents WHERE id = ?");
            $stmt->execute([(int)$residentId]);
            $resident = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$resident) {
                error_log("BotNotifier: resident with id {$residentId} not found.");
                return ['tg' => false, 'vk' => false, 'max' => false];
            }

            return $this->notifyResident($resident, $message, $withBackBtn);
        } catch (\PDOException $e) {
            error_log("BotNotifier error fetching resident: " . $e->getMessage());
            return ['tg' => false, 'vk' => false, 'max' => false];
        }
    }
}
