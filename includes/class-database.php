<?php
/**
 * Database Handler
 * 
 * @package VisionImpactCustomSolutions
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class VICS_Database {
    
    /**
     * Create all database tables
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Orientation Progress Table
        self::create_orientation_table($charset_collate);
        
        // Agent Profile Table
        self::create_profile_table($charset_collate);
        
        // License Table
        self::create_license_table($charset_collate);
        
        // Run migrations for existing tables
        self::migrate_tables();
    }
    
    /**
     * Migrate existing tables - add missing columns
     */
    public static function migrate_tables() {
        global $wpdb;
        $profile_table = $wpdb->prefix . 'vics_agent_profile';
        
        // Check if agent_code_status column exists
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_SCHEMA = %s 
             AND TABLE_NAME = %s 
             AND COLUMN_NAME = 'agent_code_status'",
            DB_NAME,
            $profile_table
        ));
        
        // Add agent_code_status column if it doesn't exist
        if (empty($column_exists)) {
            $wpdb->query(
                "ALTER TABLE $profile_table 
                 ADD COLUMN agent_code_status enum('approved','pending','rejected') DEFAULT 'approved' 
                 AFTER agent_code"
            );
        }
        
        // Add new "About You" fields
        $new_fields = array(
            'favorite_things' => "ADD COLUMN favorite_things text DEFAULT NULL AFTER goals_for_year",
            'unknown_fact' => "ADD COLUMN unknown_fact text DEFAULT NULL AFTER favorite_things",
            'support_needed' => "ADD COLUMN support_needed text DEFAULT NULL AFTER unknown_fact",
            'feedback_preference' => "ADD COLUMN feedback_preference text DEFAULT NULL AFTER support_needed"
        );
        
        foreach ($new_fields as $field_name => $alter_statement) {
            $field_exists = $wpdb->get_results($wpdb->prepare(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = %s 
                 AND TABLE_NAME = %s 
                 AND COLUMN_NAME = %s",
                DB_NAME,
                $profile_table,
                $field_name
            ));
            
            if (empty($field_exists)) {
                $wpdb->query("ALTER TABLE $profile_table $alter_statement");
            }
        }
    }
    
    /**
     * Orientation Progress Table
     */
    private static function create_orientation_table($charset_collate) {
        global $wpdb;
        $table = $wpdb->prefix . 'vics_orientation_progress';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            video_timestamp float DEFAULT 0,
            video_duration float DEFAULT 0,
            terms_accepted tinyint(1) DEFAULT 0,
            form_submitted tinyint(1) DEFAULT 0,
            video_completed tinyint(1) DEFAULT 0,
            orientation_completed tinyint(1) DEFAULT 0,
            completed_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Agent Profile Table
     */
    private static function create_profile_table($charset_collate) {
        global $wpdb;
        $table = $wpdb->prefix . 'vics_agent_profile';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            phone varchar(20) DEFAULT NULL,
            agent_code varchar(100) DEFAULT NULL,
            agent_code_status enum('approved','pending','rejected') DEFAULT 'approved',
            npn varchar(100) DEFAULT NULL,
            license_number varchar(100) DEFAULT NULL,
            date_of_birth date DEFAULT NULL,
            city varchar(100) DEFAULT NULL,
            state varchar(50) DEFAULT NULL,
            goals_for_year text DEFAULT NULL,
            experience_level varchar(100) DEFAULT NULL,
            facebook_url varchar(255) DEFAULT NULL,
            instagram_url varchar(255) DEFAULT NULL,
            twitter_url varchar(255) DEFAULT NULL,
            tiktok_url varchar(255) DEFAULT NULL,
            youtube_url varchar(255) DEFAULT NULL,
            linkedin_url varchar(255) DEFAULT NULL,
            profile_photo_id bigint(20) DEFAULT NULL,
            google_sheet_id varchar(255) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * License Table
     */
    private static function create_license_table($charset_collate) {
        global $wpdb;
        $table = $wpdb->prefix . 'vics_agent_licenses';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            license_type varchar(100) NOT NULL,
            license_state varchar(50) NOT NULL,
            license_number varchar(100) DEFAULT NULL,
            issue_date date DEFAULT NULL,
            expiry_date date DEFAULT NULL,
            status enum('active','pending','expired','rejected') DEFAULT 'pending',
            notes text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY status (status)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    // ================== ORIENTATION METHODS ==================
    
    /**
     * Check if orientation completed
     */
    public static function has_completed_orientation($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'vics_orientation_progress';
        
        $completed = $wpdb->get_var($wpdb->prepare(
            "SELECT orientation_completed FROM $table WHERE user_id = %d",
            $user_id
        ));
        
        return (bool) $completed;
    }
    
    /**
     * Get orientation progress
     */
    public static function get_orientation_progress($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'vics_orientation_progress';
        
        $progress = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d",
            $user_id
        ), ARRAY_A);
        
        if (!$progress) {
            // Create initial record
            $wpdb->insert($table, array(
                'user_id' => $user_id,
                'video_timestamp' => 0,
                'created_at' => current_time('mysql')
            ));
            
            return array(
                'user_id' => $user_id,
                'video_timestamp' => 0,
                'video_duration' => 0,
                'terms_accepted' => 0,
                'form_submitted' => 0,
                'video_completed' => 0,
                'orientation_completed' => 0
            );
        }
        
        return $progress;
    }
    
    /**
     * Update orientation progress
     */
    public static function update_orientation_progress($user_id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'vics_orientation_progress';
        
        $data['updated_at'] = current_time('mysql');
        
        return $wpdb->update($table, $data, array('user_id' => $user_id));
    }
    
    // ================== PROFILE METHODS ==================
    
    /**
     * Get agent profile
     */
    public static function get_profile($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'vics_agent_profile';
        
        $profile = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d",
            $user_id
        ), ARRAY_A);
        
        if (!$profile) {
            // Create empty profile
            $wpdb->insert($table, array(
                'user_id' => $user_id,
                'created_at' => current_time('mysql')
            ));
            
            return array(
                'user_id' => $user_id,
                'phone' => '',
                'agent_code' => '',
                'npn' => '',
                'license_number' => '',
                'date_of_birth' => '',
                'city' => '',
                'state' => '',
                'goals_for_year' => '',
                'facebook_url' => '',
                'instagram_url' => '',
                'twitter_url' => '',
                'tiktok_url' => '',
                'youtube_url' => '',
                'linkedin_url' => '',
                'profile_photo_id' => null,
                'favorite_things' => '',
                'unknown_fact' => '',
                'support_needed' => '',
                'feedback_preference' => ''
            );
        }
        
        return $profile;
    }
    
    /**
     * Update agent profile
     */
    public static function update_profile($user_id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'vics_agent_profile';
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE user_id = %d",
            $user_id
        ));
        
        $data['updated_at'] = current_time('mysql');
        
        // Filter out any fields that don't exist in the table
        $allowed_columns = $wpdb->get_col("DESCRIBE $table", 0);
        $filtered_data = array_intersect_key($data, array_flip($allowed_columns));
        
        if ($exists) {
            // Only update the fields that are provided and exist in the table
            return $wpdb->update($table, $filtered_data, array('user_id' => $user_id));
        } else {
            // For new profiles, include all default fields
            $filtered_data['user_id'] = $user_id;
            $filtered_data['created_at'] = current_time('mysql');
            return $wpdb->insert($table, $filtered_data);
        }
    }
    
    // ================== LICENSE METHODS ==================
    
    /**
     * Get all licenses for user
     */
    public static function get_licenses($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'vics_agent_licenses';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d ORDER BY created_at DESC",
            $user_id
        ), ARRAY_A);
    }
    
    /**
     * Get single license
     */
    public static function get_license($license_id, $user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'vics_agent_licenses';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND user_id = %d",
            $license_id,
            $user_id
        ), ARRAY_A);
    }
    
    /**
     * Add license
     */
    public static function add_license($user_id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'vics_agent_licenses';
        
        $data['user_id'] = $user_id;
        $data['created_at'] = current_time('mysql');
        
        $wpdb->insert($table, $data);
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update license
     * If $user_id is 0, admin can update any license (skip user_id check)
     */
    public static function update_license($license_id, $user_id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'vics_agent_licenses';
        
        $data['updated_at'] = current_time('mysql');
        
        // Admin override: if user_id is 0, update without user check
        if ($user_id === 0) {
            return $wpdb->update(
                $table,
                $data,
                array('id' => $license_id)
            );
        }
        
        return $wpdb->update(
            $table,
            $data,
            array('id' => $license_id, 'user_id' => $user_id)
        );
    }
    
    /**
     * Delete license
     * If $user_id is 0, admin can delete any license (skip user_id check)
     */
    public static function delete_license($license_id, $user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'vics_agent_licenses';
        
        // Admin override: if user_id is 0, delete without user check
        if ($user_id === 0) {
            return $wpdb->delete($table, array('id' => $license_id));
        }
        
        return $wpdb->delete($table, array(
            'id' => $license_id,
            'user_id' => $user_id
        ));
    }
    
    /**
     * Get primary license status
     */
    public static function get_primary_license_status($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'vics_agent_licenses';
        
        // Get the most recent active license
        $license = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d ORDER BY 
             CASE WHEN status = 'active' THEN 0 
                  WHEN status = 'pending' THEN 1 
                  ELSE 2 END, 
             created_at DESC LIMIT 1",
            $user_id
        ), ARRAY_A);
        
        return $license;
    }
}