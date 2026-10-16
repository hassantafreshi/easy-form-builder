<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/** Real WordPress publishing and content classification, isolated temporary posts. */
require dirname(__DIR__, 4) . '/wp-load.php';
use Emsfb\Cache_Compat;
if (($argv[1] ?? '') === '--bootstrap') {
    echo 'BOOT_OK';
    exit;
}
$posts = array(); $passed = 0; $purged = array();
$scan_before = get_option(Cache_Compat::SCAN, null);
$event_before = wp_next_scheduled(Cache_Compat::SCAN);
$health_before = get_option(\Emsfb\Request_Health::OPTION,null);
$broken_adapter = static function () { throw new RuntimeException('Test adapter failure'); };
add_action('litespeed_purge_post', static function ($id) use (&$purged) { $purged[] = (int)$id; });
$check = static function ($ok, $label) use (&$passed) { if (!$ok) throw new RuntimeException($label); $passed++; };
$create = static function ($content, $type='page') use (&$posts) {
    $id = wp_insert_post(array('post_title'=>'EFB cache isolated test','post_status'=>'publish','post_type'=>$type,'post_content'=>$content), true);
    if (is_wp_error($id)) throw new RuntimeException('Test post creation failed');
    $posts[] = $id; return $id;
};
try {
    $check(Cache_Compat::can_cache(true)===true, 'Ordinary request retains previous cache policy');
    $check(!Cache_Compat::contains('Ordinary article'), 'Ordinary content stays cacheable');
    $check(!Cache_Compat::contains('[EMS_Form_Builder_other id="1"]'), 'Similar shortcode is not a form');
    foreach (array('[EMS_Form_Builder id="134"]','[ems_form_builder id="134"]','[Easy_Form_Builder_confirmation_code_finder]') as $shortcode) {
        $id=$create($shortcode);
        $check(get_post_meta($id,Cache_Compat::META,true)===get_permalink($id), 'Published shortcode is classified automatically');
        $check(in_array($id,$purged,true), 'Existing page cache is purged');
    }
    $block='<!-- wp:group --><div><!-- wp:easy-form-builder/form {"formId":"tracking"} /--></div><!-- /wp:group -->';
    $id=$create($block);
    $check((bool)get_post_meta($id,Cache_Compat::META,true), 'Nested response box block detected');
    $worker=proc_open(array(PHP_BINARY,__FILE__,'--bootstrap'),array(0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w')),$pipes);
    if (!is_resource($worker)) throw new RuntimeException('Bootstrap worker could not start');
    fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    $check(proc_close($worker)===0&&strpos($out,'BOOT_OK')!==false,'Real LiteSpeed option reads during plugin bootstrap do not require WP_Rewrite');
    for ($visit=0;$visit<2;$visit++) {
        $response=wp_remote_get(get_permalink($id),array('timeout'=>20));
        $check(!is_wp_error($response)&&wp_remote_retrieve_response_code($response)===200&&strpos(wp_remote_retrieve_header($response,'cache-control'),'no-store')!==false,'First and repeat HTTP visits to response-box block carry no-store');
    }
    $check(!Cache_Compat::contains('<!-- wp:easy-form-builder/form {"formId":""} /-->'), 'Empty block does not exclude a page');
    $reusable=$create('[EMS_Form_Builder id="134"]','wp_block');
    $page=$create('<!-- wp:block {"ref":'.$reusable.'} /-->');
    $check((bool)get_post_meta($page,Cache_Compat::META,true), 'Reusable block is followed');
    wp_update_post(array('ID'=>$reusable,'post_content'=>'<!-- wp:block {"ref":'.$reusable.'} /-->'));
    $check(!Cache_Compat::post_contains(get_post($page)), 'Reusable block cycles terminate');
    Cache_Compat::saved($page,get_post($page));
    $check(!get_post_meta($page,Cache_Compat::META,true), 'Removed reusable form is unclassified');
    $elementor=$create('Elementor fixture');
    update_post_meta($elementor,'_elementor_data',wp_slash(wp_json_encode(array(array('widgetType'=>'emsfb_form','settings'=>array('form_id'=>'tracking'))))));
    $check((bool)get_post_meta($elementor,Cache_Compat::META,true), 'Elementor metadata saved after post is detected');
    delete_post_meta($elementor,'_elementor_data');
    $check(!get_post_meta($elementor,Cache_Compat::META,true), 'Removed Elementor widget is unclassified');
    $ids=Cache_Compat::exclude_page_ids(array(987654));
    $check(in_array(987654,$ids,true) && in_array($id,$ids,true), 'Breeze page rules preserve administrator exclusions');
    $ls=Cache_Compat::litespeed_paths(array('/admin-rule'));
    $check(in_array('/admin-rule',$ls,true) && count($ls)>1, 'LiteSpeed canonical URI rules preserve existing rules');
    $check(in_array('/admin-rule',Cache_Compat::rocket_paths(array('/admin-rule')),true), 'Rocket exclusions are additive');
    $check(in_array('/admin-rule',Cache_Compat::relative_paths(array('/admin-rule')),true), 'SiteGround exclusions are additive');
    wp_update_post(array('ID'=>$id,'post_status'=>'draft'));
    $check(!get_post_meta($id,Cache_Compat::META,true) && !in_array($id,Cache_Compat::page_ids(),true), 'Unpublished page removed from automatic exclusions');
    $scripts=wp_scripts();
    wp_register_script('efb-cache-test',EMSFB_PLUGIN_URL.'public/assets/js/core-efb.js',array('jquery'));
    $handles=Cache_Compat::exclude_handles(array('admin-owned'));
    $check(in_array('admin-owned',$handles,true) && in_array('efb-cache-test',$handles,true) && in_array('jquery-core',$handles,true), 'Script dependency chain excluded without removing existing exclusions');
    $tag=Cache_Compat::script_tag('<script src="test.js"></script>','efb-cache-test','test.js');
    $check(strpos($tag,'data-no-optimize="1"')!==false && strpos($tag,'data-wpacu-skip="1"')!==false, 'LiteSpeed and Asset CleanUp script attributes emitted');
    $check(Cache_Compat::concat(true,'efb-cache-test')===false, 'Jetpack does not combine critical scripts');
    $check(Cache_Compat::keep_scripts(array('efb-cache-test','unrelated-script'))===array('unrelated-script'), 'Asset CleanUp cannot unload the form dependency chain');
    $guard=wp_remote_get(EMSFB_PLUGIN_URL.'tests/test-stripe-live-verification.php',array('timeout'=>10));
    $check(!is_wp_error($guard)&&wp_remote_retrieve_response_code($guard)===404, 'Payment regression scripts cannot execute over HTTP');
    add_action('litespeed_control_set_nocache',$broken_adapter,999);
    Cache_Compat::bypass();
    $check(defined('DONOTCACHEPAGE') && DONOTCACHEPAGE && Cache_Compat::can_cache(true)===false && Cache_Compat::skip_optimization(false)===true, 'Rendered form disables page caching and unsafe optimization');
    if (class_exists('LiteSpeed\\Optimize')) {
        $optimizer_ref=new ReflectionClass('LiteSpeed\\Optimize');
        $optimizer=$optimizer_ref->newInstanceWithoutConstructor();
        $finalize=$optimizer_ref->getMethod('_finalize');$finalize->setAccessible(true);
        $html='<html><head><style>.form{display:block}</style></head><body><script src="core-efb.js"></script></body></html>';
        $check($finalize->invoke($optimizer,$html)===$html, 'Real LiteSpeed optimizer leaves form HTML/scripts/styles intact');
    }
    $check(isset(get_option(\Emsfb\Request_Health::OPTION)['server:cache:500:cache_adapter_failed']), 'Cache adapter exception is reported without breaking form rendering');
    remove_action('litespeed_control_set_nocache',$broken_adapter,999);
    echo "$passed cache compatibility checks passed.\n";
} finally {
    remove_action('litespeed_control_set_nocache',$broken_adapter,999);
    foreach (array_reverse($posts) as $id) wp_delete_post($id,true);
    if ($scan_before===null) delete_option(Cache_Compat::SCAN); else update_option(Cache_Compat::SCAN,$scan_before,false);
    if (!$event_before) wp_clear_scheduled_hook(Cache_Compat::SCAN);
    wp_deregister_script('efb-cache-test');
    if ($health_before===null) delete_option(\Emsfb\Request_Health::OPTION); else update_option(\Emsfb\Request_Health::OPTION,$health_before,false);
}
