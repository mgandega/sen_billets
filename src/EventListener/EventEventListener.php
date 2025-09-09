<?php

namespace App\EventListener;

use App\Entity\Event;
use App\Service\NotificationService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

#[AsEntityListener(event: Events::postUpdate, method: 'postUpdate', entity: Event::class)]
class EventEventListener
{
    public function __construct(
        private NotificationService $notificationService
    ) {}

    public function postUpdate(Event $event, LifecycleEventArgs $args): void
    {
        $entityManager = $args->getObjectManager();
        $changeSet = $entityManager->getUnitOfWork()->getEntityChangeSet($event);

        // Détecter les changements importants
        $importantChanges = [];
        $notificationChannels = ['email', 'push'];

        if (isset($changeSet['eventDate'])) {
            $importantChanges['date'] = [
                'old' => $changeSet['eventDate'][0]->format('d/m/Y H:i'),
                'new' => $changeSet['eventDate'][1]->format('d/m/Y H:i')
            ];
        }

        if (isset($changeSet['venue'])) {
            $importantChanges['venue'] = [
                'old' => $changeSet['venue'][0],
                'new' => $changeSet['venue'][1]
            ];
        }

        if (isset($changeSet['address'])) {
            $importantChanges['address'] = [
                'old' => $changeSet['address'][0],
                'new' => $changeSet['address'][1]
            ];
        }

        // Si l'événement est annulé
        if (isset($changeSet['status']) && $event->getStatus() === 'cancelled') {
            $this->notificationService->notifyEventCancellation($event);
            return;
        }

        // Si il y a des changements importants
        if (!empty($importantChanges)) {
            $this->notificationService->notifyEventUpdate($event, 'update', $importantChanges);
        }
    }
}