<?php
if (!defined('ABSPATH')) { exit; }

require_once __DIR__ . '/ao-util.php';

class AO_Images {
    public static function init(){
        add_filter('the_content', [__CLASS__, 'filter_content_imgs'], 12);
        add_filter('wp_get_attachment_image_attributes', [__CLASS__, 'filter_img_attrs'], 10, 3);
    }

    public static function filter_img_attrs($attr, $attachment, $size){
        $o = AO_Settings::get();
        if (AO_Util::is_logged_in_skip()) return $attr;
        if (!empty($o['images_async'])){
            if (empty($attr['decoding'])) $attr['decoding'] = 'async';
        }
        if (!empty($o['images_fetch_low_lazy'])){
            $is_lazy = false;
            if (!empty($attr['loading']) && strtolower($attr['loading']) === 'lazy') $is_lazy = true;
            if ($is_lazy && empty($attr['fetchpriority'])) $attr['fetchpriority'] = 'low';
        }
        if (!empty($o['images_fix_dimensions'])){
            if (empty($attr['width']) || empty($attr['height'])){
                $src = $attr['src'] ?? '';
                $path = AO_Util::url_to_path($src);
                if ($path && file_exists($path)){
                    $info = @getimagesize($path);
                    if (is_array($info) && !empty($info[0]) && !empty($info[1])){
                        $attr['width'] = $info[0];
                        $attr['height'] = $info[1];
                    }
                }
            }
        }
        return $attr;
    }

    public static function filter_content_imgs($html){
        if (is_admin() || AO_Util::is_logged_in_skip()) return $html;
        $o = AO_Settings::get();
        $html = preg_replace_callback('#<img\s+[^>]*>#i', function($m) use ($o){
            $tag = $m[0];
            if (!empty($o['images_async']) && stripos($tag, 'decoding=') === false){
                $tag = preg_replace('#<img#i', '<img decoding="async"', $tag, 1);
            }
            if (!empty($o['images_fetch_low_lazy'])){
                $lazy = preg_match('#\sloading=["\']lazy["\']#i', $tag);
                if ($lazy && stripos($tag, 'fetchpriority=') === false){
                    $tag = preg_replace('#<img#i', '<img fetchpriority="low"', $tag, 1);
                }
            }
            if (!empty($o['images_fix_dimensions'])){
                $has_w = preg_match('#\swidth=["\']\d+["\']#i', $tag);
                $has_h = preg_match('#\sheight=["\']\d+["\']#i', $tag);
                if (!$has_w or !$has_h){
                    if (preg_match('#\ssrc=["\']([^"\']+)["\']#i', $tag, $mm)){
                        $src = html_entity_decode($mm[1]);
                        if (AO_Util::is_local_url($src)){
                            $path = AO_Util::url_to_path($src);
                            if ($path && file_exists($path)){
                                $info = @getimagesize($path);
                                if ($info && !empty($info[0]) && !empty($info[1])){
                                    if (!$has_w) $tag = preg_replace('#<img#i', '<img width="'.$info[0].'"', $tag, 1);
                                    if (!$has_h) $tag = preg_replace('#<img#i', '<img height="'.$info[1].'"', $tag, 1);
                                }
                            }
                        }
                    }
                }
            }
            return $tag;
        }, $html);
        return $html;
    }
}
