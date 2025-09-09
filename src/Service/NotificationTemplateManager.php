<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\Event;
use App\Entity\Ticket;
use App\Entity\Payment;

class NotificationTemplateManager
{
    /**
     * Templates pour les notifications push
     */
    public function getPushTemplate(string $type, array $data = []): array
    {
        $templates = [
            'ticket_purchase' => [
                'title' => '🎫 Billets confirmés !',
                'body' => "Vos billets pour {$data['event_title']} sont prêts",
                'icon' => '/assets/images/ticket-icon.png',
                'click_action' => '/tickets/my-tickets'
            ],
            'event_reminder_24h' => [
                'title' => '⏰ Événement demain !',
                'body' => "{$data['event_title']} commence dans 24h",
                'icon' => '/assets/images/reminder-icon.png',
                'click_action' => "/events/{$data['event_id']}"
            ],
            'event_reminder_2h' => [
                'title' => '🚨 Événement bientôt !',
                'body' => "{$data['event_title']} commence dans 2h",
                'icon' => '/assets/images/urgent-icon.png',
                'click_action' => "/events/{$data['event_id']}"
            ],
            'payment_success' => [
                'title' => '✅ Paiement confirmé',
                'body' => "Paiement de {$data['amount']} traité avec succès",
                'icon' => '/assets/images/payment-icon.png',
                'click_action' => '/payment/history'
            ],
            'event_cancelled' => [
                'title' => '❌ Événement annulé',
                'body' => "{$data['event_title']} a été annulé. Remboursement en cours.",
                'icon' => '/assets/images/cancel-icon.png',
                'click_action' => "/events/{$data['event_id']}"
            ],
            'new_event_recommendation' => [
                'title' => '🎯 Nouvel événement pour vous',
                'body' => "Découvrez {$data['event_title']} - {$data['category']}",
                'icon' => '/assets/images/recommendation-icon.png',
                'click_action' => "/events/{$data['event_id']}"
            ],
            'organizer_new_sale' => [
                'title' => '🎉 Nouvelle vente !',
                'body' => "Billet vendu pour {$data['event_title']}",
                'icon' => '/assets/images/sale-icon.png',
                'click_action' => '/admin/dashboard'
            ],
            'organizer_milestone' => [
                'title' => '🏆 Objectif atteint !',
                'body' => "{$data['milestone']} billets vendus pour {$data['event_title']}",
                'icon' => '/assets/images/milestone-icon.png',
                'click_action' => "/analytics/event/{$data['event_id']}"
            ]
        ];

        return $templates[$type] ?? [
            'title' => 'Sen-Billets',
            'body' => 'Nouvelle notification',
            'icon' => '/assets/images/logo-192.png',
            'click_action' => '/'
        ];
    }

    /**
     * Templates pour les SMS
     */
    public function getSMSTemplate(string $type, array $data = []): string
    {
        $templates = [
            'ticket_purchase' => "🎫 Sen-Billets: Billets confirmés pour {$data['event_title']}! Code: {$data['qr_code']}. Détails: sen-billets.sn",
            'event_reminder_24h' => "⏰ Sen-Billets: {$data['event_title']} commence demain à {$data['time']}. Lieu: {$data['venue']}",
            'event_reminder_2h' => "🚨 Sen-Billets: {$data['event_title']} commence dans 2h! N'oubliez pas vos billets.",
            'payment_success' => "✅ Sen-Billets: Paiement de {$data['amount']} confirmé. Merci!",
            'event_cancelled' => "❌ Sen-Billets: {$data['event_title']} annulé. Remboursement automatique. Info: sen-billets.sn",
            'verification_code' => "🔐 Sen-Billets: Votre code de vérification est {$data['code']}. Valide 10 minutes.",
            'password_reset' => "🔑 Sen-Billets: Réinitialisation mot de passe. Code: {$data['code']}. Si ce n'est pas vous, ignorez ce SMS."
        ];

        return $templates[$type] ?? "Sen-Billets: Nouvelle notification. Consultez votre compte: sen-billets.sn";
    }

    /**
     * Templates pour WhatsApp Business
     */
    public function getWhatsAppTemplate(string $type, array $data = []): array
    {
        $templates = [
            'ticket_purchase' => [
                'template_name' => 'ticket_confirmation',
                'components' => [
                    [
                        'type' => 'header',
                        'parameters' => [
                            ['type' => 'text', 'text' => $data['event_title']]
                        ]
                    ],
                    [
                        'type' => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => $data['customer_name']],
                            ['type' => 'text', 'text' => $data['ticket_type']],
                            ['type' => 'text', 'text' => $data['qr_code']]
                        ]
                    ]
                ]
            ],
            'event_reminder' => [
                'template_name' => 'event_reminder',
                'components' => [
                    [
                        'type' => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => $data['event_title']],
                            ['type' => 'text', 'text' => $data['time_until']],
                            ['type' => 'text', 'text' => $data['venue']]
                        ]
                    ]
                ]
            ]
        ];

        return $templates[$type] ?? [];
    }

    /**
     * Templates pour Slack (organisateurs)
     */
    public function getSlackTemplate(string $type, array $data = []): array
    {
        $templates = [
            'new_sale' => [
                'text' => 'Nouvelle vente sur Sen-Billets !',
                'title' => 'Vente confirmée',
                'message' => "Un billet a été vendu pour l'événement *{$data['event_title']}*",
                'color' => 'good',
                'fields' => [
                    [
                        'title' => 'Client',
                        'value' => $data['customer_name'],
                        'short' => true
                    ],
                    [
                        'title' => 'Type de billet',
                        'value' => $data['ticket_type'],
                        'short' => true
                    ],
                    [
                        'title' => 'Montant',
                        'value' => $data['amount'],
                        'short' => true
                    ],
                    [
                        'title' => 'Total vendus',
                        'value' => $data['total_sold'],
                        'short' => true
                    ]
                ]
            ],
            'event_milestone' => [
                'text' => 'Objectif atteint !',
                'title' => 'Milestone atteint',
                'message' => "L'événement *{$data['event_title']}* a atteint {$data['milestone']} billets vendus !",
                'color' => 'warning',
                'fields' => [
                    [
                        'title' => 'Taux de remplissage',
                        'value' => $data['occupancy_rate'] . '%',
                        'short' => true
                    ],
                    [
                        'title' => 'Revenus',
                        'value' => $data['revenue'],
                        'short' => true
                    ]
                ]
            ],
            'low_stock_alert' => [
                'text' => 'Alerte stock faible',
                'title' => 'Stock faible',
                'message' => "Il ne reste que {$data['remaining']} billets pour *{$data['event_title']}*",
                'color' => 'danger',
                'fields' => [
                    [
                        'title' => 'Type de billet',
                        'value' => $data['ticket_type'],
                        'short' => true
                    ],
                    [
                        'title' => 'Restants',
                        'value' => $data['remaining'],
                        'short' => true
                    ]
                ]
            ]
        ];

        return $templates[$type] ?? [
            'text' => 'Notification Sen-Billets',
            'title' => 'Notification',
            'message' => 'Nouvelle notification',
            'color' => 'good'
        ];
    }

    /**
     * Générer un template personnalisé
     */
    public function generateCustomTemplate(string $channel, array $config): array
    {
        switch ($channel) {
            case 'push':
                return [
                    'title' => $config['title'] ?? 'Sen-Billets',
                    'body' => $config['body'] ?? 'Nouvelle notification',
                    'icon' => $config['icon'] ?? '/assets/images/logo-192.png',
                    'click_action' => $config['click_action'] ?? '/',
                    'custom_data' => $config['custom_data'] ?? []
                ];

            case 'slack':
                return [
                    'text' => $config['text'] ?? 'Notification Sen-Billets',
                    'title' => $config['title'] ?? 'Notification',
                    'message' => $config['message'] ?? 'Nouvelle notification',
                    'color' => $config['color'] ?? 'good',
                    'fields' => $config['fields'] ?? []
                ];

            default:
                return $config;
        }
    }

    /**
     * Personnaliser un template avec des données utilisateur
     */
    public function personalizeTemplate(array $template, User $user, array $additionalData = []): array
    {
        $personalizedTemplate = $template;
        
        // Variables de personnalisation
        $variables = [
            '{user_name}' => $user->getName(),
            '{user_email}' => $user->getEmail(),
            '{user_first_name}' => explode(' ', $user->getName())[0],
            ...$additionalData
        ];

        // Remplacer les variables dans tous les champs texte
        array_walk_recursive($personalizedTemplate, function (&$value) use ($variables) {
            if (is_string($value)) {
                $value = str_replace(array_keys($variables), array_values($variables), $value);
            }
        });

        return $personalizedTemplate;
    }

    /**
     * Valider un template
     */
    public function validateTemplate(string $channel, array $template): array
    {
        $errors = [];

        switch ($channel) {
            case 'push':
                if (empty($template['title'])) {
                    $errors[] = 'Le titre est requis pour les notifications push';
                }
                if (empty($template['body'])) {
                    $errors[] = 'Le corps du message est requis pour les notifications push';
                }
                if (strlen($template['title']) > 65) {
                    $errors[] = 'Le titre ne peut pas dépasser 65 caractères';
                }
                if (strlen($template['body']) > 240) {
                    $errors[] = 'Le corps du message ne peut pas dépasser 240 caractères';
                }
                break;

            case 'sms':
                if (strlen($template) > 160) {
                    $errors[] = 'Le SMS ne peut pas dépasser 160 caractères';
                }
                break;

            case 'slack':
                if (empty($template['text'])) {
                    $errors[] = 'Le texte est requis pour les notifications Slack';
                }
                break;
        }

        return $errors;
    }
}