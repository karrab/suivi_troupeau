<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;

abstract class Controller
{
    protected function render(string $view, array $data = [], ?string $layout = 'layouts/main'): never
    {
        echo View::render($view, $data, $layout);
        clear_old();
        exit;
    }

    protected function authorize(string $permission): void
    {
        Auth::authorize($permission);
    }

    protected function perPage(): int
    {
        $pp = query_int('per_page', (int) setting('items_par_page', 25));
        return in_array($pp, [10, 25, 50, 100, 200], true) ? $pp : (int) setting('items_par_page', 25);
    }

    /** Résumé textuel des filtres actifs (en-tête des exports PDF). */
    protected function filterSummary(array $labels): string
    {
        $parts = [];
        foreach ($labels as $key => $label) {
            $v = $_GET[$key] ?? '';
            if ($v !== '' && $v !== null) {
                $parts[] = $label . ' : ' . (is_array($v) ? implode(', ', $v) : $v);
            }
        }
        return $parts ? 'Filtres — ' . implode(' | ', $parts) : 'Aucun filtre';
    }
}
