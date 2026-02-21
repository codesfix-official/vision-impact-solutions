/**
 * Admin JavaScript
 *
 * @package VisionImpactCustomSolutions
 * @since 1.0.0
 */

(function($) {
    'use strict';

    var VICS_Admin = {

        init: function() {
            this.bindEvents();
            this.initSortable();
        },

        bindEvents: function() {
            var self = this;

            // Add list item
            $(document).on('click', '#vics-add-list-item', function(e) {
                e.preventDefault();
                self.addListItem();
            });

            // Remove list item
            $(document).on('click', '.vics-remove-item', function(e) {
                e.preventDefault();
                $(this).closest('.vics-list-item-row').remove();
            });

            // Add About question
            $(document).on('click', '#vics-add-about-question', function(e) {
                e.preventDefault();
                self.addAboutQuestion();
            });

            // Remove About question
            $(document).on('click', '.vics-remove-about-question', function(e) {
                e.preventDefault();
                $(this).closest('.vics-about-question-row').remove();
            });

            // Reset orientation
            $(document).on('click', '.vics-reset-orientation', function(e) {
                e.preventDefault();
                if (confirm('Are you sure you want to reset this user\'s orientation?')) {
                    window.location.href = $(this).attr('href');
                }
            });

            // License status update
            $(document).on('change', '.license-status-select', function(e) {
                self.updateLicenseStatus($(this));
            });
        },

        initSortable: function() {
            $('#vics-list-items-container').sortable({
                handle: '.vics-drag-handle',
                update: function() {
                    // Update order when items are reordered
                }
            });

            $('#vics-about-questions-container').sortable({
                handle: '.vics-drag-handle',
                update: function() {
                    // Order updates automatically by DOM field order
                }
            });
        },

        addListItem: function() {
            var itemHtml = `
                <div class="vics-list-item-row">
                    <span class="vics-drag-handle dashicons dashicons-menu"></span>
                    <span class="vics-item-icon">✓✓</span>
                    <input type="text" name="vics_list_items[]" value="" class="regular-text" />
                    <button type="button" class="button vics-remove-item">
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                </div>
            `;

            $('#vics-list-items-container').append(itemHtml);
        },

        addAboutQuestion: function() {
            var questionId = 'question_' + Date.now() + '_' + Math.floor(Math.random() * 1000);

            var itemHtml = `
                <div class="vics-list-item-row vics-about-question-row" data-question-id="${questionId}">
                    <span class="vics-drag-handle dashicons dashicons-menu"></span>
                    <input type="text" name="vics_about_questions[${questionId}]" value="" class="regular-text" placeholder="Enter question text" />
                    <button type="button" class="button vics-remove-about-question">
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                </div>
            `;

            $('#vics-about-questions-container').append(itemHtml);
        },

        updateLicenseStatus: function($select) {
            var self = this;
            var licenseId = $select.data('license-id');
            var newStatus = $select.val();
            var currentStatus = $select.data('current-status');

            // Don't do anything if status hasn't changed
            if (newStatus === currentStatus) {
                return;
            }

            // Show loading state
            $select.prop('disabled', true);

            $.ajax({
                url: vicsAdminData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vics_update_license_status',
                    license_id: licenseId,
                    new_status: newStatus,
                    nonce: vicsAdminData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Update the data attribute
                        $select.data('current-status', newStatus);
                        // Update the status badge
                        var $badge = $select.closest('tr').find('.vics-status-badge');
                        $badge.removeClass('vics-status-' + currentStatus).addClass('vics-status-' + newStatus);
                        $badge.text(newStatus.charAt(0).toUpperCase() + newStatus.slice(1));
                        
                        // Show success message
                        self.showNotice('License status updated successfully!', 'success');
                    } else {
                        // Revert the select
                        $select.val(currentStatus);
                        self.showNotice(response.data || 'Failed to update license status.', 'error');
                    }
                },
                error: function() {
                    // Revert the select
                    $select.val(currentStatus);
                    self.showNotice('An error occurred while updating the license status.', 'error');
                },
                complete: function() {
                    // Re-enable the select
                    $select.prop('disabled', false);
                }
            });
        },

        showNotice: function(message, type) {
            // Remove existing notices
            $('.notice').remove();
            
            var noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
            var noticeHtml = '<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>';
            
            $('.wrap h1').after(noticeHtml);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $('.notice').fadeOut();
            }, 5000);
        }
    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        if (typeof vicsAdminData !== 'undefined') {
            VICS_Admin.init();
        }
    });

})(jQuery);
