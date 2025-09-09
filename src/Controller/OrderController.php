<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Repository\CartItemRepository;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/orders', name: 'order_')]
#[IsGranted('ROLE_USER')]
class OrderController extends AbstractController
{
    #[Route('/', name: 'index')]
    public function index(OrderRepository $orderRepository): Response
    {
        $user = $this->getUser();
        $orders = $orderRepository->findBy(['user' => $user], ['createdAt' => 'DESC']);

        return $this->render('order/index.html.twig', [
            'orders' => $orders,
        ]);
    }

    #[Route('/checkout', name: 'checkout')]
    public function checkout(CartItemRepository $cartItemRepository): Response
    {
        $user = $this->getUser();
        $cartItems = $cartItemRepository->findBy(['user' => $user]);

        if (empty($cartItems)) {
            $this->addFlash('warning', 'Votre panier est vide');
            return $this->redirectToRoute('cart_index');
        }

        return $this->render('order/checkout.html.twig', [
            'cartItems' => $cartItems,
        ]);
    }

    #[Route('/create', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        CartItemRepository $cartItemRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $this->getUser();
        $cartItems = $cartItemRepository->findBy(['user' => $user]);

        if (empty($cartItems)) {
            $this->addFlash('error', 'Votre panier est vide');
            return $this->redirectToRoute('cart_index');
        }

        // Create order
        $order = new Order();
        $order->setUser($user);
        $order->setShippingAddress($request->request->get('shipping_address'));
        $order->setShippingCity($request->request->get('shipping_city'));
        $order->setShippingPostalCode($request->request->get('shipping_postal_code'));
        $order->setShippingCountry($request->request->get('shipping_country'));
        $order->setPaymentMethod($request->request->get('payment_method'));
        $order->setNotes($request->request->get('notes'));

        $totalAmount = 0;

        // Create order items from cart items
        foreach ($cartItems as $cartItem) {
            $orderItem = new OrderItem();
            $orderItem->setOrderRef($order);
            $orderItem->setProduct($cartItem->getProduct());
            $orderItem->setQuantity($cartItem->getQuantity());
            $orderItem->setUnitPrice($cartItem->getProduct()->getPrice());
            
            $order->addOrderItem($orderItem);
            $totalAmount += $cartItem->getSubtotal();
            
            // Remove cart item
            $entityManager->remove($cartItem);
        }

        $order->setTotalAmount((string) $totalAmount);
        $order->setStatus(Order::STATUS_CONFIRMED);
        $order->setPaymentStatus('pending');

        $entityManager->persist($order);
        $entityManager->flush();

        $this->addFlash('success', 'Commande créée avec succès ! Numéro de commande : ' . $order->getOrderNumber());

        return $this->redirectToRoute('order_show', ['id' => $order->getId()]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'])]
    public function show(Order $order): Response
    {
        // Check if order belongs to current user
        if ($order->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('order/show.html.twig', [
            'order' => $order,
        ]);
    }

    #[Route('/{id}/cancel', name: 'cancel', methods: ['POST'])]
    public function cancel(Order $order, EntityManagerInterface $entityManager): Response
    {
        // Check if order belongs to current user
        if ($order->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        // Only allow cancellation for pending or confirmed orders
        if (!in_array($order->getStatus(), [Order::STATUS_PENDING, Order::STATUS_CONFIRMED])) {
            $this->addFlash('error', 'Cette commande ne peut plus être annulée');
            return $this->redirectToRoute('order_show', ['id' => $order->getId()]);
        }

        $order->setStatus(Order::STATUS_CANCELLED);
        $entityManager->flush();

        $this->addFlash('success', 'Commande annulée avec succès');

        return $this->redirectToRoute('order_show', ['id' => $order->getId()]);
    }
}