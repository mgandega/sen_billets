<?php 
// src/Controller/QRCodeController.php
namespace App\Controller\Api;

use App\Service\QRCodeService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class QRCodeController
{
    private QRCodeService $qrCodeService;

    public function __construct(QRCodeService $qrCodeService)
    {
        $this->qrCodeService = $qrCodeService;
    }

    #[Route('/qr-code/{code}', name: 'app_qr_image')]
    public function qrCodeImage(string $code): Response
    {
        $pngData = $this->qrCodeService->getQrCodePngData($code);

        return new Response($pngData, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'inline; filename="qrcode.png"',
        ]);
    }
}
