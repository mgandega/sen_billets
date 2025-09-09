<?php

namespace App\MessageHandler;

use App\Message\SendPushNotificationMessage;
use App\Repository\UserRepository;
use App\Service\NotificationChannelService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class SendPushNotificationMessageHandler
{
    public function __construct(
        private NotificationChannelService $notificationChannelService,
        private UserRepository $userRepository
    ) {
    }

    public function __invoke(SendPushNotificationMessage $message): void
    {
        $user = $this->userRepository->find($message->getUserId());
        
        if ($user) {
            try {
                $this->notificationChannelService->sendPushNotification(
                    $user,
                    $message->getData()
                );
            } catch (\Exception $e) {
                // Log error but don't crash the application
            }
        }
    }
}