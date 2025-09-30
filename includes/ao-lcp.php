<?php
if (!defined('ABSPATH')) { exit; }

class AO_LCP {
    private static $auto_url = '';

    public static function init(){
        add_action('wp', [__CLASS__, 'detect_auto'], 1);
        add_action('wp_head', [__CLASS__, 'print_preload'], 2);
    }

    public static function detect_auto(){
        if (is_admin() || AO_Util::is_logged_in_skip()) return;
        $o = AO_Settings::get();
        if (empty($o['lcp_auto'])) return;
        $routes = array_map('trim', explode(',', $o['lcp_auto_routes'] ?? 'single,page'));
        $route = AO_Util::current_route();
        if (!in_array($route, $routes, true)) return;

        if (is_singular()){
            $post_id = get_queried_object_id();
            if ($post_id && has_post_thumbnail($post_id)){
                $url = get_the_post_thumbnail_url($post_id, 'full');
                if ($url) self::$auto_url = $url;
            }
        }
    }

    public static function candidate_url(){
        $o = AO_Settings::get();
        if (!empty($o['lcp_preload_url'])) return $o['lcp_preload_url'];
        return self::$auto_url;
    }

    public static function print_preload(){
        if (is_admin() || AO_Util::is_logged_in_skip()) return;
        $url = self::candidate_url();
        if (!$url) return;
        $href = esc_url($url);
        echo '<link rel="preload" as="image" href="'.$href.'" />' . "\n";

        $o = AO_Settings::get();
        if (!empty($o['lcp_fetchpriority'])){
            $js = "document.addEventListener('DOMContentLoaded',function(){var i=document.querySelector('img[src=\"".$href."\"]'); if(i) i.setAttribute('fetchpriority','high');});";
            echo "<script>{$js}</script>\n";
        }
    }
}
