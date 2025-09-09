<?php

namespace App\EventListener;

use App\Entity\User;
use App\Service\NotificationService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

#[AsEntityListener(event: Events::postPersist, method: 'postPersist', entity: User::class)]
class UserEventListener
{
    public function __construct(
        private NotificationService $notificationService
    ) {}

    public function postPersist(User $user, LifecycleEventArgs $event): void
    {
        // Envoyer la séquence de bienvenue pour les nouveaux utilisateurs
        $this->notificationService->sendWelcomeSequence($user);
    }
}