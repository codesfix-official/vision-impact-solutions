<?php
/**
 * Single Event Template
 *
 * @package VisionImpactCustomSolutions
 * @since 1.1.1
 */

get_header();

if (have_posts()) :
    while (have_posts()) :
        the_post();
        $pst_timezone = new DateTimeZone('America/Los_Angeles');
        $pst_now = new DateTime('now', $pst_timezone);
        $today_pst = $pst_now->format('Y-m-d');

        $parse_pst_date = function ($date_value) use ($pst_timezone) {
            if (empty($date_value)) {
                return null;
            }

            return new DateTime($date_value, $pst_timezone);
        };

        $parse_pst_time = function ($time_value) use ($pst_timezone) {
            if (empty($time_value)) {
                return null;
            }

            $time_obj = DateTime::createFromFormat('H:i', $time_value, $pst_timezone);
            if (!$time_obj) {
                $time_obj = new DateTime($time_value, $pst_timezone);
            }

            return $time_obj;
        };

        $event_id = get_the_ID();
        $start_date = get_post_meta($event_id, '_event_start_date', true);
        $start_time = get_post_meta($event_id, '_event_start_time', true);
        $end_date = get_post_meta($event_id, '_event_end_date', true);
        $end_time = get_post_meta($event_id, '_event_end_time', true);
        $event_type = get_post_meta($event_id, '_event_type', true);
        $address = get_post_meta($event_id, '_event_address', true);
        $map_link = get_post_meta($event_id, '_event_map_link', true);
        $virtual_link = get_post_meta($event_id, '_event_virtual_link', true);

        // Get featured image
        $featured_image_url = get_the_post_thumbnail_url($event_id, 'full');
        
        // Format dates
        $date_display = '';
        if ($start_date) {
            $start = $parse_pst_date($start_date);
            $date_display = $start->format('l, F j, Y');

            if ($start_time) {
                $time = $parse_pst_time($start_time);
                $date_display .= ' at ' . $time->format('g:i A') . ' PST';
            }

            if ($end_date && $end_date !== $start_date) {
                $end = $parse_pst_date($end_date);
                $date_display .= ' - ' . $end->format('l, F j, Y');
                if ($end_time) {
                    $time = $parse_pst_time($end_time);
                    $date_display .= ' at ' . $time->format('g:i A') . ' PST';
                }
            } elseif ($end_time && $end_time !== $start_time) {
                $time = $parse_pst_time($end_time);
                $date_display .= ' - ' . $time->format('g:i A') . ' PST';
            }
        }
        ?>

        <div class="single-event">
            <!-- Hero Section with Featured Image -->
            <div class="event-hero" style="background-image: url('<?php echo esc_url($featured_image_url ?: 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100"><rect fill="%234a6fa5" width="100" height="100"/></svg>'); ?>');">
                <div class="event-hero-overlay"></div>
                <div class="event-hero-content">
                    <div class="event-hero-inner">
                        <div class="event-type-badge <?php echo esc_attr($event_type); ?>">
                            <?php echo $event_type === 'virtual' ? 'Virtual Event' : 'Physical Event'; ?>
                        </div>
                        <h1 class="event-hero-title"><?php the_title(); ?></h1>
                        <div class="event-hero-meta">
                            <span class="event-date-time">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                    <line x1="16" y1="2" x2="16" y2="6"></line>
                                    <line x1="8" y1="2" x2="8" y2="6"></line>
                                    <line x1="3" y1="10" x2="21" y2="10"></line>
                                </svg>
                                <?php echo esc_html($date_display); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="event-main-wrapper">
                <div class="container event-container">
                    <div class="event-content-grid">
                        <!-- Main Content -->
                        <main class="event-main-content">
                            <div class="event-content-box">
                                <h2>About This Event</h2>
                                <div class="event-description">
                                    <?php the_content(); ?>
                                </div>
                            </div>

                            <!-- Event Details -->
                            <div class="event-info-box">
                                <h2>Event Information</h2>
                                <div class="event-info-grid">
                                    <div class="info-item">
                                        <h4>Date & Time</h4>
                                        <p><?php echo esc_html($date_display); ?></p>
                                    </div>

                                    <div class="info-item">
                                        <h4>Event Type</h4>
                                        <p><?php echo $event_type === 'virtual' ? 'Virtual Event' : 'Physical Event'; ?></p>
                                    </div>

                                    <?php if ($event_type === 'physical' && $address) : ?>
                                        <div class="info-item">
                                            <h4>Location</h4>
                                            <p><?php echo wp_kses_post(nl2br($address)); ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="event-actions">
                                <?php if ($event_type === 'virtual' && $virtual_link) : ?>
                                    <a href="<?php echo esc_url($virtual_link); ?>" target="_blank" class="btn btn-primary">
                                        <span>Join Virtual Meeting</span>
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                            <polyline points="15 3 21 3 21 9"></polyline>
                                            <line x1="10" y1="14" x2="21" y2="3"></line>
                                        </svg>
                                    </a>
                                <?php endif; ?>

                                <?php if ($event_type === 'physical' && $map_link) : ?>
                                    <a href="<?php echo esc_url($map_link); ?>" target="_blank" class="btn btn-secondary">
                                        <span>View on Map</span>
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                            <circle cx="12" cy="10" r="3"></circle>
                                        </svg>
                                    </a>
                                <?php endif; ?>
                            </div>

                        </main>

                        <!-- Sidebar -->
                        <aside class="event-sidebar">
                            <!-- This Week's Events -->
                            <div class="sidebar-widget">
                                <h3>This Week's Events</h3>
                                <?php
                                $start_of_week_obj = clone $pst_now;
                                $start_of_week_obj->modify('monday this week');
                                $end_of_week_obj = clone $pst_now;
                                $end_of_week_obj->modify('sunday this week');

                                $start_of_week = $start_of_week_obj->format('Y-m-d');
                                $end_of_week = $end_of_week_obj->format('Y-m-d');

                                $week_args = array(
                                    'post_type' => 'events',
                                    'posts_per_page' => 5,
                                    'meta_key' => '_event_start_date',
                                    'orderby' => 'meta_value',
                                    'order' => 'ASC',
                                    'meta_query' => array(
                                        'relation' => 'AND',
                                        array(
                                            'key' => '_event_start_date',
                                            'value' => array($start_of_week, $end_of_week),
                                            'compare' => 'BETWEEN',
                                            'type' => 'DATE'
                                        )
                                    )
                                );

                                $week_events = get_posts($week_args);

                                if (!empty($week_events)) :
                                    ?>
                                    <div class="week-events-list">
                                        <?php foreach ($week_events as $week_event) : ?>
                                            <?php
                                            $week_id = $week_event->ID;
                                            $week_date = get_post_meta($week_id, '_event_start_date', true);
                                            $week_time = get_post_meta($week_id, '_event_start_time', true);

                                            $week_date_obj = $parse_pst_date($week_date);
                                            $week_day = $week_date_obj->format('D');
                                            $week_date_num = $week_date_obj->format('j');
                                            
                                            $week_time_display = '';
                                            if ($week_time) {
                                                $week_time_obj = $parse_pst_time($week_time);
                                                $week_time_display = $week_time_obj->format('g:i A') . ' PST';
                                            }
                                            ?>
                                            <div class="week-event-item">
                                                <div class="week-event-date">
                                                    <span class="day"><?php echo $week_day; ?></span>
                                                    <span class="date"><?php echo $week_date_num; ?></span>
                                                </div>
                                                <div class="week-event-details">
                                                    <h5><a href="<?php echo get_permalink($week_id); ?>"><?php echo get_the_title($week_id); ?></a></h5>
                                                    <?php if ($week_time_display) : ?>
                                                        <p><?php echo esc_html($week_time_display); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php
                                    wp_reset_postdata();
                                else :
                                    ?>
                                    <p class="no-events">No events this week.</p>
                                    <?php
                                endif;
                                ?>
                            </div>

                            <!-- Upcoming Events -->
                            <div class="sidebar-widget">
                                <h3>Upcoming Events</h3>
                                <?php
                                $upcoming_args = array(
                                    'post_type' => 'events',
                                    'posts_per_page' => 5,
                                    'meta_key' => '_event_start_date',
                                    'orderby' => 'meta_value',
                                    'order' => 'ASC',
                                    'meta_query' => array(
                                        array(
                                            'key' => '_event_start_date',
                                            'value' => $today_pst,
                                            'compare' => '>=',
                                            'type' => 'DATE'
                                        )
                                    )
                                );

                                $upcoming_events = get_posts($upcoming_args);

                                if (!empty($upcoming_events)) :
                                    ?>
                                    <div class="upcoming-events-list">
                                        <?php foreach ($upcoming_events as $upcoming_event) : ?>
                                            <?php
                                            $upcoming_id = $upcoming_event->ID;
                                            $upcoming_date = get_post_meta($upcoming_id, '_event_start_date', true);
                                            $upcoming_time = get_post_meta($upcoming_id, '_event_start_time', true);

                                            $upcoming_date_obj = $parse_pst_date($upcoming_date);
                                            $upcoming_day = $upcoming_date_obj->format('j');
                                            $upcoming_month = $upcoming_date_obj->format('M');
                                            
                                            $upcoming_time_display = '';
                                            if ($upcoming_time) {
                                                $upcoming_time_obj = $parse_pst_time($upcoming_time);
                                                $upcoming_time_display = $upcoming_time_obj->format('g:i A') . ' PST';
                                            }
                                            ?>
                                            <div class="upcoming-event-item">
                                                <div class="upcoming-event-date">
                                                    <span class="day"><?php echo $upcoming_day; ?></span>
                                                    <span class="month"><?php echo $upcoming_month; ?></span>
                                                </div>
                                                <div class="upcoming-event-info">
                                                    <h5><a href="<?php echo get_permalink($upcoming_id); ?>"><?php echo get_the_title($upcoming_id); ?></a></h5>
                                                    <?php if ($upcoming_time_display) : ?>
                                                        <p><?php echo esc_html($upcoming_time_display); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php
                                    wp_reset_postdata();
                                else :
                                    ?>
                                    <p class="no-events">No upcoming events.</p>
                                    <?php
                                endif;
                                ?>
                            </div>
                        </aside>
                    </div>
                </div>
            </div>
        </div>

        <?php
    endwhile;
endif;

get_footer();
?>