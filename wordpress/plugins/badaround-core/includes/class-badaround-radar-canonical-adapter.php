<?php
/**
 * RADAR-02C — dormant canonical Discovery adapter.
 * No hooks, REST, cron, persistence, public response, or writes.
 * No coordinates, captions, media or private fields leave this function.
 */
if (!defined('ABSPATH')) exit;
final class BadAround_Radar_Canonical_Adapter {
    const MAX_PAGES = 20;
    public static function rows($discovery) {
        if (!($discovery instanceof BadAround_Discovery_Query)) {
            throw new InvalidArgumentException('Canonical Discovery required');
        }
        $pages=array(); $total=null; $count=null;
        $filters=array('per_page'=>100,'period'=>0,'territory'=>'','category'=>'',
            'event_type'=>'','search'=>'');
        for($p=1;$p<=self::MAX_PAGES;$p++) {
            $data=$discovery->discover(array_merge($filters,array('page'=>$p)));
            if(!is_array($data)||!isset($data['items'],$data['total'],$data['pages'],$data['page'])
                ||!is_array($data['items'])||!is_int($data['total'])||$data['total']<0
                ||!is_int($data['pages'])||!is_int($data['page'])
                ||$data['page']!==$p||$data['pages']<0||$data['pages']>self::MAX_PAGES
                ||($data['pages']===0 && ($p!==1||$data['total']!==0||count($data['items'])!==0))
                ||($data['pages']>0 && $p>$data['pages'])) {
                throw new RuntimeException('Invalid canonical Discovery response');
            }
            if($count===null){$count=$data['pages'];$total=$data['total'];}
            if($count!==$data['pages']||$total!==$data['total']) {
                throw new RuntimeException('Discovery changed');
            }
            $pages[]=$data;
            if($p===$count||$count===0)break;
        }
        $rows=array();$ids=array();
        foreach($pages as $data)foreach($data['items'] as $item) {
            if(!is_array($item)||!isset($item['id'])||!is_int($item['id'])
                ||$item['id']<1||isset($ids[$item['id']])) {
                throw new RuntimeException('Duplicate or invalid public report');
            }
            $ids[$item['id']]=true;
            $rows[]=array('id'=>$item['id'],
                'occurred_date'=>$item['occurred_date']??null,
                'territory'=>array('slug'=>$item['territory']['slug']??null),
                'event_type'=>array('slug'=>$item['event_type']['slug']??null));
        }
        if(count($rows)!==$total)throw new RuntimeException('Incomplete public snapshot');
        foreach($pages as $saved) {
            $again=$discovery->discover(array_merge($filters,array('page'=>$saved['page'])));
            if(!is_array($again)||($again['items']??null)!==$saved['items']
                ||($again['total']??null)!==$total||($again['pages']??null)!==$count) {
                throw new RuntimeException('Unstable public snapshot');
            }
        }
        return $rows;
    }
}
