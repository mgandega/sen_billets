<?php

namespace App\Service;

use App\Entity\Payment;
use App\Entity\Ticket;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Symfony\Component\String\Slugger\SluggerInterface;
use DateTimeImmutable;
use Endroid\QrCode\QrCode;

class PaymentService
{
    private string $paydunyaMasterKey;
    private string $paydunyaPrivateKey;
    private string $paydunyaPublicKey;
    private string $paydunyaToken;
    private string $paydunyaMode;

    public function __construct(
        string $paydunyaMasterKey,
        string $paydunyaPrivateKey,
        string $paydunyaPublicKey,
        string $paydunyaToken,
        string $paydunyaMode,
        private PdfGenerator $pdfGenerator,
        private EntityManagerInterface $em,
        private UrlGeneratorInterface $urlGenerator,
        private HttpClientInterface $httpClient,
        private string $projectDir,
        private SluggerInterface $slugger,
        private LoggerInterface $logger
    ) {
        $this->paydunyaMasterKey = $paydunyaMasterKey;
        $this->paydunyaPrivateKey = $paydunyaPrivateKey;
        $this->paydunyaPublicKey = $paydunyaPublicKey;
        $this->paydunyaToken = $paydunyaToken;
        $this->paydunyaMode = $paydunyaMode;
    }

    /**
     * Crée une facture PayDunya et renvoie l'URL de paiement.
     *
     * @param Payment $payment
     * @param array $cartItems  objets contenant getEvent(), getTicketType(), getTotalPrice()
     * @param float $amount
     * @param string $returnUrl
     * @param string $cancelUrl
     * @return string URL de redirection vers le checkout PayDunya
     * @throws \Exception
     */
    public function createPaydunyaInvoice(Payment $payment, array $cartItems, float $amount, string $returnUrl, string $cancelUrl): string
    {
        // marque la tentative de paiement
        $payment->setCreatedAt(new DateTimeImmutable());
        $payment->setStatus(Payment::STATUS_PROCESSING);
        $this->em->persist($payment);
        $this->em->flush();

        $url = $this->paydunyaMode === 'sandbox'
            ? 'https://app.paydunya.com/sandbox-api/v1/checkout-invoice/create'
            : 'https://app.paydunya.com/api/v1/checkout-invoice/create';

        $headers = [
            'Content-Type'          => 'application/json',
            'PAYDUNYA-MASTER-KEY'   => $this->paydunyaMasterKey,
            'PAYDUNYA-PRIVATE-KEY'  => $this->paydunyaPrivateKey,
            'PAYDUNYA-PUBLIC-KEY'   => $this->paydunyaPublicKey,
            'PAYDUNYA-TOKEN'        => $this->paydunyaToken,
        ];

        $items = array_map(function ($item) {
            $name = method_exists($item, 'getEvent') ? $item->getEvent()->getTitle() : 'Billet';
            $price = method_exists($item, 'getTotalPrice') ? $item->getTotalPrice() : 0;
            return [
                'name' => $name,
                'quantity' => 1,
                'unit_price' => $price,
                'total_price' => $price,
            ];
        }, $cartItems);

        $data = [
            'invoice' => [
                'items' => $items,
                'total_amount' => $amount,
                'description' => 'Paiement de billets - ' . ($payment->getId() ?? 'N/A'),
            ],
            'store' => [
                'name' => 'Sen Billets',
            ],
            'actions' => [
                'cancel_url' => $cancelUrl,
                'return_url' => $returnUrl,
            ],
        ];

        // appel HTTP
        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => $headers,
                'json' => $data,
                'timeout' => 15,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('PayDunya request failed', ['exception' => $e]);
            throw new \Exception('Impossible de contacter PayDunya.');
        }

        $statusCode = $response->getStatusCode();
        if ($statusCode < 200 || $statusCode >= 300) {
            $this->logger->error('PayDunya non-2xx', ['status' => $statusCode, 'body' => $response->getContent(false)]);
            throw new \Exception('Erreur PayDunya (status ' . $statusCode . ').');
        }

    $responseData = $response->toArray(false);

    if (!isset($responseData['response_code']) || $responseData['response_code'] !== "00") {
        throw new \Exception('Erreur PayDunya : ' . json_encode($responseData));
    }

    if (!isset($responseData['token'])) {
        throw new \Exception('Token PayDunya manquant : ' . json_encode($responseData));
    }

    $payment->setPaydunyaToken($responseData['token']);
    $this->em->persist($payment);
    $this->em->flush();

    return $responseData['response_text']; // URL de redirection

    }

    /**
     * Vérifie la facture PayDunya (confirm)
     */
    public function verifyPaydunyaInvoice(Payment $payment): bool
    {
        if (!$payment->getPaydunyaToken()) {
            throw new \Exception("Aucun token PayDunya associé à ce paiement.");
        }
        // $url = $this->paydunyaMode === 'sandbox'
        //     ? 'https://app.paydunya.com/sandbox-api/v1/checkout-invoice/confirm/' . $payment->getId()
        //     : 'https://app.paydunya.com/api/v1/checkout-invoice/confirm/' . $payment->getId();

        $url = $this->paydunyaMode === 'sandbox'
        ? 'https://app.paydunya.com/sandbox-api/v1/checkout-invoice/confirm/' . $payment->getPaydunyaToken()
        : 'https://app.paydunya.com/api/v1/checkout-invoice/confirm/' . $payment->getPaydunyaToken();
        
        $headers = [
            'Content-Type'          => 'application/json',
            'PAYDUNYA-MASTER-KEY'   => $this->paydunyaMasterKey,
            'PAYDUNYA-PRIVATE-KEY'  => $this->paydunyaPrivateKey,
            'PAYDUNYA-PUBLIC-KEY'   => $this->paydunyaPublicKey,
            'PAYDUNYA-TOKEN'        => $this->paydunyaToken,
        ];

        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => $headers,
                'timeout' => 10,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('PayDunya verify failed', ['exception' => $e]);
            return false;
        }

        $responseData = $response->toArray(false);
        return isset($responseData['response_code']) && (string)$responseData['response_code'] === "00";
    }

    // /**
    //  * Finalise le paiement, crée les tickets, génère QRs signés et enregistre PDFs.
    //  */
    // public function finalizePayment(Payment $payment, User $user, array $cartItems): void
    // {
    //     $payment->setStatus(Payment::STATUS_COMPLETED);
    //     $this->em->persist($payment);
    //     $this->em->flush();

    //     $tickets = [];
    //     $fs = new Filesystem();

    //     foreach ($cartItems as $item) {
    //         $ticket = new Ticket();
    //         $ticket->setUser($user);
    //         $ticket->setEvent($item->getEvent());
    //         $ticket->setTicketType($item->getTicketType());
    //         $ticket->setPayment($payment);
    //         $ticket->setStatus('valid');
    //         $ticket->setIsUsed(false);
    //         $ticket->setCustomerName($user->getName());
    //         $ticket->setCustomerEmail($user->getEmail());

    //         $this->em->persist($ticket);
    //         $this->em->flush(); // flush pour obtenir l'ID

    //         // valeur signée et horodatée pour sécurité
    //         $qrPayload = $this->buildQrPayload($ticket->getId(), $user->getEmail(), 3600 * 24); // expire 24h par défaut
    //         $ticket->setQrCode($qrPayload);

    //         $qrPath = $this->generateQrCode($qrPayload);
    //         $ticket->setQrCodeImagePath($qrPath);

    //         $this->em->persist($ticket);
    //         $tickets[] = $ticket;
    //     }

    //     $this->em->flush();

    //     $this->generatePdf($payment, $tickets);
    // }

    public function finalizePayment(Payment $payment, User $user, array $cartItems): array
    {
        $tickets = [];

        foreach ($cartItems as $item) {
            for ($i = 0; $i < $item->getQuantity(); $i++) {
                $ticket = new Ticket();
                $ticket->setUser($user);
                $ticket->setEvent($item->getEvent());
                $ticket->setTicketType($item->getTicketType());
                $ticket->setPayment($payment);
                $ticket->setStatus('valid');
                $ticket->setCustomerName($user->FullName());
                $ticket->setCustomerEmail($user->getEmail());

                $this->em->persist($ticket);
                $this->em->flush();

                $qrCodeValue = 'TICKET-' . $ticket->getId() . '-' . md5($user->getEmail());
                $ticket->setQrCode($qrCodeValue);
                $qrPath = $this->generateQrCode($qrCodeValue);
                $ticket->setQrCodeImagePath($qrPath);

                $this->em->persist($ticket);
                $tickets[] = $ticket;
            }
        }

        $this->em->flush();
        $this->generatePdf($payment, $tickets);

        return $tickets; // 🔑 on retourne les tickets créés
    }

    /**
     * Construit un payload signé (base64url) contenant ticketId|email|expires|signature
     */
    private function buildQrPayload(int $ticketId, string $email, int $ttlSeconds = 3600): string
    {
        $expires = (new \DateTimeImmutable())->getTimestamp() + $ttlSeconds;
        $data = sprintf('%d|%s|%d', $ticketId, $email, $expires);
        $signature = hash_hmac('sha256', $data, $this->paydunyaPrivateKey); // utilise la clé privée pour signer
        $payload = $data . '|' . $signature;
        // base64url
        return rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
    }

    // /**
    //  * Génère un QR code PNG et renvoie le chemin public relatif.
    //  */
    // private function generateQrCode(string $qrPayload): string
    // {
    //     // URL de validation (route ticket_verify attendra le token)
    //     $validationUrl = $this->urlGenerator->generate('ticket_verify', ['qrCode' => $qrPayload], UrlGeneratorInterface::ABSOLUTE_URL);

    //     // Endroid Builder usage (compatibilité générique)
    //     // $builder = Builder::create()
    //     //     ->writer(new PngWriter())
    //     //     ->data($validationUrl)
    //     //     ->encoding(new Encoding('UTF-8'))
    //     //     ->errorCorrectionLevel(new ErrorCorrectionLevelHigh())
    //     //     ->size(400)
    //     //     ->margin(10)
    //     //     ->roundBlockSizeMode(new RoundBlockSizeModeMargin());

        
    //     //     $result = $builder->build();

    //     //     $builder = new Builder(
    //     //     writer: new PngWriter(),
    //     //     writerOptions: [],
    //     //     validateResult: false
    //     // );

    //     // $result = $builder
    //     //     ->data($validationUrl)
    //     //     ->encoding(new Encoding('UTF-8'))
    //     //     ->errorCorrectionLevel(new ErrorCorrectionLevelHigh())
    //     //     ->size(400)
    //     //     ->margin(10)
    //     //     ->roundBlockSizeMode(new RoundBlockSizeModeMargin())
    //     //     ->build();

        
    //     //     $fileName = sprintf('qrcode_%s.png', $this->slugger->slug(uniqid((string) time())) );
    //     // $publicPath = '/qr-codes/' . $fileName;
    //     // $absolutePath = rtrim($this->projectDir, '/') . '/public' . $publicPath;

    //     // $filesystem = new Filesystem();
    //     // if (!$filesystem->exists(dirname($absolutePath))) {
    //     //     $filesystem->mkdir(dirname($absolutePath), 0755);
    //     // }

    //     // $result->saveToFile($absolutePath);

    //     // return $publicPath;

    //     $result = (new Builder(
    //         writer: new PngWriter(),
    //         writerOptions: [],
    //         validateResult: false,
    //         data: $validationUrl,
    //         encoding: new Encoding('UTF-8'),
    //         errorCorrectionLevel: new ErrorCorrectionLevelHigh(),
    //         size: 400,
    //         margin: 10,
    //         roundBlockSizeMode: new RoundBlockSizeModeMargin()
    //     ))->build();
    //     $result->saveToFile($absolutePath);
    // }

    private function generateQrCode(string $qrPayload): string
    {
        $validationUrl = $this->urlGenerator->generate(
            'ticket_verify',
            ['qrCode' => $qrPayload],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        // ✅ Crée un objet QrCode avec juste les données
        $qrCode = new QrCode($validationUrl);

        // ✅ Writer pour PNG
        $writer = new PngWriter();

        // ✅ On génère l'image avec options
        $result = $writer->write(
            $qrCode,
            null,
            null,
            [
                'size' => 400,
                'margin' => 10,
            ]
        );

        $fileName = sprintf('qrcode_%s.png', $this->slugger->slug(uniqid((string) time())));
        $publicPath = '/qr-codes/' . $fileName;
        $absolutePath = rtrim($this->projectDir, '/') . '/public' . $publicPath;

        $filesystem = new Filesystem();
        if (!$filesystem->exists(dirname($absolutePath))) {
            $filesystem->mkdir(dirname($absolutePath), 0755);
        }

        $result->saveToFile($absolutePath);

        return $publicPath;
    }

    private function generatePdf(Payment $payment, array $tickets): void
    {
        $invoiceDir = rtrim($this->projectDir, '/') . '/public/uploads/invoices';
        $ticketDir = rtrim($this->projectDir, '/') . '/public/uploads/tickets';

        $filesystem = new Filesystem();
        foreach ([$invoiceDir, $ticketDir] as $dir) {
            if (!$filesystem->exists($dir)) {
                $filesystem->mkdir($dir, 0755);
            }
        }

        // Chemins des fichiers PDF
        $invoiceFile = $invoiceDir . '/invoice_' . $payment->getId() . '.pdf';
        $ticketsFile = $ticketDir . '/tickets_' . $payment->getId() . '.pdf';

        // --- Génération de la facture ---
        // Si ton template facture PDF attend un ticket unique, on prend le premier
        $firstTicket = $tickets[0] ?? null;

        $this->pdfGenerator->generateAndSave('invoice/pdf.html.twig', [
            'payment' => $payment,
            'tickets' => $tickets, // <-- tableau complet
            'project_dir' => $this->projectDir,
        ], $invoiceFile);


        // --- Génération des tickets ---
        // Ici, on passe le tableau complet pour que Twig puisse boucler dessus
        $this->pdfGenerator->generateAndSave('ticket/pdf.html.twig', [
            'tickets' => $tickets,
            'project_dir' => $this->projectDir,
        ], $ticketsFile);

        // Stocke le chemin relatif de la facture dans l'entité
        $payment->setInvoicePath('/uploads/invoices/invoice_' . $payment->getId() . '.pdf');
        $this->em->persist($payment);
        $this->em->flush();
    }

}
