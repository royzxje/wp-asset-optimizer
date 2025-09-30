<?php
if (!defined('ABSPATH')) { exit; }

class AO_Fonts {
    public static function init(){
        add_filter('style_loader_src', [__CLASS__, 'intercept_google_fonts'], 10, 2);
    }

    public static function intercept_google_fonts($src, $handle){
        $o = AO_Settings::get();
        if (is_admin() || AO_Util::is_logged_in_skip() || empty($o['fonts_localize'])) return $src;
        if (stripos($src, 'fonts.googleapis.com') === false) return $src;

        $subset_legacy = implode(',', array_filter(array_map('trim', explode(',', $o['fonts_subset']))));
        if ($subset_legacy){
            $src = add_query_arg('subset', rawurlencode($subset_legacy), $src);
        }

        $css = self::fetch($src);
        if (!$css){
            update_option('ao_fonts_last_error', 'Không tải được CSS Google Fonts (giữ nguyên nguồn gốc).');
            return $src;
        }

        $dir = ao_opt_upload_dir();
        $font_dir = trailingslashit($dir['dir']) . 'fonts';
        $font_url = trailingslashit($dir['url']) . 'fonts';
        if (!file_exists($font_dir)) wp_mkdir_p($font_dir);

        $had_error = false;
        $local_css = preg_replace_callback('#url\\((https:[^)]+)\\)#', function($m) use ($font_dir, $font_url, &$had_error){
            $u = trim($m[1], "'\" ");
            $filename = wp_hash($u) . '-' . basename(parse_url($u, PHP_URL_PATH));
            $target = trailingslashit($font_dir) . $filename;
            if (!file_exists($target)){
                $res = wp_remote_get($u, ['timeout'=>10]);
                if (is_wp_error($res) || wp_remote_retrieve_response_code($res) !== 200){
                    $had_error = true;
                    return $m[0];
                }
                $ok = @file_put_contents($target, wp_remote_retrieve_body($res));
                if ($ok === false){ $had_error = true; return $m[0]; }
            }
            return "url('{$font_url}/{$filename}')";
        }, $css);

        $local_css = preg_replace_callback('#@font-face\\s*\\{[^}]*\\}#i', function($m){
            $block = $m[0];
            if (stripos($block, 'font-display') === false){
                $block = rtrim($block, '}') . 'font-display:swap;}';
            }
            return $block;
        }, $local_css);

        $lang = $o['fonts_lang'] ?? 'vi';
        $custom = $o['fonts_unicode_custom'] ?? '';
        $local_css = self::filter_by_lang($local_css, $lang, $custom);

        if ($had_error && $local_css === $css){
            update_option('ao_fonts_last_error', 'Không thể localize đầy đủ Google Fonts — đã giữ nguyên URL gốc để đảm bảo hiển thị.');
            return $src;
        }

        $hash = substr(md5($local_css), 0, 12);
        $css_file = trailingslashit($dir['dir']) . "fonts-local-{$hash}.css";
        $ok = @file_put_contents($css_file, $local_css);
        if ($ok === false){
            update_option('ao_fonts_last_error', 'Lỗi ghi file CSS font local — giữ nguyên nguồn gốc.');
            return $src;
        }
        return trailingslashit($dir['url']) . basename($css_file);
    }

    private static function fetch($url){
        $key = 'ao_fonts_' . md5($url);
        $cached = get_transient($key);
        if ($cached) return $cached;
        $res = wp_remote_get($url, ['timeout'=>10]);
        if (is_wp_error($res) || wp_remote_retrieve_response_code($res) !== 200) return '';
        $body = wp_remote_retrieve_body($res);
        set_transient($key, $body, DAY_IN_SECONDS);
        return $body;
    }

    private static function filter_by_lang($css, $lang, $custom){
        $keepRanges = [];
        if ($lang === 'vi'){
            $keepRanges = ['U+0000-00FF','U+0102-0103','U+0110-0111','U+1EA0-1EF9','U+20AB'];
        } elseif ($lang === 'en'){
            $keepRanges = ['U+0000-00FF'];
        } elseif ($lang === 'custom'){
            $keepRanges = array_filter(array_map('trim', explode(',', $custom)));
        } else {
            return $css;
        }
        if (!$keepRanges) return $css;

        $parts = preg_split('#(?=@font-face\\s*\\{)#i', $css);
        $out = '';
        foreach ($parts as $b){
            if (stripos($b, '@font-face') === false){ $out .= $b; continue; }
            $ur = '';
            if (preg_match('#unicode-range\\s*:\\s*([^;]+);#i', $b, $m)){ $ur = strtoupper($m[1]); }
            $keep = false;
            foreach ($keepRanges as $r){
                if (stripos($ur, strtoupper($r)) !== false){ $keep = true; break; }
            }
            if ($keep) $out .= $b;
        }
        return $out ?: $css;
    }
}
