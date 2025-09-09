<?php
// src/Service/InvoiceGenerator.php
namespace App\Service;

use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;
use App\Entity\Ticket;
use App\Entity\Payment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Templating\EngineInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class InvoiceGenerator
{
    private Environment $twig;
    private string $invoiceDirectory;
    private EntityManagerInterface $em;

    public function __construct(Environment $twig, ParameterBagInterface $params, EntityManagerInterface $em)
    {
        $this->twig = $twig;
        $this->em = $em; 
        $this->invoiceDirectory = $params->get('kernel.project_dir') . '/public/invoices';

        if (!is_dir($this->invoiceDirectory)) {
            mkdir($this->invoiceDirectory, 0777, true);
        }
    }

    public function generate(Payment $payment): string
    {
        $ticket = $this->em->getRepository(Ticket::class)->findOneBy(['payment' => $payment]);

        $html = $this->twig->render('invoice/pdf.html.twig', [
            'payment' => $payment,
            'ticket' => $ticket
        ]);

        $options = new Options();
        $options->set('defaultFont', 'Arial');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'invoice_' . $payment->getId() . '.pdf';
        $path = $this->invoiceDirectory . '/' . $filename;
        file_put_contents($path, $dompdf->output());

        return '/invoices/' . $filename;
    }
}
