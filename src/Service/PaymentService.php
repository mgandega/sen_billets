<?php
namespace App\Service;

use App\Entity\Payment;
use App\Entity\Ticket;
use App\Entity\User;
use App\Entity\CartItem;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;
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
     * Crée une facture PayDunya et retourne l'URL de redirection
     */
    // public function createPaydunyaInvoice(Payment $payment, array $cartItems, float $amount, string $returnUrl, string $cancelUrl): string
    // {
    //     $payment->setAmount($amount);
    //     $payment->setStatus(Payment::STATUS_PROCESSING);
    //     $payment->setMethod('paydunya'); // ⚠️ obligatoire pour la DB
    //     $this->em->persist($payment);
    //     $this->em->flush();

    //     // Ici, tu peux appeler l'API PayDunya et récupérer l'URL de redirection
    //     // Pour l'instant, on simule
    //     return $returnUrl . '?payment_id=' . $payment->getId();
    // }

    public function createPaydunyaInvoice(
    Payment $payment,
    array $cartItems,
    float $amount,
    string $returnUrl,
    string $cancelUrl
): string {
        $items = [];
        foreach ($cartItems as $item) {
            $items[] = [
                'name'        => $item->getTicketType()->getName(),
                'quantity'    => $item->getQuantity(),
                'unit_price'  => $item->getTicketType()->getPrice(),
                'total_price' => $item->getTotalPrice(),
            ];
        }

        $payload = [
            'invoice' => [
                'items'          => $items,
                'total_amount'   => $amount,
                'description'    => 'Paiement de billets',
                'callback_url'   => $returnUrl,
                'cancel_url'     => $cancelUrl,
                'return_url'     => $returnUrl,
            ],
        ];

        $headers = [
            'Content-Type'        => 'application/json',
            'PAYDUNYA-MASTER-KEY' => $this->paydunyaMasterKey,
            'PAYDUNYA-PRIVATE-KEY'=> $this->paydunyaPrivateKey,
            'PAYDUNYA-TOKEN'      => $this->paydunyaToken,
            'PAYDUNYA-PUBLIC-KEY' => $this->paydunyaPublicKey,
        ];

        $response = $this->httpClient->request('POST', 'https://app.paydunya.com/api/v1/checkout-invoice/create', [
            'headers' => $headers,
            'json'    => $payload,
        ]);

        $data = $response->toArray(false);

        if (!isset($data['response_code']) || $data['response_code'] !== '00') {
            throw new \RuntimeException('Erreur PayDunya : ' . ($data['response_text'] ?? 'Réponse invalide'));
        }

        // stocker l’ID de la facture PayDunya
        $payment->setReference($data['invoice']['token']);
        $this->em->persist($payment);
        $this->em->flush();

        return $data['response_text']; // URL de redirection PayDunya
    }


    /**
     * Vérifie le paiement PayDunya
     */
    public function verifyPaydunyaInvoice(Payment $payment): bool
    {
        // Appel API PayDunya ici
        $payment->setStatus(Payment::STATUS_COMPLETED);
        $this->em->flush();
        return true;
    }

    /**
     * Finalise le paiement : génère les tickets individuels et PDF
     */
    // public function finalizePayment(Payment $payment, User $user, array $cartItems): array
    // {
    //     $tickets = [];
    //     $fs = new Filesystem();

    //     foreach ($cartItems as $item) {
    //         for ($i = 0; $i < $item->getQuantity(); $i++) {
    //             $ticket = new Ticket();
    //             $ticket->setUser($user)
    //                 ->setEvent($item->getEvent())
    //                 ->setTicketType($item->getTicketType())
    //                 ->setPayment($payment)
    //                 ->setStatus('valid')
    //                 ->setCustomerName($user->FullName())
    //                 ->setCustomerEmail($user->getEmail());

    //             $this->em->persist($ticket);
    //             $this->em->flush();

    //             // QR Code sécurisé
    //             $qrPayload = $this->buildQrPayload($ticket->getId(), $user->getEmail(), 3600*24);
    //             $ticket->setQrCode($qrPayload);

    //             $qrPath = $this->generateQrCode($qrPayload);
    //             $ticket->setQrCodeImagePath($qrPath);

    //             $this->em->persist($ticket);
    //             $tickets[] = $ticket;
    //         }
    //     }

    //     $this->em->flush();

    //     // PDF facture globale
    //     $this->pdfGenerator->generateAndSave(
    //         'invoice/pdf.html.twig',
    //         [
    //             'payment' => $payment,
    //             'tickets' => $tickets,
    //             'project_dir' => $this->projectDir,
    //         ],
    //         $this->projectDir . '/public/uploads/invoices/invoice_' . $payment->getId() . '.pdf'
    //     );

    //     // PDF billets individuels
    //     // foreach ($tickets as $ticket) {
    //     //     $this->pdfGenerator->generateAndSave(
    //     //         'ticket/pdf.html.twig',
    //     //         [
    //     //             'tickets' => [$ticket],
    //     //             'project_dir' => $this->projectDir,
    //     //         ],
    //     //         $this->projectDir . '/public/uploads/tickets/ticket_' . $ticket->getId() . '.pdf'
    //     //     );
    //     // }
    //     $this->pdfGenerator->generateAndSave(
    //         'ticket/pdf.html.twig',
    //         [
    //             'ticket' => $ticket,
    //             'project_dir' => $this->projectDir,
    //         ],
    //         $this->projectDir . '/public/uploads/tickets/ticket_' . $ticket->getId() . '.pdf'
    //     );


    //     return $tickets;
    // }

    private function buildQrPayload(int $ticketId, string $email, int $ttlSeconds = 3600): string
    {
        $expires = (new DateTimeImmutable())->getTimestamp() + $ttlSeconds;
        $data = sprintf('%d|%s|%d', $ticketId, $email, $expires);
        $signature = hash_hmac('sha256', $data, $this->paydunyaPrivateKey);
        return rtrim(strtr(base64_encode($data . '|' . $signature), '+/', '-_'), '=');
    }

    // private function generateQrCode(string $qrPayload): string
    // {
    //     $validationUrl = $this->urlGenerator->generate('ticket_verify', ['qrCode' => $qrPayload], UrlGeneratorInterface::ABSOLUTE_URL);

    //     $result = Builder::create()
    //         ->writer(new PngWriter())
    //         ->data($validationUrl)
    //         ->encoding(new Encoding('UTF-8'))
    //         ->size(400)
    //         ->margin(10)
    //         ->roundBlockSizeMode(new RoundBlockSizeModeMargin())
    //         ->build();

    //     $fileName = 'qrcode_' . $this->slugger->slug(uniqid((string)time())) . '.png';
    //     $publicPath = '/qr-codes/' . $fileName;
    //     $absolutePath = rtrim($this->projectDir, '/') . '/public' . $publicPath;

    //     if (!$fs->exists(dirname($absolutePath))) {
    //         $fs->mkdir(dirname($absolutePath), 0755);
    //     }

    //     $result->saveToFile($absolutePath);

    //     return $publicPath;
    // }
        public function finalizePayment(Payment $payment, User $user, array $cartItems): array
        {
            $tickets = [];

            $filesystem = new Filesystem();
            $ticketDir = rtrim($this->projectDir, '/') . '/public/uploads/tickets';
            $invoiceDir = rtrim($this->projectDir, '/') . '/public/uploads/invoices';
            $qrDir = rtrim($this->projectDir, '/') . '/public/qr-codes';

            // s'assurer que les dossiers existent
            foreach ([$ticketDir, $invoiceDir, $qrDir] as $dir) {
                if (!$filesystem->exists($dir)) {
                    $filesystem->mkdir($dir, 0755);
                }
            }

            // Crée les tickets (un ticket par quantité)
            foreach ($cartItems as $item) {
                $quantity = (int) $item->getQuantity();
                for ($i = 0; $i < $quantity; $i++) {
                    $ticket = new Ticket();
                    $ticket->setUser($user)
                        ->setEvent($item->getEvent())
                        ->setTicketType($item->getTicketType())
                        ->setPayment($payment)
                        ->setStatus('valid')
                        ->setCustomerName($user->getName() ?? $user->getFullName() ?? 'Client')
                        ->setCustomerEmail($user->getEmail());

                    $this->em->persist($ticket);
                    $this->em->flush(); // flush pour obtenir l'ID

                    // Génère un payload signé pour le QR
                    // $qrPayload = $this->buildQrPayload($ticket->getId(), $ticket->getCustomerEmail(), 3600 * 24);
                    // $ticket->setQrCode($qrPayload);

                    // Génère QR (endroid v6) et stocke le path public
                    // $qrPath = $this->generateQrCode($qrPayload);
                    $qrPath = $this->generateQrCode($ticket);
                    $ticket->setQrCodeImagePath($qrPath);

                    // $ticket->setQrCodeImagePath($qrPath);

                    $this->em->persist($ticket);
                    $this->em->flush();

                    $tickets[] = $ticket;

                    // Génère immédiatement le PDF du billet (fichier unique par ticket)
                    $ticketPdfPath = $ticketDir . '/ticket_' . $ticket->getId() . '.pdf';
                    $this->pdfGenerator->generateAndSave(
                        'ticket/pdf.html.twig',
                        [
                            'ticket' => $ticket,
                            'project_dir' => $this->projectDir,
                        ],
                        $ticketPdfPath
                    );
                }
            }

            // Après avoir créé tous les tickets, générer la facture globale
            $invoicePath = $invoiceDir . '/invoice_' . $payment->getId() . '.pdf';
            $this->pdfGenerator->generateAndSave(
                'invoice/pdf.html.twig',
                [
                    'payment' => $payment,
                    'tickets' => $tickets,
                    'project_dir' => $this->projectDir,
                ],
                $invoicePath
            );

            // Sauvegarder le chemin relatif de la facture dans l'entité
            $payment->setInvoicePath('/uploads/invoices/invoice_' . $payment->getId() . '.pdf');
            $this->em->persist($payment);
            $this->em->flush();

            return $tickets;
        }


    // private function generateQrCode(string $qrPayload): string
    // {
    //     $validationUrl = $this->urlGenerator->generate(
    //         'ticket_verify',
    //         ['qrCode' => $qrPayload],
    //         UrlGeneratorInterface::ABSOLUTE_URL
    //     );

    //     // Créer le QR Code
    //     $qrCode = QrCode::create($validationUrl)
    //         ->setEncoding(new Encoding('UTF-8'))
    //         ->setSize(400)
    //         ->setMargin(10)
    //         ->setRoundBlockSizeMode(new RoundBlockSizeModeMargin());

    //     // Écrire en PNG
    //     $writer = new PngWriter();
    //     $result = $writer->write($qrCode);

    //     // Enregistrer le fichier
    //     $fileName = 'qrcode_' . $this->slugger->slug(uniqid((string) time())) . '.png';
    //     $publicPath = '/qr-codes/' . $fileName;
    //     $absolutePath = rtrim($this->projectDir, '/') . '/public' . $publicPath;

    //     $fs = new Filesystem();
    //     if (!$fs->exists(dirname($absolutePath))) {
    //         $fs->mkdir(dirname($absolutePath), 0755);
    //     }

    //     $result->saveToFile($absolutePath);

    //         return $publicPath;
    // }
    // private function generateQrCode(string $qrPayload): string
    // {
    //     $validationUrl = $this->urlGenerator->generate('ticket_verify', ['qrCode' => $qrPayload], UrlGeneratorInterface::ABSOLUTE_URL);

    //     // Création du QrCode (v6 API)
    //     $qrCode = QrCode::create($validationUrl)
    //         ->setEncoding(new Encoding('UTF-8'))
    //         ->setSize(400)
    //         ->setMargin(10)
    //         ->setRoundBlockSizeMode(new RoundBlockSizeModeMargin());

    //     $writer = new PngWriter();
    //     $result = $writer->write($qrCode);

    //     $fileName = 'qrcode_' . $this->slugger->slug(uniqid((string) time())) . '.png';
    //     $publicPath = '/qr-codes/' . $fileName;
    //     $absolutePath = rtrim($this->projectDir, '/') . '/public' . $publicPath;

    //     $filesystem = new Filesystem();
    //     if (!$filesystem->exists(dirname($absolutePath))) {
    //         $filesystem->mkdir(dirname($absolutePath), 0755);
    //     }

    //     $result->saveToFile($absolutePath);

    //     return $publicPath;
    // }

    private function generateQrCode(Ticket $ticket): string
    {
        $hash = md5($ticket->getCustomerEmail());
        $qrPayload = sprintf("TICKET-%d-%s", $ticket->getId(), $hash);

        $qrCode = QrCode::create($qrPayload)
            ->setEncoding(new Encoding('UTF-8'))
            ->setSize(400)
            ->setMargin(10)
            ->setRoundBlockSizeMode(new RoundBlockSizeModeMargin());

        $writer = new PngWriter();
        $result = $writer->write($qrCode);

        $fileName = 'qrcode_' . $this->slugger->slug(uniqid((string) time())) . '.png';
        $publicPath = '/qr-codes/' . $fileName;
        $absolutePath = rtrim($this->projectDir, '/') . '/public' . $publicPath;

        $fs = new Filesystem();
        if (!$fs->exists(dirname($absolutePath))) {
            $fs->mkdir(dirname($absolutePath), 0755);
        }

        $result->saveToFile($absolutePath);

        $ticket->setQrCode($qrPayload); // on stocke aussi le code brut
        return $publicPath;
    }


}
