<?php

namespace App\EventListener;

use App\Entity\Payment;
use App\Entity\Ticket;
use App\Service\NotificationService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

#[AsEntityListener(event: Events::postUpdate, method: 'postUpdate', entity: Payment::class)]
class PaymentEventListener
{
    public function __construct(
        private NotificationService $notificationService
    ) {}

    public function postUpdate(Payment $payment, LifecycleEventArgs $event): void
    {
        $entityManager = $event->getObjectManager();
        $changeSet = $entityManager->getUnitOfWork()->getEntityChangeSet($payment);

        // Si le statut du paiement a changé vers "completed"
        if (isset($changeSet['status']) && $payment->getStatus() === Payment::STATUS_COMPLETED) {
            // Envoyer notification de confirmation de paiement
            $this->notificationService->notifyPaymentConfirmation($payment);

            // Si des billets sont associés, envoyer notification de billets
            $tickets = $entityManager->getRepository(Ticket::class)
                ->findBy(['payment' => $payment]);

            foreach ($tickets as $ticket) {
                $this->notificationService->notifyTicketPurchase($ticket);
                
                // Notifier l'organisateur de la nouvelle vente
                $this->notificationService->notifyOrganizerNewSale(
                    $ticket->getEvent(),
                    $ticket
                );
            }
        }
    }
}