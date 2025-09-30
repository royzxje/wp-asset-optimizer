<?php
if (!defined('ABSPATH')) { exit; }

class AO_Debug {
    public static function init(){
        add_action('admin_menu', [__CLASS__, 'menu']);
    }

    public static function menu(){
        add_submenu_page('ao-optimizer', 'AO Debug', 'AO Debug', 'manage_options', 'ao-debug', [__CLASS__, 'render']);
    }

    public static function render(){
        if (!current_user_can('manage_options')) return;
        $up = ao_opt_upload_dir();
        $dir = trailingslashit($up['dir']);
        $url = trailingslashit($up['url']);

        if (isset($_GET['ao_del']) && isset($_GET['_wpnonce'])){
            if (wp_verify_nonce($_GET['_wpnonce'], 'ao_del')){
                $file = basename(sanitize_file_name($_GET['ao_del']));
                $path = $dir . $file;
                if (strpos(realpath($path), realpath($dir)) === 0 && file_exists($path)){
                    @unlink($path);
                    echo '<div class="notice notice-success"><p>Đã xóa: '.esc_html($file).'</p></div>';
                }
            }
        }
        if (isset($_GET['ao_purge_all']) && isset($_GET['_wpnonce'])){
            if (wp_verify_nonce($_GET['_wpnonce'], 'ao_purge_all')){
                AO_Cleaner::purge_all();
                echo '<div class="notice notice-success"><p>Đã xóa toàn bộ bundle.</p></div>';
            }
        }

        $files = glob($dir . '*');
        echo '<div class="wrap"><h1>AO Debug</h1>';
        echo '<p>Thư mục cache: <code>'.esc_html($dir).'</code></p>';
        $purge_all = esc_url( add_query_arg(['ao_purge_all'=>1, '_wpnonce'=>wp_create_nonce('ao_purge_all')]) );
        echo '<p><a href="'.$purge_all.'" class="button button-secondary">Purge All</a> ';
        echo '<a href="'.esc_url(admin_url('admin.php?page=ao-optimizer&ao_purge=1&_wpnonce='.wp_create_nonce('ao_purge'))).'" class="button">Purge từ trang Settings</a></p>';

        $sus = get_transient('ao_docwrite_suspects');
        if (!empty($sus)){
            echo '<div class="notice notice-warning"><p><strong>Script nghi vấn document.write():</strong> '.esc_html(implode(', ', array_unique($sus))).'</p></div>';
        }

        if (!$files){
            echo '<p><em>Chưa có bundle nào.</em></p></div>';
            return;
        }
        echo '<table class="widefat striped"><thead><tr><th>File</th><th>Kích thước</th><th>Sửa đổi</th><th>Link</th><th>Thao tác</th></tr></thead><tbody>';
        foreach ($files as $f){
            if (!is_file($f)) continue;
            $name = basename($f);
            $size = size_format(@filesize($f) ?: 0);
            $time = date_i18n('Y-m-d H:i:s', @filemtime($f) ?: time());
            $href = trailingslashit($url) . $name;
            $del = esc_url( add_query_arg(['ao_del'=>$name,'_wpnonce'=>wp_create_nonce('ao_del')]) );
            echo '<tr><td><code>'.$name.'</code></td><td>'.$size.'</td><td>'.$time.'</td><td><a href="'.$href.'" target="_blank">Mở</a></td><td><a href="'.$del.'" class="button">Xóa</a></td></tr>';
        }
        echo '</tbody></table></div>';
    }
}
