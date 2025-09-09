<?php

namespace App\Controller;

use App\Entity\Ticket;
use App\Service\PaymentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class PaymentWebhookController extends AbstractController
{
    #[Route('/paiement/webhook', name: 'payment_webhook', methods: ['POST'])]
    public function webhook(Request $request, PaymentService $paymentService, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (isset($data['data']['custom_data']['ticket_id'])) {
            $ticketId = $data['data']['custom_data']['ticket_id'];

            $ticket = $em->getRepository(Ticket::class)->find($ticketId);

            if ($ticket) {
                $paymentService->handleSuccessfulPayment($ticket);
                return new JsonResponse(['status' => 'ok']);
            }
        }

        return new JsonResponse(['status' => 'ignored'], 400);
    }
}
