<?php

namespace App\Controller;

use App\Entity\Ticket;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\ErrorCorrectionLevel;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;


class TicketController extends AbstractController
{
    #[Route('/ticket/{qrCode}', name: 'ticket_show')]
    public function show(string $qrCode, EntityManagerInterface $em): Response
    {
        $ticket = $em->getRepository(Ticket::class)->findOneBy(['qrCode' => $qrCode]);

        if (!$ticket) {
            throw $this->createNotFoundException('Ticket non trouvé.');
        }

        return $this->render('ticket/show.html.twig', [
            'ticket' => $ticket,
        ]);
    }

    #[Route('/ticket/verify/{qrCode}', name: 'ticket_verify')]
    public function verify(string $qrCode, EntityManagerInterface $em): Response
    {
        $ticket = $em->getRepository(Ticket::class)->findOneBy(['qrCode' => $qrCode]);

        if (!$ticket) {
            return $this->render('ticket/verify.html.twig', [
                'status' => 'invalid',
                'message' => '❌ Ce ticket est invalide ou n’existe pas.',
            ]);
        }

        if ($ticket->isCancelled()) {
            return $this->render('ticket/verify.html.twig', [
                'status' => 'cancelled',
                'message' => '⚠️ Ce ticket a été annulé.',
            ]);
        }

        if ($ticket->isUsed()) {
            return $this->render('ticket/verify.html.twig', [
                'status' => 'used',
                'message' => '❌ Ce ticket a déjà été utilisé le ' . $ticket->getUsedAt()?->format('d/m/Y H:i'),
            ]);
        }

        // Marquer le ticket comme utilisé (optionnel)
        $ticket->setIsUsed(true);
        $ticket->setStatus('used');
        $ticket->setUsedAt(new \DateTimeImmutable());
        $em->flush();

        return $this->render('ticket/verify.html.twig', [
            'status' => 'valid',
            'ticket' => $ticket,
            'message' => '✅ Ticket valide. Accès autorisé.',
        ]);
    }

    #[Route('/ticket/qrcode/{qrCode}', name: 'ticket_qrcode')]
    public function generateQrCode(string $qrCode): Response
    {
        $validationUrl = $this->generateUrl('ticket_verify', ['qrCode' => $qrCode], UrlGeneratorInterface::ABSOLUTE_URL);

        $builder = new Builder(
            writer: new PngWriter(),
            data: $validationUrl,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 300,
            margin: 10,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        );

        $qr = $builder->build();

        return new Response($qr->getString(), 200, [
            'Content-Type' => $qr->getMimeType(),
        ]);
    }


}
