<?php
/**
 * Events Management
 *
 * @package VisionImpactCustomSolutions
 * @since 1.1.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class VICS_Events {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('init', array($this, 'add_single_rewrite_rule'), 20);
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_boxes'));
        add_filter('manage_events_posts_columns', array($this, 'add_admin_columns'));
        add_action('manage_events_posts_custom_column', array($this, 'admin_column_content'), 10, 2);
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_filter('single_template', array($this, 'load_single_event_template'));
        add_shortcode('events_archive', array($this, 'render_events_archive'));

        // AJAX handlers
        add_action('wp_ajax_search_events', array($this, 'ajax_search_events'));
        add_action('wp_ajax_nopriv_search_events', array($this, 'ajax_search_events'));
    }

    /**
     * Register Events Post Type
     */
    public function register_post_type() {
        $labels = array(
            'name'               => __('Events', 'vics'),
            'singular_name'      => __('Event', 'vics'),
            'menu_name'          => __('Events', 'vics'),
            'name_admin_bar'     => __('Event', 'vics'),
            'add_new'            => __('Add New', 'vics'),
            'add_new_item'       => __('Add New Event', 'vics'),
            'new_item'           => __('New Event', 'vics'),
            'edit_item'          => __('Edit Event', 'vics'),
            'view_item'          => __('View Event', 'vics'),
            'all_items'          => __('All Events', 'vics'),
            'search_items'       => __('Search Events', 'vics'),
            'parent_item_colon'  => __('Parent Events:', 'vics'),
            'not_found'          => __('No events found.', 'vics'),
            'not_found_in_trash' => __('No events found in Trash.', 'vics')
        );

        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array('slug' => 'events'),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => null,
            'menu_icon'          => 'dashicons-calendar-alt',
            'supports'           => array('title', 'editor', 'thumbnail'),
            'show_in_rest'       => true,
        );

        register_post_type('events', $args);
    }

    private function get_pacific_timezone() {
        return new DateTimeZone('America/Los_Angeles');
    }

    private function get_pacific_today() {
        $now = new DateTime('now', $this->get_pacific_timezone());
        return $now->format('Y-m-d');
    }

    private function get_pacific_week_range() {
        $now = new DateTime('now', $this->get_pacific_timezone());
        $start = clone $now;
        $start->modify('monday this week');

        $end = clone $now;
        $end->modify('sunday this week');

        return array(
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d')
        );
    }

    private function format_event_time_pacific($date_value, $time_value) {
        if (empty($date_value) || empty($time_value)) {
            return '';
        }

        $timezone = $this->get_pacific_timezone();
        $date_obj = new DateTime($date_value, $timezone);
        $time_obj = DateTime::createFromFormat('H:i', $time_value, $timezone);

        if (!$time_obj) {
            $time_obj = new DateTime($time_value, $timezone);
        }

        return $date_obj->format('l, M j') . ' — ' . $time_obj->format('g:i A') . ' PST';
    }

    private function format_time_only_pacific($time_value) {
        if (empty($time_value)) {
            return '';
        }

        $timezone = $this->get_pacific_timezone();
        $time_obj = DateTime::createFromFormat('H:i', $time_value, $timezone);

        if (!$time_obj) {
            $time_obj = new DateTime($time_value, $timezone);
        }

        return $time_obj->format('g:i A') . ' PST';
    }

    public function add_single_rewrite_rule() {
        add_rewrite_rule(
            '^events/([^/]+)/?$',
            'index.php?post_type=events&name=$matches[1]',
            'top'
        );
    }

    /**
     * Add Meta Boxes
     */
    public function add_meta_boxes() {
        add_meta_box(
            'event_details',
            __('Event Details', 'vics'),
            array($this, 'render_event_details_meta_box'),
            'events',
            'normal',
            'high'
        );
    }

    /**
     * Render Event Details Meta Box
     */
    public function render_event_details_meta_box($post) {
        wp_nonce_field('vics_event_details_nonce', 'vics_event_details_nonce');

        $start_date = get_post_meta($post->ID, '_event_start_date', true);
        $start_time = get_post_meta($post->ID, '_event_start_time', true);
        $end_date = get_post_meta($post->ID, '_event_end_date', true);
        $end_time = get_post_meta($post->ID, '_event_end_time', true);
        $event_type = get_post_meta($post->ID, '_event_type', true);
        $address = get_post_meta($post->ID, '_event_address', true);
        $map_link = get_post_meta($post->ID, '_event_map_link', true);
        $virtual_link = get_post_meta($post->ID, '_event_virtual_link', true);

        ?>
        <table class="form-table">
            <tr>
                <th><label for="event_start_date"><?php _e('Start Date & Time', 'vics'); ?></label></th>
                <td>
                    <input type="date" id="event_start_date" name="event_start_date" value="<?php echo esc_attr($start_date); ?>" required>
                    <input type="time" id="event_start_time" name="event_start_time" value="<?php echo esc_attr($start_time); ?>">
                </td>
            </tr>
            <tr>
                <th><label for="event_end_date"><?php _e('End Date & Time', 'vics'); ?></label></th>
                <td>
                    <input type="date" id="event_end_date" name="event_end_date" value="<?php echo esc_attr($end_date); ?>">
                    <input type="time" id="event_end_time" name="event_end_time" value="<?php echo esc_attr($end_time); ?>">
                </td>
            </tr>
            <tr>
                <th><label for="event_type"><?php _e('Event Type', 'vics'); ?></label></th>
                <td>
                    <select id="event_type" name="event_type" required>
                        <option value=""><?php _e('Select Event Type', 'vics'); ?></option>
                        <option value="physical" <?php selected($event_type, 'physical'); ?>><?php _e('Physical', 'vics'); ?></option>
                        <option value="virtual" <?php selected($event_type, 'virtual'); ?>><?php _e('Virtual', 'vics'); ?></option>
                    </select>
                </td>
            </tr>
        </table>

        <div id="physical_fields" style="display: <?php echo $event_type === 'physical' ? 'block' : 'none'; ?>;">
            <h4><?php _e('Physical Event Details', 'vics'); ?></h4>
            <table class="form-table">
                <tr>
                    <th><label for="event_address"><?php _e('Address', 'vics'); ?></label></th>
                    <td>
                        <textarea id="event_address" name="event_address" rows="3" cols="50"><?php echo esc_textarea($address); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th><label for="event_map_link"><?php _e('Map Link', 'vics'); ?></label></th>
                    <td>
                        <input type="url" id="event_map_link" name="event_map_link" value="<?php echo esc_url($map_link); ?>" placeholder="https://maps.google.com/...">
                    </td>
                </tr>
            </table>
        </div>

        <div id="virtual_fields" style="display: <?php echo $event_type === 'virtual' ? 'block' : 'none'; ?>;">
            <h4><?php _e('Virtual Event Details', 'vics'); ?></h4>
            <table class="form-table">
                <tr>
                    <th><label for="event_virtual_link"><?php _e('Meeting Link', 'vics'); ?></label></th>
                    <td>
                        <input type="url" id="event_virtual_link" name="event_virtual_link" value="<?php echo esc_url($virtual_link); ?>" placeholder="https://zoom.us/...">
                        <p class="description"><?php _e('Enter the Zoom, Google Meet, or other virtual meeting platform link.', 'vics'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#event_type').change(function() {
                var eventType = $(this).val();
                if (eventType === 'physical') {
                    $('#physical_fields').show();
                    $('#virtual_fields').hide();
                } else if (eventType === 'virtual') {
                    $('#physical_fields').hide();
                    $('#virtual_fields').show();
                } else {
                    $('#physical_fields').hide();
                    $('#virtual_fields').hide();
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Save Meta Boxes
     */
    public function save_meta_boxes($post_id) {
        if (!isset($_POST['vics_event_details_nonce']) || !wp_verify_nonce($_POST['vics_event_details_nonce'], 'vics_event_details_nonce')) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Save event details
        $fields = array(
            'event_start_date',
            'event_start_time',
            'event_end_date',
            'event_end_time',
            'event_type',
            'event_address',
            'event_map_link',
            'event_virtual_link'
        );

        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                $value = sanitize_text_field($_POST[$field]);
                if ($field === 'event_map_link' || $field === 'event_virtual_link') {
                    $value = esc_url_raw($_POST[$field]);
                }
                update_post_meta($post_id, '_' . $field, $value);
            }
        }
    }

    /**
     * Add Admin Columns
     */
    public function add_admin_columns($columns) {
        $new_columns = array();
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['event_date'] = __('Event Date', 'vics');
                $new_columns['event_time'] = __('Event Time (PST)', 'vics');
                $new_columns['event_type'] = __('Type', 'vics');
            }
        }
        return $new_columns;
    }

    /**
     * Admin Column Content
     */
    public function admin_column_content($column, $post_id) {
        switch ($column) {
            case 'event_date':
                $start_date = get_post_meta($post_id, '_event_start_date', true);
                if ($start_date) {
                    $date = new DateTime($start_date, $this->get_pacific_timezone());
                    echo esc_html($date->format('M j, Y'));
                } else {
                    echo __('—', 'vics');
                }
                break;

            case 'event_type':
                $event_type = get_post_meta($post_id, '_event_type', true);
                if ($event_type) {
                    $type_label = $event_type === 'virtual' ? __('Virtual', 'vics') : __('Physical', 'vics');
                    echo esc_html($type_label);
                } else {
                    echo __('—', 'vics');
                }
                break;

            case 'event_time':
                $start_time = get_post_meta($post_id, '_event_start_time', true);
                $end_time = get_post_meta($post_id, '_event_end_time', true);

                $start_time_display = $this->format_time_only_pacific($start_time);
                $end_time_display = $this->format_time_only_pacific($end_time);

                if ($start_time_display && $end_time_display && $start_time !== $end_time) {
                    echo esc_html($start_time_display . ' - ' . $end_time_display);
                } elseif ($start_time_display) {
                    echo esc_html($start_time_display);
                } elseif ($end_time_display) {
                    echo esc_html($end_time_display);
                } else {
                    echo __('—', 'vics');
                }
                break;
        }
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        if (is_singular('events')) {
            wp_enqueue_style('vics-single-event', VICS_PLUGIN_URL . 'assets/css/single-event.css', array(), VICS_VERSION);
        }

        if (has_shortcode(get_post()->post_content ?? '', 'events_archive')) {
            wp_enqueue_style('vics-events-archive', VICS_PLUGIN_URL . 'assets/css/events-archive.css', array(), VICS_VERSION);
            wp_enqueue_script('vics-events-archive', VICS_PLUGIN_URL . 'assets/js/events-archive.js', array('jquery'), VICS_VERSION, true);

            wp_localize_script('vics-events-archive', 'vics_events_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('vics_events_nonce')
            ));
        }
    }

    /**
     * Render Events Archive Shortcode
     */
    public function render_events_archive($atts) {
        ob_start();

        $atts = shortcode_atts(array(
            'show_past' => false
        ), $atts);

        $upcoming_events = $this->get_events_for_archive(false);
        $current_month_events = $this->get_current_month_events();
        $week_events = $this->get_week_events();

        ?>
        <section class="hero-section">
            <div class="hero-content">
                <h1>Events</h1>
                <div class="search-box">
                    <span class="search-icon">🔍</span>
                    <input type="text" id="events-search" placeholder="Search events by name or trainer...">
                </div>
            </div>
        </section>

        <section class="content-section">
            <div class="container">
                <h2>Upcoming Events</h2>

                <div class="events-list" id="events-list">
                    <?php foreach ($upcoming_events as $event): ?>
                        <?php
                        $event_id = $event->ID;
                        $start_date = get_post_meta($event_id, '_event_start_date', true);
                        $start_time = get_post_meta($event_id, '_event_start_time', true);
                        $event_type = get_post_meta($event_id, '_event_type', true);
                        $virtual_link = get_post_meta($event_id, '_event_virtual_link', true);

                        $date_obj = new DateTime($start_date, $this->get_pacific_timezone());
                        $day = $date_obj->format('j');
                        $month = $date_obj->format('M Y');

                        $time_display = $this->format_event_time_pacific($start_date, $start_time);
                        ?>
                        <div class="event-card" data-event-id="<?php echo $event_id; ?>">
                            <div class="event-date">
                                <span class="day"><?php echo $day; ?></span>
                                <span class="month"><?php echo $month; ?></span>
                            </div>
                            <div class="event-image">
                                <?php if (has_post_thumbnail($event_id)): ?>
                                    <?php echo get_the_post_thumbnail($event_id, 'medium'); ?>
                                <?php else: ?>
                                    <svg width="100%" height="100%" viewBox="0 0 180 120">
                                        <rect fill="#8896ab" width="180" height="120"/>
                                    </svg>
                                <?php endif; ?>
                            </div>
                            <div class="event-details">
                                <h3><a href="<?php echo get_permalink($event_id); ?>"><?php echo get_the_title($event_id); ?></a></h3>
                                <p class="time"><?php echo $time_display; ?></p>
                                <p class="description"><?php echo $this->trim_excerpt($event_id, 10); ?></p>
                            </div>
                            <?php if ($event_type === 'virtual' && $virtual_link): ?>
                                <a href="<?php echo esc_url($virtual_link); ?>" class="join-btn" target="_blank">JOIN EVENT →</a>
                            <?php else: ?>
                                <a href="<?php echo get_permalink($event_id); ?>" class="join-btn">VIEW DETAILS →</a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="bottom-section">
                    <div class="calendar-widget">
                        <h3>Monthly Calendar</h3>
                        <div class="calendar">
                            <?php echo $this->render_calendar_widget(); ?>
                        </div>
                    </div>

                    <div class="week-events">
                        <h3>This Week at a Glance</h3>
                        <?php foreach ($week_events as $event): ?>
                            <?php
                            $event_id = $event->ID;
                            $start_date = get_post_meta($event_id, '_event_start_date', true);
                            $start_time = get_post_meta($event_id, '_event_start_time', true);

                            $date_obj = new DateTime($start_date, $this->get_pacific_timezone());
                            $day = $date_obj->format('j');
                            $month = $date_obj->format('M Y');

                            $time_display = $this->format_event_time_pacific($start_date, $start_time);
                            ?>
                            <div class="mini-event">
                                <div class="mini-date">
                                    <span class="day"><?php echo $day; ?></span>
                                    <span class="month"><?php echo $month; ?></span>
                                </div>
                                <div class="mini-details">
                                    <h4><?php echo get_the_title($event_id); ?></h4>
                                    <p class="time"><?php echo $time_display; ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>
        <?php

        return ob_get_clean();
    }

    /**
     * Load custom single event template
     */
    public function load_single_event_template($template) {
        global $post;

        if ($post && $post->post_type === 'events') {
            $custom_template = VICS_PLUGIN_PATH . 'templates/single-event.php';
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }

        return $template;
    }

    /**     * Get events for archive display
     */
    private function get_events_for_archive($show_past = false) {
        $args = array(
            'post_type' => 'events',
            'posts_per_page' => 8,
            'meta_key' => '_event_start_date',
            'orderby' => 'meta_value',
            'order' => 'ASC',
            'meta_query' => array(
                array(
                    'key' => '_event_start_date',
                    'value' => $this->get_pacific_today(),
                    'compare' => $show_past ? '<=' : '>=',
                    'type' => 'DATE'
                )
            )
        );

        return get_posts($args);
    }

    /**
     * Get current month events for calendar
     */
    private function get_current_month_events() {
        $now = new DateTime('now', $this->get_pacific_timezone());
        $current_month = $now->format('m');
        $current_year = $now->format('Y');

        $args = array(
            'post_type' => 'events',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_event_start_date',
                    'value' => $current_year . '-' . $current_month . '-01',
                    'compare' => '>=',
                    'type' => 'DATE'
                ),
                array(
                    'key' => '_event_start_date',
                    'value' => $current_year . '-' . $current_month . '-31',
                    'compare' => '<=',
                    'type' => 'DATE'
                )
            )
        );

        return get_posts($args);
    }

    /**
     * Get this week's events
     */
    private function get_week_events() {
        $week_range = $this->get_pacific_week_range();
        $start_of_week = $week_range['start'];
        $end_of_week = $week_range['end'];

        $args = array(
            'post_type' => 'events',
            'posts_per_page' => 4,
            'meta_key' => '_event_start_date',
            'orderby' => 'meta_value',
            'order' => 'ASC',
            'meta_query' => array(
                array(
                    'key' => '_event_start_date',
                    'value' => $start_of_week,
                    'compare' => '>=',
                    'type' => 'DATE'
                ),
                array(
                    'key' => '_event_start_date',
                    'value' => $end_of_week,
                    'compare' => '<=',
                    'type' => 'DATE'
                )
            )
        );

        return get_posts($args);
    }

    /**
     * Trim excerpt to specified word count
     */
    private function trim_excerpt($post_id, $word_count = 10) {
        $excerpt = get_the_excerpt($post_id);
        if (!$excerpt) {
            $excerpt = get_post_field('post_content', $post_id);
            $excerpt = strip_tags($excerpt);
        }

        $words = explode(' ', $excerpt);
        if (count($words) > $word_count) {
            $words = array_slice($words, 0, $word_count);
            $excerpt = implode(' ', $words) . '...';
        }

        return $excerpt;
    }

    /**
     * Render calendar widget
     */
    private function render_calendar_widget() {
        $now = new DateTime('now', $this->get_pacific_timezone());
        $current_month = (int) $now->format('n');
        $current_year = (int) $now->format('Y');
        $current_day = (int) $now->format('j');

        $month_events = $this->get_current_month_events();
        $event_days = array();

        foreach ($month_events as $event) {
            $event_date = get_post_meta($event->ID, '_event_start_date', true);
            if ($event_date) {
                $event_date_obj = new DateTime($event_date, $this->get_pacific_timezone());
                $day = $event_date_obj->format('j');
                $event_days[] = (int)$day;
            }
        }

        $days_in_month = cal_days_in_month(CAL_GREGORIAN, $current_month, $current_year);
        $first_day_of_month = (int) (new DateTime($current_year . '-' . $current_month . '-01', $this->get_pacific_timezone()))->format('w');

        ob_start();
        ?>
        <div class="calendar-header">
            <div>Sun</div>
            <div>Mon</div>
            <div>Tue</div>
            <div>Wed</div>
            <div>Thu</div>
            <div>Fri</div>
            <div>Sat</div>
        </div>
        <div class="calendar-grid">
            <?php
            // Empty cells for previous month
            for ($i = 0; $i < $first_day_of_month; $i++) {
                echo '<div class="calendar-day empty"></div>';
            }

            // Days of current month
            for ($day = 1; $day <= $days_in_month; $day++) {
                $is_today = ($day == $current_day) ? ' active' : '';
                $has_event = in_array($day, $event_days) ? ' has-event' : '';
                echo '<div class="calendar-day' . $is_today . $has_event . '">' . $day . '</div>';
            }

            // Fill remaining cells
            $total_cells = $first_day_of_month + $days_in_month;
            $remaining_cells = 42 - $total_cells; // 6 rows * 7 days

            for ($i = 0; $i < $remaining_cells; $i++) {
                echo '<div class="calendar-day empty"></div>';
            }
            ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * AJAX handler for search events
     */
    public function ajax_search_events() {
        check_ajax_referer('vics_events_nonce', 'nonce');

        $search_term = sanitize_text_field($_POST['search_term']);

        $args = array(
            'post_type' => 'events',
            'posts_per_page' => 8,
            's' => $search_term,
            'meta_query' => array(
                array(
                    'key' => '_event_start_date',
                    'value' => $this->get_pacific_today(),
                    'compare' => '>=',
                    'type' => 'DATE'
                )
            )
        );

        $events = get_posts($args);
        $html = '';

        foreach ($events as $event) {
            $event_id = $event->ID;
            $start_date = get_post_meta($event_id, '_event_start_date', true);
            $start_time = get_post_meta($event_id, '_event_start_time', true);
            $event_type = get_post_meta($event_id, '_event_type', true);
            $virtual_link = get_post_meta($event_id, '_event_virtual_link', true);

            $date_obj = new DateTime($start_date, $this->get_pacific_timezone());
            $day = $date_obj->format('j');
            $month = $date_obj->format('M Y');

            $time_display = $this->format_event_time_pacific($start_date, $start_time);

            $html .= '<div class="event-card" data-event-id="' . $event_id . '">';
            $html .= '<div class="event-date">';
            $html .= '<span class="day">' . $day . '</span>';
            $html .= '<span class="month">' . $month . '</span>';
            $html .= '</div>';
            $html .= '<div class="event-image">';
            if (has_post_thumbnail($event_id)) {
                $html .= get_the_post_thumbnail($event_id, 'medium');
            } else {
                $html .= '<svg width="100%" height="100%" viewBox="0 0 180 120"><rect fill="#8896ab" width="180" height="120"/></svg>';
            }
            $html .= '</div>';
            $html .= '<div class="event-details">';
            $html .= '<h3><a href="' . get_permalink($event_id) . '">' . get_the_title($event_id) . '</a></h3>';
            $html .= '<p class="time">' . $time_display . '</p>';
            $html .= '<p class="description">' . $this->trim_excerpt($event_id, 10) . '</p>';
            $html .= '</div>';
            if ($event_type === 'virtual' && $virtual_link) {
                $html .= '<a href="' . esc_url($virtual_link) . '" class="join-btn" target="_blank">JOIN EVENT →</a>';
            } else {
                $html .= '<a href="' . get_permalink($event_id) . '" class="join-btn">VIEW DETAILS →</a>';
            }
            $html .= '</div>';
        }

        if (empty($html)) {
            $html = '<p class="no-events">No events found matching your search.</p>';
        }

        wp_send_json_success(array('html' => $html));
    }
}

// Initialize Events
new VICS_Events();