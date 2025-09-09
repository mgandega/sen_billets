<?php

namespace App\Controller;

use App\Entity\CartItem;
use App\Entity\TicketType;
use App\Entity\EventTicket;
use App\Entity\EventTicketType;
use App\Repository\CartItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/cart', name: 'cart_')]
#[IsGranted('ROLE_USER')]
class CartController extends AbstractController
{
    #[Route('/', name: 'index')]
    public function index(CartItemRepository $cartItemRepository): Response
    {
        $user = $this->getUser();
        $cartItems = $cartItemRepository->findBy(['user' => $user]);

        return $this->render('cart/index.html.twig', [
            'cartItems' => $cartItems,
        ]);
    }

    #[Route('/add/{id}', name: 'add', methods: ['POST'])]
    public function add(
        EventTicket $ticketType, 
        Request $request,
        EntityManagerInterface $entityManager,
        CartItemRepository $cartItemRepository
    ): Response {
        $user = $this->getUser();
        $quantity = (int) $request->request->get('quantity', 1);
        $isAjax = $request->isXmlHttpRequest();

        if ($quantity <= 0) {
            if ($isAjax) {
                return new JsonResponse(['error' => 'La quantité doit être positive'], 400);
            }
            $this->addFlash('error', 'La quantité doit être positive');
            return $this->redirectToRoute('event_show', ['id' => $ticketType->getEvent()->getId()]);
        }

        // Vérifier la disponibilité
        if (!$ticketType->isAvailable()) {
            if ($isAjax) {
                return new JsonResponse(['error' => 'Ce type de billet n\'est plus disponible'], 400);
            }
            $this->addFlash('error', 'Ce type de billet n\'est plus disponible');
            return $this->redirectToRoute('event_show', ['id' => $ticketType->getEvent()->getId()]);
        }

        if ($ticketType->getAvailableQuantity() < $quantity) {
            if ($isAjax) {
                return new JsonResponse(['error' => 'Quantité insuffisante disponible'], 400);
            }
            $this->addFlash('error', 'Quantité insuffisante disponible');
            return $this->redirectToRoute('event_show', ['id' => $ticketType->getEvent()->getId()]);
        }

        // Check if ticket type is already in cart
        $existingCartItem = $cartItemRepository->findOneBy([
            'user' => $user,
            'ticketType' => $ticketType 
        ]);

        if ($existingCartItem) {
            $newQuantity = $existingCartItem->getQuantity() + $quantity;
            if ($ticketType->getAvailableQuantity() < $newQuantity) {
                if ($isAjax) {
                    return new JsonResponse(['error' => 'Quantité totale insuffisante disponible'], 400);
                }
                $this->addFlash('error', 'Quantité totale insuffisante disponible');
                return $this->redirectToRoute('event_show', ['id' => $ticketType->getEvent()->getId()]);
            }
            $existingCartItem->setQuantity($newQuantity);
        } else {
            $cartItem = new CartItem();
            $cartItem->setUser($user);
            $cartItem->setTicketType($ticketType);
            $cartItem->setQuantity($quantity);
            $entityManager->persist($cartItem);
        }

        $entityManager->flush();

        if ($isAjax) {
            return new JsonResponse([
                'success' => true,
                'message' => 'Billet ajouté au panier',
                'cartCount' => $user->getCartItemsCount(),
                'cartTotal' => $user->getCartTotal()
            ]);
        }

        $this->addFlash('success', 'Billet ajouté au panier');
        return $this->redirectToRoute('cart_index');
    }

    #[Route('/update/{id}', name: 'update', methods: ['POST'])]
    public function update(
        CartItem $cartItem,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        // Check if cart item belongs to current user
        if ($cartItem->getUser() !== $this->getUser()) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['error' => 'Accès refusé'], 403);
            }
            throw $this->createAccessDeniedException();
        }

        $quantity = (int) $request->request->get('quantity');

        if ($quantity <= 0) {
            $entityManager->remove($cartItem);
        } else {
            if ($cartItem->getTicketType()->getAvailableQuantity() < $quantity) {
                if ($request->isXmlHttpRequest()) {
                    return new JsonResponse(['error' => 'Quantité insuffisante disponible'], 400);
                }
                $this->addFlash('error', 'Quantité insuffisante disponible');
                return $this->redirectToRoute('cart_index');
            }
            $cartItem->setQuantity($quantity);
        }

        $entityManager->flush();

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'success' => true,
                'subtotal' => $cartItem->getSubtotal(),
                'cartTotal' => $this->getUser()->getCartTotal(),
                'cartCount' => $this->getUser()->getCartItemsCount()
            ]);
        }

        $this->addFlash('success', 'Panier mis à jour');
        return $this->redirectToRoute('cart_index');
    }

    #[Route('/remove/{id}', name: 'remove', methods: ['POST'])]
    public function remove(CartItem $cartItem, EntityManagerInterface $entityManager, Request $request): Response {
        // Check if cart item belongs to current user
        if ($cartItem->getUser() !== $this->getUser()) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['error' => 'Accès refusé'], 403);
            }
            throw $this->createAccessDeniedException();
        }

        $entityManager->remove($cartItem);
        $entityManager->flush();

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'success' => true,
                'message' => 'Billet retiré du panier',
                'cartTotal' => $this->getUser()->getCartTotal(),
                'cartCount' => $this->getUser()->getCartItemsCount()
            ]);
        }

        $this->addFlash('success', 'Billet retiré du panier');
        return $this->redirectToRoute('cart_index');
    }

    #[Route('/clear', name: 'clear', methods: ['POST'])]
    public function clear(CartItemRepository $cartItemRepository, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        $cartItems = $cartItemRepository->findBy(['user' => $user]);

        foreach ($cartItems as $cartItem) {
            $entityManager->remove($cartItem);
        }

        $entityManager->flush();

        $this->addFlash('success', 'Panier vidé avec succès');

        return $this->redirectToRoute('cart_index');
    }
}