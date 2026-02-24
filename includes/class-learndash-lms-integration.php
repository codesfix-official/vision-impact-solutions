<?php
/**
 * LearnDash LMS Integration
 *
 * @package VisionImpactCustomSolutions
 * @since 1.2.3
 */

if (!defined('ABSPATH')) {
    exit;
}

class VICS_LearnDash_LMS_Integration {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_notices', array($this, 'check_learndash_lms'));
    }

    /**
     * Check if LearnDash LMS is active
     */
    public function check_learndash_lms() {
        if (!self::is_active() && current_user_can('manage_options')) {
            $screen = get_current_screen();
            if ($screen && strpos($screen->id, 'vics') !== false) {
                echo '<div class="notice notice-warning"><p>';
                echo __('Vision Impact Custom Solutions: LearnDash LMS plugin is not active. LMS features will be limited.', 'vics');
                echo '</p></div>';
            }
        }
    }

    /**
     * Check if LearnDash LMS is active
     */
    public static function is_active() {
        return function_exists('learndash_get_courses_user') || defined('LEARNDASH_VERSION');
    }

    /**
     * Get enrolled LearnDash course IDs
     */
    private static function get_enrolled_course_ids($user_id) {
        $course_ids = array();

        if (function_exists('learndash_user_get_enrolled_courses')) {
            $course_ids = learndash_user_get_enrolled_courses($user_id);
        }

        if (empty($course_ids) && function_exists('learndash_get_courses_user')) {
            $course_ids = learndash_get_courses_user($user_id);
        }

        if (!is_array($course_ids)) {
            return array();
        }

        $course_ids = array_map('intval', $course_ids);
        $course_ids = array_filter($course_ids, function ($course_id) {
            return $course_id > 0;
        });

        return array_values(array_unique($course_ids));
    }

    /**
     * Get user's enrolled courses
     */
    public static function get_enrolled_courses($user_id) {
        if (!self::is_active()) {
            return array();
        }

        $course_ids = self::get_enrolled_course_ids($user_id);
        if (empty($course_ids)) {
            return array();
        }

        $enrolled = array();

        foreach ($course_ids as $course_id) {
            $enrolled[] = array(
                'id' => $course_id,
                'title' => get_the_title($course_id),
                'permalink' => get_permalink($course_id),
                'progress' => self::get_course_progress($course_id, $user_id),
                'status' => self::get_course_status($course_id, $user_id)
            );
        }

        return $enrolled;
    }

    /**
     * Get course progress percentage
     */
    public static function get_course_progress($course_id, $user_id) {
        if (!self::is_active()) {
            return 0;
        }

        $percent = 0;

        if (function_exists('learndash_course_progress')) {
            $progress = learndash_course_progress(array(
                'user_id' => $user_id,
                'course_id' => $course_id,
                'array' => true
            ));

            if (is_array($progress)) {
                if (isset($progress['percentage'])) {
                    $percent = intval($progress['percentage']);
                } elseif (isset($progress['completed'], $progress['total']) && intval($progress['total']) > 0) {
                    $percent = intval(round((intval($progress['completed']) / intval($progress['total'])) * 100));
                }
            }
        }

        if ($percent <= 0 && function_exists('learndash_course_completed') && learndash_course_completed($user_id, $course_id)) {
            $percent = 100;
        }

        if ($percent < 0) {
            $percent = 0;
        } elseif ($percent > 100) {
            $percent = 100;
        }

        return $percent;
    }

    /**
     * Get course status
     */
    public static function get_course_status($course_id, $user_id) {
        if (!self::is_active()) {
            return 'not_started';
        }

        if (function_exists('learndash_course_completed') && learndash_course_completed($user_id, $course_id)) {
            return 'completed';
        }

        $progress = self::get_course_progress($course_id, $user_id);

        if ($progress >= 100) {
            return 'completed';
        }

        if ($progress > 0) {
            return 'in_progress';
        }

        return 'not_started';
    }

    /**
     * Get user's overall LMS progress
     */
    public static function get_user_progress($user_id) {
        $courses = self::get_enrolled_courses($user_id);

        if (empty($courses)) {
            return array(
                'courses' => array(),
                'total_courses' => 0,
                'completed_courses' => 0,
                'overall_percent' => 0
            );
        }

        $total = count($courses);
        $completed = 0;
        $total_progress = 0;

        foreach ($courses as $course) {
            $total_progress += $course['progress'];
            if ($course['status'] === 'completed') {
                $completed++;
            }
        }

        $overall = $total > 0 ? round($total_progress / $total) : 0;

        return array(
            'courses' => $courses,
            'total_courses' => $total,
            'completed_courses' => $completed,
            'overall_percent' => $overall
        );
    }

    /**
     * Get LMS dashboard URL
     */
    public static function get_dashboard_url() {
        $configured_lms_url = trim((string) get_option('vics_lms_page_url', ''));
        if (!empty($configured_lms_url)) {
            return $configured_lms_url;
        }

        return home_url('/courses');
    }
}
