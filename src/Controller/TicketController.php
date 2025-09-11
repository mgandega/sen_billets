<?php

namespace App\Controller;

use App\Entity\Payment;
use App\Entity\Ticket;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;

class TicketController extends AbstractController
{
    #[Route('/ticket/verify/{qrCode}', name: 'ticket_verify')]
    public function verify(string $qrCode, EntityManagerInterface $em): Response
    {
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

        $expectedHash = md5($ticket->getUser()->getEmail());
        if ($hash !== $expectedHash) {
            return $this->render('ticket/verify.html.twig', [
                'ticket'  => null,
                'status'  => 'invalid',
                'message' => 'QR Code invalide (hash incorrect).',
            ]);
        }

        if (!$ticket->isValid()) {
            return $this->render('ticket/verify.html.twig', [
                'ticket'  => $ticket,
                'status'  => 'invalid',
                'message' => 'Ce ticket n’est plus valide.',
            ]);
        }

        // Marquer comme utilisé dès le scan
        $ticket->setStatus('used');
        $em->persist($ticket);
        $em->flush();

        return $this->render('ticket/verify.html.twig', [
            'ticket'  => $ticket,
            'status'  => 'valid',
            'message' => 'Ticket valide !',
        ]);
    }

    // ----------------------------
    // Nouvelle route pour téléchargement des tickets PDF
    // ----------------------------
    #[Route('/tickets/{id}/download', name: 'tickets_download')]
    public function downloadTickets(Payment $payment): Response
    {
        $filePath = $this->getParameter('kernel.project_dir') . '/public/uploads/tickets/tickets_' . $payment->getId() . '.pdf';

        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('Le fichier PDF des tickets n’existe pas.');
        }

        return $this->file(
            $filePath,
            'tickets_' . $payment->getId() . '.pdf',
            ResponseHeaderBag::DISPOSITION_ATTACHMENT
        );
    }
}
