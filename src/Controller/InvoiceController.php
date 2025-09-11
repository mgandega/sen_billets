<?php
// src/Controller/InvoiceController.php

namespace App\Controller;

use App\Entity\Payment;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class InvoiceController extends AbstractController
{
    #[Route('/facture/{id}', name: 'invoice_download')]
    public function download(Payment $payment): Response
    {
        // Vérifie que la facture appartient bien à l'utilisateur connecté
        if ($payment->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Accès interdit à cette facture.');
        }

        $filePath = $this->getParameter('kernel.project_dir') . '/public' . $payment->getInvoicePath();

        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('Facture introuvable.');
        }

        // ✅ Forcer le téléchargement avec un nom de fichier propre
        return $this->file($filePath, 'facture_' . $payment->getId() . '.pdf');
    }
}
