<?php
if (!defined('ABSPATH')) { exit; }

class AO_Dom {
    public static function init(){
        add_action('wp_head', [__CLASS__, 'print_head'], 1);
    }

    public static function print_head(){
        if (is_admin() || AO_Util::is_logged_in_skip()) return;
        $o = AO_Settings::get();
        if (!empty($o['speculation_rules'])){
            $origin = esc_js(home_url('/'));
            echo '<script type="speculationrules">';
            echo json_encode([
                "prefetch"=>[["source"=>"document","eagerness"=>"conservative","where"=>["href_matches"=>[$origin."*"]]]],
                "prerender"=>[["source"=>"document","eagerness"=>"moderate","where"=>["href_matches"=>[$origin."*"]]]]
            ]);
            echo '</script>';
        }
        if (!empty($o['lcp_preload_url'])){
            $href = esc_url($o['lcp_preload_url']);
            echo '<link rel="preload" as="image" href="'.$href.'" />';
            if (!empty($o['lcp_fetchpriority'])){
                echo "<script>document.addEventListener('DOMContentLoaded',function(){var img=document.querySelector('img[src=\"".$href."\"]'); if(img) img.setAttribute('fetchpriority','high');});</script>";
            }
        }
    }
}
