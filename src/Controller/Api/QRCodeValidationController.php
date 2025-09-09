<?php 
// src/Controller/Api/QRCodeValidationController.php
namespace App\Controller\Api;

use App\Service\QRCodeService;
use App\Repository\TicketRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class QRCodeValidationController extends AbstractController
{
    public function __construct(private QRCodeService $qrCodeService) {}

    #[Route('/api/qr/validate/{qrCode}', name: 'api_qr_validate', methods: ['GET'])]
    public function validate(string $qrCode): JsonResponse
    {
        dd('qrcode controller');
        $result = $this->qrCodeService->validateQRCode($qrCode);

        if (!$result['valid']) {
            return $this->json([
                'status' => 'error',
                'message' => $result['message'],
                'code' => $result['error'] ?? null,
            ], 400);
        }

        return $this->json([
            'status' => 'success',
            'message' => $result['message'],
            'ticket' => [
                'code' => $qrCode,
                'event' => $result['event']->getName(),
                'user' => [
                    'nom' => $result['customer']->getFullName(),
                    'email' => $result['customer']->getEmail(),
                ],
                'validatedAt' => $result['validatedAt']?->format('Y-m-d H:i:s'),
            ]
        ]);
    }

    #[Route('/qr/validate/{code}', name: 'app_qr_validate', methods: ['GET'])]
public function validateQrCode(
    string $code,
    TicketRepository $ticketRepository,
    EntityManagerInterface $em
): Response {
    $ticket = $ticketRepository->findOneBy(['qrCode' => $code]);

    if (!$ticket) {
        return $this->json([
            'success' => false,
            'message' => 'QR code invalide',
        ], 404);
    }

    if (!$ticket->canBeUsed()) {
        return $this->json([
            'success' => false,
            'message' => 'Billet déjà utilisé ou expiré',
            'status' => $ticket->getStatus(),
            'usedAt' => $ticket->getUsedAt()?->format('Y-m-d H:i:s'),
        ], 400);
    }

    // Validation du billet
    $ticket->setStatus('used'); // Cela enregistre aussi usedAt
    $em->flush();

    return $this->json([
        'success' => true,
        'message' => 'Billet validé avec succès',
        'ticket' => [
            'customer' => $ticket->getCustomerName(),
            'event' => $ticket->getEvent()->getTitle(),
            'date' => $ticket->getEvent()->getEventDate()->format('d/m/Y'),
            'email' => $ticket->getCustomerEmail(),
        ],
    ]);
}

}
