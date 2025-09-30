<?php
if (!defined('ABSPATH')) { exit; }

class AO_Hints {
    public static function init(){
        add_filter('wp_resource_hints', [__CLASS__, 'resource_hints'], 10, 2);
        add_action('send_headers', [__CLASS__, 'send_link_headers'], 9);
        add_action('send_headers', [__CLASS__, 'send_server_timing'], 10);
    }

    public static function resource_hints($hints, $relation_type){
        if (AO_Util::is_logged_in_skip()) return $hints;
        $o = AO_Settings::get();
        if (empty($o['hints_enable'])) return $hints;
        $domains = array_filter(array_map('trim', explode(',', $o['exclude_domains'])));
        for ($i=0; $i<count($domains); $i++){
            $d = $domains[$i];
            if (!$d) continue;
            $proto = 'https://www.' . $d;
            if ($relation_type === 'preconnect') $hints[] = $proto;
            if ($relation_type === 'dns-prefetch') $hints[] = '//' . $d;
        }
        return array_values(array_unique($hints));
    }

    public static function send_link_headers(){
        if (headers_sent() || AO_Util::is_logged_in_skip()) return;
        $built = AO_Combiner::$built_urls;
        if (!$built) return;
        foreach ($built as $u){
            $as = (stripos($u, '.css') !== false) ? 'style' : ((stripos($u, '.js') !== false) ? 'script' : 'fetch');
            header("Link: <{$u}>; rel=preload; as={$as}", false);
        }
    }

    public static function send_server_timing(){
        if (headers_sent() || AO_Util::is_logged_in_skip()) return;
        $upload = ao_opt_upload_dir();
        $dir = trailingslashit($upload['dir']);
        $files = glob($dir . '*.{css,js}', GLOB_BRACE);
        if (!$files) return;
        $size = 0;
        foreach ($files as $f){ $size += @filesize($f) ?: 0; }
        $kb = $size > 0 ? round($size/1024) : 0;
        header('Server-Timing: ao;desc="AO bundles";dur=' . $kb, false);
    }
}
