<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;

/**
 * Renders SunnyTrips travel documents (vouchers, itineraries) to A4 PDFs with embedded-font
 * subsetting and a "Page N of M" stamp, which dompdf's CSS counters cannot produce.
 */
class TravelDocumentPdf
{
    /** Footer text size in points (7.5px in the document CSS). */
    private const FOOTER_SIZE = 5.6;

    /** Right page margin in points (15mm in the document CSS). */
    private const RIGHT_MARGIN = 42.5;

    /** Distance from the bottom edge to the top of the footer text, in points. */
    private const FOOTER_BASELINE = 38.5;

    /**
     * @param  array<string, mixed>  $data
     */
    public static function render(string $view, array $data): DomPdf
    {
        $pdf = Pdf::loadView($view, $data)->setPaper('a4')->setOption('isFontSubsettingEnabled', true);
        $pdf->render();

        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $metrics = $dompdf->getFontMetrics();
        $font = $metrics->getFont('DejaVu Sans');
        $label = 'Page {PAGE_NUM} of {PAGE_COUNT}';
        $width = $metrics->getTextWidth('Page 00 of 00', $font, self::FOOTER_SIZE);
        $canvas->page_text($canvas->get_width() - self::RIGHT_MARGIN - $width, $canvas->get_height() - self::FOOTER_BASELINE, $label, $font, self::FOOTER_SIZE, [0.36, 0.44, 0.48]);

        return $pdf;
    }
}
