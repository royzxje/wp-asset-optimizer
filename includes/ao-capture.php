<?php
if (!defined('ABSPATH')) { exit; }

require_once __DIR__ . '/ao-util.php';
require_once __DIR__ . '/ao-combiner.php';

class AO_Capture {
    public static function init(){
        add_action('template_redirect', [__CLASS__, 'start'], 0);
    }

    public static function start(){
        if (is_admin() || AO_Util::is_logged_in_skip()) return;
        if (!AO_Combiner::is_route_enabled()) return;
        $o = AO_Settings::get();
        if (empty($o['compat_capture_tags'])) return;
        ob_start([__CLASS__, 'buffer']);
    }

    public static function buffer($html){
        try {
            if (stripos($html, '<html') === false) return $html;

            $head_end = stripos($html, '</head>');
            if ($head_end === false) $head_end = 0;

            // Capture LINK rel=stylesheet
            $css_groups = []; // media => list
            $remove_spans = [];

            if (preg_match_all('#<link\b[^>]*rel=["\']stylesheet["\'][^>]*>#i', $html, $m, PREG_OFFSET_CAPTURE)){
                foreach ($m[0] as $match){
                    $tag = $match[0]; $off = $match[1];
                    if (stripos($tag, 'data-ao-skip') !== false) continue;
                    if (stripos($tag, 'ao-optimizer') !== false) continue;
                    if (!preg_match('#href=["\']([^"\']+)#i', $tag, $mm)) continue;
                    $href = html_entity_decode($mm[1]);
                    if (!AO_Util::is_local_url($href)) continue;
                    if (AO_Combiner::should_skip_src($href)) continue;
                    $path = AO_Util::url_to_path($href);
                    if (!$path) continue;

                    $media = 'all';
                    if (preg_match('#\bmedia=["\']([^"\']+)#i', $tag, $mmedia)){
                        $media = strtolower(trim($mmedia[1])) ?: 'all';
                    }
                    if (!isset($css_groups[$media])) $css_groups[$media] = [];
                    $css_groups[$media][] = ['href'=>$href,'path'=>$path,'tag'=>$tag,'offset'=>$off];
                    $remove_spans[] = [$off, strlen($tag)];
                }
            }

            // Capture SCRIPT src (non-async/defer/module) and group by head/foot
            $js_groups = ['head'=>[], 'foot'=>[]];
            if (preg_match_all('#<script\b[^>]*\bsrc=["\']([^"\']+)["\'][^>]*>\s*</script>#i', $html, $mm, PREG_OFFSET_CAPTURE)){
                for ($i=0; $i<count($mm[0]); $i++){
                    $tag = $mm[0][$i][0]; $off = $mm[0][$i][1];
                    $src = html_entity_decode($mm[1][$i][0]);
                    if (stripos($tag, 'data-ao-skip') !== false) continue;
                    if (stripos($src, 'ao-optimizer') !== false) continue;
                    if (!AO_Util::is_local_url($src)) continue;
                    if (preg_match('#\b(async|defer)\b#i', $tag)) continue;
                    if (preg_match('#\btype=["\']module["\']#i', $tag)) continue;
                    if (AO_Combiner::should_skip_src($src)) continue;
                    $path = AO_Util::url_to_path($src);
                    if (!$path) continue;
                    $pos = ($off < $head_end) ? 'head' : 'foot';
                    $js_groups[$pos][] = ['src'=>$src,'path'=>$path,'tag'=>$tag,'offset'=>$off];
                    $remove_spans[] = [$off, strlen($tag)];
                }
            }

            // Build combined CSS
            $injections_head = '';
            if (!empty($css_groups)){
                $dest = ao_opt_upload_dir();
                foreach ($css_groups as $media => $list){
                    $contents = '';
                    foreach ($list as $it){
                        $css = AO_Util::safe_read($it['path']);
                        $file_dir_url = trailingslashit(dirname($it['href']));
                        $css = AO_Util::css_rewrite_urls($css, $file_dir_url);
                        $contents .= "\n/* captured {$it['href']} */\n".$css;
                    }
                    if ($contents){
                        $hash = AO_Util::hash_contents($contents . AO_Util::current_route() . $media . 'capt');
                        $file = "{$dest['dir']}/compat-".AO_Util::current_route()."-{$media}-{$hash}.css";
                        @file_put_contents($file, $contents);
                        $url = "{$dest['url']}/" . basename($file);
                        AO_Combiner::$built_urls[] = $url;
                        $injections_head .= '<link rel="stylesheet" href="'.esc_url($url).'" media="'.esc_attr($media).'">'."\n";
                    }
                }
            }

            // Build combined JS
            $injections_head_js = '';
            $injections_foot_js = '';
            if (!empty($js_groups)){
                $dest = ao_opt_upload_dir();
                foreach (['head','foot'] as $pos){
                    if (empty($js_groups[$pos])) continue;
                    $contents = '';
                    foreach ($js_groups[$pos] as $it){
                        $code = AO_Util::safe_read($it['path']);
                        $contents .= "\n/* captured {$it['src']} */\n".$code."\n;";
                    }
                    if ($contents){
                        $hash = AO_Util::hash_contents($contents . AO_Util::current_route() . $pos . 'capt');
                        $file = "{$dest['dir']}/compat-".AO_Util::current_route()."-{$pos}-{$hash}.js";
                        @file_put_contents($file, $contents);
                        $url = "{$dest['url']}/" . basename($file);
                        AO_Combiner::$built_urls[] = $url;
                        $script = '<script src="'.esc_url($url).'"'.($pos==='foot'?' defer':'').'></script>'."\n";
                        if ($pos==='head') $injections_head_js .= $script; else $injections_foot_js .= $script;
                    }
                }
            }

            // Remove originals (from end to start)
            if (!empty($remove_spans)){
                usort($remove_spans, function($a,$b){ return $b[0] <=> $a[0]; });
                foreach ($remove_spans as $sp){
                    $html = substr_replace($html, '', $sp[0], $sp[1]);
                }
            }

            // Inject head CSS/JS
            if ($injections_head || $injections_head_js){
                $p = stripos($html, '</head>');
                if ($p !== false){
                    $html = substr_replace($html, $injections_head . $injections_head_js, $p, 0);
                } else {
                    $html = $injections_head . $injections_head_js . $html;
                }
            }

            // Inject foot JS
            if ($injections_foot_js){
                $p = stripos($html, '</body>');
                if ($p !== false){
                    $html = substr_replace($html, $injections_foot_js, $p, 0);
                } else {
                    $html .= $injections_foot_js;
                }
            }

            return $html;
        } catch (\Throwable $e){
            return $html;
        }
    }
}
