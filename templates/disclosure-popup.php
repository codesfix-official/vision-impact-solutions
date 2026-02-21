<?php
/**
 * Login Disclosure Popup Template
 *
 * @package VisionImpactCustomSolutions
 */

if (!defined('ABSPATH')) {
    exit;
}

$default_disclosure_text = 'By accessing and using the tools, training, systems, and resources provided on this website, you acknowledge that there is no guarantee of success, income, or specific results. These resources are intended to support your development, but your success depends entirely on your personal effort, discipline, consistency, and ability to take action. You understand and agree that you are solely responsible for your own performance and outcomes as an independent agent, and results will vary based on individual commitment and execution.';

$disclosure_text = get_option('vics_disclosure_text', $default_disclosure_text);
$disclosure_text = !empty($disclosure_text) ? $disclosure_text : $default_disclosure_text;
?>

<div id="vics-disclosure-overlay" class="vics-disclosure-overlay" data-nonce="<?php echo esc_attr($disclosure_nonce); ?>">
    <div class="vics-disclosure-modal" role="dialog" aria-modal="true" aria-labelledby="vics-disclosure-title" tabindex="-1">
        <h3 id="vics-disclosure-title">Disclosure Notice</h3>
        <p><?php echo nl2br(esc_html($disclosure_text)); ?></p>
        <div class="vics-disclosure-actions">
            <button type="button" id="vics-disclosure-continue" class="vics-disclosure-btn">I Understand</button>
        </div>
    </div>
</div>

<style>
.vics-disclosure-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.65);
    z-index: 100001;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.vics-disclosure-modal {
    width: 100%;
    max-width: 760px;
    background: #fff;
    border-radius: 10px;
    padding: 28px;
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.25);
}

.vics-disclosure-modal h3 {
    margin: 0 0 14px;
    color: #2f3f58;
    font-size: 24px;
}

.vics-disclosure-modal p {
    margin: 0;
    color: #374151;
    line-height: 1.7;
    font-size: 15px;
}

.vics-disclosure-actions {
    margin-top: 22px;
    display: flex;
    justify-content: flex-end;
}

.vics-disclosure-btn {
    border: 0;
    background: #2f6db0;
    color: #fff;
    padding: 11px 18px;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
}

.vics-disclosure-btn:hover {
    background: #275d97;
}

body.vics-disclosure-lock {
    overflow: hidden !important;
}
</style>

<script>
(function($) {
    'use strict';

    var $overlay = $('#vics-disclosure-overlay');
    var $modal = $overlay.find('.vics-disclosure-modal');
    var $button = $('#vics-disclosure-continue');

    function lockDisclosureInteraction() {
        $('body').addClass('vics-disclosure-lock');
        $button.trigger('focus');
    }

    function unlockDisclosureInteraction() {
        $('body').removeClass('vics-disclosure-lock');
        $(document).off('keydown.vicsDisclosure');
        $(document).off('focusin.vicsDisclosure');
        $(document).off('wheel.vicsDisclosure');
        $(document).off('touchmove.vicsDisclosure');
        $(document).off('mousedown.vicsDisclosure');
    }

    // Strict keyboard lock: no Escape dismissal, focus trapped in modal.
    $(document).on('keydown.vicsDisclosure', function(e) {
        if (!$overlay.length || !$overlay.is(':visible')) {
            return;
        }

        if (e.key === 'Escape') {
            e.preventDefault();
            e.stopPropagation();
            return false;
        }

        if (e.key === 'Tab') {
            e.preventDefault();
            $button.trigger('focus');
            return false;
        }
    });

    // Keep focus inside modal at all times.
    $(document).on('focusin.vicsDisclosure', function(e) {
        if (!$overlay.length || !$overlay.is(':visible')) {
            return;
        }

        if (!$modal[0].contains(e.target)) {
            e.stopPropagation();
            $button.trigger('focus');
        }
    });

    // Block page interactions outside the disclosure while visible.
    $(document).on('wheel.vicsDisclosure touchmove.vicsDisclosure mousedown.vicsDisclosure', function(e) {
        if (!$overlay.length || !$overlay.is(':visible')) {
            return;
        }

        if (!$modal[0].contains(e.target)) {
            e.preventDefault();
        }
    });

    lockDisclosureInteraction();

    $(document).on('click', '#vics-disclosure-continue', function() {
        var nonce = $overlay.data('nonce');

        $.ajax({
            url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
            type: 'POST',
            data: {
                action: 'vics_acknowledge_disclosure',
                nonce: nonce
            },
            complete: function() {
                unlockDisclosureInteraction();
                $overlay.fadeOut(200, function() {
                    $(this).remove();
                });
            }
        });
    });
})(jQuery);
</script>
