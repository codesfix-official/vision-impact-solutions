<?php
/**
 * License Handler - CRUD operations
 * 
 * @package VisionImpactCustomSolutions
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class VICS_License_Handler {
    
    /**
     * Constructor
     */
    public function __construct() {
        // AJAX handlers
        add_action('wp_ajax_vics_get_licenses', array($this, 'get_licenses'));
        add_action('wp_ajax_vics_get_license', array($this, 'get_license'));
        add_action('wp_ajax_vics_add_license', array($this, 'add_license'));
        add_action('wp_ajax_vics_update_license', array($this, 'update_license'));
        add_action('wp_ajax_vics_delete_license', array($this, 'delete_license'));
    }
    
    /**
     * Get all licenses
     */
    public function get_licenses() {
        check_ajax_referer('vics_profile_nonce', 'vics_profile_nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('Not logged in', 'vics'));
        }
        
        $user_id = get_current_user_id();
        $licenses = VICS_Database::get_licenses($user_id);
        
        wp_send_json_success(array(
            'licenses' => $licenses
        ));
    }
    
    /**
     * Get single license
     */
    public function get_license() {
        check_ajax_referer('vics_profile_nonce', 'vics_profile_nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('Not logged in', 'vics'));
        }
        
        $user_id = get_current_user_id();
        $license_id = intval($_POST['license_id'] ?? 0);
        
        if (!$license_id) {
            wp_send_json_error(__('Invalid license ID', 'vics'));
        }
        
        $license = VICS_Database::get_license($license_id, $user_id);
        
        if (!$license) {
            wp_send_json_error(__('License not found', 'vics'));
        }
        
        wp_send_json_success(array(
            'license' => $license
        ));
    }
    
    /**
     * Add new license
     */
    public function add_license() {
        check_ajax_referer('vics_profile_nonce', 'vics_profile_nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('Not logged in', 'vics'));
        }
        
        $user_id = get_current_user_id();
        
        // Validate required fields
        if (empty($_POST['license_state'])) {
            wp_send_json_error(__('License state is required', 'vics'));
        }

        $license_type = '';
        if (isset($_POST['license_type']) && $_POST['license_type'] !== '') {
            $license_type = sanitize_text_field($_POST['license_type']);
        } elseif (isset($_POST['license_id']) && $_POST['license_id'] !== '') {
            $license_type = sanitize_text_field($_POST['license_id']);
        }
        
        $data = array(
            'license_type' => $license_type,
            'license_state' => sanitize_text_field($_POST['license_state']),
            'license_number' => sanitize_text_field($_POST['license_number'] ?? ''),
            'issue_date' => !empty($_POST['issue_date']) ? sanitize_text_field($_POST['issue_date']) : null,
            'expiry_date' => !empty($_POST['expiry_date']) ? sanitize_text_field($_POST['expiry_date']) : null,
            'status' => sanitize_text_field($_POST['status'] ?? 'pending'),
            'notes' => sanitize_textarea_field($_POST['notes'] ?? '')
        );
        
        $license_id = VICS_Database::add_license($user_id, $data);
        
        if (!$license_id) {
            wp_send_json_error(__('Failed to add license', 'vics'));
        }
        
        do_action('vics_license_added', $user_id, $license_id, $data);
        
        wp_send_json_success(array(
            'message' => __('License added successfully!', 'vics'),
            'license_id' => $license_id
        ));
    }
    
    /**
     * Update license
     */
    public function update_license() {
        check_ajax_referer('vics_profile_nonce', 'vics_profile_nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('Not logged in', 'vics'));
        }
        
        $user_id = get_current_user_id();
        $license_id = intval($_POST['license_id'] ?? 0);
        
        if (!$license_id) {
            wp_send_json_error(__('Invalid license ID', 'vics'));
        }
        
        // Check ownership
        $existing = VICS_Database::get_license($license_id, $user_id);
        if (!$existing) {
            wp_send_json_error(__('License not found', 'vics'));
        }
        
        $data = array(
            'license_type' => sanitize_text_field($_POST['license_type'] ?? $existing['license_type']),
            'license_state' => sanitize_text_field($_POST['license_state'] ?? $existing['license_state']),
            'license_number' => sanitize_text_field($_POST['license_number'] ?? ''),
            'issue_date' => !empty($_POST['issue_date']) ? sanitize_text_field($_POST['issue_date']) : null,
            'expiry_date' => !empty($_POST['expiry_date']) ? sanitize_text_field($_POST['expiry_date']) : null,
            'status' => sanitize_text_field($_POST['status'] ?? 'pending'),
            'notes' => sanitize_textarea_field($_POST['notes'] ?? '')
        );
        
        VICS_Database::update_license($license_id, $user_id, $data);
        
        do_action('vics_license_updated', $user_id, $license_id, $data);
        
        wp_send_json_success(array(
            'message' => __('License updated successfully!', 'vics')
        ));
    }
    
    /**
     * Delete license
     */
    public function delete_license() {
        check_ajax_referer('vics_profile_nonce', 'vics_profile_nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('Not logged in', 'vics'));
        }
        
        $user_id = get_current_user_id();
        $license_id = intval($_POST['license_id'] ?? 0);
        
        if (!$license_id) {
            wp_send_json_error(__('Invalid license ID', 'vics'));
        }
        
        // Check ownership
        $existing = VICS_Database::get_license($license_id, $user_id);
        if (!$existing) {
            wp_send_json_error(__('License not found', 'vics'));
        }
        
        VICS_Database::delete_license($license_id, $user_id);
        
        do_action('vics_license_deleted', $user_id, $license_id);
        
        wp_send_json_success(array(
            'message' => __('License deleted successfully!', 'vics')
        ));
    }
}