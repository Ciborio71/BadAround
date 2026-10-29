<?php
/**
 * INTELLIGENCE-01J — passive WordPress Core read model.
 * No hooks, endpoints, cron, writes, or public response exposure.
 * Reports are NOT verified distinct episodes or risk observations.
 */
if (!defined('ABSPATH')) exit;
final class BadAround_Intelligence_Read_Model {
    const MAX_PAGES = 20;
    public static function snapshot($discovery) {
        if (!($discovery instanceof BadAround_Discovery_Query)) {
            throw new InvalidArgumentException('Canonical Discovery required');
        }
        $pages = array(); $total = null; $count = null;
        for ($page=1; $page<=self::MAX_PAGES; $page++) {
            $chunk = $discovery->discover(array('page'=>$page,'per_page'=>100,
                'period'=>0,'territory'=>'','category'=>'','event_type'=>'','search'=>''));
            if (!is_array($chunk) || !isset($chunk['items'],$chunk['total'],$chunk['pages'],$chunk['page'])
                || !is_array($chunk['items']) || !is_int($chunk['total'])
                || !is_int($chunk['pages']) || !is_int($chunk['page'])
                || $chunk['pages']<1 || $chunk['pages']>self::MAX_PAGES
                || $chunk['page']!==$page) {
                throw new RuntimeException('Discovery snapshot unavailable');
            }
            if ($count===null) {$count=$chunk['pages'];$total=$chunk['total'];}
            if ($chunk['pages']!==$count || $chunk['total']!==$total) {
                throw new RuntimeException('Discovery pagination changed');
            }
            $pages[]=$chunk;
            if ($page===$count)break;
        }
        $rows=array();$ids=array();
        foreach ($pages as $chunk) {
            foreach ($chunk['items'] as $item) {
                if (!is_array($item) || !isset($item['id']) || !is_int($item['id'])
                    || $item['id']<1 || isset($ids[$item['id']])) {
                    throw new RuntimeException('Invalid or repeated Discovery record');
                }
                $ids[$item['id']]=true;
                $rows[]=$item;
            }
        }
        if (count($rows)!==$total)throw new RuntimeException('Incomplete Discovery snapshot');
        // Second pass rejects publication, withdrawal or edit drift during pagination.
        foreach ($pages as $first) {
            $again=$discovery->discover(array('page'=>$first['page'],'per_page'=>100,
                'period'=>0,'territory'=>'','category'=>'','event_type'=>'','search'=>''));
            if (!is_array($again) || ($again['items']??null)!==$first['items']
                || ($again['total']??null)!==$total || ($again['pages']??null)!==$count) {
                throw new RuntimeException('Discovery changed while reading');
            }
        }
        $types=array();$territories=array();$dated=0;
        foreach ($rows as $item) {
            $type=$item['event_type']['name']??null;
            if (is_string($type) && $type!=='')$types[$type]=($types[$type]??0)+1;
            $term=$item['territory']['name']??null;
            if (is_string($term) && $term!=='')$territories[$term]=($territories[$term]??0)+1;
            $date=$item['occurred_date']??null;
            if (is_string($date)) {
                $format = preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $date) ? 'Y-m-d'
                    : (preg_match('/^\\d{2}\\/\\d{2}\\/\\d{4}$/', $date) ? 'd/m/Y' : null);
                if ($format !== null) {
                    $parsed=DateTimeImmutable::createFromFormat('!'.$format,$date,wp_timezone());
                    if ($parsed && $parsed->format($format)===$date
                        && $parsed<=new DateTimeImmutable('today',wp_timezone()))$dated++;
                }
            }
        }
        ksort($types);ksort($territories);
        return array('contract'=>'badaround-intelligence-read-model/v0.1',
            'unit'=>'published_reports_not_unique_incidents','report_count'=>count($rows),
            'occurred_date_available'=>$dated,'by_event_type'=>$types,
            'by_territory_label'=>$territories,'public_alerts'=>array(),
            'certified_episode_count'=>false,'transactional_snapshot'=>false);
    }
}
