<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\Ticket;
use App\Entity\Payment;
use App\Entity\CartItem;
use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\ErrorCorrectionLevel;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PaymentService
{
    private EntityManagerInterface $em;
    private string $projectDir;
    private Filesystem $filesystem;
    private PdfGenerator $pdfGenerator;
    private UrlGeneratorInterface $urlGenerator;
    private string $paydunyaMasterKey;
    private string $paydunyaPrivateKey;
    private string $paydunyaPublicKey;
    private string $paydunyaToken;
    private string $paydunyaMode;

    public function __construct(
        EntityManagerInterface $em,
        KernelInterface $kernel,
        PdfGenerator $pdfGenerator,
        UrlGeneratorInterface $urlGenerator,
        string $paydunyaMasterKey,
        string $paydunyaPrivateKey,
        string $paydunyaPublicKey,
        string $paydunyaToken,
        string $paydunyaMode
        ) {
            $this->em = $em;
            $this->projectDir = $kernel->getProjectDir();
            $this->filesystem = new Filesystem();
            $this->pdfGenerator = $pdfGenerator; 
            $this->urlGenerator = $urlGenerator;
            $this->paydunyaMasterKey = $paydunyaMasterKey;
            $this->paydunyaPrivateKey = $paydunyaPrivateKey;
            $this->paydunyaPublicKey = $paydunyaPublicKey;
            $this->paydunyaToken = $paydunyaToken;
            $this->paydunyaMode = $paydunyaMode;
        }
        public function processPayment(
            array $cartItems,
            User $user,
            string $paymentMethod,
            float $amount,
            string $returnUrl,
            string $cancelUrl,
            Payment $payment
        ): Payment {
            foreach ($cartItems as $item) {
                $payment->addCartItem($item);
            }

            $payment->setUser($user);
            $payment->setAmount($amount);
            $payment->setMethod($paymentMethod);
            $payment->setStatus(Payment::STATUS_PROCESSING);
            $payment->setCreatedAt(new \DateTimeImmutable());

            $this->em->persist($payment);
            $this->em->flush(); // Pour générer un ID de paiement

            $tickets = [];
            foreach ($cartItems as $item) {
                $ticket = new Ticket();
                $ticket->setUser($user);
                $ticket->setEvent($item->getEvent());
                $ticket->setTicketType($item->getTicketType());
                $ticket->setPayment($payment);
                $ticket->setStatus('valid');
                $ticket->setIsUsed(false);
                $ticket->setCustomerName($user->getName());
                $ticket->setCustomerEmail($user->getEmail());

                $this->em->persist($ticket);
                $this->em->flush(); // Pour générer un ID de ticket

                // ⚠️ Générer un identifiant unique simple (sans URL) pour le QR code
                $qrCodeValue = 'TICKET-' . $ticket->getId();
                $ticket->setQrCode($qrCodeValue);

                // 🔗 Génère le QR code basé sur cet identifiant
                $qrPath = $this->generateQrCode($qrCodeValue);
                $ticket->setQrCodeImagePath($qrPath);

                $this->em->persist($ticket);
                $tickets[] = $ticket;
            }

            $this->em->flush();

            // Préparation des dossiers PDF
            $invoiceDir = $this->projectDir . '/public/uploads/invoices';
            $ticketDir = $this->projectDir . '/public/uploads/tickets';
            
            foreach ([$invoiceDir, $ticketDir] as $dir) {
                if (!$this->filesystem->exists($dir)) {
                    $this->filesystem->mkdir($dir, 0755);
                }
            }

            $firstTicket = $tickets[0] ?? null;

            $invoicePath = $invoiceDir . '/invoice_' . $payment->getId() . '.pdf';
            $ticketPath = $ticketDir . '/ticket_' . $payment->getId() . '.pdf';

            $this->pdfGenerator->generateAndSave(
                'invoice/pdf.html.twig',
                ['payment' => $payment, 'ticket' => $firstTicket],
                $invoicePath
            );

            $this->pdfGenerator->generateAndSave(
                'invoice/pdf.html.twig',
                [
                    'ticket' => $firstTicket,
                    'project_dir' => $this->projectDir,
                ],
                $ticketPath
            );

            return $payment;
        }

        private function generateQrCode(string $qrCode): string
        {
            // On génère ici l'URL proprement
            $validationUrl = $this->urlGenerator->generate('ticket_verify', [
                'qrCode' => $qrCode,
            ], UrlGeneratorInterface::ABSOLUTE_URL);

            $builder = new Builder(
                writer: new PngWriter(),
                data: $validationUrl,
                encoding: new Encoding('UTF-8'),
                errorCorrectionLevel: ErrorCorrectionLevel::High,
                size: 300,
                margin: 10,
                roundBlockSizeMode: RoundBlockSizeMode::Margin,
            );

            $qrResult = $builder->build();

            $fileName = 'qrcode_' . uniqid() . '.png';
            $publicPath = '/qr-codes/' . $fileName;
            $absolutePath = $this->projectDir . '/public' . $publicPath;
            if (!$this->filesystem->exists(dirname($absolutePath))) {
                $this->filesystem->mkdir(dirname($absolutePath), 0777);
            }

            $qrResult->saveToFile($absolutePath);

            // Optionnel : copie pour DomPDF (si besoin d'un chemin plus simple)
            $pdfPath = $this->projectDir . '/var/pdf_qrcodes/' . $fileName;
            if (!$this->filesystem->exists(dirname($pdfPath))) {
                $this->filesystem->mkdir(dirname($pdfPath), 0775);
            }
            $this->filesystem->copy($absolutePath, $pdfPath, true);

            return $publicPath;
        }
    
}
