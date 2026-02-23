<?php
/**
 * Leaders Management
 *
 * @package VisionImpactCustomSolutions
 * @since 1.1.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class VICS_Leaders {

    public function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('init', array($this, 'add_single_rewrite_rule'), 20);
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_boxes'));
        add_filter('manage_leaders_posts_columns', array($this, 'add_admin_columns'));
        add_action('manage_leaders_posts_custom_column', array($this, 'admin_column_content'), 10, 2);
        add_filter('single_template', array($this, 'load_single_leader_template'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_shortcode('leaders_grid', array($this, 'render_leaders_grid'));
    }

    public function register_post_type() {
        $labels = array(
            'name'               => __('Leaders', 'vics'),
            'singular_name'      => __('Leader', 'vics'),
            'menu_name'          => __('Leaders', 'vics'),
            'name_admin_bar'     => __('Leader', 'vics'),
            'add_new'            => __('Add New', 'vics'),
            'add_new_item'       => __('Add New Leader', 'vics'),
            'new_item'           => __('New Leader', 'vics'),
            'edit_item'          => __('Edit Leader', 'vics'),
            'view_item'          => __('View Leader', 'vics'),
            'all_items'          => __('All Leaders', 'vics'),
            'search_items'       => __('Search Leaders', 'vics'),
            'not_found'          => __('No leaders found.', 'vics'),
            'not_found_in_trash' => __('No leaders found in Trash.', 'vics')
        );

        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array('slug' => 'leaders'),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => null,
            'menu_icon'          => 'dashicons-groups',
            'supports'           => array('title', 'editor', 'thumbnail'),
            'show_in_rest'       => true,
        );

        register_post_type('leaders', $args);
    }

    public function add_single_rewrite_rule() {
        add_rewrite_rule(
            '^leaders/([^/]+)/?$',
            'index.php?post_type=leaders&name=$matches[1]',
            'top'
        );
    }

    public function add_meta_boxes() {
        add_meta_box(
            'leader_details',
            __('Leader Details', 'vics'),
            array($this, 'render_leader_details_meta_box'),
            'leaders',
            'normal',
            'high'
        );
    }

    public function render_leader_details_meta_box($post) {
        wp_nonce_field('vics_leader_details_nonce', 'vics_leader_details_nonce');

        $position = get_post_meta($post->ID, '_leader_position', true);
        $phone = get_post_meta($post->ID, '_leader_phone', true);
        $email = get_post_meta($post->ID, '_leader_email', true);
        $social_linkedin = get_post_meta($post->ID, '_leader_social_linkedin', true);
        $social_twitter = get_post_meta($post->ID, '_leader_social_twitter', true);
        $showcase_leader = get_post_meta($post->ID, '_leader_showcase', true) === '1';

        ?>
        <table class="form-table">
            <tr>
                <th><label for="leader_position"><?php _e('Position/Title', 'vics'); ?></label></th>
                <td>
                    <input type="text" id="leader_position" name="leader_position" value="<?php echo esc_attr($position); ?>" required style="width: 100%; padding: 8px;">
                </td>
            </tr>
            <tr>
                <th><label for="leader_email"><?php _e('Email', 'vics'); ?></label></th>
                <td>
                    <input type="email" id="leader_email" name="leader_email" value="<?php echo esc_attr($email); ?>" style="width: 100%; padding: 8px;">
                </td>
            </tr>
            <tr>
                <th><label for="leader_phone"><?php _e('Phone', 'vics'); ?></label></th>
                <td>
                    <input type="tel" id="leader_phone" name="leader_phone" value="<?php echo esc_attr($phone); ?>" style="width: 100%; padding: 8px;">
                </td>
            </tr>
            <tr>
                <th><label for="leader_social_linkedin"><?php _e('LinkedIn Profile URL', 'vics'); ?></label></th>
                <td>
                    <input type="url" id="leader_social_linkedin" name="leader_social_linkedin" value="<?php echo esc_url($social_linkedin); ?>" placeholder="https://linkedin.com/in/..." style="width: 100%; padding: 8px;">
                </td>
            </tr>
            <tr>
                <th><label for="leader_social_twitter"><?php _e('Twitter/X Profile URL', 'vics'); ?></label></th>
                <td>
                    <input type="url" id="leader_social_twitter" name="leader_social_twitter" value="<?php echo esc_url($social_twitter); ?>" placeholder="https://twitter.com/..." style="width: 100%; padding: 8px;">
                </td>
            </tr>
            <tr>
                <th><label for="leader_showcase"><?php _e('Showcase Leader', 'vics'); ?></label></th>
                <td>
                    <label>
                        <input type="checkbox" id="leader_showcase" name="leader_showcase" value="1" <?php checked($showcase_leader); ?>>
                        <?php _e('Show this leader in the showcase section', 'vics'); ?>
                    </label>
                </td>
            </tr>
        </table>
        <?php
    }

    public function save_meta_boxes($post_id) {
        if (!isset($_POST['vics_leader_details_nonce']) || !wp_verify_nonce($_POST['vics_leader_details_nonce'], 'vics_leader_details_nonce')) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        update_post_meta($post_id, '_leader_showcase', isset($_POST['leader_showcase']) ? '1' : '0');

        $fields = array(
            'leader_position',
            'leader_phone',
            'leader_email',
            'leader_social_linkedin',
            'leader_social_twitter'
        );

        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                $value = sanitize_text_field($_POST[$field]);
                if (in_array($field, array('leader_social_linkedin', 'leader_social_twitter', 'leader_email'))) {
                    $value = sanitize_url($_POST[$field]);
                }
                update_post_meta($post_id, '_' . $field, $value);
            }
        }
    }

    public function add_admin_columns($columns) {
        $new_columns = array();
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['leader_position'] = __('Position', 'vics');
            }
        }
        return $new_columns;
    }

    public function admin_column_content($column, $post_id) {
        switch ($column) {
            case 'leader_position':
                $position = get_post_meta($post_id, '_leader_position', true);
                echo esc_html($position ?: '—');
                break;
        }
    }

    public function load_single_leader_template($template) {
        global $post;

        if ($post && $post->post_type === 'leaders') {
            $custom_template = VICS_PLUGIN_PATH . 'templates/single-leader.php';
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }

        return $template;
    }

    public function enqueue_scripts() {
        if (is_singular('leaders')) {
            wp_enqueue_style('vics-single-leader', VICS_PLUGIN_URL . 'assets/css/single-leader.css', array(), VICS_VERSION);
        }

        if (is_post_type_archive('leaders') || has_shortcode(get_post()->post_content ?? '', 'leaders_grid')) {
            wp_enqueue_style('vics-leaders', VICS_PLUGIN_URL . 'assets/css/leaders.css', array(), VICS_VERSION);
        }
    }

    public function render_leaders_grid($atts) {
        ob_start();

        $atts = shortcode_atts(array(
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ), $atts);

        $args = array(
            'post_type' => 'leaders',
            'posts_per_page' => $atts['posts_per_page'],
            'orderby' => $atts['orderby'],
            'order' => $atts['order']
        );

        $leaders = get_posts($args);
        $showcase_leaders = array();
        $remaining_leaders = array();

        foreach ($leaders as $leader) {
            if (get_post_meta($leader->ID, '_leader_showcase', true) === '1') {
                $showcase_leaders[] = $leader;
            } else {
                $remaining_leaders[] = $leader;
            }
        }

        $render_leader_card = function ($leader) {
            $leader_id = $leader->ID;
            $position = get_post_meta($leader_id, '_leader_position', true);
            $featured_image = get_the_post_thumbnail_url($leader_id, 'large');
            ?>
            <div class="leader-card">
                <div class="leader-image">
                    <a href="<?php echo get_permalink($leader_id); ?>" class="leader-image-link">
                        <?php if ($featured_image) : ?>
                            <img src="<?php echo esc_url($featured_image); ?>" alt="<?php echo esc_attr(get_the_title($leader_id)); ?>">
                        <?php else : ?>
                            <div class="leader-image-placeholder">
                                <svg width="100%" height="100%" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg">
                                    <rect fill="#e0e0e0" width="200" height="200"/>
                                    <circle cx="100" cy="70" r="30" fill="#999"/>
                                    <path d="M 60 150 Q 60 130 100 130 Q 140 130 140 150 L 140 200 L 60 200 Z" fill="#999"/>
                                </svg>
                            </div>
                        <?php endif; ?>
                    </a>
                </div>
                <div class="leader-info">
                    <h3><a href="<?php echo get_permalink($leader_id); ?>"><?php echo get_the_title($leader_id); ?></a> <span class="leader-arrow">→</span></h3>
                    <?php if ($position) : ?>
                        <p class="leader-position"><?php echo esc_html($position); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php
        };

        ?>
        <div class="leaders-grid-wrapper">
            <?php if (!empty($showcase_leaders)) : ?>
                <h2><?php //_e('Showcase Leaders', 'vics'); ?></h2>
                <div class="leaders-grid leaders-showcase-grid">
                    <?php foreach ($showcase_leaders as $leader) : ?>
                        <?php $render_leader_card($leader); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($remaining_leaders)) : ?>
                <?php if (!empty($showcase_leaders)) : ?>
                    <h2><?php //_e('All Leaders', 'vics'); ?></h2>
                <?php endif; ?>
                <div class="leaders-grid">
                    <?php foreach ($remaining_leaders as $leader) : ?>
                        <?php $render_leader_card($leader); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php

        wp_reset_postdata();
        return ob_get_clean();
    }
}

// Initialize Leaders
new VICS_Leaders();