<?php
if (!defined('ABSPATH')) { exit; }

class AO_Lazyload {
    public static function init(){
        add_filter('the_content', [__CLASS__, 'filter_content'], 20);
    }

    public static function filter_content($html){
        $o = AO_Settings::get();
        if (empty($o['lazyload_enable']) || is_admin() || AO_Util::is_logged_in_skip()) return $html;
        $html = preg_replace_callback('#<(img|iframe)([^>]*?)>#i', function($m){
            $tag = $m[1]; $attrs = $m[2];
            if (stripos($attrs, 'loading=') === false){
                $attrs .= ' loading="lazy"';
            }
            $out = "<{$tag}{$attrs}>";
            if (strtolower($tag) === 'img'){
                $out .= "<noscript>{$m[0]}</noscript>";
            }
            return $out;
        }, $html);
        return $html;
    }
}
