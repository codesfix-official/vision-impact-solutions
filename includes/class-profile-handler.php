<?php
/**
 * Profile Handler
 *
 * @package VisionImpactCustomSolutions
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class VICS_Profile_Handler {

    /**
     * Constructor
     */
    public function __construct() {
        // AJAX handlers
        add_action('wp_ajax_vics_update_profile', array($this, 'update_profile'));
        add_action('wp_ajax_vics_upload_avatar', array($this, 'upload_avatar'));
        add_action('wp_ajax_vics_update_social_links', array($this, 'update_social_links'));
        add_action('wp_ajax_vics_update_password', array($this, 'update_password'));

        // Unify WordPress avatar output with VICS uploaded profile image (used by LearnDash [ld_profile] too)
        add_filter('get_avatar_data', array($this, 'filter_avatar_data'), 10, 2);
    }

    /**
     * Resolve user ID from get_avatar() input.
     *
     * @param mixed $id_or_email
     * @return int
     */
    private function resolve_user_id_from_avatar_input($id_or_email) {
        if (is_numeric($id_or_email)) {
            return absint($id_or_email);
        }

        if (is_object($id_or_email) && !empty($id_or_email->user_id)) {
            return absint($id_or_email->user_id);
        }

        if ($id_or_email instanceof WP_User) {
            return absint($id_or_email->ID);
        }

        if (is_string($id_or_email) && is_email($id_or_email)) {
            $user = get_user_by('email', $id_or_email);
            if ($user) {
                return absint($user->ID);
            }
        }

        return 0;
    }

    /**
     * Replace default avatar URL with VICS profile image if available.
     *
     * @param array $args
     * @param mixed $id_or_email
     * @return array
     */
    public function filter_avatar_data($args, $id_or_email) {
        $user_id = $this->resolve_user_id_from_avatar_input($id_or_email);
        if ($user_id <= 0) {
            return $args;
        }

        $profile = VICS_Database::get_profile($user_id);
        $photo_id = !empty($profile['profile_photo_id']) ? absint($profile['profile_photo_id']) : 0;
        if ($photo_id <= 0) {
            return $args;
        }

        $size = !empty($args['size']) ? absint($args['size']) : 96;
        $avatar_url = wp_get_attachment_image_url($photo_id, array($size, $size));

        if (!empty($avatar_url)) {
            $args['url'] = $avatar_url;
            $args['found_avatar'] = true;
        }

        return $args;
    }

    /**
     * Update profile
     */
    public function update_profile() {
        check_ajax_referer('vics_profile_nonce', 'vics_profile_nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(__('Not logged in', 'vics'));
        }

        $user_id = get_current_user_id();

        // Update WordPress user data (only if these fields are present)
        if (isset($_POST['first_name']) || isset($_POST['last_name']) || isset($_POST['email'])) {
            $user_data = array(
                'ID' => $user_id,
                'first_name' => sanitize_text_field($_POST['first_name'] ?? ''),
                'last_name' => sanitize_text_field($_POST['last_name'] ?? ''),
                'user_email' => sanitize_email($_POST['email'] ?? '')
            );

            $result = wp_update_user($user_data);
            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            }
        }

        // Update custom profile data - only update fields that are present in the request
        $profile_data = array();
        
        $allowed_fields = array(
            'phone', 'agent_code', 'npn', 'license_number', 'date_of_birth', 
            'city', 'state', 'goals_for_year', 'google_sheet_id',
            'facebook_url', 'instagram_url', 'twitter_url', 'tiktok_url', 'youtube_url', 'linkedin_url',
            'favorite_things', 'unknown_fact', 'support_needed', 'feedback_preference'
        );
        
        // Check if agent_code is being changed by agent (not admin)
        $agent_code_changed = false;
        if (isset($_POST['agent_code']) && !current_user_can('manage_options')) {
            $current_profile = VICS_Database::get_profile($user_id);
            if (isset($current_profile['agent_code']) && $current_profile['agent_code'] !== $_POST['agent_code']) {
                $agent_code_changed = true;
            } elseif (!isset($current_profile['agent_code']) && !empty($_POST['agent_code'])) {
                $agent_code_changed = true;
            }
        }
        
        foreach ($allowed_fields as $field) {
            if (isset($_POST[$field])) {
                if ($field === 'date_of_birth') {
                    $profile_data[$field] = !empty($_POST[$field]) ? sanitize_text_field($_POST[$field]) : null;
                } elseif (in_array($field, array('goals_for_year', 'favorite_things', 'unknown_fact', 'support_needed', 'feedback_preference'))) {
                    $profile_data[$field] = sanitize_textarea_field($_POST[$field]);
                } else {
                    $profile_data[$field] = sanitize_text_field($_POST[$field]);
                }
            }
        }

        // Dynamic About You answers (stored in user meta)
        if (isset($_POST['about_answers']) && is_array($_POST['about_answers'])) {
            $about_answers = array();
            foreach ($_POST['about_answers'] as $question_id => $answer) {
                $clean_question_id = sanitize_key($question_id);
                if ($clean_question_id === '') {
                    continue;
                }
                $about_answers[$clean_question_id] = sanitize_textarea_field($answer);
            }

            update_user_meta($user_id, 'vics_about_answers', $about_answers);

            // Backward compatibility: sync legacy fields if they exist in dynamic questions
            $legacy_about_fields = array('goals_for_year', 'favorite_things', 'unknown_fact', 'support_needed', 'feedback_preference');
            foreach ($legacy_about_fields as $legacy_field) {
                if (array_key_exists($legacy_field, $about_answers)) {
                    $profile_data[$legacy_field] = $about_answers[$legacy_field];
                }
            }
        }
        
        // If agent code is being changed by agent, set status to pending
        if ($agent_code_changed) {
            $profile_data['agent_code_status'] = 'pending';
        }

        VICS_Database::update_profile($user_id, $profile_data);

        do_action('vics_profile_updated', $user_id, $profile_data);
        
        // Custom message if agent code was changed
        $message = __('Profile updated successfully!', 'vics');
        if ($agent_code_changed) {
            $message = __('Profile updated! Your agent code has been submitted for admin review.', 'vics');
        }

        wp_send_json_success(array(
            'message' => $message
        ));
    }

    /**
     * Upload avatar
     */
    public function upload_avatar() {
        check_ajax_referer('vics_profile_nonce', 'vics_profile_nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(__('Not logged in', 'vics'));
        }

        $user_id = get_current_user_id();

        if (empty($_FILES['avatar'])) {
            wp_send_json_error(__('No file uploaded', 'vics'));
        }

        $file = $_FILES['avatar'];

        // Validate file type
        $allowed_types = array('image/jpeg', 'image/png', 'image/gif');
        if (!in_array($file['type'], $allowed_types)) {
            wp_send_json_error(__('Invalid file type. Only JPG, PNG, and GIF are allowed.', 'vics'));
        }

        // Validate file size (max 2MB)
        if ($file['size'] > 2 * 1024 * 1024) {
            wp_send_json_error(__('File too large. Maximum size is 2MB.', 'vics'));
        }

        // Handle upload
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $attachment_id = media_handle_upload('avatar', 0);

        if (is_wp_error($attachment_id)) {
            wp_send_json_error($attachment_id->get_error_message());
        }

        // Update profile with new avatar
        VICS_Database::update_profile($user_id, array(
            'profile_photo_id' => $attachment_id
        ));

        $avatar_url = wp_get_attachment_image_url($attachment_id, 'thumbnail');

        wp_send_json_success(array(
            'message' => __('Avatar uploaded successfully!', 'vics'),
            'avatar_url' => $avatar_url
        ));
    }

    /**
     * Update social links
     */
    public function update_social_links() {
        check_ajax_referer('vics_profile_nonce', 'vics_profile_nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(__('Not logged in', 'vics'));
        }

        $user_id = get_current_user_id();

        $social_data = array(
            'facebook_url' => esc_url_raw($_POST['facebook_url'] ?? ''),
            'instagram_url' => esc_url_raw($_POST['instagram_url'] ?? ''),
            'twitter_url' => esc_url_raw($_POST['twitter_url'] ?? ''),
            'tiktok_url' => esc_url_raw($_POST['tiktok_url'] ?? ''),
            'youtube_url' => esc_url_raw($_POST['youtube_url'] ?? ''),
            'linkedin_url' => esc_url_raw($_POST['linkedin_url'] ?? '')
        );

        VICS_Database::update_profile($user_id, $social_data);

        wp_send_json_success(array(
            'message' => __('Social links updated successfully!', 'vics')
        ));
    }

    /**
     * Update password
     */
    public function update_password() {
        check_ajax_referer('vics_profile_nonce', 'vics_profile_nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(__('Not logged in', 'vics'));
        }

        $user_id = get_current_user_id();
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Validate current password
        $user = get_user_by('ID', $user_id);
        if (!wp_check_password($current_password, $user->user_pass, $user_id)) {
            wp_send_json_error(__('Current password is incorrect.', 'vics'));
        }

        // Validate new password
        if (strlen($new_password) < 8) {
            wp_send_json_error(__('New password must be at least 8 characters long.', 'vics'));
        }

        if ($new_password !== $confirm_password) {
            wp_send_json_error(__('New passwords do not match.', 'vics'));
        }

        // Update password
        wp_set_password($new_password, $user_id);

        wp_send_json_success(array(
            'message' => __('Password updated successfully! Please log in again.', 'vics')
        ));
    }
}