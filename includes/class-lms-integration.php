<?php
/**
 * LMS Provider Wrapper Integration
 *
 * @package VisionImpactCustomSolutions
 * @since 1.2.3
 */

if (!defined('ABSPATH')) {
    exit;
}

class VICS_LMS_Integration {

    /**
     * Detect active LMS provider
     */
    public static function get_provider() {
        if (class_exists('VICS_LearnDash_LMS_Integration') && VICS_LearnDash_LMS_Integration::is_active()) {
            return 'learndash';
        }

        if (class_exists('VICS_Tutor_LMS_Integration') && VICS_Tutor_LMS_Integration::is_active()) {
            return 'tutor';
        }

        return 'none';
    }

    /**
     * Get user progress from active LMS provider
     */
    public static function get_user_progress($user_id) {
        $provider = self::get_provider();

        if ($provider === 'learndash') {
            return VICS_LearnDash_LMS_Integration::get_user_progress($user_id);
        }

        if ($provider === 'tutor') {
            return VICS_Tutor_LMS_Integration::get_user_progress($user_id);
        }

        return array(
            'courses' => array(),
            'total_courses' => 0,
            'completed_courses' => 0,
            'overall_percent' => 0
        );
    }

    /**
     * Get dashboard URL from active LMS provider
     */
    public static function get_dashboard_url() {
        $provider = self::get_provider();

        if ($provider === 'learndash') {
            return VICS_LearnDash_LMS_Integration::get_dashboard_url();
        }

        if ($provider === 'tutor') {
            return VICS_Tutor_LMS_Integration::get_dashboard_url();
        }

        $configured_lms_url = trim((string) get_option('vics_lms_page_url', ''));
        if (!empty($configured_lms_url)) {
            return $configured_lms_url;
        }

        return home_url('/courses');
    }
}
