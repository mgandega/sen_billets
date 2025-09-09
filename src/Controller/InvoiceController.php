<?php 
// src/Controller/InvoiceController.php

namespace App\Controller;

use App\Entity\Ticket;
use App\Service\PdfGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class InvoiceController extends AbstractController
{
    #[Route('/facture/{id}', name: 'invoice_download')]
    public function invoice(Ticket $ticket, PdfGenerator $pdfGenerator): Response
    {
        $pdfContent = $pdfGenerator->generate('invoice/invoice.html.twig', [
            'ticket' => $ticket, // ← cette ligne est essentielle
        ]);

        return new Response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="facture_ticket_' . $ticket->getId() . '.pdf"',
        ]);
    }
}
