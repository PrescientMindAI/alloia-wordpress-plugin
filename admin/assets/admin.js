/**
 * AlloIA WooCommerce Plugin - Admin JavaScript
 * 
 * @package AlloIA_WooCommerce
 */

(function($) {
    'use strict';

    // AlloIA Admin namespace
    window.AlloIA_Admin = window.AlloIA_Admin || {};

    /**
     * Initialize admin functionality
     */
    AlloIA_Admin.init = function() {
        this.initTabs();
        this.initToggles();
        this.initForms();
        this.initNotifications();
        this.initTooltips();
        this.initProgressBars();
        this.initDataTables();
        this.initLazyLoading();
        this.bindEvents();
    };

    /**
     * Initialize tab navigation
     */
    AlloIA_Admin.initTabs = function() {
        $('.alloia-nav-tabs .nav-tab').on('click', function(e) {
            e.preventDefault();
            
            var targetTab = $(this).attr('href');
            var tabContainer = $(this).closest('.alloia-admin-wrap');
            
            // Update active tab
            tabContainer.find('.nav-tab').removeClass('active');
            $(this).addClass('active');
            
            // Show target content
            tabContainer.find('.tab-content').removeClass('active');
            $(targetTab).addClass('active');
            
            // Update URL hash
            if (history.pushState) {
                history.pushState(null, null, targetTab);
            }
        });

        // Handle direct links to tabs
        if (window.location.hash) {
            var hash = window.location.hash;
            var tabContainer = $('.alloia-admin-wrap');
            var targetTab = tabContainer.find('.nav-tab[href="' + hash + '"]');
            
            if (targetTab.length) {
                targetTab.trigger('click');
            }
        }
    };

    /**
     * Initialize toggle switches
     */
    AlloIA_Admin.initToggles = function() {
        $('.alloia-toggle input[type="checkbox"]').on('change', function() {
            var toggle = $(this);
            var settingName = toggle.attr('name');
            var isEnabled = toggle.is(':checked');
            
            // Show loading state
            toggle.prop('disabled', true);
            toggle.closest('.alloia-toggle').addClass('loading');
            
            // Save setting via AJAX
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'alloia_save_setting',
                    setting: settingName,
                    value: isEnabled ? '1' : '0',
                    nonce: alloia_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        AlloIA_Admin.showNotification('Setting saved successfully!', 'success');
                        
                        // Update UI state
                        toggle.closest('.alloia-setting-group').toggleClass('enabled', isEnabled);
                    } else {
                        AlloIA_Admin.showNotification('Failed to save setting: ' + response.data, 'error');
                        toggle.prop('checked', !isEnabled); // Revert
                    }
                },
                error: function() {
                    AlloIA_Admin.showNotification('Failed to save setting. Please try again.', 'error');
                    toggle.prop('checked', !isEnabled); // Revert
                },
                complete: function() {
                    toggle.prop('disabled', false);
                    toggle.closest('.alloia-toggle').removeClass('loading');
                }
            });
        });
    };

    /**
     * Initialize form handling
     */
    AlloIA_Admin.initForms = function() {
        // Handle form submissions
        $('.alloia-form').on('submit', function(e) {
            var form = $(this);
            var submitBtn = form.find('input[type="submit"], button[type="submit"]');
            
            // Prevent double submission
            if (submitBtn.hasClass('submitting')) {
                e.preventDefault();
                return false;
            }
            
            // Add loading state
            submitBtn.addClass('submitting').prop('disabled', true);
            submitBtn.val('Saving...');
            
            // Form will submit normally, but we can add AJAX handling here if needed
        });

        // Handle input validation
        $('.alloia-form input, .alloia-form select, .alloia-form textarea').on('blur', function() {
            AlloIA_Admin.validateField($(this));
        });
    };

    /**
     * Validate form field
     */
    AlloIA_Admin.validateField = function(field) {
        var value = field.val();
        var required = field.prop('required');
        var type = field.attr('type');
        var isValid = true;
        var errorMessage = '';
        
        // Remove existing error
        field.removeClass('error');
        field.siblings('.field-error').remove();
        
        // Required field validation
        if (required && (!value || value.trim() === '')) {
            isValid = false;
            errorMessage = 'This field is required.';
        }
        
        // Email validation
        if (type === 'email' && value && !AlloIA_Admin.isValidEmail(value)) {
            isValid = false;
            errorMessage = 'Please enter a valid email address.';
        }
        
        // URL validation
        if (type === 'url' && value && !AlloIA_Admin.isValidUrl(value)) {
            isValid = false;
            errorMessage = 'Please enter a valid URL.';
        }
        
        // Show error if invalid
        if (!isValid) {
            field.addClass('error');
            field.after('<div class="field-error">' + errorMessage + '</div>');
        }
        
        return isValid;
    };

    /**
     * Email validation helper
     */
    AlloIA_Admin.isValidEmail = function(email) {
        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    };

    /**
     * URL validation helper
     */
    AlloIA_Admin.isValidUrl = function(url) {
        try {
            new URL(url);
            return true;
        } catch (e) {
            return false;
        }
    };

    /**
     * Initialize notification system
     */
    AlloIA_Admin.initNotifications = function() {
        // Create notification container if it doesn't exist
        if (!$('#alloia-notifications').length) {
            $('body').append('<div id="alloia-notifications"></div>');
        }
    };

    /**
     * Show notification
     */
    AlloIA_Admin.showNotification = function(message, type, duration) {
        type = type || 'info';
        duration = duration || 5000;
        
        var notification = $('<div class="alloia-notification alloia-notification-' + type + '">' +
            '<span class="notification-message">' + message + '</span>' +
            '<button class="notification-close">&times;</button>' +
            '</div>');
        
        $('#alloia-notifications').append(notification);
        
        // Show notification
        setTimeout(function() {
            notification.addClass('show');
        }, 100);
        
        // Auto-hide
        if (duration > 0) {
            setTimeout(function() {
                AlloIA_Admin.hideNotification(notification);
            }, duration);
        }
        
        // Close button
        notification.find('.notification-close').on('click', function() {
            AlloIA_Admin.hideNotification(notification);
        });
        
        return notification;
    };

    /**
     * Hide notification
     */
    AlloIA_Admin.hideNotification = function(notification) {
        notification.removeClass('show');
        setTimeout(function() {
            notification.remove();
        }, 300);
    };

    /**
     * Initialize tooltips
     */
    AlloIA_Admin.initTooltips = function() {
        $('[data-tooltip]').each(function() {
            var element = $(this);
            var tooltipText = element.attr('data-tooltip');
            
            element.on('mouseenter', function() {
                AlloIA_Admin.showTooltip(element, tooltipText);
            }).on('mouseleave', function() {
                AlloIA_Admin.hideTooltip();
            });
        });
    };

    /**
     * Show tooltip
     */
    AlloIA_Admin.showTooltip = function(element, text) {
        var tooltip = $('<div class="alloia-tooltip">' + text + '</div>');
        $('body').append(tooltip);
        
        var elementOffset = element.offset();
        var elementHeight = element.outerHeight();
        
        tooltip.css({
            position: 'absolute',
            top: elementOffset.top - tooltip.outerHeight() - 10,
            left: elementOffset.left + (element.outerWidth() / 2) - (tooltip.outerWidth() / 2),
            zIndex: 9999
        });
        
        setTimeout(function() {
            tooltip.addClass('show');
        }, 100);
    };

    /**
     * Hide tooltip
     */
    AlloIA_Admin.hideTooltip = function() {
        $('.alloia-tooltip').removeClass('show');
        setTimeout(function() {
            $('.alloia-tooltip').remove();
        }, 200);
    };

    /**
     * Initialize progress bars
     */
    AlloIA_Admin.initProgressBars = function() {
        $('.alloia-progress').each(function() {
            var progressBar = $(this);
            var progressValue = progressBar.data('value') || 0;
            var progressMax = progressBar.data('max') || 100;
            var percentage = Math.min((progressValue / progressMax) * 100, 100);
            
            progressBar.find('.alloia-progress-bar').css('width', percentage + '%');
        });
    };

    /**
     * Initialize data tables
     */
    AlloIA_Admin.initDataTables = function() {
        $('.alloia-table').each(function() {
            var table = $(this);
            
            // Add sorting functionality
            table.find('th[data-sortable]').on('click', function() {
                var column = $(this);
                var columnIndex = column.index();
                var isAscending = column.hasClass('sort-asc');
                
                // Update sort indicators
                table.find('th').removeClass('sort-asc sort-desc');
                column.addClass(isAscending ? 'sort-desc' : 'sort-asc');
                
                // Sort table rows
                var rows = table.find('tbody tr').get();
                rows.sort(function(a, b) {
                    var aValue = $(a).find('td').eq(columnIndex).text();
                    var bValue = $(b).find('td').eq(columnIndex).text();
                    
                    if (isAscending) {
                        return aValue.localeCompare(bValue);
                    } else {
                        return bValue.localeCompare(aValue);
                    }
                });
                
                table.find('tbody').empty().append(rows);
            });
        });
    };

    // Charts initialization removed - not currently used

    /**
     * Bind global events
     */
    AlloIA_Admin.bindEvents = function() {
        // Handle window resize
        $(window).on('resize', function() {
            AlloIA_Admin.handleResize();
        });
        
        // Page visibility handling removed - data refresh not implemented
    };

    /**
     * Handle window resize
     */
    AlloIA_Admin.handleResize = function() {
        // Adjust responsive elements
        if ($(window).width() <= 782) {
            $('.alloia-dashboard-grid').addClass('mobile');
        } else {
            $('.alloia-dashboard-grid').removeClass('mobile');
        }
    };

    /**
     * Utility function to format numbers
     */
    AlloIA_Admin.formatNumber = function(number, decimals) {
        decimals = decimals || 0;
        return parseFloat(number).toFixed(decimals).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    };

    /**
     * Utility function to debounce function calls
     */
    AlloIA_Admin.debounce = function(func, wait) {
        var timeout;
        return function executedFunction() {
            var later = function() {
                timeout = null;
                func.apply(this, arguments);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    };

    /**
     * Utility function to throttle function calls
     */
    AlloIA_Admin.throttle = function(func, limit) {
        var inThrottle;
        return function() {
            var args = arguments;
            var context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(function() {
                    inThrottle = false;
                }, limit);
            }
        };
    };

    /**
     * Initialize lazy loading for dashboard widgets
     */
    AlloIA_Admin.initLazyLoading = function() {
        // Only initialize on dashboard pages
        if (!$('.alloia-dashboard-widget').length) {
            return;
        }

        // Load widget data only when they become visible
        $('.alloia-dashboard-widget[data-lazy-load]').each(function() {
            var $widget = $(this);
            var observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        AlloIA_Admin.loadWidgetData($widget);
                        observer.unobserve(entry.target);
                    }
                });
            }, {
                threshold: 0.1,
                rootMargin: '50px'
            });
            
            observer.observe(this);
        });

        // Load data for visible widgets immediately
        $('.alloia-dashboard-widget:not([data-lazy-load])').each(function() {
            var $widget = $(this);
            if (AlloIA_Admin.isElementVisible($widget[0])) {
                AlloIA_Admin.loadWidgetData($widget);
            }
        });
    };

    /**
     * Load widget data via AJAX
     */
    AlloIA_Admin.loadWidgetData = function($widget) {
        var widgetType = $widget.data('widget-type');
        var $content = $widget.find('.widget-content');
        
        if (!widgetType || $widget.data('loaded')) {
            return;
        }

        // Show loading state
        $content.html('<div class="alloia-loading-widget"><div class="alloia-loading"></div><p>Loading data...</p></div>');
        
        // Make AJAX request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'alloia_load_widget_data',
                widget_type: widgetType,
                nonce: $('#alloia_ajax_nonce').val()
            },
            success: function(response) {
                if (response.success) {
                    $content.html(response.data.html);
                    $widget.data('loaded', true);
                    
                    // Trigger custom event for widget loaded
                    $widget.trigger('widget-loaded', [response.data]);
                } else {
                    $content.html('<div class="error">Failed to load data: ' + (response.data || 'Unknown error') + '</div>');
                }
            },
            error: function() {
                $content.html('<div class="error">Failed to load widget data. Please try again.</div>');
            }
        });
    };

    /**
     * Check if element is visible in viewport
     */
    AlloIA_Admin.isElementVisible = function(element) {
        var rect = element.getBoundingClientRect();
        return (
            rect.top >= 0 &&
            rect.left >= 0 &&
            rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
            rect.right <= (window.innerWidth || document.documentElement.clientWidth)
        );
    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        AlloIA_Admin.init();
    });

    // Make functions globally available
    window.AlloIA_Admin = AlloIA_Admin;

})(jQuery); 