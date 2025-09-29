<?php

namespace App\Controller;

use App\Service\OpenAIService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class OpenAIController extends AbstractController
{
    #[Route('/open/a/i', name: 'app_open_a_i')]
    public function index(OpenAIService $openAI): Response
    {
        $result = $openAI->chat("Bonjour ChatGPT, aide-moi à intégrer l'API dans Symfony !");

        return $this->json($result);
    }
}
