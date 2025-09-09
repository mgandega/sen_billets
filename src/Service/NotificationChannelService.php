<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\Event;
use App\Entity\Ticket;
use App\Entity\Payment;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\NotificationTemplateManager;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class NotificationChannelService
{
    private const FCM_URL = 'https://fcm.googleapis.com/fcm/send';
    private const ORANGE_SMS_URL = 'https://api.orange.com/smsmessaging/v1';
    private const TWILIO_SMS_URL = 'https://api.twilio.com/2010-04-01';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private HttpClientInterface $httpClient,
        private NotificationTemplateManager $templateManager,
        private ?LoggerInterface $logger = null,
        private ?string $fcmServerKey = null,
        private ?string $orangeSmsApiKey = null,
        private ?string $twilioAccountSid = null,
        private ?string $twilioAuthToken = null,
        private ?string $twilioPhoneNumber = null
    ) {}

    /**
     * Envoyer une notification push via Firebase Cloud Messaging
     */
    public function sendPushNotification(User $user, array $data): bool
    {
        try {
            // Récupérer le token FCM de l'utilisateur
            $fcmToken = $this->getUserFCMToken($user);
            if (!$fcmToken) {
                $this->logger->warning('No FCM token found for user', ['user_id' => $user->getId()]);
                return false;
            }

            $payload = [
                'to' => $fcmToken,
                'notification' => [
                    'title' => $data['title'],
                    'body' => $data['body'],
                    'icon' => $data['icon'] ?? '/assets/images/logo-192.png',
                    'badge' => '/assets/images/badge-72.png',
                    'click_action' => $data['click_action'] ?? 'https://sen-billets.sn',
                    'sound' => 'default'
                ],
                'data' => $data['custom_data'] ?? [],
                'android' => [
                    'notification' => [
                        'channel_id' => 'sen_billets_notifications',
                        'priority' => 'high'
                    ]
                ],
                'apns' => [
                    'payload' => [
                        'aps' => [
                            'alert' => [
                                'title' => $data['title'],
                                'body' => $data['body']
                            ],
                            'badge' => 1,
                            'sound' => 'default'
                        ]
                    ]
                ]
            ];

            $response = $this->httpClient->request('POST', self::FCM_URL, [
                'headers' => [
                    'Authorization' => 'key=' . $this->fcmServerKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload
            ]);

            $result = $response->toArray();
            
            if ($result['success'] > 0) {
                $this->logger->info('Push notification sent successfully', [
                    'user_id' => $user->getId(),
                    'title' => $data['title']
                ]);
                return true;
            } else {
                $this->logger->error('Push notification failed', [
                    'user_id' => $user->getId(),
                    'error' => $result['results'][0]['error'] ?? 'Unknown error'
                ]);
                return false;
            }

        } catch (\Exception $e) {
            $this->logger->error('Push notification exception', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Envoyer un SMS via Orange Money API
     */
    public function sendSMSOrange(User $user, string $message): bool
    {
        if (!$user->getPhone()) {
            return false;
        }

        try {
            $response = $this->httpClient->request('POST', self::ORANGE_SMS_URL . '/outbound/tel%3A%2B221XXXXXXXX/requests', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->orangeSmsApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'outboundSMSMessageRequest' => [
                        'address' => 'tel:' . $user->getPhone(),
                        'senderAddress' => 'tel:+221XXXXXXXX',
                        'outboundSMSTextMessage' => [
                            'message' => $message
                        ]
                    ]
                ]
            ]);

            return $response->getStatusCode() === 201;

        } catch (\Exception $e) {
            $this->logger->error('Orange SMS failed', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Envoyer un SMS via Twilio (alternative)
     */
    public function sendSMSTwilio(User $user, string $message): bool
    {
        if (!$user->getPhone()) {
            return false;
        }

        try {
            $response = $this->httpClient->request('POST', self::TWILIO_SMS_URL . '/Accounts/' . $this->twilioAccountSid . '/Messages.json', [
                'auth_basic' => [$this->twilioAccountSid, $this->twilioAuthToken],
                'body' => [
                    'From' => $this->twilioPhoneNumber,
                    'To' => $user->getPhone(),
                    'Body' => $message
                ]
            ]);

            return $response->getStatusCode() === 201;

        } catch (\Exception $e) {
            $this->logger->error('Twilio SMS failed', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Envoyer une notification WhatsApp Business
     */
    public function sendWhatsAppMessage(User $user, array $templateData): bool
    {
        if (!$user->getPhone()) {
            return false;
        }

        try {
            // Utiliser l'API WhatsApp Business
            $response = $this->httpClient->request('POST', 'https://graph.facebook.com/v17.0/YOUR_PHONE_NUMBER_ID/messages', [
                'headers' => [
                    'Authorization' => 'Bearer YOUR_WHATSAPP_ACCESS_TOKEN',
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'messaging_product' => 'whatsapp',
                    'to' => $user->getPhone(),
                    'type' => 'template',
                    'template' => [
                        'name' => $templateData['template_name'],
                        'language' => ['code' => 'fr'],
                        'components' => $templateData['components'] ?? []
                    ]
                ]
            ]);

            return $response->getStatusCode() === 200;

        } catch (\Exception $e) {
            $this->logger->error('WhatsApp message failed', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Envoyer une notification Slack (pour les organisateurs)
     */
    public function sendSlackNotification(string $webhookUrl, array $data): bool
    {
        try {
            $payload = [
                'text' => $data['text'],
                'username' => 'Sen-Billets',
                'icon_emoji' => ':ticket:',
                'attachments' => [
                    [
                        'color' => $data['color'] ?? 'good',
                        'title' => $data['title'],
                        'text' => $data['message'],
                        'fields' => $data['fields'] ?? [],
                        'footer' => 'Sen-Billets',
                        'ts' => time()
                    ]
                ]
            ];

            $response = $this->httpClient->request('POST', $webhookUrl, [
                'json' => $payload
            ]);

            return $response->getStatusCode() === 200;

        } catch (\Exception $e) {
            $this->logger->error('Slack notification failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Envoyer une notification Discord
     */
    public function sendDiscordNotification(string $webhookUrl, array $data): bool
    {
        try {
            $payload = [
                'username' => 'Sen-Billets',
                'avatar_url' => 'https://sen-billets.sn/assets/images/logo.png',
                'embeds' => [
                    [
                        'title' => $data['title'],
                        'description' => $data['message'],
                        'color' => hexdec($data['color'] ?? '0099ff'),
                        'timestamp' => (new \DateTime())->format('c'),
                        'footer' => [
                            'text' => 'Sen-Billets',
                            'icon_url' => 'https://sen-billets.sn/assets/images/logo-32.png'
                        ],
                        'fields' => $data['fields'] ?? []
                    ]
                ]
            ];

            $response = $this->httpClient->request('POST', $webhookUrl, [
                'json' => $payload
            ]);

            return $response->getStatusCode() === 204;

        } catch (\Exception $e) {
            $this->logger->error('Discord notification failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Récupérer le token FCM de l'utilisateur
     */
    private function getUserFCMToken(User $user): ?string
    {
        // Ici vous devriez récupérer le token FCM depuis la base de données
        // Pour l'exemple, on suppose qu'il y a une table user_tokens
        $connection = $this->entityManager->getConnection();
        $sql = 'SELECT fcm_token FROM user_tokens WHERE user_id = :user_id AND is_active = 1 ORDER BY created_at DESC LIMIT 1';
        $stmt = $connection->prepare($sql);
        $result = $stmt->executeQuery(['user_id' => $user->getId()]);
        
        return $result->fetchOne() ?: null;
    }

    /**
     * Sauvegarder le token FCM de l'utilisateur
     */
    public function saveUserFCMToken(User $user, string $token): void
    {
        $connection = $this->entityManager->getConnection();
        
        // Désactiver les anciens tokens
        $sql = 'UPDATE user_tokens SET is_active = 0 WHERE user_id = :user_id';
        $connection->executeStatement($sql, ['user_id' => $user->getId()]);
        
        // Ajouter le nouveau token
        $sql = 'INSERT INTO user_tokens (user_id, fcm_token, is_active, created_at) VALUES (:user_id, :token, 1, NOW())';
        $connection->executeStatement($sql, [
            'user_id' => $user->getId(),
            'token' => $token
        ]);
    }

    /**
     * Récupérer un template SMS
     */
    public function getSMSTemplate(string $type, array $data = []): string
    {
        return $this->templateManager->getSMSTemplate($type, $data);
    }

    /**
     * Envoyer une notification à un groupe d'utilisateurs
     */
    public function sendBulkNotification(array $users, array $data, array $channels = ['push']): array
    {
        $results = [
            'success' => 0,
            'failed' => 0,
            'details' => []
        ];

        foreach ($users as $user) {
            $userResult = ['user_id' => $user->getId(), 'channels' => []];
            
            foreach ($channels as $channel) {
                switch ($channel) {
                    case 'push':
                        $success = $this->sendPushNotification($user, $data);
                        break;
                    case 'sms':
                        $success = $this->sendSMSOrange($user, $data['sms_message'] ?? $data['body']);
                        break;
                    case 'whatsapp':
                        $success = $this->sendWhatsAppMessage($user, $data['whatsapp_template'] ?? []);
                        break;
                    default:
                        $success = false;
                }
                
                $userResult['channels'][$channel] = $success;
                
                if ($success) {
                    $results['success']++;
                } else {
                    $results['failed']++;
                }
            }
            
            $results['details'][] = $userResult;
        }

        return $results;
    }
}