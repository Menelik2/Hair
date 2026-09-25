<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Revenue and operational analytics for the Admin Dashboard.
 */
final class AnalyticsService
{
    public function getTodayKpis(): array
    {
        $revenue = Database::fetch(
            "SELECT COALESCE(SUM(total_price_etb), 0) AS total
             FROM tickets
             WHERE status = 'completed' AND DATE(completed_at) = CURDATE()"
        );

        $completed = Database::fetch(
            "SELECT COUNT(*) AS cnt FROM tickets
             WHERE status = 'completed' AND DATE(completed_at) = CURDATE()"
        );

        $waiting = Database::fetch(
            "SELECT COUNT(*) AS cnt FROM tickets WHERE status IN ('waiting','called')"
        );

        $inChair = Database::fetch(
            "SELECT COUNT(*) AS cnt FROM tickets WHERE status = 'in_chair'"
        );

        $avgWait = Database::fetch(
            "SELECT AVG(estimated_wait_minutes) AS avg_wait
             FROM tickets
             WHERE status IN ('waiting','called') AND estimated_wait_minutes IS NOT NULL"
        );

        $yest = Database::fetch(
            "SELECT COALESCE(SUM(total_price_etb), 0) AS total
             FROM tickets
             WHERE status = 'completed' AND DATE(completed_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)"
        );

        $todayRev = (float)($revenue['total'] ?? 0);
        $yestRev  = (float)($yest['total'] ?? 0);
        $deltaPct = $yestRev > 0 ? round((($todayRev - $yestRev) / $yestRev) * 100, 1) : 0;

        return [
            'revenue_today'     => $todayRev,
            'revenue_delta_pct' => $deltaPct,
            'completed_today'   => (int)($completed['cnt'] ?? 0),
            'waiting'           => (int)($waiting['cnt'] ?? 0),
            'in_chair'          => (int)($inChair['cnt'] ?? 0),
            'avg_wait_minutes'  => (int)round((float)($avgWait['avg_wait'] ?? 0)),
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
             WHERE status = 'completed' AND DATE(completed_at) = CURDATE()
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
