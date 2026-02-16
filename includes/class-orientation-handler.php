<?php
/**
 * Orientation Handler
 * 
 * @package VisionImpactCustomSolutions
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class VICS_Orientation_Handler {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Check orientation status
        add_action('template_redirect', array($this, 'check_orientation_status'));
        
        // Render popup
        add_action('wp_footer', array($this, 'render_orientation_popup'));
        
        // AJAX handlers
        add_action('wp_ajax_vics_submit_orientation_form', array($this, 'handle_form_submission'));
        add_action('wp_ajax_vics_save_video_progress', array($this, 'save_video_progress'));
        add_action('wp_ajax_vics_mark_video_complete', array($this, 'mark_video_complete'));
        add_action('wp_ajax_vics_complete_orientation', array($this, 'complete_orientation'));
    }
    
    /**
     * Check if user needs to complete orientation
     */
    public function check_orientation_status() {
        // Skip for non-logged in, admins, and AJAX
        if (!is_user_logged_in() || current_user_can('manage_options') || wp_doing_ajax()) {
            return;
        }
        
        // Skip for logout
        if (isset($_GET['action']) && $_GET['action'] === 'logout') {
            return;
        }
        
        // Allow access to profile page during orientation
        if (is_page('my-profile')) {
            return;
        }
        
        $user_id = get_current_user_id();
        
        if (!VICS_Database::has_completed_orientation($user_id)) {
            // Redirect to profile page where orientation will be shown
            wp_redirect(home_url('/my-profile'));
            exit;
        }
    }
    
    /**
     * Render orientation popup
     */
    public function render_orientation_popup() {
        if (!is_user_logged_in() || current_user_can('manage_options')) {
            return;
        }
        
        $user_id = get_current_user_id();
        
        if (VICS_Database::has_completed_orientation($user_id)) {
            return;
        }
        
        $progress = VICS_Database::get_orientation_progress($user_id);
        $user = wp_get_current_user();
        
        // Get settings
        $popup_header = get_option('vics_popup_header', 'Welcome to Our Platform!');
        $popup_description = get_option('vics_popup_description', 'Please review the following information:');
        $list_items = get_option('vics_list_items', array());
        $checkbox_text = get_option('vics_checkbox_text', 'I agree to the Terms and Conditions');
        $button_text = get_option('vics_button_text', 'Continue to Orientation Video');
        $video_url = get_option('vics_video_url', '');
        
        // Detect video type and ID
        $video_type = '';
        $video_id = '';
        
        if (preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $video_url, $matches)) {
            $video_type = 'youtube';
            $video_id = $matches[1];
        } elseif (preg_match('/(?:vimeo\.com\/(?:video\/)?|player\.vimeo\.com\/video\/)(\d+)/', $video_url, $matches)) {
            $video_type = 'vimeo';
            $video_id = $matches[1];
        } elseif (preg_match('/\.(mp4|webm|ogg)(\?.*)?$/i', $video_url)) {
            $video_type = 'html5';
            $video_id = $video_url;
        }
        
        include VICS_PLUGIN_PATH . 'templates/orientation-popup.php';
    }
    
    /**
     * Handle form submission
     */
    public function handle_form_submission() {
        check_ajax_referer('vics_orientation_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('Not logged in', 'vics'));
        }
        
        $user_id = get_current_user_id();
        
        if (empty($_POST['terms_accepted'])) {
            wp_send_json_error(__('Please accept the terms and conditions', 'vics'));
        }
        
        VICS_Database::update_orientation_progress($user_id, array(
            'terms_accepted' => 1,
            'form_submitted' => 1
        ));
        
        wp_send_json_success(array(
            'message' => __('Form submitted successfully', 'vics'),
            'next_step' => 'video'
        ));
    }
    
    /**
     * Save video progress
     */
    public function save_video_progress() {
        check_ajax_referer('vics_orientation_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('Not logged in', 'vics'));
        }
        
        $user_id = get_current_user_id();
        $timestamp = floatval($_POST['timestamp'] ?? 0);
        $duration = floatval($_POST['duration'] ?? 0);
        
        VICS_Database::update_orientation_progress($user_id, array(
            'video_timestamp' => $timestamp,
            'video_duration' => $duration
        ));
        
        wp_send_json_success(array('saved' => true));
    }
    
    /**
     * Mark video as complete
     */
    public function mark_video_complete() {
        check_ajax_referer('vics_orientation_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('Not logged in', 'vics'));
        }
        
        $user_id = get_current_user_id();
        
        VICS_Database::update_orientation_progress($user_id, array(
            'video_completed' => 1
        ));
        
        wp_send_json_success(array('video_completed' => true));
    }
    
    /**
     * Complete orientation
     */
    public function complete_orientation() {
        check_ajax_referer('vics_orientation_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('Not logged in', 'vics'));
        }
        
        $user_id = get_current_user_id();
        $progress = VICS_Database::get_orientation_progress($user_id);
        
        if (!$progress['form_submitted']) {
            wp_send_json_error(__('Form not submitted', 'vics'));
        }
        
        if (!$progress['video_completed']) {
            wp_send_json_error(__('Video not completed', 'vics'));
        }
        
        VICS_Database::update_orientation_progress($user_id, array(
            'orientation_completed' => 1,
            'completed_at' => current_time('mysql')
        ));
        
        // Trigger action for extensions
        do_action('vics_orientation_completed', $user_id);
        
        wp_send_json_success(array(
            'message' => get_option('vics_welcome_message'),
            'redirect' => home_url('/my-profile')
        ));
    }
}