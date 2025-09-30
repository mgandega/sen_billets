<?php
namespace App\Controller;

use App\Entity\Event;
use App\Form\EventType;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

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

        if ($form->isSubmitted() && $form->isValid()) {
            // Gestion de l'upload d'image
            // $imageFile = $form->get('imageFile')->getData();
            // if ($imageFile) {
            //     $newFilename = uniqid().'.'.$imageFile->guessExtension();

            //     $imageFile->move(
            //         $this->getParameter('events_images_directory'),
            //         $newFilename
            //     );

            //     $event->setImage($newFilename);
            // }

            $imageFile = $form->get('imageFile')->getData();

            if ($imageFile) {
                $newFilename = uniqid().'.'.$imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('events_images_directory'), // défini dans services.yaml
                        $newFilename
                    );
                } catch (FileException $e) {
                    $this->addFlash('error', 'Erreur lors de l\'upload de l\'image.');
                }

                // On met à jour l'URL de l'image dans l'entité
                $event->setImageUrl('/uploads/events/'.$newFilename);
                $event->setImage($newFilename);
            }

// dd($event);
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
        if ($event->getOrganizer() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Vous ne pouvez modifier que vos propres événements.');
        }

        $form = $this->createForm(EventType::class, $event);
        $form->handleRequest($request);

        // if ($form->isSubmitted() && $form->isValid()) {
        if ($form->isSubmitted()) {
            // Gestion de l'upload d'image (remplace seulement si nouvelle image)
            $imageFile = $form->get('imageFile')->getData();

            if ($imageFile) {
                $newFilename = uniqid().'.'.$imageFile->guessExtension();
                
                // dd($newFilename);
                $imageFile->move(
                    $this->getParameter('events_images_directory'),
                    $newFilename
                );

                $event->setImage($newFilename);
                $event->setImageUrl('/uploads/events/'.$newFilename);
            }

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
