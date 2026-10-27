<?php
/**
 * BA-RADAR-01C — isolated, non-registered prototype.
 * This file does not register WordPress routes or query production data.
 */
declare(strict_types=1);

final class BadAround_Radar_Prototype {
    public const CONTRACT = 'radar-analysis/v1';
    public const MIN_EVENTS = 3;

    /**
     * Only pre-approved, coarse public cells are accepted. Never generate cells
     * from private coordinates or the centroid of an approximate public area.
     * Dataset is synthetic and supplied by the calling test harness.
     */
    public static function analyze(array $events, array $query, DateTimeImmutable $now): array {
        $period = (int) ($query['period_days'] ?? 30);
        $radius = (float) ($query['radius_km'] ?? 1);
        $category = (string) ($query['category'] ?? 'all');
        if (!in_array($period, [7,30,90,365], true) || !in_array($radius, [0.5,1.0,2.0,5.0], true)) {
            throw new InvalidArgumentException('Invalid Radar period or radius');
        }
        if (!preg_match('/^(all|veicoli|case-e-attivita|pericoli|spazi-pubblici|animali|oggetti-e-documenti)$/', $category)) {
            throw new InvalidArgumentException('Invalid Radar category');
        }
        $valid = []; $exclusions = ['test'=>0,'date'=>0,'geo'=>0,'duplicate'=>0,'status'=>0];
        $seen = [];
        $end = $now->setTime(23,59,59);
        $start = $now->setTime(0,0)->modify('-'.($period-1).' days');
        foreach ($events as $event) {
            if (($event['publication_status'] ?? '') !== 'published') { $exclusions['status']++; continue; }
            // Explicit trusted provenance; titles are not a security or QA criterion.
            if (($event['provenance'] ?? '') !== 'real_verified') { $exclusions['test']++; continue; }
            $date = (string) ($event['occurred_date'] ?? '');
            if (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $date)) { $exclusions['date']++; continue; }
            $when = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $now->getTimezone());
            if (!$when || $when->format('Y-m-d') !== $date || $when < $start || $when > $end) { $exclusions['date']++; continue; }
            if ($category !== 'all' && ($event['category'] ?? '') !== $category) continue;
            // Coarse cell assigned by a separately audited publication-layer adapter.
            $cell = $event['approved_public_cell'] ?? null;
            if (!is_string($cell) || !preg_match('/^RAD-[A-Z0-9-]{3,32}$/', $cell)
                || !in_array(($event['public_geo_mode'] ?? ''), ['exact_point','approximate_area'], true)
                || ($event['cell_safe_for_publication'] ?? false) !== true) {
                $exclusions['geo']++; continue;
            }
            $episode = (string) ($event['verified_episode_id'] ?? '');
            // Dedup only when a trusted episode identifier exists.
            if ($episode !== '') {
                if (isset($seen[$episode])) { $exclusions['duplicate']++; continue; }
                $seen[$episode] = true;
            }
            $valid[$cell] = ($valid[$cell] ?? 0) + 1;
        }
        $cells = [];
        foreach ($valid as $cell => $count) {
            if ($count >= self::MIN_EVENTS) $cells[] = ['cell_id'=>$cell,'count'=>$count];
        }
        usort($cells, static fn($a,$b) => strcmp($a['cell_id'],$b['cell_id']));
        return [
            'contract'=>self::CONTRACT,
            'mode'=>'synthetic_fixture_only',
            'window'=>['days'=>$period,'as_of'=>$now->format('Y-m-d')],
            'radius_km'=>$radius,'category'=>$category,
            'coverage'=>count($cells) ? 'sample_available' : 'insufficient_data',
            'heat_cells'=>$cells,
            'alerts'=>[], // real preventive alerts explicitly disabled
            'exclusion_summary'=>$exclusions,
            'disclaimer'=>'Counts of eligible public reports, not an official risk or safety score.',
        ];
    }
}
