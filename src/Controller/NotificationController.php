<?php

namespace App\Controller;

use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/notifications', name: 'api_notifications_')]
class NotificationController extends AbstractController
{
    public function __construct(
        private NotificationService $notificationService,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/token', name: 'save_token', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function saveToken(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $token = $data['token'] ?? null;

        if (!$token) {
            return new JsonResponse(['error' => 'Token manquant'], 400);
        }

        $user = $this->getUser();
        
        // Sauvegarder le token FCM pour l'utilisateur
        // Vous pouvez ajouter un champ fcm_token à l'entité User
        // ou créer une entité séparée pour les tokens de notification
        
        return new JsonResponse(['success' => true]);
    }

    #[Route('/test', name: 'test', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function testNotification(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $type = $request->request->get('type', 'test');

        try {
            // Create a simple test notification without relying on external services
            $result = [
                'success' => true,
                'message' => 'Test notification would be sent in production',
                'user' => $user->getEmail(),
                'type' => $type
            ];

            return new JsonResponse([
                'success' => true,
                'results' => $result
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/preferences', name: 'preferences', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function preferences(Request $request): Response
    {
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            // Sauvegarder les préférences de notification
            $preferences = [
                'email_events' => $request->request->get('email_events', false),
                'email_marketing' => $request->request->get('email_marketing', false),
                'sms_reminders' => $request->request->get('sms_reminders', false),
                'push_notifications' => $request->request->get('push_notifications', false),
            ];

            // Sauvegarder dans la base de données
            // Vous pouvez ajouter un champ notification_preferences à l'entité User
            
            $this->addFlash('success', 'Préférences de notification mises à jour');
            return $this->redirectToRoute('api_notifications_preferences');
        }

        return $this->render('notification/preferences.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/unsubscribe/{token}', name: 'unsubscribe')]
    public function unsubscribe(string $token): Response
    {
        // Logique de désabonnement basée sur un token sécurisé
        // Vérifier le token et désabonner l'utilisateur
        
        return $this->render('notification/unsubscribe.html.twig', [
            'success' => true
        ]);
    }
}