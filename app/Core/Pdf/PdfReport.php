<?php
declare(strict_types=1);

namespace App\Core\Pdf;

use App\Core\Auth;
use App\Core\Settings;

/**
 * Mise en page des rapports : en-tête (exploitation, titre), pied de page
 * (date, utilisateur, pagination), tableaux avec en-tête répété, fiches
 * clé / valeur et titres de section.
 */
final class PdfReport extends SimplePdf
{
    private string $subtitle;

    public function __construct(string $title, string $subtitle = '', string $orientation = 'P')
    {
        parent::__construct($orientation, $title);
        $this->subtitle = $subtitle;
        $this->onHeader = function (SimplePdf $pdf, int $page): void {
            $farm = (string) Settings::get('nom_exploitation', 'Exploitation');
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
        $this->onFooter = function (SimplePdf $pdf, int $page): void {
            $pdf->setFont(false, 7.5);
            $pdf->setTextColor(120, 120, 120);
            $u = Auth::user();
            $left = 'Édité le ' . date('d/m/Y à H:i') . ($u ? ' par ' . $u['full_name'] : '');
            $y = $this->h - 7;
            $pdf->text($pdf->marginLeft, $y, $left);
            $right = "Page $page / {nb}";
            $pdf->text($pdf->pageWidth() - $pdf->marginRight - $pdf->stringWidth("Page $page / 99"), $y, $right);
            $pdf->setTextColor(0, 0, 0);
        };
    }

    public function sectionTitle(string $title): void
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
    public function table(array $columns, array $rows, float $fontSize = 8): void
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
    public function keyValues(array $pairs, int $cols = 2): void
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
