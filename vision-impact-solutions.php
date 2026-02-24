<?php
/**
 * Plugin Name: Vision Impact Solutions
 * Plugin URI: https://visionimpact.com
 * Description: Complete agent management system with orientation, profile management, license tracking, and Tutor LMS integration
 * Version: 1.2.2
 * Author: Vision Impact
 * Author URI: https://visionimpact.com
 * Text Domain: vics
 * Domain Path: /languages
 * 
 * @package VisionImpactCustomSolutions
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plugin Constants
define('VICS_VERSION', '1.2.2');
define('VICS_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('VICS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('VICS_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Debug Constants
define('VICS_DEBUG_LOG_FILE', WP_CONTENT_DIR . '/vics-debug.log');

/**
 * Check if debug logging is enabled
 * Returns true if WP_DEBUG is enabled and vics_debug_logging option is set
 */
function vics_debug_enabled() {
    return defined('WP_DEBUG') && WP_DEBUG && get_option('vics_enable_debug_logging', false);
}

/**
 * Debug logging function
 * Only logs when WP_DEBUG is true and vics_debug_logging option is enabled
 */
function vics_log($message, $level = 'info') {
    if (!vics_debug_enabled()) {
        return;
    }

    $timestamp = current_time('Y-m-d H:i:s');
    $log_message = sprintf('[%s] VICS %s: %s', $timestamp, strtoupper($level), $message);

    // Log to file
    if (defined('VICS_DEBUG_LOG_FILE')) {
        @file_put_contents(VICS_DEBUG_LOG_FILE, $log_message . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    // Also log to WordPress debug.log if available
    if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        error_log($log_message);
    }
}

/**
 * Main Plugin Class
 * 
 * @since 1.0.0
 */
final class Vision_Impact_Custom_Solutions {
    
    /**
     * Single instance
     * @var Vision_Impact_Custom_Solutions
     */
    private static $instance = null;
    
    /**
     * Get single instance
     * 
     * @return Vision_Impact_Custom_Solutions
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->includes();
        $this->init_hooks();
    }
    
    /**
     * Prevent cloning
     */
    private function __clone() {}
    
    /**
     * Prevent unserializing
     */
    public function __wakeup() {}
    
    /**
     * Include required files
     */
    private function includes() {
        // Core
        require_once VICS_PLUGIN_PATH . 'includes/class-database.php';
        
        // Orientation Module
        require_once VICS_PLUGIN_PATH . 'includes/class-orientation-handler.php';
        
        // Profile Module
        require_once VICS_PLUGIN_PATH . 'includes/class-profile-handler.php';
        
        // License Module
        require_once VICS_PLUGIN_PATH . 'includes/class-license-handler.php';
        
        // LMS Integrations
        require_once VICS_PLUGIN_PATH . 'includes/class-tutor-lms-integration.php';
        require_once VICS_PLUGIN_PATH . 'includes/class-learndash-lms-integration.php';
        require_once VICS_PLUGIN_PATH . 'includes/class-lms-integration.php';
        
        // Google Integration
        require_once VICS_PLUGIN_PATH . 'includes/class-google-auth.php';
        require_once VICS_PLUGIN_PATH . 'includes/class-google-sheets.php';
        require_once VICS_PLUGIN_PATH . 'includes/class-google-sync.php';
        
        // Events Module
        require_once VICS_PLUGIN_PATH . 'includes/class-events.php';

        // Leaders Module
        require_once VICS_PLUGIN_PATH . 'includes/class-leaders.php';
        
        // Admin
        if (is_admin()) {
            require_once VICS_PLUGIN_PATH . 'includes/class-main-admin.php';
        }
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Init
        add_action('init', array($this, 'init'));
        add_action('init', array($this, 'maybe_refresh_rewrite_rules'), 99);
        add_action('parse_request', array($this, 'fallback_single_cpt_routes'), 1);
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Scripts & Styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Shortcodes
        add_shortcode('agent_profile', array($this, 'render_profile_page'));
        
        // Run database migrations on admin init
        add_action('admin_init', array($this, 'check_and_migrate_database'));
    }
    
    /**
     * Check and run database migrations
     */
    public function check_and_migrate_database() {
        $db_version = get_option('vics_db_version', '1.0.0');
        $current_version = '1.0.2'; // Increment this when you need to run new migrations
        
        if (version_compare($db_version, $current_version, '<')) {
            VICS_Database::migrate_tables();
            update_option('vics_db_version', $current_version);
        }
    }
    
    /**
     * Plugin Activation
     */
    public function activate() {
        // Create database tables
        VICS_Database::create_tables();
        
        // Create Agent role
        vics_create_agent_role();
        
        // Create pages
        vics_create_pages();
        
        // Set default options
        vics_set_default_options();
        
        // Clear rewrite rules
        flush_rewrite_rules();
        
        // Trigger activation hook for extensions
        do_action('vics_activated');
    }
    
    /**
     * Plugin Deactivation
     */
    public function deactivate() {
        flush_rewrite_rules();
        
        // Trigger deactivation hook for extensions
        do_action('vics_deactivated');
    }
    
    /**
     * Load Text Domain
     */
    public function load_textdomain() {
        load_plugin_textdomain('vics', false, dirname(VICS_PLUGIN_BASENAME) . '/languages');
    }
    
    /**
     * Initialize Classes
     */
    public function init() {
        // Orientation
        new VICS_Orientation_Handler();
        
        // Profile
        new VICS_Profile_Handler();
        
        // License
        new VICS_License_Handler();
        
        // LMS Integration (prefer LearnDash when active, keep Tutor as fallback)
        if (VICS_LearnDash_LMS_Integration::is_active()) {
            new VICS_LearnDash_LMS_Integration();
        } else {
            new VICS_Tutor_LMS_Integration();
        }
        
        // Google Integration
        new VICS_Google_Sync();
        
        // Admin
        if (is_admin()) {
            new VICS_Admin_Settings();
            
            // Add AJAX handler for testing sheet creation
            add_action('wp_ajax_vics_test_sheet_creation', array($this, 'test_sheet_creation'));
        }
        
        // Allow extensions to hook in
        do_action('vics_init');
    }

    /**
     * Refresh rewrite rules after plugin updates
     */
    public function maybe_refresh_rewrite_rules() {
        $rewrite_version = get_option('vics_rewrite_version', '');

        if ($rewrite_version !== VICS_VERSION) {
            flush_rewrite_rules(false);
            update_option('vics_rewrite_version', VICS_VERSION);
        }
    }

    /**
     * Fallback route handling for leaders/events single pages when rewrite rules are stale.
     */
    public function fallback_single_cpt_routes($wp) {
        if (is_admin() || !isset($wp->request)) {
            return;
        }

        $request_path = trim((string) $wp->request, '/');

        if (preg_match('#^leaders/([^/]+)$#', $request_path, $matches)) {
            $slug = sanitize_title(rawurldecode($matches[1]));
            $wp->query_vars = array(
                'post_type' => 'leaders',
                'name' => $slug
            );
            $wp->matched_rule = 'vics_fallback_leaders_single';
            $wp->matched_query = 'post_type=leaders&name=' . $slug;
            return;
        }

        if (preg_match('#^events/([^/]+)$#', $request_path, $matches)) {
            $slug = sanitize_title(rawurldecode($matches[1]));
            $wp->query_vars = array(
                'post_type' => 'events',
                'name' => $slug
            );
            $wp->matched_rule = 'vics_fallback_events_single';
            $wp->matched_query = 'post_type=events&name=' . $slug;
        }
    }
    
    /**
     * Test sheet creation via AJAX
     */
    public function test_sheet_creation() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $user_id = intval($_POST['user_id'] ?? 0);
        
        if ($user_id <= 0) {
            wp_send_json_error('Invalid user ID');
        }
        
        vics_log('=== TEST SHEET CREATION ===');
        vics_log('Test initiated for user_id: ' . $user_id);
        
        // Check debug logging
        vics_log('Debug logging enabled: ' . (vics_debug_enabled() ? 'YES' : 'NO'));
        vics_log('WP_DEBUG: ' . (defined('WP_DEBUG') && WP_DEBUG ? 'YES' : 'NO'));
        
        $google_sync = new VICS_Google_Sync();
        $result = $google_sync->create_agent_sheet($user_id);
        
        vics_log('create_agent_sheet() returned: ' . ($result ? 'TRUE' : 'FALSE'));
        vics_log('=== TEST COMPLETE ===');
        
        if ($result) {
            wp_send_json_success('Sheet creation test completed successfully. Check debug logs for details.');
        } else {
            wp_send_json_error('Sheet creation test failed. Check debug logs for details.');
        }
    }
    
    /**
     * Enqueue Frontend Scripts & Styles
     */
    public function enqueue_frontend_scripts() {
        $user_id = get_current_user_id();
        
        // CRITICAL: Enqueue CSS overrides FIRST to ensure high priority
        // This must load before all other plugin CSS and theme CSS
        wp_enqueue_style(
            'vics-overrides',
            VICS_PLUGIN_URL . 'assets/css/vics-overrides.css',
            array(),
            VICS_VERSION
        );
        
        // Add inline CSS for additional specificity override
        wp_add_inline_style('vics-overrides', $this->get_critical_inline_css());
        
        // Dequeue conflicting Elementor styles to prevent override
       // add_action('wp_enqueue_scripts', array($this, 'dequeue_conflicting_styles'), 999);
        
        // Orientation assets (for non-completed users)
        if (is_user_logged_in() && !current_user_can('manage_options')) {
            if (!VICS_Database::has_completed_orientation($user_id)) {
                $this->enqueue_orientation_assets($user_id);
            }
        }
        
        // Profile page assets
        if (is_page('my-profile') && is_user_logged_in()) {
            $this->enqueue_profile_assets();
        }
        
        // Leaders and Events pages
        if (is_singular('leaders') || is_post_type_archive('leaders')) {
            $this->enqueue_leaders_assets();
        }
        if (is_singular('events') || is_post_type_archive('events')) {
            $this->enqueue_events_assets();
        }
    }

    /**
     * Dequeue conflicting theme or page builder styles
     */
    public function dequeue_conflicting_styles() {
        // Elementor front-end styles (common handles)
//         wp_dequeue_style('elementor-frontend');
//         wp_dequeue_style('elementor-pro');
//         wp_dequeue_style('elementor-icons');
//         wp_dequeue_style('elementor-animations');

//         // Elementor post/page specific styles
//         if (is_singular()) {
//             $post_id = get_queried_object_id();
//             if ($post_id) {
//                 wp_dequeue_style('elementor-post-' . $post_id);
//             }
//         }
    }
    

    

    
    /**
     * Get Critical Inline CSS for maximum specificity
     */
    private function get_critical_inline_css() {
        return '
        /* Critical inline styles - highest priority */
        .leader-hero {
            background: linear-gradient(135deg, #4a6fa5 0%, #5a82b4 100%) !important;
        }
        .leader-profile-info h1,
        .leader-profile-info h2,
        .leader-profile-info h3,
        .leader-profile-info h4,
        .leader-profile-info h5,
        .leader-profile-info h6 {
            color: white !important;
        }
        .leader-contact {
            background: rgba(255, 255, 255, 0.1) !important;
            box-sizing: border-box !important;
        }
        
        /* Prevent Elementor CSS from affecting VICS elements */
        .leader-hero * {
            all: revert !important;
        }
        .leader-hero {
            all: unset !important;
            background: linear-gradient(135deg, #4a6fa5 0%, #5a82b4 100%) !important;
            color: white !important;
            position: relative !important;
            overflow: hidden !important;
            margin-top: -60px !important;
            padding-top: 100px !important;
            padding: 40px 20px 60px 20px !important;
        }
        
        /* Override Elementor box-sizing for VICS components */
        .leader-profile-grid,
        .leader-contact,
        .social-link,
        .leader-sidebar {
            box-sizing: border-box !important;
        }
        
        /* Force VICS styles over Elementor */
        .leader-profile-info h1 {
            color: white !important;
            font-size: 42px !important;
            font-weight: 700 !important;
        }
        ';
    }
    
    /**
     * Enqueue Leaders Assets
     */
    private function enqueue_leaders_assets() {
        wp_enqueue_style(
            'vics-leaders',
            VICS_PLUGIN_URL . 'assets/css/leaders.css',
            array('vics-overrides'),
            VICS_VERSION
        );
        
        wp_enqueue_style(
            'vics-single-leader',
            VICS_PLUGIN_URL . 'assets/css/single-leader.css',
            array('vics-overrides', 'vics-leaders'),
            VICS_VERSION
        );
    }
    
    /**
     * Enqueue Events Assets
     */
    private function enqueue_events_assets() {
        wp_enqueue_style(
            'vics-events',
            VICS_PLUGIN_URL . 'assets/css/single-event.css',
            array('vics-overrides'),
            VICS_VERSION
        );
        
        wp_enqueue_style(
            'vics-events-archive',
            VICS_PLUGIN_URL . 'assets/css/events-archive.css',
            array('vics-overrides', 'vics-events'),
            VICS_VERSION
        );
    }
    
    /**
     * Enqueue Orientation Assets
     */
    private function enqueue_orientation_assets($user_id) {
        // CSS - include overrides as dependency
        wp_enqueue_style(
            'vics-orientation',
            VICS_PLUGIN_URL . 'assets/css/orientation.css',
            array('vics-overrides'),
            VICS_VERSION
        );
        
        // Vimeo SDK
        wp_enqueue_script(
            'vimeo-player-sdk',
            'https://player.vimeo.com/api/player.js',
            array(),
            null,
            true
        );
        
        // JS
        wp_enqueue_script(
            'vics-orientation',
            VICS_PLUGIN_URL . 'assets/js/orientation.js',
            array('jquery', 'vimeo-player-sdk'),
            VICS_VERSION,
            true
        );
        
        $progress = VICS_Database::get_orientation_progress($user_id);
        $video_url = get_option('vics_video_url', '');
        $video_type = $this->detect_video_type($video_url);
        $video_id = '';
        
        // Extract video ID based on type
        if ($video_type === 'youtube') {
            if (preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $video_url, $matches)) {
                $video_id = $matches[1];
            }
        } elseif ($video_type === 'vimeo') {
            if (preg_match('/(?:vimeo\.com\/(?:video\/)?|player\.vimeo\.com\/video\/)(\d+)/', $video_url, $matches)) {
                $video_id = $matches[1];
            }
        } elseif ($video_type === 'html5') {
            $video_id = $video_url;
        }
        
        wp_localize_script('vics-orientation', 'vicsOrientationData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vics_orientation_nonce'),
            'videoUrl' => $video_url,
            'videoType' => $video_type,
            'videoId' => $video_id,
            'savedTimestamp' => floatval($progress['video_timestamp'] ?? 0),
            'formSubmitted' => (bool) ($progress['form_submitted'] ?? false),
            'videoCompleted' => (bool) ($progress['video_completed'] ?? false),
            'completionThreshold' => intval(get_option('vics_video_completion_threshold', 95)),
            'testingMode' => (bool) intval(get_option('vics_orientation_testing_mode', '0')),
            'welcomeMessage' => get_option('vics_welcome_message'),
            'profileUrl' => home_url('/my-profile')
        ));
    }
    
    /**
     * Enqueue Profile Assets
     */
    private function enqueue_profile_assets() {
        // Font Awesome
        wp_enqueue_style(
            'font-awesome',
            VICS_PLUGIN_URL . 'assets/css/font-awesome.min.css',
            array(),
            '6.4.0'
        );
        
        // CSS - include overrides as dependency for priority
        wp_enqueue_style(
            'vics-profile',
            VICS_PLUGIN_URL . 'assets/css/profile.css',
            array('font-awesome', 'vics-overrides'),
            VICS_VERSION
        );
        
        // JS
        wp_enqueue_script(
            'vics-profile',
            VICS_PLUGIN_URL . 'assets/js/profile.js',
            array('jquery'),
            VICS_VERSION,
            true
        );
        
        wp_localize_script('vics-profile', 'vicsProfileData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vics_profile_nonce'),
            'userId' => get_current_user_id(),
            'lmsUrl' => get_option('vics_lms_page_url', home_url('/courses')),
            'trackerUrl' => get_option('vics_production_tracker_url', '#'),
            'primaryLicense' => VICS_Database::get_primary_license_status(get_current_user_id())
        ));
    }
    
    /**
     * Enqueue Admin Scripts
     */
    public function enqueue_admin_scripts($hook) {
        // Only on our plugin pages
        if (strpos($hook, 'vics') === false && strpos($hook, 'vision-impact') === false) {
            return;
        }
        
        wp_enqueue_style(
            'vics-admin',
            VICS_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            VICS_VERSION
        );
        
        wp_enqueue_script(
            'vics-admin',
            VICS_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'jquery-ui-sortable'),
            VICS_VERSION,
            true
        );
        
        wp_localize_script('vics-admin', 'vicsAdminData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vics_admin_nonce')
        ));
    }
    
    /**
     * Detect Video Type
     */
    private function detect_video_type($url) {
        if (empty($url)) {
            return 'unknown';
        }
        
        if (preg_match('/youtube|youtu\.be/', $url)) {
            return 'youtube';
        } elseif (preg_match('/vimeo/', $url)) {
            return 'vimeo';
        } elseif (preg_match('/\.(mp4|webm|ogg)(\?.*)?$/i', $url)) {
            return 'html5';
        }
        
        return 'unknown';
    }
    
    /**
     * Render Profile Page (Shortcode)
     */
    public function render_profile_page() {
        // Login check
        if (!is_user_logged_in()) {
            return '<div class="ad-login-required">
                <h2>' . __('Access Denied', 'vics') . '</h2>
                <p>' . sprintf(
                    __('Please %s to access your profile.', 'vics'),
                    '<a href="' . wp_login_url(get_permalink()) . '</a>' . __('login', 'vics') . '</a>'
                ) . '</p>
            </div>';
        }
        
        // For users who haven't completed orientation, show minimal profile with orientation popup
        $user_id = get_current_user_id();
        if (!current_user_can('manage_options') && !VICS_Database::has_completed_orientation($user_id)) {
            // Show basic profile structure but orientation popup will be shown via wp_footer
            ob_start();
            include VICS_PLUGIN_PATH . 'templates/profile-page.php';
            return ob_get_clean();
        }
        
        // Show full profile for completed users
        ob_start();
        include VICS_PLUGIN_PATH . 'templates/profile-page.php';
        return ob_get_clean();
    }
}

/**
 * Returns the main instance of the plugin
 * 
 * @return Vision_Impact_Custom_Solutions
 */
function VICS() {
    return Vision_Impact_Custom_Solutions::get_instance();
}

// Initialize Plugin
VICS();

// Register activation/deactivation hooks
register_activation_hook(__FILE__, 'vics_activate_plugin');
register_deactivation_hook(__FILE__, 'vics_deactivate_plugin');

/**
 * Plugin activation callback
 */
function vics_activate_plugin() {
    // Create database tables
    VICS_Database::create_tables();
    
    // Create Agent role
    vics_create_agent_role();
    
    // Create pages
    vics_create_pages();
    
    // Set default options
    vics_set_default_options();
    
    // Clear rewrite rules
    flush_rewrite_rules();
    update_option('vics_rewrite_version', VICS_VERSION);
    
    // Trigger activation hook for extensions
    do_action('vics_activated');
}

/**
 * Plugin deactivation callback
 */
function vics_deactivate_plugin() {
    flush_rewrite_rules();
    delete_option('vics_rewrite_version');
    
    // Trigger deactivation hook for extensions
    do_action('vics_deactivated');
}

/**
 * Create Agent Role
 */
function vics_create_agent_role() {
    if (!get_role('agent')) {
        add_role('agent', __('Agent', 'vics'), array(
            'read' => true,
            'edit_posts' => false,
            'delete_posts' => false,
            'upload_files' => true
        ));
    }
}

/**
 * Create Required Pages
 */
function vics_create_pages() {
    // Profile Page
    if (!get_page_by_path('my-profile')) {
        wp_insert_post(array(
            'post_title'   => __('My Profile', 'vics'),
            'post_name'    => 'my-profile',
            'post_content' => '[agent_profile]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_author'  => 1
        ));
    }
}

/**
 * Set Default Options
 */
function vics_set_default_options() {
    // Orientation Options
    $orientation_defaults = array(
        'vics_video_url' => '',
        'vics_popup_header' => __('Welcome to Our Platform!', 'vics'),
        'vics_popup_description' => __('Please review the following information before continuing:', 'vics'),
        'vics_list_items' => array(),
        'vics_checkbox_text' => __('I have read and agree to the Terms and Conditions', 'vics'),
        'vics_button_text' => __('Continue to Orientation Video', 'vics'),
        'vics_welcome_message' => __('Welcome Onboard! You have successfully completed the orientation.', 'vics'),
        'vics_disclosure_text' => __('By accessing and using the tools, training, systems, and resources provided on this website, you acknowledge that there is no guarantee of success, income, or specific results. These resources are intended to support your development, but your success depends entirely on your personal effort, discipline, consistency, and ability to take action. You understand and agree that you are solely responsible for your own performance and outcomes as an independent agent, and results will vary based on individual commitment and execution.', 'vics'),
        'vics_video_completion_threshold' => 100,
        'vics_orientation_testing_mode' => '0',
    );
    
    foreach ($orientation_defaults as $key => $value) {
        add_option($key, $value);
    }
    
    // Profile Options
    $profile_defaults = array(
        'vics_lms_page_url' => '',
        'vics_production_tracker_url' => '',
    );
    
    foreach ($profile_defaults as $key => $value) {
        add_option($key, $value);
    }
    
    // Google Sheets Options (for future)
    $google_defaults = array(
        'vics_google_client_id' => '',
        'vics_google_client_secret' => '',
        'vics_master_sheet_id' => '',
    );
    
    foreach ($google_defaults as $key => $value) {
        add_option($key, $value);
    }
}

/**
 * Hide admin bar for agents
 */
function vics_hide_admin_bar_for_agents($show) {
    if (current_user_can('agent')) {
        return false;
    }
    return $show;
}
add_filter('show_admin_bar', 'vics_hide_admin_bar_for_agents');