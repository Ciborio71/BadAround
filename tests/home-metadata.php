<?php
define('ABSPATH',__DIR__);$GLOBALS['front']=true;$GLOBALS['hooks']=[];
function is_front_page(){return $GLOBALS['front'];}
function esc_attr($s){return htmlspecialchars($s,ENT_QUOTES,'UTF-8');}
function add_action($hook,$callback,$priority=10){$GLOBALS['hooks'][$hook]=[$callback,$priority];}
$owner=$argv[1]??'none';if($owner==='yoast')define('WPSEO_VERSION','fixture');if($owner==='rankmath')define('RANK_MATH_VERSION','fixture');
require dirname(__DIR__).'/wordpress/themes/badaround-child/inc/home-seo.php';
ob_start();badaround_home_meta_description();$html=ob_get_clean();
if($owner==='none'){
 if(substr_count($html,'name="description"')!==1||!str_contains($html,'Segnalazioni di furti')||str_contains($html,'robots'))throw new RuntimeException('Home description missing/duplicated or robots modified');
}else if($html!=='')throw new RuntimeException('External SEO owner must suppress duplicate metadata');
$GLOBALS['front']=false;ob_start();badaround_home_meta_description();$html=ob_get_clean();if($html!=='')throw new RuntimeException('Home metadata leaked to other screens');
if(array_keys($GLOBALS['hooks'])!==['wp_head'])throw new RuntimeException('Unexpected hook scope');
echo "HOME_METADATA_SUITE_PASS owner={$owner}\n";
