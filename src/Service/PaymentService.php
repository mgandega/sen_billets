<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\Ticket;
use App\Entity\Payment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\HttpClient;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\ErrorCorrectionLevel;

class PaymentService
{
    private EntityManagerInterface $em;
    private UrlGeneratorInterface $urlGenerator;
    private PdfGenerator $pdfGenerator;
    private string $projectDir;
    private Filesystem $filesystem;

    private string $paydunyaMasterKey;
    private string $paydunyaPrivateKey;
    private string $paydunyaPublicKey;
    private string $paydunyaToken;
    private string $paydunyaMode;

    public function __construct(
        EntityManagerInterface $em,
        KernelInterface $kernel,
        UrlGeneratorInterface $urlGenerator,
        PdfGenerator $pdfGenerator,
        string $paydunyaMasterKey,
        string $paydunyaPrivateKey,
        string $paydunyaPublicKey,
        string $paydunyaToken,
        string $paydunyaMode
    ) {
        $this->em = $em;
        $this->urlGenerator = $urlGenerator;
        $this->pdfGenerator = $pdfGenerator;
        $this->projectDir = $kernel->getProjectDir();
        $this->filesystem = new Filesystem();

        $this->paydunyaMasterKey = $paydunyaMasterKey;
        $this->paydunyaPrivateKey = $paydunyaPrivateKey;
        $this->paydunyaPublicKey = $paydunyaPublicKey;
        $this->paydunyaToken = $paydunyaToken;
        $this->paydunyaMode = $paydunyaMode;
    }

    /**
     * Crée une facture PayDunya
     */
    public function createPaydunyaInvoice(User $user, array $cartItems, Payment $payment, float $amount, string $returnUrl, string $cancelUrl): string
    {
        $payment->setCreatedAt(new \DateTimeImmutable());
        $payment->setStatus(Payment::STATUS_PROCESSING);
        $this->em->persist($payment);
        $this->em->flush();

        $client = HttpClient::create();
        $url = $this->paydunyaMode === 'sandbox'
            ? 'https://app.paydunya.com/sandbox-api/v1/checkout-invoice/create'
            : 'https://app.paydunya.com/api/v1/checkout-invoice/create';

        $headers = [
            'Content-Type' => 'application/json',
            'PAYDUNYA-MASTER-KEY'  => $this->paydunyaMasterKey,
            'PAYDUNYA-PRIVATE-KEY' => $this->paydunyaPrivateKey,
            'PAYDUNYA-PUBLIC-KEY'  => $this->paydunyaPublicKey,
            'PAYDUNYA-TOKEN'       => $this->paydunyaToken,
        ];

        $data = [
            'invoice' => [
                'items' => array_map(fn($item) => [
                    'name'  => $item->getEvent()->getTitle(),
                    'quantity' => 1,
                    'unit_price' => $item->getTotalPrice(),
                    'total_price' => $item->getTotalPrice(),
                ], $cartItems),
                'total_amount' => $amount,
                'description' => 'Paiement de billets',
            ],
            'store' => [
                'name' => 'Sen Billets',
            ],
            'actions' => [
                'cancel_url' => $cancelUrl,
                'return_url' => $returnUrl,
            ]
        ];

        $response = $client->request('POST', $url, [
            'headers' => $headers,
            'json' => $data,
        ]);

        $responseData = $response->toArray(false);

        if (!isset($responseData['response_code']) || $responseData['response_code'] !== "00") {
            throw new \Exception('Erreur PayDunya : ' . ($responseData['response_text'] ?? 'Erreur inconnue'));
        }

        return $responseData['response_text'] ?? throw new \Exception('URL de paiement introuvable');
    }

    /**
     * Vérifie la facture PayDunya (confirmation après retour)
     */
    public function verifyPaydunyaInvoice(Payment $payment): bool
    {
        $client = HttpClient::create();

        $url = $this->paydunyaMode === 'sandbox'
            ? 'https://app.paydunya.com/sandbox-api/v1/checkout-invoice/confirm/' . $payment->getId()
            : 'https://app.paydunya.com/api/v1/checkout-invoice/confirm/' . $payment->getId();

        $headers = [
            'Content-Type' => 'application/json',
            'PAYDUNYA-MASTER-KEY'  => $this->paydunyaMasterKey,
            'PAYDUNYA-PRIVATE-KEY' => $this->paydunyaPrivateKey,
            'PAYDUNYA-PUBLIC-KEY'  => $this->paydunyaPublicKey,
            'PAYDUNYA-TOKEN'       => $this->paydunyaToken,
        ];

        $response = $client->request('GET', $url, [
            'headers' => $headers,
        ]);

        $responseData = $response->toArray(false);

        return isset($responseData['response_code']) && $responseData['response_code'] === "00";
    }

    /**
     * Finalise le paiement : génère tickets + QR + PDF
     */
    public function finalizePayment(Payment $payment, User $user, array $cartItems): void
    {
        $payment->setStatus(Payment::STATUS_COMPLETED);
        $this->em->persist($payment);
        $this->em->flush();

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
            $this->em->flush();

            $qrCodeValue = 'TICKET-' . $ticket->getId();
            $ticket->setQrCode($qrCodeValue);
            $qrPath = $this->generateQrCode($qrCodeValue);
            $ticket->setQrCodeImagePath($qrPath);

            $this->em->persist($ticket);
            $tickets[] = $ticket;
        }

        $this->em->flush();

        $this->generatePdf($payment, $tickets);
    }

    private function generateQrCode(string $qrCode): string
    {
        $validationUrl = $this->urlGenerator->generate('ticket_verify', ['qrCode' => $qrCode], UrlGeneratorInterface::ABSOLUTE_URL);

        $builder = new Builder(
            writer: new PngWriter(),
            data: $validationUrl,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 300,
            margin: 10,
            roundBlockSizeMode: RoundBlockSizeMode::Margin
        );

        $qrResult = $builder->build();

        $fileName = 'qrcode_' . uniqid() . '.png';
        $publicPath = '/qr-codes/' . $fileName;
        $absolutePath = $this->projectDir . '/public' . $publicPath;

        if (!$this->filesystem->exists(dirname($absolutePath))) {
            $this->filesystem->mkdir(dirname($absolutePath), 0777);
        }

        $qrResult->saveToFile($absolutePath);

        return $publicPath;
    }

    private function generatePdf(Payment $payment, array $tickets): void
    {
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

        $this->pdfGenerator->generateAndSave('invoice/pdf.html.twig', ['payment' => $payment, 'ticket' => $firstTicket], $invoicePath);
        $this->pdfGenerator->generateAndSave('invoice/pdf.html.twig', ['ticket' => $firstTicket, 'project_dir' => $this->projectDir], $ticketPath);
    }
}
