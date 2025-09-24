<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\CartItem;
use App\Service\QRCodeService;
use App\Repository\EventRepository;
use App\Repository\TicketRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

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

    // #[Route('/api/validate/{qrCode}', name: 'api_validate', methods: ['POST','GET'])]
    // public function validateQRCode(string $qrCode): JsonResponse
    // {
    //     $ticket = $this->qrCodeService->validate($qrCode);

    //     if ($ticket) {
    //         $event = $ticket->getEvent();

    //         return $this->json([
    //             'valid' => true,
    //             'ticket' => [
    //                 'id'           => $ticket->getId(),
    //                 'customerName' => $ticket->getCustomerName(),
    //                 'status'       => $ticket->getStatus(),
    //                 'qrCode'       => $ticket->getQrCode(),
    //                 'ticketType'   => [
    //                     'id'   => $ticket->getTicketType()->getId(),
    //                     'name' => $ticket->getTicketType()->getName(),
    //                 ],
    //             ],
    //             'event' => [
    //                 'id'    => $event->getId(),
    //                 'title' => $event->getTitle(),
    //                 'date'  => $event->getEventDate()->format('Y-m-d H:i'),
    //             ]
    //         ]);
    //     }

    //     return $this->json([
    //         'valid' => false,
    //         'message' => 'Billet invalide ou déjà scanné',
    //     ]);
    // }
    // #[Route('/api/validate/{qrCode}', name: 'api_validate', methods: ['POST'])]
    // public function validateQRCode(string $qrCode): JsonResponse
    // {
    //     $ticket = $this->qrCodeService->validate($qrCode);

    //     if ($ticket) {
    //         $event = $ticket->getEvent();

    //         return $this->json([
    //             'valid' => true,
    //             'ticket' => [
    //                 'id'           => $ticket->getId(),
    //                 'customerName' => $ticket->getCustomerName(),
    //                 'status'       => $ticket->getStatus(),
    //                 'qrCode'       => $ticket->getQrCode(),
    //                 'ticketType'   => [
    //                     'id'   => $ticket->getTicketType()->getId(),
    //                     'name' => $ticket->getTicketType()->getName(),
    //                 ],
    //             ],
    //             'event' => [
    //                 'id'    => $event->getId(),
    //                 'title' => $event->getTitle(),
    //                 'date'  => $event->getEventDate()->format('Y-m-d H:i'),
    //             ]
    //         ]);
    //     }

    //     return $this->json([
    //         'valid' => false,
    //         'message' => 'Billet invalide ou déjà scanné',
    //     ]);
    // }

    // #[Route('/api/validate', name: 'api_validate', methods: ['POST'])]
    // public function validateQRCode(Request $request): JsonResponse
    // {
    //     dd('ok');
    //     $data = json_decode($request->getContent(), true);
    //     $qrCode = $data['code'] ?? null;

    //     if (!$qrCode) {
    //         return $this->json([
    //             'valid' => false,
    //             'message' => 'Code QR manquant'
    //         ], 400);
    //     }

    //     $ticket = $this->qrCodeService->validate($qrCode);

    //     if ($ticket) {
    //         $event = $ticket->getEvent();
    //         return $this->json([
    //             'valid' => true,
    //             'ticket' => [
    //                 'id' => $ticket->getId(),
    //                 'customerName' => $ticket->getCustomerName(),
    //                 'status' => $ticket->getStatus(),
    //                 'qrCode' => $ticket->getQrCode(),
    //                 'ticketType' => [
    //                     'id' => $ticket->getTicketType()->getId(),
    //                     'name' => $ticket->getTicketType()->getName(),
    //                 ],
    //             ],
    //             'event' => [
    //                 'id' => $event->getId(),
    //                 'title' => $event->getTitle(),
    //                 'date' => $event->getEventDate()->format('Y-m-d H:i'),
    //             ]
    //         ]);
    //     }

    //     return $this->json([
    //         'valid' => false,
    //         'message' => 'Billet invalide ou déjà scanné',
    //     ]);
    // }

    // #[Route('/api/validate', name: 'api_validate', methods: ['POST'])]
    // #[Route('/api/validate/{qrCode}', name: 'api_validate', requirements: ['qrCode' => '.+'], methods: ['POST'])]
    // public function validateQRCode(Request $request): JsonResponse
    // {
    //     $data = json_decode($request->getContent(), true);
    //     $qrCode = $data['code'] ?? null;
    //     if (!$qrCode) {
    //         return $this->json(['valid'=>false,'message'=>'Code manquant'], 400);
    //     }
    //         $ticket = $this->qrCodeService->validate($qrCode);

    //         if ($ticket) {
    //             $event = $ticket->getEvent();
    //             return $this->json([
    //                 'valid' => true,
    //                 'ticket' => [
    //                     'id' => $ticket->getId(),
    //                     'customerName' => $ticket->getCustomerName(),
    //                     'status' => $ticket->getStatus(),
    //                     'qrCode' => $ticket->getQrCode(),
    //                     'ticketType' => [
    //                         'id' => $ticket->getTicketType()->getId(),
    //                         'name' => $ticket->getTicketType()->getName(),
    //                     ],
    //                 ],
    //                 'event' => [
    //                     'id' => $event->getId(),
    //                     'title' => $event->getTitle(),
    //                     'date' => $event->getEventDate()->format('Y-m-d H:i'),
    //                 ]
    //             ]);
    //         }

    //         return $this->json([
    //             'valid' => false,
    //             'message' => 'Billet invalide ou déjà scanné',
    //         ]);
    
    // }

    #[Route('/api/validate/{qrCode}', name: 'api_validate', methods: ['POST'])]
    public function validateQRCode(
        string $qrCode, 
        TicketRepository $ticketRepository, 
        EntityManagerInterface $em, 
        Security $security
    ): JsonResponse {
        $ticket = $ticketRepository->findOneBy(['qrCode' => $qrCode]);

        if (!$ticket) {
            return $this->json(['success' => false, 'message' => 'Ticket introuvable'], 404);
        }

        if ($ticket->isUsed()) {
            return $this->json(['success' => false, 'message' => 'Ticket déjà utilisé'], 400);
        }

        if ($ticket->isCancelled()) {
            return $this->json(['success' => false, 'message' => 'Ticket annulé'], 400);
        }

        if (!$ticket->canBeUsed()) {
            return $this->json(['success' => false, 'message' => 'Ticket non valide (événement passé ?)'], 400);
        }

        // ✅ Marquer comme utilisé
        $ticket->setStatus('used');
        $ticket->setUsedAt(new \DateTime());

        // ✅ Qui a validé (utilisateur connecté, ex: organisateur ou contrôleur)
        $user = $security->getUser();
        if ($user) {
            $ticket->setValidatedBy($user);
        }

        // ✅ Paiement associé (si pas déjà défini dans Ticket)
        if (!$ticket->getPayment()) {
            $eventTicket = $ticket->getTicketType();
            if ($eventTicket) {
                $cartItem = $em->getRepository(CartItem::class)->findOneBy([
                    'ticketType' => $eventTicket,
                    'user' => $ticket->getUser(),
                ]);
                if ($cartItem && $cartItem->getPayment()) {
                    $ticket->setPayment($cartItem->getPayment());
                }
            }
        }

        $em->persist($ticket);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Ticket validé avec succès',
            'ticket' => [
                'id' => $ticket->getId(),
                'status' => $ticket->getStatus(),
                'validatedBy' => $ticket->getValidatedBy()?->getEmail(),
                'usedAt' => $ticket->getUsedAt()?->format('Y-m-d H:i:s'),
                'paymentId' => $ticket->getPayment()?->getId()
            ]
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
