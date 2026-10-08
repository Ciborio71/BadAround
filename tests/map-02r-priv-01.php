<?php
define('ABSPATH', __DIR__ . '/');

function __($x){return $x;}
function absint($v){return abs((int)$v);}
function sanitize_key($v){return strtolower(preg_replace('/[^a-z0-9_\-]/i','',(string)$v));}
function sanitize_text_field($v){return trim(strip_tags((string)$v));}
function wp_strip_all_tags($v){return strip_tags((string)$v);}
function wp_kses_post($v){return (string)$v;}
function is_wp_error($v){return $v instanceof WP_Error;}

class WP_Error {
    private $code;
    public function __construct($code,$message=''){ $this->code=$code; }
    public function get_error_code(){ return $this->code; }
}
class WP_Term {
    public $term_id,$parent,$slug,$name,$taxonomy;
    public function __construct($id,$parent,$slug,$name,$taxonomy='ba_territorio'){
        $this->term_id=$id;$this->parent=$parent;$this->slug=$slug;$this->name=$name;$this->taxonomy=$taxonomy;
    }
}
class BadAround_Event_Post_Type {
    const POST_TYPE='ba_evento';
    const EVENT_TYPE_TAX='ba_tipo_evento';
    const TERRITORY_TAX='ba_territorio';
}
class BadAround_Audit_Log {
    public static $rows=[];
    public static function record(){ self::$rows[]=func_get_args(); return true; }
}
class BadAround_Moderation_Service { const STATUS_APPROVED='approved'; }

$GLOBALS['m_terms']=[
    2=>new WP_Term(2,0,'lazio','Lazio'),
    3=>new WP_Term(3,2,'roma','Roma'),
    4=>new WP_Term(4,3,'pomezia','Pomezia'),
    5=>new WP_Term(5,4,'torvaianica','Torvaianica'),
    6=>new WP_Term(6,5,'quartiere-mare','Quartiere Mare'),
    7=>new WP_Term(7,2,'chain-incomplete','Catena Incompleta'),
    8=>new WP_Term(8,0,'standalone','Standalone'),
    19=>new WP_Term(19,0,'hazard','Pericoli','ba_tipo_evento'),
    20=>new WP_Term(20,19,'hazard-road-obstruction','Ostacolo stradale','ba_tipo_evento'),
];
$GLOBALS['m_term_meta']=[];
$GLOBALS['m_posts']=[];
$GLOBALS['m_post_meta']=[];
$GLOBALS['m_object_terms']=[];
$GLOBALS['m_wpdb_queries']=0;

class M_WPDB {
    public $prefix='wp_';
    public $postmeta='wp_postmeta';
    public function prepare($sql){ return $sql; }
    public function get_row($sql){ $GLOBALS['m_wpdb_queries']++; return (object)['exact_lat'=>41.777777,'exact_lng'=>12.777777]; }
    public function get_results($sql){ return []; }
    public function get_var($sql){ return 0; }
}
$wpdb=new M_WPDB();

function get_term($id,$taxonomy=''){
    $id=(int)$id;
    if(!isset($GLOBALS['m_terms'][$id])) return null;
    $term=$GLOBALS['m_terms'][$id];
    if($taxonomy && $term->taxonomy!==$taxonomy) return null;
    return $term;
}
function get_ancestors($id,$taxonomy,$type='taxonomy'){
    $out=[];$seen=[];$cur=get_term($id,$taxonomy);
    while($cur && $cur->parent && !isset($seen[$cur->parent])){
        $seen[$cur->parent]=true;
        $out[]=(int)$cur->parent;
        $cur=get_term($cur->parent,$taxonomy);
    }
    return $out;
}
function get_term_meta($id,$key,$single=true){
    return $GLOBALS['m_term_meta'][(int)$id][$key] ?? '';
}
function get_post($id){ return $GLOBALS['m_posts'][(int)$id] ?? null; }
function get_post_meta($id,$key,$single=true){ return $GLOBALS['m_post_meta'][(int)$id][$key] ?? ''; }
function update_post_meta($id,$key,$value){ $GLOBALS['m_post_meta'][(int)$id][$key]=$value; return true; }
function delete_post_meta($id,$key){ unset($GLOBALS['m_post_meta'][(int)$id][$key]); return true; }
function wp_get_post_terms($id,$taxonomy){
    $ids=$GLOBALS['m_object_terms'][(int)$id][$taxonomy] ?? [];
    return array_values(array_filter(array_map(fn($term_id)=>get_term($term_id,$taxonomy),$ids)));
}
function wp_set_object_terms($id,$terms,$taxonomy,$append=false){
    $GLOBALS['m_object_terms'][(int)$id][$taxonomy]=array_values(array_map('intval',$terms));
    return $GLOBALS['m_object_terms'][(int)$id][$taxonomy];
}
function wp_update_post($data,$error=false){
    $id=(int)$data['ID'];
    if(!isset($GLOBALS['m_posts'][$id])) return new WP_Error('missing_post');
    foreach($data as $k=>$v){ if($k!=='ID') $GLOBALS['m_posts'][$id]->$k=$v; }
    return $id;
}
function current_user_can(){return true;}
function get_post_status($id){ $p=get_post($id); return $p?$p->post_status:null; }
function add_filter(){return true;} function remove_filter(){return true;} function do_action(){return true;}
function has_post_thumbnail(){return false;} function set_post_thumbnail(){return true;}
function current_time($t,$gmt=false){return '2026-10-08 12:00:00';}
function wp_salt($scheme='auth'){return 'synthetic-test-salt';}

require_once dirname(__DIR__) . '/wordpress/plugins/badaround-core/includes/class-badaround-publication-service.php';

function m_assert($ok,$message){
    if(!$ok){fwrite(STDERR,"FAIL: $message\n");exit(1);}
    echo "PASS: $message\n";
}
function m_event($id,$territory,$precision,$meta=[]){
    $GLOBALS['m_posts'][$id]=(object)[
        'ID'=>$id,'post_type'=>'ba_evento','post_status'=>'pending',
        'post_title'=>'Titolo pubblico','post_content'=>'Contenuto pubblico'
    ];
    $GLOBALS['m_object_terms'][$id]=[
        'ba_territorio'=>[$territory],
        'ba_tipo_evento'=>[20],
    ];
    $GLOBALS['m_post_meta'][$id]=array_merge([
        '_ba_public_location_precision'=>$precision,
        '_ba_public_place_name'=>get_term($territory,'ba_territorio')->name,
        '_ba_occurred_date'=>'2026-10-08',
    ],$meta);
}
function m_reset_geo_meta(){
    $GLOBALS['m_term_meta'][4]=[];
}

$svc=new BadAround_Publication_Service();

/* A. Torvaianica -> municipality -> Pomezia */
m_reset_geo_meta();
m_event(100,5,'f32-c7',[
    '_ba_public_place_name'=>'Torvaianica',
    '_ba_public_address'=>'Via Privata 9',
    '_ba_public_lat'=>41.650001,
    '_ba_public_lng'=>12.450001,
    '_ba_public_radius_m'=>150,
]);
$GLOBALS['m_wpdb_queries']=0;
$r=$svc->prepare_public_projection(100);
m_assert(true===$r,'A municipality projection succeeds for Torvaianica');
m_assert([4]===$GLOBALS['m_object_terms'][100]['ba_territorio'],'A public territory collapses to municipality Pomezia');
m_assert('Pomezia'===get_post_meta(100,'_ba_public_place_name',true),'A public label is Pomezia');
m_assert(''===get_post_meta(100,'_ba_public_address',true),'A no street/civic remains public');
m_assert(''===get_post_meta(100,'_ba_public_lat',true) && ''===get_post_meta(100,'_ba_public_lng',true),'A stale public coordinates are removed');
m_assert(0===$GLOBALS['m_wpdb_queries'],'A municipality does not query private report coordinates');

/* B. Pomezia -> municipality -> Pomezia */
m_event(101,4,'f32-c7',['_ba_public_place_name'=>'Pomezia']);
$r=$svc->prepare_public_projection(101);
m_assert(true===$r && [4]===$GLOBALS['m_object_terms'][101]['ba_territorio'],'B municipality term remains Pomezia');
m_assert('Pomezia'===get_post_meta(101,'_ba_public_place_name',true),'B municipality label remains canonical');

/* C. deeper locality with municipality ancestor */
m_event(102,6,'f32-c7',['_ba_public_place_name'=>'Quartiere Mare']);
$r=$svc->prepare_public_projection(102);
m_assert(true===$r && [4]===$GLOBALS['m_object_terms'][102]['ba_territorio'],'C deeper locality resolves ancestor municipality');
m_assert('Pomezia'===get_post_meta(102,'_ba_public_place_name',true),'C deeper locality label stops at municipality');

/* D. incomplete territorial chain */
m_event(103,7,'f32-c7');
$r=$svc->prepare_public_projection(103);
m_assert(is_wp_error($r) && 'ba_publication_municipality_missing'===$r->get_error_code(),'D incomplete chain fails closed');

/* E. municipality ancestor absent */
m_event(104,8,'f32-c7');
$r=$svc->prepare_public_projection(104);
m_assert(is_wp_error($r) && 'ba_publication_municipality_missing'===$r->get_error_code(),'E missing municipality ancestor fails closed');

/* F/G. no verified centroid + pre-existing precise public geo */
m_reset_geo_meta();
m_event(105,5,'f32-c7',[
    '_ba_public_lat'=>41.600001,'_ba_public_lng'=>12.400001,'_ba_public_radius_m'=>100,
]);
$GLOBALS['m_wpdb_queries']=0;
$r=$svc->prepare_public_projection(105);
m_assert(true===$r,'F municipality without verified centroid still has label-only projection');
m_assert(''===get_post_meta(105,'_ba_public_lat',true) && ''===get_post_meta(105,'_ba_public_lng',true),'F/G no centroid means no public coordinates');
m_assert(''===get_post_meta(105,'_ba_public_radius_m',true),'G stale radius is removed');
m_assert(0===$GLOBALS['m_wpdb_queries'],'F/G no private-coordinate fallback occurs');

/* verified municipality centre is allowed and is not derived from the private point */
$GLOBALS['m_term_meta'][4]=[
    '_ba_geo_verified'=>'1',
    '_ba_center_lat'=>'41.671234',
    '_ba_center_lng'=>'12.501234',
];
m_event(106,5,'f32-c7',[
    '_ba_public_lat'=>41.612345,'_ba_public_lng'=>12.412345,'_ba_public_radius_m'=>150,
]);
$GLOBALS['m_wpdb_queries']=0;
$r=$svc->prepare_public_projection(106);
m_assert(true===$r,'verified municipality centre projection succeeds');
m_assert(41.671234===(float)get_post_meta(106,'_ba_public_lat',true) && 12.501234===(float)get_post_meta(106,'_ba_public_lng',true),'verified canonical municipality centre is used');
m_assert(3000===(int)get_post_meta(106,'_ba_public_radius_m',true),'verified municipality radius remains municipality-level');
m_assert(0===$GLOBALS['m_wpdb_queries'],'verified centre does not access private report coordinates');

/* H. point -> municipality clears the old point */
m_reset_geo_meta();
m_event(107,5,'f32-c7',[
    '_ba_public_lat'=>41.620001,'_ba_public_lng'=>12.420001,'_ba_public_radius_m'=>150,
]);
$r=$svc->prepare_public_projection(107);
m_assert(true===$r && ''===get_post_meta(107,'_ba_public_lat',true),'H point to municipality clears old point');

/* I. area -> municipality clears the old area point */
m_event(108,5,'f32-c7',[
    '_ba_public_lat'=>41.630001,'_ba_public_lng'=>12.430001,'_ba_public_radius_m'=>1000,
]);
$r=$svc->prepare_public_projection(108);
m_assert(true===$r && ''===get_post_meta(108,'_ba_public_lat',true),'I area to municipality clears old generalized point');

/* J. exact lat/lng/civic never enter municipality projection. */
m_event(109,5,'f32-c7',[
    '_ba_public_address'=>'Via Privata 99',
    '_ba_public_lat'=>41.777777,'_ba_public_lng'=>12.777777,'_ba_public_radius_m'=>100,
]);
$GLOBALS['m_wpdb_queries']=0;
$r=$svc->prepare_public_projection(109);
$projection=json_encode($GLOBALS['m_post_meta'][109]);
m_assert(true===$r,'J privacy projection succeeds without private fallback');
m_assert(false===strpos($projection,'41.777777') && false===strpos($projection,'12.777777') && false===strpos($projection,'Via Privata 99'),'J exact coordinates/address are absent from municipality public meta');
m_assert(0===$GLOBALS['m_wpdb_queries'],'J private report remains unread for municipality projection');

/* point/street/area retain their existing semantics when public geo is already valid. */
foreach([
    110=>['f32-c4',150],
    111=>['f32-c5',300],
    112=>['f32-c6',1000],
] as $id=>$cfg){
    m_event($id,5,$cfg[0],[
        '_ba_public_place_name'=>'Torvaianica',
        '_ba_public_address'=>'Area pubblica',
        '_ba_public_lat'=>41.640001+$id/1000000,
        '_ba_public_lng'=>12.440001+$id/1000000,
        '_ba_public_radius_m'=>$cfg[1],
    ]);
    $before=$GLOBALS['m_post_meta'][$id];
    $termsBefore=$GLOBALS['m_object_terms'][$id]['ba_territorio'];
    $r=$svc->prepare_public_projection($id);
    m_assert(true===$r,"other precision {$cfg[0]} remains valid");
    m_assert($termsBefore===$GLOBALS['m_object_terms'][$id]['ba_territorio'],"other precision {$cfg[0]} keeps deepest territory");
    m_assert($before['_ba_public_lat']==get_post_meta($id,'_ba_public_lat',true) && $before['_ba_public_lng']==get_post_meta($id,'_ba_public_lng',true),"other precision {$cfg[0]} keeps existing public coordinates");
}

/* Source-level privacy guard: municipality branch must return before private report lookup. */
$source=file_get_contents(dirname(__DIR__) . '/wordpress/plugins/badaround-core/includes/class-badaround-publication-service.php');
m_assert(false!==strpos($source,"'f32-c7' === \$precision"),'municipality precision has explicit application branch');
m_assert(false!==strpos($source,"_ba_geo_verified"),'municipality centre requires verification metadata');
m_assert(false!==strpos($source,"delete_post_meta( \$event_id, '_ba_public_lat' )"),'stale latitude is explicitly cleared');
m_assert(false!==strpos($source,"delete_post_meta( \$event_id, '_ba_public_lng' )"),'stale longitude is explicitly cleared');

echo "MAP02R_PRIV01_TORVAIANICA_POMEZIA=PASS\n";
echo "MAP02R_PRIV01_PRIVATE_COORD_DERIVATION=0\n";
echo "MAP02R_PRIV01_STALE_PUBLIC_GEO=REVALIDATED\n";
echo "MAP02R_PRIV01_OTHER_PRECISIONS=PASS\n";
echo "MAP-02R-PRIV-01 tests complete.\n";
