<?php
namespace App\Controller;

use App\Entity\Payment;
use App\Entity\Ticket;
use App\Entity\CartItem;
use App\Repository\CartItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\PaymentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/checkout', name: 'checkout_')]
#[IsGranted('ROLE_USER')]
class CheckoutController extends AbstractController
{
    public function __construct(
        private PaymentService $paymentService    
    ) {}

    #[Route('/', name: 'index')]
    public function index(CartItemRepository $cartItemRepository): Response
    {
        $user = $this->getUser(); 
        $cartItems = $cartItemRepository->findBy(['user' => $user, 'payment' => null]);

        if (empty($cartItems)) {
            $this->addFlash('warning', 'Votre panier est vide');
            return $this->redirectToRoute('cart_index');
        }

        $total = array_reduce($cartItems, fn($sum, $item) => $sum + $item->getTotalPrice(), 0);

        return $this->render('checkout/index.html.twig', [
            'cartItems' => $cartItems,
            'total' => $total,
        ]);
    }

    #[Route('/process', name: 'process', methods: ['POST'])]
    public function process(
        Request $request,
        CartItemRepository $cartItemRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $this->getUser();
        $cartItems = $cartItemRepository->findBy(['user' => $user, 'payment' => null]);

        if (empty($cartItems)) {
            $this->addFlash('error', 'Votre panier est vide');
            return $this->redirectToRoute('cart_index');
        }

        // Crée le paiement
        $payment = new Payment();
        $payment->setUser($user);
        $payment->setAmount(array_reduce($cartItems, fn($sum, $item) => $sum + $item->getTotalPrice(), 0));
        $payment->setStatus(Payment::STATUS_PROCESSING); // Obligatoire
        $payment->setMethod('paydunya'); // Obligatoire
        $entityManager->persist($payment);
        $entityManager->flush();

        // URLs de redirection
        $returnUrl = $this->generateUrl('checkout_success', ['paymentId' => $payment->getId()], true);
        $cancelUrl = $this->generateUrl('checkout_index', [], true);

        try {
            $redirectUrl = $this->paymentService->createPaydunyaInvoice(
                $payment,
                $cartItems,
                $payment->getAmount(),
                $returnUrl,
                $cancelUrl
            );
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la création du paiement : ' . $e->getMessage());
            return $this->redirectToRoute('checkout_index');
        }

        return $this->redirect($redirectUrl);
    }

    #[Route('/success/{paymentId}', name: 'success')]
    public function success(int $paymentId, EntityManagerInterface $entityManager): Response
    {
        $payment = $entityManager->getRepository(Payment::class)->find($paymentId);

        if (!$payment) {
            throw $this->createNotFoundException('Paiement introuvable.');
        }

        try {
            $confirmed = $this->paymentService->verifyPaydunyaInvoice($payment);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la vérification du paiement : ' . $e->getMessage());
            return $this->redirectToRoute('checkout_index');
        }

        if (!$confirmed) {
            $this->addFlash('error', 'Le paiement n\'a pas été confirmé.');
            return $this->redirectToRoute('checkout_index');
        }

        // Récupère les items du panier pour ce paiement
        $cartItems = $entityManager->getRepository(CartItem::class)
            ->findBy(['user' => $payment->getUser(), 'payment' => null]);

        // Marque les items comme payés
        foreach ($cartItems as $item) {
            $item->setPayment($payment);
            $entityManager->persist($item);
        }
        $entityManager->flush();

        // Génère les tickets individuels
        $tickets = $this->paymentService->finalizePayment($payment, $payment->getUser(), $cartItems);

        return $this->render('checkout/success.html.twig', [
            'payment' => $payment,
            'tickets' => $tickets,
        ]);
    }
}
