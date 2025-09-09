<?php 
// src/Factory/QrCodeBuilderFactory.php
namespace App\Factory;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

class QrCodeBuilderFactory
{
    public function create(): Builder
    {
        return Builder::create()
            ->writer(new PngWriter());
    }
}
