<?php

namespace App\MessageHandler;

use App\Message\SendEmailMessage;
use App\Repository\UserRepository;
use App\Service\NotificationService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class SendEmailMessageHandler
{
    public function __construct(
        private NotificationService $notificationService,
        private UserRepository $userRepository
    ) {}

    public function __invoke(SendEmailMessage $message): void
    {
        $user = $this->userRepository->find($message->getUserId());
        
        if ($user) {
            $this->notificationService->sendEmail(
                $user,
                $message->getType(),
                $message->getData()
            );
        }
    }
}