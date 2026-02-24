<?php
// templates/profile-page.php

if (!defined('ABSPATH')) {
    exit;
}

$user_id = get_current_user_id();
$user = wp_get_current_user();
$profile = VICS_Database::get_profile($user_id);

$about_questions = get_option('vics_about_questions', array());
if (!is_array($about_questions)) {
    $about_questions = array();
}

$about_answers = get_user_meta($user_id, 'vics_about_answers', true);
if (!is_array($about_answers)) {
    $about_answers = array();
}

// Check if user is an agent and create Google Sheet if it doesn't exist
if (in_array('agent', $user->roles) && empty($profile['google_sheet_id'])) {
    $sync = new VICS_Google_Sync();
    $sync->create_agent_sheet($user_id);
    // Re-fetch profile data after sheet creation
    $profile = VICS_Database::get_profile($user_id);
}

// User data
$first_name = $user->first_name;
$last_name = $user->last_name;
$display_name = $user->display_name;
$email = $user->user_email;

// Get initials for avatar
$initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));
if (empty($initials)) {
    $initials = strtoupper(substr($display_name, 0, 2));
}

// Avatar URL
$avatar_url = '';
if (!empty($profile['profile_photo_id'])) {
    $avatar_url = wp_get_attachment_image_url($profile['profile_photo_id'], 'thumbnail');
}

// LMS Progress - Get real course data from active LMS provider (LearnDash/Tutor)
$lms_progress = VICS_LMS_Integration::get_user_progress($user_id);
$lms_modules = array();

// Add actual LMS courses
if (!empty($lms_progress['courses'])) {
    foreach ($lms_progress['courses'] as $course) {
        $lms_modules[] = array(
            'module_name' => $course['title'],
            'status' => $course['status'],
            'progress' => $course['progress'],
            'type' => 'course',
            'permalink' => $course['permalink'],
            'course_id' => $course['id']
        );
    }
}

// License status
$primary_license = VICS_Database::get_primary_license_status($user_id);
$license_status = $primary_license ? $primary_license['status'] : 'pending';
$license_expiry = $primary_license ? $primary_license['expiry_date'] : '';
?>

<div class="ad-container vics-profile-container">
    
    <!-- Success/Error Messages -->
    <div class="ad-message ad-message-success" id="vics-success-message" style="display: none;">
        <button class="ad-message-close">&times;</button>
    </div>
    <div class="ad-message ad-message-error" id="vics-error-message" style="display: none;">
        <button class="ad-message-close">&times;</button>
    </div>

    <!-- Profile Header -->
    <div class="card">
        <div class="profile-header">
            <div class="profile-info">
                <div class="profile-avatar" id="profile-avatar">
                    <?php if ($avatar_url): ?>
                        <img src="<?php echo esc_url($avatar_url); ?>" alt="Avatar" />
                    <?php else: ?>
                        <?php echo esc_html($initials); ?>
                    <?php endif; ?>
                    <!-- <div class="verified-badge">✓</div> -->
                    <div class="avatar-upload-overlay" id="avatar-upload-trigger">
                        <span>📷</span>
                    </div>
                </div>
                <input type="file" id="avatar-upload-input" accept="image/*" style="display: none;" />
                
                <div class="profile-details">
                    <h1><?php echo esc_html($first_name . ' ' . $last_name); ?></h1>
                    <div class="rating">⭐⭐⭐⭐⭐</div>
                    <div class="contact-info">
                        Phone: <?php echo esc_html($profile['phone'] ?? 'Not set'); ?>
                    </div>
                    <div class="contact-info">
                        Email: <?php echo esc_html($email); ?>
                    </div>
                </div>
            </div>
            <div class="social-links">
                <?php if (!empty($profile['facebook_url'])): ?>
                    <a href="<?php echo esc_url($profile['facebook_url']); ?>" target="_blank" title="Facebook" data-platform="facebook" class="social-icon">f</a>
                <?php else: ?>
                    <a href="#" class="social-empty" title="Facebook not set" data-platform="facebook" class="social-icon">f</a>
                <?php endif; ?>
                
                <?php if (!empty($profile['tiktok_url'])): ?>
                    <a href="<?php echo esc_url($profile['tiktok_url']); ?>" target="_blank" title="TikTok" data-platform="tiktok" class="social-icon">♪</a>
                <?php else: ?>
                    <a href="#" class="social-empty" title="TikTok not set" data-platform="tiktok" class="social-icon">♪</a>
                <?php endif; ?>
                
                <?php if (!empty($profile['instagram_url'])): ?>
                    <a href="<?php echo esc_url($profile['instagram_url']); ?>" target="_blank" title="Instagram" data-platform="instagram" class="social-icon">📷</a>
                <?php else: ?>
                    <a href="#" class="social-empty" title="Instagram not set" data-platform="instagram" class="social-icon">📷</a>
                <?php endif; ?>
                
                <?php if (!empty($profile['twitter_url'])): ?>
                    <a href="<?php echo esc_url($profile['twitter_url']); ?>" target="_blank" title="Twitter" data-platform="twitter" class="social-icon">𝕏</a>
                <?php else: ?>
                    <a href="#" class="social-empty" title="Twitter not set" data-platform="twitter" class="social-icon">𝕏</a>
                <?php endif; ?>
                
                <?php if (!empty($profile['youtube_url'])): ?>
                    <a href="<?php echo esc_url($profile['youtube_url']); ?>" target="_blank" title="YouTube" data-platform="youtube" class="social-icon">▶</a>
                <?php else: ?>
                    <a href="#" class="social-empty" title="YouTube not set" data-platform="youtube" class="social-icon">▶</a>
                <?php endif; ?>
                
                <?php if (!empty($profile['linkedin_url'])): ?>
                    <a href="<?php echo esc_url($profile['linkedin_url']); ?>" target="_blank" title="LinkedIn" data-platform="linkedin" class="social-icon">in</a>
                <?php else: ?>
                    <a href="#" class="social-empty" title="LinkedIn not set" data-platform="linkedin" class="social-icon">in</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Basic Info -->
    <div class="card">
        <h2 class="section-title">Basic Info</h2>
        <form id="ad-profile-form">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">First Name *</label>
                    <input type="text" class="form-input" name="first_name" 
                           value="<?php echo esc_attr($first_name); ?>" required />
                </div>
                <div class="form-group">
                    <label class="form-label">Last Name *</label>
                    <input type="text" class="form-input" name="last_name" 
                           value="<?php echo esc_attr($last_name); ?>" required />
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Email *</label>
                    <input type="email" class="form-input" name="email" 
                           value="<?php echo esc_attr($email); ?>" required />
                </div>
                <div class="form-group">
                    <label class="form-label">Phone *</label>
                    <input type="tel" class="form-input" name="phone" 
                           value="<?php echo esc_attr($profile['phone'] ?? ''); ?>" 
                           placeholder="+(123) 456-7890" required />
                </div>
            </div>
            
            <div class="form-divider">
                <h3>Agent Credentials</h3>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Agent Code</label>
                    <input type="text" class="form-input" name="agent_code" 
                           value="<?php echo esc_attr($profile['agent_code'] ?? ''); ?>" />
                    <?php if (!empty($profile['agent_code'])): ?>
                        <?php if (isset($profile['agent_code_status']) && $profile['agent_code_status'] === 'pending'): ?>
                            <p style="color: #f0ad4e; font-size: 12px; margin-top: 5px;">
                                ⏳ <strong>Pending Admin Review</strong> - Your agent code is awaiting approval.
                            </p>
                        <?php elseif (isset($profile['agent_code_status']) && $profile['agent_code_status'] === 'rejected'): ?>
                            <p style="color: #d9534f; font-size: 12px; margin-top: 5px;">
                                ❌ <strong>Rejected</strong> - Please update and resubmit your agent code.
                            </p>
                        <?php elseif (isset($profile['agent_code_status']) && $profile['agent_code_status'] === 'approved'): ?>
                            <p style="color: #5cb85c; font-size: 12px; margin-top: 5px;">
                                ✓ <strong>Approved</strong>
                            </p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label class="form-label">NPN</label>
                    <input type="text" class="form-input" name="npn" 
                           value="<?php echo esc_attr($profile['npn'] ?? ''); ?>" />
                </div>
            </div>
            
            <div class="form-divider">
                <h3>Social Media Links</h3>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Facebook URL</label>
                    <input type="url" class="form-input social-link-input" name="facebook_url" data-platform="facebook" 
                           value="<?php echo esc_attr($profile['facebook_url'] ?? ''); ?>" 
                           placeholder="https://facebook.com/username" data-platform="facebook" />
                </div>
                <div class="form-group">
                    <label class="form-label">Instagram URL</label>
                    <input type="url" class="form-input social-link-input" name="instagram_url" 
                           value="<?php echo esc_attr($profile['instagram_url'] ?? ''); ?>" 
                           placeholder="https://instagram.com/username" data-platform="instagram" />
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Twitter/X URL</label>
                    <input type="url" class="form-input social-link-input" name="twitter_url" data-platform="twitter"
                           value="<?php echo esc_attr($profile['twitter_url'] ?? ''); ?>" 
                           placeholder="https://twitter.com/username" data-platform="twitter" />
                </div>
                <div class="form-group">
                    <label class="form-label">TikTok URL</label>
                    <input type="url" class="form-input social-link-input" name="tiktok_url" 
                           value="<?php echo esc_attr($profile['tiktok_url'] ?? ''); ?>" 
                           placeholder="https://tiktok.com/@username" data-platform="tiktok" />
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">YouTube URL</label>
                    <input type="url" class="form-input social-link-input" name="youtube_url" data-platform="youtube"
                           value="<?php echo esc_attr($profile['youtube_url'] ?? ''); ?>" 
                           placeholder="https://youtube.com/@channel" data-platform="youtube" />
                </div>
                <div class="form-group">
                    <label class="form-label">LinkedIn URL</label>
                    <input type="url" class="form-input social-link-input" name="linkedin_url" 
                           value="<?php echo esc_attr($profile['linkedin_url'] ?? ''); ?>" 
                           placeholder="https://linkedin.com/in/username" data-platform="linkedin" />
                </div>
            </div>
            
            <div class="form-divider">
                <h3>Additional Information</h3>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Google Sheet ID</label>
                    <input type="text" class="form-input" name="google_sheet_id" 
                           value="<?php echo esc_attr($profile['google_sheet_id'] ?? ''); ?>" 
                           placeholder="Your Google Sheet ID will appear here automatically" 
                           readonly disabled />
                    <?php if (!empty($profile['google_sheet_id'])): ?>
                        <div style="margin-top: 8px; padding: 8px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; font-size: 12px; color: #155724;">
                            <strong>✅ Sheet Created:</strong> Your personal Google Sheet has been automatically created and is ready to use.
                            <br><em>Your sheet is shared with administrators for tracking and support.</em>
                        </div>
                    <?php else: ?>
                        <div style="margin-top: 8px; padding: 8px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; font-size: 12px;">
                            <strong>⏳ Sheet Pending:</strong> Your personal Google Sheet is being prepared and will be created automatically.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="form-actions">
                <?php if (!empty($profile['google_sheet_id'])): ?>
                <a href="https://docs.google.com/spreadsheets/d/<?php echo esc_attr($profile['google_sheet_id']); ?>"
                   target="_blank" class="btn btn-secondary" style="margin-right: 15px;">
                    <i class="fas fa-external-link-alt"></i> Your Agent Tracker
                </a>
                <?php endif; ?>
                <button type="submit" class="btn btn-primary" id="ad-save-profile">
                    <span class="btn-text">SAVE PROFILE</span>
                    <span class="btn-loading" style="display: none;">Saving...</span>
                </button>
            </div>
        </form>
    </div>

    <!-- Change Password -->
    <div class="card">
        <h2 class="section-title">Change Password</h2>
        <form id="ad-password-form">
            <div class="password-row">
                <div class="form-group">
                    <label class="form-label">Current Password</label>
                    <div class="password-field">
                        <input type="password" class="form-input" name="current_password" required />
                        <button type="button" class="toggle-password">👁</button>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <div class="password-field">
                        <input type="password" class="form-input" name="new_password" 
                               minlength="8" required />
                        <button type="button" class="toggle-password">👁</button>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm New Password</label>
                    <div class="password-field">
                        <input type="password" class="form-input" name="confirm_password" required />
                        <button type="button" class="toggle-password">👁</button>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-secondary" id="ad-save-password">
                <span class="btn-text">Update Password</span>
                <span class="btn-loading" style="display: none;">Updating...</span>
            </button>
        </form>
    </div>

    <!-- Grid Section -->
    <div class="grid-2">
        <!-- LMS Training Status -->
        <div class="card">
            <h2 class="section-title">LMS Training Status</h2>
            <div class="progress-bar">
                <div class="progress-fill" id="lms-progress-bar" style="width: <?php echo esc_attr(!empty($lms_modules) ? (($lms_progress['completed_courses'] / count($lms_modules)) * 100) : 0); ?>%;"></div>
            </div>
            <div class="progress-text"><?php echo esc_html($lms_progress['completed_courses']); ?> of <?php echo esc_html(count($lms_modules)); ?> courses completed</div>
            <div id="lms-modules-list">
                <?php if (!empty($lms_modules)): ?>
                    <!-- Course table header -->
                    <div style="display: grid; grid-template-columns: 2fr 2fr 1fr; gap: 15px; padding: 10px 0; border-bottom: 1px solid #e0e0e0; font-weight: 600; color: #666; font-size: 14px;">
                        <div>Course Name</div>
                        <div>Progress</div>
                        <div>Status</div>
                    </div>
                    <?php foreach ($lms_modules as $module): ?>
                        <?php if ($module['type'] === 'course'): ?>
                            <!-- Course row -->
                            <div style="display: grid; grid-template-columns: 2fr 2fr 1fr; gap: 15px; padding: 15px 0; border-bottom: 1px solid #f0f0f0; align-items: center; cursor: pointer;" onclick="window.open('<?php echo esc_url($module['permalink']); ?>', '_blank')">
                                <!-- Course Name -->
                                <div style="font-weight: 500; color: #007cba;"><?php echo esc_html($module['module_name']); ?></div>

                                <!-- Progress Bar -->
                                <div>
                                    <div class="progress-bar" style="background: #f0f0f0; height: 8px; border-radius: 4px; overflow: hidden; margin-bottom: 4px;">
                                        <div class="progress-fill" style="width: <?php echo esc_attr($module['progress']); ?>%; height: 100%; background: #007cba; transition: width 0.3s ease;"></div>
                                    </div>
                                    <!-- <div style="font-size: 11px; color: #666;"><?php //echo esc_html($module['progress']); ?>% Complete</div> -->
                                </div>

                                <!-- Status -->
                                <div>
                                    <span class="module-status <?php
                                        $status_class = 'status-not-started';
                                        if ($module['status'] === 'completed') {
                                            $status_class = 'status-completed';
                                        } elseif ($module['status'] === 'in_progress') {
                                            $status_class = 'status-progress';
                                        }
                                        echo $status_class;
                                    ?>" style="font-size: 12px; padding: 4px 8px; border-radius: 12px; display: inline-block;">
                                        <?php
                                        if ($module['status'] === 'completed') {
                                            echo '✓ Completed';
                                        } elseif ($module['status'] === 'in_progress') {
                                            echo '⏳ In Progress';
                                        } else {
                                            echo '○ Not Started';
                                        }
                                        ?>
                                    </span>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align: center; color: #666; padding: 20px;">No courses enrolled yet.</p>
                <?php endif; ?>
            </div>
            <button class="btn btn-primary" style="margin-top: 20px;" onclick="window.location.href='<?php echo esc_url(VICS_LMS_Integration::get_dashboard_url()); ?>'">
                Go To LMS
            </button>
        </div>

        <!-- License Tracking -->
        <div class="card">
            <h2 class="section-title">License Tracking</h2>
            <div class="form-group">
                <label class="form-label">Status</label>
                <?php
                $status_badge_class = '';
                switch ($license_status) {
                    case 'active':
                        $status_badge_class = 'status-badge-active';
                        break;
                    case 'expired':
                        $status_badge_class = 'status-badge-expired';
                        break;
                    default:
                        $status_badge_class = 'status-badge-pending';
                }
                ?>
                <span class="status-badge <?php echo $status_badge_class; ?>">
                    <?php echo ucfirst(esc_html($license_status)); ?>
                </span>
            </div>
            <div class="subscriber-info">
                <strong>License #:</strong> <?php echo esc_html($profile['license_number'] ?? 'Not set'); ?><br>
                <?php if ($license_expiry): ?>
                    <strong>Expires:</strong> <?php echo date('m/d/Y', strtotime($license_expiry)); ?>
                <?php endif; ?>
            </div>
            <button class="btn btn-primary" style="margin-top: 20px;" id="update-license-btn">
                Update License Info
            </button>
        </div>
    </div>

    <!-- About You Section -->
    <div class="card">
        <h2 class="section-title">About You</h2>
        <form id="ad-about-form">
            <div class="form-group">
                <label class="form-label">Birthday (Auto-reminds admins 2 days before)</label>
                <input type="date" class="form-input" name="date_of_birth" 
                       value="<?php echo esc_attr($profile['date_of_birth'] ?? ''); ?>" />
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">City</label>
                    <input type="text" class="form-input" name="city" 
                           value="<?php echo esc_attr($profile['city'] ?? ''); ?>" />
                </div>
                <div class="form-group">
                    <label class="form-label">State</label>
                    <input type="text" class="form-input" name="state" 
                           value="<?php echo esc_attr($profile['state'] ?? ''); ?>" />
                </div>
            </div>
            <?php foreach ($about_questions as $question) : ?>
                <?php
                $question_id = sanitize_key($question['id'] ?? '');
                $question_text = $question['text'] ?? '';

                if ($question_id === '' || $question_text === '') {
                    continue;
                }

                $answer = $about_answers[$question_id] ?? '';
                if ($answer === '' && isset($profile[$question_id])) {
                    $answer = $profile[$question_id];
                }
                ?>
                <div class="form-group">
                    <label class="form-label"><?php echo esc_html($question_text); ?></label>
                    <textarea class="form-input" name="about_answers[<?php echo esc_attr($question_id); ?>]" rows="3"><?php echo esc_textarea($answer); ?></textarea>
                </div>
            <?php endforeach; ?>
            <button type="submit" class="btn btn-primary" id="ad-save-about">
                <span class="btn-text">Save Changes</span>
                <span class="btn-loading" style="display: none;">Saving...</span>
            </button>
        </form>
    </div>

</div>

<!-- License Modal -->
<div id="vics-license-modal" class="vics-modal" style="display: none;">
    <div class="vics-modal-content">
        <span class="vics-modal-close">&times;</span>
        <h2 id="vics-license-modal-title">Add License</h2>
        <form id="vics-license-form">
            <input type="hidden" id="vics-license-id" name="license_id" value="" />
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">State *</label>
                    <input type="text" id="vics-license-state" class="form-input" name="license_state" required placeholder="e.g., CA, NY, TX" />
                </div>
                <div class="form-group">
                    <label class="form-label">License Number</label>
                    <input type="text" id="vics-license-number" class="form-input" name="license_number" />
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Issue Date</label>
                    <input type="date" id="vics-issue-date" class="form-input" name="issue_date" />
                </div>
                <div class="form-group">
                    <label class="form-label">Expiry Date</label>
                    <input type="date" id="vics-expiry-date" class="form-input" name="expiry_date" />
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Notes</label>
                <textarea id="vics-license-notes" class="form-input" name="notes" rows="3"></textarea>
            </div>
            
            <div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; padding: 10px; margin-bottom: 15px; font-size: 13px;">
                ℹ️ Your license will be submitted for admin review and approval.
            </div>
            
            <div class="form-actions" style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="submit" class="btn btn-primary" id="vics-submit-license">
                    <span class="btn-text">Submit License</span>
                    <span class="btn-loading" style="display: none;">Submitting...</span>
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.vics-modal {
    display: none;
    position: fixed;
    z-index: 99999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.5);
}

.vics-modal-content {
    background-color: #fefefe;
    margin: 5% auto;
    padding: 30px;
    border: 1px solid #888;
    border-radius: 8px;
    width: 90%;
    max-width: 600px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.2);
}

.vics-modal-close {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
    line-height: 20px;
    cursor: pointer;
}

.vics-modal-close:hover,
.vics-modal-close:focus {
    color: #000;
}

#vics-license-modal-title {
    margin-top: 0;
    margin-bottom: 20px;
    color: #333;
}
</style>