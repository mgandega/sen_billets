<?php

namespace App\Command;

use App\Service\NotificationService;
use App\Repository\EventRepository;
use App\Repository\UserRepository;
use App\Repository\CartItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:send-notifications',
    description: 'Envoie les notifications automatiques (rappels, paniers abandonnés, etc.)'
)]
class SendNotificationsCommand extends Command
{
    public function __construct(
        private NotificationService $notificationService,
        private EventRepository $eventRepository,
        private UserRepository $userRepository,
        private CartItemRepository $cartItemRepository,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Envoi des notifications automatiques');

        $sentCount = 0;

        // 1. Rappels d'événements (24h avant)
        $io->section('Rappels d\'événements');
        $sentCount += $this->sendEventReminders($io);

        // 2. Paniers abandonnés (après 2h)
        $io->section('Rappels de paniers abandonnés');
        $sentCount += $this->sendAbandonedCartReminders($io);

        // 3. Recommandations hebdomadaires
        $io->section('Recommandations d\'événements');
        $sentCount += $this->sendWeeklyRecommendations($io);

        // 4. Rapports quotidiens pour organisateurs
        $io->section('Rapports quotidiens organisateurs');
        $sentCount += $this->sendDailySalesReports($io);

        $io->success("Notifications envoyées avec succès ! Total : {$sentCount}");

        return Command::SUCCESS;
    }

    private function sendEventReminders(SymfonyStyle $io): int
    {
        $tomorrow = new \DateTime('+24 hours');
        $dayAfterTomorrow = new \DateTime('+25 hours');

        $events = $this->eventRepository->createQueryBuilder('e')
            ->where('e.eventDate BETWEEN :start AND :end')
            ->andWhere('e.status = :status')
            ->setParameter('start', $tomorrow)
            ->setParameter('end', $dayAfterTomorrow)
            ->setParameter('status', 'published')
            ->getQuery()
            ->getResult();

        $sentCount = 0;

        foreach ($events as $event) {
            $tickets = $this->entityManager->getRepository('App:Ticket')
                ->findBy(['event' => $event, 'status' => 'valid']);

            $notifiedUsers = [];
            foreach ($tickets as $ticket) {
                $user = $ticket->getUser();
                if (!in_array($user->getId(), $notifiedUsers)) {
                    $this->notificationService->notifyEventReminder($event, $user);
                    $notifiedUsers[] = $user->getId();
                    $sentCount++;
                }
            }

            $io->writeln("Rappels envoyés pour {$event->getTitle()}: " . count($notifiedUsers) . " utilisateurs");
        }

        return $sentCount;
    }

    private function sendAbandonedCartReminders(SymfonyStyle $io): int
    {
        $twoHoursAgo = new \DateTime('-2 hours');

        $abandonedCarts = $this->cartItemRepository->createQueryBuilder('c')
            ->select('c.user')
            ->where('c.updatedAt < :time')
            ->setParameter('time', $twoHoursAgo)
            ->groupBy('c.user')
            ->getQuery()
            ->getResult();

        $sentCount = 0;

        foreach ($abandonedCarts as $result) {
            $user = $result['user'];
            $this->notificationService->sendAbandonedCartReminder($user);
            $sentCount++;
        }

        $io->writeln("Rappels de paniers abandonnés envoyés: {$sentCount}");

        return $sentCount;
    }

    private function sendWeeklyRecommendations(SymfonyStyle $io): int
    {
        // Envoyer seulement le dimanche
        if (date('w') !== '0') {
            $io->writeln("Recommandations hebdomadaires: pas aujourd'hui");
            return 0;
        }

        $users = $this->userRepository->findAll();
        $sentCount = 0;

        foreach ($users as $user) {
            $this->notificationService->sendEventRecommendations($user);
            $sentCount++;
        }

        $io->writeln("Recommandations hebdomadaires envoyées: {$sentCount}");

        return $sentCount;
    }

    private function sendDailySalesReports(SymfonyStyle $io): int
    {
        $organizers = $this->userRepository->createQueryBuilder('u')
            ->where('JSON_CONTAINS(u.roles, :role) = 1')
            ->setParameter('role', '"ROLE_ORGANIZER"')
            ->getQuery()
            ->getResult();

        $sentCount = 0;

        foreach ($organizers as $organizer) {
            $this->notificationService->sendDailySalesReport($organizer);
            $sentCount++;
        }

        $io->writeln("Rapports quotidiens envoyés: {$sentCount}");

        return $sentCount;
    }
}