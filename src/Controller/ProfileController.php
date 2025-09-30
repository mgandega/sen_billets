<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Event;
use App\Entity\Ticket;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class ProfileController extends AbstractController
{
    #[Route('/profile/{id}', name: 'app_profile')]
    public function index(EntityManagerInterface $em, int $id): Response
    {
        // Récupération de l'utilisateur
        $user = $em->getRepository(User::class)->find($id);

        if (!$user) {
            throw $this->createNotFoundException('Utilisateur introuvable.');
        }

        // Nombre de billets achetés
        $ticketsBought = count($user->getTickets());

        // Nombre d'événements organisés par l'utilisateur
        $eventsFavorited = count($user->getOrganizedEvents());

        // Total dépensé
        $totalSpent = 0;
        foreach ($user->getTickets() as $ticket) {
            // Si le ticket a une relation vers EventTicketType pour le prix
            if (method_exists($ticket, 'getTicketType') && $ticket->getTicketType()) {
                $totalSpent += $ticket->getTicketType()->getPrice() ?? 0;
            } 
            // Sinon, si Ticket a un champ price
            elseif (method_exists($ticket, 'getPrice')) {
                $totalSpent += $ticket->getPrice() ?? 0;
            }
        }

        // dd($totalSpent);
        // Récupération des 3 derniers événements organisés
        $recentEvents = $em->getRepository(Event::class)
            ->createQueryBuilder('e')
            ->where('e.organizer = :user')
            ->setParameter('user', $user)
            ->orderBy('e.createdAt', 'DESC')
            ->setMaxResults(3)
            ->getQuery()
            ->getResult();

        // Transformation des événements pour Twig
        $recentEventsArray = array_map(function(Event $event) {
            return [
                'name' => $event->getTitle(),
                'date' => $event->getEventDate(),
                'image' => $event->getImage() ?? '/images/default-event.jpg',
            ];
        }, $recentEvents);

        return $this->render('profile/index.html.twig', [
            'user' => [
                'fullName' => $user->getFullName(),
                'email' => $user->getEmail(),
                'avatar' => $user->getAvatar(),
                'ticketsBought' => $ticketsBought,
                'eventsFavorited' => $eventsFavorited,
                'totalSpent' => $totalSpent,
                'recentEvents' => $recentEventsArray,
            ]
        ]);
    }
}
