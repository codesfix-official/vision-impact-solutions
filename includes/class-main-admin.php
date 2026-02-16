<?php
/**
 * Admin Settings
 * 
 * @package VisionImpactCustomSolutions
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class VICS_Admin_Settings {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        
        // Users list columns
        add_filter('manage_users_columns', array($this, 'add_user_columns'));
        add_filter('manage_users_custom_column', array($this, 'user_column_content'), 10, 3);
        
        // Reset orientation action
        add_filter('user_row_actions', array($this, 'add_user_actions'), 10, 2);
        add_action('admin_init', array($this, 'handle_reset_orientation'));
        
        // License management
        add_action('admin_init', array($this, 'handle_license_status_update'));
        add_action('wp_ajax_vics_update_license_status', array($this, 'handle_ajax_license_status_update'));
        add_action('wp_ajax_vics_admin_get_license', array($this, 'admin_get_license'));
        add_action('wp_ajax_vics_admin_add_license', array($this, 'admin_add_license'));
        add_action('wp_ajax_vics_admin_update_license', array($this, 'admin_update_license'));
        add_action('wp_ajax_vics_admin_delete_license', array($this, 'admin_delete_license'));
        add_action('wp_ajax_vics_approve_agent_code', array($this, 'approve_agent_code'));
        add_action('wp_ajax_vics_reject_agent_code', array($this, 'reject_agent_code'));
        
        // Google authentication and sync
        add_action('admin_init', array($this, 'handle_google_auth'));
        add_action('admin_init', array($this, 'handle_google_sync_actions'));
        
        // Debug log management
        add_action('admin_init', array($this, 'handle_debug_log_actions'));
    }
    
    /**
     * Add Admin Menu
     */
    public function add_admin_menu() {
        // Main Menu
        add_menu_page(
            __('Vision Impact', 'vics'),
            __('Vision Impact', 'vics'),
            'manage_options',
            'vics-settings',
            array($this, 'render_settings_page'),
            'dashicons-groups',
            30
        );
        
        // Orientation Settings
        add_submenu_page(
            'vics-settings',
            __('Orientation Settings', 'vics'),
            __('Orientation', 'vics'),
            'manage_options',
            'vics-settings',
            array($this, 'render_settings_page')
        );
        
        // Agents List
        add_submenu_page(
            'vics-settings',
            __('All Agents', 'vics'),
            __('All Agents', 'vics'),
            'manage_options',
            'vics-agents',
            array($this, 'render_agents_page')
        );
        
        // Orientation Report
        add_submenu_page(
            'vics-settings',
            __('Orientation Report', 'vics'),
            __('Orientation Report', 'vics'),
            'manage_options',
            'vics-orientation-report',
            array($this, 'render_orientation_report')
        );
        
        // License Management
        add_submenu_page(
            'vics-settings',
            __('License Management', 'vics'),
            __('License Management', 'vics'),
            'manage_options',
            'vics-license-management',
            array($this, 'render_license_management')
        );
    }
    
    /**
     * Register Settings
     */
    public function register_settings() {
        // Orientation settings
        register_setting('vics_orientation_settings', 'vics_video_url');
        register_setting('vics_orientation_settings', 'vics_popup_header');
        register_setting('vics_orientation_settings', 'vics_popup_description');
        register_setting('vics_orientation_settings', 'vics_checkbox_text');
        register_setting('vics_orientation_settings', 'vics_button_text');
        register_setting('vics_orientation_settings', 'vics_welcome_message');
        register_setting('vics_orientation_settings', 'vics_video_completion_threshold');
        register_setting('vics_orientation_settings', 'vics_playbook_url');
        register_setting('vics_orientation_settings', 'vics_list_items', array(
            'type' => 'array',
            'sanitize_callback' => array($this, 'sanitize_list_items')
        ));
        
        // Profile settings
        register_setting('vics_profile_settings', 'vics_lms_page_url');
        register_setting('vics_profile_settings', 'vics_production_tracker_url');
        
        // Google settings
        register_setting('vics_google_settings', 'vics_google_client_id');
        register_setting('vics_google_settings', 'vics_google_client_secret');
        register_setting('vics_google_settings', 'vics_master_sheet_id');
        
        // Debug settings
        register_setting('vics_debug_settings', 'vics_enable_debug_logging');
    }
    
    /**
     * Sanitize list items
     */
    public function sanitize_list_items($items) {
        if (!is_array($items)) {
            return array();
        }
        
        $sanitized = array();
        foreach ($items as $item) {
            $clean = sanitize_text_field($item);
            if (!empty($clean)) {
                $sanitized[] = $clean;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Render Settings Page
     */
    public function render_settings_page() {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'orientation';
        $list_items = get_option('vics_list_items', array());
        ?>
        <div class="wrap vics-admin-wrap">
            <h1><?php _e('Vision To Impact', 'vics'); ?></h1>
            
            <nav class="nav-tab-wrapper">
                <a href="?page=vics-settings&tab=orientation" 
                   class="nav-tab <?php echo $active_tab === 'orientation' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Orientation', 'vics'); ?>
                </a>
                <a href="?page=vics-settings&tab=profile" 
                   class="nav-tab <?php echo $active_tab === 'profile' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Profile', 'vics'); ?>
                </a>
                <a href="?page=vics-settings&tab=google" 
                   class="nav-tab <?php echo $active_tab === 'google' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Google Sheets', 'vics'); ?>
                </a>
                <a href="?page=vics-settings&tab=birthdays" 
                   class="nav-tab <?php echo $active_tab === 'birthdays' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Upcoming Birthdays', 'vics'); ?>
                </a>
                <a href="?page=vics-settings&tab=debug" 
                   class="nav-tab <?php echo $active_tab === 'debug' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Debug', 'vics'); ?>
                </a>
            </nav>
            
            <?php
            // Google authentication notices
            if ($active_tab === 'google') {
                if (isset($_GET['google_auth']) && $_GET['google_auth'] === 'success') {
                    echo '<div class="notice notice-success is-dismissible"><p>' . __('Successfully connected to Google!', 'vics') . '</p></div>';
                } elseif (isset($_GET['google_auth']) && $_GET['google_auth'] === 'error') {
                    echo '<div class="notice notice-error is-dismissible"><p>' . __('Failed to connect to Google. Please check your credentials.', 'vics') . '</p></div>';
                } elseif (isset($_GET['revoked']) && $_GET['revoked'] === '1') {
                    echo '<div class="notice notice-success is-dismissible"><p>' . __('Successfully disconnected from Google.', 'vics') . '</p></div>';
                } elseif (isset($_GET['sync_master']) && $_GET['sync_master'] === 'success') {
                    echo '<div class="notice notice-success is-dismissible"><p>' . __('Master tracker synced successfully!', 'vics') . '</p></div>';
                } elseif (isset($_GET['sync_master']) && $_GET['sync_master'] === 'error') {
                    echo '<div class="notice notice-error is-dismissible"><p>' . __('Failed to sync master tracker. Please check your connection and sheet ID.', 'vics') . '</p></div>';                } elseif (isset($_GET['test_connection']) && $_GET['test_connection'] === 'success') {
                    echo '<div class="notice notice-success is-dismissible"><p>' . __('Google API connection test successful!', 'vics') . '</p></div>';
                } elseif (isset($_GET['test_connection']) && $_GET['test_connection'] === 'error') {
                    echo '<div class="notice notice-error is-dismissible"><p>' . __('Google API connection test failed. Please reconnect.', 'vics') . '</p></div>';
                }
            }
            ?>
            
            <?php if ($active_tab === 'orientation'): ?>
            <!-- Orientation Settings -->
            <form method="post" action="options.php">
                <?php settings_fields('vics_orientation_settings'); ?>
                
                <div class="vics-admin-section">
                    <h2><?php _e('Video Settings', 'vics'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th><label for="vics_video_url"><?php _e('Video URL', 'vics'); ?></label></th>
                            <td>
                                <input type="url" name="vics_video_url" id="vics_video_url" 
                                       value="<?php echo esc_attr(get_option('vics_video_url')); ?>" 
                                       class="large-text" placeholder="https://youtu.be/xxxxx" />
                                <p class="description">
                                    <?php _e('Supported: YouTube, Vimeo, or direct MP4 URL', 'vics'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="vics_video_completion_threshold"><?php _e('Completion Threshold (%)', 'vics'); ?></label></th>
                            <td>
                                <input type="number" name="vics_video_completion_threshold" 
                                       id="vics_video_completion_threshold" 
                                       value="<?php echo esc_attr(get_option('vics_video_completion_threshold', 95)); ?>" 
                                       min="50" max="100" class="small-text" />
                            </td>
                        </tr>
                        <tr>
                            <th><label for="vics_playbook_url"><?php _e('Playbook PDF URL', 'vics'); ?></label></th>
                            <td>
                                <input type="url" name="vics_playbook_url" id="vics_playbook_url" 
                                       value="<?php echo esc_attr(get_option('vics_playbook_url')); ?>" 
                                       class="large-text" placeholder="https://example.com/agent-playbook.pdf" />
                                <p class="description">
                                    <?php _e('External link to the Agent Playbook PDF. Leave empty to hide the download link.', 'vics'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="vics-admin-section">
                    <h2><?php _e('Popup Content', 'vics'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th><label for="vics_popup_header"><?php _e('Header Text', 'vics'); ?></label></th>
                            <td>
                                <input type="text" name="vics_popup_header" id="vics_popup_header" 
                                       value="<?php echo esc_attr(get_option('vics_popup_header')); ?>" 
                                       class="large-text" />
                            </td>
                        </tr>
                        <tr>
                            <th><label for="vics_popup_description"><?php _e('Description', 'vics'); ?></label></th>
                            <td>
                                <textarea name="vics_popup_description" id="vics_popup_description" 
                                          rows="3" class="large-text"><?php 
                                    echo esc_textarea(get_option('vics_popup_description')); 
                                ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th><label><?php _e('Checklist Items', 'vics'); ?></label></th>
                            <td>
                                <div id="vics-list-items-container">
                                    <?php if (!empty($list_items)): ?>
                                        <?php foreach ($list_items as $item): ?>
                                            <div class="vics-list-item-row">
                                                <span class="vics-drag-handle dashicons dashicons-menu"></span>
                                                <span class="vics-item-icon">✓✓</span>
                                                <input type="text" name="vics_list_items[]" 
                                                       value="<?php echo esc_attr($item); ?>" 
                                                       class="regular-text" />
                                                <button type="button" class="button vics-remove-item">
                                                    <span class="dashicons dashicons-trash"></span>
                                                </button>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="vics-list-item-row">
                                            <span class="vics-drag-handle dashicons dashicons-menu"></span>
                                            <span class="vics-item-icon">✓✓</span>
                                            <input type="text" name="vics_list_items[]" value="" class="regular-text" />
                                            <button type="button" class="button vics-remove-item">
                                                <span class="dashicons dashicons-trash"></span>
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <button type="button" id="vics-add-list-item" class="button">
                                    <span class="dashicons dashicons-plus-alt2"></span> <?php _e('Add Item', 'vics'); ?>
                                </button>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="vics_checkbox_text"><?php _e('Checkbox Text', 'vics'); ?></label></th>
                            <td>
                                <input type="text" name="vics_checkbox_text" id="vics_checkbox_text" 
                                       value="<?php echo esc_attr(get_option('vics_checkbox_text')); ?>" 
                                       class="large-text" />
                            </td>
                        </tr>
                        <tr>
                            <th><label for="vics_button_text"><?php _e('Button Text', 'vics'); ?></label></th>
                            <td>
                                <input type="text" name="vics_button_text" id="vics_button_text" 
                                       value="<?php echo esc_attr(get_option('vics_button_text')); ?>" 
                                       class="regular-text" />
                            </td>
                        </tr>
                        <tr>
                            <th><label for="vics_welcome_message"><?php _e('Welcome Message', 'vics'); ?></label></th>
                            <td>
                                <textarea name="vics_welcome_message" id="vics_welcome_message" 
                                          rows="3" class="large-text"><?php 
                                    echo esc_textarea(get_option('vics_welcome_message')); 
                                ?></textarea>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <?php submit_button(); ?>
            </form>
            
            <?php elseif ($active_tab === 'profile'): ?>
            <!-- Profile Settings -->
            <form method="post" action="options.php">
                <?php settings_fields('vics_profile_settings'); ?>
                
                <div class="vics-admin-section">
                    <h2><?php _e('Page URLs', 'vics'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th><label for="vics_lms_page_url"><?php _e('LMS Page URL', 'vics'); ?></label></th>
                            <td>
                                <input type="url" name="vics_lms_page_url" id="vics_lms_page_url" 
                                       value="<?php echo esc_attr(get_option('vics_lms_page_url')); ?>" 
                                       class="regular-text" />
                                <p class="description"><?php _e('URL of your Tutor LMS dashboard', 'vics'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="vics_production_tracker_url"><?php _e('Production Tracker URL', 'vics'); ?></label></th>
                            <td>
                                <input type="url" name="vics_production_tracker_url" 
                                       id="vics_production_tracker_url" 
                                       value="<?php echo esc_attr(get_option('vics_production_tracker_url')); ?>" 
                                       class="regular-text" />
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="vics-admin-section">
                    <h2><?php _e('Quick Info', 'vics'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th><?php _e('Profile Page', 'vics'); ?></th>
                            <td>
                                <code><?php echo home_url('/my-profile'); ?></code>
                                <a href="<?php echo home_url('/my-profile'); ?>" target="_blank" class="button button-small">
                                    <?php _e('View', 'vics'); ?>
                                </a>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Shortcode', 'vics'); ?></th>
                            <td><code>[agent_profile]</code></td>
                        </tr>
                    </table>
                </div>
                
                <?php submit_button(); ?>
            </form>
            
            <?php elseif ($active_tab === 'google'): ?>
            <!-- Google Sheets Settings -->
            <div class="vics-admin-section">
                <h2><?php _e('Google Sheets API Integration', 'vics'); ?></h2>

                <?php
                $auth = new VICS_Google_Auth();
                $is_authenticated = $auth->is_authenticated();
                $authenticated_email = $auth->get_authenticated_email();
                $needs_reauth = $is_authenticated && !$authenticated_email;
                ?>

                <!-- Authentication Status -->
                <div class="google-auth-status" style="margin-bottom: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
                    <h3><?php _e('Authentication Status', 'vics'); ?></h3>
                    <?php if ($needs_reauth): ?>
                        <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; margin: 10px 0; border-radius: 4px;">
                            <strong><?php _e('Re-authentication Required', 'vics'); ?></strong><br>
                            <?php _e('Your Google authentication token needs to be updated with additional permissions. Please disconnect and reconnect to enable all features.', 'vics'); ?>
                        </div>
                        <p>
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=vics-settings&tab=google&action=revoke'), 'revoke_google_auth'); ?>"
                               class="button button-secondary">
                                <?php _e('Disconnect & Reconnect', 'vics'); ?>
                            </a>
                        </p>
                    <?php elseif ($is_authenticated): ?>
                        <p style="color: #28a745;">
                            <span class="dashicons dashicons-yes" style="font-size: 18px;"></span>
                            <?php _e('Connected to Google', 'vics'); ?>
                        </p>
                        <?php if ($authenticated_email): ?>
                            <p><strong><?php _e('Authenticated Account:', 'vics'); ?></strong> <?php echo esc_html($authenticated_email); ?></p>
                            <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; margin: 10px 0; border-radius: 4px;">
                                <strong><?php _e('Important:', 'vics'); ?></strong><br>
                                <?php _e('Users must share their personal Google Sheets with this email address for automatic sync to work.', 'vics'); ?><br>
                                <?php _e('Each user should: Open their sheet → Share → Add this email as Editor.', 'vics'); ?>
                            </div>
                        <?php endif; ?>
                        <p>
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=vics-settings&tab=google&action=revoke'), 'revoke_google_auth'); ?>"
                               class="button button-secondary">
                                <?php _e('Disconnect', 'vics'); ?>
                            </a>
                        </p>
                    <?php else: ?>
                        <p style="color: #dc3545;">
                            <span class="dashicons dashicons-no" style="font-size: 18px;"></span>
                            <?php _e('Not connected to Google', 'vics'); ?>
                        </p>
                        <p>
                            <a href="<?php echo esc_url($auth->get_auth_url()); ?>" class="button button-primary">
                                <?php _e('Connect to Google', 'vics'); ?>
                            </a>
                        </p>
                    <?php endif; ?>
                </div>

                <!-- Settings Form -->
                <form method="post" action="options.php">
                    <?php settings_fields('vics_google_settings'); ?>

                    <table class="form-table">
                        <tr>
                            <th><label for="vics_google_client_id"><?php _e('Client ID', 'vics'); ?></label></th>
                            <td>
                                <input type="text" name="vics_google_client_id" id="vics_google_client_id"
                                       value="<?php echo esc_attr(get_option('vics_google_client_id')); ?>"
                                       class="large-text" />
                                <p class="description">
                                    <?php _e('Get this from Google Cloud Console > APIs & Credentials > OAuth 2.0 Client IDs', 'vics'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="vics_google_client_secret"><?php _e('Client Secret', 'vics'); ?></label></th>
                            <td>
                                <input type="password" name="vics_google_client_secret"
                                       id="vics_google_client_secret"
                                       value="<?php echo esc_attr(get_option('vics_google_client_secret')); ?>"
                                       class="large-text" />
                                <p class="description">
                                    <?php _e('Get this from Google Cloud Console > APIs & Credentials > OAuth 2.0 Client IDs', 'vics'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="vics_master_sheet_id"><?php _e('Master Sheet ID', 'vics'); ?></label></th>
                            <td>
                                <input type="text" name="vics_master_sheet_id" id="vics_master_sheet_id"
                                       value="<?php echo esc_attr(get_option('vics_master_sheet_id')); ?>"
                                       class="large-text" />
                                <p class="description">
                                    <?php _e('The Google Sheet ID that contains the master agent tracker template', 'vics'); ?>
                                </p>
                                <?php
                                $master_sheet_id = get_option('vics_master_sheet_id');
                                if ($master_sheet_id && $is_authenticated) {
                                    $sheets = new VICS_Google_Sheets();
                                    if ($sheets->is_available()) {
                                        try {
                                            $sheets->get_sheets_service()->spreadsheets->get($master_sheet_id);
                                            echo '<p style="color: #28a745;"><span class="dashicons dashicons-yes"></span> ' . __('Master sheet is accessible', 'vics') . '</p>';
                                        } catch (Exception $e) {
                                            echo '<p style="color: #dc3545;"><span class="dashicons dashicons-no"></span> ' . __('Master sheet not found or not accessible. Please check the Sheet ID and sharing permissions.', 'vics') . '</p>';
                                        }
                                    }
                                }
                                ?>
                            </td>
                        </tr>
                    </table>

                    <?php submit_button(__('Save Settings', 'vics')); ?>
                </form>

                <!-- Master Tracker Actions -->
                <?php if ($is_authenticated): ?>
                <div class="master-tracker-actions" style="margin-top: 30px; padding: 15px; background: #f0f8ff; border: 1px solid #b3d9ff; border-radius: 4px;">
                    <h3><?php _e('Master Tracker', 'vics'); ?></h3>
                    <p><?php _e('Sync all agent data to the master tracker sheet.', 'vics'); ?></p>
                    <p>
                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=vics-settings&tab=google&action=sync_master'), 'sync_master_tracker'); ?>"
                           class="button button-secondary">
                            <?php _e('Sync All Agents to Master Tracker', 'vics'); ?>
                        </a>
                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=vics-settings&tab=google&action=test_connection'), 'test_google_connection'); ?>"
                           class="button button-secondary" style="margin-left: 10px;">
                            <?php _e('Test Connection', 'vics'); ?>
                        </a>
                    </p>
                </div>
                <?php endif; ?>
            </div>
            
            <?php elseif ($active_tab === 'birthdays'): ?>
            <!-- Upcoming Birthdays -->
            <div class="vics-admin-section">
                <h2><?php _e('Upcoming Agent Birthdays', 'vics'); ?></h2>
                <p><?php _e('Agents with birthdays in the next 30 days. This list automatically updates daily.', 'vics'); ?></p>

                <?php
                $upcoming_birthdays = $this->get_upcoming_birthdays_from_db();
                ?>

                <?php if (!empty($upcoming_birthdays)): ?>
                    <div class="vics-birthdays-table-wrapper">
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Agent Name', 'vics'); ?></th>
                                    <th><?php _e('Agent Code', 'vics'); ?></th>
                                    <th><?php _e('License', 'vics'); ?></th>
                                    <th><?php _e('Date of Birth', 'vics'); ?></th>
                                    <th><?php _e('Days Remaining', 'vics'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($upcoming_birthdays as $birthday): ?>
                                    <tr>
                                        <td><?php echo esc_html($birthday['name']); ?></td>
                                        <td><?php echo esc_html($birthday['agent_code']); ?></td>
                                        <td><?php echo esc_html($birthday['license_number']); ?></td>
                                        <td><?php echo esc_html(date('F j, Y', strtotime($birthday['date_of_birth']))); ?></td>
                                        <td>
                                            <span class="vics-days-remaining <?php echo $birthday['days_remaining'] <= 7 ? 'vics-urgent' : ''; ?>">
                                                <?php echo esc_html($birthday['days_remaining']); ?> 
                                                <?php echo $birthday['days_remaining'] === 1 ? __('day', 'vics') : __('days', 'vics'); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="vics-birthdays-summary" style="margin-top: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
                        <h3><?php _e('Summary', 'vics'); ?></h3>
                        <p>
                            <strong><?php echo count($upcoming_birthdays); ?></strong> 
                            <?php echo count($upcoming_birthdays) === 1 ? __('agent has', 'vics') : __('agents have', 'vics'); ?> 
                            <?php _e('birthdays in the next 30 days.', 'vics'); ?>
                        </p>
                        <?php
                        $urgent_count = count(array_filter($upcoming_birthdays, function($b) { return $b['days_remaining'] <= 7; }));
                        if ($urgent_count > 0):
                        ?>
                        <p style="color: #d63638;">
                            <strong><?php echo $urgent_count; ?></strong> 
                            <?php echo $urgent_count === 1 ? __('birthday is', 'vics') : __('birthdays are', 'vics'); ?> 
                            <?php _e('within the next 7 days!', 'vics'); ?>
                        </p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="vics-no-birthdays" style="padding: 40px; text-align: center; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
                        <p><?php _e('No upcoming birthdays in the next 30 days.', 'vics'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php elseif ($active_tab === 'debug'): ?>
            <!-- Debug Settings -->
            <?php
            // Debug log cleared notices
            if (isset($_GET['log_cleared']) && $_GET['log_cleared'] === '1') {
                echo '<div class="notice notice-success is-dismissible"><p>' . __('Debug log cleared successfully!', 'vics') . '</p></div>';
            } elseif (isset($_GET['log_cleared']) && $_GET['log_cleared'] === 'error') {
                echo '<div class="notice notice-error is-dismissible"><p>' . __('Failed to clear debug log. Please check file permissions.', 'vics') . '</p></div>';
            }
            ?>
            <div class="vics-admin-section">
                <h2><?php _e('Debug Settings', 'vics'); ?></h2>
                <p><?php _e('Configure debug logging for troubleshooting. Debug logs help identify issues but may impact performance.', 'vics'); ?></p>
                
                <form method="post" action="options.php">
                    <?php settings_fields('vics_debug_settings'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="vics_enable_debug_logging"><?php _e('Enable Debug Logging', 'vics'); ?></label></th>
                            <td>
                                <input type="checkbox" name="vics_enable_debug_logging" id="vics_enable_debug_logging" 
                                       value="1" <?php checked(get_option('vics_enable_debug_logging'), '1'); ?> />
                                <p class="description">
                                    <?php _e('Enable detailed debug logging for Google API operations, database queries, and other plugin functions. Logs are saved to wp-content/debug.log.', 'vics'); ?><br>
                                    <strong><?php _e('Warning:', 'vics'); ?></strong> <?php _e('Leave disabled in production environments for better performance and security.', 'vics'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button(__('Save Debug Settings', 'vics')); ?>
                </form>
                
                <div class="vics-debug-info" style="margin-top: 30px; padding: 15px; background: #f0f8ff; border: 1px solid #b3d9ff; border-radius: 4px;">
                    <h3><?php _e('Debug Information', 'vics'); ?></h3>
                    <p><strong><?php _e('WP_DEBUG Status:', 'vics'); ?></strong> 
                        <?php echo WP_DEBUG ? '<span style="color: #28a745;">Enabled</span>' : '<span style="color: #dc3545;">Disabled</span>'; ?>
                    </p>
                    <p><strong><?php _e('Debug Logging:', 'vics'); ?></strong> 
                        <?php echo get_option('vics_enable_debug_logging') ? '<span style="color: #28a745;">Enabled</span>' : '<span style="color: #dc3545;">Disabled</span>'; ?>
                    </p>
                    <p><strong><?php _e('Log File Location:', 'vics'); ?></strong> 
                        <?php echo esc_html(VICS_DEBUG_LOG_FILE); ?>
                    </p>
                    <?php if (file_exists(VICS_DEBUG_LOG_FILE)): ?>
                        <p><strong><?php _e('Log File Size:', 'vics'); ?></strong> 
                            <?php echo esc_html(size_format(filesize(VICS_DEBUG_LOG_FILE))); ?>
                        </p>
                        <p>
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=vics-settings&tab=debug&action=clear_debug_log'), 'clear_debug_log'); ?>" 
                               class="button button-secondary" onclick="return confirm('<?php _e('Are you sure you want to clear the debug log?', 'vics'); ?>');">
                                <?php _e('Clear Debug Log', 'vics'); ?>
                            </a>
                        </p>
                    <?php else: ?>
                        <p><?php _e('No debug log file found.', 'vics'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Get upcoming birthdays from database
     *
     * @return array
     */
    public function get_upcoming_birthdays_from_db() {
        global $wpdb;
        
        // Get all agents with birth dates
        $agents = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT u.ID, u.display_name, u.user_email, p.date_of_birth, p.agent_code, p.license_number
                 FROM {$wpdb->users} u
                 INNER JOIN {$wpdb->prefix}vics_agent_profile p ON u.ID = p.user_id
                 WHERE p.date_of_birth IS NOT NULL 
                 AND p.date_of_birth != ''
                 AND p.date_of_birth != '0000-00-00'
                 AND u.ID IN (
                     SELECT user_id FROM {$wpdb->usermeta} 
                     WHERE meta_key = %s 
                     AND meta_value LIKE %s
                 )
                 ORDER BY u.display_name",
                $wpdb->prefix . 'capabilities',
                '%"agent"%'
            ),
            ARRAY_A
        );
        
        // Debug: Log the raw data
        if (get_option('vics_enable_debug_logging')) {
            vics_log('Birthday Check: Found ' . count($agents) . ' agents with birth dates');
            foreach ($agents as $agent) {
                vics_log('Agent: ' . $agent['display_name'] . ' | DOB: ' . $agent['date_of_birth']);
            }
        }

        $upcoming = [];
        $today = current_time('Y-m-d');
        $current_year = (int)current_time('Y');
        $today_timestamp = strtotime($today);
        $thirty_days_timestamp = strtotime('+30 days', $today_timestamp);

        foreach ($agents as $agent) {
            if (empty($agent['date_of_birth'])) {
                continue;
            }
            
            // Clean the date string
            $dob = trim($agent['date_of_birth']);
            
            // Try multiple date formats
            $birth_timestamp = false;
            $formats = ['Y-m-d', 'Y/m/d', 'm/d/Y', 'd/m/Y', 'Y-m-d H:i:s'];
            
            foreach ($formats as $format) {
                $date_obj = DateTime::createFromFormat($format, $dob);
                if ($date_obj !== false) {
                    $birth_timestamp = $date_obj->getTimestamp();
                    break;
                }
            }
            
            // Fallback to strtotime
            if ($birth_timestamp === false) {
                $birth_timestamp = strtotime($dob);
            }
            
            // Skip if still invalid
            if ($birth_timestamp === false || $birth_timestamp < 0) {
                if (get_option('vics_enable_debug_logging')) {
                    vics_log('Birthday Check: Invalid date for ' . $agent['display_name'] . ': ' . $dob);
                }
                continue;
            }
            
            // Extract month and day from birth date
            $birth_month = date('m', $birth_timestamp);
            $birth_day = date('d', $birth_timestamp);
            
            // Calculate this year's birthday
            $birthday_this_year = $current_year . '-' . $birth_month . '-' . $birth_day;
            $birthday_timestamp = strtotime($birthday_this_year);
            
            // If birthday has passed this year, use next year
            if ($birthday_timestamp < $today_timestamp) {
                $birthday_this_year = ($current_year + 1) . '-' . $birth_month . '-' . $birth_day;
                $birthday_timestamp = strtotime($birthday_this_year);
            }

            // Check if birthday is within the next 30 days
            if ($birthday_timestamp >= $today_timestamp && $birthday_timestamp <= $thirty_days_timestamp) {
                $days_remaining = floor(($birthday_timestamp - $today_timestamp) / (60 * 60 * 24));

                $upcoming[] = [
                    'name' => $agent['display_name'],
                    'agent_code' => $agent['agent_code'] ?? '',
                    'license_number' => $agent['license_number'] ?? '',
                    'date_of_birth' => $agent['date_of_birth'],
                    'birthday_date' => $birthday_this_year,
                    'days_remaining' => (int)$days_remaining,
                    'email' => $agent['user_email']
                ];
                
                if (get_option('vics_enable_debug_logging')) {
                    vics_log('Birthday Check: Added ' . $agent['display_name'] . ' with ' . $days_remaining . ' days remaining');
                }
            }
        }
        
        if (get_option('vics_enable_debug_logging')) {
            vics_log('Birthday Check: Total upcoming birthdays: ' . count($upcoming));
        }

        // Sort by days remaining (closest first)
        usort($upcoming, function($a, $b) {
            return $a['days_remaining'] - $b['days_remaining'];
        });

        return $upcoming;
    }
    public function render_agents_page() {
        $agents = get_users(array('role' => 'agent'));
        ?>
        <div class="wrap">
            <h1><?php _e('All Agents', 'vics'); ?></h1>
            
            <?php
            // Sheet creation notices
            if (isset($_GET['sheet_created']) && $_GET['sheet_created'] === 'success') {
                echo '<div class="notice notice-success is-dismissible"><p>' . __('Agent Google Sheet created successfully!', 'vics') . '</p></div>';
            } elseif (isset($_GET['sheet_created']) && $_GET['sheet_created'] === 'error') {
                echo '<div class="notice notice-error is-dismissible"><p>' . __('Failed to create agent Google Sheet. Please check your master sheet configuration and permissions.', 'vics') . '</p></div>';
            }
            ?>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Name', 'vics'); ?></th>
                        <th><?php _e('Email', 'vics'); ?></th>
                        <th><?php _e('Phone', 'vics'); ?></th>
                        <th><?php _e('Agent Code', 'vics'); ?></th>
                        <th><?php _e('Orientation', 'vics'); ?></th>
                        <th><?php _e('Licenses', 'vics'); ?></th>
                        <th><?php _e('Google Sheet', 'vics'); ?></th>
                        <th><?php _e('Registered', 'vics'); ?></th>
                        <th><?php _e('Actions', 'vics'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($agents)): ?>
                        <tr>
                            <td colspan="9"><?php _e('No agents found.', 'vics'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($agents as $agent): 
                            $profile = VICS_Database::get_profile($agent->ID);
                            $orientation_completed = VICS_Database::has_completed_orientation($agent->ID);
                            $licenses = VICS_Database::get_licenses($agent->ID);
                        ?>
                            <tr>
                                <td>
                                    <?php echo get_avatar($agent->ID, 32); ?>
                                    <strong><?php echo esc_html($agent->display_name); ?></strong>
                                </td>
                                <td><?php echo esc_html($agent->user_email); ?></td>
                                <td><?php echo esc_html($profile['phone'] ?? '-'); ?></td>
                                <td><?php echo esc_html($profile['agent_code'] ?? '-'); ?></td>
                                <td>
                                    <?php if ($orientation_completed): ?>
                                        <span class="vics-status vics-status-active"><?php _e('Completed', 'vics'); ?></span>
                                    <?php else: ?>
                                        <span class="vics-status vics-status-pending"><?php _e('Pending', 'vics'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo count($licenses); ?></td>
                                <td>
                                    <?php if (!empty($profile['google_sheet_id'])): ?>
                                        <span class="vics-status vics-status-active"><?php _e('Created', 'vics'); ?></span>
                                        <a href="https://docs.google.com/spreadsheets/d/<?php echo esc_attr($profile['google_sheet_id']); ?>" 
                                           target="_blank" class="button button-small">
                                            <?php _e('View', 'vics'); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="vics-status vics-status-pending"><?php _e('Not Created', 'vics'); ?></span>
                                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=vics-agents&action=create_agent_sheet&user_id=' . $agent->ID), 'create_agent_sheet'); ?>" 
                                           class="button button-small button-primary">
                                            <?php _e('Create Sheet', 'vics'); ?>
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($agent->user_registered)); ?></td>
                                <td>
                                    <a href="<?php echo get_edit_user_link($agent->ID); ?>" class="button button-small">
                                        <?php _e('Edit', 'vics'); ?>
                                    </a>
                                    <button type="button" class="button button-small view-agent-details" 
                                            data-agent-id="<?php echo $agent->ID; ?>"
                                            data-name="<?php echo esc_attr($agent->display_name); ?>"
                                            data-birthday="<?php echo esc_attr($profile['date_of_birth'] ?? ''); ?>"
                                            data-city="<?php echo esc_attr($profile['city'] ?? ''); ?>"
                                            data-state="<?php echo esc_attr($profile['state'] ?? ''); ?>"
                                            data-goals="<?php echo esc_attr($profile['goals_for_year'] ?? ''); ?>"
                                            data-favorites="<?php echo esc_attr($profile['favorite_things'] ?? ''); ?>"
                                            data-unknown="<?php echo esc_attr($profile['unknown_fact'] ?? ''); ?>"
                                            data-support="<?php echo esc_attr($profile['support_needed'] ?? ''); ?>"
                                            data-feedback="<?php echo esc_attr($profile['feedback_preference'] ?? ''); ?>">
                                        <?php _e('View Details', 'vics'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Agent Details Modal -->
        <div id="agent-details-modal" style="display: none; position: fixed; z-index: 100000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5);">
            <div style="background-color: #fefefe; margin: 5% auto; padding: 30px; border: 1px solid #888; border-radius: 8px; width: 90%; max-width: 700px; box-shadow: 0 4px 20px rgba(0,0,0,0.2);">
                <span id="close-agent-modal" style="color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
                <h2 id="agent-modal-title" style="margin-top: 0;">Agent Details</h2>
                <div id="agent-modal-content" style="margin-top: 20px;">
                    <div style="margin-bottom: 20px;">
                        <strong>Birthday:</strong> <span id="modal-birthday">-</span>
                    </div>
                    <div style="margin-bottom: 20px;">
                        <strong>Location:</strong> <span id="modal-location">-</span>
                    </div>
                    <div style="margin-bottom: 20px;">
                        <strong>Goals for the year:</strong>
                        <div id="modal-goals" style="margin-top: 8px; padding: 10px; background: #f9f9f9; border-radius: 4px; white-space: pre-wrap;">-</div>
                    </div>
                    <div style="margin-bottom: 20px;">
                        <strong>What are your 5 favorite things to do outside of work?</strong>
                        <div id="modal-favorites" style="margin-top: 8px; padding: 10px; background: #f9f9f9; border-radius: 4px; white-space: pre-wrap;">-</div>
                    </div>
                    <div style="margin-bottom: 20px;">
                        <strong>What's one thing most people don't know about you?</strong>
                        <div id="modal-unknown" style="margin-top: 8px; padding: 10px; background: #f9f9f9; border-radius: 4px; white-space: pre-wrap;">-</div>
                    </div>
                    <div style="margin-bottom: 20px;">
                        <strong>Where do you feel you need the most support right now?</strong>
                        <div id="modal-support" style="margin-top: 8px; padding: 10px; background: #f9f9f9; border-radius: 4px; white-space: pre-wrap;">-</div>
                    </div>
                    <div style="margin-bottom: 20px;">
                        <strong>What type of feedback helps you grow the most?</strong>
                        <div id="modal-feedback" style="margin-top: 8px; padding: 10px; background: #f9f9f9; border-radius: 4px; white-space: pre-wrap;">-</div>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // View Agent Details
            $('.view-agent-details').on('click', function() {
                var btn = $(this);
                var name = btn.data('name');
                var birthday = btn.data('birthday');
                var city = btn.data('city');
                var state = btn.data('state');
                var goals = btn.data('goals');
                var favorites = btn.data('favorites');
                var unknown = btn.data('unknown');
                var support = btn.data('support');
                var feedback = btn.data('feedback');
                
                console.log('Agent Details:', {
                    name: name,
                    birthday: birthday,
                    city: city,
                    state: state,
                    goals: goals,
                    favorites: favorites,
                    unknown: unknown,
                    support: support,
                    feedback: feedback
                });
                
                $('#agent-modal-title').text(name + ' - About');
                $('#modal-birthday').text(birthday || 'Not set');
                $('#modal-location').text((city && state) ? (city + ', ' + state) : (city || state || 'Not set'));
                $('#modal-goals').text(goals || 'Not set');
                $('#modal-favorites').text(favorites || 'Not set');
                $('#modal-unknown').text(unknown || 'Not set');
                $('#modal-support').text(support || 'Not set');
                $('#modal-feedback').text(feedback || 'Not set');
                
                $('#agent-details-modal').fadeIn();
            });
            
            // Close modal
            $('#close-agent-modal, #agent-details-modal').on('click', function(e) {
                if (e.target === this) {
                    $('#agent-details-modal').fadeOut();
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render Orientation Report
     */
    public function render_orientation_report() {
        global $wpdb;
        $table = $wpdb->prefix . 'vics_orientation_progress';
        
        $results = $wpdb->get_results("
            SELECT p.*, u.user_login, u.user_email, u.display_name
            FROM $table p
            JOIN {$wpdb->users} u ON p.user_id = u.ID
            ORDER BY p.created_at DESC
        ");
        ?>
        <div class="wrap">
            <h1><?php _e('Orientation Report', 'vics'); ?></h1>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('User', 'vics'); ?></th>
                        <th><?php _e('Email', 'vics'); ?></th>
                        <th><?php _e('Terms', 'vics'); ?></th>
                        <th><?php _e('Form', 'vics'); ?></th>
                        <th><?php _e('Video Progress', 'vics'); ?></th>
                        <th><?php _e('Video', 'vics'); ?></th>
                        <th><?php _e('Completed', 'vics'); ?></th>
                        <th><?php _e('Completed At', 'vics'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($results)): ?>
                        <tr>
                            <td colspan="8"><?php _e('No records found.', 'vics'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($results as $row): ?>
                            <tr>
                                <td><?php echo esc_html($row->display_name); ?></td>
                                <td><?php echo esc_html($row->user_email); ?></td>
                                <td><?php echo $row->terms_accepted ? '✓' : '✗'; ?></td>
                                <td><?php echo $row->form_submitted ? '✓' : '✗'; ?></td>
                                <td>
                                    <?php 
                                    $percent = $row->video_duration > 0 
                                        ? round(($row->video_timestamp / $row->video_duration) * 100) 
                                        : 0;
                                    ?>
                                    <div class="vics-progress-mini">
                                        <div class="vics-progress-mini-fill" style="width: <?php echo $percent; ?>%"></div>
                                    </div>
                                    <?php echo $percent; ?>%
                                </td>
                                <td><?php echo $row->video_completed ? '✓' : '✗'; ?></td>
                                <td><?php echo $row->orientation_completed ? '✓' : '✗'; ?></td>
                                <td>
                                    <?php echo $row->completed_at 
                                        ? date('M j, Y g:i A', strtotime($row->completed_at)) 
                                        : '-'; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Render License Management Page
     */
    public function render_license_management() {
        // Check for success message
        if (isset($_GET['updated']) && $_GET['updated'] == '1') {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('License status updated successfully.', 'vics') . '</p></div>';
        }
        
        // Get pending agent code changes
        global $wpdb;
        $pending_agent_codes = $wpdb->get_results("
            SELECT p.*, u.display_name, u.user_email 
            FROM {$wpdb->prefix}vics_agent_profile p
            JOIN {$wpdb->users} u ON p.user_id = u.ID 
            WHERE p.agent_code_status = 'pending'
            ORDER BY p.updated_at DESC
        ");
        
        // Get all licenses
        $licenses = $wpdb->get_results("
            SELECT l.*, u.display_name, u.user_email, p.agent_code, p.agent_code_status
            FROM {$wpdb->prefix}vics_agent_licenses l 
            JOIN {$wpdb->users} u ON l.user_id = u.ID
            LEFT JOIN {$wpdb->prefix}vics_agent_profile p ON l.user_id = p.user_id
            ORDER BY l.created_at DESC
        ");
        
        // Separate pending licenses
        $pending_licenses = array_filter($licenses, function($license) {
            return $license->status === 'pending';
        });
        
        // Get all agents for quick reference
        $all_agents = get_users(array('role' => 'agent'));
        $agents_with_licenses = array();
        foreach ($licenses as $license) {
            $agents_with_licenses[$license->user_id] = true;
        }
        
        ?>
        <div class="wrap">
            <h1><?php _e('License Management', 'vics'); ?></h1>
            
            <p class="description" style="margin-bottom: 20px;">
                Manage agent licenses and agent codes. Review pending submissions and approve/reject them.
            </p>
            
            <!-- Quick Agent Overview -->
            <!-- <div style="padding: 20px; margin-bottom: 20px; background: #f0f9ff; border: 1px solid #b3d9ff; border-left: 4px solid #0073aa; border-radius: 4px;">
                <h2>👥 All Agents (<?php echo count($all_agents); ?>)</h2>
                <p style="color: #666; margin-bottom: 15px;">
                    Quick overview of all agents and their license status. Click "Add License" to create a license record.
                </p>
                <table class="wp-list-table widefat striped" style="background: white;">
                    <thead>
                        <tr>
                            <th>Agent Name</th>
                            <th>Email</th>
                            <th>Agent Code</th>
                            <th>License Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_agents as $agent): 
                            $agent_profile = VICS_Database::get_profile($agent->ID);
                            $agent_licenses = VICS_Database::get_licenses($agent->ID);
                            $has_license = count($agent_licenses) > 0;
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html($agent->display_name); ?></strong></td>
                            <td><?php echo esc_html($agent->user_email); ?></td>
                            <td>
                                <?php if (!empty($agent_profile['agent_code'])): ?>
                                    <code><?php echo esc_html($agent_profile['agent_code']); ?></code>
                                    <?php if ($agent_profile['agent_code_status'] === 'pending'): ?>
                                        <br><span style="color: #f0ad4e; font-size: 11px;">⏳ Pending</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color: #999;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($has_license): ?>
                                    <span class="vics-status-badge vics-status-active">
                                        <?php echo count($agent_licenses); ?> License<?php echo count($agent_licenses) > 1 ? 's' : ''; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="vics-status-badge vics-status-pending">No Licenses</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" class="button button-small button-primary add-license-for-agent" 
                                        data-user-id="<?php echo $agent->ID; ?>"
                                        data-user-name="<?php echo esc_attr($agent->display_name); ?>"
                                        data-user-email="<?php echo esc_attr($agent->user_email); ?>"
                                        data-agent-code="<?php echo esc_attr($agent_profile['agent_code'] ?? ''); ?>"
                                        data-license-number="<?php echo esc_attr($agent_profile['license_number'] ?? ''); ?>">
                                    <?php echo $has_license ? 'Add Another' : 'Add License'; ?>
                                </button>
                                <?php if ($has_license): ?>
                                    <a href="#licenses-section" class="button button-small" 
                                       onclick="document.querySelector('[data-filter-agent=\"<?php echo $agent->ID; ?>\"]')?.scrollIntoView({behavior: 'smooth'});">
                                        View Licenses
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div> -->
            
            <!-- Pending Reviews Section -->
            <?php if (!empty($pending_agent_codes) || !empty($pending_licenses)): ?>
            <div style="padding: 20px; margin-bottom: 20px; background: #fff; border: 1px solid #ccd0d4; border-left: 4px solid #f0ad4e; border-radius: 4px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                <h2>⏳ Pending Reviews (<?php echo count($pending_agent_codes) + count($pending_licenses); ?>)</h2>
                <p style="color: #666; margin-bottom: 15px;">These items are awaiting your approval.</p>
                
                <?php if (!empty($pending_agent_codes)): ?>
                <h3 style="margin-top: 20px;">Agent Code Changes</h3>
                <table class="wp-list-table widefat fixed striped" style="margin-bottom: 20px;">
                    <thead>
                        <tr>
                            <th>Agent</th>
                            <th>Email</th>
                            <th>Agent Code</th>
                            <th>Submitted</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_agent_codes as $item): ?>
                        <tr>
                            <td><strong><?php echo esc_html($item->display_name); ?></strong></td>
                            <td><?php echo esc_html($item->user_email); ?></td>
                            <td><code><?php echo esc_html($item->agent_code); ?></code></td>
                            <td><?php echo human_time_diff(strtotime($item->updated_at), current_time('timestamp')) . ' ago'; ?></td>
                            <td>
                                <button type="button" class="button button-small button-primary approve-agent-code" 
                                        data-user-id="<?php echo $item->user_id; ?>">Approve</button>
                                <button type="button" class="button button-small reject-agent-code" 
                                        data-user-id="<?php echo $item->user_id; ?>"
                                        style="margin-left: 5px;">Reject</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
                
                <?php if (!empty($pending_licenses)): ?>
                <h3 style="margin-top: 20px;">Pending Licenses</h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Agent</th>
                            <th>State</th>
                            <th>License Number</th>
                            <th>Submitted</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_licenses as $license): ?>
                        <tr>
                            <td><strong><?php echo esc_html($license->display_name); ?></strong></td>
                            <td><?php echo esc_html($license->license_state); ?></td>
                            <td><?php echo esc_html($license->license_number); ?></td>
                            <td><?php echo human_time_diff(strtotime($license->created_at), current_time('timestamp')) . ' ago'; ?></td>
                            <td>
                                <button type="button" class="button button-small edit-license" 
                                        data-license-id="<?php echo $license->id; ?>">Review</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <button type="button" class="button button-primary" id="add-new-license" style="margin-bottom: 20px;">
                <span class="dashicons dashicons-plus-alt2" style="vertical-align: middle; margin-top: -4px;"></span> Add New License
            </button>
            
            <div class="vics-admin-section" id="licenses-section">
                <h2>All License Records (<?php echo count($licenses); ?>)</h2>
                <?php if (empty($licenses)): ?>
                    <div style="padding: 40px 20px; text-align: center; background: #fff9e6; border: 2px dashed #f0ad4e; border-radius: 4px;">
                        <p style="font-size: 18px; color: #856404; margin-bottom: 10px;">📋 No license records found</p>
                        <p style="color: #856404; font-size: 14px; margin-bottom: 15px;">
                            License records are different from the "License #" field in agent profiles.<br>
                            Use the table above to find agents and click <strong>"Add License"</strong> to create proper license records.
                        </p>
                        <button type="button" class="button button-primary button-large" 
                                onclick="document.getElementById('add-new-license').click();">
                            <span class="dashicons dashicons-plus-alt2" style="vertical-align: middle;"></span> Create First License Record
                        </button>
                    </div>
                <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Agent', 'vics'); ?></th>
                            <th><?php _e('Agent Code', 'vics'); ?></th>
                            <th><?php _e('State', 'vics'); ?></th>
                            <th><?php _e('License Number', 'vics'); ?></th>
                            <th><?php _e('Status', 'vics'); ?></th>
                            <th><?php _e('Expiry Date', 'vics'); ?></th>
                            <th><?php _e('Actions', 'vics'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($licenses): ?>
                            <?php foreach ($licenses as $license): ?>
                                <tr data-filter-agent="<?php echo $license->user_id; ?>">
                                    <td>
                                        <strong><?php echo esc_html($license->display_name); ?></strong><br>
                                        <small><?php echo esc_html($license->user_email); ?></small>
                                    </td>
                                    <td>
                                        <?php if (!empty($license->agent_code)): ?>
                                            <code><?php echo esc_html($license->agent_code); ?></code>
                                            <?php if ($license->agent_code_status === 'pending'): ?>
                                                <br><span class="vics-status-badge vics-status-pending" style="font-size: 10px;">Pending Review</span>
                                            <?php elseif ($license->agent_code_status === 'rejected'): ?>
                                                <br><span class="vics-status-badge vics-status-rejected" style="font-size: 10px;">Rejected</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span style="color: #999;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html($license->license_state); ?></td>
                                    <td><?php echo esc_html($license->license_number); ?></td>
                                    <td>
                                        <span class="vics-status-badge vics-status-<?php echo esc_attr($license->status); ?>">
                                            <?php echo esc_html(ucfirst($license->status)); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo $license->expiry_date 
                                            ? date('M j, Y', strtotime($license->expiry_date)) 
                                            : '-'; ?>
                                    </td>
                                    <td>
                                        <select class="license-status-select" 
                                                data-license-id="<?php echo $license->id; ?>"
                                                data-current-status="<?php echo $license->status; ?>">
                                            <option value="pending" <?php selected($license->status, 'pending'); ?>>Pending</option>
                                            <option value="active" <?php selected($license->status, 'active'); ?>>Active</option>
                                            <option value="expired" <?php selected($license->status, 'expired'); ?>>Expired</option>
                                            <option value="rejected" <?php selected($license->status, 'rejected'); ?>>Rejected</option>
                                        </select>
                                        <button type="button" class="button button-small edit-license" 
                                                data-license-id="<?php echo $license->id; ?>"
                                                style="margin-left: 5px;">Edit</button>
                                        <button type="button" class="button button-small button-link-delete delete-license" 
                                                data-license-id="<?php echo $license->id; ?>"
                                                style="margin-left: 5px; color: #b32d2e;">Delete</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 30px;"><?php _e('No licenses found.', 'vics'); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- License Modal -->
        <div id="license-modal" style="display: none;">
            <div class="vics-modal-overlay"></div>
            <div class="vics-modal-content">
                <div class="vics-modal-header">
                    <h2 id="modal-title">Add License</h2>
                    <button type="button" class="vics-modal-close">&times;</button>
                </div>
                <div class="vics-modal-body">
                    <form id="license-form">
                        <input type="hidden" id="license-id" name="license_id" value="">
                        
                        <p>
                            <label for="agent-select"><strong>Select Agent *</strong></label>
                            <select id="agent-select" name="user_id" required style="width: 100%;">
                                <option value="">-- Select Agent --</option>
                                <?php
                                $agents = get_users(array('role' => 'agent'));
                                foreach ($agents as $agent) {
                                    echo '<option value="' . $agent->ID . '">' . esc_html($agent->display_name) . ' (' . esc_html($agent->user_email) . ')</option>';
                                }
                                ?>
                            </select>
                        </p>
                        
                        <p>
                            <label for="agent-code"><strong>Agent Code</strong></label>
                            <input type="text" id="agent-code" name="agent_code" 
                                   placeholder="e.g., AG12345" style="width: 100%;">
                            <small style="color: #666;">Optional: Update agent's code if needed</small>
                        </p>
                        
                        <p>
                            <label for="license-state"><strong>State *</strong></label>
                            <input type="text" id="license-state" name="license_state" required 
                                   placeholder="e.g., CA" style="width: 100%;">
                        </p>
                        
                        <p>
                            <label for="license-number"><strong>License Number</strong></label>
                            <input type="text" id="license-number" name="license_number" 
                                   placeholder="e.g., 12345678" style="width: 100%;">
                        </p>
                        
                        <p>
                            <label for="issue-date"><strong>Issue Date</strong></label>
                            <input type="date" id="issue-date" name="issue_date" style="width: 100%;">
                        </p>
                        
                        <p>
                            <label for="expiry-date"><strong>Expiry Date</strong></label>
                            <input type="date" id="expiry-date" name="expiry_date" style="width: 100%;">
                        </p>
                        
                        <p>
                            <label for="license-status"><strong>Status *</strong></label>
                            <select id="license-status" name="status" required style="width: 100%;">
                                <option value="pending">Pending</option>
                                <option value="active">Active</option>
                                <option value="expired">Expired</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </p>
                        
                        <p>
                            <label for="license-notes"><strong>Notes</strong></label>
                            <textarea id="license-notes" name="notes" rows="3" 
                                      placeholder="Additional notes..." style="width: 100%;"></textarea>
                        </p>
                    </form>
                </div>
                <div class="vics-modal-footer">
                    <button type="button" class="button button-large button-secondary vics-modal-close">Cancel</button>
                    <button type="button" class="button button-large button-primary" id="save-license">Save License</button>
                </div>
            </div>
        </div>
        
        <style>
            .vics-modal-overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.7);
                z-index: 100000;
            }
            .vics-modal-content {
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: #fff;
                border-radius: 4px;
                box-shadow: 0 5px 15px rgba(0,0,0,0.3);
                z-index: 100001;
                max-width: 600px;
                width: 90%;
                max-height: 90vh;
                overflow-y: auto;
            }
            .vics-modal-header {
                padding: 20px;
                border-bottom: 1px solid #ddd;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .vics-modal-header h2 {
                margin: 0;
            }
            .vics-modal-close {
                background: none;
                border: none;
                font-size: 28px;
                cursor: pointer;
                color: #666;
                line-height: 1;
                padding: 0;
            }
            .vics-modal-close:hover {
                color: #000;
            }
            .vics-modal-body {
                padding: 20px;
            }
            .vics-modal-footer {
                padding: 15px 20px;
                border-top: 1px solid #ddd;
                text-align: right;
            }
            .vics-modal-footer .button {
                margin-left: 10px;
            }
            .vics-status-badge {
                padding: 4px 10px;
                border-radius: 12px;
                font-size: 12px;
                font-weight: 500;
                display: inline-block;
            }
            .vics-status-pending {
                background: #fff3cd;
                color: #856404;
            }
            .vics-status-active {
                background: #d4edda;
                color: #155724;
            }
            .vics-status-expired {
                background: #f8d7da;
                color: #721c24;
            }
            .vics-status-rejected {
                background: #e2e3e5;
                color: #383d41;
            }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Open modal for adding new license
            $('#add-new-license').on('click', function() {
                $('#modal-title').text('Add New License');
                $('#license-form')[0].reset();
                $('#license-id').val('');
                $('#agent-select').prop('disabled', false);
                $('#agent-code').val('');
                $('#license-modal').fadeIn();
            });
            
            // Open modal for adding license to specific agent
            $('.add-license-for-agent').on('click', function() {
                var userId = $(this).data('user-id');
                var userName = $(this).data('user-name');
                var userEmail = $(this).data('user-email');
                var agentCode = $(this).data('agent-code');
                var licenseNumber = $(this).data('license-number');
                
                $('#modal-title').text('Add License for ' + userName);
                $('#license-form')[0].reset();
                $('#license-id').val('');
                $('#agent-select').val(userId).prop('disabled', true);
                $('#agent-code').val(agentCode || '');
                $('#license-number').val(licenseNumber || '');
                $('#license-status').val('pending'); // Default to pending for manual addition
                $('#license-modal').fadeIn();
            });
            
            // Close modal
            $('.vics-modal-close').on('click', function() {
                $('#license-modal').fadeOut();
            });
            
            // Close modal on overlay click
            $('.vics-modal-overlay').on('click', function() {
                $('#license-modal').fadeOut();
            });
            
            // Edit license
            $('.edit-license').on('click', function() {
                var licenseId = $(this).data('license-id');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'vics_admin_get_license',
                        license_id: licenseId,
                        nonce: '<?php echo wp_create_nonce("vics_admin_license"); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            var license = response.data.license;
                            console.log('License Data:', license); // Debug log
                            
                            $('#modal-title').text('Edit License');
                            $('#license-id').val(license.id);
                            $('#agent-select').val(license.user_id).prop('disabled', true);
                            $('#agent-code').val(license.agent_code || '');
                            $('#license-state').val(license.license_state || '');
                            $('#license-number').val(license.license_number || '');
                            
                            // Handle dates - check for null, '0000-00-00', or empty
                            var issueDate = license.issue_date && license.issue_date !== '0000-00-00' ? license.issue_date : '';
                            var expiryDate = license.expiry_date && license.expiry_date !== '0000-00-00' ? license.expiry_date : '';
                            $('#issue-date').val(issueDate);
                            $('#expiry-date').val(expiryDate);
                            
                            $('#license-status').val(license.status || 'pending');
                            $('#license-notes').val(license.notes || '');
                            $('#license-modal').fadeIn();
                        } else {
                            alert('Error: ' + response.data.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', error);
                        alert('Failed to load license data. Please try again.');
                    }
                });
            });
            
            // Save license
            $('#save-license').on('click', function() {
                var licenseId = $('#license-id').val();
                var formData = $('#license-form').serialize();
                var action = licenseId ? 'vics_admin_update_license' : 'vics_admin_add_license';
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData + '&action=' + action + '&nonce=<?php echo wp_create_nonce("vics_admin_license"); ?>',
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message);
                            location.reload();
                        } else {
                            alert('Error: ' + response.data.message);
                        }
                    }
                });
            });
            
            // Delete license
            $('.delete-license').on('click', function() {
                if (!confirm('Are you sure you want to delete this license? This action cannot be undone.')) {
                    return;
                }
                
                var licenseId = $(this).data('license-id');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'vics_admin_delete_license',
                        license_id: licenseId,
                        nonce: '<?php echo wp_create_nonce("vics_admin_license"); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message);
                            location.reload();
                        } else {
                            alert('Error: ' + response.data.message);
                        }
                    }
                });
            });
            
            // Update status on change
            $('.license-status-select').on('change', function() {
                var $select = $(this);
                var licenseId = $select.data('license-id');
                var newStatus = $select.val();
                var currentStatus = $select.data('current-status');
                
                if (!confirm('Are you sure you want to change the status to \"' + newStatus + '\"?')) {
                    $select.val(currentStatus);
                    return;
                }
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'vics_update_license_status',
                        license_id: licenseId,
                        new_status: newStatus,
                        nonce: '<?php echo wp_create_nonce("vics_admin_nonce"); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $select.data('current-status', newStatus);
                            alert(response.data.message);
                            location.reload();
                        } else {
                            alert('Error: ' + response.data.message);
                            $select.val(currentStatus);
                        }
                    },
                    error: function() {
                        alert('An error occurred. Please try again.');
                        $select.val(currentStatus);
                    }
                });
            });
            
            // Approve agent code
            $('.approve-agent-code').on('click', function() {
                if (!confirm('Approve this agent code?')) {
                    return;
                }
                
                var userId = $(this).data('user-id');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'vics_approve_agent_code',
                        user_id: userId,
                        nonce: '<?php echo wp_create_nonce("vics_admin_agent_code"); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message);
                            location.reload();
                        } else {
                            alert('Error: ' + response.data.message);
                        }
                    }
                });
            });
            
            // Reject agent code
            $('.reject-agent-code').on('click', function() {
                if (!confirm('Reject this agent code? The agent will need to resubmit.')) {
                    return;
                }
                
                var userId = $(this).data('user-id');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'vics_reject_agent_code',
                        user_id: userId,
                        nonce: '<?php echo wp_create_nonce("vics_admin_agent_code"); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message);
                            location.reload();
                        } else {
                            alert('Error: ' + response.data.message);
                        }
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Handle License Status Update
     */
    public function handle_license_status_update() {
        // Handle AJAX requests
        if (isset($_POST['action']) && $_POST['action'] === 'vics_update_license_status') {
            $this->handle_ajax_license_status_update();
            return;
        }
        
        // Handle form submissions (legacy)
        if (!isset($_POST['update_license_status']) || !isset($_POST['license_id'])) {
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.'));
        }
        
        // Use wp_verify_nonce instead of check_admin_referer for more control
        if (!wp_verify_nonce($_POST['_wpnonce'], 'vics_update_license_status')) {
            wp_die(__('Security check failed - nonce verification failed.'));
        }
        
        $license_id = intval($_POST['license_id']);
        $new_status = sanitize_text_field($_POST['new_status']);
        
        if (!in_array($new_status, array('pending', 'active', 'expired', 'rejected'))) {
            wp_die(__('Invalid status.'));
        }
        
        VICS_Database::update_license($license_id, 0, array('status' => $new_status));
        
        // Redirect back with success message
        wp_redirect(add_query_arg('updated', '1', wp_get_referer()));
        exit;
    }
    
    /**
     * Handle AJAX License Status Update
     */
    private function handle_ajax_license_status_update() {
        check_ajax_referer('vics_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.'));
        }
        
        $license_id = intval($_POST['license_id']);
        $new_status = sanitize_text_field($_POST['new_status']);
        
        if (!in_array($new_status, array('pending', 'active', 'expired', 'rejected'))) {
            wp_send_json_error(__('Invalid status.'));
        }
        
        $result = VICS_Database::update_license($license_id, 0, array('status' => $new_status));
        
        if ($result !== false) {
            wp_send_json_success(array(
                'message' => __('License status updated successfully!', 'vics')
            ));
        } else {
            wp_send_json_error(__('Failed to update license status.', 'vics'));
        }
    }
    
    /**
     * Admin Get License (AJAX)
     */
    public function admin_get_license() {
        check_ajax_referer('vics_admin_license', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.')));
        }
        
        $license_id = intval($_POST['license_id']);
        
        if (!$license_id) {
            wp_send_json_error(array('message' => __('Invalid license ID')));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'vics_agent_licenses';
        $profile_table = $wpdb->prefix . 'vics_agent_profile';
        
        $license = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                l.id,
                l.user_id,
                l.license_type,
                l.license_state,
                l.license_number,
                l.issue_date,
                l.expiry_date,
                l.status,
                l.notes,
                l.created_at,
                l.updated_at,
                p.agent_code
             FROM $table l
             LEFT JOIN $profile_table p ON l.user_id = p.user_id
             WHERE l.id = %d",
            $license_id
        ), ARRAY_A);
        
        if (!$license) {
            wp_send_json_error(array('message' => __('License not found')));
        }
        
        // Debug log to check what data is being returned
        error_log('Admin Get License - License ID: ' . $license_id);
        error_log('License Data: ' . print_r($license, true));
        
        wp_send_json_success(array('license' => $license));
    }
    
    /**
     * Admin Add License (AJAX)
     */
    public function admin_add_license() {
        check_ajax_referer('vics_admin_license', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.')));
        }
        
        $user_id = intval($_POST['user_id']);
        
        if (!$user_id) {
            wp_send_json_error(array('message' => __('Please select an agent')));
        }
        
        // Verify user exists and is an agent
        $user = get_user_by('ID', $user_id);
        if (!$user || !in_array('agent', $user->roles)) {
            wp_send_json_error(array('message' => __('Invalid agent selected')));
        }
        
        $data = array(
            'license_type' => '',
            'license_state' => sanitize_text_field($_POST['license_state']),
            'license_number' => sanitize_text_field($_POST['license_number'] ?? ''),
            'issue_date' => !empty($_POST['issue_date']) ? sanitize_text_field($_POST['issue_date']) : null,
            'expiry_date' => !empty($_POST['expiry_date']) ? sanitize_text_field($_POST['expiry_date']) : null,
            'status' => sanitize_text_field($_POST['status'] ?? 'pending'),
            'notes' => sanitize_textarea_field($_POST['notes'] ?? '')
        );
        
        $license_id = VICS_Database::add_license($user_id, $data);
        
        // Update agent code if provided (admin-set agent codes are auto-approved)
        $profile_updates = array();
        
        if (!empty($_POST['agent_code'])) {
            $profile_updates['agent_code'] = sanitize_text_field($_POST['agent_code']);
            $profile_updates['agent_code_status'] = 'approved';
        }
        
        // Sync license number to profile (same as frontend behavior)
        if (!empty($_POST['license_number'])) {
            $profile_updates['license_number'] = sanitize_text_field($_POST['license_number']);
        }
        
        if (!empty($profile_updates)) {
            VICS_Database::update_profile($user_id, $profile_updates);
        }
        
        if ($license_id) {
            wp_send_json_success(array('message' => __('License added successfully!')));
        } else {
            wp_send_json_error(array('message' => __('Failed to add license')));
        }
    }
    
    /**
     * Admin Update License (AJAX)
     */
    public function admin_update_license() {
        check_ajax_referer('vics_admin_license', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.')));
        }
        
        $license_id = intval($_POST['license_id']);
        
        if (!$license_id) {
            wp_send_json_error(array('message' => __('Invalid license ID')));
        }
        
        // Get the license to find user_id
        global $wpdb;
        $table = $wpdb->prefix . 'vics_agent_licenses';
        $existing_license = $wpdb->get_row($wpdb->prepare(
            "SELECT user_id FROM $table WHERE id = %d",
            $license_id
        ));
        
        if (!$existing_license) {
            wp_send_json_error(array('message' => __('License not found')));
        }
        
        $data = array(
            'license_type' => '',
            'license_state' => sanitize_text_field($_POST['license_state']),
            'license_number' => sanitize_text_field($_POST['license_number'] ?? ''),
            'issue_date' => !empty($_POST['issue_date']) ? sanitize_text_field($_POST['issue_date']) : null,
            'expiry_date' => !empty($_POST['expiry_date']) ? sanitize_text_field($_POST['expiry_date']) : null,
            'status' => sanitize_text_field($_POST['status'] ?? 'pending'),
            'notes' => sanitize_textarea_field($_POST['notes'] ?? '')
        );
        
        // Admin can update any license (user_id = 0)
        $result = VICS_Database::update_license($license_id, 0, $data);
        
        // Update agent code and license number in profile
        $profile_updates = array();
        
        if (isset($_POST['agent_code'])) {
            $profile_updates['agent_code'] = sanitize_text_field($_POST['agent_code']);
            $profile_updates['agent_code_status'] = 'approved';
        }
        
        // Sync license number to profile (same as frontend behavior)
        if (isset($_POST['license_number'])) {
            $profile_updates['license_number'] = sanitize_text_field($_POST['license_number']);
        }
        
        if (!empty($profile_updates)) {
            VICS_Database::update_profile($existing_license->user_id, $profile_updates);
        }
        
        if ($result !== false) {
            wp_send_json_success(array('message' => __('License updated successfully!')));
        } else {
            wp_send_json_error(array('message' => __('Failed to update license')));
        }
    }
    
    /**
     * Admin Delete License (AJAX)
     */
    public function admin_delete_license() {
        check_ajax_referer('vics_admin_license', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.')));
        }
        
        $license_id = intval($_POST['license_id']);
        
        if (!$license_id) {
            wp_send_json_error(array('message' => __('Invalid license ID')));
        }
        
        // Admin can delete any license (user_id = 0)
        $result = VICS_Database::delete_license($license_id, 0);
        
        if ($result !== false) {
            wp_send_json_success(array('message' => __('License deleted successfully!')));
        } else {
            wp_send_json_error(array('message' => __('Failed to delete license')));
        }
    }
    
    /**
     * Approve Agent Code (AJAX)
     */
    public function approve_agent_code() {
        check_ajax_referer('vics_admin_agent_code', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.')));
        }
        
        $user_id = intval($_POST['user_id']);
        
        if (!$user_id) {
            wp_send_json_error(array('message' => __('Invalid user ID')));
        }
        
        VICS_Database::update_profile($user_id, array('agent_code_status' => 'approved'));
        
        wp_send_json_success(array('message' => __('Agent code approved successfully!')));
    }
    
    /**
     * Reject Agent Code (AJAX)
     */
    public function reject_agent_code() {
        check_ajax_referer('vics_admin_agent_code', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.')));
        }
        
        $user_id = intval($_POST['user_id']);
        
        if (!$user_id) {
            wp_send_json_error(array('message' => __('Invalid user ID')));
        }
        
        VICS_Database::update_profile($user_id, array('agent_code_status' => 'rejected'));
        
        wp_send_json_success(array('message' => __('Agent code rejected. Agent can resubmit.')));
    }
    
    /**
     * Add user columns
     */
    public function add_user_columns($columns) {
        $columns['vics_orientation'] = __('Orientation', 'vics');
        $columns['vics_agent_code'] = __('Agent Code', 'vics');
        return $columns;
    }
    
    /**
     * User column content
     */
    public function user_column_content($value, $column_name, $user_id) {
        if ($column_name === 'vics_orientation') {
            $completed = VICS_Database::has_completed_orientation($user_id);
            return $completed 
                ? '<span style="color:green;">✓</span>' 
                : '<span style="color:orange;">⏳</span>';
        }
        
        if ($column_name === 'vics_agent_code') {
            $profile = VICS_Database::get_profile($user_id);
            return esc_html($profile['agent_code'] ?? '-');
        }
        
        return $value;
    }
    
    /**
     * Add user row actions
     */
    public function add_user_actions($actions, $user) {
        if (current_user_can('manage_options')) {
            $url = wp_nonce_url(
                admin_url('users.php?action=vics_reset_orientation&user_id=' . $user->ID),
                'vics_reset_orientation_' . $user->ID
            );
            $actions['reset_orientation'] = '<a href="' . esc_url($url) . '">' . 
                __('Reset Orientation', 'vics') . '</a>';
        }
        return $actions;
    }
    
    /**
     * Handle reset orientation
     */
    public function handle_reset_orientation() {
        if (!isset($_GET['action']) || $_GET['action'] !== 'vics_reset_orientation') {
            return;
        }
        
        $user_id = intval($_GET['user_id'] ?? 0);
        
        if (!$user_id || !isset($_GET['_wpnonce'])) {
            return;
        }
        
        if (!wp_verify_nonce($_GET['_wpnonce'], 'vics_reset_orientation_' . $user_id)) {
            wp_die(__('Security check failed', 'vics'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'vics'));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'vics_orientation_progress';
        $wpdb->delete($table, array('user_id' => $user_id));
        
        wp_redirect(admin_url('users.php?orientation_reset=1'));
        exit;
    }
    
    /**
     * Handle Google OAuth callback
     */
    public function handle_google_auth() {
        if (!isset($_GET['code']) || !isset($_GET['state'])) {
            return;
        }

        // Expect state in format 'vics_google_auth:NONCE'
        if (strpos($_GET['state'], 'vics_google_auth:') !== 0) {
            return;
        }

        // Verify nonce
        $parts = explode(':', $_GET['state'], 2);
        $nonce = $parts[1] ?? '';
        if (empty($nonce) || !wp_verify_nonce($nonce, 'vics_google_auth')) {
            // Invalid state/nonce
            $redirect_url = admin_url('admin.php?page=vics-settings&tab=google');
            $redirect_url = add_query_arg('google_auth', 'error', $redirect_url);
            wp_redirect($redirect_url);
            exit;
        }

        $auth = new VICS_Google_Auth();
        $redirect_uri = admin_url('admin.php?page=vics-settings&tab=google');
        $result = $auth->handle_callback($_GET['code'], $redirect_uri);

        $redirect_url = admin_url('admin.php?page=vics-settings&tab=google');

        if ($result) {
            $redirect_url = add_query_arg('google_auth', 'success', $redirect_url);
        } else {
            $redirect_url = add_query_arg('google_auth', 'error', $redirect_url);
        }

        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Handle Google sync actions
     */
    public function handle_google_sync_actions() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'vics-settings' || !isset($_GET['tab']) || $_GET['tab'] !== 'google') {
            return;
        }
        
        // Handle revoke authentication
        if (isset($_GET['action']) && $_GET['action'] === 'revoke') {
            if (!wp_verify_nonce($_GET['_wpnonce'], 'revoke_google_auth')) {
                wp_die(__('Security check failed', 'vics'));
            }
            
            $auth = new VICS_Google_Auth();
            $auth->revoke_auth();
            
            wp_redirect(admin_url('admin.php?page=vics-settings&tab=google&revoked=1'));
            exit;
        }
        
        // Handle sync master tracker
        if (isset($_GET['action']) && $_GET['action'] === 'sync_master') {
            if (!wp_verify_nonce($_GET['_wpnonce'], 'sync_master_tracker')) {
                wp_die(__('Security check failed', 'vics'));
            }
            
            $sync = new VICS_Google_Sync();
            $result = $sync->sync_all_to_master();
            
            $redirect_url = admin_url('admin.php?page=vics-settings&tab=google');
            if ($result) {
                $redirect_url = add_query_arg('sync_master', 'success', $redirect_url);
            } else {
                $redirect_url = add_query_arg('sync_master', 'error', $redirect_url);
            }
            
            wp_redirect($redirect_url);
            exit;
        }
        
        // Handle create agent sheet
        if (isset($_GET['action']) && $_GET['action'] === 'create_agent_sheet' && isset($_GET['user_id'])) {
            if (!wp_verify_nonce($_GET['_wpnonce'], 'create_agent_sheet')) {
                wp_die(__('Security check failed', 'vics'));
            }
            
            $user_id = intval($_GET['user_id']);
            $sync = new VICS_Google_Sync();
            $result = $sync->create_agent_sheet($user_id);
            
            $redirect_url = admin_url('admin.php?page=vics-agents');
            if ($result) {
                $redirect_url = add_query_arg('sheet_created', 'success', $redirect_url);
            } else {
                $redirect_url = add_query_arg('sheet_created', 'error', $redirect_url);
            }
            
            wp_redirect($redirect_url);
            exit;
        }
        
        // Handle test connection
        if (isset($_GET['action']) && $_GET['action'] === 'test_connection') {
            if (!wp_verify_nonce($_GET['_wpnonce'], 'test_google_connection')) {
                wp_die(__('Security check failed', 'vics'));
            }
            
            $auth = new VICS_Google_Auth();
            $test_result = $auth->is_authenticated();
            
            $redirect_url = admin_url('admin.php?page=vics-settings&tab=google');
            if ($test_result) {
                $redirect_url = add_query_arg('test_connection', 'success', $redirect_url);
            } else {
                $redirect_url = add_query_arg('test_connection', 'error', $redirect_url);
            }
            
            wp_redirect($redirect_url);
            exit;
        }
    }
    
    /**
     * Handle debug log actions
     */
    public function handle_debug_log_actions() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'vics-settings' || !isset($_GET['tab']) || $_GET['tab'] !== 'debug') {
            return;
        }
        
        // Handle clear debug log
        if (isset($_GET['action']) && $_GET['action'] === 'clear_debug_log') {
            if (!wp_verify_nonce($_GET['_wpnonce'], 'clear_debug_log')) {
                wp_die(__('Security check failed', 'vics'));
            }
            
            if (!current_user_can('manage_options')) {
                wp_die(__('You do not have permission to perform this action.', 'vics'));
            }
            
            $log_file = WP_CONTENT_DIR . '/debug.log';
            if (file_exists($log_file)) {
                if (unlink($log_file)) {
                    wp_redirect(admin_url('admin.php?page=vics-settings&tab=debug&log_cleared=1'));
                } else {
                    wp_redirect(admin_url('admin.php?page=vics-settings&tab=debug&log_cleared=error'));
                }
            } else {
                wp_redirect(admin_url('admin.php?page=vics-settings&tab=debug&log_cleared=1'));
            }
            exit;
        }
    }
}