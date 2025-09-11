<?php

namespace App\Controller;

use App\Entity\Ticket;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TicketController extends AbstractController
{
    #[Route('/ticket/verify/{qrCode}', name: 'ticket_verify')]
    public function verify(string $qrCode, EntityManagerInterface $em): Response
    {
        // Décomposer le QR Code
        $parts = explode('-', $qrCode);

        if (count($parts) !== 3) {
            return $this->render('ticket/verify.html.twig', [
                'ticket'  => null,
                'status'  => 'invalid',
                'message' => 'QR Code invalide.',
            ]);
        }

        [$prefix, $ticketId, $hash] = $parts;

        if ($prefix !== 'TICKET') {
            return $this->render('ticket/verify.html.twig', [
                'ticket'  => null,
                'status'  => 'invalid',
                'message' => 'QR Code invalide (mauvais préfixe).',
            ]);
        }

        $ticket = $em->getRepository(Ticket::class)->find($ticketId);

        if (!$ticket) {
            return $this->render('ticket/verify.html.twig', [
                'ticket'  => null,
                'status'  => 'invalid',
                'message' => 'Ticket introuvable.',
            ]);
        }

        // Vérifier hash email
        $expectedHash = md5($ticket->getUser()->getEmail());
        if ($hash !== $expectedHash) {
            return $this->render('ticket/verify.html.twig', [
                'ticket'  => null,
                'status'  => 'invalid',
                'message' => 'QR Code invalide (hash incorrect).',
            ]);
        }

        // Vérifier statut du ticket
        if (!$ticket->isValid()) {
            return $this->render('ticket/verify.html.twig', [
                'ticket'  => $ticket,
                'status'  => 'invalid',
                'message' => 'Ce ticket n’est plus valide.',
            ]);
        }

        // (Optionnel) Marquer comme utilisé dès le scan
        $ticket->setStatus('used');
        $em->persist($ticket);
        $em->flush();

        return $this->render('ticket/verify.html.twig', [
            'ticket'  => $ticket,
            'status'  => 'valid',
            'message' => 'Ticket valide !',
        ]);
    }
}
