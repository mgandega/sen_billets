<?php

namespace App\Controller;

use App\Entity\Event;
use App\Repository\EventRepository;
use App\Repository\TicketRepository;
use App\Service\QRCodeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/scanner', name: 'scanner_')]
#[IsGranted('ROLE_ORGANIZER')]
class QrScannerController extends AbstractController
{
    public function __construct(
        private QRCodeService $qrCodeService,
        private EventRepository $eventRepository,
        private TicketRepository $ticketRepository
    ) {}

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $events = $this->eventRepository->findBy(['organizer' => $this->getUser()]);
        return $this->render('scanner/index.html.twig', ['events' => $events]);
    }

    #[Route('/event/{id}', name: 'event', methods: ['GET'])]
    public function event(Event $event): Response
    {
        if ($event->getOrganizer() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        $stats = $this->qrCodeService->getValidationStats($event);

        return $this->render('scanner/scan.html.twig', [
            'event' => $event,
            'stats' => $stats
        ]);
    }

    #[Route('/dashboard/{id}', name: 'dashboard', methods: ['GET'])]
    public function dashboard(Event $event): Response
    {
        if ($event->getOrganizer() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        $report = $this->qrCodeService->generateValidationReport($event);

        return $this->render('scanner/dashboard.html.twig', [
            'event' => $event,
            'report' => $report
        ]);
    }

    #[Route('/api/validate/{qrCode}', name: 'api_validate', methods: ['POST'])]
    public function validateQRCode(string $qrCode): JsonResponse
    {
        $ticket = $this->qrCodeService->validate($qrCode);

        if ($ticket) {
            $event = $ticket->getEvent();

            return $this->json([
                'valid' => true,
                'ticket' => [
                    'id'           => $ticket->getId(),
                    'customerName' => $ticket->getCustomerName(),
                    'status'       => $ticket->getStatus(),
                    'qrCode'       => $ticket->getQrCode(),
                    'ticketType'   => [
                        'id'   => $ticket->getTicketType()->getId(),
                        'name' => $ticket->getTicketType()->getName(),
                    ],
                ],
                'event' => [
                    'id'    => $event->getId(),
                    'title' => $event->getTitle(),
                    'date'  => $event->getEventDate()->format('Y-m-d H:i'),
                ]
            ]);
        }

        return $this->json([
            'valid' => false,
            'message' => 'Billet invalide ou déjà scanné',
        ]);
    }

    #[Route('/api/bulk-validate', name: 'api_bulk_validate', methods: ['POST'])]
    public function bulkValidate(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $results = $this->qrCodeService->bulkValidate($data['qrCodes'] ?? []);
        return $this->json($results);
    }
}
