<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Cache;
use App\Core\Database;

final class DashboardController extends Controller
{
    public function index(): never
    {
        $year = (int) date('Y');
        // Statistiques mises en cache APCu 60 s (invalidées à chaque modification d'animal/suivi)
        $stats = Cache::remember(Cache::tagged(['animals', 'tracking'], 'dashboard:' . $year), 60, static function () use ($year) {
            $s = "$year-01-01";
            return [
                'by_category' => Database::all(
                    "SELECT c.label, c.species, COUNT(a.id) AS n FROM categories c
                     LEFT JOIN animals a ON a.category_id = c.id AND a.status = 'actif'
                     GROUP BY c.id, c.label, c.species, c.sort_order ORDER BY c.sort_order"
                ),
                'totals' => Database::one(
                    "SELECT SUM(status = 'actif') AS actifs,
                            SUM(status = 'actif' AND species = 'caprin') AS caprins,
                            SUM(status = 'actif' AND species = 'ovin') AS ovins,
                            SUM(birth_date >= :s1 AND origin = 'naissance') AS naissances,
                            SUM(status = 'mort' AND exit_date >= :s2) AS morts,
                            SUM(status = 'vendu' AND exit_date >= :s3) AS ventes
                     FROM animals",
                    ['s1' => $s, 's2' => $s, 's3' => $s]
                ),
                'births_by_month' => Database::pairs(
                    'SELECT MONTH(event_date) m, SUM(born_alive) FROM births WHERE event_date >= :s GROUP BY m ORDER BY m',
                    ['s' => $s]
                ),
                'milk_by_month' => Database::pairs(
                    'SELECT MONTH(event_date) m, ROUND(AVG(quantity), 2) FROM milk_records WHERE event_date >= :s GROUP BY m ORDER BY m',
                    ['s' => $s]
                ),
            ];
        });

        $alertBirth = (int) setting('alerte_mise_bas_jours', 15);
        $alertHealth = (int) setting('alerte_rappel_jours', 15);
        $upcomingBirths = Database::all(
            "SELECT m.id, m.expected_birth, a.id AS animal_id, a.tag, a.name FROM matings m
             JOIN animals a ON a.id = m.animal_id
             WHERE m.expected_birth BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL $alertBirth DAY)
               AND m.diagnosis <> 'vide' AND a.status = 'actif'
               AND NOT EXISTS (SELECT 1 FROM births b WHERE b.animal_id = m.animal_id AND b.event_date >= m.event_date)
             ORDER BY m.expected_birth LIMIT 15"
        );
        $reminders = Database::all(
            "SELECT h.id, h.next_date, h.event_type, h.product, a.id AS animal_id, a.tag FROM health_events h
             JOIN animals a ON a.id = h.animal_id
             WHERE h.next_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL $alertHealth DAY) AND a.status = 'actif'
             ORDER BY h.next_date LIMIT 15"
        );
        $withdrawal = Database::all(
            "SELECT h.id, h.product, a.id AS animal_id, a.tag, DATE_ADD(h.event_date, INTERVAL h.withdrawal_days DAY) AS until_date
             FROM health_events h JOIN animals a ON a.id = h.animal_id
             WHERE h.withdrawal_days > 0 AND h.event_date >= DATE_SUB(CURDATE(), INTERVAL 365 DAY)
               AND DATE_ADD(h.event_date, INTERVAL h.withdrawal_days DAY) >= CURDATE() AND a.status = 'actif'
             ORDER BY until_date LIMIT 15"
        );
        $recent = Database::all(
            'SELECT h.*, a.tag FROM animal_history h JOIN animals a ON a.id = h.animal_id ORDER BY h.id DESC LIMIT 10'
        );

        $this->render('dashboard/index', compact('stats', 'upcomingBirths', 'reminders', 'withdrawal', 'recent', 'year'));
    }
}
