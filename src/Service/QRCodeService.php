<?php 
namespace App\Service;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Label\Font\OpenSans;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Label\LabelAlignment;
use Symfony\Component\Filesystem\Filesystem;


class QRCodeService
{
    public function generate(string $text, string $filename): string
        {
            $builder = new Builder(
                writer: new PngWriter(),
                writerOptions: [],
                validateResult: false,
                data: 'Custom QR code contents',
                encoding: new Encoding('UTF-8'),
                errorCorrectionLevel: ErrorCorrectionLevel::High,
                size: 300,
                margin: 10,
                roundBlockSizeMode: RoundBlockSizeMode::Margin,
                logoPath: __DIR__.'/assets/logo.png',
                logoResizeToWidth: 50,
                logoPunchoutBackground: true,
                labelText: 'This is the label',
                labelFont: new OpenSans(20),
                labelAlignment: LabelAlignment::Center
            );

        $result = $builder->build();


        $dir = __DIR__ . '/../../public/uploads/qrcodes';
        $filesystem = new Filesystem();

        if (!$filesystem->exists($dir)) {
            $filesystem->mkdir($dir, 0775);
        }

        $fullPath = $dir . '/' . $filename;
        $result->saveToFile($fullPath);

        return '/uploads/qrcodes/' . $filename;
    }
}
