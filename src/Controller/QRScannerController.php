<?php

namespace App\Controller;

use App\Entity\Event;
use App\Service\QRCodeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/scanner', name: 'scanner_')]
#[IsGranted('ROLE_ORGANIZER')]
class QRScannerController extends AbstractController
{
    public function __construct(
        private QRCodeService $qrCodeService
    ) {}

    #[Route('/', name: 'index')]
    public function index(): Response
    {
        return $this->render('scanner/index.html.twig');
    }

    #[Route('/event/{id}', name: 'event')]
    public function scannerForEvent(Event $event): Response
    {
        // Vérifier que l'utilisateur peut scanner pour cet événement
        if ($event->getOrganizer() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        $stats = $this->qrCodeService->getValidationStats($event);

        return $this->render('scanner/event.html.twig', [
            'event' => $event,
            'stats' => $stats
        ]);
    }

    #[Route('/dashboard/{id}', name: 'dashboard')]
    public function dashboard(Event $event): Response
    {
        // Vérifier que l'utilisateur peut accéder au dashboard de cet événement
        if ($event->getOrganizer() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        $report = $this->qrCodeService->generateValidationReport($event);

        return $this->render('scanner/dashboard.html.twig', [
            'event' => $event,
            'report' => $report
        ]);
    }
}