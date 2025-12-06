<?php
/**
 * Common Dashboard Styles and Scripts
 * 
 * @package AlloIA_WooCommerce
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>

<style>
/* Common AlloIA Dashboard Styles */
.alloia-settings {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
}

.alloia-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.alloia-card h3 {
    margin-top: 0;
    border-bottom: 1px solid #eee;
    padding-bottom: 10px;
}

.alloia-info-box {
    background: #f0f6fc;
    border-left: 4px solid #0073aa;
    padding: 12px;
    margin: 16px 0;
}

.alloia-success-box {
    background: #ecf7ed;
    border-left: 4px solid #46b450;
    padding: 12px;
    margin: 16px 0;
}

.alloia-warning-box {
    background: #fff8e5;
    border-left: 4px solid #ffb900;
    padding: 12px;
    margin: 16px 0;
}

.alloia-error-box {
    background: #fcf0f1;
    border-left: 4px solid #dc3232;
    padding: 12px;
    margin: 16px 0;
}

.alloia-button-primary {
    background: #0073aa;
    border-color: #0073aa;
    color: #fff;
    text-decoration: none;
    padding: 8px 12px;
    border-radius: 3px;
    display: inline-block;
    transition: all 0.2s;
}

.alloia-button-primary:hover {
    background: #005a87;
    border-color: #005a87;
    color: #fff;
}

.alloia-button-secondary {
    background: #f7f7f7;
    border: 1px solid #ccc;
    color: #555;
    text-decoration: none;
    padding: 8px 12px;
    border-radius: 3px;
    display: inline-block;
    transition: all 0.2s;
}

.alloia-button-secondary:hover {
    background: #fafafa;
    border-color: #999;
    color: #23282d;
}

.alloia-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin: 20px 0;
}

.alloia-stat-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 16px;
    text-align: center;
}

.alloia-stat-value {
    font-size: 32px;
    font-weight: bold;
    color: #0073aa;
    display: block;
    margin-bottom: 8px;
}

.alloia-stat-label {
    font-size: 14px;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.alloia-form-table {
    width: 100%;
    margin-top: 20px;
}

.alloia-form-table th {
    text-align: left;
    padding: 12px 0;
    font-weight: 600;
}

.alloia-form-table td {
    padding: 12px 0;
}

.alloia-form-table input[type="text"],
.alloia-form-table input[type="url"],
.alloia-form-table input[type="email"],
.alloia-form-table select,
.alloia-form-table textarea {
    width: 100%;
    max-width: 500px;
}

.alloia-help-text {
    font-size: 13px;
    color: #666;
    font-style: italic;
    margin-top: 4px;
}

.alloia-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.alloia-badge-free {
    background: #ecf7ed;
    color: #46b450;
}

.alloia-badge-pro {
    background: #fff8e5;
    color: #ffb900;
}

.alloia-badge-active {
    background: #ecf7ed;
    color: #46b450;
}

.alloia-badge-inactive {
    background: #f0f0f1;
    color: #666;
}

/* Loading Spinner */
.alloia-spinner {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 3px solid rgba(0,115,170,.2);
    border-radius: 50%;
    border-top-color: #0073aa;
    animation: alloia-spin 1s ease-in-out infinite;
}

@keyframes alloia-spin {
    to { transform: rotate(360deg); }
}

/* Responsive */
@media (max-width: 768px) {
    .alloia-stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    'use strict';
    
    /**
     * Common AlloIA Dashboard JavaScript
     */
    
    // Add loading state to buttons
    $('.alloia-settings').on('click', 'button[type="submit"], .alloia-button-primary', function() {
        var $button = $(this);
        if (!$button.hasClass('alloia-loading')) {
            $button.addClass('alloia-loading').prop('disabled', true);
            
            // Add spinner if it doesn't exist
            if (!$button.find('.alloia-spinner').length) {
                $button.append(' <span class="alloia-spinner"></span>');
            }
        }
    });
    
    // Auto-hide notices after 5 seconds
    setTimeout(function() {
        $('.notice.is-dismissible').fadeOut(500, function() {
            $(this).remove();
        });
    }, 5000);
    
    // Copy to clipboard functionality
    $('.alloia-copy-button').on('click', function(e) {
        e.preventDefault();
        var $button = $(this);
        var textToCopy = $button.data('copy-text') || $button.prev('input').val();
        
        // Create temporary textarea
        var $temp = $('<textarea>');
        $('body').append($temp);
        $temp.val(textToCopy).select();
        
        try {
            document.execCommand('copy');
            $button.text('✓ Copied!');
            setTimeout(function() {
                $button.text('Copy');
            }, 2000);
        } catch(err) {
            console.error('Failed to copy text:', err);
        }
        
        $temp.remove();
    });
    
    // Confirm dialogs for dangerous actions
    $('.alloia-confirm-action').on('click', function(e) {
        var confirmMessage = $(this).data('confirm') || 'Are you sure?';
        if (!confirm(confirmMessage)) {
            e.preventDefault();
            return false;
        }
    });
    
    // Toggle visibility
    $('.alloia-toggle-trigger').on('click', function(e) {
        e.preventDefault();
        var targetSelector = $(this).data('toggle-target');
        $(targetSelector).slideToggle(300);
    });
    
    // AJAX form submissions with better error handling
    $('.alloia-ajax-form').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $submitButton = $form.find('button[type="submit"]');
        var $messageContainer = $form.find('.alloia-form-message');
        
        // Show loading state
        $submitButton.prop('disabled', true).addClass('alloia-loading');
        $messageContainer.html('<div class="alloia-info-box">Processing...</div>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: $form.serialize(),
            success: function(response) {
                if (response.success) {
                    $messageContainer.html('<div class="alloia-success-box">' + response.data.message + '</div>');
                } else {
                    $messageContainer.html('<div class="alloia-error-box">' + (response.data.message || 'An error occurred') + '</div>');
                }
            },
            error: function(xhr, status, error) {
                $messageContainer.html('<div class="alloia-error-box">Connection error: ' + error + '</div>');
            },
            complete: function() {
                $submitButton.prop('disabled', false).removeClass('alloia-loading');
            }
        });
    });
    
    // Initialize tooltips if available
    if (typeof $.fn.tooltip === 'function') {
        $('.alloia-tooltip').tooltip();
    }
    
    // Console log for debugging (only in debug mode)
    if (window.alloiaDebug) {
        console.log('AlloIA Dashboard Scripts Loaded');
    }
});
</script>

