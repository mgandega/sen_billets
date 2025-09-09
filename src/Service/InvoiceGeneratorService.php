<?php

namespace App\Service;

use App\Entity\Payment;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;

class InvoiceGeneratorService
{
    private Environment $twig;
    private string $projectDir;
    private Filesystem $filesystem;

    public function __construct(Environment $twig, KernelInterface $kernel)
    {
        $this->twig = $twig;
        $this->projectDir = $kernel->getProjectDir();
        $this->filesystem = new Filesystem();
    }

    public function generateInvoice(Payment $payment): string
    {
        // Rendu du HTML avec Twig
        $html = $this->twig->render('invoice/invoice.html.twig', [
            'payment' => $payment,
        ]);

        // Configuration Dompdf
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Définition du nom de fichier
        $filename = 'invoice_' . $payment->getId() . '.pdf';
        $relativePath = '/uploads/invoices/' . $filename;
        $absolutePath = $this->projectDir . '/public' . $relativePath;

        if (!$this->filesystem->exists(dirname($absolutePath))) {
            $this->filesystem->mkdir(dirname($absolutePath), 0755);
        }

        file_put_contents($absolutePath, $dompdf->output());

        return $relativePath;
    }
}
