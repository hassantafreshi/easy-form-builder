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
            echo wp_json_encode(array('kind'=>$kind,'visit'=>$visit,'status'=>$code,'server'=>wp_remote_retrieve_header($response,'server'),'x-litespeed-cache'=>$cache,'x-litespeed-cache-control'=>wp_remote_retrieve_header($response,'x-litespeed-cache-control'),'cache-control'=>$policy))."\n";
            if ($code !== 200) throw new RuntimeException('Fixture HTTP status is not 200');
            if (!$expected_dynamic) { if (stripos($cache,'hit')!==false) $control_hit = true; }
            elseif (stripos($cache,'hit')!==false || stripos($policy,'no-store')===false) throw new RuntimeException('Dynamic response box is cached or lacks no-store');
        }
    }
    echo $control_hit ? "PASS: ordinary page HIT; response-box pages never HIT and carry no-store.\n" : "INCONCLUSIVE: response-box no-store verified, but control page did not demonstrate server cache HIT.\n";
} catch (Throwable $error) { fwrite(STDERR, $error->getMessage()."\n"); $failed=true; }
finally { foreach ($ids as $id) wp_delete_post($id,true); if ($fid) $wpdb->delete($wpdb->prefix.'emsfb_form',array('form_id'=>$fid)); }
exit($failed ? 1 : 0);
