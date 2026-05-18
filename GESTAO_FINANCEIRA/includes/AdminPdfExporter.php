<?php
/**
 * PDF multi-página (Helvetica + Helvetica-Bold, WinAnsiEncoding).
 * Modo relatório: cabeçalho com faixa de cor, tabela com colunas e linhas alternadas.
 */
declare(strict_types=1);

final class AdminPdfExporter
{
    /** @var list<string> */
    private array $pageBuffers = [];

    private string $buf = '';
    private float $yBaseline;
    private int $fontSize = 10;
    private int $maxPages = 12;

    private const PAGE_H = 842;
    private const PAGE_W = 595;
    private const MARGIN_L = 40;
    private const MARGIN_R = 40;
    private const LINE = 12;

    private const BRAND_R = 0.16;
    private const BRAND_G = 0.38;
    private const BRAND_B = 0.92;

    private const TEXT_R = 0.12;
    private const TEXT_G = 0.14;
    private const TEXT_B = 0.18;

    private bool $truncated = false;

    public function __construct(private string $docTitle)
    {
        $this->startPhysicalPage();
    }

    private function startPhysicalPage(): void
    {
        $this->yBaseline = self::PAGE_H - 50;
        $this->buf = '';
    }

    private static function toWin1252(string $s): string
    {
        if ($s === '') {
            return '';
        }
        $t = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $s);

        return $t !== false ? $t : preg_replace('/[^\x00-\xFF]/u', '?', $s);
    }

    private static function esc(string $s): string
    {
        $s = self::toWin1252($s);
        $s = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
        if (strlen($s) > 240) {
            $s = substr($s, 0, 237) . '...';
        }

        return $s;
    }

    /** @param list<float> $weights */
    private function columnWidths(array $weights): array
    {
        $usable = self::PAGE_W - self::MARGIN_L - self::MARGIN_R;
        $sum = array_sum($weights);
        $w = [];
        foreach ($weights as $wt) {
            $w[] = $usable * ($wt / $sum);
        }

        return $w;
    }

    /** @param list<float> $widths */
    private function columnXs(array $widths): array
    {
        $xs = [];
        $x = self::MARGIN_L;
        foreach ($widths as $cw) {
            $xs[] = $x;
            $x += $cw;
        }

        return $xs;
    }

    /** @param list<float> $widths */
    private function maxCharsForWidths(array $widths, int $fontSize): array
    {
        $out = [];
        foreach ($widths as $cw) {
            $out[] = max(3, (int)floor($cw / ($fontSize * 0.48)));
        }

        return $out;
    }

    private static function truncateCell(string $s, int $maxChars): string
    {
        $t = self::toWin1252($s);
        if (strlen($t) <= $maxChars) {
            return $t;
        }

        return substr($t, 0, max(0, $maxChars - 1)) . '.';
    }

    public function setFontSize(int $pt): void
    {
        $this->fontSize = max(7, min(16, $pt));
    }

    private function flushPage(): void
    {
        if ($this->buf !== '') {
            $this->pageBuffers[] = $this->buf;
        }
        $this->startPhysicalPage();
    }

    private function needNewPage(): bool
    {
        return $this->yBaseline < 56;
    }

    /**
     * Cabeçalho visual (faixa + título + subtítulo + meta), como o relatório HTML da app.
     */
    public function beginStyledReport(string $heroTitle, string $subtitle, string $metaLine): void
    {
        if ($this->truncated) {
            return;
        }
        if ($this->needNewPage() && $this->buf !== '') {
            $this->flushPage();
        }
        if (count($this->pageBuffers) >= $this->maxPages) {
            $this->truncated = true;

            return;
        }

        $bandH = 88.0;
        $yBand = self::PAGE_H - $bandH;
        $this->buf .= sprintf(
            "q %.3F %.3F %.3F rg 0 %.2F %.2F %.2F re f Q\n",
            self::BRAND_R,
            self::BRAND_G,
            self::BRAND_B,
            $yBand,
            self::PAGE_W,
            $bandH
        );

        $titlePlain = self::truncateCell($heroTitle, 48);
        $tw = strlen($titlePlain) * 18 * 0.5;
        $tx = max(self::MARGIN_L, (self::PAGE_W - $tw) / 2);
        $this->buf .= sprintf(
            "BT 1 1 1 rg /F2 18 Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET\n",
            $tx,
            self::PAGE_H - 32,
            self::esc($titlePlain)
        );

        if ($subtitle !== '') {
            $subPlain = self::truncateCell($subtitle, 78);
            $sw = strlen($subPlain) * 9 * 0.5;
            $sx = max(self::MARGIN_L, (self::PAGE_W - $sw) / 2);
            $this->buf .= sprintf(
                "BT 1 1 1 rg /F1 9 Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET\n",
                $sx,
                self::PAGE_H - 50,
                self::esc($subPlain)
            );
        }

        $this->yBaseline = $yBand - 16;
        $this->buf .= sprintf(
            "BT %.3F %.3F %.3F rg /F1 9 Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET\n",
            self::TEXT_R,
            self::TEXT_G,
            self::TEXT_B,
            self::MARGIN_L,
            $this->yBaseline,
            self::esc(self::truncateCell($metaLine, 110))
        );
        $this->yBaseline -= self::LINE + 10;
        $this->divider();
    }

    public function divider(): void
    {
        if ($this->truncated) {
            return;
        }
        if ($this->needNewPage()) {
            if (count($this->pageBuffers) >= $this->maxPages) {
                $this->truncated = true;

                return;
            }
            $this->flushPage();
        }
        $yb = $this->yBaseline;
        $this->buf .= sprintf(
            "q 0.82 0.84 0.88 RG 0.6 w %.2F %.2F m %.2F %.2F l S Q\n",
            self::MARGIN_L,
            $yb,
            self::PAGE_W - self::MARGIN_R,
            $yb
        );
        $this->yBaseline -= 16;
    }

    public function mutedLine(string $text): void
    {
        if ($this->truncated) {
            return;
        }
        if ($this->needNewPage()) {
            if (count($this->pageBuffers) >= $this->maxPages) {
                $this->truncated = true;

                return;
            }
            $this->flushPage();
        }
        $this->buf .= sprintf(
            "BT 0.38 0.40 0.44 rg /F1 9 Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET\n",
            self::MARGIN_L,
            $this->yBaseline,
            self::esc(self::truncateCell($text, 118))
        );
        $this->yBaseline -= self::LINE + 2;
    }

    /**
     * @param list<string> $cells
     * @param list<float>  $weights
     */
    public function tableHeaderRow(array $cells, array $weights): void
    {
        if ($this->truncated || count($cells) !== count($weights)) {
            return;
        }
        if ($this->needNewPage()) {
            if (count($this->pageBuffers) >= $this->maxPages) {
                $this->truncated = true;

                return;
            }
            $this->flushPage();
        }

        $widths = $this->columnWidths($weights);
        $xs = $this->columnXs($widths);
        $fs = 9;
        $h = 16.0;
        $yb = $this->yBaseline;
        $wTotal = self::PAGE_W - self::MARGIN_L - self::MARGIN_R;
        $this->buf .= sprintf(
            "q 0.90 0.93 0.99 rg %.2F %.2F %.2F %.2F re f Q\n",
            self::MARGIN_L,
            $yb - $h + 3,
            $wTotal,
            $h
        );
        $ty = $yb - 5;
        $maxChars = $this->maxCharsForWidths($widths, $fs);
        foreach ($cells as $i => $cell) {
            $t = self::truncateCell((string)$cell, $maxChars[$i]);
            $this->buf .= sprintf(
                "BT %.3F %.3F %.3F rg /F2 %d Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET\n",
                self::TEXT_R,
                self::TEXT_G,
                self::TEXT_B,
                $fs,
                $xs[$i],
                $ty,
                self::esc($t)
            );
        }
        $this->yBaseline = $yb - $h - 8;
    }

    /**
     * @param list<string> $cells
     * @param list<float>  $weights
     */
    public function tableDataRow(array $cells, array $weights, bool $stripe = false): void
    {
        if ($this->truncated || count($cells) !== count($weights)) {
            return;
        }
        if ($this->needNewPage()) {
            if (count($this->pageBuffers) >= $this->maxPages) {
                $this->buf .= sprintf(
                    "BT /F1 8 Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET\n",
                    self::MARGIN_L,
                    max(45.0, $this->yBaseline),
                    self::esc('Fim do limite de paginas no PDF. Usa exportacao CSV para lista completa.')
                );
                $this->truncated = true;
                $this->flushPage();

                return;
            }
            $this->flushPage();
        }

        $widths = $this->columnWidths($weights);
        $xs = $this->columnXs($widths);
        $fs = 9;
        $h = 15.0;
        $yb = $this->yBaseline;
        $wTotal = self::PAGE_W - self::MARGIN_L - self::MARGIN_R;
        if ($stripe) {
            $this->buf .= sprintf(
                "q 0.97 0.98 1 rg %.2F %.2F %.2F %.2F re f Q\n",
                self::MARGIN_L,
                $yb - $h + 3,
                $wTotal,
                $h
            );
        }
        $ty = $yb - 4;
        $maxChars = $this->maxCharsForWidths($widths, $fs);
        foreach ($cells as $i => $cell) {
            $t = self::truncateCell((string)$cell, $maxChars[$i]);
            $this->buf .= sprintf(
                "BT %.3F %.3F %.3F rg /F1 %d Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET\n",
                self::TEXT_R,
                self::TEXT_G,
                self::TEXT_B,
                $fs,
                $xs[$i],
                $ty,
                self::esc($t)
            );
        }
        $this->yBaseline = $yb - $h - 1;
    }

    public function title(string $text): void
    {
        if ($this->truncated) {
            return;
        }
        if ($this->needNewPage() && $this->buf !== '') {
            $this->flushPage();
        }
        if (count($this->pageBuffers) >= $this->maxPages) {
            $this->truncated = true;

            return;
        }
        $this->setFontSize(14);
        $this->buf .= sprintf(
            "BT %.3F %.3F %.3F rg /F1 %d Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET\n",
            self::TEXT_R,
            self::TEXT_G,
            self::TEXT_B,
            $this->fontSize,
            self::MARGIN_L,
            $this->yBaseline,
            self::esc($text)
        );
        $this->yBaseline -= self::LINE + 6;
        $this->setFontSize(10);
    }

    public function line(string $text): void
    {
        if ($this->truncated) {
            return;
        }
        if ($this->needNewPage()) {
            if (count($this->pageBuffers) >= $this->maxPages) {
                $this->buf .= sprintf(
                    "BT /F1 8 Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET\n",
                    self::MARGIN_L,
                    max(45.0, $this->yBaseline),
                    self::esc('Fim do limite de paginas no PDF. Usa exportacao CSV para lista completa.')
                );
                $this->truncated = true;
                $this->flushPage();

                return;
            }
            $this->flushPage();
        }
        $this->buf .= sprintf(
            "BT %.3F %.3F %.3F rg /F1 %d Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET\n",
            self::TEXT_R,
            self::TEXT_G,
            self::TEXT_B,
            $this->fontSize,
            self::MARGIN_L,
            $this->yBaseline,
            self::esc($text)
        );
        $this->yBaseline -= self::LINE;
    }

    public function row(array $cells, int $maxLenEach = 24): void
    {
        if ($this->truncated) {
            return;
        }
        $parts = [];
        foreach ($cells as $c) {
            $s = self::toWin1252((string)$c);
            if (strlen($s) > $maxLenEach) {
                $s = substr($s, 0, $maxLenEach - 1) . '.';
            }
            $parts[] = $s;
        }
        $this->line(implode('  |  ', $parts));
    }

    public function output(string $filename, bool $inline): void
    {
        $this->flushPage();

        $metaBase = $this->docTitle;
        $pageStreams = [];
        foreach ($this->pageBuffers as $idx => $body) {
            $suffix = $idx > 0 ? ' (pag. ' . ($idx + 1) . ')' : '';
            $hdr = sprintf(
                'BT 0.45 0.47 0.50 rg /F1 8 Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET' . "\n",
                self::MARGIN_L,
                self::PAGE_H - 22,
                self::esc($metaBase . $suffix)
            );
            $pageStreams[] = $hdr . $body;
        }

        if ($pageStreams === []) {
            $pageStreams[] = 'BT 0.35 0.37 0.40 rg /F1 10 Tf 1 0 0 1 40 400 Tm (' . self::esc('Sem dados') . ') Tj ET';
        }

        $objs = [];
        $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';

        $fontRegular = 3;
        $fontBold = 4;
        $objs[$fontRegular] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objs[$fontBold] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        $pageIds = [];
        $id = 5;
        foreach ($pageStreams as $ps) {
            $streamBody = rtrim($ps, "\r\n") . "\n";
            $len = strlen($streamBody);
            $objs[$id] = '<< /Length ' . $len . " >>\nstream\n" . $streamBody . 'endstream';
            $id++;
            $pageIds[] = $id;
            $objs[$id] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . self::PAGE_W . ' ' . self::PAGE_H . '] '
                . '/Contents ' . ($id - 1) . ' 0 R /Resources << /Font << /F1 ' . $fontRegular . ' 0 R /F2 ' . $fontBold . ' 0 R >> >> >>';
            $id++;
        }

        $kids = [];
        foreach ($pageIds as $pid) {
            $kids[] = $pid . ' 0 R';
        }
        $objs[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($kids) . ' >>';

        $maxId = $id - 1;
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = array_fill(0, $maxId + 1, 0);
        for ($i = 1; $i <= $maxId; $i++) {
            $offsets[$i] = strlen($pdf);
            $pdf .= $i . " 0 obj\n" . $objs[$i] . "\nendobj\n";
        }
        $xrefPos = strlen($pdf);
        $pdf .= "xref\n0 " . ($maxId + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= $maxId; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size " . ($maxId + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefPos}\n%%EOF";

        $disp = $inline ? 'inline' : 'attachment';
        $safe = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $filename) ?: 'relatorio.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . $disp . '; filename="' . $safe . '"');
        header('Cache-Control: private, max-age=0');
        echo $pdf;
    }
}
