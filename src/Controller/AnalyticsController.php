<?php

namespace App\Controller;

use App\Entity\Event;
use App\Service\AnalyticsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/analytics', name: 'analytics_')]
#[IsGranted('ROLE_ORGANIZER')]
class AnalyticsController extends AbstractController
{
    public function __construct(
        private AnalyticsService $analyticsService,
        private EntityManagerInterface $em
    ) {}

    #[Route('/', name: 'index')]
    public function index(): Response
    {
        $user = $this->getUser();
        
        // Récupérer les événements de l'organisateur
        $events = $this->em->getRepository(Event::class)
            ->findBy(['organizer' => $user], ['eventDate' => 'DESC']);

        return $this->render('analytics/index.html.twig', [
            'events' => $events
        ]);
    }

    #[Route('/event/{id}', name: 'event')]
    public function eventAnalytics(Event $event): Response
    {
        // Vérifier les permissions
        if ($event->getOrganizer() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        $report = $this->analyticsService->generateEventReport($event);

        return $this->render('analytics/event.html.twig', [
            'event' => $event,
            'report' => $report
        ]);
    }

    #[Route('/dashboard/{id}', name: 'dashboard')]
    public function dashboard(Event $event): Response
    {
        // Vérifier les permissions
        if ($event->getOrganizer() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        $report = $this->analyticsService->generateEventReport($event);
        $realTimeMetrics = $this->analyticsService->getRealTimeMetrics($event);

        return $this->render('analytics/dashboard.html.twig', [
            'event' => $event,
            'report' => $report,
            'realTimeMetrics' => $realTimeMetrics
        ]);
    }

    #[Route('/api/realtime/{id}', name: 'api_realtime', methods: ['GET'])]
    public function realtimeData(Event $event): JsonResponse
    {
        // Vérifier les permissions
        if ($event->getOrganizer() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['error' => 'Accès refusé'], 403);
        }

        $metrics = $this->analyticsService->getRealTimeMetrics($event);

        return new JsonResponse($metrics);
    }

    #[Route('/api/report/{id}', name: 'api_report', methods: ['GET'])]
    public function apiReport(Event $event): JsonResponse
    {
        // Vérifier les permissions
        if ($event->getOrganizer() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['error' => 'Accès refusé'], 403);
        }

        $report = $this->analyticsService->generateEventReport($event);

        return new JsonResponse($report);
    }

    #[Route('/export/{id}/{type}', name: 'export', methods: ['GET'])]
    public function export(Event $event, string $type = 'full'): Response
    {
        // Vérifier les permissions
        if ($event->getOrganizer() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        $allowedTypes = ['full', 'sales', 'attendance', 'revenue'];
        if (!in_array($type, $allowedTypes)) {
            throw $this->createNotFoundException('Type d\'export non valide');
        }

        $csv = $this->analyticsService->exportToCSV($event, $type);
        $filename = sprintf('analytics_%s_%s_%s.csv', 
            $type,
            $event->getId(), 
            (new \DateTime())->format('Y-m-d_H-i-s')
        );

        $response = new Response($csv);
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }

    #[Route('/pdf/{id}', name: 'pdf', methods: ['GET'])]
    public function generatePDF(Event $event): Response
    {
        // Vérifier les permissions
        if ($event->getOrganizer() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        $html = $this->analyticsService->generatePDFReport($event);
        $filename = sprintf('rapport_%s_%s.html', 
            $event->getId(), 
            (new \DateTime())->format('Y-m-d_H-i-s')
        );

        $response = new Response($html);
        $response->headers->set('Content-Type', 'text/html; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }

    #[Route('/compare', name: 'compare')]
    public function compare(Request $request): Response
    {
        $user = $this->getUser();
        $eventIds = $request->query->get('events', []);
        
        if (empty($eventIds) || !is_array($eventIds)) {
            $this->addFlash('warning', 'Veuillez sélectionner au moins un événement à comparer');
            return $this->redirectToRoute('analytics_index');
        }

        $events = $this->em->getRepository(Event::class)
            ->createQueryBuilder('e')
            ->where('e.id IN (:ids)')
            ->andWhere('e.organizer = :organizer')
            ->setParameter('ids', $eventIds)
            ->setParameter('organizer', $user)
            ->getQuery()
            ->getResult();

        $comparisons = [];
        foreach ($events as $event) {
            $comparisons[] = $this->analyticsService->generateEventReport($event);
        }

        return $this->render('analytics/compare.html.twig', [
            'comparisons' => $comparisons
        ]);
    }

    #[Route('/trends', name: 'trends')]
    public function trends(): Response
    {
        $user = $this->getUser();
        
        // Récupérer tous les événements de l'organisateur
        $events = $this->em->getRepository(Event::class)
            ->findBy(['organizer' => $user], ['eventDate' => 'ASC']);

        // Calculer les tendances globales
        $trends = $this->calculateTrends($events);

        return $this->render('analytics/trends.html.twig', [
            'events' => $events,
            'trends' => $trends
        ]);
    }

    private function calculateTrends(array $events): array
    {
        $trends = [
            'totalEvents' => count($events),
            'totalRevenue' => 0,
            'totalTicketsSold' => 0,
            'avgOccupancyRate' => 0,
            'monthlyTrends' => [],
            'categoryPerformance' => []
        ];

        $monthlyData = [];
        $categoryData = [];

        foreach ($events as $event) {
            $revenue = $this->analyticsService->generateEventReport($event)['overview']['totalRevenue'];
            $trends['totalRevenue'] += $revenue;
            $trends['totalTicketsSold'] += $event->getSoldTickets();
            $trends['avgOccupancyRate'] += $event->getOccupancyRate();

            // Données mensuelles
            $month = $event->getEventDate()->format('Y-m');
            if (!isset($monthlyData[$month])) {
                $monthlyData[$month] = ['events' => 0, 'revenue' => 0, 'tickets' => 0];
            }
            $monthlyData[$month]['events']++;
            $monthlyData[$month]['revenue'] += $revenue;
            $monthlyData[$month]['tickets'] += $event->getSoldTickets();

            // Données par catégorie
            $category = $event->getCategory();
            if (!isset($categoryData[$category])) {
                $categoryData[$category] = ['events' => 0, 'revenue' => 0, 'tickets' => 0];
            }
            $categoryData[$category]['events']++;
            $categoryData[$category]['revenue'] += $revenue;
            $categoryData[$category]['tickets'] += $event->getSoldTickets();
        }

        if (count($events) > 0) {
            $trends['avgOccupancyRate'] /= count($events);
        }

        $trends['monthlyTrends'] = $monthlyData;
        $trends['categoryPerformance'] = $categoryData;

        return $trends;
    }
}