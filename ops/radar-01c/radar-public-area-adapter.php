<?php
/**
 * RADAR-01C2: deterministic public-area registry adapter.
 * Offline synthetic fixtures only; no WordPress hooks, API, database, private geo or deploy.
 */
declare(strict_types=1);

final class BadAround_Radar_Public_Area_Adapter {
    /**
     * Registry entries are supplied by a trusted server-side publisher, never query input.
     * Only public exact points, or *entire* public uncertainty envelopes contained in one
     * region, may be assigned. Boundary/overlap ambiguity always suppresses assignment.
     * Simplified axis-aligned rectangles here are TEST GEOMETRY ONLY.
     */
    public static function assign(array $registry, array $publicProjection): ?string {
        if (!isset($registry['version']) || !is_string($registry['version'])
            || !preg_match('/^radar-areas-v[0-9]+$/', $registry['version'])
            || !isset($registry['areas']) || !is_array($registry['areas'])) return null;
        $areas = [];
        foreach ($registry['areas'] as $key => $bounds) {
            if (!is_string($key) || !preg_match('/^AREA-[A-Z0-9-]{3,24}$/', $key)
                || !self::bounds($bounds)) return null;
            foreach ($areas as $existing) {
                // Shared borders are allowed; interiors may never overlap.
                if (max($bounds['south'],$existing['south']) < min($bounds['north'],$existing['north'])
                    && max($bounds['west'],$existing['west']) < min($bounds['east'],$existing['east'])) return null;
            }
            $areas[$key]=$bounds;
        }
        if (!$areas || ($publicProjection['publication_status'] ?? null) !== 'published'
            || ($publicProjection['projection_authority'] ?? null) !== 'approved_public_projection'
            || ($publicProjection['projection_version'] ?? null) !== $registry['version']) return null;
        $mode=$publicProjection['public_geo_mode'] ?? null;
        if ($mode==='exact_point') {
            $point=$publicProjection['public_point'] ?? null;
            if (!self::point($point))return null;
            $envelope=['south'=>$point['lat'],'north'=>$point['lat'],'west'=>$point['lng'],'east'=>$point['lng']];
        } elseif ($mode==='approximate_area') {
            // REQUIRED trusted public uncertainty bounds. Centroid alone is not sufficient.
            // No private location, inferred circle radius, or public centerpoint is accepted.
            $envelope=$publicProjection['public_uncertainty_bounds'] ?? null;
            if (!self::bounds($envelope))return null;
        } else return null;
        $found=[];
        foreach($areas as $id=>$bounds) {
            // Strict containment: points and uncertainty envelopes touching any area
            // boundary are withheld, preventing disclosure of border precision.
            if($envelope['south']>$bounds['south'] && $envelope['north']<$bounds['north']
                && $envelope['west']>$bounds['west'] && $envelope['east']<$bounds['east'])$found[]=$id;
        }
        return count($found)===1?$found[0]:null;
    }
    private static function point(mixed $p): bool {
        return is_array($p) && count($p)===2 && isset($p['lat'],$p['lng'])
            && self::number($p['lat'],-90,90) && self::number($p['lng'],-180,180);
    }
    private static function number(mixed $v,float $lo,float $hi): bool {
        return (is_int($v)||is_float($v)) && is_finite((float)$v) && $v>=$lo && $v<=$hi;
    }
    private static function bounds(mixed $b): bool {
        return is_array($b) && count($b)===4
            && isset($b['south'],$b['north'],$b['west'],$b['east'])
            && self::number($b['south'],-90,90) && self::number($b['north'],-90,90)
            && self::number($b['west'],-180,180) && self::number($b['east'],-180,180)
            && $b['south']<$b['north'] && $b['west']<$b['east'];
    }
}
