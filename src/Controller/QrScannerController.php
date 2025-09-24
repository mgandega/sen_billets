<?php

namespace App\Controller;

use App\Entity\Event;
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


        // #[Route('/scanner/api/validate', name: 'api_validate', methods: ['POST','OPTIONS'])]
        // public function validateQRCode(Request $request, TicketRepository $ticketRepository, EntityManagerInterface $em, Security $security): JsonResponse
        // {
        //     $origin = $request->headers->get('Origin');

        //     // Réponse préflight
        //     if ($request->getMethod() === 'OPTIONS') {
        //         $resp = new JsonResponse(null, 204);
        //         $resp->headers->set('Access-Control-Allow-Origin', $origin ?: '*');
        //         $resp->headers->set('Access-Control-Allow-Methods', 'POST, OPTIONS');
        //         $resp->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        //         $resp->headers->set('Access-Control-Allow-Credentials', 'true');
        //         return $resp;
        //     }

        //     // lecture du JSON (si tu envoies JSON)
        //     $data = json_decode($request->getContent(), true);
        //     $code = $data['code'] ?? null;

        //     if (!$code) {
        //         $resp = new JsonResponse(['valid' => false, 'message' => 'Code manquant'], 400);
        //         $resp->headers->set('Access-Control-Allow-Origin', $origin ?: '*');
        //         $resp->headers->set('Access-Control-Allow-Credentials', 'true');
        //         return $resp;
        //     }

        //     // Exemple de recherche
        //     $ticket = $ticketRepository->findOneBy(['qrCode' => $code]);
        //     if (!$ticket) {
        //         $resp = new JsonResponse(['valid' => false, 'message' => 'Ticket introuvable'], 404);
        //         $resp->headers->set('Access-Control-Allow-Origin', $origin ?: '*');
        //         $resp->headers->set('Access-Control-Allow-Credentials', 'true');
        //         return $resp;
        //     }

        //     // ... ta logique de validation ...
        //     $ticket->setStatus('used');
        //     $ticket->setUsedAt(new \DateTime());
        //     $ticket->setValidatedBy($security->getUser());
        //     $em->persist($ticket);
        //     $em->flush();

        //     $resp = new JsonResponse(['valid' => true, 'ticket' => ['id' => $ticket->getId()]]);
        //     $resp->headers->set('Access-Control-Allow-Origin', $origin ?: '*');
        //     $resp->headers->set('Access-Control-Allow-Credentials', 'true');
        //     return $resp;
        // }

        // #[Route('/api/validate', name: 'api_validate', methods: ['POST','OPTIONS'])]
        // public function validateQRCode(Request $request, TicketRepository $ticketRepository, EntityManagerInterface $em, Security $security): JsonResponse
        // {
        //     $origin = $request->headers->get('Origin');

        //     // Autoriser seulement ton frontend
        //     $allowedOrigins = ['http://127.0.0.1:8081'];
        //     $allowOrigin = in_array($origin, $allowedOrigins) ? $origin : null;

        //     // Réponse préflight
        //     if ($request->getMethod() === 'OPTIONS') {
        //         $resp = new JsonResponse(null, 204);
        //         $resp->headers->set('Access-Control-Allow-Origin', $allowOrigin ?: '*');
        //         $resp->headers->set('Access-Control-Allow-Methods', 'POST, OPTIONS');
        //         $resp->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        //         $resp->headers->set('Access-Control-Allow-Credentials', 'true');
        //         return $resp;
        //     }

        //     $data = json_decode($request->getContent(), true);
        //     $code = $data['code'] ?? null;

        //     if (!$code) {
        //         $resp = new JsonResponse(['valid' => false, 'message' => 'Code manquant'], 400);
        //         $resp->headers->set('Access-Control-Allow-Origin', $allowOrigin ?: '*');
        //         $resp->headers->set('Access-Control-Allow-Credentials', 'true');
        //         return $resp;
        //     }

        //     $ticket = $ticketRepository->findOneBy(['qrCode' => $code]);
        //     if (!$ticket) {
        //         $resp = new JsonResponse(['valid' => false, 'message' => 'Ticket introuvable'], 404);
        //         $resp->headers->set('Access-Control-Allow-Origin', $allowOrigin ?: '*');
        //         $resp->headers->set('Access-Control-Allow-Credentials', 'true');
        //         return $resp;
        //     }

        //     // Valider le ticket
        //     $ticket->setStatus('used');
        //     $ticket->setUsedAt(new \DateTime());
        //     $ticket->setValidatedBy($security->getUser());
        //     $em->persist($ticket);
        //     $em->flush();

        //     // $resp = new JsonResponse(['valid' => true, 'ticket' => ['id' => $ticket->getId()]]);
            
        // $resp = new JsonResponse([
        //     'valid' => true,
        //     'ticket' => [
        //         'id' => $ticket->getId(),
        //         'qrCode' => $ticket->getQrCode(),
        //         // 'customerName' => $ticket->getCustomerName()?->getFullName(), // si ton entité a un Customer
        //         'customerName' => $ticket->getCustomerName(), // si ton entité a un Customer
        //         'ticketType' => [
        //             'id' => $ticket->getTicketType()?->getId(),
        //             'name' => $ticket->getTicketType()?->getName(),
        //         ],
        //         'validatedBy' => $ticket->getValidatedBy()?->getUserIdentifier(),
        //         'usedAt' => $ticket->getUsedAt()?->format('Y-m-d H:i:s'),
        //     ],
        //     'event' => [
        //         'id' => $ticket->getEvent()?->getId(),
        //         'title' => $ticket->getEvent()?->getTitle(),
        //     ]
        // ]);
        // $resp->headers->set('Access-Control-Allow-Origin', $origin ?: '*');
        // $resp->headers->set('Access-Control-Allow-Credentials', 'true');

        //     return $resp;
        // }

        #[Route('/api/validate', name: 'api_validate', methods: ['POST','OPTIONS'])]
        public function validateQRCode(
            Request $request,
            TicketRepository $ticketRepository,
            EntityManagerInterface $em,
            Security $security
        ): JsonResponse {
            $origin = $request->headers->get('Origin');

            // 🔹 Gestion du préflight OPTIONS
            if ($request->getMethod() === 'OPTIONS') {
                $resp = new JsonResponse(null, 204);
                $resp->headers->set('Access-Control-Allow-Origin', $origin ?: '*');
                $resp->headers->set('Access-Control-Allow-Methods', 'POST, OPTIONS');
                $resp->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization');
                $resp->headers->set('Access-Control-Allow-Credentials', 'true');
                return $resp;
            }

            // 🔹 Lecture du JSON envoyé
            $data = json_decode($request->getContent(), true);
            $code = $data['code'] ?? null;

            if (!$code) {
                return $this->corsJson(['valid' => false, 'message' => 'Code manquant'], $origin, 400);
            }

            // 🔹 Recherche du billet
            $ticket = $ticketRepository->findOneBy(['qrCode' => $code]);
            if (!$ticket) {
                return $this->corsJson(['valid' => false, 'message' => 'Ticket introuvable'], $origin, 404);
            }

            // 🔹 Vérifie si déjà utilisé
            if ($ticket->getStatus() === 'used') {
                return $this->corsJson([
                    'valid' => false,
                    'message' => 'Billet déjà utilisé',
                    'ticket' => $this->serializeTicket($ticket),
                    'event'  => $this->serializeEvent($ticket->getEvent())
                ], $origin, 400);
            }

            // 🔹 Validation du billet
            $ticket->setStatus('used');
            $ticket->setUsedAt(new \DateTimeImmutable());
            $ticket->setValidatedBy($security->getUser());

            $em->persist($ticket);
            $em->flush();

            // 🔹 Réponse enrichie
            return $this->corsJson([
                'valid' => true,
                'message' => 'Billet validé avec succès',
                'ticket' => $this->serializeTicket($ticket),
                'event'  => $this->serializeEvent($ticket->getEvent())
            ], $origin);
        }

        /**
         * Helpers pour rendre le code clean et premium 😎
         */
        private function corsJson(array $data, ?string $origin, int $status = 200): JsonResponse
        {
            $resp = new JsonResponse($data, $status);
            $resp->headers->set('Access-Control-Allow-Origin', $origin ?: '*');
            $resp->headers->set('Access-Control-Allow-Credentials', 'true');
            return $resp;
        }

        private function serializeTicket(\App\Entity\Ticket $ticket): array
        {
            return [
                'id' => $ticket->getId(),
                'qrCode' => $ticket->getQrCode(),
                'customerName' => $ticket->getCustomerName() ?? 'Invité',
                'ticketType' => [
                    'id'   => $ticket->getTicketType()?->getId(),
                    'name' => $ticket->getTicketType()?->getName() ?? 'Standard',
                ],
                'validatedBy' => $ticket->getValidatedBy()?->getUserIdentifier(),
                'usedAt' => $ticket->getUsedAt()?->format('Y-m-d H:i:s'),
            ];
        }

        private function serializeEvent(?\App\Entity\Event $event): ?array
        {
            if (!$event) return null;
            return [
                'id'    => $event->getId(),
                'title' => $event->getTitle(),
                'date'  => $event->getEventDate()?->format('Y-m-d H:i'),
            ];
        }

    #[Route('/api/bulk-validate', name: 'api_bulk_validate', methods: ['POST'])]
    public function bulkValidate(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $results = $this->qrCodeService->bulkValidate($data['qrCodes'] ?? []);
        return $this->json($results);
    }
}
