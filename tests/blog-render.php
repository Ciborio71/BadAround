<?php
/** Render real editorial templates against isolated native WordPress fixtures. */
define('ABSPATH',__DIR__);
$GLOBALS['ctx']=$argv[1]??'index';$GLOBALS['empty']=in_array('--empty',$argv,true);$GLOBALS['hooks']=array();$GLOBALS['post_id']=1;
function add_action($n,$cb,$priority=10,$args=1){$GLOBALS['hooks'][$n][]=$cb;}
function add_filter($n,$cb,$priority=10,$args=1){add_action($n,$cb,$priority,$args);}
function home_url($path=''){return 'https://badaround.it'.$path;}
function get_stylesheet_directory_uri(){return home_url('/wp-content/themes/badaround-child');}
function get_stylesheet_directory(){return __DIR__.'/../wordpress/themes/badaround-child';}
function esc_html($s){return htmlspecialchars((string)$s,ENT_QUOTES);}
function esc_attr($s){return esc_html($s);}
function esc_url($s){return esc_html($s);}
function esc_html_e($s,$d=''){echo esc_html($s);}function esc_attr_e($s,$d=''){echo esc_attr($s);}
function is_page($s=''){return $GLOBALS['ctx']==='index'&&($s===''||$s==='blog');}
function is_singular($s=''){return $GLOBALS['ctx']==='single'&&($s===''||$s==='post');}
function is_category($id=null){return $GLOBALS['ctx']==='category'&&($id===null||$id===3);}
function is_front_page(){return $GLOBALS['ctx']==='home';}
function is_wp_error($v){return false;}
function get_queried_object_id(){return $GLOBALS['ctx']==='single'?1:3;}
function get_queried_object(){return (object)array('slug'=>'animali','term_id'=>3,'count'=>$GLOBALS['empty']?0:3);}
function get_the_category($id){$keys=array('il-progetto-badaround','animali','veicoli');return array((object)array('slug'=>$keys[($id-1)%3],'term_id'=>$id,'name'=>$keys[($id-1)%3]));}
function get_term_by($f,$v,$t){return (object)array('slug'=>$v,'term_id'=>array_search($v,array_keys(badaround_blog_sections()))+1);}
function get_category_link($id){return home_url('/category/argomento-'.$id.'/');}
function get_query_var($key){return 0;}
function get_pagenum_link($p){return home_url('/blog/page/'.$p.'/');}
function user_trailingslashit($s){return $s.'/';}
function add_rewrite_rule($r,$q,$o){$GLOBALS['rules'][]=array($r,$q);}
function get_post_field($field,$id){return $field==='post_excerpt'?'Una guida per conoscere BadAround, condividere informazioni utili e partecipare alla community.':'<p>Testo della guida.</p>';}
function strip_shortcodes($s){return $s;}
function wp_strip_all_tags($s){return strip_tags($s);}
function wp_trim_words($s,$n,$suffix='…'){$w=preg_split('/\s+/',$s);return implode(' ',array_slice($w,0,$n)).(count($w)>$n?$suffix:'');}
function get_the_ID(){return $GLOBALS['post_id'];}
function get_the_title($id=null){return 'Cos’è BadAround: informazioni locali e collaborazione tra cittadini';}
function the_title(){echo esc_html(get_the_title());}
function get_permalink($id=null){return home_url($GLOBALS['ctx']==='index'?'/blog/':'/blog/articolo-'.$id.'/');}
function the_permalink(){echo esc_url(get_permalink(get_the_ID()));}
function get_the_date($format='',$id=null){return $format===DATE_W3C?'2026-11-12T12:00:00+01:00':($format==='Y-m-d'?'2026-11-12':'12 novembre 2026');}
function get_the_modified_date($f=''){return get_the_date($f);}
function has_post_thumbnail($id=null){return ($id??get_the_ID())===2;}
function get_the_post_thumbnail_url($id,$size=''){return $id===2?home_url('/uploads/article.jpg'):false;}
function get_the_post_thumbnail($id,$size='',$args=array()){return '<img src="'.esc_url(home_url('/uploads/article.jpg')).'" width="640" height="360" alt="">';}
function the_post_thumbnail($s,$a){echo get_the_post_thumbnail(get_the_ID(),$s,$a);}
function has_excerpt(){return true;}
function get_the_excerpt(){return get_post_field('post_excerpt',1);}
function single_cat_title($prefix='',$display=true){$s='Animali';if($display)echo $s;return $s;}
function category_description($id=null){return '<p>Guide e approfondimenti sugli animali smarriti, avvistati o in difficoltà.</p>';}
function wp_kses_post($s){return $s;}
function paginate_links($args){return '<span aria-current="page">1</span><a href="'.home_url('/blog/page/2/').'">2</a>';}
function the_posts_pagination($args){echo paginate_links($args);}
function wp_reset_postdata(){$GLOBALS['post_id']=1;}
function get_template_part($slug,$name=null,$args=array()){include get_stylesheet_directory().'/'.$slug.'.php';}
function badaround_child_placeholder_url($category=''){return get_stylesheet_directory_uri().'/assets/event-placeholder-'.$category.'.svg';}
function wp_enqueue_style(...$args){}
function wp_html_excerpt($s,$n,$more=''){return mb_strlen($s)>$n?mb_substr($s,0,$n).$more:$s;}
function wp_json_encode($v,$f=0){return json_encode($v,$f);}
function get_post_time($f,$gmt=false,$id=0){return '2026-11-12T11:00:00+00:00';}
function get_post_modified_time($f,$gmt=false,$id=0){return get_post_time($f);}
class WP_Query{
 public $max_num_pages=2,$i=0,$args;
 function __construct($args=array()){$this->args=$args;$GLOBALS['queries'][]=$args;}
 function have_posts(){return !$GLOBALS['empty']&&$this->i<($GLOBALS['ctx']==='single'?1:3);}
 function the_post(){$GLOBALS['post_id']=++$this->i;}
}
$GLOBALS['main_query']=new WP_Query();
function have_posts(){return $GLOBALS['main_query']->have_posts();}
function the_post(){$GLOBALS['main_query']->the_post();}
function the_content(){echo '<p>Le informazioni locali aiutano le persone a orientarsi.</p><h2>Informazioni utili e collaborazione</h2><p>Una guida leggibile anche da mobile, con <a href="/come-funziona/">approfondimenti sul progetto</a>.</p>';}
function wp_link_pages(){}
function language_attributes(){echo 'lang="it"';}
function bloginfo($n){echo $n==='charset'?'UTF-8':'BadAround';}
function wp_head(){foreach($GLOBALS['hooks']['wp_head']??array() as $cb)$cb();}
function body_class(){echo 'class="blog-fixture"';}
function wp_body_open(){}
function get_page_by_path($s){return null;}
function wp_footer(){}
function wp_date($f){return '2026';}
function get_header(){include get_stylesheet_directory().'/header.php';}
function get_footer(){include get_stylesheet_directory().'/footer.php';}
class BadAround_Identity_WordPress_Portal{public static function enabled(){return true;}public static function account_authenticated(){return true;}}
require get_stylesheet_directory().'/inc/pins.php';require get_stylesheet_directory().'/inc/blog.php';
if($GLOBALS['ctx']==='verify'){
 badaround_blog_query(3);$q=end($GLOBALS['queries']);
 if($q['post_type']!=='post'||$q['post_status']!=='publish'||count($q['tax_query'][0]['terms'])!==7)throw new RuntimeException('Published editorial-only query');
 foreach($GLOBALS['hooks']['init'] as $cb)$cb();if(count($GLOBALS['rules'])!==2)throw new RuntimeException('Scoped blog routes');
 $GLOBALS['ctx']='index';ob_start();wp_head();$head=ob_get_clean();if(substr_count($head,'name="description"')!==1||str_contains($head,'rel="canonical"'))throw new RuntimeException('One description, native singular canonical owner');
 $GLOBALS['ctx']='category';ob_start();wp_head();$head=ob_get_clean();if(substr_count($head,'rel="canonical"')!==1)throw new RuntimeException('Category canonical');
 $GLOBALS['ctx']='single';ob_start();wp_head();$head=ob_get_clean();preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s',$head,$m);$schema=json_decode($m[1],true,512,JSON_THROW_ON_ERROR);if($schema['@type']!=='BlogPosting'||$schema['author']['name']!=='BadAround')throw new RuntimeException('Article schema and institutional author');
 $GLOBALS['empty']=true;ob_start();badaround_blog_home();$home=ob_get_clean();if($home!=='')throw new RuntimeException('No fake Home cards');
 echo "BLOG_NATIVE_PHP_PASS\n";exit;
}
if($GLOBALS['ctx']==='home'){get_header();echo '<main class="ba-container ba-home" id="main-content"><form class="ba-home-search"><span class="ba-home-search__icon"></span><input type="search" data-ba-home-place-input placeholder="Cerca indirizzo, comune o quartiere…"><button type="button" class="ba-button">Cerca</button></form><h2 class="ba-home-display-heading ba-home-display-heading--sentinel" data-test-sentinel-heading><span>Diventa una </span><span class="ba-home-display-heading__accent">Sentinella</span></h2>';badaround_blog_home();echo '</main>';get_footer();}
else include get_stylesheet_directory().'/'.array('index'=>'page-blog.php','category'=>'category.php','single'=>'single-post.php')[$GLOBALS['ctx']];
