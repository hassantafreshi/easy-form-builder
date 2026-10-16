<?php
namespace Emsfb;

defined('ABSPATH') || exit;

/** Owns only EFB's page classification; never overwrites an administrator's rules. */
class Cache_Compat {
    const META = '_emsfb_dynamic_page_v1';
    const SCAN = 'emsfb_cache_scan_v1';
    private static $dynamic = false;
    private static $ids = array();
    private static $canonical = array();

    public static function register() {
        global $wp_version;
        add_action('wp', array(__CLASS__, 'current_page'), 0);
        add_action(version_compare($wp_version,'5.6','>=') ? 'wp_after_insert_post' : 'save_post', array(__CLASS__, 'saved'), 100, 2);
        foreach (array('added_post_meta', 'updated_post_meta', 'deleted_post_meta') as $hook) {
            add_action($hook, array(__CLASS__, 'metadata'), 100, 4);
        }
        add_action('before_delete_post', array(__CLASS__, 'deleted'));
        add_action('admin_init', array(__CLASS__, 'scan'));
        add_action(self::SCAN, array(__CLASS__, 'scan'));
        add_action('activated_plugin', array(__CLASS__, 'rescan'));
        add_action('update_option_permalink_structure', array(__CLASS__, 'rescan'));
        add_filter('rocket_cache_reject_uri', array(__CLASS__, 'rocket_paths'));
        foreach (array('option_litespeed.conf.cache-exc', 'default_option_litespeed.conf.cache-exc') as $hook) {
            add_filter($hook, array(__CLASS__, 'litespeed_paths'));
        }
        // These callbacks preserve another component's decision to bypass caching.
        add_filter('wpo_can_cache_page', array(__CLASS__, 'can_cache'), 999);
        add_filter('do_createsupercache', array(__CLASS__, 'can_cache'), 999);
        add_filter('autoptimize_filter_noptimize', array(__CLASS__, 'skip_optimization'), 999);
        add_filter('cache_enabler_bypass_cache', array(__CLASS__, 'skip_optimization'), 999);
        add_filter('jetpack_boost_cache_request_cacheable', array(__CLASS__, 'can_cache'), 999);
        add_filter('sgo_exclude_urls_from_cache', array(__CLASS__, 'relative_paths'));
        foreach (array('option_breeze_exclude_url_pages','default_option_breeze_exclude_url_pages') as $hook) add_filter($hook, array(__CLASS__, 'exclude_page_ids'));
        foreach (array('sgo_js_async_exclude','sgo_javascript_combine_exclude','jetpack_boost_render_blocking_js_exclude_handles') as $hook) add_filter($hook, array(__CLASS__, 'exclude_handles'));
        add_filter('js_do_concat', array(__CLASS__, 'concat'), 999, 2);
        add_filter('script_loader_tag', array(__CLASS__, 'script_tag'), 999, 3);
        add_filter('script_loader_src', array(__CLASS__, 'asset_src'), 1, 2);
        add_filter('wpo_minify_exclude_contents', array(__CLASS__, 'skip_optimization'), 999);
        add_filter('w3tc_minify_enable', array(__CLASS__, 'can_cache'), 999);
        add_filter('wpacu_filter_scripts_list_unload', array(__CLASS__, 'keep_scripts'), 999);
    }

    public static function contains($content, $seen = array(), $depth = 0) {
        if (!is_string($content) || $depth > 12) return false;
        foreach (array('EMS_Form_Builder', 'ems_form_builder', 'Easy_Form_Builder_confirmation_code_finder') as $tag) {
            if (has_shortcode($content, $tag)) return true;
        }
        foreach (parse_blocks($content) as $block) {
            if (self::block_contains($block, $seen, $depth)) return true;
        }
        return false;
    }

    private static function block_contains($block, $seen, $depth) {
        if ($depth > 12) return false;
        if (($block['blockName'] ?? '') === 'easy-form-builder/form' && !empty($block['attrs']['formId'])) return true;
        if (($block['blockName'] ?? '') === 'core/block' && !empty($block['attrs']['ref'])) {
            $id = (int) $block['attrs']['ref'];
            if (!isset($seen[$id])) {
                $seen[$id] = true;
                $post = get_post($id);
                if ($post && self::contains($post->post_content, $seen, $depth + 1)) return true;
            }
        }
        foreach ($block['innerBlocks'] ?? array() as $inner) {
            if (self::block_contains($inner, $seen, $depth + 1)) return true;
        }
        return false;
    }

    private static function elementor_contains($data, $depth = 0) {
        if (!is_array($data) || $depth > 30) return false;
        if (($data['widgetType'] ?? '') === 'emsfb_form') return true;
        foreach ($data as $value) {
            if (is_array($value) && self::elementor_contains($value, $depth + 1)) return true;
            if (is_string($value) && strpos($value, '[') !== false && self::contains($value)) return true;
        }
        return false;
    }

    public static function post_contains($post) {
        if (!$post instanceof \WP_Post) return false;
        if (self::contains($post->post_content)) return true;
        return self::elementor_contains(json_decode((string) get_post_meta($post->ID, '_elementor_data', true), true));
    }

    public static function current_page() {
        if (is_admin() || !is_singular()) return;
        $post = get_queried_object();
        if (self::post_contains($post)) self::bypass();
    }

    /** Also called at render time for page builders and dynamically inserted shortcodes. */
    public static function bypass() {
        self::$dynamic = true;
        if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
        if (!defined('DONOTMINIFY')) define('DONOTMINIFY', true);
        // LiteSpeed checks this before guest optimization and URI exclusions.
        if (!defined('LITESPEED_NO_PAGEOPTM')) define('LITESPEED_NO_PAGEOPTM', true);
        if (!headers_sent()) {
            nocache_headers();
            header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
        }
        self::invoke('do_action', array('litespeed_control_set_nocache', 'Easy Form Builder interactive form or response box'));
        if (function_exists('wpfc_exclude_current_page')) self::invoke('wpfc_exclude_current_page');
    }

    public static function can_cache($allowed) { return self::$dynamic ? false : $allowed; }
    public static function skip_optimization($skip) { return self::$dynamic ? true : $skip; }

    public static function metadata($meta_id, $post_id, $key, $value) {
        if ($key === '_elementor_data') self::saved($post_id, get_post($post_id));
    }

    public static function saved($id, $post) {
        if (!$post instanceof \WP_Post || wp_is_post_revision($id) || wp_is_post_autosave($id)) return;
        if ($post->post_type === 'wp_block') { self::rescan(); return; }
        if (!is_post_type_viewable($post->post_type)) return;
        $old = get_post_meta($id, self::META, true);
        $url = $post->post_status === 'publish' && self::post_contains($post) ? get_permalink($id) : '';
        if ($url) update_post_meta($id, self::META, esc_url_raw($url));
        else delete_post_meta($id, self::META);
        self::$ids = array();
        if ($old || $url) {
            self::purge($id, array_filter(array($old, $url)));
            self::refresh_config();
        }
    }

    public static function deleted($id) {
        if (get_post_type($id) === 'wp_block') self::rescan();
        $url = get_post_meta($id, self::META, true);
        if (!$url) return;
        delete_post_meta($id, self::META);
        self::$ids = array();
        self::purge($id, array($url));
        self::refresh_config();
    }

    public static function page_ids() {
        $blog = get_current_blog_id();
        // Cache plugins read options while plugins are still loading, before
        // WP_Rewrite and custom post types exist. Do not run WP_Query here.
        global $wpdb;
        if (!isset(self::$ids[$blog])) {
            $rows=$wpdb->get_results($wpdb->prepare("SELECT DISTINCT p.ID,m.meta_value FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id=p.ID WHERE p.post_status='publish' AND m.meta_key=%s ORDER BY p.ID", self::META));
            self::$ids[$blog]=array();self::$canonical[$blog]=array();
            foreach ($rows as $row) {
                self::$ids[$blog][]=(int)$row->ID;
                if (is_string($row->meta_value) && $row->meta_value !== '') self::$canonical[$blog][]=$row->meta_value;
            }
        }
        return self::$ids[$blog];
    }

    public static function exclude_page_ids($ids) { return array_values(array_unique(array_merge((array)$ids, self::page_ids()))); }

    public static function urls() {
        self::page_ids();
        // One bounded-to-marked-pages query, including during plugin bootstrap.
        return array_values(array_unique(self::$canonical[get_current_blog_id()]));
    }

    public static function rocket_paths($paths) {
        $paths = (array) $paths;
        foreach (self::urls() as $url) {
            $path = wp_parse_url($url, PHP_URL_PATH);
            // Query based permalinks are handled by the request-time guard.
            if ($path && !wp_parse_url($url, PHP_URL_QUERY)) $paths[] = preg_quote($path, '#');
        }
        return array_values(array_unique((array) $paths));
    }

    public static function relative_paths($paths) {
        $paths = (array)$paths;
        $home = untrailingslashit(home_url());
        foreach (self::urls() as $url) {
            if (strpos($url, $home . '/') === 0) $paths[] = substr($url, strlen($home));
        }
        return array_values(array_unique($paths));
    }

    public static function exclude_handles($handles) {
        global $wp_scripts;
        $handles = (array)$handles;
        if (!$wp_scripts instanceof \WP_Scripts) return $handles;
        $critical = array();
        foreach ($wp_scripts->registered as $handle => $script) {
            if (is_string($script->src) && strpos($script->src, EMSFB_PLUGIN_URL) === 0) $critical[] = $handle;
        }
        if (self::$dynamic || wp_script_is('efb-main-js','enqueued')) $critical = array_merge($critical, array('jquery','jquery-core','jquery-migrate','stripe-js'));
        $seen = array();
        while ($critical) {
            $handle = array_pop($critical);
            if (isset($seen[$handle])) continue;
            $seen[$handle] = true;
            if (isset($wp_scripts->registered[$handle])) $critical = array_merge($critical, $wp_scripts->registered[$handle]->deps);
        }
        return array_values(array_unique(array_merge($handles, array_keys($seen))));
    }

    public static function concat($allowed, $handle) {
        return in_array($handle, self::exclude_handles(array()), true) ? false : $allowed;
    }

    public static function keep_scripts($unload) {
        return array_values(array_diff((array)$unload, self::exclude_handles(array())));
    }

    public static function script_tag($tag, $handle, $src) {
        if (!in_array($handle, self::exclude_handles(array()), true)) return $tag;
        if (!class_exists('WP_HTML_Tag_Processor')) {
            $attributes = '';
            foreach (array('data-no-optimize'=>'1','data-no-defer'=>'1','data-wpacu-skip'=>'1','data-cfasync'=>'false') as $name=>$value) {
                if (!preg_match('/\b' . $name . '\s*=/i', $tag)) $attributes .= ' ' . $name . '="' . $value . '"';
            }
            return preg_replace('/<script\b/i', '<script' . $attributes, $tag, 1);
        }
        $processor = new \WP_HTML_Tag_Processor($tag);
        if ($processor->next_tag('script')) {
            foreach (array('data-no-optimize','data-no-defer','data-wpacu-skip') as $attribute) $processor->set_attribute($attribute, '1');
            $processor->set_attribute('data-cfasync','false');
        }
        return $processor->get_updated_html();
    }

    public static function asset_src($src,$handle) {
        $files=array('stripe_js'=>'public/assets/js/stripe_pay-efb.js','Emsfb-list_form-efb-js'=>'includes/admin/assets/js/list_form-efb.js','efb-request-health'=>'public/assets/js/request-health-efb.js');
        if (!isset($files[$handle]) || !is_string($src) || strpos($src,EMSFB_PLUGIN_URL)!==0) return $src;
        $path=EMSFB_PLUGIN_DIRECTORY.$files[$handle];
        return is_readable($path) ? add_query_arg('ver',(string)filemtime($path),$src) : $src;
    }

    public static function litespeed_paths($paths) {
        $paths = is_array($paths) ? $paths : array_filter(explode("\n", (string) $paths));
        foreach (self::urls() as $url) {
            $uri = wp_parse_url($url, PHP_URL_PATH);
            $query = wp_parse_url($url, PHP_URL_QUERY);
            if ($query) $uri .= '?' . $query;
            if ($uri) $paths[] = '^' . $uri . '$';
        }
        return array_values(array_unique($paths));
    }

    private static function purge($id, $urls) {
        self::invoke('do_action', array('litespeed_purge_post', $id));
        foreach (array('wpfc_clear_post_cache_by_id','wp_cache_post_change','rocket_clean_post','w3tc_flush_post') as $callback) {
            if (function_exists($callback)) self::invoke($callback,array($id));
        }
        self::invoke('do_action', array('purge_post_cache', $id)); // Breeze public purge hook.
        foreach (array_unique($urls) as $url) {
            self::invoke('do_action',array('litespeed_purge_url',$url));
            self::invoke('do_action',array('cache_enabler_clear_page_cache_by_url',$url));
            foreach (array(array('WPO_Page_Cache','delete_cache_by_url'),array('SpeedyCache\\Delete','url')) as $callback) {
                if (is_callable($callback)) self::invoke($callback,array($url));
            }
            if (function_exists('rocket_clean_files')) self::invoke('rocket_clean_files',array(array($url)));
            if (is_callable(array('SiteGround_Optimizer\\Supercacher\\Supercacher','purge_cache_request'))) self::invoke(array('SiteGround_Optimizer\\Supercacher\\Supercacher','purge_cache_request'),array($url,false));
            $boost='Automattic\\Jetpack_Boost\\Modules\\Optimizations\\Page_Cache\\Pre_WordPress\\Boost_Cache';
            if (class_exists($boost,false)) {
                self::invoke(static function()use($boost,$url){if(!$boost::is_loaded())return;$cache=new $boost();$cache->delete_page($url);});
            }
        }
    }

    private static function refresh_config() {
        if (function_exists('rocket_generate_config_file')) self::invoke('rocket_generate_config_file');
        if (is_callable(array('Breeze_ConfigCache','write_config_cache'))) self::invoke(array('Breeze_ConfigCache','write_config_cache'));
    }

    private static function invoke($callback,$args=array()) {
        try {
            $result=call_user_func_array($callback,$args);
            if (!is_wp_error($result)) return;
        } catch (\Throwable $error) {
            // A cache adapter failure must not break publication or form rendering.
        }
        if (class_exists('Emsfb\\Request_Health')) Request_Health::record('cache',500,'server','cache_adapter_failed');
    }

    public static function rescan() {
        self::$ids = array();
        delete_option(self::SCAN);
        if (!wp_next_scheduled(self::SCAN)) wp_schedule_single_event(time() + 5, self::SCAN);
    }

    /** Bounded migration for previously published pages; no full-site purge. */
    public static function scan() {
        $cursor = get_option(self::SCAN, 0);
        if ($cursor === 'done') return;
        global $wpdb;
        $ids = $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE ID > %d AND post_status = 'publish' ORDER BY ID LIMIT 50", (int) $cursor));
        foreach ($ids as $id) {
            $post = get_post($id);
            if ($post && is_post_type_viewable($post->post_type)) self::saved((int) $id, $post);
        }
        update_option(self::SCAN, count($ids) < 50 ? 'done' : (int) end($ids), false);
        if (count($ids) === 50 && !wp_next_scheduled(self::SCAN)) wp_schedule_single_event(time() + 10, self::SCAN);
    }
}
