<?php
/**
 * Orientation Popup Template
 * 
 * @package VisionImpactCustomSolutions
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div id="vics-orientation-overlay" class="vics-overlay">
    <div class="vics-popup-container">
        
        <!-- Step 1: Terms and Form -->
        <div id="vics-step-form" class="vics-step <?php echo !$progress['form_submitted'] ? 'active' : ''; ?>">
            <div class="vics-popup-header">
                <h2><?php echo esc_html($popup_header); ?></h2>
                <p class="vics-user-greeting"><?php 
                    /*printf(__('Hello, %s!', 'vics'), esc_html($user->display_name)); */
                ?></p>
            </div>
            
            <div class="vics-popup-body">
                <form id="vics-orientation-form">
                    
                    <!-- Description -->
                    <div class="vics-description">
                        <p><?php echo esc_html($popup_description); ?></p>
                    </div>
                    
                    <!-- Checklist Items -->
                    <?php if (!empty($list_items) && is_array($list_items)): ?>
                    <div class="vics-checklist">
                        <ul class="vics-checklist-items">
                            <?php foreach ($list_items as $item): ?>
                                <?php if (!empty($item)): ?>
                                <li class="vics-checklist-item">
                                    <span class="vics-double-check-icon">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                                            <path d="M2 12L6 16L12 8" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="M8 12L12 16L22 4" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </span>
                                    <span class="vics-checklist-text"><?php echo esc_html($item); ?></span>
                                </li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Terms Checkbox -->
                    <div class="vics-checkbox-field">
                        <label class="vics-checkbox-label">
                            <input type="checkbox" id="terms_accepted" name="terms_accepted" required />
                            <span class="vics-checkbox-custom"></span>
                            <span class="vics-checkbox-text"><?php echo esc_html($checkbox_text); ?></span>
                        </label>
                    </div>
                    
                    <!-- Submit Button -->
                    <div class="vics-form-actions">
                        <button type="submit" class="vics-btn vics-btn-primary" id="vics-submit-form">
                            <?php echo esc_html($button_text); ?>
                            <span class="vics-btn-arrow">→</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Step 2: Video -->
        <div id="vics-step-video" class="vics-step <?php echo $progress['form_submitted'] && !$progress['video_completed'] ? 'active' : ''; ?>">
            <div class="vics-popup-header">
                <h2><?php _e('Orientation Video', 'vics'); ?></h2>
                <p><?php _e('Please watch the complete orientation video to continue.', 'vics'); ?></p>
            </div>
            
            <div class="vics-popup-body">
                <div class="vics-video-container" data-video-type="<?php echo esc_attr($video_type); ?>">
                    
                    <?php if ($video_type === 'youtube'): ?>
                        <div id="vics-youtube-player" data-video-id="<?php echo esc_attr($video_id); ?>"></div>
                        
                    <?php elseif ($video_type === 'vimeo'): ?>
                        <div id="vics-vimeo-player" data-video-id="<?php echo esc_attr($video_id); ?>"></div>
                        
                    <?php elseif ($video_type === 'html5'): ?>
                        <video id="vics-html5-player" controls controlsList="nodownload noplaybackrate noremoteplayback" disablePictureInPicture style="width: 100%; max-width: 100%;">

                            <source src="<?php echo esc_url($video_id); ?>" type="video/mp4">
                            <?php _e('Your browser does not support the video tag.', 'vics'); ?>
                        </video>
                        
                    <?php else: ?>
                        <div class="vics-no-video">
                            <p><?php _e('⚠️ No valid video URL configured.', 'vics'); ?></p>
                        </div>
                    <?php endif; ?>
                    
                </div>
                
                <div class="vics-video-notice">
                    <p><?php _e('⚠️ You must watch the entire video sequentially to complete orientation.', 'vics'); ?></p>
                    <p class="vics-resume-notice" id="vics-resume-notice" style="display: none;">
                        <?php _e('📍 Resuming from where you left off...', 'vics'); ?>
                    </p>
                </div>
                
                <!-- Playbook Download -->
                <?php $playbook_url = get_option('vics_playbook_url'); ?>
                <?php if (!empty($playbook_url)): ?>
                <div class="vics-playbook-section">
                    <div class="vics-playbook-content">
                        <div class="vics-playbook-icon">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <polyline points="14,2 14,8 20,8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <line x1="16" y1="13" x2="8" y2="13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <line x1="16" y1="17" x2="8" y2="17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <polyline points="10,9 9,9 8,9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <div class="vics-playbook-info">
                            <h4><?php _e('Agent Playbook', 'vics'); ?></h4>
                            <p><?php _e('Download our comprehensive guide to get started as a successful agent.', 'vics'); ?></p>
                        </div>
                        <a href="<?php echo esc_url($playbook_url); ?>" 
                           class="vics-playbook-download" target="_blank" rel="noopener noreferrer">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <polyline points="7,10 12,15 17,10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <line x1="12" y1="15" x2="12" y2="3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <?php _e('Download', 'vics'); ?>
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Step 3: Welcome Message -->
        <div id="vics-step-complete" class="vics-step">
            <div class="vics-popup-header vics-success">
                <div class="vics-success-icon">
                    <svg width="60" height="60" viewBox="0 0 24 24" fill="none">
                        <path d="M2 12L6 16L12 8" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M8 12L12 16L22 4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <h2><?php _e('Welcome Onboard!', 'vics'); ?></h2>
            </div>
            
            <div class="vics-popup-body">
                <p class="vics-welcome-message" id="vics-welcome-text"></p>
                
                <div class="vics-form-actions">
                    <button type="button" class="vics-btn vics-btn-primary" id="vics-access-site">
                        <?php _e('Go to My Profile', 'vics'); ?>
                        <span class="vics-btn-arrow">→</span>
                    </button>
                </div>
            </div>
        </div>
        
    </div>
</div>