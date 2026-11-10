<?php
defined('INC') || exit; // fichier inclus uniquement
/**
 * Générateur PDF natif (aucune dépendance) : polices standard Helvetica /
 * Helvetica-Bold en WinAnsiEncoding (accents français), texte, lignes,
 * rectangles, couleurs, sauts de page, en-tête / pied de page et
 * compression Flate. Unités : millimètres, origine en haut à gauche.
 */
class PdfSimple
{
    protected const K = 72 / 25.4; // points par mm

    protected array $pages = [];
    protected int $page = -1;
    protected float $w;
    protected float $h;
    protected string $font = 'F1';
    protected float $fontSize = 10;
    protected array $fillColor = [0, 0, 0];
    protected array $textColor = [0, 0, 0];
    public float $marginLeft = 12;
    public float $marginRight = 12;
    public float $marginTop = 12;
    public float $marginBottom = 14;
    public float $y = 12;
    /** @var callable|null */
    public $onHeader = null;
    /** @var callable|null */
    public $onFooter = null;
    protected string $title = '';

    protected static ?array $widths = null;

    public function __construct(string $orientation = 'P', string $title = '')
    {
        [$this->w, $this->h] = strtoupper($orientation) === 'L' ? [297.0, 210.0] : [210.0, 297.0];
        $this->title = $title;
        self::$widths ??= self::fontWidths();
    }

    public function pageWidth(): float
    {
        return $this->w;
    }

    public function contentWidth(): float
    {
        return $this->w - $this->marginLeft - $this->marginRight;
    }

    public function ajouterPage(): void
    {
        if ($this->page >= 0 && $this->onFooter) {
            ($this->onFooter)($this, $this->page + 1);
        }
        $this->pages[] = '';
        $this->page = count($this->pages) - 1;
        $this->y = $this->marginTop;
        [$font, $size, $color] = [$this->font, $this->fontSize, $this->textColor];
        if ($this->onHeader) {
            ($this->onHeader)($this, $this->page + 1);
        }
        $this->setFont($font === 'F2', $size);
        $this->textColor = $color;
    }

    /** Saut de page automatique si $height ne tient pas. */
    public function ensureSpace(float $height): bool
    {
        if ($this->y + $height > $this->h - $this->marginBottom) {
            $this->ajouterPage();
            return true;
        }
        return false;
    }

    public function setFont(bool $bold = false, float $size = 10): void
    {
        $this->font = $bold ? 'F2' : 'F1';
        $this->fontSize = $size;
        $this->out(sprintf('BT /%s %.2F Tf ET', $this->font, $size));
    }

    public function setTextColor(int $r, int $g, int $b): void
    {
        $this->textColor = [$r, $g, $b];
    }

    public function setFillColor(int $r, int $g, int $b): void
    {
        $this->fillColor = [$r, $g, $b];
    }

    public function setDrawColor(int $r, int $g, int $b): void
    {
        $this->out(sprintf('%.3F %.3F %.3F RG', $r / 255, $g / 255, $b / 255));
    }

    public function setLineWidth(float $mm): void
    {
        $this->out(sprintf('%.2F w', $mm * self::K));
    }

    public function line(float $x1, float $y1, float $x2, float $y2): void
    {
        $this->out(sprintf('%.2F %.2F m %.2F %.2F l S', $x1 * self::K, ($this->h - $y1) * self::K, $x2 * self::K, ($this->h - $y2) * self::K));
    }

    /** $style : 'S' contour, 'F' plein, 'FD' les deux */
    public function rect(float $x, float $y, float $w, float $h, string $style = 'S'): void
    {
        $op = match ($style) { 'F' => 'f', 'FD', 'DF' => 'B', default => 'S' };
        if ($op !== 'S') {
            [$r, $g, $b] = $this->fillColor;
            $this->out(sprintf('%.3F %.3F %.3F rg', $r / 255, $g / 255, $b / 255));
        }
        $this->out(sprintf('%.2F %.2F %.2F %.2F re %s', $x * self::K, ($this->h - $y) * self::K, $w * self::K, -$h * self::K, $op));
    }

    /** Texte à la position (x, y) = ligne de base. */
    public function text(float $x, float $y, string $txt): void
    {
        [$r, $g, $b] = $this->textColor;
        $this->out(sprintf(
            'BT %.3F %.3F %.3F rg /%s %.2F Tf %.2F %.2F Td (%s) Tj ET',
            $r / 255, $g / 255, $b / 255, $this->font, $this->fontSize,
            $x * self::K, ($this->h - $y) * self::K, $this->escape(self::toWin($txt))
        ));
    }

    /** Largeur d'une chaîne (mm) dans la police courante. */
    public function stringWidth(string $s): float
    {
        $win = self::toWin($s);
        $cw = self::$widths[$this->font];
        $w = 0;
        $len = strlen($win);
        for ($i = 0; $i < $len; $i++) {
            $w += $cw[ord($win[$i])] ?? 556;
        }
        return $w * $this->fontSize / 1000 / self::K;
    }

    /** Tronque avec « … » pour tenir dans $width mm. */
    public function fit(string $s, float $width): string
    {
        if ($this->stringWidth($s) <= $width) {
            return $s;
        }
        while ($s !== '' && $this->stringWidth($s . '...') > $width) {
            $s = mb_substr($s, 0, -1);
        }
        return $s . '...';
    }

    /** Découpe un texte en lignes de largeur max $width. */
    public function wrap(string $text, float $width): array
    {
        $lines = [];
        foreach (preg_split('/\R/u', $text) as $para) {
            $line = '';
            foreach (preg_split('/\s+/u', trim($para)) as $word) {
                $try = $line === '' ? $word : $line . ' ' . $word;
                if ($this->stringWidth($try) <= $width || $line === '') {
                    $line = $try;
                } else {
                    $lines[] = $line;
                    $line = $word;
                }
            }
            $lines[] = $line;
        }
        return $lines;
    }

    /**
     * Cellule : texte dans un rectangle, alignement L/C/R, fond optionnel.
     */
    public function cell(float $x, float $y, float $w, float $h, string $txt, string $align = 'L', bool $fill = false, bool $border = false): void
    {
        if ($fill) {
            $this->rect($x, $y, $w, $h, $border ? 'FD' : 'F');
        } elseif ($border) {
            $this->rect($x, $y, $w, $h, 'S');
        }
        $pad = 1.2;
        $txt = $this->fit($txt, $w - 2 * $pad);
        $tw = $this->stringWidth($txt);
        $tx = match ($align) {
            'R' => $x + $w - $pad - $tw,
            'C' => $x + ($w - $tw) / 2,
            default => $x + $pad,
        };
        $baseline = $y + $h / 2 + $this->fontSize * 0.35 / self::K;
        $this->text($tx, $baseline, $txt);
    }

    /** Paragraphe multi-lignes à partir de $this->y. */
    public function paragraph(string $text, float $lineHeight = 5, ?float $x = null, ?float $width = null): void
    {
        $x ??= $this->marginLeft;
        $width ??= $this->contentWidth();
        foreach ($this->wrap($text, $width) as $line) {
            $this->ensureSpace($lineHeight);
            $this->text($x, $this->y + $lineHeight * 0.75, $line);
            $this->y += $lineHeight;
        }
    }

    public function output(): string
    {
        if ($this->page < 0) {
            $this->ajouterPage();
        }
        if ($this->onFooter) {
            ($this->onFooter)($this, $this->page + 1);
        }
        $nb = (string) count($this->pages);
        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $kids = [];
        $n = 5;
        $compress = function_exists('gzcompress');
        foreach ($this->pages as $content) {
            $content = str_replace('{nb}', $nb, $content);
            $pageObj = $n++;
            $contentObj = $n++;
            $kids[] = "$pageObj 0 R";
            $objects[$pageObj] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>',
                $this->w * self::K, $this->h * self::K, $contentObj
            );
            $data = $compress ? gzcompress($content) : $content;
            $objects[$contentObj] = '<< ' . ($compress ? '/Filter /FlateDecode ' : '') . '/Length ' . strlen($data) . " >>\nstream\n" . $data . "\nendstream";
        }
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($kids) . ' >>';
        $info = $n;
        $objects[$info] = sprintf(
            '<< /Title (%s) /Producer (Suivi Troupeau - PHP natif) /CreationDate (D:%s) >>',
            $this->escape(self::toWin($this->title)), date('YmdHis')
        );
        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objects as $id => $obj) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "$id 0 obj\n$obj\nendobj\n";
        }
        $xref = strlen($pdf);
        $count = max(array_keys($objects)) + 1;
        $pdf .= "xref\n0 $count\n0000000000 65535 f \n";
        for ($i = 1; $i < $count; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }
        $pdf .= "trailer\n<< /Size $count /Root 1 0 R /Info $info 0 R >>\nstartxref\n$xref\n%%EOF";
        return $pdf;
    }

    /** Envoie le PDF au navigateur. $inline = affichage (impression) ou téléchargement. */
    public function envoyer(string $filename, bool $inline = true): never
    {
        $data = $this->output();
        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $filename) . '"');
        header('Content-Length: ' . strlen($data));
        header('Cache-Control: private, max-age=0, must-revalidate');
        echo $data;
        exit;
    }

    protected function out(string $s): void
    {
        if ($this->page < 0) {
            $this->ajouterPage();
        }
        $this->pages[$this->page] .= $s . "\n";
    }

    protected function escape(string $s): string
    {
        return str_replace(['\\', '(', ')', "\r"], ['\\\\', '\\(', '\\)', ''], $s);
    }

    public static function toWin(string $s): string
    {
        $s = strtr($s, ['≥' => '>=', '≤' => '<=', '→' => '->', '—' => '-', '–' => '-', "\u{00A0}" => ' ', "\u{202F}" => ' ']);
        $r = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $s);
        return $r === false ? mb_convert_encoding($s, 'Windows-1252', 'UTF-8') : $r;
    }

    /** Métriques des polices Helvetica (unités 1/1000). */
    protected static function fontWidths(): array
    {
        $reg = array_fill(0, 256, 556);
        $bold = array_fill(0, 256, 556);
        $r = [32 => 278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278,
            556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556,
            1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778,
            667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556,
            333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556,
            556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584];
        $b = [32 => 278, 333, 474, 556, 556, 889, 722, 238, 333, 333, 389, 584, 278, 333, 278, 278,
            556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 333, 333, 584, 584, 584, 611,
            975, 722, 722, 722, 722, 667, 611, 778, 722, 278, 556, 722, 611, 833, 722, 778,
            667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 333, 278, 333, 584, 556,
            333, 556, 611, 556, 611, 556, 333, 611, 611, 278, 278, 556, 278, 889, 611, 611,
            611, 611, 389, 556, 333, 611, 556, 778, 556, 556, 500, 389, 280, 389, 584];
        foreach ($r as $k => $v) {
            $reg[$k] = $v;
        }
        foreach ($b as $k => $v) {
            $bold[$k] = $v;
        }
        // Lettres accentuées Latin-1 (À..ÿ)
        foreach (range(0xC0, 0xC5) as $c) { $reg[$c] = 667; $bold[$c] = 722; }
        $reg[0xC7] = $bold[0xC7] = 722;
        foreach (range(0xC8, 0xCB) as $c) { $reg[$c] = $bold[$c] = 667; }
        foreach (range(0xCC, 0xCF) as $c) { $reg[$c] = $bold[$c] = 278; }
        foreach (range(0xD2, 0xD6) as $c) { $reg[$c] = $bold[$c] = 778; }
        foreach (range(0xD9, 0xDC) as $c) { $reg[$c] = $bold[$c] = 722; }
        foreach (range(0xE0, 0xE5) as $c) { $reg[$c] = 556; $bold[$c] = 556; }
        $reg[0xE7] = 500; $bold[0xE7] = 556;
        foreach (range(0xE8, 0xEB) as $c) { $reg[$c] = $bold[$c] = 556; }
        foreach (range(0xEC, 0xEF) as $c) { $reg[$c] = $bold[$c] = 278; }
        foreach (range(0xF2, 0xF6) as $c) { $reg[$c] = 556; $bold[$c] = 611; }
        foreach (range(0xF9, 0xFC) as $c) { $reg[$c] = 556; $bold[$c] = 611; }
        $reg[0x80] = $bold[0x80] = 556;  // €
        $reg[0xB0] = $bold[0xB0] = 400;  // °
        return ['F1' => $reg, 'F2' => $bold];
    }
}

final class PdfRapport extends PdfSimple
{
    private string $subtitle;

    public function __construct(string $title, string $subtitle = '', string $orientation = 'P')
    {
        parent::__construct($orientation, $title);
        $this->subtitle = $subtitle;
        $this->onHeader = function (PdfSimple $pdf, int $page): void {
            $farm = (string) parametre('nom_exploitation', 'Exploitation');
            $pdf->setFillColor(25, 92, 62);
            $pdf->rect(0, 0, $pdf->pageWidth(), 4, 'F');
            $pdf->setTextColor(25, 92, 62);
            $pdf->setFont(true, 13);
            $pdf->text($pdf->marginLeft, 13, $this->title);
            $pdf->setFont(false, 8.5);
            $pdf->setTextColor(90, 90, 90);
            $right = $farm;
            $pdf->text($pdf->pageWidth() - $pdf->marginRight - $pdf->stringWidth($right), 13, $right);
            $y = 18;
            if ($this->subtitle !== '') {
                foreach ($pdf->wrap($this->subtitle, $pdf->contentWidth()) as $line) {
                    $pdf->text($pdf->marginLeft, $y, $line);
                    $y += 4;
                }
            }
            $pdf->setDrawColor(200, 200, 200);
            $pdf->setLineWidth(0.2);
            $pdf->line($pdf->marginLeft, $y, $pdf->pageWidth() - $pdf->marginRight, $y);
            $pdf->y = $y + 4;
            $pdf->setTextColor(0, 0, 0);
        };
        $this->onFooter = function (PdfSimple $pdf, int $page): void {
            $pdf->setFont(false, 7.5);
            $pdf->setTextColor(120, 120, 120);
            $u = utilisateur();
            $left = 'Édité le ' . date('d/m/Y à H:i') . ($u ? ' par ' . $u['nom_complet'] : '');
            $y = $this->h - 7;
            $pdf->text($pdf->marginLeft, $y, $left);
            $right = "Page $page / {nb}";
            $pdf->text($pdf->pageWidth() - $pdf->marginRight - $pdf->stringWidth("Page $page / 99"), $y, $right);
            $pdf->setTextColor(0, 0, 0);
        };
    }

    public function titreSection(string $title): void
    {
        $this->ensureSpace(14);
        $this->y += 2;
        $this->setFillColor(232, 242, 236);
        $this->rect($this->marginLeft, $this->y, $this->contentWidth(), 7, 'F');
        $this->setFont(true, 10);
        $this->setTextColor(25, 92, 62);
        $this->text($this->marginLeft + 2, $this->y + 5, $title);
        $this->setTextColor(0, 0, 0);
        $this->y += 9;
    }

    /**
     * Tableau.
     * @param array $columns [['label' => 'Boucle', 'key' => 'tag', 'w' => 1.5, 'align' => 'L'], ...]
     *                       w = largeur relative
     * @param array $rows    lignes (tableaux associatifs) ; valeurs déjà formatées
     */
    public function tableau(array $columns, array $rows, float $fontSize = 8): void
    {
        $total = array_sum(array_map(static fn($c) => $c['w'] ?? 1, $columns));
        $avail = $this->contentWidth();
        foreach ($columns as &$c) {
            $c['mm'] = ($c['w'] ?? 1) / $total * $avail;
        }
        unset($c);
        $rowH = $fontSize * 0.62;

        $header = function () use ($columns, $fontSize, $rowH): void {
            $this->setFont(true, $fontSize);
            $this->setFillColor(25, 92, 62);
            $this->setTextColor(255, 255, 255);
            $x = $this->marginLeft;
            foreach ($columns as $c) {
                $this->cell($x, $this->y, $c['mm'], $rowH + 1, (string) $c['label'], $c['align'] ?? 'L', true);
                $x += $c['mm'];
            }
            $this->y += $rowH + 1;
            $this->setTextColor(0, 0, 0);
            $this->setFont(false, $fontSize);
        };

        $this->ensureSpace($rowH * 3);
        $header();
        if (!$rows) {
            $this->setFont(false, $fontSize);
            $this->cell($this->marginLeft, $this->y, $avail, $rowH, 'Aucune donnée.', 'C');
            $this->y += $rowH;
            return;
        }
        $this->setDrawColor(220, 220, 220);
        $this->setLineWidth(0.1);
        foreach ($rows as $i => $row) {
            if ($this->ensureSpace($rowH)) {
                $header();
            }
            if ($i % 2 === 1) {
                $this->setFillColor(245, 247, 246);
                $this->rect($this->marginLeft, $this->y, $avail, $rowH, 'F');
            }
            $x = $this->marginLeft;
            foreach ($columns as $c) {
                $this->cell($x, $this->y, $c['mm'], $rowH, (string) ($row[$c['key']] ?? ''), $c['align'] ?? 'L');
                $x += $c['mm'];
            }
            $this->y += $rowH;
            $this->line($this->marginLeft, $this->y, $this->marginLeft + $avail, $this->y);
        }
        $this->y += 2;
        $this->setFont(false, 7.5);
        $this->setTextColor(110, 110, 110);
        $this->ensureSpace(5);
        $this->text($this->marginLeft, $this->y + 3, count($rows) . ' ligne(s)');
        $this->setTextColor(0, 0, 0);
        $this->y += 6;
    }

    /** Fiche clé / valeur sur 2 colonnes. */
    public function clesValeurs(array $pairs, int $cols = 2): void
    {
        $colW = $this->contentWidth() / $cols;
        $labelW = $colW * 0.42;
        $rowH = 5.5;
        $i = 0;
        $pairs = array_filter($pairs, static fn($v) => $v !== null);
        foreach ($pairs as $label => $value) {
            $col = $i % $cols;
            if ($col === 0) {
                $this->ensureSpace($rowH);
            }
            $x = $this->marginLeft + $col * $colW;
            $this->setFont(true, 8.5);
            $this->setTextColor(80, 80, 80);
            $this->cell($x, $this->y, $labelW, $rowH, (string) $label);
            $this->setFont(false, 8.5);
            $this->setTextColor(0, 0, 0);
            $this->cell($x + $labelW, $this->y, $colW - $labelW, $rowH, (string) $value);
            if ($col === $cols - 1) {
                $this->y += $rowH;
            }
            $i++;
        }
        if ($i % $cols !== 0) {
            $this->y += $rowH;
        }
        $this->y += 2;
    }
}
