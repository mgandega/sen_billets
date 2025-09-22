<?php

namespace App\Service;

use App\Repository\TicketRepository;
use App\Entity\Ticket;
use Doctrine\ORM\EntityManagerInterface;

class QRCodeService
{
    public function __construct(
        private TicketRepository $ticketRepository,
        private EntityManagerInterface $em
    ) {}

    /**
     * Valide un seul billet par son QR code
     */
    public function validate(string $qrCode): ?Ticket
    {
        $ticket = $this->ticketRepository->findOneBy(['qrCode' => $qrCode]);

        if (!$ticket) {
            return null; // billet introuvable
        }

        // Si déjà validé, on peut renvoyer null ou le ticket avec status "déjà validé"
        if ($ticket->getStatus() === 'validated') {
            return null;
        }

        $ticket->setStatus('validated');
        $this->em->persist($ticket);
        $this->em->flush();

        return $ticket;
    }

    /**
     * Valide une liste de billets
     */
    public function bulkValidate(array $qrCodes): array
    {
        $results = [];

        foreach ($qrCodes as $code) {
            $ticket = $this->validate($code);
            $results[$code] = $ticket ? true : false;
        }

        return $results;
    }

    /**
     * Exemple de stats pour un event
     */
    public function getValidationStats($event): array
    {
        $tickets = $this->ticketRepository->findBy(['event' => $event]);

        $total = count($tickets);
        $validated = count(array_filter($tickets, fn ($t) => $t->getStatus() === 'validated'));

        return [
            'total' => $total,
            'validated' => $validated,
            'remaining' => $total - $validated
        ];
    }

    /**
     * Exemple de rapport
     */
    public function generateValidationReport($event): array
    {
        $tickets = $this->ticketRepository->findBy(['event' => $event]);

        return array_map(fn ($t) => [
            'id' => $t->getId(),
            'customer' => $t->getCustomerName(),
            'status' => $t->getStatus(),
            'qrCode' => $t->getQrCode(),
        ], $tickets);
    }
}
