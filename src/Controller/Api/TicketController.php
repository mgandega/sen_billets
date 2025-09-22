<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Entity\Event;
use App\Entity\Ticket;
use App\Entity\EventTicket;
use App\Repository\TicketRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/api/tickets', name: 'api_tickets_')]
class TicketController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SerializerInterface $serializer,
        private TicketRepository $ticketRepository
    ) {}

    #[Route('/purchase', name: 'purchase', methods: ['POST'])]
    public function purchase(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        $data = json_decode($request->getContent(), true);
        $user = $this->getUser();
        dd($user);
        // $user = new User;

        $event = $this->entityManager->getRepository(Event::class)->find($data['eventId']);
        if (!$event) {
            return new JsonResponse(['message' => 'Événement non trouvé'], 404);
        }

        $tickets = [];
        $totalAmount = 0;

        foreach ($data['tickets'] as $ticketData) {
            $ticketType = $this->entityManager->getRepository(EventTicket::class)->find($ticketData['ticketTypeId']);
            if (!$ticketType) {
                return new JsonResponse(['message' => 'Type de billet non trouvé'], 404);
            }

            $quantity = $ticketData['quantity'];
            if ($ticketType->getAvailableQuantity() < $quantity) {
                return new JsonResponse(['message' => 'Pas assez de billets disponibles'], 400);
            }

            for ($i = 0; $i < $quantity; $i++) {
                $ticket = new Ticket();
                $ticket->setEvent($event);
                $ticket->setUser($user);
                $ticket->setTicketType($ticketType);
                $ticket->setCustomerName($user->getName());
                $ticket->setCustomerEmail($user->getEmail());

                $this->entityManager->persist($ticket);
                $tickets[] = $ticket;
                $totalAmount += $ticketType->getPrice();
            }

            // Update sold tickets
            $ticketType->setSold($ticketType->getSold() + $quantity);
        }

        // Update event sold tickets
        $event->setSoldTickets($event->getSoldTickets() + count($tickets));

        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'Billets achetés avec succès',
            'tickets' => json_decode($this->serializer->serialize($tickets, 'json', ['groups' => ['ticket:read']])),
            'totalAmount' => $totalAmount
        ], 201);
    }

    #[Route('/my-tickets', name: 'my_tickets', methods: ['GET'])]
    public function myTickets(): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        $tickets = $this->ticketRepository->findByUser($this->getUser());

        return new JsonResponse([
            'tickets' => json_decode($this->serializer->serialize($tickets, 'json', ['groups' => ['ticket:read']]))
        ]);
    }

    #[Route('/validate/{qrCode}', name: 'validate', methods: ['POST'])]
    public function validate(string $qrCode): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ORGANIZER');
        
        $ticket = $this->ticketRepository->findByQrCode($qrCode);
        
        if (!$ticket) {
            return new JsonResponse(['message' => 'Billet non trouvé'], 404);
        }

        if ($ticket->getStatus() === 'used') {
            return new JsonResponse(['message' => 'Billet déjà utilisé'], 400);
        }

        if ($ticket->getStatus() === 'cancelled') {
            return new JsonResponse(['message' => 'Billet annulé'], 400);
        }

        $ticket->setStatus('used');
        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'Billet validé avec succès',
            'ticket' => json_decode($this->serializer->serialize($ticket, 'json', ['groups' => ['ticket:read']]))
        ]);
    }
    // src/Controller/TicketController.php

    #[Route('/ticket/verify/{qrCode}', name: 'ticket_verify')]
    public function verify(string $qrCode, TicketRepository $ticketRepository): Response
    {
        die('ok1');
        $ticket = $ticketRepository->findOneBy(['qrCode' => $qrCode]);

        if (!$ticket) {
            throw $this->createNotFoundException('Billet introuvable.');
        }

        return $this->render('ticket/verify.html.twig', [
            'ticket' => $ticket,
        ]);
    }

}