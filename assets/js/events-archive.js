/**
 * Events Archive JavaScript
 *
 * @package VisionImpactCustomSolutions
 * @since 1.1.1
 */

(function($) {
    'use strict';

    var VICS_Events_Archive = {

        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            // Search functionality with debounce
            var searchTimeout;
            $('#events-search').on('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    VICS_Events_Archive.searchEvents();
                }, 300);
            });

            // Clear search on escape
            $('#events-search').on('keydown', function(e) {
                if (e.keyCode === 27) { // Escape key
                    $(this).val('');
                    VICS_Events_Archive.searchEvents();
                }
            });
        },

        searchEvents: function() {
            var searchTerm = $('#events-search').val().trim();

            // Show loading state
            $('#events-list').html('<div class="events-loading"></div>');

            $.ajax({
                url: vics_events_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'search_events',
                    search_term: searchTerm,
                    nonce: vics_events_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#events-list').html(response.data.html);
                    } else {
                        $('#events-list').html('<p class="no-events">Error loading events. Please try again.</p>');
                    }
                },
                error: function() {
                    $('#events-list').html('<p class="no-events">Error loading events. Please try again.</p>');
                }
            });
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        VICS_Events_Archive.init();
    });

})(jQuery);