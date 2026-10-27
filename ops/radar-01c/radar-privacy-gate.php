<?php
/**
 * RADAR-DATA-04 privacy facade prototype (synthetic only).
 * This class deliberately refuses arbitrary query windows, category slices,
 * coordinates and radii. It is NOT a public endpoint or production safe release.
 */
declare(strict_types=1);
require_once __DIR__.'/radar-prototype.php';

final class BadAround_Radar_Privacy_Gate {
    public const VERSION = 'radar-private-gate/v0';
    public const MIN_COHORT = 5; // testing parameter, NOT a certified privacy threshold.
    public static function aggregate(array $syntheticEvents, array $request, DateTimeImmutable $asOf): array {
        // A single fixed published snapshot prevents direct time/category subtraction
        // inside this facade. Arbitrary centers and area intersections are forbidden.
        if (array_keys($request) !== ['area_id'] || !is_string($request['area_id'])
            || !preg_match('/^AREA-[A-Z0-9-]{3,24}$/', $request['area_id'])) {
            throw new InvalidArgumentException('Only registered area identifiers accepted');
        }
        $area = $request['area_id'];
        $eligible = [];
        $episodes = [];
        foreach ($syntheticEvents as $event) {
            if (($event['publication_status'] ?? null) !== 'published') continue;
            if (($event['provenance'] ?? null) !== 'real_verified') continue;
            if (($event['approved_public_area'] ?? null) !== $area) continue;
            if (($event['cell_safe_for_publication'] ?? false) !== true) continue;
            if (!in_array(($event['public_geo_mode'] ?? null), ['exact_point','approximate_area'], true)) continue;
            if (($event['area_assignment_audited'] ?? false) !== true) continue;
            if (($event['geometry_precision_verified'] ?? false) !== true) continue;
            $date = $event['occurred_date'] ?? '';
            if (!is_string($date) || !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/',$date))continue;
            $when = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $asOf->getTimezone());
            if (!$when || $when->format('Y-m-d') !== $date)continue;
            $start=$asOf->setTime(0,0)->modify('-29 days');
            if ($when<$start || $when>$asOf->setTime(23,59,59))continue;
            $episode=$event['verified_episode_id'] ?? null;
            // No trustworthy episode ID => exclude rather than double count.
            if (!is_string($episode)||$episode===''||isset($episodes[$episode]))continue;
            $episodes[$episode]=true;
            $eligible[]=$event;
        }
        $n=count($eligible);
        $base=['contract'=>self::VERSION,'area_id'=>$area,'window_days'=>30,
            'as_of'=>$asOf->format('Y-m-d'),'alerts'=>[],
            'heat_cells'=>[],'disclaimer'=>'Community reports are not a risk or safety assessment'];
        if ($n<self::MIN_COHORT) return $base+['coverage'=>'insufficient_data'];
        // Exact counts and records are intentionally not exposed. The renderer may
        // display a coarse presence-only footprint after full privacy certification.
        return $base+['coverage'=>'candidate_cohort_only'];
    }
}
