<?php

namespace App\Service;

use App\Entity\Event;
use App\Entity\Payment;
use App\Entity\Ticket;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class NotificationService
{
    private const SMS_API_URL = 'https://api.orange.com/smsmessaging/v1';
    private const PUSH_API_URL = 'https://fcm.googleapis.com/fcm/send';

    public function __construct(
        private MailerInterface $mailer,
        private HttpClientInterface $httpClient,
        private EntityManagerInterface $entityManager,
        private NotificationTemplateManager $templateManager,
        private ?string $smsApiKey = null,
        private ?string $pushApiKey = null,
        private string $fromEmail = 'noreply@sen-billets.sn', 
        private string $fromName = 'Sen-Billets'
    ) {}

    /**
     * Envoyer une notification complète (email + SMS + push)
     */
    public function sendNotification(
        User $user,
        string $type,
        array $data = [],
        array $channels = ['email', 'sms', 'push']
    ): array {
        $results = [];

        if (in_array('email', $channels)) {
            $results['email'] = $this->sendEmail($user, $type, $data);
        }

        if (in_array('sms', $channels) && $user->getPhone()) {
            $results['sms'] = $this->sendSMS($user, $type, $data);
        }

        if (in_array('push', $channels)) {
            $results['push'] = $this->sendPushNotification($user, $type, $data);
        }

        return $results;
    }

    /**
     * Envoyer un email templated
     */
    public function sendEmail(User $user, string $type, array $data = []): bool
    {
        try {
            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($user->getEmail(), $user->getName()))
                ->subject($this->getEmailSubject($type, $data))
                ->htmlTemplate($this->getEmailTemplate($type))
                ->context(array_merge($data, [
                    'user' => $user,
                    'app_name' => 'Sen-Billets',
                    'app_url' => 'https://sen-billets.sn'
                ]));

            $this->mailer->send($email);
            return true;
        } catch (\Exception $e) {
            error_log("Erreur envoi email: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Envoyer un SMS
     */
    public function sendSMS(User $user, string $type, array $data = []): bool
    {
        if (!$user->getPhone()) {
            return false;
        }

        try {
            $message = $this->templateManager->getSMSTemplate($type, $data);
            
            $response = $this->httpClient->request('POST', self::SMS_API_URL . '/outbound/tel%3A%2B221XXXXXXXX/requests', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->smsApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'outboundSMSMessageRequest' => [
                        'address' => 'tel:' . $user->getPhone(),
                        'senderAddress' => 'tel:+221XXXXXXXX',
                        'outboundSMSTextMessage' => [
                            'message' => $message
                        ]
                    ]
                ]
            ]);

            return $response->getStatusCode() === 201;
        } catch (\Exception $e) {
            error_log("Erreur envoi SMS: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Envoyer une notification push
     */
    public function sendPushNotification(User $user, string $type, array $data = []): bool
    {
        try {
            $pushData = $this->templateManager->getPushTemplate($type, $data);
            
            $response = $this->httpClient->request('POST', self::PUSH_API_URL, [
                'headers' => [
                    'Authorization' => 'key=' . $this->pushApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'to' => '/topics/user_' . $user->getId(),
                    'notification' => [
                        'title' => $pushData['title'],
                        'body' => $pushData['body'],
                        'icon' => '/assets/images/logo.png',
                        'click_action' => $pushData['url'] ?? 'https://sen-billets.sn'
                    ],
                    'data' => $data
                ]
            ]);

            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            error_log("Erreur notification push: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Notifications spécifiques aux événements
     */
    public function notifyTicketPurchase(Ticket $ticket): array
    {
        return $this->sendNotification(
            $ticket->getUser(),
            'ticket_purchase',
            [
                'ticket' => $ticket,
                'event' => $ticket->getEvent(),
                'qr_code_url' => $this->generateQRCodeUrl($ticket->getQrCode())
            ]
        );
    }

    public function notifyPaymentConfirmation(Payment $payment): array
    {
        return $this->sendNotification(
            $payment->getUser(),
            'payment_confirmation',
            [
                'payment' => $payment,
                'amount' => $payment->getFormattedAmount()
            ]
        );
    }

    public function notifyEventReminder(Event $event, User $user): array
    {
        $hoursUntilEvent = $this->getHoursUntilEvent($event);
        
        return $this->sendNotification(
            $user,
            'event_reminder',
            [
                'event' => $event,
                'hours_until' => $hoursUntilEvent,
                'user_tickets' => $this->getUserTicketsForEvent($user, $event)
            ]
        );
    }

    public function notifyEventUpdate(Event $event, string $updateType, array $changes = []): void
    {
        $attendees = $this->getEventAttendees($event);
        
        foreach ($attendees as $user) {
            $this->sendNotification(
                $user,
                'event_update',
                [
                    'event' => $event,
                    'update_type' => $updateType,
                    'changes' => $changes
                ],
                ['email', 'push'] // Pas de SMS pour les mises à jour
            );
        }
    }

    public function notifyEventCancellation(Event $event): void
    {
        $attendees = $this->getEventAttendees($event);
        
        foreach ($attendees as $user) {
            $this->sendNotification(
                $user,
                'event_cancellation',
                [
                    'event' => $event,
                    'refund_info' => 'Vous serez remboursé sous 3-5 jours ouvrables.'
                ]
            );
        }
    }

    /**
     * Campagnes marketing automatisées
     */
    public function sendWelcomeSequence(User $user): void
    {
        // Email de bienvenue immédiat
        $this->sendEmail($user, 'welcome', []);

        // Programmer les emails de suivi (à implémenter avec un système de queue)
        $this->scheduleEmail($user, 'welcome_day_3', [], '+3 days');
        $this->scheduleEmail($user, 'welcome_week_1', [], '+1 week');
    }

    public function sendEventRecommendations(User $user): void
    {
        $recommendedEvents = $this->getRecommendedEvents($user);
        
        if (!empty($recommendedEvents)) {
            $this->sendEmail($user, 'event_recommendations', [
                'events' => $recommendedEvents
            ]);
        }
    }

    public function sendAbandonedCartReminder(User $user): void
    {
        $cartItems = $this->entityManager->getRepository('App:CartItem')
            ->findBy(['user' => $user]);

        if (!empty($cartItems)) {
            $this->sendNotification(
                $user,
                'abandoned_cart',
                [
                    'cart_items' => $cartItems,
                    'cart_total' => $user->getCartTotal()
                ],
                ['email', 'push']
            );
        }
    }

    /**
     * Notifications pour organisateurs
     */
    public function notifyOrganizerNewSale(Event $event, Ticket $ticket): void
    {
        $this->sendNotification(
            $event->getOrganizer(),
            'new_sale',
            [
                'event' => $event,
                'ticket' => $ticket,
                'total_sold' => $event->getSoldTickets(),
                'revenue' => $event->getTotalRevenue()
            ],
            ['email', 'push']
        );
    }

    public function sendDailySalesReport(User $organizer): void
    {
        $events = $this->entityManager->getRepository('App:Event')
            ->findByOrganizer($organizer);

        $salesData = $this->calculateDailySales($events);

        if ($salesData['total_sales'] > 0) {
            $this->sendEmail($organizer, 'daily_sales_report', [
                'sales_data' => $salesData,
                'events' => $events
            ]);
        }
    }

    /**
     * Méthodes utilitaires
     */
    private function getEmailSubject(string $type, array $data): string
    {
        return match($type) {
            'welcome' => '🎫 Bienvenue sur Sen-Billets !',
            'ticket_purchase' => '✅ Vos billets pour ' . ($data['event']->getTitle() ?? 'votre événement'),
            'payment_confirmation' => '💳 Paiement confirmé - ' . ($data['amount'] ?? ''),
            'event_reminder' => '⏰ Votre événement commence bientôt !',
            'event_update' => '📝 Mise à jour de votre événement',
            'event_cancellation' => '❌ Événement annulé - Remboursement en cours',
            'abandoned_cart' => '🛒 Vous avez oublié vos billets !',
            'event_recommendations' => '🎯 Événements recommandés pour vous',
            'new_sale' => '🎉 Nouvelle vente pour votre événement !',
            'daily_sales_report' => '📊 Rapport de ventes quotidien',
            default => 'Notification Sen-Billets'
        };
    }

    private function getEmailTemplate(string $type): string
    {
        return match($type) {
            'welcome' => 'emails/welcome.html.twig',
            'ticket_purchase' => 'emails/ticket_purchase.html.twig',
            'payment_confirmation' => 'emails/payment_confirmation.html.twig',
            'event_reminder' => 'emails/event_reminder.html.twig',
            'event_update' => 'emails/event_update.html.twig',
            'event_cancellation' => 'emails/event_cancellation.html.twig',
            'abandoned_cart' => 'emails/abandoned_cart.html.twig',
            'event_recommendations' => 'emails/event_recommendations.html.twig',
            'new_sale' => 'emails/new_sale.html.twig',
            'daily_sales_report' => 'emails/daily_sales_report.html.twig',
            default => 'emails/default.html.twig'
        };
    }

    private function generateQRCodeUrl(string $qrCode): string
    {
        return "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($qrCode);
    }

    private function getHoursUntilEvent(Event $event): int
    {
        $now = new \DateTime();
        $eventDate = $event->getEventDate();
        $diff = $now->diff($eventDate);
        
        return ($diff->days * 24) + $diff->h;
    }

    private function getUserTicketsForEvent(User $user, Event $event): array
    {
        return $this->entityManager->getRepository('App:Ticket')
            ->findBy(['user' => $user, 'event' => $event]);
    }

    private function getEventAttendees(Event $event): array
    {
        $tickets = $this->entityManager->getRepository('App:Ticket')
            ->findBy(['event' => $event]);

        $attendees = [];
        foreach ($tickets as $ticket) {
            $user = $ticket->getUser();
            if (!in_array($user, $attendees)) {
                $attendees[] = $user;
            }
        }

        return $attendees;
    }

    private function getRecommendedEvents(User $user): array
    {
        // Logique de recommandation basée sur l'historique
        return $this->entityManager->getRepository('App:Event')
            ->findPublishedEvents();
    }

    private function calculateDailySales(array $events): array
    {
        $today = new \DateTime('today');
        $totalSales = 0;
        $totalRevenue = 0;

        foreach ($events as $event) {
            $dailyTickets = $this->entityManager->getRepository('App:Ticket')
                ->createQueryBuilder('t')
                ->where('t.event = :event')
                ->andWhere('t.purchasedAt >= :today')
                ->setParameter('event', $event)
                ->setParameter('today', $today)
                ->getQuery()
                ->getResult();

            $totalSales += count($dailyTickets);
            foreach ($dailyTickets as $ticket) {
                $totalRevenue += $ticket->getTicketType()->getPrice();
            }
        }

        return [
            'total_sales' => $totalSales,
            'total_revenue' => $totalRevenue,
            'date' => $today->format('d/m/Y')
        ];
    }

    private function scheduleEmail(User $user, string $type, array $data, string $delay): void
    {
        // À implémenter avec Symfony Messenger ou un système de queue
        // Pour l'instant, on peut utiliser une table de tâches programmées
    }
}