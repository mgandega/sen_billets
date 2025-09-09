<?php
namespace App\Controller;

use App\Entity\Ticket;
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
    ) {

    }

    #[Route('/', name: 'index')]
    public function index(CartItemRepository $cartItemRepository): Response
    {
        
        $user = $this->getUser(); 
        $cartItems = $cartItemRepository->findBy(['user' => $user]);

        if (empty($cartItems)) {
            $this->addFlash('warning', 'Votre panier est vide');
            return $this->redirectToRoute('cart_index');
        }

        return $this->render('checkout/index.html.twig', [
            'cartItems' => $cartItems,
        ]);
    }

    #[Route('/process', name: 'process', methods: ['POST'])]
    public function process(
        Request $request,
        CartItemRepository $cartItemRepository, 
        EntityManagerInterface $entityManager,
        PaymentService $paymentService
    ): Response {
        $user = $this->getUser();
        $cartItems = $cartItemRepository->findBy(['user' => $user]);

        if (empty($cartItems)) {
            $this->addFlash('error', 'Votre panier est vide');
            return $this->redirectToRoute('cart_index');
        }

        $paymentMethod = $request->request->get('payment_method');
        $paymentData = $request->request->all();

        try {
            $result = $paymentService->processPayment(
                $cartItems,
                $user,
                $paymentMethod,
                $paymentData
            );

            // Si le paiement nécessite une redirection
            if (isset($result['redirect_url'])) {
                // Remplacer le token dans les URLs
                $redirectUrl = $result['redirect_url'];
                if (isset($result['payment_id'])) {
                    $redirectUrl = str_replace('TOKEN_TO_REPLACE', $result['payment_id'], $redirectUrl);
                }
                
                return $this->redirect($redirectUrl);
            }

            // Si le paiement est réussi directement
            if ($result['success']) {
                // Vider le panier
                foreach ($cartItems as $cartItem) {
                    $entityManager->remove($cartItem);
                }
                $entityManager->flush();
                
                $this->addFlash('success', 'Paiement effectué avec succès !');
                return $this->redirectToRoute('payment_success', ['id' => $result['payment_id']]);
            }

            // Cas par défaut
            $this->addFlash('error', 'Une erreur est survenue lors du paiement');
            return $this->redirectToRoute('checkout_index');

        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('checkout_index');
        }
    }
}