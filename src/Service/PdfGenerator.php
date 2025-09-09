<?php 
namespace App\Service;

use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

class PdfGenerator
{
    private Environment $twig;

    public function __construct(Environment $twig)
    {
        $this->twig = $twig;
    }

    public function generateAndSave(string $template, array $params, string $outputPath): void
    {
        $html = $this->twig->render($template, $params);

        $options = new Options();
        $options->setIsRemoteEnabled(true); // Autoriser images/fonts externes
        $options->setDefaultFont('DejaVu Sans'); // Police unicode safe

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        file_put_contents($outputPath, $dompdf->output());

        // (facultatif) tu peux aussi logger ou renvoyer le chemin si besoin
    }
}
