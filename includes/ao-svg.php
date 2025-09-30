<?php
if (!defined('ABSPATH')) { exit; }

class AO_SVG {
    public static function init(){
        add_action('admin_init', [__CLASS__, 'maybe_rebuild']);
        add_action('wp_footer', [__CLASS__, 'print_inline_sprite'], 0);
    }

    public static function icon_dir(){
        $o = AO_Settings::get();
        $up = ao_opt_upload_dir();
        $rel = trim($o['svg_icon_dir'] ?? 'ao-optimizer/svg-icons', '/');
        $dir = trailingslashit($up['dir']) . $rel;
        $url = trailingslashit($up['url']) . $rel;
        if (!file_exists($dir)) wp_mkdir_p($dir);
        return ['dir'=>$dir,'url'=>$url];
    }

    public static function sprite_path(){
        $up = ao_opt_upload_dir();
        return trailingslashit($up['dir']) . 'sprite.svg';
    }

    public static function maybe_rebuild(){
        if (!current_user_can('manage_options')) return;
        if (isset($_GET['page']) && $_GET['page']==='ao-optimizer' && isset($_GET['ao_sprite_rebuild'])){
            check_admin_referer('ao_sprite');
            self::build_sprite();
            add_settings_error('ao-optimizer', 'sprite', __('Đã rebuild SVG sprite.', 'ao-optimizer'), 'updated');
        }
        add_action('admin_notices', function(){
            if (!isset($_GET['page']) || $_GET['page']!=='ao-optimizer') return;
            $url = wp_nonce_url(admin_url('options-general.php?page=ao-optimizer&ao_sprite_rebuild=1'), 'ao_sprite');
            echo '<div class="notice"><p>SVG Sprite: <a href="'.$url.'">Rebuild</a> (đặt SVG vào uploads/'.esc_html(AO_Settings::get()['svg_icon_dir']).')</p></div>';
        });
    }

    public static function build_sprite(){
        $o = self::icon_dir();
        $files = glob(trailingslashit($o['dir']) . '*.svg');
        $sprite = "<svg xmlns='http://www.w3.org/2000/svg' style='display:none'>\n";
        foreach ($files as $f){
            $id = sanitize_title(basename($f, '.svg'));
            $raw = @file_get_contents($f);
            if (!$raw) continue;
            $inner = preg_replace('#<\?xml[^>]*\?>#', '', $raw);
            $inner = preg_replace('#<!DOCTYPE[^>]*>#', '', $inner);
            $inner = preg_replace('#<svg[^>]*>|</svg>#i', '', $inner);
            $sprite .= "<symbol id='{$id}'>{$inner}</symbol>\n";
        }
        $sprite .= "</svg>";
        file_put_contents(self::sprite_path(), $sprite);
    }

    public static function print_inline_sprite(){
        $o = AO_Settings::get();
        if (is_admin() || AO_Util::is_logged_in_skip() || empty($o['svg_sprite_enable']) || empty($o['svg_inline_footer'])) return;
        $path = self::sprite_path();
        if (file_exists($path)){
            echo "\n<!-- AO SVG Sprite -->\n";
            echo file_get_contents($path);
        }
    }

    public static function icon($name, $attrs=[]) {
        $up = ao_opt_upload_dir();
        $href = trailingslashit($up['url']) . 'sprite.svg#' . sanitize_title($name);
        $attr = '';
        foreach ($attrs as $k=>$v){ $attr .= ' '.esc_attr($k).'="'.esc_attr($v).'"'; }
        return '<svg'.$attr.'><use href="'.$href.'"></use></svg>';
    }
}
