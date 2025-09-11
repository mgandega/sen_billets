<?php

namespace App\Controller;

use App\Entity\Payment;
use App\Service\PaymentService;
use App\Repository\CartItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PaymentController extends AbstractController
{
    public function __construct(
        private PaymentService $paymentService,
        private EntityManagerInterface $em,
        private CartItemRepository $cartItemRepository
    ) {}

    #[Route('/payment/success/{paymentId}', name: 'payment_success')]
    public function success(int $paymentId): Response
    {
        $payment = $this->em->getRepository(Payment::class)->find($paymentId);

        if (!$payment) {
            throw $this->createNotFoundException('Paiement introuvable.');
        }

        $cartItems = $this->cartItemRepository->findBy(['payment' => $payment]);

        $tickets = $this->paymentService->finalizePayment($payment, $payment->getUser(), $cartItems);

        return $this->render('payment/success.html.twig', [
            'payment' => $payment,
            'tickets' => $tickets,
        ]);
    }
}
