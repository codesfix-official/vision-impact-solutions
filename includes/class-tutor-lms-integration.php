<?php
/**
 * Tutor LMS Integration
 * 
 * @package VisionImpactCustomSolutions
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class VICS_Tutor_LMS_Integration {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Check if Tutor LMS is active
        add_action('admin_notices', array($this, 'check_tutor_lms'));
    }
    
    /**
     * Check if Tutor LMS is active
     */
    public function check_tutor_lms() {
        if (!function_exists('tutor') && current_user_can('manage_options')) {
            // Only show on our plugin pages
            $screen = get_current_screen();
            if ($screen && strpos($screen->id, 'vics') !== false) {
                echo '<div class="notice notice-warning"><p>';
                echo __('Vision Impact Custom Solutions: Tutor LMS plugin is not active. LMS features will be limited.', 'vics');
                echo '</p></div>';
            }
        }
    }
    
    /**
     * Check if Tutor LMS is active
     */
    public static function is_active() {
        return function_exists('tutor') && function_exists('tutor_utils');
    }
    
    /**
     * Get user's enrolled courses
     */
    public static function get_enrolled_courses($user_id) {
        if (!self::is_active()) {
            return array();
        }
        
        $courses = tutor_utils()->get_enrolled_courses_by_user($user_id);
        
        if (!$courses || !$courses->have_posts()) {
            return array();
        }
        
        $enrolled = array();
        
        while ($courses->have_posts()) {
            $courses->the_post();
            $course_id = get_the_ID();
            
            $enrolled[] = array(
                'id' => $course_id,
                'title' => get_the_title(),
                'permalink' => get_permalink(),
                'progress' => self::get_course_progress($course_id, $user_id),
                'status' => self::get_course_status($course_id, $user_id)
            );
        }
        
        wp_reset_postdata();
        
        return $enrolled;
    }
    
    /**
     * Get course progress percentage
     */
    public static function get_course_progress($course_id, $user_id) {
        if (!self::is_active()) {
            return 0;
        }
        
        $completed = tutor_utils()->get_course_completed_percent($course_id, $user_id);
        
        return intval($completed);
    }
    
    /**
     * Get course status
     */
    public static function get_course_status($course_id, $user_id) {
        if (!self::is_active()) {
            return 'not_started';
        }
        
        $progress = self::get_course_progress($course_id, $user_id);
        
        if ($progress >= 100) {
            return 'completed';
        } elseif ($progress > 0) {
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
        if (!self::is_active()) {
            return home_url('/courses');
        }
        
        $dashboard_page = tutor_utils()->get_option('tutor_dashboard_page_id');
        
        if ($dashboard_page) {
            return get_permalink($dashboard_page);
        }
        
        return home_url('/dashboard');
    }
}