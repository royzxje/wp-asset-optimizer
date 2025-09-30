<?php
if (!defined('ABSPATH')) { exit; }

require_once __DIR__ . '/ao-util.php';

class AO_Combiner {
    public static $built_urls = [];

    public static function init(){
        add_action('wp', [__CLASS__, 'maybe_combine_assets'], 1);
        add_action('wp_head', [__CLASS__, 'print_critical_css'], 0);
        add_filter('script_loader_tag', [__CLASS__, 'filter_script_tag'], 10, 3);
    }

    public static function is_route_enabled(){
        $opts = AO_Settings::get();
        $route = AO_Util::current_route();
        return !empty($opts['routes'][$route]);
    }

    public static function get_excludes() {
        $o = AO_Settings::get();
        $handles = array_filter(array_map('trim', explode(',', $o['exclude_handles'])));
        return array_map('strtolower', $handles);
    }

    public static function domain_patterns() {
        $o = AO_Settings::get();
        $domains = array_filter(array_map('trim', explode(',', $o['exclude_domains'])));
        return $domains;
    }

    public static function should_skip_src($src){
        if (!$src) return true;
        $o = AO_Settings::get();
        if (!empty($o['auto_skip_external']) && !AO_Util::is_local_url($src)) return true;
        $host = parse_url($src, PHP_URL_HOST);
        foreach (self::domain_patterns() as $pat){
            if ($pat && $host && stripos($host, $pat) !== false) return true;
        }
        return false;
    }

    public static function maybe_combine_assets(){
        if (is_admin() || AO_Util::is_logged_in_skip()) return;
        if (!self::is_route_enabled()) return;

        $opts = AO_Settings::get();
        $route = AO_Util::current_route();
        $cartCheckout = in_array($route, ['cart','checkout'], true);
        if ($cartCheckout && !empty($opts['cart_checkout_safe'])){
            $opts['single_js_file'] = 0;
            $opts['combine_mode'] = 'position';
            if (!empty($opts['cart_checkout_excludes'])){
                $extra = array_map('trim', explode(',', $opts['cart_checkout_excludes']));
                $cur = array_map('trim', explode(',', $opts['exclude_handles']));
                $opts['exclude_handles'] = implode(',', array_unique(array_merge($cur, $extra)));
                update_option(AO_Settings::KEY, array_merge(AO_Settings::get(), ['exclude_handles'=>$opts['exclude_handles']]));
            }
        }

        if (!empty($opts['enable_css'])) self::combine_styles();
        if (!empty($opts['enable_js'])){
            $mode = $opts['combine_mode'] ?? (!empty($opts['single_js_file']) ? 'single' : 'position');
            self::combine_scripts($mode === 'single', $mode);
        }
    }

    private static function combine_styles(){
        global $wp_styles;
        if (!isset($wp_styles)) return;
        $opts = AO_Settings::get();
        $excludes = self::get_excludes();
        $inline_limit = max(0, intval($opts['inline_small_kb'])) * 1024;
        $group_by_media = !empty($opts['css_group_media']);

        $queue = (array) $wp_styles->queue;
        $buckets = []; // media => [items]
        $inlined = '';

        foreach ($queue as $handle){
            if (in_array(strtolower($handle), $excludes, true)) continue;
            $obj = isset($wp_styles->registered[$handle]) ? $wp_styles->registered[$handle] : null;
            if (!$obj) continue;
            $media = isset($obj->args) && $obj->args ? strtolower($obj->args) : 'all';
            $src = $obj->src;
            if (self::should_skip_src($src)) continue;
            $src_full = wp_make_link_relative($src) ? site_url($src) : $src;
            $path = AO_Util::url_to_path($src_full);
            if (!$path) continue;
            $size = @filesize($path);
            if ($inline_limit > 0 && $size > 0 && $size <= $inline_limit){
                $inlined .= "\n/* {$handle} */\n" . AO_Util::safe_read($path);
                wp_dequeue_style($handle);
            } else {
                $key = $group_by_media ? $media : 'all';
                if (!isset($buckets[$key])) $buckets[$key]=[];
                $buckets[$key][] = ['handle'=>$handle, 'src'=>$src_full, 'path'=>$path, 'media'=>$media];
            }
        }

        if ($inlined){
            add_action('wp_head', function() use ($inlined){
                echo "<style id='ao-inline-css'>\n" . $inlined . "\n</style>\n";
            }, 1);
        }

        $dest = ao_opt_upload_dir();
        foreach ($buckets as $media => $list){
            $contents = '';
            foreach ($list as $it){
                $css = AO_Util::safe_read($it['path']);
                $file_dir_url = trailingslashit(dirname($it['src']));
                $css = AO_Util::css_rewrite_urls($css, $file_dir_url);
                $contents .= "\n/* {$it['handle']} */\n" . $css;
                wp_dequeue_style($it['handle']);
            }
            if (!$contents) continue;
            $hash = AO_Util::hash_contents($contents . AO_Util::current_route() . $media);
            $file = "{$dest['dir']}/combined-".AO_Util::current_route()."-{$media}-{$hash}.css";
            file_put_contents($file, $contents);
            $url = "{$dest['url']}/" . basename($file);
            self::$built_urls[] = $url;
            wp_enqueue_style('ao-combined-'.$media, $url, [], null, $media);
        }
    }

    private static function combine_scripts($single_file = false, $mode = 'position'){
        global $wp_scripts;
        if (!isset($wp_scripts)) return;

        $opts = AO_Settings::get();
        $excludes = self::get_excludes();
        $inline_limit = max(0, intval($opts['inline_small_kb'])) * 1024;

        $queue = (array) $wp_scripts->queue;
        $groups = ['header'=>[], 'footer'=>[]];
        $inlined = '';

        foreach ($queue as $handle){
            if (in_array(strtolower($handle), $excludes, true)) continue;
            $obj = isset($wp_scripts->registered[$handle]) ? $wp_scripts->registered[$handle] : null;
            if (!$obj) continue;
            $src = $obj->src;
            if (self::should_skip_src($src)) continue;
            $src_full = wp_make_link_relative($src) ? site_url($src) : $src;
            $path = AO_Util::url_to_path($src_full);
            if (!$path) continue;
            $group = (!empty($obj->extra['group']) && intval($obj->extra['group']) === 1) ? 'footer' : 'header';
            $size = @filesize($path);
            if ($inline_limit > 0 && $size > 0 && $size <= $inline_limit){
                $code = AO_Util::safe_read($path);
                $inlined .= "\n/* {$handle} */\n" . $code;
                if (preg_match('#document\.(write|writeln|open)\s*\(#i', $code)){ self::flag_docwrite($handle); }
                wp_dequeue_script($handle);
            } else {
                $code = AO_Util::safe_read($path);
                if (preg_match('#document\.(write|writeln|open)\s*\(#i', $code)){ self::flag_docwrite($handle); }
                $groups[$group][] = ['handle'=>$handle, 'src'=>$src_full, 'path'=>$path];
            }
        }

        if ($inlined){
            add_action('wp_head', function() use ($inlined){
                echo "<script id='ao-inline-js'>(function(){\n" . $inlined . "\n})();</script>\n";
            }, 1);
        }

        $dest = ao_opt_upload_dir();

        $make_file = function($list, $pos, $suffix='') use ($dest){
            if (!$list) return false;
            $contents = '';
            foreach ($list as $it){
                $code = AO_Util::safe_read($it['path']);
                $contents .= "\n/* {$it['handle']} */\n" . $code . "\n;";
                wp_dequeue_script($it['handle']);
            }
            if (!$contents) return false;
            $hash = AO_Util::hash_contents($contents . AO_Util::current_route() . $pos . $suffix);
            $file = "{$dest['dir']}/combined-".AO_Util::current_route()."-{$pos}".($suffix?'-'.sanitize_title($suffix):'')."-{$hash}.js";
            file_put_contents($file, $contents);
            return "{$dest['url']}/" . basename($file);
        };

        if ($single_file){
            $all = array_merge($groups['header'], $groups['footer']);
            $url = $make_file($all, 'all');
            if ($url){
                self::$built_urls[] = $url;
                add_action('wp_enqueue_scripts', function() use ($url){
                    wp_enqueue_script('ao-combined-all', $url, [], null, true);
                }, PHP_INT_MAX);
            }
        } else {
            $h = $make_file($groups['header'], 'head');
            $f = $make_file($groups['footer'], 'foot');
            if ($h){
                self::$built_urls[] = $h;
                add_action('wp_enqueue_scripts', function() use ($h){
                    wp_enqueue_script('ao-combined-head', $h, [], null, false);
                }, PHP_INT_MAX);
            }
            if ($f){
                self::$built_urls[] = $f;
                add_action('wp_enqueue_scripts', function() use ($f){
                    wp_enqueue_script('ao-combined-foot', $f, [], null, true);
                }, PHP_INT_MAX);
            }
        }
    }

    private static function flag_docwrite($handle){
        $sus = get_transient('ao_docwrite_suspects');
        if (!is_array($sus)) $sus = [];
        $sus[] = $handle;
        set_transient('ao_docwrite_suspects', array_slice(array_unique($sus), 0, 50), HOUR_IN_SECONDS);
    }

    public static function print_critical_css(){
        if (is_admin() || AO_Util::is_logged_in_skip()) return;
        $opts = AO_Settings::get();
        $route = AO_Util::current_route();
        $css = isset($opts['critical_css'][$route]) ? $opts['critical_css'][$route] : '';
        if ($css){
            echo "<style id='ao-critical-css'>\n" . $css . "\n</style>\n";
        }
    }

    public static function filter_script_tag($tag, $handle, $src){
        $o = AO_Settings::get();
        if (AO_Util::is_logged_in_skip()) return $tag;
        if (empty($o['thirdparty_delay'])) return $tag;
        $targets = array_map('trim', explode(',', $o['thirdparty_handles']));
        if (in_array($handle, $targets, true) || (!AO_Util::is_local_url($src) && $src)){
            $tag = preg_replace('#\s+src=([\'"][^\'"]+[\'"])#', ' data-ao-delay="1" src=$1', $tag);
            add_action('wp_footer', [__CLASS__,'print_delay_loader'], 1);
        }
        return $tag;
    }

    public static function print_delay_loader(){
        static $done=false; if ($done) return; $done=true;
        $loader = file_get_contents(AO_OPT_DIR . 'assets/js/ao-delay.js');
        echo "<script id='ao-delay-loader'>{$loader}</script>";
    }
}
