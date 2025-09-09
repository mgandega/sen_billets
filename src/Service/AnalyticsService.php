<?php

namespace App\Service;

use App\Entity\Event;
use App\Entity\User;
use App\Entity\Ticket;
use App\Entity\Payment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

class AnalyticsService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * Générer un rapport complet pour un événement
     */
    public function generateEventReport(Event $event): array
    {
        return [
            'event' => $event,
            'overview' => $this->getEventOverview($event),
            'sales' => $this->getSalesAnalytics($event),
            'attendance' => $this->getAttendanceAnalytics($event),
            'revenue' => $this->getRevenueAnalytics($event),
            'demographics' => $this->getDemographicsAnalytics($event),
            'timeline' => $this->getTimelineAnalytics($event),
            'performance' => $this->getPerformanceMetrics($event),
            'comparisons' => $this->getComparisonData($event),
            'predictions' => $this->getPredictions($event),
            'generatedAt' => new \DateTime()
        ];
    }

    /**
     * Vue d'ensemble de l'événement
     */
    private function getEventOverview(Event $event): array
    {
        $totalTickets = $event->getCapacity();
        $soldTickets = $event->getSoldTickets();
        $validatedTickets = $this->getValidatedTicketsCount($event);
        $revenue = $this->getTotalRevenue($event);

        return [
            'capacity' => $totalTickets,
            'sold' => $soldTickets,
            'validated' => $validatedTickets,
            'available' => $totalTickets - $soldTickets,
            'occupancyRate' => $totalTickets > 0 ? ($soldTickets / $totalTickets) * 100 : 0,
            'validationRate' => $soldTickets > 0 ? ($validatedTickets / $soldTickets) * 100 : 0,
            'totalRevenue' => $revenue,
            'averageTicketPrice' => $soldTickets > 0 ? $revenue / $soldTickets : 0,
            'status' => $this->getEventStatus($event)
        ];
    }

    /**
     * Analytics des ventes
     */
    private function getSalesAnalytics(Event $event): array
    {
        // Ventes par jour
        $salesByDay = $this->entityManager->getRepository(Ticket::class)
            ->createQueryBuilder('t')
            ->select('DATE(t.purchasedAt) as date, COUNT(t.id) as count, SUM(tt.price) as revenue')
            ->join('t.ticketType', 'tt')
            ->where('t.event = :event')
            ->setParameter('event', $event)
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->getQuery()
            ->getResult();

        // Ventes par heure
        $salesByHour = $this->entityManager->getRepository(Ticket::class)
            ->createQueryBuilder('t')
            ->select('HOUR(t.purchasedAt) as hour, COUNT(t.id) as count')
            ->where('t.event = :event')
            ->setParameter('event', $event)
            ->groupBy('hour')
            ->orderBy('hour', 'ASC')
            ->getQuery()
            ->getResult();

        // Ventes par type de billet
        $salesByTicketType = $this->entityManager->getRepository(Ticket::class)
            ->createQueryBuilder('t')
            ->select('tt.name, COUNT(t.id) as sold, SUM(tt.price) as revenue, tt.price as unitPrice')
            ->join('t.ticketType', 'tt')
            ->where('t.event = :event')
            ->setParameter('event', $event)
            ->groupBy('tt.id')
            ->getQuery()
            ->getResult();

        return [
            'byDay' => $salesByDay,
            'byHour' => $salesByHour,
            'byTicketType' => $salesByTicketType,
            'peakSalesDay' => $this->getPeakSalesDay($salesByDay),
            'peakSalesHour' => $this->getPeakSalesHour($salesByHour),
            'bestSellingTicketType' => $this->getBestSellingTicketType($salesByTicketType)
        ];
    }

    /**
     * Analytics de présence
     */
    private function getAttendanceAnalytics(Event $event): array
    {
        // Validations par heure
        $validationsByHour = $this->entityManager->getRepository(Ticket::class)
            ->createQueryBuilder('t')
            ->select('HOUR(t.usedAt) as hour, COUNT(t.id) as count')
            ->where('t.event = :event')
            ->andWhere('t.status = :status')
            ->setParameter('event', $event)
            ->setParameter('status', 'used')
            ->groupBy('hour')
            ->orderBy('hour', 'ASC')
            ->getQuery()
            ->getResult();

        // Taux de présence par type de billet
        $attendanceByTicketType = $this->entityManager->getRepository(Ticket::class)
            ->createQueryBuilder('t')
            ->select('tt.name, 
                     COUNT(t.id) as total,
                     SUM(CASE WHEN t.status = \'used\' THEN 1 ELSE 0 END) as attended')
            ->join('t.ticketType', 'tt')
            ->where('t.event = :event')
            ->setParameter('event', $event)
            ->groupBy('tt.id')
            ->getQuery()
            ->getResult();

        return [
            'byHour' => $validationsByHour,
            'byTicketType' => $attendanceByTicketType,
            'peakAttendanceHour' => $this->getPeakAttendanceHour($validationsByHour),
            'noShowRate' => $this->getNoShowRate($event)
        ];
    }

    /**
     * Analytics de revenus
     */
    private function getRevenueAnalytics(Event $event): array
    {
        // Revenus par jour
        $revenueByDay = $this->entityManager->getRepository(Ticket::class)
            ->createQueryBuilder('t')
            ->select('DATE(t.purchasedAt) as date, SUM(tt.price) as revenue, COUNT(t.id) as tickets')
            ->join('t.ticketType', 'tt')
            ->where('t.event = :event')
            ->setParameter('event', $event)
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->getQuery()
            ->getResult();

        // Revenus par méthode de paiement
        $revenueByPaymentMethod = $this->entityManager->getRepository(Payment::class)
            ->createQueryBuilder('p')
            ->select('p.method, SUM(p.amount) as revenue, COUNT(p.id) as transactions')
            ->join('p.tickets', 't')
            ->where('t.event = :event')
            ->andWhere('p.status = :status')
            ->setParameter('event', $event)
            ->setParameter('status', Payment::STATUS_COMPLETED)
            ->groupBy('p.method')
            ->getQuery()
            ->getResult();

        $totalRevenue = $this->getTotalRevenue($event);
        $projectedRevenue = $this->getProjectedRevenue($event);

        return [
            'total' => $totalRevenue,
            'projected' => $projectedRevenue,
            'byDay' => $revenueByDay,
            'byPaymentMethod' => $revenueByPaymentMethod,
            'averageOrderValue' => $this->getAverageOrderValue($event),
            'revenueGrowthRate' => $this->getRevenueGrowthRate($event)
        ];
    }

    /**
     * Analytics démographiques
     */
    private function getDemographicsAnalytics(Event $event): array
    {
        // Répartition par domaine email (approximation géographique)
        $emailDomains = $this->entityManager->getRepository(Ticket::class)
            ->createQueryBuilder('t')
            ->select('SUBSTRING(t.customerEmail, LOCATE(\'@\', t.customerEmail) + 1) as domain, COUNT(t.id) as count')
            ->where('t.event = :event')
            ->setParameter('event', $event)
            ->groupBy('domain')
            ->orderBy('count', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        // Analyse des noms (approximation genre)
        $nameAnalysis = $this->analyzeCustomerNames($event);

        return [
            'emailDomains' => $emailDomains,
            'nameAnalysis' => $nameAnalysis,
            'uniqueCustomers' => $this->getUniqueCustomersCount($event),
            'repeatCustomers' => $this->getRepeatCustomersCount($event)
        ];
    }

    /**
     * Analytics temporelles
     */
    private function getTimelineAnalytics(Event $event): array
    {
        $eventDate = $event->getEventDate();
        $now = new \DateTime();

        // Ventes par semaine avant l'événement
        $salesTimeline = [];
        for ($i = 12; $i >= 0; $i--) {
            $weekStart = clone $eventDate;
            $weekStart->sub(new \DateInterval('P' . ($i * 7) . 'D'));
            $weekEnd = clone $weekStart;
            $weekEnd->add(new \DateInterval('P7D'));

            $sales = $this->entityManager->getRepository(Ticket::class)
                ->createQueryBuilder('t')
                ->select('COUNT(t.id) as count, SUM(tt.price) as revenue')
                ->join('t.ticketType', 'tt')
                ->where('t.event = :event')
                ->andWhere('t.purchasedAt BETWEEN :start AND :end')
                ->setParameter('event', $event)
                ->setParameter('start', $weekStart)
                ->setParameter('end', $weekEnd)
                ->getQuery()
                ->getSingleResult();

            $salesTimeline[] = [
                'week' => $weekStart->format('W'),
                'date' => $weekStart->format('Y-m-d'),
                'sales' => (int) $sales['count'],
                'revenue' => (float) $sales['revenue']
            ];
        }

        return [
            'salesTimeline' => $salesTimeline,
            'daysUntilEvent' => $now < $eventDate ? $now->diff($eventDate)->days : 0,
            'salesVelocity' => $this->getSalesVelocity($event),
            'lastMinuteSales' => $this->getLastMinuteSales($event)
        ];
    }

    /**
     * Métriques de performance
     */
    private function getPerformanceMetrics(Event $event): array
    {
        $organizer = $event->getOrganizer();
        $organizerEvents = $this->entityManager->getRepository(Event::class)
            ->findBy(['organizer' => $organizer]);

        $avgOccupancy = 0;
        $avgRevenue = 0;
        $totalEvents = count($organizerEvents);

        foreach ($organizerEvents as $orgEvent) {
            $avgOccupancy += $orgEvent->getOccupancyRate();
            $avgRevenue += $this->getTotalRevenue($orgEvent);
        }

        if ($totalEvents > 0) {
            $avgOccupancy /= $totalEvents;
            $avgRevenue /= $totalEvents;
        }

        return [
            'occupancyVsAverage' => $event->getOccupancyRate() - $avgOccupancy,
            'revenueVsAverage' => $this->getTotalRevenue($event) - $avgRevenue,
            'categoryRanking' => $this->getCategoryRanking($event),
            'marketShare' => $this->getMarketShare($event),
            'competitorAnalysis' => $this->getCompetitorAnalysis($event)
        ];
    }

    /**
     * Données de comparaison
     */
    private function getComparisonData(Event $event): array
    {
        // Comparer avec des événements similaires
        $similarEvents = $this->entityManager->getRepository(Event::class)
            ->createQueryBuilder('e')
            ->where('e.category = :category')
            ->andWhere('e.id != :eventId')
            ->andWhere('e.status = :status')
            ->setParameter('category', $event->getCategory())
            ->setParameter('eventId', $event->getId())
            ->setParameter('status', 'published')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        $comparisons = [];
        foreach ($similarEvents as $similarEvent) {
            $comparisons[] = [
                'event' => $similarEvent,
                'occupancyRate' => $similarEvent->getOccupancyRate(),
                'revenue' => $this->getTotalRevenue($similarEvent),
                'avgTicketPrice' => $this->getAverageTicketPrice($similarEvent)
            ];
        }

        return [
            'similarEvents' => $comparisons,
            'industryBenchmarks' => $this->getIndustryBenchmarks($event->getCategory()),
            'seasonalTrends' => $this->getSeasonalTrends($event->getCategory())
        ];
    }

    /**
     * Prédictions et projections
     */
    private function getPredictions(Event $event): array
    {
        $now = new \DateTime();
        $eventDate = $event->getEventDate();

        if ($now >= $eventDate) {
            return ['message' => 'Événement terminé - pas de prédictions disponibles'];
        }

        $daysRemaining = $now->diff($eventDate)->days;
        $currentSales = $event->getSoldTickets();
        $salesVelocity = $this->getSalesVelocity($event);

        $projectedSales = $currentSales + ($salesVelocity * $daysRemaining);
        $projectedOccupancy = min(100, ($projectedSales / $event->getCapacity()) * 100);

        return [
            'projectedSales' => (int) $projectedSales,
            'projectedOccupancy' => $projectedOccupancy,
            'projectedRevenue' => $this->getProjectedRevenue($event),
            'sellOutDate' => $this->predictSellOutDate($event),
            'recommendations' => $this->getRecommendations($event)
        ];
    }

    /**
     * Générer un rapport PDF
     */
    public function generatePDFReport(Event $event): string
    {
        $report = $this->generateEventReport($event);
        
        // Ici vous pourriez utiliser une bibliothèque comme TCPDF ou DomPDF
        // Pour cet exemple, on retourne du HTML qui peut être converti en PDF
        
        return $this->renderReportHTML($report);
    }

    /**
     * Exporter les données en CSV
     */
    public function exportToCSV(Event $event, string $type = 'full'): string
    {
        $report = $this->generateEventReport($event);
        
        switch ($type) {
            case 'sales':
                return $this->exportSalesCSV($report['sales']);
            case 'attendance':
                return $this->exportAttendanceCSV($report['attendance']);
            case 'revenue':
                return $this->exportRevenueCSV($report['revenue']);
            default:
                return $this->exportFullCSV($report);
        }
    }

    /**
     * Obtenir les métriques en temps réel
     */
    public function getRealTimeMetrics(Event $event): array
    {
        return [
            'currentAttendees' => $this->getCurrentAttendees($event),
            'recentSales' => $this->getRecentSales($event, 60), // Dernière heure
            'recentValidations' => $this->getRecentValidations($event, 60),
            'salesRate' => $this->getCurrentSalesRate($event),
            'validationRate' => $this->getCurrentValidationRate($event),
            'timestamp' => new \DateTime()
        ];
    }

    // Méthodes utilitaires privées

    private function getValidatedTicketsCount(Event $event): int
    {
        return $this->entityManager->getRepository(Ticket::class)
            ->count(['event' => $event, 'status' => 'used']);
    }

    private function getTotalRevenue(Event $event): float
    {
        $result = $this->entityManager->getRepository(Ticket::class)
            ->createQueryBuilder('t')
            ->select('SUM(tt.price) as revenue')
            ->join('t.ticketType', 'tt')
            ->where('t.event = :event')
            ->setParameter('event', $event)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    private function getProjectedRevenue(Event $event): float
    {
        $salesVelocity = $this->getSalesVelocity($event);
        $avgTicketPrice = $this->getAverageTicketPrice($event);
        $daysRemaining = max(0, (new \DateTime())->diff($event->getEventDate())->days);
        
        return $this->getTotalRevenue($event) + ($salesVelocity * $avgTicketPrice * $daysRemaining);
    }

    private function getSalesVelocity(Event $event): float
    {
        // Ventes par jour sur les 7 derniers jours
        $sevenDaysAgo = new \DateTime('-7 days');
        
        $recentSales = $this->entityManager->getRepository(Ticket::class)
            ->createQueryBuilder('t')
            ->select('COUNT(t.id) as count')
            ->where('t.event = :event')
            ->andWhere('t.purchasedAt >= :date')
            ->setParameter('event', $event)
            ->setParameter('date', $sevenDaysAgo)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) $recentSales / 7;
    }

    private function getAverageTicketPrice(Event $event): float
    {
        $result = $this->entityManager->getRepository(Ticket::class)
            ->createQueryBuilder('t')
            ->select('AVG(tt.price) as avgPrice')
            ->join('t.ticketType', 'tt')
            ->where('t.event = :event')
            ->setParameter('event', $event)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    private function getEventStatus(Event $event): string
    {
        $now = new \DateTime();
        $eventDate = $event->getEventDate();
        
        if ($now < $eventDate) {
            $daysUntil = $now->diff($eventDate)->days;
            if ($daysUntil > 30) return 'En vente';
            if ($daysUntil > 7) return 'Bientôt';
            return 'Imminent';
        } elseif ($now->format('Y-m-d') === $eventDate->format('Y-m-d')) {
            return 'En cours';
        } else {
            return 'Terminé';
        }
    }

    private function renderReportHTML(array $report): string
    {
        // Template HTML pour le rapport PDF
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <title>Rapport - {$report['event']->getTitle()}</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; margin-bottom: 30px; }
                .metric { display: inline-block; margin: 10px; padding: 15px; border: 1px solid #ddd; }
                .chart { margin: 20px 0; }
                table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>Rapport d'Analytics</h1>
                <h2>{$report['event']->getTitle()}</h2>
                <p>Généré le {$report['generatedAt']->format('d/m/Y à H:i')}</p>
            </div>
            
            <div class='overview'>
                <h3>Vue d'ensemble</h3>
                <div class='metric'>
                    <strong>Capacité:</strong> {$report['overview']['capacity']}
                </div>
                <div class='metric'>
                    <strong>Vendus:</strong> {$report['overview']['sold']}
                </div>
                <div class='metric'>
                    <strong>Taux d'occupation:</strong> " . round($report['overview']['occupancyRate'], 1) . "%
                </div>
                <div class='metric'>
                    <strong>Revenus:</strong> " . number_format($report['overview']['totalRevenue'], 0, ',', ' ') . " FCFA
                </div>
            </div>
            
            <!-- Ajouter d'autres sections du rapport -->
            
        </body>
        </html>";
    }

    private function exportFullCSV(array $report): string
    {
        $csv = "Métrique,Valeur\n";
        $csv .= "Événement,{$report['event']->getTitle()}\n";
        $csv .= "Capacité,{$report['overview']['capacity']}\n";
        $csv .= "Billets vendus,{$report['overview']['sold']}\n";
        $csv .= "Taux d'occupation," . round($report['overview']['occupancyRate'], 2) . "%\n";
        $csv .= "Revenus total," . $report['overview']['totalRevenue'] . "\n";
        
        return $csv;
    }

    // Autres méthodes utilitaires...
    private function getPeakSalesDay(array $salesByDay): ?array
    {
        if (empty($salesByDay)) return null;
        
        return array_reduce($salesByDay, function($max, $day) {
            return ($max === null || $day['count'] > $max['count']) ? $day : $max;
        });
    }

    private function getPeakSalesHour(array $salesByHour): ?array
    {
        if (empty($salesByHour)) return null;
        
        return array_reduce($salesByHour, function($max, $hour) {
            return ($max === null || $hour['count'] > $max['count']) ? $hour : $max;
        });
    }

    private function getBestSellingTicketType(array $salesByTicketType): ?array
    {
        if (empty($salesByTicketType)) return null;
        
        return array_reduce($salesByTicketType, function($max, $type) {
            return ($max === null || $type['sold'] > $max['sold']) ? $type : $max;
        });
    }

    private function getPeakAttendanceHour(array $validationsByHour): ?array
    {
        if (empty($validationsByHour)) return null;
        
        return array_reduce($validationsByHour, function($max, $hour) {
            return ($max === null || $hour['count'] > $max['count']) ? $hour : $max;
        });
    }

    private function getNoShowRate(Event $event): float
    {
        $sold = $event->getSoldTickets();
        $validated = $this->getValidatedTicketsCount($event);
        
        return $sold > 0 ? (($sold - $validated) / $sold) * 100 : 0;
    }

    private function getAverageOrderValue(Event $event): float
    {
        // Implémentation simplifiée
        return $this->getAverageTicketPrice($event);
    }

    private function getRevenueGrowthRate(Event $event): float
    {
        // Calculer le taux de croissance des revenus
        // Implémentation simplifiée
        return 0;
    }

    private function analyzeCustomerNames(Event $event): array
    {
        // Analyse basique des prénoms pour approximer le genre
        // Implémentation simplifiée
        return [
            'maleApprox' => 45,
            'femaleApprox' => 55
        ];
    }

    private function getUniqueCustomersCount(Event $event): int
    {
        return $this->entityManager->getRepository(Ticket::class)
            ->createQueryBuilder('t')
            ->select('COUNT(DISTINCT t.customerEmail)')
            ->where('t.event = :event')
            ->setParameter('event', $event)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function getRepeatCustomersCount(Event $event): int
    {
        // Clients qui ont acheté plusieurs billets
        return 0; // Implémentation simplifiée
    }

    private function getLastMinuteSales(Event $event): int
    {
        $oneDayBefore = clone $event->getEventDate();
        $oneDayBefore->sub(new \DateInterval('P1D'));
        
        return $this->entityManager->getRepository(Ticket::class)
            ->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.event = :event')
            ->andWhere('t.purchasedAt >= :date')
            ->setParameter('event', $event)
            ->setParameter('date', $oneDayBefore)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function getCategoryRanking(Event $event): int
    {
        // Classement dans la catégorie
        return 1; // Implémentation simplifiée
    }

    private function getMarketShare(Event $event): float
    {
        // Part de marché approximative
        return 15.5; // Implémentation simplifiée
    }

    private function getCompetitorAnalysis(Event $event): array
    {
        // Analyse de la concurrence
        return []; // Implémentation simplifiée
    }

    private function getIndustryBenchmarks(string $category): array
    {
        // Benchmarks de l'industrie
        return [
            'avgOccupancyRate' => 75,
            'avgTicketPrice' => 25000,
            'avgNoShowRate' => 15
        ];
    }

    private function getSeasonalTrends(string $category): array
    {
        // Tendances saisonnières
        return []; // Implémentation simplifiée
    }

    private function predictSellOutDate(Event $event): ?\DateTime
    {
        $velocity = $this->getSalesVelocity($event);
        if ($velocity <= 0) return null;
        
        $remaining = $event->getCapacity() - $event->getSoldTickets();
        $daysToSellOut = $remaining / $velocity;
        
        $sellOutDate = new \DateTime();
        $sellOutDate->add(new \DateInterval('P' . (int)$daysToSellOut . 'D'));
        
        return $sellOutDate < $event->getEventDate() ? $sellOutDate : null;
    }

    private function getRecommendations(Event $event): array
    {
        $recommendations = [];
        $occupancy = $event->getOccupancyRate();
        $daysUntil = (new \DateTime())->diff($event->getEventDate())->days;
        
        if ($occupancy < 50 && $daysUntil > 7) {
            $recommendations[] = "Considérez une campagne marketing renforcée";
        }
        
        if ($occupancy > 90) {
            $recommendations[] = "Excellent taux de remplissage ! Préparez la gestion des foules";
        }
        
        return $recommendations;
    }

    private function getCurrentAttendees(Event $event): int
    {
        return $this->getValidatedTicketsCount($event);
    }

    private function getRecentSales(Event $event, int $minutes): int
    {
        $since = new \DateTime("-{$minutes} minutes");
        
        return $this->entityManager->getRepository(Ticket::class)
            ->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.event = :event')
            ->andWhere('t.purchasedAt >= :since')
            ->setParameter('event', $event)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function getRecentValidations(Event $event, int $minutes): int
    {
        $since = new \DateTime("-{$minutes} minutes");
        
        return $this->entityManager->getRepository(Ticket::class)
            ->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.event = :event')
            ->andWhere('t.status = :status')
            ->andWhere('t.usedAt >= :since')
            ->setParameter('event', $event)
            ->setParameter('status', 'used')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function getCurrentSalesRate(Event $event): float
    {
        return $this->getSalesVelocity($event);
    }

    private function getCurrentValidationRate(Event $event): float
    {
        // Taux de validation par heure
        $lastHour = new \DateTime('-1 hour');
        
        $validations = $this->entityManager->getRepository(Ticket::class)
            ->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.event = :event')
            ->andWhere('t.status = :status')
            ->andWhere('t.usedAt >= :since')
            ->setParameter('event', $event)
            ->setParameter('status', 'used')
            ->setParameter('since', $lastHour)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) $validations;
    }
}