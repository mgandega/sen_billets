<?php

namespace App\Controller;

use App\Entity\Event;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(EntityManagerInterface $em): Response
    {

        $events = $em->getRepository(Event::class)->findTree();
        return $this->render('home/index.html.twig', [
            'events'=>$events,
            'title' => 'Bienvenue sur Symfony 7.2',
            'features' => [
                [
                    'title' => 'Performance',
                    'description' => 'Symfony 7.2 offre des performances exceptionnelles',
                    'icon' => 'rocket'
                ],
                [
                    'title' => 'Sécurité',
                    'description' => 'Framework sécurisé avec les meilleures pratiques',
                    'icon' => 'shield'
                ],
                [
                    'title' => 'Flexibilité',
                    'description' => 'Architecture modulaire et extensible',
                    'icon' => 'puzzle'
                ]
            ]
        ]);
    }

    #[Route('/about', name: 'app_about')]
    public function about(): Response
    {
        return $this->render('home/about.html.twig', [
            'title' => 'À propos',
            'description' => 'Application Symfony 7.2 avec Bootstrap 5.3 et Asset Mapper'
        ]);
    }
}