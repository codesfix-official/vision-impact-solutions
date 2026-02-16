<?php
/**
 * Single Leader Template
 *
 * @package VisionImpactCustomSolutions
 * @since 1.1.1
 */

get_header();

if (have_posts()) :
    while (have_posts()) :
        the_post();
        $leader_id = get_the_ID();
        $position = get_post_meta($leader_id, '_leader_position', true);
        $email = get_post_meta($leader_id, '_leader_email', true);
        // Clean email - remove http:// or https:// prefix if present
        $email = preg_replace('~^https?://~i', '', $email);
        $phone = get_post_meta($leader_id, '_leader_phone', true);
        $social_linkedin = get_post_meta($leader_id, '_leader_social_linkedin', true);
        $social_twitter = get_post_meta($leader_id, '_leader_social_twitter', true);
        $featured_image = get_the_post_thumbnail_url($leader_id, 'large');
        ?>

        <div class="single-leader">
            <div class="leader-hero">
                <div class="leader-container">
                    <div class="leader-profile-grid">
                        <!-- Leader Image -->
                        <div class="leader-profile-image">
                            <?php if ($featured_image) : ?>
                                <img src="<?php echo esc_url($featured_image); ?>" alt="<?php echo esc_attr(get_the_title()); ?>">
                            <?php else : ?>
                                <div class="leader-image-placeholder-large">
                                    <svg width="100%" height="100%" viewBox="0 0 300 300" xmlns="http://www.w3.org/2000/svg">
                                        <rect fill="#e0e0e0" width="300" height="300"/>
                                        <circle cx="150" cy="100" r="50" fill="#999"/>
                                        <path d="M 80 200 Q 80 160 150 160 Q 220 160 220 200 L 220 300 L 80 300 Z" fill="#999"/>
                                    </svg>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Leader Info -->
                        <div class="leader-profile-info">
                            <h1><?php the_title(); ?></h1>
                            <?php if ($position) : ?>
                                <p class="leader-title"><?php echo esc_html($position); ?></p>
                            <?php endif; ?>

                            <!-- Contact Information -->
                            <div class="leader-contact">
                                <?php if ($email) : ?>
                                    <div class="contact-item">
                                        <strong>Email:</strong>
                                        <a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a>
                                    </div>
                                <?php endif; ?>
                                <?php if ($phone) : ?>
                                    <div class="contact-item">
                                        <strong>Phone:</strong>
                                        <a href="tel:<?php echo esc_attr($phone); ?>"><?php echo esc_html($phone); ?></a>
                                    </div>
                                <?php endif; ?>

                                <!-- Social Links -->
                                <?php if ($social_linkedin || $social_twitter) : ?>
                                    <div class="leader-social">
                                        <?php if ($social_linkedin) : ?>
                                            <a href="<?php echo esc_url($social_linkedin); ?>" target="_blank" class="social-link linkedin" title="LinkedIn">in</a>
                                        <?php endif; ?>
                                        <?php if ($social_twitter) : ?>
                                            <a href="<?php echo esc_url($social_twitter); ?>" target="_blank" class="social-link twitter" title="Twitter/X">𝕏</a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bio/Content -->
            <div class="leader-content-section">
                <div class="leader-container">
                    <div class="leader-bio">
                        <h2>About</h2>
                        <div class="leader-description">
                            <?php the_content(); ?>
                        </div>
                    </div>

                    <!-- Other Leaders -->
                    <div class="leader-sidebar">
                        <h3>Our Leadership Team</h3>
                        <?php
                        $other_leaders = get_posts(array(
                            'post_type' => 'leaders',
                            'posts_per_page' => 5,
                            'post__not_in' => array($leader_id),
                            'orderby' => 'title',
                            'order' => 'ASC'
                        ));

                        if (!empty($other_leaders)) :
                            ?>
                            <div class="other-leaders-list">
                                <?php foreach ($other_leaders as $other_leader) : ?>
                                    <?php
                                    $other_pos = get_post_meta($other_leader->ID, '_leader_position', true);
                                    $other_img = get_the_post_thumbnail_url($other_leader->ID, 'thumbnail');
                                    ?>
                                    <div class="other-leader-item">
                                        <?php if ($other_img) : ?>
                                            <img src="<?php echo esc_url($other_img); ?>" alt="<?php echo esc_attr(get_the_title($other_leader->ID)); ?>">
                                        <?php endif; ?>
                                        <div class="other-leader-info">
                                            <h4><a href="<?php echo get_permalink($other_leader->ID); ?>"><?php echo get_the_title($other_leader->ID); ?></a></h4>
                                            <?php if ($other_pos) : ?>
                                                <p><?php echo esc_html($other_pos); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php
                            wp_reset_postdata();
                        endif;
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <?php
    endwhile;
endif;

get_footer();
?>