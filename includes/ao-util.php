<?php
if (!defined('ABSPATH')) { exit; }

class AO_Util {
    public static function is_logged_in_skip(){
        $o = AO_Settings::get();
        return (!is_admin() && !empty($o['skip_logged_in']) && is_user_logged_in());
    }

    public static function current_route(){
        if (is_admin()) return 'admin';
        if (function_exists('is_cart') && is_cart()) return 'cart';
        if (function_exists('is_checkout') && is_checkout()) return 'checkout';
        if (is_front_page() || is_home()) return 'home';
        if (is_singular()) return 'single';
        if (is_archive()) return 'archive';
        if (is_page()) return 'page';
        return 'other';
    }

    public static function is_local_url($src){
        if (!$src) return false;
        $host = parse_url($src, PHP_URL_HOST);
        $site = parse_url(home_url('/'), PHP_URL_HOST);
        return empty($host) || strtolower($host) === strtolower($site);
    }

    public static function url_to_path($url){
        $url = strtok($url, '#');
        $content_url = content_url();
        $upload = wp_upload_dir(null, false);
        $map = [
            [trailingslashit(site_url()), trailingslashit(ABSPATH)],
            [trailingslashit($content_url), trailingslashit(WP_CONTENT_DIR)],
            [trailingslashit($upload['baseurl']), trailingslashit($upload['basedir'])],
            [trailingslashit(plugins_url('/')), trailingslashit(WP_PLUGIN_DIR) . '/'],
            [trailingslashit(get_theme_file_uri('/')), trailingslashit(get_theme_file_path('/'))],
        ];
        foreach ($map as $pair){
            list($from, $to) = $pair;
            if (strpos($url, $from) === 0){
                $rel = substr($url, strlen($from));
                $path = wp_normalize_path($to . $rel);
                if (file_exists($path)) return $path;
            }
        }
        $maybe = ABSPATH . ltrim(parse_url($url, PHP_URL_PATH) ?? '', '/');
        $maybe = wp_normalize_path($maybe);
        if (file_exists($maybe)) return $maybe;
        return false;
    }

    public static function hash_contents($contents){
        return substr(md5($contents), 0, 12);
    }

    public static function safe_read($path){
        if (!$path) return '';
        $data = @file_get_contents($path);
        return $data === false ? '' : $data;
    }

    public static function css_rewrite_urls($css, $file_dir_url){
        if (!$file_dir_url) return $css;
        $base = rtrim($file_dir_url, '/') . '/';
        $css = preg_replace_callback('#url\(([^)]+)\)#i', function($m) use ($base){
            $u = trim($m[1], " \t\n\r'\"");
            if ($u === '' || preg_match('#^(data:|https?:|//|/)#i', $u)) return "url(".$m[1].")";
            $q = '';
            if (strpos($u, '?') !== false) { list($u, $q) = explode('?', $u, 2); $q = '?' . $q; }
            if (strpos($u, '#') !== false) { list($u, $h) = explode('#', $u, 2); $q .= '#' . $h; }
            $bp = wp_parse_url($base);
            $prefix = '';
            if (!empty($bp['scheme']) && !empty($bp['host'])){
                $prefix = $bp['scheme'].'://'.$bp['host'].(isset($bp['port'])?':'.$bp['port']:'');
            }
            $bpath = isset($bp['path']) ? $bp['path'] : '/';
            if (substr($bpath, -1) !== '/') $bpath .= '/';
            $raw = $bpath . $u;
            $segs = explode('/', $raw);
            $stack = [];
            foreach ($segs as $seg){
                if ($seg === '' || $seg === '.') continue;
                if ($seg === '..'){ array_pop($stack); continue; }
                $stack[] = $seg;
            }
            $norm = '/' . implode('/', $stack);
            $resolved = $prefix . $norm . $q;
            return "url('{$resolved}')";
        }, $css);
        return $css;
    }}
