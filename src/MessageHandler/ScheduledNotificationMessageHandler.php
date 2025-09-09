<?php

namespace App\MessageHandler;

use App\Message\ScheduledNotificationMessage;
use App\Service\NotificationService;
use App\Repository\UserRepository;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ScheduledNotificationMessageHandler
{
    public function __construct(
        private NotificationService $notificationService,
        private UserRepository $userRepository,
        private EventRepository $eventRepository,
        private EntityManagerInterface $entityManager
    ) {}

    public function __invoke(ScheduledNotificationMessage $message): void
    {
        $data = $message->getNotificationData();
        $type = $message->getType();
        
        // Récupérer l'utilisateur si spécifié
        $user = null;
        if ($message->getUserId()) {
            $user = $this->userRepository->find($message->getUserId());
        }
        
        // Récupérer l'événement si spécifié
        $event = null;
        if ($message->getEventId()) {
            $event = $this->eventRepository->find($message->getEventId());
        }
        
        // Traiter selon le type de notification
        switch ($type) {
            case 'event_reminder':
                if ($user && $event) {
                    $this->notificationService->notifyEventReminder($event, $user);
                }
                break;
                
            case 'abandoned_cart_reminder':
                if ($user) {
                    $this->notificationService->sendAbandonedCartReminder($user);
                }
                break;
                
            case 'weekly_sales_report':
                if ($user) {
                    $this->notificationService->sendDailySalesReport($user);
                }
                break;
                
            case 'event_feedback_request':
                if ($user && $event) {
                    $this->notificationService->sendNotification(
                        $user,
                        'event_feedback',
                        ['event' => $event],
                        $message->getChannels()
                    );
                }
                break;
                
            default:
                // Notification générique
                if ($user) {
                    $this->notificationService->sendNotification(
                        $user,
                        $type,
                        $data,
                        $message->getChannels()
                    );
                }
        }
    }
}