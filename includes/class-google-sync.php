<?php
/**
 * Google Sync Handler
 * 
 * Each agent gets a personal clone of the master sheet (template only, no data sync).
 * Birthday reminders are calculated from WordPress database.
 *
 * @package VisionImpactCustomSolutions
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class VICS_Google_Sync {

    /**
     * Google Sheets instance
     * @var VICS_Google_Sheets
     */
    private $sheets;

    /**
     * Constructor
     */
    public function __construct() {
        $this->sheets = new VICS_Google_Sheets();
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Hook into user registration (for new agent sheet creation)
        add_action('user_register', array($this, 'create_agent_sheet'), 10, 1);

        // Hook into admin notices for birthday reminders
        add_action('admin_notices', array($this, 'birthday_reminders'));

        // Daily cron for birthday checks
        if (!wp_next_scheduled('vics_daily_birthday_check')) {
            wp_schedule_event(time(), 'daily', 'vics_daily_birthday_check');
        }
        add_action('vics_daily_birthday_check', array($this, 'process_birthday_reminders'));
    }

    /**
     * Create Google Sheet for new agent
     * 
     * Creates a clone of the master sheet and shares it with the agent.
     * NO data is synced - this is just an empty template clone.
     *
     * @param int $user_id
     * @return bool
     */
    public function create_agent_sheet($user_id) {
        vics_log('DEBUG: create_agent_sheet triggered for user_id=' . $user_id);
        
        // Only create sheets for agents
        $user = get_userdata($user_id);
        if (!$user) {
            vics_log('DEBUG: User not found for ID ' . $user_id, 'error');
            return false;
        }
        
        if (!in_array('agent', $user->roles)) {
            vics_log('DEBUG: User ' . $user_id . ' is not an agent. Roles: ' . implode(', ', $user->roles), 'info');
            return false;
        }
        
        vics_log('DEBUG: User ' . $user_id . ' is an agent. Proceeding with sheet creation.');

        $master_sheet_id = get_option('vics_master_sheet_id');
        vics_log('DEBUG: Master sheet ID from options: ' . ($master_sheet_id ? $master_sheet_id : 'NOT SET'));
        
        if (!$master_sheet_id) {
            vics_log('Create Agent Sheet Error: Master sheet ID not configured in admin settings', 'error');
            return false;
        }
        
        if (!$this->sheets->is_available()) {
            vics_log('Create Agent Sheet Error: Google Sheets service not available', 'error');
            return false;
        }

        // Validate that the master sheet exists and is accessible
        try {
            vics_log('DEBUG: Validating master sheet accessibility...');
            $this->sheets->get_sheets_service()->spreadsheets->get($master_sheet_id);
            vics_log('DEBUG: Master sheet validation successful');
        } catch (Exception $e) {
            vics_log('Create Agent Sheet Error: Master sheet not found or not accessible: ' . $e->getMessage(), 'error');
            return false;
        }

        // Create sheet name from user info
        $first_name = get_user_meta($user_id, 'first_name', true);
        $last_name = get_user_meta($user_id, 'last_name', true);
        $sheet_name = trim($first_name . ' ' . $last_name);
        if (empty($sheet_name)) {
            $sheet_name = $user->user_login;
        }
        $sheet_name = 'Agent - ' . $sheet_name;

        vics_log('Create Agent Sheet: Creating sheet "' . $sheet_name . '" for user ' . $user_id . ' (email: ' . $user->user_email . ')');

        // Create the sheet and share it with the agent (as empty template clone)
        $new_sheet_id = $this->sheets->create_sheet_from_master($master_sheet_id, $sheet_name, $user->user_email);

        if ($new_sheet_id) {
            vics_log('DEBUG: Sheet created successfully with ID: ' . $new_sheet_id . '. Storing in database...');
            
            // Store the sheet ID in user profile
            $db_result = VICS_Database::update_profile($user_id, array(
                'google_sheet_id' => $new_sheet_id
            ));
            
            vics_log('DEBUG: Database update result: ' . ($db_result ? 'SUCCESS' : 'FAILED'));
            vics_log('Create Agent Sheet Success: Sheet created with ID: ' . $new_sheet_id . ' (template only, no data synced)');
            return true;
        } else {
            vics_log('Create Agent Sheet Error: create_sheet_from_master() returned null for user ' . $user_id, 'error');
            return false;
        }
    }

    /**
     * Display birthday reminders in admin
     */
    public function birthday_reminders() {
        global $wpdb;
        
        // Use WordPress timezone
        $now = new DateTime('now', new DateTimeZone(wp_timezone_string()));
        $current_year = (int)$now->format('Y');
        
        // Get agents with birthdays in the next 7 days - avoid date comparison to prevent MySQL errors
        $upcoming_agents = $wpdb->get_results($wpdb->prepare(
            "SELECT u.display_name, p.date_of_birth
             FROM {$wpdb->users} u
             INNER JOIN {$wpdb->prefix}vics_agent_profile p ON u.ID = p.user_id
             WHERE p.date_of_birth IS NOT NULL
             AND LENGTH(p.date_of_birth) > 0
             AND u.ID IN (
                 SELECT user_id FROM {$wpdb->usermeta}
                 WHERE meta_key = %s
                 AND meta_value LIKE %s
             )",
            $wpdb->prefix . 'capabilities',
            '%"agent"%'
        ), ARRAY_A);

        // Filter and calculate days until birthday
        $birthdays_to_show = array();
        
        foreach ($upcoming_agents as $agent) {
            if (empty($agent['date_of_birth'])) {
                continue;
            }
            
            $dob_raw = trim($agent['date_of_birth']);
            
            // Skip invalid dates
            if ($dob_raw === '' || $dob_raw === '0000-00-00' || strlen($dob_raw) < 8) {
                continue;
            }
            
            // Parse the date
            $dob_obj = null;
            $formats = ['Y-m-d', 'Y-m-d H:i:s', 'Y/m/d', 'm/d/Y', 'd/m/Y'];
            foreach ($formats as $format) {
                $temp = DateTime::createFromFormat($format, $dob_raw);
                if ($temp && $temp->format($format) === $dob_raw) {
                    $dob_obj = $temp;
                    break;
                }
            }
            
            // Fallback to strtotime
            if (!$dob_obj) {
                $timestamp = strtotime($dob_raw);
                if ($timestamp !== false && $timestamp > 0) {
                    $dob_obj = new DateTime('@' . $timestamp);
                }
            }
            
            if (!$dob_obj) {
                continue;
            }
            
            // Get birth month and day
            $birth_month = $dob_obj->format('m');
            $birth_day = $dob_obj->format('d');
            
            // Create this year's birthday
            $birthday_this_year_str = sprintf('%04d-%02d-%02d', $current_year, (int)$birth_month, (int)$birth_day);
            $birthday_this_year = DateTime::createFromFormat('Y-m-d', $birthday_this_year_str, new DateTimeZone(wp_timezone_string()));
            
            // If birthday already passed, use next year
            if ($birthday_this_year < $now) {
                $birthday_this_year_str = sprintf('%04d-%02d-%02d', $current_year + 1, (int)$birth_month, (int)$birth_day);
                $birthday_this_year = DateTime::createFromFormat('Y-m-d', $birthday_this_year_str, new DateTimeZone(wp_timezone_string()));
            }
            
            // Calculate days until birthday
            $interval = $now->diff($birthday_this_year);
            $days_until = (int)$interval->days;
            
            // Show birthdays in next 7 days
            if ($days_until >= 0 && $days_until <= 7) {
                $birthdays_to_show[] = array(
                    'name' => $agent['display_name'],
                    'date' => $birthday_this_year->format('F j, Y'),
                    'days' => $days_until
                );
            }
        }

        if (!empty($birthdays_to_show)) {
            // Sort by days remaining
            usort($birthdays_to_show, function($a, $b) {
                return $a['days'] - $b['days'];
            });
            
            echo '<div class="notice notice-info is-dismissible">';
            echo '<h3>' . __('Upcoming Agent Birthdays', 'vics') . '</h3>';
            echo '<ul style="margin: 10px 0;">';
            foreach ($birthdays_to_show as $bday) {
                $days_text = $bday['days'] == 0 ? 'Today!' : ($bday['days'] == 1 ? 'Tomorrow' : 'in ' . $bday['days'] . ' days');
                echo '<li><strong>' . esc_html($bday['name']) . '</strong> - ' . esc_html($bday['date']) . ' <em>(' . $days_text . ')</em></li>';
            }
            echo '</ul>';
            echo '</div>';
        }
    }

    /**
     * Process daily birthday reminders - sends email 2 days before agent birthdays
     */
    public function process_birthday_reminders() {
        global $wpdb;
        
        // Get all agents with birthdays - avoid date comparison to prevent MySQL errors
        $agents = $wpdb->get_results($wpdb->prepare(
            "SELECT u.display_name, u.user_email, p.date_of_birth
             FROM {$wpdb->users} u
             INNER JOIN {$wpdb->prefix}vics_agent_profile p ON u.ID = p.user_id
             WHERE p.date_of_birth IS NOT NULL
             AND LENGTH(p.date_of_birth) > 0
             AND u.ID IN (
                 SELECT user_id FROM {$wpdb->usermeta}
                 WHERE meta_key = %s
                 AND meta_value LIKE %s
             )",
            $wpdb->prefix . 'capabilities',
            '%"agent"%'
        ), ARRAY_A);

        // Use WordPress timezone
        $now = new DateTime('now', new DateTimeZone(wp_timezone_string()));
        $current_year = (int)$now->format('Y');
        $check_date = new DateTime('+2 days', new DateTimeZone(wp_timezone_string()));
        
        $birthdays_in_2_days = array();
        
        foreach ($agents as $agent) {
            if (empty($agent['date_of_birth'])) {
                continue;
            }
            
            $dob_raw = trim($agent['date_of_birth']);
            
            // Skip invalid dates
            if ($dob_raw === '' || $dob_raw === '0000-00-00' || strlen($dob_raw) < 8) {
                continue;
            }
            
            // Parse the date
            $dob_obj = null;
            $formats = ['Y-m-d', 'Y-m-d H:i:s', 'Y/m/d', 'm/d/Y', 'd/m/Y'];
            foreach ($formats as $format) {
                $temp = DateTime::createFromFormat($format, $dob_raw);
                if ($temp && $temp->format($format) === $dob_raw) {
                    $dob_obj = $temp;
                    break;
                }
            }
            
            if (!$dob_obj) {
                $timestamp = strtotime($dob_raw);
                if ($timestamp !== false && $timestamp > 0) {
                    $dob_obj = new DateTime('@' . $timestamp);
                }
            }
            
            if (!$dob_obj) {
                continue;
            }
            
            // Get birth month and day
            $birth_month = $dob_obj->format('m');
            $birth_day = $dob_obj->format('d');
            
            // Create this year's birthday
            $birthday_this_year_str = sprintf('%04d-%02d-%02d', $current_year, (int)$birth_month, (int)$birth_day);
            $birthday_this_year = DateTime::createFromFormat('Y-m-d', $birthday_this_year_str, new DateTimeZone(wp_timezone_string()));
            
            // If birthday already passed, use next year
            if ($birthday_this_year < $now) {
                $birthday_this_year_str = sprintf('%04d-%02d-%02d', $current_year + 1, (int)$birth_month, (int)$birth_day);
                $birthday_this_year = DateTime::createFromFormat('Y-m-d', $birthday_this_year_str, new DateTimeZone(wp_timezone_string()));
            }
            
            // Check if birthday is in exactly 2 days
            $interval = $now->diff($birthday_this_year);
            $days_until = (int)$interval->days;
            
            if ($days_until === 2) {
                $birthdays_in_2_days[] = array(
                    'name' => $agent['display_name'],
                    'email' => $agent['user_email'],
                    'birthday_date' => $birthday_this_year->format('F j, Y')
                );
            }
        }

        // Send email to admin if there are birthdays in 2 days
        if (!empty($birthdays_in_2_days)) {
            $admin_email = get_option('admin_email');
            $site_name = get_bloginfo('name');
            
            $subject = sprintf('[%s] Agent Birthday Reminder - 2 Days', $site_name);
            
            $message = "Hello,\n\n";
            $message .= "The following agent(s) have birthdays coming up in 2 days:\n\n";
            
            foreach ($birthdays_in_2_days as $birthday) {
                $message .= sprintf("• %s - Birthday: %s\n", $birthday['name'], $birthday['birthday_date']);
            }
            
            $message .= "\n---\n";
            $message .= "This is an automated reminder from " . $site_name . "\n";
            $message .= "Login to view all upcoming birthdays: " . admin_url('admin.php?page=vics-settings&tab=birthdays');
            
            $headers = array('Content-Type: text/plain; charset=UTF-8');
            
            wp_mail($admin_email, $subject, $message, $headers);
            
            // Log the email send
            if (get_option('vics_enable_debug_logging')) {
                vics_log('Birthday reminder email sent to ' . $admin_email . ' for ' . count($birthdays_in_2_days) . ' agent(s)');
            }
        }
    }

    /**
     * Get upcoming birthdays from database
     * Used for admin display in settings page
     *
     * @return array
     */
    public function get_upcoming_birthdays_from_db() {
        global $wpdb;
        
        $upcoming_birthdays = array();
        
        // Use WordPress timezone
        $now = new DateTime('now', new DateTimeZone(wp_timezone_string()));
        $current_year = (int)$now->format('Y');
        
        // Get all agents with birthdays - avoid date comparison to prevent MySQL errors
        $agents = $wpdb->get_results($wpdb->prepare(
            "SELECT u.ID, u.display_name, u.user_email, p.date_of_birth
             FROM {$wpdb->users} u
             INNER JOIN {$wpdb->prefix}vics_agent_profile p ON u.ID = p.user_id
             WHERE p.date_of_birth IS NOT NULL
             AND LENGTH(p.date_of_birth) > 0
             AND u.ID IN (
                 SELECT user_id FROM {$wpdb->usermeta}
                 WHERE meta_key = %s
                 AND meta_value LIKE %s
             )",
            $wpdb->prefix . 'capabilities',
            '%"agent"%'
        ), ARRAY_A);

        foreach ($agents as $agent) {
            if (empty($agent['date_of_birth'])) {
                continue;
            }
            
            $dob_raw = trim($agent['date_of_birth']);
            
            // Skip invalid dates
            if ($dob_raw === '' || $dob_raw === '0000-00-00' || strlen($dob_raw) < 8) {
                continue;
            }
            
            // Parse the date
            $dob_obj = null;
            $formats = ['Y-m-d', 'Y-m-d H:i:s', 'Y/m/d', 'm/d/Y', 'd/m/Y'];
            foreach ($formats as $format) {
                $temp = DateTime::createFromFormat($format, $dob_raw);
                if ($temp && $temp->format($format) === $dob_raw) {
                    $dob_obj = $temp;
                    break;
                }
            }
            
            // Fallback to strtotime
            if (!$dob_obj) {
                $timestamp = strtotime($dob_raw);
                if ($timestamp !== false && $timestamp > 0) {
                    $dob_obj = new DateTime('@' . $timestamp);
                }
            }
            
            if (!$dob_obj) {
                continue;
            }
            
            // Get birth month and day
            $birth_month = $dob_obj->format('m');
            $birth_day = $dob_obj->format('d');
            
            // Create this year's birthday
            $birthday_this_year_str = sprintf('%04d-%02d-%02d', $current_year, (int)$birth_month, (int)$birth_day);
            $birthday_this_year = DateTime::createFromFormat('Y-m-d', $birthday_this_year_str, new DateTimeZone(wp_timezone_string()));
            
            // If birthday already passed, use next year
            if ($birthday_this_year < $now) {
                $birthday_this_year_str = sprintf('%04d-%02d-%02d', $current_year + 1, (int)$birth_month, (int)$birth_day);
                $birthday_this_year = DateTime::createFromFormat('Y-m-d', $birthday_this_year_str, new DateTimeZone(wp_timezone_string()));
            }
            
            // Calculate days until birthday
            $interval = $now->diff($birthday_this_year);
            $days_until = (int)$interval->days;
            
            // Only include birthdays in the next 30 days
            if ($days_until >= 0 && $days_until <= 30) {
                $upcoming_birthdays[] = array(
                    'name' => $agent['display_name'],
                    'email' => $agent['user_email'],
                    'birthday' => $dob_obj->format('F j'),
                    'birthday_full' => $birthday_this_year->format('F j, Y'),
                    'days_remaining' => $days_until
                );
            }
        }
        
        // Sort by days remaining
        usort($upcoming_birthdays, function($a, $b) {
            return $a['days_remaining'] - $b['days_remaining'];
        });
        
        return $upcoming_birthdays;
    }
}