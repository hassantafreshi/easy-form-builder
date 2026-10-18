<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/** Compares genuine server cache headers on temporary control and response-box pages. */
require dirname(__DIR__, 4) . '/wp-load.php';
$ids = array(); $failed = false; $fid=0;
global $wpdb;
try {
    $structure=array(
        array('type'=>'form','steps'=>1,'formName'=>'Cache test','captcha'=>false,'sendEmail'=>false,'smsnoti'=>'0','stateForm'=>'0','trackingCode'=>true),
        array('id_'=>'1','type'=>'step','dataId'=>'1','id'=>'1','name'=>'Step','step'=>'1','amount'=>1,'visible'=>1),
        array('id_'=>'cache_text','dataId'=>'cache_text-id','type'=>'text','name'=>'Text','step'=>'1','amount'=>2,'required'=>'0','visible'=>1,'minlength'=>0,'maxlength'=>1000)
    );
    $wpdb->insert($wpdb->prefix.'emsfb_form',array('form_name'=>'Isolated server cache test','form_type'=>'form','form_structer'=>wp_json_encode($structure),'form_email'=>'','form_created_by'=>1));
    $fid=(int)$wpdb->insert_id;
    if (!$fid) throw new RuntimeException('Could not create isolated form');
    $fixtures = array(
        'control' => '<p>EFB isolated cache control.</p>',
        'response-shortcode' => '[Easy_Form_Builder_confirmation_code_finder]',
        'response-block' => '<!-- wp:easy-form-builder/form {"formId":"tracking"} /-->',
        'form-shortcode' => '[EMS_Form_Builder id="'.$fid.'"]',
        'form-block' => '<!-- wp:easy-form-builder/form {"formId":"'.$fid.'"} /-->',
    );
    $control_hit = false;
    foreach ($fixtures as $kind => $content) {
        $id = wp_insert_post(array('post_type'=>'page','post_status'=>'publish','post_title'=>'EFB isolated server cache '.wp_generate_uuid4(),'post_content'=>$content), true);
        if (is_wp_error($id)) throw new RuntimeException('Could not create isolated fixture');
        $ids[] = $id;
        for ($visit=1; $visit<=3; $visit++) {
            $expected_dynamic=$kind!=='control';
            $response = wp_remote_get(get_permalink($id), array('timeout'=>30,'headers'=>array('User-Agent'=>'EFB-cache-validation/1.0')));
            if (is_wp_error($response)) throw new RuntimeException('HTTP request failed: '.$response->get_error_code());
            $code = wp_remote_retrieve_response_code($response);
            $cache = (string)wp_remote_retrieve_header($response, 'x-litespeed-cache');
            $policy = (string)wp_remote_retrieve_header($response, 'cache-control');
            $edge = (string)wp_remote_retrieve_header($response, 'cf-edge-cache');
            echo wp_json_encode(array('kind'=>$kind,'visit'=>$visit,'status'=>$code,'server'=>wp_remote_retrieve_header($response,'server'),'x-litespeed-cache'=>$cache,'x-litespeed-cache-control'=>wp_remote_retrieve_header($response,'x-litespeed-cache-control'),'cache-control'=>$policy,'cf-edge-cache'=>$edge))."\n";
            if ($code !== 200) throw new RuntimeException('Fixture HTTP status is not 200');
            if (!$expected_dynamic) { if (stripos($cache,'hit')!==false) $control_hit = true; }
            elseif (stripos($cache,'hit')!==false || stripos($policy,'no-store')===false) throw new RuntimeException('Dynamic response box is cached or lacks no-store');
            elseif (stripos($edge,'no-cache')===false) throw new RuntimeException('Dynamic page lacks cf-edge-cache: no-cache (Cloudflare APO)');
        }
    }
    echo $control_hit ? "PASS: ordinary page HIT; response-box pages never HIT and carry no-store.\n" : "INCONCLUSIVE: response-box no-store verified, but control page did not demonstrate server cache HIT.\n";
    // A form in a block theme's footer template part runs inside the main loop (WordPress 6.4+)
    // but is not part of the page content: that page must stay cacheable.
    if (function_exists('wp_is_block_theme') && wp_is_block_theme()) {
        $theme = get_stylesheet();
        $existing = function_exists('get_block_template') ? get_block_template($theme.'//footer','wp_template_part') : null;
        if ($existing && !empty($existing->wp_id)) {
            echo "INCONCLUSIVE: the site already customised its footer template part; not replaced.\n";
        } else {
            $part = wp_insert_post(array('post_type'=>'wp_template_part','post_status'=>'publish','post_name'=>'footer','post_title'=>'EFB isolated footer','post_content'=>($existing ? $existing->content : '').'<!-- wp:easy-form-builder/form {"formId":"'.$fid.'"} /-->'), true);
            if (is_wp_error($part)) throw new RuntimeException('Could not create isolated template part');
            $ids[] = $part;
            wp_set_object_terms($part, $theme, 'wp_theme');
            wp_set_object_terms($part, 'footer', 'wp_template_part_area');
            $plain = wp_insert_post(array('post_type'=>'page','post_status'=>'publish','post_title'=>'EFB isolated footer form '.wp_generate_uuid4(),'post_content'=>'<p>No form in the content.</p>'), true);
            if (is_wp_error($plain)) throw new RuntimeException('Could not create isolated footer fixture');
            $ids[] = $plain;
            foreach (array('footer-template-part'=>get_permalink($plain), 'footer-template-part-track'=>add_query_arg('track', 'EFBTESTTRACK1', get_permalink($plain))) as $kind => $url) {
                $response = wp_remote_get($url, array('timeout'=>30,'headers'=>array('User-Agent'=>'EFB-cache-validation/1.0')));
                if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) throw new RuntimeException('Footer fixture HTTP request failed');
                $policy = (string) wp_remote_retrieve_header($response, 'cache-control');
                $body = wp_remote_retrieve_body($response);
                $rendered = strpos($body, 'Emsfb-core_js-js') !== false || strpos($body, 'body_tracker_emsFormBuilder') !== false;
                echo wp_json_encode(array('kind'=>$kind,'cache-control'=>$policy,'cf-edge-cache'=>(string) wp_remote_retrieve_header($response, 'cf-edge-cache'),'form_rendered'=>$rendered,'request_health'=>strpos($body, 'efb-request-health-js') !== false))."\n";
                if (!$rendered) { echo "INCONCLUSIVE: the footer template part did not render the form (theme keeps its own footer part).\n"; break; }
                if ($kind === 'footer-template-part') {
                    if (stripos($policy, 'no-store') !== false) throw new RuntimeException('A footer form must not take the page out of the page cache');
                    if (strpos($body, 'efb-request-health-js') === false) throw new RuntimeException('A cacheable footer form needs request-health-efb.js (EFBRuntime.prepare)');
                    echo "PASS: footer template-part form leaves the page cacheable.\n";
                } else {
                    if (stripos($policy, 'no-store') === false) throw new RuntimeException('A ?track= response view must never be page-cached');
                    echo "PASS: ?track= view of the same page is excluded from page caches.\n";
                }
            }
        }
    }
} catch (Throwable $error) { fwrite(STDERR, $error->getMessage()."\n"); $failed=true; }
finally { foreach ($ids as $id) wp_delete_post($id,true); if ($fid) $wpdb->delete($wpdb->prefix.'emsfb_form',array('form_id'=>$fid)); }
exit($failed ? 1 : 0);
