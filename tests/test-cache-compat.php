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
$ls_before = get_option('litespeed.conf.cache-exc',null);
$repair_before = get_option(Cache_Compat::LSCWP_REPAIR,null);
$user_before = get_current_user_id();
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
    foreach (array('option_litespeed.conf.cache-exc','default_option_litespeed.conf.cache-exc','option_breeze_exclude_url_pages','default_option_breeze_exclude_url_pages') as $hook) {
        $check(!has_filter($hook), 'Another plugin\'s stored option is never filtered: '.$hook);
    }
    $check(!method_exists('Emsfb\\Cache_Compat','litespeed_paths') && !method_exists('Emsfb\\Cache_Compat','exclude_page_ids'), 'Option rewriting helpers are gone');
    update_option('litespeed.conf.cache-exc',wp_json_encode(array('/members/','^/private-area')));
    $check(get_option('litespeed.conf.cache-exc')===wp_json_encode(array('/members/','^/private-area')), 'LiteSpeed Do Not Cache URIs (stored as JSON) reach LiteSpeed unchanged');
    $rocket=Cache_Compat::rocket_paths(array('/admin-rule'));
    $check(in_array('/admin-rule',$rocket,true) && in_array(preg_quote(wp_parse_url(get_permalink($id),PHP_URL_PATH),'#'),$rocket,true), 'Rocket exclusions are additive and list form pages');
    $optout=static function ($bypass,$context,$post_id) use ($id) { return $context==='singular' && (int)$post_id===(int)$id ? false : $bypass; };
    add_filter('emsfb_cache_bypass',$optout,10,3);
    $check(!in_array(get_permalink($id),Cache_Compat::urls(),true), 'emsfb_cache_bypass opt-out also removes the page from URI exclusion lists');
    remove_filter('emsfb_cache_bypass',$optout,10);
    $check(in_array(get_permalink($id),Cache_Compat::urls(),true), 'Form page is listed again without the opt-out');
    $check(in_array('/admin-rule',Cache_Compat::relative_paths(array('/admin-rule')),true), 'SiteGround exclusions are additive');
    wp_update_post(array('ID'=>$id,'post_status'=>'draft'));
    $check(!get_post_meta($id,Cache_Compat::META,true) && !in_array($id,Cache_Compat::page_ids(),true), 'Unpublished page removed from automatic exclusions');
    $scripts=wp_scripts();
    wp_register_script('efb-cache-test',EMSFB_PLUGIN_URL.'public/assets/js/core-efb.js',array('jquery'));
    $handles=Cache_Compat::exclude_handles(array('admin-owned'));
    $check(in_array('admin-owned',$handles,true) && in_array('efb-cache-test',$handles,true) && in_array('jquery-core',$handles,true), 'Script dependency chain excluded without removing existing exclusions');
    $tag=Cache_Compat::script_tag('<script src="test.js"></script>','efb-cache-test','test.js');
    $check(strpos($tag,'data-no-optimize="1"')!==false && strpos($tag,'data-wpacu-skip="1"')!==false, 'LiteSpeed and Asset CleanUp script attributes emitted');
    $check(stripos($tag,'cfasync')===false, 'No data-cfasync: Cloudflare Rocket Loader keeps EFB in order with its inline data and jQuery');
    $group="<script id=\"efb-cache-test-js-before\">var a=1;</script>\n<script src=\"test.js\" id=\"efb-cache-test-js\"></script>\n<script id='efb-cache-test-js-after'>var b=2;</script>\n";
    $marked=Cache_Compat::script_tag($group,'efb-cache-test','test.js');
    $check(substr_count($marked,'data-no-defer="1"')===3 && substr_count($marked,'data-no-optimize="1"')===3 && stripos($marked,'cfasync')===false && strpos($marked,'var a=1;')!==false, 'Every script of the handle (before, file, after) is marked the same way');
    $regex=new ReflectionMethod('Emsfb\\Cache_Compat','mark_scripts'); $regex->setAccessible(true);
    $fallback=$regex->invoke(null,$group.'<SCRIPT data-no-defer="0" src="x.js"></SCRIPT>',array('data-no-optimize'=>'1','data-no-defer'=>'1','data-wpacu-skip'=>'1'));
    $check(substr_count($fallback,'data-no-optimize="1"')===4 && substr_count($fallback,'data-no-defer=')===4 && strpos($fallback,'var b=2;')!==false && stripos($fallback,'cfasync')===false, 'WordPress < 6.2 fallback marks every script once and keeps existing attributes');
    $check(Cache_Compat::script_tag('<script src="other.js"></script>','unrelated-script','other.js')==='<script src="other.js"></script>', 'Scripts outside the form chain are untouched');
    $check(!has_filter('jetpack_boost_render_blocking_js_exclude_handles'), 'Jetpack Boost may move EFB files together with their inline data (no pinned handles)');
    $check(has_filter('sgo_js_async_exclude',array('Emsfb\\Cache_Compat','exclude_handles'))!==false, 'SiteGround async exclusion is kept');
    $page="<head><script src='/wp-includes/js/jquery/jquery.min.js' id='jquery-core-js'></script>\n<script src=\"/wp-includes/js/jquery/jquery-migrate.min.js\" id=\"jquery-migrate-js\"></script><script data-no-defer=\"1\" id=\"jquery-ui-core-js\" src=\"ui.js\"></script></head><body><script id=\"Emsfb-core_js-js\" src=\"core-efb.js\"></script></body>";
    $ls_page=Cache_Compat::litespeed_buffer($page);
    $check(substr_count($ls_page,'data-no-defer="1"')===3 && strpos($ls_page,"<script data-no-defer=\"1\" src='/wp-includes/js/jquery/jquery.min.js' id='jquery-core-js'>")!==false && strpos($ls_page,'<script data-no-defer="1" src="/wp-includes/js/jquery/jquery-migrate.min.js"')!==false, 'LiteSpeed: jQuery printed in <head> is not deferred on a page that runs EFB');
    $check(Cache_Compat::litespeed_buffer($ls_page)===$ls_page, 'LiteSpeed jQuery marking is idempotent');
    $plain=str_replace('Emsfb-core_js-js','theme-js',$page);
    $check(Cache_Compat::litespeed_buffer($plain)===$plain, 'Pages without an EFB form keep LiteSpeed\'s own defer decision for jQuery');
    $check(has_filter('litespeed_buffer_finalize',array('Emsfb\\Cache_Compat','litespeed_buffer'))===19, 'Runs before LiteSpeed\'s optimizer (priority 20)');
    // Autoptimize passes a CSV string or a pattern => '' | 'async' | 'remove' array; Breeze a CSV string.
    $ao=apply_filters('autoptimize_filter_js_exclude','admin-a.js, admin-b.js','');
    $check(is_string($ao) && strpos($ao,'admin-a.js,admin-b.js,')===0 && strpos($ao,'core-efb.js')!==false && strpos($ao,'sitekye_emsFormBuilder')!==false && strpos($ao,',,')===false, 'Autoptimize CSV exclusions are kept and EFB patterns added');
    $ao_array=apply_filters('autoptimize_filter_js_exclude',array('async-me.js'=>'async','drop-me.js'=>'remove'),'');
    $check(is_array($ao_array) && $ao_array['async-me.js']==='async' && $ao_array['drop-me.js']==='remove' && isset($ao_array['core-efb.js']) && $ao_array['core-efb.js']==='' && !isset($ao_array['Array']), 'Autoptimize array exclusions keep their async/remove values and gain EFB patterns');
    $breeze=apply_filters('breeze_filter_js_exclude','seal.js');
    $check(is_string($breeze) && strpos($breeze,'seal.js,')===0 && strpos($breeze,'efb_loading_ui_')!==false, 'Breeze exclusions stay a CSV string');
    $check(has_action('wp_print_footer_scripts',array('Emsfb\\Request_Health','enqueue'))===1 && has_action('wp_footer',array('Emsfb\\Request_Health','enqueue'))===1, 'A popup or late footer form still gets request-health-efb.js');
    $check(Cache_Compat::concat(true,'efb-cache-test')===false, 'Jetpack does not combine critical scripts');
    $check(Cache_Compat::keep_scripts(array('efb-cache-test','unrelated-script'))===array('unrelated-script'), 'Asset CleanUp cannot unload the form dependency chain');
    $guard=wp_remote_get(EMSFB_PLUGIN_URL.'tests/test-stripe-live-verification.php',array('timeout'=>10));
    $check(!is_wp_error($guard)&&wp_remote_retrieve_response_code($guard)===404, 'Payment regression scripts cannot execute over HTTP');
    // One-time repair of a LiteSpeed list a 4.3.1 pre-release corrupted (one JSON element per save).
    $admin_rules=array('/members/','^/private-area');
    $save1=wp_json_encode(array(wp_json_encode($admin_rules),'^/contact-efb-test/$'));
    $save2=wp_json_encode(array($save1,'^/contact-efb-test/$'));
    $repair=static function ($stored,$as_admin=true) {
        delete_option(Cache_Compat::LSCWP_REPAIR);
        if ($stored===null) delete_option('litespeed.conf.cache-exc'); else update_option('litespeed.conf.cache-exc',$stored);
        wp_set_current_user($as_admin ? 1 : 0);
        Cache_Compat::repair_litespeed_exclusions();
        return get_option('litespeed.conf.cache-exc',null);
    };
    $check(has_action('admin_init',array('Emsfb\\Cache_Compat','repair_litespeed_exclusions'))!==false, 'LiteSpeed list repair is scheduled for wp-admin');
    $check($repair($save1)===wp_json_encode(array('/members/','^/private-area','^/contact-efb-test/$')), 'Repair unpacks a list corrupted by one LiteSpeed settings save');
    $check($repair($save2)===wp_json_encode(array('/members/','^/private-area','^/contact-efb-test/$')), 'Repair unpacks a list corrupted twice and drops the duplicate copy');
    $check((int)get_option(Cache_Compat::LSCWP_REPAIR)===1, 'Repair records that it ran');
    $repaired=get_option('litespeed.conf.cache-exc');
    Cache_Compat::repair_litespeed_exclusions();
    delete_option(Cache_Compat::LSCWP_REPAIR); Cache_Compat::repair_litespeed_exclusions();
    $check(get_option('litespeed.conf.cache-exc')===$repaired, 'Repair is idempotent');
    foreach (array('clean'=>wp_json_encode($admin_rules),'empty-list'=>'[]','empty'=>'','plain-lines'=>"/a/\n/b/",'json-object'=>'{"a":"[\\"x\\"]"}','numeric-rule'=>wp_json_encode(array('[1]','/x/')),'broken-json'=>wp_json_encode(array('[not json','/x/'))) as $label=>$stored) {
        $check($repair($stored)===$stored, 'Repair leaves an uncorrupted list untouched: '.$label);
    }
    $check($repair(null)===null, 'Repair never creates the LiteSpeed option');
    $check($repair(wp_json_encode(array('[]','^/contact-efb-test/$')))===wp_json_encode(array('^/contact-efb-test/$')), 'Repair unpacks a corrupted empty list');
    $check($repair($save1,false)===$save1 && !get_option(Cache_Compat::LSCWP_REPAIR), 'Repair runs only for an administrator');
    wp_set_current_user($user_before);
    Cache_Compat::render_bypass(1,'render');
    $check(!defined('DONOTCACHEPAGE') && Cache_Compat::can_cache(true)===true, 'A form rendered outside the queried post content (widget, footer, popup) leaves the page cacheable');
    $veto=static function ($bypass,$context) { return $context==='session' ? false : $bypass; };
    add_filter('emsfb_cache_bypass',$veto,10,2);
    Cache_Compat::render_bypass(1,'session');
    $check(!defined('DONOTCACHEPAGE') && Cache_Compat::can_cache(true)===true, 'emsfb_cache_bypass can veto the bypass');
    remove_filter('emsfb_cache_bypass',$veto,10);
    add_action('litespeed_control_set_nocache',$broken_adapter,999);
    Cache_Compat::render_bypass(1,'session');
    $check(defined('DONOTCACHEPAGE') && DONOTCACHEPAGE && Cache_Compat::can_cache(true)===false && Cache_Compat::skip_optimization(false)===true, 'Payment/Autofill form (page-embedded session) disables page caching and unsafe optimization');
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
    if ($ls_before===null) delete_option('litespeed.conf.cache-exc'); else update_option('litespeed.conf.cache-exc',$ls_before);
    if ($repair_before===null) delete_option(Cache_Compat::LSCWP_REPAIR); else update_option(Cache_Compat::LSCWP_REPAIR,$repair_before);
    wp_set_current_user($user_before);
    if ($health_before===null) delete_option(\Emsfb\Request_Health::OPTION); else update_option(\Emsfb\Request_Health::OPTION,$health_before,false);
}
