<?php

namespace App\Controller;

use App\Repository\EventRepository;
use App\Repository\TicketRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin', name: 'app_')]
class DashboardController extends AbstractController
{
    public function __construct(
        private EventRepository $eventRepository,
        private TicketRepository $ticketRepository
    ) {}

    #[Route('/dashboard', name: 'dashboard')]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ORGANIZER');
        
        $user = $this->getUser();
        $events = $this->eventRepository->findByOrganizer($user);
        
        $totalRevenue = 0;
        $totalTicketsSold = 0;
        
        foreach ($events as $event) {
            $totalTicketsSold += $event->getSoldTickets();
            foreach ($event->getTicketTypes() as $ticketType) {
                $totalRevenue += $ticketType->getSold() * $ticketType->getPrice();
            }
        }

        return $this->render('dashboard/index.html.twig', [
            'events' => $events,
            'totalRevenue' => $totalRevenue,
            'totalTicketsSold' => $totalTicketsSold,
            'totalEvents' => count($events),
        ]);
    }

    #[Route('/events', name: 'events')]
    public function events(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ORGANIZER');
        
        $events = $this->eventRepository->findByOrganizer($this->getUser());

        return $this->render('dashboard/events.html.twig', [
            'events' => $events,
        ]);
    }
}