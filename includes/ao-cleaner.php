<?php
if (!defined('ABSPATH')) { exit; }

class AO_Cleaner {
    public static function init(){
        add_action('init', [__CLASS__, 'maybe_schedule']);
        add_action('ao_opt_cleanup', [__CLASS__, 'cleanup_old']);
    }

    public static function maybe_schedule(){
        $o = AO_Settings::get();
        if (!empty($o['cleanup_cron']) && !wp_next_scheduled('ao_opt_cleanup')){
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'ao_opt_cleanup');
        }
        if (empty($o['cleanup_cron']) && wp_next_scheduled('ao_opt_cleanup')){
            wp_clear_scheduled_hook('ao_opt_cleanup');
        }
    }

    public static function ensure_cache_rules($dir){
        $index = trailingslashit($dir) . 'index.php';
        if (!file_exists($index)){
            @file_put_contents($index, "<?php // Silence is golden.\n");
        }
        $ht = trailingslashit($dir) . '.htaccess';
        $rules = <<<HT
<IfModule mod_expires.c>
  ExpiresActive On
  ExpiresByType text/css "access plus 1 year"
  ExpiresByType application/javascript "access plus 1 year"
</IfModule>
<IfModule mod_headers.c>
  Header set Cache-Control "public, max-age=31536000, immutable"
</IfModule>
HT;
        if (!file_exists($ht)){
            @file_put_contents($ht, $rules);
        }
    }

    public static function purge_all(){
        $dir = ao_opt_upload_dir()['dir'];
        if (!file_exists($dir)) return;
        $files = glob(trailingslashit($dir) . '*');
        foreach ($files as $f){
            if (is_file($f)) @unlink($f);
            if (is_dir($f)) self::rrmdir($f);
        }
    }

    public static function cleanup_old(){
        $dir = ao_opt_upload_dir()['dir'];
        if (!file_exists($dir)) return;
        $files = glob($dir . '/*');
        $threshold = time() - 7*DAY_IN_SECONDS;
        foreach ($files as $f){
            if (is_file($f) && filemtime($f) < $threshold) @unlink($f);
        }
    }

    private static function rrmdir($dir){
        $it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
        foreach($files as $file){
            if ($file->isDir()) rmdir($file->getRealPath());
            else unlink($file->getRealPath());
        }
        rmdir($dir);
    }
}
