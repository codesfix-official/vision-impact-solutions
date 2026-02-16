/**
 * Profile JavaScript
 *
 * @package VisionImpactCustomSolutions
 * @since 1.0.0
 */

(function($) {
    'use strict';

    var VICS_Profile = {

        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            var self = this;

            // Profile form submission
            $(document).on('submit', '#ad-profile-form', function(e) {
                e.preventDefault();
                self.updateProfile();
            });

            // About form submission
            $(document).on('submit', '#ad-about-form', function(e) {
                e.preventDefault();
                self.updateAbout();
            });

            // Avatar upload
            $(document).on('change', '#avatar-upload-input', function() {
                self.uploadAvatar();
            });

            $(document).on('click', '#profile-avatar', function() {
                $('#avatar-upload-input').click();
            });

            // Social links update
            $(document).on('blur', '.social-link-input', function() {
                self.updateSocialLinks();
            });

            // Message close
            $(document).on('click', '.ad-message-close', function() {
                $(this).closest('.ad-message').fadeOut();
            });
            // Password form submission
            $(document).on('submit', '#ad-password-form', function(e) {
                e.preventDefault();
                self.updatePassword(); // You need to create this function
            });

            // FIX: Add Toggle Password Handler
            $(document).on('click', '.toggle-password', function() {
                var input = $(this).prev('input');
                var type = input.attr('type') === 'password' ? 'text' : 'password';
                input.attr('type', type);
            });

            // License Modal Handlers
            $(document).on('click', '#update-license-btn', function(e) {
                e.preventDefault();
                self.openLicenseModal();
            });

            $(document).on('click', '.vics-modal-close', function() {
                self.closeLicenseModal();
            });

            $(document).on('submit', '#vics-license-form', function(e) {
                e.preventDefault();
                self.submitLicense();
            });
        },

        updateProfile: function() {
            var self = this;
            var formData = new FormData(document.getElementById('ad-profile-form'));
            formData.append('action', 'vics_update_profile');
            formData.append('vics_profile_nonce', vicsProfileData.nonce);

            // Show loading state - button shrinks and text vanishes
            var $button = $('#ad-save-profile');
            $button.addClass('loading').prop('disabled', true);
            $button.find('.btn-text').hide();
            $button.find('.btn-loading').show();

            $.ajax({
                url: vicsProfileData.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    // Hide loading state
                    $button.removeClass('loading').prop('disabled', false);
                    $button.find('.btn-text').show();
                    $button.find('.btn-loading').hide();

                    if (response.success) {
                        self.showMessage('success', response.data.message);
                        // Update header information after profile save
                        self.updateHeaderInfo();
                        self.updateHeaderSocialLinks();
                    } else {
                        self.showMessage('error', response.data);
                    }
                },
                error: function() {
                    // Hide loading state
                    $button.removeClass('loading').prop('disabled', false);
                    $button.find('.btn-text').show();
                    $button.find('.btn-loading').hide();
                    self.showMessage('error', 'An error occurred. Please try again.');
                }
            });
        },

        updateAbout: function() {
            var self = this;
            var formData = new FormData(document.getElementById('ad-about-form'));
            formData.append('action', 'vics_update_profile');
            formData.append('vics_profile_nonce', vicsProfileData.nonce);

            // Debug: Log form data
            console.log('About Form Submission - Data:');
            for (var pair of formData.entries()) {
                console.log(pair[0] + ': ' + pair[1]);
            }

            // Show loading state - button shrinks and text vanishes
            var $button = $('#ad-save-about');
            $button.addClass('loading').prop('disabled', true);
            $button.find('.btn-text').hide();
            $button.find('.btn-loading').show();

            $.ajax({
                url: vicsProfileData.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    // Hide loading state
                    $button.removeClass('loading').prop('disabled', false);
                    $button.find('.btn-text').show();
                    $button.find('.btn-loading').hide();

                    console.log('About Form Response:', response);

                    if (response.success) {
                        self.showMessage('success', response.data.message);
                        // Form values remain in the textareas - they are not cleared
                    } else {
                        self.showMessage('error', response.data);
                    }
                },
                error: function() {
                    // Hide loading state
                    $button.removeClass('loading').prop('disabled', false);
                    $button.find('.btn-text').show();
                    $button.find('.btn-loading').hide();
                    self.showMessage('error', 'An error occurred. Please try again.');
                }
            });
        },

        updatePassword: function() {
            var self = this;
            var formData = new FormData(document.getElementById('ad-password-form'));
            formData.append('action', 'vics_update_password');
            formData.append('vics_profile_nonce', vicsProfileData.nonce);

            // Show loading state - button shrinks and text vanishes
            var $button = $('#ad-save-password');
            $button.addClass('loading').prop('disabled', true);
            $button.find('.btn-text').hide();
            $button.find('.btn-loading').show();

            $.ajax({
                url: vicsProfileData.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    // Hide loading state
                    $button.removeClass('loading').prop('disabled', false);
                    $button.find('.btn-text').show();
                    $button.find('.btn-loading').hide();

                    if (response.success) {
                        self.showMessage('success', response.data.message);
                        // Clear password fields
                        $('#ad-password-form input[type="password"]').val('');
                    } else {
                        self.showMessage('error', response.data);
                    }
                },
                error: function() {
                    // Hide loading state
                    $button.removeClass('loading').prop('disabled', false);
                    $button.find('.btn-text').show();
                    $button.find('.btn-loading').hide();
                    self.showMessage('error', 'An error occurred. Please try again.');
                }
            });
        },

        updateHeaderSocialLinks: function() {
            var self = this;
            
            // Get social media URLs from the form
            $('[data-platform]').each(function() {
                var platform = $(this).data('platform');
                var inputField = $('input[name="' + platform + '_url"]');
                var url = inputField.val();
                var href = url ? url : '#';
                $(this).attr('href', href).toggleClass('social-empty', !url);
            });
        },

        uploadAvatar: function() {
            var self = this;
            var fileInput = document.getElementById('avatar-upload-input');
            var file = fileInput.files[0];

            if (!file) return;

            var formData = new FormData();
            formData.append('action', 'vics_upload_avatar');
            formData.append('vics_profile_nonce', vicsProfileData.nonce);
            formData.append('avatar', file);

            $.ajax({
                url: vicsProfileData.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        $('#profile-avatar img').attr('src', response.data.avatar_url);
                        $('#profile-avatar .avatar-upload-overlay').hide();
                        self.showMessage('success', response.data.message);
                    } else {
                        self.showMessage('error', response.data);
                    }
                },
                error: function() {
                    self.showMessage('error', 'Upload failed. Please try again.');
                }
            });
        },

        updateSocialLinks: function() {
            var self = this;
            var socialData = {};

            $('.social-link-input').each(function() {
                var platform = $(this).data('platform');
                socialData[platform + '_url'] = $(this).val();
            });

            $.ajax({
                url: vicsProfileData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vics_update_social_links',
                    vics_profile_nonce: vicsProfileData.nonce,
                    ...socialData
                },
                success: function(response) {
                    if (response.success) {
                        // Update header link states
                        $('[data-platform]').each(function() {
                            var platform = $(this).data('platform');
                            var url = socialData[platform + '_url'];
                            var href = url ? url : '#';
                            $(this).attr('href', href).toggleClass('social-empty', !url);
                        });
                    }
                }
            });
        },

        updateHeaderInfo: function() {
            // Update header name
            var firstName = $('input[name="first_name"]').val();
            var lastName = $('input[name="last_name"]').val();
            $('.profile-details h1').text(firstName + ' ' + lastName);

            // Update header email
            var email = $('input[name="email"]').val();
            $('.contact-info:contains("Email:")').html('Email: ' + email);

            // Update header phone
            var phone = $('input[name="phone"]').val();
            $('.contact-info:contains("Phone:")').html('Phone: ' + (phone || 'Not set'));
        },

        showMessage: function(type, message) {
            var messageEl = type === 'success' ? '#vics-success-message' : '#vics-error-message';

            $(messageEl).text(message);
            $(messageEl).fadeIn();

            // Auto-hide after 5 seconds
            setTimeout(function() {
                $(messageEl).fadeOut();
            }, 5000);
        },

        openLicenseModal: function() {
            var self = this;
            $('#vics-license-form')[0].reset();
            $('#vics-license-id').val('');
            
            // Check if user has existing license data
            if (vicsProfileData.primaryLicense) {
                var license = vicsProfileData.primaryLicense;
                $('#vics-license-modal-title').text('Update License');
                $('#vics-submit-license .btn-text').text('Update License');
                $('#vics-license-id').val(license.id || '');
                $('#vics-license-state').val(license.license_state || '');
                $('#vics-license-number').val(license.license_number || '');
                $('#vics-issue-date').val(license.issue_date || '');
                $('#vics-expiry-date').val(license.expiry_date || '');
                $('#vics-license-notes').val(license.notes || '');
            } else {
                $('#vics-license-modal-title').text('Add License');
                $('#vics-submit-license .btn-text').text('Submit License');
            }
            
            $('#vics-license-modal').fadeIn(300);
        },

        closeLicenseModal: function() {
            $('#vics-license-modal').fadeOut(300);
            $('#vics-license-form')[0].reset();
            $('#vics-submit-license .btn-text').text('Submit License');
        },

        submitLicense: function() {
            var self = this;
            var formData = new FormData(document.getElementById('vics-license-form'));
            var licenseId = $('#vics-license-id').val();
            
            formData.append('action', licenseId ? 'vics_update_license' : 'vics_add_license');
            formData.append('vics_profile_nonce', vicsProfileData.nonce);

            var $button = $('#vics-submit-license');
            $button.addClass('loading').prop('disabled', true);
            $button.find('.btn-text').hide();
            $button.find('.btn-loading').show();

            $.ajax({
                url: vicsProfileData.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    $button.removeClass('loading').prop('disabled', false);
                    $button.find('.btn-text').show();
                    $button.find('.btn-loading').hide();

                    if (response.success) {
                        self.showMessage('success', response.data.message);
                        self.closeLicenseModal();
                        // Reload page after 1.5 seconds to show updated license status
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        self.showMessage('error', response.data);
                    }
                },
                error: function() {
                    $button.removeClass('loading').prop('disabled', false);
                    $button.find('.btn-text').show();
                    $button.find('.btn-loading').hide();
                    self.showMessage('error', 'An error occurred. Please try again.');
                }
            });
        }
    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        if (typeof vicsProfileData !== 'undefined') {
            VICS_Profile.init();
        }
    });

})(jQuery);
