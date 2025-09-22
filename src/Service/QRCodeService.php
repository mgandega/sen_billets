<?php

namespace App\Service;

use App\Entity\Event;
use App\Repository\TicketRepository;

class QRCodeService
{
    public function __construct(private TicketRepository $ticketRepository) {}

    public function getValidationStats(Event $event): array
    {
        $tickets = $this->ticketRepository->findBy(['event' => $event]);
        $total = count($tickets);
        $validated = count(array_filter($tickets, fn($t) => $t->isUsed()));
        $pending = $total - $validated;

        $lastValidations = array_reverse(array_filter($tickets, fn($t) => $t->isUsed()));
        $lastValidations = array_slice($lastValidations, 0, 10);

        return [
            'total' => $total,
            'validated' => $validated,
            'pending' => $pending,
            'lastValidations' => $lastValidations
        ];
    }

    public function generateValidationReport(Event $event): array
    {
        $tickets = $this->ticketRepository->findBy(['event' => $event]);
        $ticketTypes = [];

        foreach ($tickets as $ticket) {
            $type = $ticket->getTicketType()->getName();
            if (!isset($ticketTypes[$type])) {
                $ticketTypes[$type] = ['name' => $type, 'total' => 0, 'validated' => 0];
            }
            $ticketTypes[$type]['total']++;
            if ($ticket->isUsed()) $ticketTypes[$type]['validated']++;
        }

        return [
            'stats' => $this->getValidationStats($event),
            'ticketTypes' => array_values($ticketTypes),
            'generatedAt' => new \DateTime()
        ];
    }

    public function validate(string $qrCode)
    {
        $ticket = $this->ticketRepository->findOneBy(['qrCode' => $qrCode]);
        if ($ticket && $ticket->isValid()) {
            $ticket->setStatus('used');
            return $ticket;
        }
        return null;
    }

    public function bulkValidate(array $qrCodes): array
    {
        $success = 0;
        $errors = 0;

        foreach ($qrCodes as $code) {
            $ticket = $this->validate($code);
            if ($ticket) $success++;
            else $errors++;
        }

        return ['summary' => ['success' => $success, 'errors' => $errors]];
    }
}
