<?php
/** Real preview template with synthetic public fixtures; no DB or external requests. */
define('ABSPATH',__DIR__);$theme=dirname(__DIR__).'/wordpress/themes/badaround-child';
function get_stylesheet_directory_uri(){return '/wordpress/themes/badaround-child';}
function home_url($path='/'){return $path;}
function add_query_arg($key,$value,$url){return $url.'?'.rawurlencode($key).'='.rawurlencode($value);}
function esc_url($s){return htmlspecialchars($s,ENT_QUOTES);}
function esc_attr($s){return htmlspecialchars($s,ENT_QUOTES);}
function esc_html($s){return htmlspecialchars($s,ENT_QUOTES);}
function esc_html_e($s,$d=''){echo esc_html($s);}
function esc_attr_e($s,$d=''){echo esc_attr($s);}
function __($s,$d=''){return $s;}
function wp_parse_args($a,$b){return array_merge($b,$a);}
function language_attributes(){echo 'lang="it"';}function bloginfo($k){echo 'UTF-8';}
function wp_body_open(){}function body_class(){echo 'class="ba-home-two-page"';}function is_page($s){return false;}function is_singular($s){return false;}function is_category(){return false;}
function wp_head(){foreach(['base','components','shell','blog','home-two']as $s)echo '<link rel="stylesheet" href="/wordpress/themes/badaround-child/assets/css/'.$s.'.css">';}
function get_header(){include $GLOBALS['theme'].'/header.php';}
function get_footer(){echo '<script src="/wordpress/themes/badaround-child/assets/js/navigation.js"></script><script src="/wordpress/themes/badaround-child/assets/js/home-two.js"></script></body></html>';}
function badaround_pin_svg($slug){$color=['veicoli'=>'#1677E8','animali'=>'#7040C8','case-e-attivita'=>'#C9153D'][$slug]??'#087f8c';return '<svg viewBox="0 0 48 60"><path fill="'.$color.'" d="M24 59S1 34 1 24a23 23 0 1 1 46 0c0 10-23 35-23 35Z"/><circle cx="24" cy="24" r="16" fill="white"/><circle cx="24" cy="24" r="6"/></svg>';}
function badaround_child_event_category_slug($id){return ['veicoli','animali','case-e-attivita'][($id-1)%3];}
function badaround_event_public_presentation($id){return $id===2?['mode'=>'approximate_area','radius_meters'=>100]:['mode'=>'exact_point'];}
function badaround_child_event_image_url($id,$size){return get_stylesheet_directory_uri().'/assets/event-placeholder-'.badaround_child_event_category_slug($id).'.svg';}
function badaround_child_placeholder_url($cat='',$mode=''){return get_stylesheet_directory_uri().'/assets/event-placeholder-'.($cat?:'veicoli').'.svg';}
function sanitize_html_class($s){return $s;}
function get_template_part($path,$name=null,$args=[]){if(str_contains($path,'precision-legend')){echo '<p>Punto esatto · Area approssimata</p>';return;}include $GLOBALS['theme'].'/'.$path.'.php';}
function badaround_blog_home(){echo '<section class="ba-blog ba-blog-home"><h2>Il blog di BadAround</h2><p>Informarsi, condividere, fare rete.</p></section>';}
class BadAround_Discovery_Query{function discover($args){$items=[];for($i=1;$i<=6;$i++)$items[]=['id'=>$i,'title'=>['Veicolo segnalato rubato a Roma, in Via dei Colli Portuensi','Cane smarrito a Firenze','Tentativo di effrazione a Bologna'][($i-1)%3],'permalink'=>'/eventi/esempio-'.$i.'/','event_type'=>['name'=>'Segnalazione'],'public_place_name'=>'Roma','excerpt'=>'Una segnalazione pubblica di esempio per verificare la presentazione.'];return ['items'=>$items];}}
ob_start();include $theme.'/page-home-2.php';$html=ob_get_clean();
if(substr_count($html,'<h1 ')!==1)throw new RuntimeException('H1');
if(str_contains($html,'data-ba-map '))throw new RuntimeException('Embedded map');
if(substr_count($html,'ba-event-card__media-category')!==0)throw new RuntimeException('Duplicate placeholder badge');
if(substr_count($html,'class="h2-category"')!==6)throw new RuntimeException('Categories');
file_put_contents(dirname(__DIR__).'/home-two-fixture.html',$html);echo "HOME_TWO_RENDER_PASS\n";
