<?php

namespace App\Controller\Api;

use App\Entity\Event;
use App\Entity\EventTicket;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/events', name: 'api_events_')]
class EventController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator,
        private EventRepository $eventRepository
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $category = $request->query->get('category');
        $search = $request->query->get('search');
        $featured = $request->query->get('featured');

        if ($featured) {
            $events = $this->eventRepository->findFeaturedEvents(3);
        } elseif ($category && $category !== 'all') {
            $events = $this->eventRepository->findByCategory($category);
        } elseif ($search) {
            $events = $this->eventRepository->searchEvents($search);
        } else {
            $events = $this->eventRepository->findPublishedEvents();
        }

        return new JsonResponse([
            'events' => json_decode($this->serializer->serialize($events, 'json', ['groups' => ['event:read']]))
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Event $event): JsonResponse
    {
        return new JsonResponse([
            'event' => json_decode($this->serializer->serialize($event, 'json', ['groups' => ['event:read']]))
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ORGANIZER');
        
        $data = json_decode($request->getContent(), true);
        $user = $this->getUser();
        
      
        $event = new Event();
        $event->setTitle($data['title'] ?? '');
        $event->setDescription($data['description'] ?? '');
        $event->setEventDate(new \DateTime($data['eventDate'] ?? 'now'));
        $event->setStartTime(new \DateTime($data['startTime'] ?? '09:00'));
        $event->setEndTime(new \DateTime($data['endTime'] ?? '18:00'));
        $event->setVenue($data['venue'] ?? '');
        $event->setAddress($data['address'] ?? '');
        $event->setCategory($data['category'] ?? '');
        $event->setImageUrl($data['imageUrl'] ?? null);
        $event->setCapacity($data['capacity'] ?? 100);
        $event->setTags($data['tags'] ?? []);
        $event->setOrganizer($user);

        // Add ticket types
        if (isset($data['ticketTypes']) && is_array($data['ticketTypes'])) {
            foreach ($data['ticketTypes'] as $ticketData) {
                $ticketType = new EventTicket();
                $ticketType->setName($ticketData['name'] ?? '');
                $ticketType->setPrice($ticketData['price'] ?? 0);
                $ticketType->setQuantity($ticketData['quantity'] ?? 0);
                $ticketType->setDescription($ticketData['description'] ?? null);
                $ticketType->setEarlyBird($ticketData['earlyBird'] ?? false);
                
                if (isset($ticketData['endDate'])) {
                    $ticketType->setEndDate(new \DateTime($ticketData['endDate']));
                }
                
                $event->addTicketType($ticketType);
            }
        }

        $errors = $this->validator->validate($event);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['errors' => $errorMessages], 400);
        }

        $this->entityManager->persist($event);
        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'Événement créé avec succès',
            'event' => json_decode($this->serializer->serialize($event, 'json', ['groups' => ['event:read']]))
        ], 201);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(Event $event, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ORGANIZER');
        
        if ($event->getOrganizer() !== $this->getUser()) {
            return new JsonResponse(['message' => 'Accès refusé'], 403);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['title'])) $event->setTitle($data['title']);
        if (isset($data['description'])) $event->setDescription($data['description']);
        if (isset($data['eventDate'])) $event->setEventDate(new \DateTime($data['eventDate']));
        if (isset($data['startTime'])) $event->setStartTime(new \DateTime($data['startTime']));
        if (isset($data['endTime'])) $event->setEndTime(new \DateTime($data['endTime']));
        if (isset($data['venue'])) $event->setVenue($data['venue']);
        if (isset($data['address'])) $event->setAddress($data['address']);
        if (isset($data['category'])) $event->setCategory($data['category']);
        if (isset($data['imageUrl'])) $event->setImageUrl($data['imageUrl']);
        if (isset($data['capacity'])) $event->setCapacity($data['capacity']);
        if (isset($data['tags'])) $event->setTags($data['tags']);
        if (isset($data['status'])) $event->setStatus($data['status']);

        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'Événement mis à jour avec succès',
            'event' => json_decode($this->serializer->serialize($event, 'json', ['groups' => ['event:read']]))
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(Event $event): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ORGANIZER');
        
        if ($event->getOrganizer() !== $this->getUser()) {
            return new JsonResponse(['message' => 'Accès refusé'], 403);
        }

        $this->entityManager->remove($event);
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Événement supprimé avec succès']);
    }

    #[Route('/organizer/my-events', name: 'my_events', methods: ['GET'])]
    public function myEvents(): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ORGANIZER');
        
        $events = $this->eventRepository->findByOrganizer($this->getUser());

        return new JsonResponse([
            'events' => json_decode($this->serializer->serialize($events, 'json', ['groups' => ['event:read']]))
        ]);
    }
}