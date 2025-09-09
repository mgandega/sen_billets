<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\Event;
use App\Entity\Ticket;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use App\Message\ScheduledNotificationMessage;

class NotificationSchedulerService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
        private NotificationService $notificationService
    ) {}

    /**
     * Programmer les notifications automatiques pour un événement
     */
    public function scheduleEventNotifications(Event $event): void
    {
        $eventDate = $event->getEventDate();
        $now = new \DateTime();

        // Notifications de rappel
        $reminderTimes = [
            '7 days' => '7 jours avant',
            '24 hours' => '24 heures avant',
            '2 hours' => '2 heures avant',
            '30 minutes' => '30 minutes avant'
        ];

        foreach ($reminderTimes as $interval => $label) {
            $notificationTime = clone $eventDate;
            $notificationTime->sub(\DateInterval::createFromDateString($interval));

            if ($notificationTime > $now) {
                $this->scheduleNotification([
                    'type' => 'event_reminder',
                    'event_id' => $event->getId(),
                    'interval' => $interval,
                    'scheduled_for' => $notificationTime,
                    'channels' => $this->getChannelsForReminder($interval)
                ]);
            }
        }

        // Notification de fin d'événement (pour les retours)
        $endTime = clone $eventDate;
        $endTime->add(new \DateInterval('PT' . $event->getEndTime()->format('H') . 'H'));
        $endTime->add(new \DateInterval('PT2H')); // 2h après la fin

        if ($endTime > $now) {
            $this->scheduleNotification([
                'type' => 'event_feedback_request',
                'event_id' => $event->getId(),
                'scheduled_for' => $endTime,
                'channels' => ['email', 'push']
            ]);
        }
    }

    /**
     * Programmer les notifications de suivi de vente
     */
    public function scheduleSalesFollowUp(Event $event): void
    {
        $eventDate = $event->getEventDate();
        $now = new \DateTime();

        // Alertes de stock faible
        $this->scheduleRecurringNotification([
            'type' => 'check_low_stock',
            'event_id' => $event->getId(),
            'start_date' => $now,
            'end_date' => $eventDate,
            'frequency' => 'daily',
            'channels' => ['slack', 'email']
        ]);

        // Rapports de ventes hebdomadaires
        if ($eventDate->diff($now)->days > 7) {
            $this->scheduleRecurringNotification([
                'type' => 'weekly_sales_report',
                'event_id' => $event->getId(),
                'start_date' => $now,
                'end_date' => $eventDate,
                'frequency' => 'weekly',
                'day_of_week' => 'monday',
                'channels' => ['email']
            ]);
        }
    }

    /**
     * Programmer les notifications de panier abandonné
     */
    public function scheduleAbandonedCartReminders(User $user): void
    {
        $intervals = ['2 hours', '24 hours', '3 days'];
        
        foreach ($intervals as $index => $interval) {
            $notificationTime = new \DateTime();
            $notificationTime->add(\DateInterval::createFromDateString($interval));

            $this->scheduleNotification([
                'type' => 'abandoned_cart_reminder',
                'user_id' => $user->getId(),
                'sequence_number' => $index + 1,
                'scheduled_for' => $notificationTime,
                'channels' => $index === 0 ? ['push'] : ['email', 'push']
            ]);
        }
    }

    /**
     * Programmer une séquence de bienvenue
     */
    public function scheduleWelcomeSequence(User $user): void
    {
        $welcomeSequence = [
            [
                'delay' => '0 minutes',
                'type' => 'welcome_immediate',
                'channels' => ['email']
            ],
            [
                'delay' => '3 days',
                'type' => 'welcome_day_3',
                'channels' => ['email', 'push']
            ],
            [
                'delay' => '1 week',
                'type' => 'welcome_week_1',
                'channels' => ['email']
            ],
            [
                'delay' => '1 month',
                'type' => 'welcome_month_1',
                'channels' => ['email']
            ]
        ];

        foreach ($welcomeSequence as $step) {
            $notificationTime = new \DateTime();
            $notificationTime->add(\DateInterval::createFromDateString($step['delay']));

            $this->scheduleNotification([
                'type' => $step['type'],
                'user_id' => $user->getId(),
                'scheduled_for' => $notificationTime,
                'channels' => $step['channels']
            ]);
        }
    }

    /**
     * Programmer les notifications de réactivation
     */
    public function scheduleReactivationCampaign(User $user): void
    {
        $lastActivity = $user->getLastActivityAt() ?? $user->getCreatedAt();
        $daysSinceActivity = $lastActivity->diff(new \DateTime())->days;

        if ($daysSinceActivity >= 30) {
            $reactivationSequence = [
                [
                    'delay' => '0 days',
                    'type' => 'reactivation_we_miss_you',
                    'channels' => ['email', 'push']
                ],
                [
                    'delay' => '7 days',
                    'type' => 'reactivation_special_offer',
                    'channels' => ['email']
                ],
                [
                    'delay' => '14 days',
                    'type' => 'reactivation_last_chance',
                    'channels' => ['email', 'sms']
                ]
            ];

            foreach ($reactivationSequence as $step) {
                $notificationTime = new \DateTime();
                $notificationTime->add(\DateInterval::createFromDateString($step['delay']));

                $this->scheduleNotification([
                    'type' => $step['type'],
                    'user_id' => $user->getId(),
                    'scheduled_for' => $notificationTime,
                    'channels' => $step['channels']
                ]);
            }
        }
    }

    /**
     * Programmer une notification unique
     */
    private function scheduleNotification(array $notificationData): void
    {
        $message = new ScheduledNotificationMessage($notificationData);
        
        // Calculer le délai
        $delay = $notificationData['scheduled_for']->getTimestamp() - time();
        
        if ($delay > 0) {
            // Utiliser Symfony Messenger avec délai
            $this->messageBus->dispatch($message, [
                new \Symfony\Component\Messenger\Stamp\DelayStamp($delay * 1000) // en millisecondes
            ]);
        }
    }

    /**
     * Programmer une notification récurrente
     */
    private function scheduleRecurringNotification(array $notificationData): void
    {
        $startDate = $notificationData['start_date'];
        $endDate = $notificationData['end_date'];
        $frequency = $notificationData['frequency'];

        $currentDate = clone $startDate;
        
        while ($currentDate <= $endDate) {
            $this->scheduleNotification([
                ...$notificationData,
                'scheduled_for' => clone $currentDate
            ]);

            // Calculer la prochaine occurrence
            switch ($frequency) {
                case 'daily':
                    $currentDate->add(new \DateInterval('P1D'));
                    break;
                case 'weekly':
                    $currentDate->add(new \DateInterval('P1W'));
                    break;
                case 'monthly':
                    $currentDate->add(new \DateInterval('P1M'));
                    break;
            }
        }
    }

    /**
     * Annuler les notifications programmées pour un événement
     */
    public function cancelEventNotifications(Event $event): void
    {
        // Ici vous devriez implémenter la logique pour annuler les messages
        // programmés dans la queue Messenger
        // Cela dépend de votre transport (Redis, RabbitMQ, etc.)
        
        $this->entityManager->getConnection()->executeStatement(
            'DELETE FROM messenger_messages WHERE body LIKE :pattern',
            ['pattern' => '%"event_id":' . $event->getId() . '%']
        );
    }

    /**
     * Obtenir les canaux appropriés selon l'intervalle de rappel
     */
    private function getChannelsForReminder(string $interval): array
    {
        return match ($interval) {
            '7 days' => ['email'],
            '24 hours' => ['email', 'push'],
            '2 hours' => ['push', 'sms'],
            '30 minutes' => ['push', 'sms'],
            default => ['push']
        };
    }

    /**
     * Obtenir les statistiques des notifications programmées
     */
    public function getScheduledNotificationsStats(): array
    {
        $connection = $this->entityManager->getConnection();
        
        $pending = $connection->fetchOne(
            'SELECT COUNT(*) FROM messenger_messages WHERE delivered_at IS NULL'
        );
        
        $delivered = $connection->fetchOne(
            'SELECT COUNT(*) FROM messenger_messages WHERE delivered_at IS NOT NULL'
        );
        
        $failed = $connection->fetchOne(
            'SELECT COUNT(*) FROM messenger_messages WHERE delivered_at IS NULL AND available_at < NOW() - INTERVAL 1 HOUR'
        );

        return [
            'pending' => (int) $pending,
            'delivered' => (int) $delivered,
            'failed' => (int) $failed,
            'total' => (int) ($pending + $delivered)
        ];
    }

    /**
     * Nettoyer les anciennes notifications
     */
    public function cleanupOldNotifications(int $daysOld = 30): int
    {
        $connection = $this->entityManager->getConnection();
        
        $result = $connection->executeStatement(
            'DELETE FROM messenger_messages WHERE delivered_at IS NOT NULL AND delivered_at < DATE_SUB(NOW(), INTERVAL :days DAY)',
            ['days' => $daysOld]
        );

        return $result;
    }
}