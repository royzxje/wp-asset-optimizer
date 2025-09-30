<?php
if (!defined('ABSPATH')) { exit; }

class AO_Settings {
    const KEY = 'ao_opt_settings';

    public static function defaults() {
        return [
            'enable_css' => 1,
            'enable_js' => 1,
            'single_js_file' => 0,
            'combine_mode' => 'position',
            'groups_json' => "[\n  {\"name\":\"Core Header\",\"position\":\"header\",\"handles\":\"wp-embed,imagesloaded\",\"routes\":\"home,single,archive,page\"},\n  {\"name\":\"Theme Footer\",\"position\":\"footer\",\"handles\":\"slick,swiper,theme-scripts\",\"routes\":\"home,single,page\"}\n]",
            'inline_small_kb' => 2,
            'exclude_handles' => "jquery,jquery-core,jquery-migrate,recaptcha,grecaptcha,google-recaptcha,adsbygoogle,google-analytics,gtag,ga,fbq,facebook-jssdk,maps,googlemaps",
            'exclude_domains' => "google.com,googletagmanager.com,google-analytics.com,doubleclick.net,facebook.net,cdn.jsdelivr.net,unpkg.com,cloudflare.com,youtube.com,vimeo.com",
            'routes' => ['home'=>1,'single'=>1,'archive'=>1,'cart'=>1,'checkout'=>1,'page'=>1],
            'auto_skip_external' => 1,
            'lazyload_enable' => 1,
            'critical_css' => ['home'=>'','single'=>'','archive'=>'','page'=>'','cart'=>'','checkout'=>''],
            'thirdparty_delay' => 1,
            'thirdparty_handles' => "gtag,ga,adsbygoogle,fbq",
            'hints_enable' => 1,
            'fonts_localize' => 1,
            'fonts_subset' => 'latin,vietnamese',
            'fonts_lang' => 'vi',
            'fonts_unicode_custom' => 'U+0000-00FF,U+0102-0103,U+0110-0111,U+1EA0-1EF9,U+20AB',
            'cleanup_cron' => 1,
            'cart_checkout_safe' => 1,
            'cart_checkout_excludes' => 'woocommerce,woocommerce-cart,wc-cart-fragments,wc-checkout,woo-blocks,blocks-registry,stripe,paypal',
            'svg_sprite_enable' => 1,
            'svg_inline_footer' => 1,
            'svg_icon_dir' => 'ao-optimizer/svg-icons',
            'speculation_rules' => 1,
            'lcp_preload_url' => '',
            'lcp_fetchpriority' => 1,
            'lcp_auto' => 1,
            'lcp_auto_routes' => 'single,page',
            'images_async' => 1,
            'images_fetch_low_lazy' => 1,
            'images_fix_dimensions' => 1,
            'compat_capture_tags' => 1,
            'css_group_media' => 1,
            'skip_logged_in' => 1,
        ];
    }

    public static function get() {
        $opts = get_option(self::KEY, []);
        return wp_parse_args($opts, self::defaults());
    }

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_init', [__CLASS__, 'register']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'assets']);
        add_action('switch_theme', ['AO_Cleaner','purge_all']);
        add_action('upgrader_process_complete', function(){ AO_Cleaner::purge_all(); }, 10, 0);

        add_action('admin_notices', function(){
            if (!current_user_can('manage_options')) return;
            if (!isset($_GET['page']) || $_GET['page']!=='ao-optimizer') return;
            $sus = get_transient('ao_docwrite_suspects');
            if (!empty($sus) && is_array($sus)){
                echo '<div class="notice notice-warning"><p><strong>AO:</strong> Phát hiện script có <code>document.write()</code>: ';
                $list = array_map('esc_html', array_unique($sus));
                echo implode(', ', $list);
                echo '. Hãy cân nhắc thêm vào Exclude handles.</p></div>';
            }
            $err = get_option('ao_fonts_last_error');
            if ($err){
                echo '<div class="notice notice-warning"><p><strong>AO Fonts:</strong> '.$err.'</p></div>';
                delete_option('ao_fonts_last_error');
            }
        });
    }

    public static function assets($hook){
        if (strpos($hook, 'ao-optimizer') === false) return;
        wp_enqueue_style('ao-admin', AO_OPT_URL . 'assets/admin.css', [], AO_OPT_VERSION);
        wp_enqueue_script('ao-admin', AO_OPT_URL . 'assets/admin.js', [], AO_OPT_VERSION, true);
    }

    public static function menu() {
        add_menu_page('AO Optimizer', 'AO Optimizer', 'manage_options', 'ao-optimizer', [__CLASS__, 'render'], 'dashicons-performance', 58);
        add_submenu_page('ao-optimizer', 'AO Optimizer', 'Settings', 'manage_options', 'ao-optimizer', [__CLASS__, 'render']);
    }

    public static function register() {
        register_setting('ao_opt_group', self::KEY, ['sanitize_callback'=>[__CLASS__,'sanitize']]);
        add_settings_section('ao_main', __('Thiết lập chính', 'ao-optimizer'), '__return_false', 'ao-optimizer');

        add_settings_field('enable_css', 'Gộp CSS', [__CLASS__,'field_checkbox'], 'ao-optimizer', 'ao_main', ['key'=>'enable_css']);
        add_settings_field('enable_js', 'Gộp JS', [__CLASS__,'field_checkbox'], 'ao-optimizer', 'ao_main', ['key'=>'enable_js']);

        add_settings_field('combine_mode', 'Chế độ gộp JS', [__CLASS__,'field_select'], 'ao-optimizer', 'ao_main', ['key'=>'combine_mode','options'=>['position'=>'Theo vị trí (an toàn)','single'=>'1 file duy nhất','groups'=>'Theo nhóm (tùy route)']]);
        add_settings_field('single_js_file', 'JS 1 file duy nhất (legacy)', [__CLASS__,'field_checkbox'], 'ao-optimizer', 'ao_main', ['key'=>'single_js_file']);
        add_settings_field('groups_json', 'Nhóm gộp theo route (JSON)', [__CLASS__,'field_textarea_large'], 'ao-optimizer', 'ao_main', ['key'=>'groups_json']);

        add_settings_field('inline_small_kb', 'Inline nhỏ (&lt; KB)', [__CLASS__,'field_number'], 'ao-optimizer', 'ao_main', ['key'=>'inline_small_kb','min'=>0,'max'=>64]);
        add_settings_field('exclude_handles', 'Exclude handles (CSV)', [__CLASS__,'field_textarea'], 'ao-optimizer', 'ao_main', ['key'=>'exclude_handles']);
        add_settings_field('exclude_domains', 'Exclude domain patterns (CSV)', [__CLASS__,'field_textarea'], 'ao-optimizer', 'ao_main', ['key'=>'exclude_domains']);
        add_settings_field('routes', 'Bật theo route', [__CLASS__,'field_routes'], 'ao-optimizer', 'ao_main', ['key'=>'routes']);

        add_settings_field('cart_checkout_safe', 'Chế độ an toàn Cart/Checkout', [__CLASS__,'field_checkbox'], 'ao-optimizer', 'ao_main', ['key'=>'cart_checkout_safe']);
        add_settings_field('cart_checkout_excludes', 'Exclude thêm cho Cart/Checkout', [__CLASS__,'field_textarea'], 'ao-optimizer', 'ao_main', ['key'=>'cart_checkout_excludes']);

        add_settings_field('lazyload_enable', 'Image/Iframe lazyload', [__CLASS__,'field_checkbox'], 'ao-optimizer', 'ao_main', ['key'=>'lazyload_enable']);
        add_settings_field('critical_css', 'Critical CSS per-route', [__CLASS__,'field_critical'], 'ao-optimizer', 'ao_main', ['key'=>'critical_css']);

        add_settings_field('thirdparty_delay', 'Delay third-party (GA/Ads...)', [__CLASS__,'field_checkbox'], 'ao-optimizer', 'ao_main', ['key'=>'thirdparty_delay']);
        add_settings_field('thirdparty_handles', 'Handles third-party (CSV)', [__CLASS__,'field_textarea'], 'ao-optimizer', 'ao_main', ['key'=>'thirdparty_handles']);

        add_settings_field('hints_enable', 'HTTP resource hints', [__CLASS__,'field_checkbox'], 'ao-optimizer', 'ao_main', ['key'=>'hints_enable']);

        add_settings_field('fonts_localize', 'Localize Google Fonts', [__CLASS__,'field_checkbox'], 'ao-optimizer', 'ao_main', ['key'=>'fonts_localize']);
        add_settings_field('fonts_lang', 'Font subset nâng cao', [__CLASS__,'field_select'], 'ao-optimizer', 'ao_main', ['key'=>'fonts_lang','options'=>['vi'=>'Vietnamese + Latin','en'=>'Latin only','all'=>'Giữ toàn bộ','custom'=>'Tùy biến unicode-range']]);
        add_settings_field('fonts_unicode_custom', 'Unicode-range (custom)', [__CLASS__,'field_text'], 'ao-optimizer', 'ao_main', ['key'=>'fonts_unicode_custom']);

        add_settings_field('svg_sprite_enable', 'SVG Sprite + Inline icons', [__CLASS__,'field_checkbox'], 'ao-optimizer', 'ao_main', ['key'=>'svg_sprite_enable']);
        add_settings_field('svg_inline_footer', 'Inline sprite vào footer', [__CLASS__,'field_checkbox'], 'ao-optimizer', 'ao_main', ['key'=>'svg_inline_footer']);
        add_settings_field('svg_icon_dir', 'Thư mục icon tương đối (uploads)', [__CLASS__,'field_text'], 'ao-optimizer', 'ao_main', ['key'=>'svg_icon_dir']);

        add_settings_field('speculation_rules', 'Speculation Rules (prerender/prefetch) nội bộ', [__CLASS__,'field_checkbox'], 'ao-optimizer', 'ao_main', ['key'=>'speculation_rules']);
        add_settings_field('lcp_preload_url', 'Preload ảnh LCP (URL tuyệt đối)', [__CLASS__,'field_text'], 'ao-optimizer', 'ao_main', ['key'=>'lcp_preload_url']);
        add_settings_field('lcp_fetchpriority', 'Đặt fetchpriority=high cho ảnh LCP', [__CLASS__,'field_checkbox'], 'ao-optimizer', 'ao_main', ['key'=>'lcp_fetchpriority']);
        add_settings_field('lcp_auto', 'Tự phát hiện ảnh LCP (singular)', [__CLASS__,'field_checkbox'], 'ao-optimizer', 'ao_main', ['key'=>'lcp_auto']);
        add_settings_field('lcp_auto_routes', 'Routes áp dụng auto LCP (CSV)', [__CLASS__,'field_text'], 'ao-optimizer', 'ao_main', ['key'=>'lcp_auto_routes']);

        add_settings_field('images_fix_dimensions', 'Tự bổ sung width/height cho ảnh local', [__CLASS__,'field_checkbox'], 'ao-optimizer', 'ao_main', ['key'=>'images_fix_dimensions']);
        add_settings_field('images_async', 'Thêm decoding=\"async\" cho ảnh', [__CLASS__,'field_checkbox'], 'ao-optimizer', 'ao_main', ['key'=>'images_async']);
        add_settings_field('images_fetch_low_lazy', 'Đặt fetchpriority=\"low\" cho ảnh lazy', [__CLASS__,'field_checkbox'], 'ao-optimizer', 'ao_main', ['key'=>'images_fetch_low_lazy']);

        add_settings_field('compat_capture_tags', 'Compat mode: Bắt & gộp &lt;link&gt;/&lt;script&gt; in thẳng', [__CLASS__,'field_checkbox'], 'ao-optimizer', 'ao_main', ['key'=>'compat_capture_tags']);
        add_settings_field('css_group_media', 'Gộp CSS theo media (kể cả media != all)', [__CLASS__,'field_checkbox'], 'ao-optimizer', 'ao_main', ['key'=>'css_group_media']);

        add_settings_field('cleanup_cron', 'Cron dọn cache cũ', [__CLASS__,'field_checkbox'], 'ao-optimizer', 'ao_main', ['key'=>'cleanup_cron']);
        add_settings_field('skip_logged_in', 'Bỏ qua tối ưu cho người dùng đã đăng nhập', [__CLASS__,'field_checkbox'], 'ao-optimizer', 'ao_main', ['key'=>'skip_logged_in']);
    }

    public static function render() {
        if (!current_user_can('manage_options')) return;
        $up = ao_opt_upload_dir();
        $dir = trailingslashit($up['dir']);
        $files = glob($dir . '*.{css,js}', GLOB_BRACE);
        $count = $files ? count($files) : 0;
        $size = 0;
        if ($files){ foreach ($files as $f){ $size += @filesize($f) ?: 0; } }
        $kb = $size > 0 ? round($size/1024) : 0;

        $R = function($label, $cb, $args){
            ob_start(); call_user_func([__CLASS__, $cb], $args); $html = ob_get_clean();
            echo '<div class="ao-field" data-ao-field="'.esc_attr($label).'"><div class="ao-label">'.esc_html($label).'</div><div class="ao-control">'.$html.'</div></div>';
        };

        $purge_url = wp_nonce_url(admin_url('admin.php?page=ao-optimizer&ao_purge=1'), 'ao_purge');
        ?>
        <div class="wrap ao-wrap">
          <div class="ao-header">
            <div class="ao-title"><h1>AO – Asset Optimizer</h1></div>
            <div class="ao-actions">
              <a class="button button-secondary" href="<?php echo esc_url($purge_url); ?>">Purge Cache</a>
              <div class="ao-search"><input type="search" id="ao-search-input" class="regular-text" placeholder="Tìm kiếm cài đặt..."></div>
            </div>
          </div>

          <div class="ao-tiles">
            <div class="ao-tile"><div class="ao-k"><?php echo esc_html($count); ?></div><div>Bundle hiện có</div></div>
            <div class="ao-tile"><div class="ao-k"><?php echo esc_html($kb); ?> KB</div><div>Tổng dung lượng bundle</div></div>
            <div class="ao-tile"><div class="ao-k"><?php echo esc_html(strtoupper(is_admin() ? 'admin' : AO_Util::current_route())); ?></div><div>Route hiện tại</div></div>
          </div>

          <div class="ao-tabs">
            <div class="ao-tab is-active" data-tab="overview">Tổng quan</div>
            <div class="ao-tab" data-tab="combine">Combine</div>
            <div class="ao-tab" data-tab="images">Images</div>
            <div class="ao-tab" data-tab="fonts">Fonts</div>
            <div class="ao-tab" data-tab="network">Network/Hints</div>
            <div class="ao-tab" data-tab="advanced">Nâng cao</div>
          </div>

          <form method="post" action="options.php">
            <?php settings_fields('ao_opt_group'); ?>

            <div data-ao-tab="overview" class="is-active">
              <div class="ao-grid"><div class="ao-card"><div class="ao-card-hd"><h3>Thông tin & Mẹo</h3></div><div class="ao-card-bd">
                <p>Nhánh 0.3.x ưu tiên ổn định; thêm compat mode để gộp asset in thẳng từ theme/plugin.</p>
                <p>Trang <a href="<?php echo esc_url(admin_url('options-general.php?page=ao-debug')); ?>">AO Debug</a> xem/xóa bundle, kiểm tra nhanh.</p>
              </div></div></div>
            </div>

            <div data-ao-tab="combine">
              <div class="ao-grid"><div class="ao-card"><div class="ao-card-hd"><h3>CSS & JS Combine</h3></div><div class="ao-card-bd">
                <?php
                $R('Gộp CSS', 'field_checkbox', ['key'=>'enable_css']);
                $R('Gộp JS', 'field_checkbox', ['key'=>'enable_js']);
                $R('Chế độ gộp JS', 'field_select', ['key'=>'combine_mode','options'=>['position'=>'Theo vị trí (an toàn)','single'=>'1 file duy nhất','groups'=>'Theo nhóm (tùy route)']]);
                $R('JS 1 file duy nhất (legacy)', 'field_checkbox', ['key'=>'single_js_file']);
                $R('Nhóm gộp theo route (JSON)', 'field_textarea_large', ['key'=>'groups_json']);
                $R('Inline nhỏ (< KB)', 'field_number', ['key'=>'inline_small_kb','min'=>0,'max'=>64]);
                $R('Exclude handles (CSV)', 'field_textarea', ['key'=>'exclude_handles']);
                $R('Exclude domain patterns (CSV)', 'field_textarea', ['key'=>'exclude_domains']);
                $R('Bật theo route', 'field_routes', ['key'=>'routes']);
                $R('Compat mode: Bắt & gộp <link>/<script> in thẳng', 'field_checkbox', ['key'=>'compat_capture_tags']);
                $R('Gộp CSS theo media (kể cả media != all)', 'field_checkbox', ['key'=>'css_group_media']);
                $R('Chế độ an toàn Cart/Checkout', 'field_checkbox', ['key'=>'cart_checkout_safe']);
                $R('Exclude thêm cho Cart/Checkout', 'field_textarea', ['key'=>'cart_checkout_excludes']);
                ?>
              </div></div></div>
            </div>

            <div data-ao-tab="images">
              <div class="ao-grid"><div class="ao-card"><div class="ao-card-hd"><h3>Ảnh & Lazyload</h3></div><div class="ao-card-bd">
                <?php
                $R('Tự bổ sung width/height cho ảnh local', 'field_checkbox', ['key'=>'images_fix_dimensions']);
                $R('Thêm decoding=\"async\" cho ảnh', 'field_checkbox', ['key'=>'images_async']);
                $R('Đặt fetchpriority=\"low\" cho ảnh lazy', 'field_checkbox', ['key'=>'images_fetch_low_lazy']);
                $R('Image/Iframe lazyload', 'field_checkbox', ['key'=>'lazyload_enable']);
                $R('Critical CSS per-route', 'field_critical', ['key'=>'critical_css']);
                ?>
              </div></div></div>
            </div>

            <div data-ao-tab="fonts">
              <div class="ao-grid"><div class="ao-card"><div class="ao-card-hd"><h3>Fonts</h3></div><div class="ao-card-bd">
                <?php
                $R('Localize Google Fonts', 'field_checkbox', ['key'=>'fonts_localize']);
                $R('Font subset nâng cao', 'field_select', ['key'=>'fonts_lang','options'=>['vi'=>'Vietnamese + Latin','en'=>'Latin only','all'=>'Giữ toàn bộ','custom'=>'Tùy biến unicode-range']]);
                $R('Unicode-range (custom)', 'field_text', ['key'=>'fonts_unicode_custom']);
                ?>
              </div></div></div>
            </div>

            <div data-ao-tab="network">
              <div class="ao-grid"><div class="ao-card"><div class="ao-card-hd"><h3>Network & Hints</h3></div><div class="ao-card-bd">
                <?php
                $R('HTTP resource hints', 'field_checkbox', ['key'=>'hints_enable']);
                $R('Speculation Rules (prerender/prefetch) nội bộ', 'field_checkbox', ['key'=>'speculation_rules']);
                $R('Preload ảnh LCP (URL tuyệt đối)', 'field_text', ['key'=>'lcp_preload_url']);
                $R('Đặt fetchpriority=high cho ảnh LCP', 'field_checkbox', ['key'=>'lcp_fetchpriority']);
                $R('Tự phát hiện ảnh LCP (singular)', 'field_checkbox', ['key'=>'lcp_auto']);
                $R('Routes áp dụng auto LCP (CSV)', 'field_text', ['key'=>'lcp_auto_routes']);
                ?>
              </div></div></div>
            </div>

            <div data-ao-tab="advanced">
              <div class="ao-grid"><div class="ao-card"><div class="ao-card-hd"><h3>Nâng cao</h3></div><div class="ao-card-bd">
                <?php
                $R('Delay third-party (GA/Ads...)', 'field_checkbox', ['key'=>'thirdparty_delay']);
                $R('Handles third-party (CSV)', 'field_textarea', ['key'=>'thirdparty_handles']);
                $R('SVG Sprite + Inline icons', 'field_checkbox', ['key'=>'svg_sprite_enable']);
                $R('Inline sprite vào footer', 'field_checkbox', ['key'=>'svg_inline_footer']);
                $R('Thư mục icon tương đối (uploads)', 'field_text', ['key'=>'svg_icon_dir']);
                $R('Cron dọn cache cũ', 'field_checkbox', ['key'=>'cleanup_cron']);
                $R('Bỏ qua tối ưu cho người dùng đã đăng nhập', 'field_checkbox', ['key'=>'skip_logged_in']);
                ?>
              </div></div></div>
            </div>

            <div class="ao-stickybar"><div><em>Tip:</em> Dùng thuộc tính <code>data-ao-skip</code> trên thẻ <code>&lt;link&gt;</code>/<code>&lt;script&gt;</code> nếu muốn bỏ qua trong compat mode.</div><div><?php submit_button(null, 'primary', 'submit', false); ?></div></div>
          </form>
        </div>
        <?php
    }

    /* ===== Field renderers ===== */
    public static function field_checkbox($args){
        $opts = self::get(); $key=$args['key'];
        printf('<label class="ao-switch"><input type="checkbox" name="%s[%s]" value="1" %s/></label>', self::KEY, esc_attr($key), checked(!empty($opts[$key]), true, false));
    }
    public static function field_number($args){
        $opts = self::get(); $key=$args['key']; $min = isset($args['min'])? (int)$args['min'] : 0; $max = isset($args['max'])?(int)$args['max']:100;
        printf('<input type="number" min="%d" max="%d" name="%s[%s]" value="%s" /> KB', $min, $max, self::KEY, esc_attr($key), esc_attr($opts[$key]));
    }
    public static function field_text($args){
        $opts = self::get(); $key=$args['key'];
        printf('<input type="text" style="width: 520px" name="%s[%s]" value="%s" />', self::KEY, esc_attr($key), esc_attr($opts[$key]));
    }
    public static function field_textarea($args){
        $opts = self::get(); $key=$args['key'];
        printf('<textarea name="%s[%s]" rows="3" style="width: 620px">%s</textarea>', self::KEY, esc_attr($key), esc_textarea($opts[$key]));
    }
    public static function field_textarea_large($args){
        $opts = self::get(); $key=$args['key'];
        printf('<textarea name="%s[%s]" rows="10" style="width: 760px; font-family: Menlo,Consolas,monospace">%s</textarea>', self::KEY, esc_attr($key), esc_textarea($opts[$key]));
        echo '<p class="description">JSON mảng đối tượng: {name, position: header|footer, handles: CSV, routes: CSV}</p>';
    }
    public static function field_select($args){
        $opts = self::get(); $key=$args['key']; $options = $args['options'] ?? [];
        echo '<select name="'.self::KEY.'['.esc_attr($key).']">';
        foreach ($options as $k=>$label){
            printf('<option value="%s" %s>%s</option>', esc_attr($k), selected($opts[$key], $k, false), esc_html($label));
        }
        echo '</select>';
    }
    public static function field_routes($args){
        $opts = self::get(); $key=$args['key']; $routes = ['home'=>'Home','single'=>'Single','archive'=>'Archive','page'=>'Page','cart'=>'Cart','checkout'=>'Checkout'];
        foreach ($routes as $k=>$label){
            printf('<label style="margin-right:12px"><input type="checkbox" name="%s[%s][%s]" value="1" %s/> %s</label>', self::KEY, esc_attr($key), esc_attr($k), checked(!empty($opts[$key][$k]), true, false), esc_html($label));
        }
    }
    public static function field_critical($args){
        $opts = self::get(); $key=$args['key']; $routes = ['home','single','archive','page','cart','checkout'];
        foreach ($routes as $k){
            $val = isset($opts[$key][$k]) ? $opts[$key][$k] : '';
            printf('<p><strong>%s</strong><br><textarea name="%s[%s][%s]" rows="6" style="width: 760px; font-family: Menlo,Consolas,monospace">%s</textarea></p>', esc_html(ucfirst($k)), self::KEY, esc_attr($key), esc_attr($k), esc_textarea($val));
        }
    }

    public static function sanitize($in){
        $in = is_array($in) ? $in : [];
        $old = get_option(self::KEY, []);
        $out = array_merge($old, $in);

        $checkboxes = ['enable_css','enable_js','single_js_file','lazyload_enable','thirdparty_delay','hints_enable','fonts_localize','svg_sprite_enable','svg_inline_footer','speculation_rules','lcp_fetchpriority','lcp_auto','images_async','images_fetch_low_lazy','images_fix_dimensions','compat_capture_tags','css_group_media','cleanup_cron','skip_logged_in','cart_checkout_safe','login_block_default'];
        foreach ($checkboxes as $k){
            $out[$k] = !empty($in[$k]) ? 1 : 0;
        }

        $routes_all = ['home','single','archive','page','cart','checkout'];
        if (!isset($out['routes']) || !is_array($out['routes'])) $out['routes'] = [];
        foreach ($routes_all as $r){
            $out['routes'][$r] = !empty($in['routes'][$r]) ? 1 : 0;
        }

        if (isset($in['critical_css']) && is_array($in['critical_css'])){
            $out['critical_css'] = $in['critical_css'];
        } elseif (!isset($out['critical_css'])){
            $out['critical_css'] = ['home'=>'','single'=>'','archive'=>'','page'=>'','cart'=>'','checkout'=>''];
        }

        if (isset($out['inline_small_kb'])) $out['inline_small_kb'] = max(0, intval($out['inline_small_kb']));

        return $out;
    }
    
}
