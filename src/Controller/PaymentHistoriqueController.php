<?php

namespace App\Controller;

use App\Repository\EventRepository;
use App\Repository\PaymentRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class PaymentHistoriqueController extends AbstractController
{
    #[Route('/payment/historique', name: 'app_payment_historique')]
    public function index(PaymentRepository $paymentRepository, EventRepository $eventRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ORGANIZER');
        $user = $this->getUser();
        $events = $eventRepository->findByOrganizer($user);
        
        $totalRevenue = 0;
        $totalTicketsSold = 0;
        // On récupère tous les paiements de l’utilisateur connecté (ou tous si admin)
        $user = $this->getUser();
        $payments = $paymentRepository->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC']
        );
        
        foreach ($events as $event) {
            $totalTicketsSold += $event->getSoldTickets();
            foreach ($event->getTicketTypes() as $ticketType) {
                $totalRevenue += $ticketType->getSold() * $ticketType->getPrice();
            }
        }
        return $this->render('payment_historique/index.html.twig', [
            'payments' => $payments,
            'events' => $events,
            'totalRevenue' => $totalRevenue,
            'totalTicketsSold' => $totalTicketsSold,
            'totalEvents' => count($events),
        ]);
    }
}
