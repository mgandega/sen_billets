<?php

namespace App\MessageHandler;

use App\Message\SendSMSMessage;
use App\Repository\UserRepository;
use App\Service\NotificationChannelService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class SendSMSMessageHandler
{
    public function __construct(
        private NotificationChannelService $notificationChannelService,
        private UserRepository $userRepository
    ) {
    }

    public function __invoke(SendSMSMessage $message): void
    {
        $user = $this->userRepository->find($message->getUserId());
        
        if ($user) {
            try {
                $template = $this->notificationChannelService->getSMSTemplate(
                    $message->getType(),
                    $message->getData()
                );
                
                $this->notificationChannelService->sendSMSOrange($user, $template);
            } catch (\Exception $e) {
                // Log error but don't crash the application
            }
        }
    }
}