<?php
/**
 * Plugin Name: Google Tags Scanner
 * Plugin URI: https://github.com/yourusername/wp-google-tags-scanner
 * Description: Precisely scan for Google tracking codes with full snippet preview - perfect for cleaning up before implementing fresh tracking campaigns.
 * Version: 2.0.0
 * Author: Vinny Deboasse 
 * Author URI: https://github.com/vinnydeboasse
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: google-tags-scanner
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('GTS_VERSION', '2.0.0');
define('GTS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GTS_PLUGIN_PATH', plugin_dir_path(__FILE__));

class PreciseGoogleTagsScanner {
    
    private $google_patterns = [
        'google_analytics_universal' => [
            'name' => 'Google Analytics (Universal)',
            'pattern' => '/<script[^>]*>\s*\(function\(i,s,o,g,r,a,m\)[^}]+\}[^<]*gtag[^<]*<\/script>/s',
            'id_pattern' => '/UA-[0-9]+-[0-9]+/'
        ],
        'google_analytics_ga4' => [
            'name' => 'Google Analytics 4 (GA4)',
            'pattern' => '/<script[^>]*googletagmanager\.com\/gtag\/js\?id=G-[A-Z0-9]+[^>]*><\/script>/i',
            'id_pattern' => '/G-[A-Z0-9]+/'
        ],
        'gtag_config' => [
            'name' => 'Google gtag() Configuration',
            'pattern' => '/<script[^>]*>.*?gtag\s*\(\s*["\']config["\'].*?<\/script>/s',
            'id_pattern' => '/(UA-[0-9]+-[0-9]+|G-[A-Z0-9]+|AW-[0-9]+)/'
        ],
        'google_tag_manager' => [
            'name' => 'Google Tag Manager',
            'pattern' => '/<!-- Google Tag Manager -->.*?<!-- End Google Tag Manager -->/s',
            'id_pattern' => '/GTM-[A-Z0-9]+/'
        ],
        'gtm_noscript' => [
            'name' => 'Google Tag Manager (noscript)',
            'pattern' => '/<noscript>.*?googletagmanager\.com\/ns\.html.*?<\/noscript>/s',
            'id_pattern' => '/GTM-[A-Z0-9]+/'
        ],
        'google_ads' => [
            'name' => 'Google Ads / AdWords',
            'pattern' => '/<script[^>]*googlesyndication\.com\/pagead\/js\/adsbygoogle\.js[^>]*><\/script>/i',
            'id_pattern' => '/(ca-pub-[0-9]+|AW-[0-9]+)/'
        ],
        'adsbygoogle' => [
            'name' => 'Google AdSense Code',
            'pattern' => '/<script[^>]*>.*?\(adsbygoogle.*?<\/script>/s',
            'id_pattern' => '/ca-pub-[0-9]+/'
        ],
        'google_optimize' => [
            'name' => 'Google Optimize',
            'pattern' => '/<script[^>]*googleoptimize\.com\/optimize\.js[^>]*><\/script>/i',
            'id_pattern' => '/GTM-[A-Z0-9]+/'
        ],
        'analytics_js' => [
            'name' => 'Legacy Google Analytics (analytics.js)',
            'pattern' => '/<script[^>]*google-analytics\.com\/analytics\.js[^>]*><\/script>/i',
            'id_pattern' => '/UA-[0-9]+-[0-9]+/'
        ]
    ];
    
    public function __construct() {
        add_action('wp_ajax_scan_google_tags', [$this, 'scan_google_tags']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('plugins_loaded', [$this, 'load_textdomain']);
    }
    
    public function load_textdomain() {
        load_plugin_textdomain('google-tags-scanner', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
    
    public function add_admin_menu() {
        add_management_page(
            __('Google Tags Scanner', 'google-tags-scanner'),
            __('Google Tags Scanner', 'google-tags-scanner'),
            'manage_options',
            'google-tags-scanner',
            [$this, 'admin_page']
        );
    }
    
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'tools_page_google-tags-scanner') {
            return;
        }
        wp_enqueue_script('jquery');
    }
    
    public function scan_google_tags() {
        // Verify user permissions and nonce
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'google-tags-scanner'));
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'google_tags_scanner_nonce')) {
            wp_die(__('Security check failed', 'google-tags-scanner'));
        }
        
        $results = [
            'posts_found' => $this->scan_posts_content(),
            'options_found' => $this->scan_wp_options(),
            'elementor_found' => $this->scan_elementor_data(),
            'theme_files_found' => $this->scan_theme_files(),
            'plugins_found' => $this->scan_plugins(),
            'total_snippets' => 0,
            'unique_ids' => [],
            'safety_score' => 0
        ];
        
        // Count total snippets and extract unique IDs
        $all_ids = [];
        foreach (['posts_found', 'options_found', 'elementor_found', 'theme_files_found'] as $section) {
            foreach ($results[$section] as $item) {
                if (isset($item['snippets'])) {
                    $results['total_snippets'] += count($item['snippets']);
                    foreach ($item['snippets'] as $snippet) {
                        if (isset($snippet['extracted_ids'])) {
                            $all_ids = array_merge($all_ids, $snippet['extracted_ids']);
                        }
                    }
                }
            }
        }
        
        $results['unique_ids'] = array_unique($all_ids);
        
        // Calculate safety score based on actual findings
        $total_locations = count($results['posts_found']) + count($results['options_found']) + 
                          count($results['elementor_found']) + count($results['theme_files_found']);
        
        if ($results['total_snippets'] == 0) {
            $results['safety_score'] = 100;
            $results['message'] = __('No Google tracking codes found!', 'google-tags-scanner');
        } elseif ($results['total_snippets'] <= 3 && $total_locations <= 2) {
            $results['safety_score'] = 95;
            $results['message'] = __('Very low risk - minimal tracking code found', 'google-tags-scanner');
        } elseif ($results['total_snippets'] <= 8 && $total_locations <= 5) {
            $results['safety_score'] = 85;
            $results['message'] = __('Low risk - safe to proceed with cleanup', 'google-tags-scanner');
        } elseif ($results['total_snippets'] <= 15) {
            $results['safety_score'] = 75;
            $results['message'] = __('Medium risk - review findings carefully', 'google-tags-scanner');
        } else {
            $results['safety_score'] = 60;
            $results['message'] = __('High complexity - manual review strongly recommended', 'google-tags-scanner');
        }
        
        wp_send_json_success($results);
    }
    
    private function scan_posts_content() {
        global $wpdb;
        $found_posts = [];
        
        // More targeted search
        $posts = $wpdb->get_results("
            SELECT ID, post_title, post_type, post_status, post_content
            FROM {$wpdb->posts} 
            WHERE post_status IN ('publish', 'draft', 'private')
            AND (
                post_content LIKE '%gtag(%' OR
                post_content LIKE '%googletagmanager.com%' OR
                post_content LIKE '%google-analytics.com%' OR
                post_content LIKE '%googlesyndication.com%' OR
                post_content LIKE '%UA-%' OR
                post_content LIKE '%G-%' OR
                post_content LIKE '%GTM-%' OR
                post_content LIKE '%ca-pub-%'
            )
            LIMIT 50
        ");
        
        foreach ($posts as $post) {
            $snippets = $this->extract_google_snippets($post->post_content);
            
            if (!empty($snippets)) {
                $found_posts[] = [
                    'id' => $post->ID,
                    'title' => $post->post_title ?: __('Untitled', 'google-tags-scanner'),
                    'type' => $post->post_type,
                    'status' => $post->post_status,
                    'edit_link' => get_edit_post_link($post->ID),
                    'view_link' => get_permalink($post->ID),
                    'snippets' => $snippets
                ];
            }
        }
        
        return $found_posts;
    }
    
    private function scan_wp_options() {
        global $wpdb;
        $found_options = [];
        
        // Scan specific options that commonly contain tracking codes
        $common_options = [
            'google_analytics_code',
            'gtag_code',
            'google_tag_manager',
            'ga_code',
            'analytics_code',
            'header_scripts',
            'footer_scripts',
            'custom_css',
            'custom_js',
            'theme_options',
            get_option('stylesheet') . '_options'
        ];
        
        foreach ($common_options as $option_name) {
            $value = get_option($option_name);
            if ($value) {
                $snippets = $this->extract_google_snippets($value);
                if (!empty($snippets)) {
                    $found_options[] = [
                        'option_name' => $option_name,
                        'type' => 'wp_option',
                        'location' => __('WordPress Options', 'google-tags-scanner'),
                        'snippets' => $snippets
                    ];
                }
            }
        }
        
        // Scan customizer options
        $customizer_data = get_option('theme_mods_' . get_option('stylesheet'));
        if (is_array($customizer_data)) {
            foreach ($customizer_data as $key => $value) {
                if (is_string($value)) {
                    $snippets = $this->extract_google_snippets($value);
                    if (!empty($snippets)) {
                        $found_options[] = [
                            'option_name' => 'theme_mods_' . get_option('stylesheet') . '[' . $key . ']',
                            'type' => 'customizer',
                            'location' => __('WordPress Customizer', 'google-tags-scanner'),
                            'snippets' => $snippets
                        ];
                    }
                }
            }
        }
        
        return $found_options;
    }
    
    private function scan_elementor_data() {
        global $wpdb;
        $found_elementor = [];
        
        // Check if Elementor is active
        if (!is_plugin_active('elementor/elementor.php')) {
            return $found_elementor;
        }
        
        // Scan Elementor page data
        $elementor_data = $wpdb->get_results("
            SELECT post_id, meta_value 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_elementor_data'
            AND (
                meta_value LIKE '%gtag%' OR
                meta_value LIKE '%googletagmanager%' OR
                meta_value LIKE '%UA-%' OR
                meta_value LIKE '%G-%' OR
                meta_value LIKE '%GTM-%'
            )
            LIMIT 30
        ");
        
        foreach ($elementor_data as $data) {
            $decoded_data = json_decode($data->meta_value, true);
            $snippets = $this->extract_google_snippets_from_elementor($decoded_data);
            
            if (!empty($snippets)) {
                $post_title = get_the_title($data->post_id);
                $found_elementor[] = [
                    'post_id' => $data->post_id,
                    'post_title' => $post_title ?: __('Untitled', 'google-tags-scanner'),
                    'edit_link' => admin_url('post.php?post=' . $data->post_id . '&action=elementor'),
                    'type' => 'elementor_page_data',
                    'snippets' => $snippets
                ];
            }
        }
        
        // Check Elementor global settings
        $elementor_settings = get_option('elementor_custom_css') . ' ' . get_option('elementor_custom_js');
        $snippets = $this->extract_google_snippets($elementor_settings);
        if (!empty($snippets)) {
            $found_elementor[] = [
                'post_id' => 0,
                'post_title' => __('Elementor Global Settings', 'google-tags-scanner'),
                'edit_link' => admin_url('admin.php?page=elementor#tab-advanced'),
                'type' => 'elementor_global_settings',
                'snippets' => $snippets
            ];
        }
        
        return $found_elementor;
    }
    
    private function scan_theme_files() {
        $found_files = [];
        $theme_dir = get_template_directory();
        
        $files_to_check = [
            'header.php',
            'footer.php',
            'functions.php',
            'index.php'
        ];
        
        foreach ($files_to_check as $filename) {
            $file_path = $theme_dir . '/' . $filename;
            if (file_exists($file_path) && is_readable($file_path)) {
                $content = file_get_contents($file_path);
                $snippets = $this->extract_google_snippets($content);
                
                if (!empty($snippets)) {
                    $found_files[] = [
                        'filename' => $filename,
                        'full_path' => $file_path,
                        'writable' => is_writable($file_path),
                        'size' => filesize($file_path),
                        'snippets' => $snippets
                    ];
                }
            }
        }
        
        return $found_files;
    }
    
    private function scan_plugins() {
        $found_plugins = [];
        
        $analytics_plugins = [
            'google-analytics-for-wordpress/googleanalytics.php' => 'MonsterInsights',
            'ga-google-analytics/ga-google-analytics.php' => 'GA Google Analytics',
            'googleanalytics/googleanalytics.php' => 'Google Analytics',
            'google-analytics-dashboard-for-wp/gadwp.php' => 'Google Analytics Dashboard',
            'insert-headers-and-footers/ihaf.php' => 'Insert Headers and Footers',
            'header-footer-elementor/header-footer-elementor.php' => 'Header Footer Elementor'
        ];
        
        foreach ($analytics_plugins as $plugin_file => $plugin_name) {
            if (is_plugin_active($plugin_file)) {
                $found_plugins[] = [
                    'name' => $plugin_name,
                    'file' => $plugin_file,
                    'status' => 'active',
                    'deactivate_link' => wp_nonce_url(
                        admin_url('plugins.php?action=deactivate&plugin=' . $plugin_file),
                        'deactivate-plugin_' . $plugin_file
                    )
                ];
            }
        }
        
        return $found_plugins;
    }
    
    private function extract_google_snippets($content) {
        if (!is_string($content)) {
            return [];
        }
        
        $snippets = [];
        
        foreach ($this->google_patterns as $key => $pattern_info) {
            preg_match_all($pattern_info['pattern'], $content, $matches, PREG_OFFSET_CAPTURE);
            
            foreach ($matches[0] as $match) {
                $snippet_content = $match[0];
                $position = $match[1];
                
                // Extract IDs from the snippet
                $extracted_ids = [];
                if (preg_match_all($pattern_info['id_pattern'], $snippet_content, $id_matches)) {
                    $extracted_ids = array_unique($id_matches[1] ?? $id_matches[0]);
                }
                
                // Get context around the snippet
                $context_start = max(0, $position - 100);
                $context_end = min(strlen($content), $position + strlen($snippet_content) + 100);
                $context = substr($content, $context_start, $context_end - $context_start);
                
                $snippets[] = [
                    'type' => $pattern_info['name'],
                    'key' => $key,
                    'content' => $snippet_content,
                    'length' => strlen($snippet_content),
                    'position' => $position,
                    'context' => $context,
                    'extracted_ids' => $extracted_ids,
                    'line_number' => substr_count(substr($content, 0, $position), "\n") + 1
                ];
            }
        }
        
        return $snippets;
    }
    
    private function extract_google_snippets_from_elementor($elementor_data) {
        $snippets = [];
        
        if (is_array($elementor_data)) {
            $serialized = json_encode($elementor_data);
            $snippets = $this->extract_google_snippets($serialized);
            
            // Also check specific Elementor settings
            $this->search_elementor_recursive($elementor_data, $snippets);
        }
        
        return $snippets;
    }
    
    private function search_elementor_recursive($data, &$snippets) {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (is_string($value) && (
                    strpos($value, 'gtag') !== false ||
                    strpos($value, 'googletagmanager') !== false ||
                    preg_match('/(UA-[0-9]+-[0-9]+|G-[A-Z0-9]+|GTM-[A-Z0-9]+)/', $value)
                )) {
                    $found_snippets = $this->extract_google_snippets($value);
                    foreach ($found_snippets as $snippet) {
                        $snippet['elementor_setting'] = $key;
                        $snippets[] = $snippet;
                    }
                } elseif (is_array($value)) {
                    $this->search_elementor_recursive($value, $snippets);
                }
            }
        }
    }
    
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('🔍 Google Tags Scanner - Precise Detection', 'google-tags-scanner'); ?></h1>
            <div class="notice notice-info">
                <p><strong><?php _e('This tool shows you EXACTLY what Google tracking code exists on your site.', 'google-tags-scanner'); ?></strong></p>
                <p><?php _e('Each snippet is displayed in full so you can see exactly what would be removed.', 'google-tags-scanner'); ?></p>
            </div>
            
            <div id="scan-results"></div>
            <button id="scan-tags-btn" class="button button-primary button-large"><?php _e('🔍 Start Precise Scan', 'google-tags-scanner'); ?></button>
            <div id="progress" style="display:none; margin-top:10px;">
                <p><?php _e('⏳ Scanning your website for Google tracking codes... Please wait.', 'google-tags-scanner'); ?></p>
            </div>
            
            <div style="margin-top: 30px; padding: 20px; background: #f0f8ff; border-left: 4px solid #0073aa; border-radius: 4px;">
                <h3><?php _e('🎯 What This Scanner Does:', 'google-tags-scanner'); ?></h3>
                <ul style="margin-left: 20px;">
                    <li><strong><?php _e('Shows exact code snippets', 'google-tags-scanner'); ?></strong> - <?php _e('See exactly what tracking code exists', 'google-tags-scanner'); ?></li>
                    <li><strong><?php _e('Identifies tracking IDs', 'google-tags-scanner'); ?></strong> - <?php _e('Extracts UA-, G-, GTM-, and other IDs', 'google-tags-scanner'); ?></li>
                    <li><strong><?php _e('Shows context', 'google-tags-scanner'); ?></strong> - <?php _e('See where each snippet appears in your code', 'google-tags-scanner'); ?></li>
                    <li><strong><?php _e('Precise detection', 'google-tags-scanner'); ?></strong> - <?php _e('No false positives from word matching', 'google-tags-scanner'); ?></li>
                </ul>
            </div>
        </div>
        
        <style>
        .scan-section { 
            margin: 20px 0; 
            padding: 20px; 
            border: 1px solid #ddd; 
            border-radius: 4px;
            background: white;
        }
        .scan-section h3 { 
            margin-top: 0; 
            color: #23282d;
        }
        .safety-high { color: #008000; font-weight: bold; font-size: 18px; }
        .safety-medium { color: #ffa500; font-weight: bold; font-size: 18px; }
        .safety-low { color: #ff0000; font-weight: bold; font-size: 18px; }
        .found-item { 
            margin: 15px 0; 
            padding: 15px; 
            background: #f9f9f9; 
            border-left: 3px solid #0073aa;
            border-radius: 3px;
        }
        .snippet-container {
            margin: 10px 0;
            padding: 12px;
            background: #2d3748;
            color: #e2e8f0;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            overflow-x: auto;
            position: relative;
        }
        .snippet-header {
            background: #4a5568;
            color: #fff;
            padding: 8px 12px;
            margin: -12px -12px 12px -12px;
            border-radius: 4px 4px 0 0;
            font-weight: bold;
            font-size: 11px;
        }
        .snippet-content {
            white-space: pre-wrap;
            word-break: break-all;
            max-height: 200
