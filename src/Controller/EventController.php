<?php

namespace App\Controller;

use App\Entity\Event;
use App\Form\EventType;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/events', name: 'event_')]
class EventController extends AbstractController
{
    #[Route('/', name: 'index')]
    public function index(EventRepository $eventRepository, Request $request): Response
    {
        $category = $request->query->get('category');
        $search = $request->query->get('search');

        if ($category && $category !== 'all') {
            $events = $eventRepository->findByCategory($category);
        } elseif ($search) {
            $events = $eventRepository->searchEvents($search);
        } else {
            $events = $eventRepository->findPublishedEvents();
        }

        $categories = [
            'Musique', 'Théâtre', 'Danse', 'Conférence', 'Sport', 
            'Festival', 'Exposition', 'Cinéma', 'Technologie', 'Business'
        ];

        return $this->render('event/index.html.twig', [
            'events' => $events,
            'categories' => $categories,
            'currentCategory' => $category,
            'currentSearch' => $search,
        ]);
    }

    #[Route('/new', name: 'new')]
    #[IsGranted('ROLE_ORGANIZER')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $event = new Event();
        $event->setOrganizer($this->getUser());
        
        $form = $this->createForm(EventType::class, $event);
        $form->handleRequest($request);

        // if ($form->isSubmitted() && $form->isValid()) {
        if ($form->isSubmitted()) {
            $entityManager->persist($event);
            $entityManager->flush();

            $this->addFlash('success', 'Événement créé avec succès !');

            return $this->redirectToRoute('event_show', ['id' => $event->getId()]);
        }

        return $this->render('event/new.html.twig', [
            'event' => $event,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'])]
    public function show(Event $event): Response
    {
        return $this->render('event/show.html.twig', [
            'event' => $event,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ORGANIZER')]
    public function edit(Request $request, Event $event, EntityManagerInterface $entityManager): Response
    {
        // Vérifier que l'utilisateur est le propriétaire de l'événement
        if ($event->getOrganizer() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Vous ne pouvez modifier que vos propres événements.');
        }

        $form = $this->createForm(EventType::class, $event);
        $form->handleRequest($request);

        // if ($form->isSubmitted() && $form->isValid()) {
        if ($form->isSubmitted()) {
            $entityManager->flush();

            $this->addFlash('success', 'Événement modifié avec succès !');

            return $this->redirectToRoute('event_show', ['id' => $event->getId()]);
        }

        return $this->render('event/edit.html.twig', [
            'event' => $event,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_ORGANIZER')]
    public function delete(Request $request, Event $event, EntityManagerInterface $entityManager): Response
    {
        // Vérifier que l'utilisateur est le propriétaire de l'événement
        if ($event->getOrganizer() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Vous ne pouvez supprimer que vos propres événements.');
        }

        if ($this->isCsrfTokenValid('delete'.$event->getId(), $request->request->get('_token'))) {
            $entityManager->remove($event);
            $entityManager->flush();

            $this->addFlash('success', 'Événement supprimé avec succès !');
        }

        return $this->redirectToRoute('event_index');
    }

    #[Route('/my-events', name: 'my_events')]
    #[IsGranted('ROLE_ORGANIZER')]
    public function myEvents(EventRepository $eventRepository): Response
    {
        $events = $eventRepository->findByOrganizer($this->getUser());

        return $this->render('event/my_events.html.twig', [
            'events' => $events,
        ]);
    }

    #[Route('/category/{category}', name: 'by_category')]
    public function byCategory(string $category, EventRepository $eventRepository): Response
    {
        $events = $eventRepository->findByCategory($category);
        
        $categories = [
            'Musique', 'Théâtre', 'Danse', 'Conférence', 'Sport', 
            'Festival', 'Exposition', 'Cinéma', 'Technologie', 'Business'
        ];

        return $this->render('event/index.html.twig', [
            'events' => $events,
            'categories' => $categories,
            'currentCategory' => $category,
            'currentSearch' => null,
        ]);
    }
}