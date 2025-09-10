<?php

namespace App\Controller;

use App\Entity\Payment;
use App\Repository\CartItemRepository;
use App\Service\PaymentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PaymentController extends AbstractController
{
    #[Route('/paiement/traitement', name: 'payment_process', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function process(Request $request, CartItemRepository $cartItemRepository, PaymentService $paymentService, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $cartItems = $cartItemRepository->findBy(['user' => $user]);

        if (empty($cartItems)) {
            $this->addFlash('danger', 'Votre panier est vide.');
            return $this->redirectToRoute('app_home');
        }

        $paymentMethod = $request->request->get('payment_method', 'paydunya');
        $totalAmount = array_sum(array_map(fn($item) => method_exists($item, 'getTotalPrice') ? $item->getTotalPrice() : 0, $cartItems));

        $payment = new Payment();
        $payment->setUser($user);
        $payment->setAmount($totalAmount);
        $payment->setPaymentMethod($paymentMethod);
        $payment->setStatus(Payment::STATUS_PROCESSING);
        $em->persist($payment);
        $em->flush();

        $returnUrl = $this->generateUrl('payment_success', ['id' => $payment->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        $cancelUrl = $this->generateUrl('payment_cancel', [], UrlGeneratorInterface::ABSOLUTE_URL);

        try {
            $payUrl = $paymentService->createPaydunyaInvoice($user, $cartItems, $payment, $totalAmount, $returnUrl, $cancelUrl);
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Erreur lors de la création du paiement : ' . $e->getMessage());
            return $this->redirectToRoute('app_home');
        }

        return $this->redirect($payUrl);
    }

    #[Route('/paiement/succes/{id}', name: 'payment_success')]
    #[IsGranted('ROLE_USER')]
    public function success(Payment $payment, PaymentService $paymentService, CartItemRepository $cartItemRepository): Response
    {
        try {
            $isValid = $paymentService->verifyPaydunyaInvoice($payment);
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Erreur de vérification PayDunya : ' . $e->getMessage());
            return $this->redirectToRoute('app_home');
        }

        if (!$isValid) {
            $this->addFlash('danger', 'Le paiement n’a pas été validé par PayDunya.');
            return $this->redirectToRoute('app_home');
        }

        $cartItems = $cartItemRepository->findBy(['user' => $payment->getUser()]);
        $paymentService->finalizePayment($payment, $payment->getUser(), $cartItems);

        return $this->render('payment/success.html.twig', [
            'payment' => $payment,
            'tickets' => $cartItems,
        ]);
    }

    #[Route('/paiement/annule', name: 'payment_cancel')]
    public function cancel(): Response
    {
        $this->addFlash('warning', 'Paiement annulé.');
        return $this->redirectToRoute('app_home');
    }
}
