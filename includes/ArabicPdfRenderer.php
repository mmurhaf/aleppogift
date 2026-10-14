<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

class ArabicPdfRenderer
{
    public static function render(string $html, string $paper = 'A4', string $orientation = 'portrait'): Dompdf
    {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper($paper, $orientation);
        $dompdf->render();

        return $dompdf;
    }

    public static function saveToFile(string $html, string $filePath, string $paper = 'A4', string $orientation = 'portrait'): void
    {
        $dompdf = self::render($html, $paper, $orientation);
        file_put_contents($filePath, $dompdf->output());
    }

    public static function streamDownload(string $html, string $filename, string $paper = 'A4', string $orientation = 'portrait'): void
    {
        $dompdf = self::render($html, $paper, $orientation);
        $dompdf->stream($filename, ['Attachment' => true]);
    }
}
