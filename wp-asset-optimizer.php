<?php
/**
 * Plugin Name: AO – Asset Optimizer
 * Description: Gộp & tối ưu CSS/JS theo route/vị trí + exclude, skip external/CDN, hash cache-bust, resource hints, lazyload, Google Fonts localize, critical CSS, sprite SVG, LCP preload.
 * Version: 0.3.3.7
 * Author: ntquan
 * License: GPL-2.0+
 * Text Domain: ao-optimizer
 */
if (!defined('ABSPATH')) { exit; }

define('AO_OPT_VERSION', '0.3.3.7');
define('AO_OPT_SLUG', 'ao-optimizer');
define('AO_OPT_DIR', plugin_dir_path(__FILE__));
define('AO_OPT_URL', plugin_dir_url(__FILE__));

function ao_opt_upload_dir() {
    $upload = wp_upload_dir(null, false);
    $dir = trailingslashit($upload['basedir']) . 'ao-optimizer';
    $url = trailingslashit($upload['baseurl']) . 'ao-optimizer';
    if (!file_exists($dir)) {
        wp_mkdir_p($dir);
    }
    if (file_exists($dir)) {
        AO_Cleaner::ensure_cache_rules($dir);
    }
    return ['dir' => $dir, 'url' => $url];
}

// Autoloader
spl_autoload_register(function($class){
    if (strpos($class, 'AO_') === 0) {
        $file = AO_OPT_DIR . 'includes/' . strtolower(str_replace('_', '-', $class)) . '.php';
        if (file_exists($file)) require_once $file;
    }
});

add_action('plugins_loaded', function(){
    AO_Settings::init();
    AO_Combiner::init();
    AO_Capture::init();
    AO_Lazyload::init();
    AO_Hints::init();
    AO_Fonts::init();
    AO_SVG::init();
    AO_Dom::init();
    AO_Images::init();
    AO_LCP::init();
    AO_Cleaner::init();
    AO_Debug::init();

    if (is_admin()) {
        add_action('admin_bar_menu', function($bar){
            if (!current_user_can('manage_options')) return;
            $bar->add_menu([
                'id' => 'ao-purge',
                'parent' => 'top-secondary',
                'title' => 'AO: Purge Cache',
                'href' => wp_nonce_url(admin_url('admin.php?page=ao-optimizer&ao_purge=1'), 'ao_purge'),
            ]);
        }, 100);
        add_action('admin_init', function(){
            if (isset($_GET['page']) && $_GET['page'] === 'ao-optimizer' && isset($_GET['ao_purge'])) {
                check_admin_referer('ao_purge');
                AO_Cleaner::purge_all();
                add_settings_error('ao-optimizer', 'purged', __('Đã xóa cache AO.', 'ao-optimizer'), 'updated');
            }
        });
    }
});
