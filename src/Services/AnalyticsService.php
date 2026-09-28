<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Revenue and operational analytics for the Admin Dashboard.
 * Optimized: fewer round-trips, sargable date predicates (index-friendly).
 */
final class AnalyticsService
{
    public function getTodayKpis(): array
    {
        $row = Database::fetch(
            "SELECT
                COALESCE(SUM(CASE
                    WHEN status = 'completed'
                     AND completed_at >= CURDATE()
                     AND completed_at <  DATE_ADD(CURDATE(), INTERVAL 1 DAY)
                    THEN total_price_etb END), 0) AS revenue_today,
                COALESCE(SUM(CASE
                    WHEN status = 'completed'
                     AND completed_at >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)
                     AND completed_at <  CURDATE()
                    THEN total_price_etb END), 0) AS revenue_yesterday,
                COALESCE(SUM(CASE
                    WHEN status = 'completed'
                     AND completed_at >= CURDATE()
                     AND completed_at <  DATE_ADD(CURDATE(), INTERVAL 1 DAY)
                    THEN 1 END), 0) AS completed_today,
                COALESCE(SUM(CASE WHEN status IN ('waiting','called') THEN 1 END), 0) AS waiting,
                COALESCE(SUM(CASE WHEN status = 'in_chair' THEN 1 END), 0) AS in_chair,
                AVG(CASE
                    WHEN status IN ('waiting','called') AND estimated_wait_minutes IS NOT NULL
                    THEN estimated_wait_minutes END) AS avg_wait
             FROM tickets
             WHERE status IN ('waiting','called','in_chair','completed')
               AND (
                    status IN ('waiting','called','in_chair')
                    OR completed_at >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)
               )"
        ) ?? [];

        $todayRev = (float)($row['revenue_today'] ?? 0);
        $yestRev  = (float)($row['revenue_yesterday'] ?? 0);
        $deltaPct = $yestRev > 0 ? round((($todayRev - $yestRev) / $yestRev) * 100, 1) : 0.0;

        return [
            'revenue_today'     => $todayRev,
            'revenue_delta_pct' => $deltaPct,
            'completed_today'   => (int)($row['completed_today'] ?? 0),
            'waiting'           => (int)($row['waiting'] ?? 0),
            'in_chair'          => (int)($row['in_chair'] ?? 0),
            'avg_wait_minutes'  => (int)round((float)($row['avg_wait'] ?? 0)),
        ];
    }

    /** @return list<array{date:string, label:string, revenue:float, cuts:int}> */
    public function getSevenDayRevenue(): array
    {
        $rows = Database::fetchAll(
            "SELECT DATE(completed_at) AS d,
                    COALESCE(SUM(total_price_etb), 0) AS revenue,
                    COUNT(*) AS cuts
             FROM tickets
             WHERE status = 'completed'
               AND completed_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
             GROUP BY DATE(completed_at)
             ORDER BY d ASC"
        );

        $map = [];
        foreach ($rows as $r) {
            $map[$r['d']] = [
                'revenue' => (float)$r['revenue'],
                'cuts'    => (int)$r['cuts'],
            ];
        }

        $result = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $label = date('D', strtotime($date));
            $result[] = [
                'date'    => $date,
                'label'   => $label,
                'revenue' => $map[$date]['revenue'] ?? 0.0,
                'cuts'    => $map[$date]['cuts'] ?? 0,
            ];
        }
        return $result;
    }

    /** @return list<array{hour:int, label:string, cuts:int, revenue:float}> */
    public function getHourlyVolume(): array
    {
        $rows = Database::fetchAll(
            "SELECT HOUR(completed_at) AS h,
                    COUNT(*) AS cuts,
                    COALESCE(SUM(total_price_etb), 0) AS revenue
             FROM tickets
             WHERE status = 'completed'
               AND completed_at >= CURDATE()
               AND completed_at <  DATE_ADD(CURDATE(), INTERVAL 1 DAY)
             GROUP BY HOUR(completed_at)
             ORDER BY h ASC"
        );

        $map = [];
        foreach ($rows as $r) {
            $map[(int)$r['h']] = [
                'cuts'    => (int)$r['cuts'],
                'revenue' => (float)$r['revenue'],
            ];
        }

        $result = [];
        for ($h = 8; $h <= 20; $h++) {
            $result[] = [
                'hour'    => $h,
                'label'   => sprintf('%02d:00', $h),
                'cuts'    => $map[$h]['cuts'] ?? 0,
                'revenue' => $map[$h]['revenue'] ?? 0.0,
            ];
        }
        return $result;
    }
}
