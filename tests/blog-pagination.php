<?php
/** Pagination regression: newest stays featured, all remaining IDs occur once. */
define('ABSPATH',__DIR__);
function add_action(...$a){} function add_filter(...$a){}
class WP_Query {
 public $posts=array(),$max_num_pages=0;
 function __construct($args){
  $all=range(26,1);
  $all=array_values(array_diff($all,$args['post__not_in']??array()));
  $limit=$args['posts_per_page'];$this->max_num_pages=(int)ceil(count($all)/$limit);
  $this->posts=array_slice($all,($args['paged']-1)*$limit,$limit);
 }
}
require dirname(__DIR__).'/wordpress/themes/badaround-child/inc/blog.php';
$latest=badaround_blog_query(1)->posts[0];
if($latest!==26)throw new RuntimeException('Newest article');
$seen=array();
foreach(array(1,2,3) as $page){
 $q=badaround_blog_query(12,$page,array($latest));
 if($q->max_num_pages!==3)throw new RuntimeException('Page count');
 if(count($q->posts)!==($page===3?1:12))throw new RuntimeException('Page size');
 $seen=array_merge($seen,$q->posts);
}
if($seen!==range(25,1)||count(array_unique($seen))!==25)throw new RuntimeException('Duplicate or omitted article');
echo "BLOG_PAGINATION_PASS\n";
