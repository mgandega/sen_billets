<?php 
// src/Controller/PaymentController.php

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
    public function process(
        Request $request,
        CartItemRepository $cartItemRepository,
        PaymentService $paymentService,
        EntityManagerInterface $em
    ): Response {
        $user = $this->getUser();
        $cartItems = $cartItemRepository->findBy(['user' => $user]);

        if (empty($cartItems)) {
            $this->addFlash('danger', 'Votre panier est vide.');
            return $this->redirectToRoute('homepage');
        }

        $paymentMethod = $request->request->get('payment_method');
        if (!$paymentMethod) {
            $this->addFlash('danger', 'Aucun mode de paiement sélectionné.');
            return $this->redirectToRoute('homepage');
        }

        $totalAmount = array_sum(array_map(
            fn($item) => method_exists($item, 'getTotalPrice') ? $item->getTotalPrice() : 0,
            $cartItems
        ));

        // Création de l'entité Payment
        $payment = new Payment();
        $payment->setUser($user);
        $payment->setAmount($totalAmount);
        $payment->setStatus('pending');
        $payment->setPaymentMethod($paymentMethod);
        $em->persist($payment);
        $em->flush(); // Nécessaire pour obtenir l'ID

        // Génération des URLs avec l'ID réel
        $returnUrl = $this->generateUrl('payment_success', ['id' => $payment->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        $cancelUrl = $this->generateUrl('payment_cancel', [], UrlGeneratorInterface::ABSOLUTE_URL);

        // Lancement du service de paiement
        $payment = $paymentService->processPayment(
            $cartItems,
            $user,
            $paymentMethod,
            $totalAmount,
            $returnUrl,
            $cancelUrl,
            $payment
        );

        return $this->redirectToRoute('payment_success', ['id' => $payment->getId()]);
    }

    #[Route('/paiement/succes/{id}', name: 'payment_success')]
    #[IsGranted('ROLE_USER')]
    public function success(Payment $payment): Response
    {
        return $this->render('payment/success.html.twig', [
            'payment' => $payment,
        ]);
    }

    #[Route('/paiement/facture/{id}', name: 'payment_invoice')]
    #[IsGranted('ROLE_USER')]
    public function invoice(Payment $payment): Response
    {
        return $this->file(new \Symfony\Component\HttpFoundation\File\File($payment->getInvoicePath()));
    }

    #[Route('/paiement/historique', name: 'payment_history')]
    #[IsGranted('ROLE_USER')]
    public function history(EntityManagerInterface $em): Response
    {
        $payments = $em->getRepository(Payment::class)->findBy(['user' => $this->getUser()]);

        return $this->render('payment/history.html.twig', [
            'payments' => $payments,
        ]);
    }

    #[Route('/paiement/annulation', name: 'payment_cancel')]
    #[IsGranted('ROLE_USER')]
    public function cancel(): Response
    {
        $this->addFlash('warning', 'Le paiement a été annulé.');
        return $this->redirectToRoute('homepage');
    }

    #[Route('/paiement/status/{id}', name: 'payment_status')]
    #[IsGranted('ROLE_USER')]
    public function status(Payment $payment): Response
    {
        return $this->render('payment/status.html.twig', [
            'payment' => $payment,
        ]);
    }
}
