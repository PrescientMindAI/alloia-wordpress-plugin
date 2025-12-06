/**
 * AlloIA Knowledge Graph JavaScript
 *
 * @package AlloIA_WooCommerce
 */

(function($) {
    'use strict';

    var AlloIAKnowledgeGraph = {
        init: function() {
            this.bindEvents();
            this.initializeFormValidation();
            this.setupAutoRefresh();
        },

        bindEvents: function() {
            // Export form submission
            $(document).on('submit', '#export-form', this.handleExportSubmission);
            
            // Batch size update
            $(document).on('submit', 'form[name="batch-size-form"]', this.handleBatchSizeUpdate);
            
            // Category filter change
            $(document).on('change', '#category', this.handleCategoryChange);
            
            // Price range validation
            $(document).on('input', '#min_price, #max_price', this.validatePriceRange);
            
            // Date range validation
            $(document).on('change', '#date_from, #date_to', this.validateDateRange);
        },

        handleExportSubmission: function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var $submitButton = $form.find('input[type="submit"]');
            var originalText = $submitButton.val();
            
            // Show loading state
            $submitButton.val(alloia_kg_ajax.strings.loading).prop('disabled', true);
            
            // Collect form data
            var formData = {
                action: 'alloia_export_products',
                nonce: alloia_kg_ajax.nonce,
                filters: JSON.stringify({
                    category: $('#category').val(),
                    min_price: $('#min_price').val(),
                    max_price: $('#max_price').val(),
                    in_stock: $('#in_stock').val(),
                    date_from: $('#date_from').val(),
                    date_to: $('#date_to').val()
                }),
                background: $('#background_export').is(':checked')
            };
            
            // Make AJAX call
            $.post(alloia_kg_ajax.ajax_url, formData)
                .done(function(response) {
                    if (response.success) {
                        AlloIAKnowledgeGraph.showNotification(alloia_kg_ajax.strings.export_started, 'success');
                        
                        // Reset form if export was successful
                        if (response.data && response.data.export_id) {
                            $form[0].reset();
                            AlloIAKnowledgeGraph.startExportStatusCheck(response.data.export_id);
                        }
                    } else {
                        AlloIAKnowledgeGraph.showNotification(response.data.message || alloia_kg_ajax.strings.export_failed, 'error');
                    }
                })
                .fail(function() {
                    AlloIAKnowledgeGraph.showNotification(alloia_kg_ajax.strings.export_failed, 'error');
                })
                .always(function() {
                    // Restore button state
                    $submitButton.val(originalText).prop('disabled', false);
                });
        },

        handleBatchSizeUpdate: function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var $submitButton = $form.find('input[type="submit"]');
            var originalText = $submitButton.val();
            var batchSize = $('#batch_size').val();
            
            // Show loading state
            $submitButton.val(alloia_kg_ajax.strings.loading).prop('disabled', true);
            
            // Make AJAX call
            $.post(alloia_kg_ajax.ajax_url, {
                action: 'alloia_update_batch_size',
                nonce: alloia_kg_ajax.nonce,
                batch_size: batchSize
            })
                .done(function(response) {
                    if (response.success) {
                        AlloIAKnowledgeGraph.showNotification(alloia_kg_ajax.strings.batch_size_updated, 'success');
                    } else {
                        AlloIAKnowledgeGraph.showNotification(response.data.message || 'Failed to update batch size', 'error');
                    }
                })
                .fail(function() {
                    AlloIAKnowledgeGraph.showNotification('Failed to update batch size', 'error');
                })
                .always(function() {
                    // Restore button state
                    $submitButton.val(originalText).prop('disabled', false);
                });
        },

        handleCategoryChange: function() {
            var category = $(this).val();
            
            // Enable/disable price filters based on category selection
            if (category) {
                $('#min_price, #max_price').prop('disabled', false);
            } else {
                $('#min_price, #max_price').prop('disabled', true).val('');
            }
        },

        validatePriceRange: function() {
            var minPrice = parseFloat($('#min_price').val()) || 0;
            var maxPrice = parseFloat($('#max_price').val()) || 0;
            
            if (maxPrice > 0 && minPrice > maxPrice) {
                $('#max_price').addClass('error');
                $('#min_price').addClass('error');
            } else {
                $('#max_price').removeClass('error');
                $('#min_price').removeClass('error');
            }
        },

        validateDateRange: function() {
            var dateFrom = $('#date_from').val();
            var dateTo = $('#date_to').val();
            
            if (dateFrom && dateTo && dateFrom > dateTo) {
                $('#date_to').addClass('error');
                $('#date_from').addClass('error');
            } else {
                $('#date_to').removeClass('error');
                $('#date_from').removeClass('error');
            }
        },

        initializeFormValidation: function() {
            // Add validation attributes to form fields
            $('#min_price, #max_price').attr('type', 'number').attr('step', '0.01').attr('min', '0');
            $('#batch_size').attr('min', '10').attr('max', '1000');
            
            // Initialize category change handler
            this.handleCategoryChange();
        },

        setupAutoRefresh: function() {
            // Refresh export statistics every 30 seconds if on the page
            setInterval(function() {
                if ($('#export-form').length > 0) {
                    AlloIAKnowledgeGraph.refreshExportStats();
                }
            }, 30000);
        },

        refreshExportStats: function() {
            // This would call an endpoint to refresh export statistics
            // For now, just update the display if we have new data
            var $statsGrid = $('.stats-grid');
            if ($statsGrid.length > 0) {
                // Add a subtle refresh indicator
                $statsGrid.addClass('refreshing');
                setTimeout(function() {
                    $statsGrid.removeClass('refreshing');
                }, 1000);
            }
        },

        startExportStatusCheck: function(exportId) {
            // Check export status every 10 seconds
            var statusCheck = setInterval(function() {
                $.post(alloia_kg_ajax.ajax_url, {
                    action: 'alloia_get_export_status',
                    nonce: alloia_kg_ajax.nonce,
                    export_id: exportId
                })
                    .done(function(response) {
                        if (response.success && response.data) {
                            var status = response.data.status;
                            
                            if (status === 'completed' || status === 'failed') {
                                clearInterval(statusCheck);
                                
                                if (status === 'completed') {
                                    AlloIAKnowledgeGraph.showNotification('Export completed successfully!', 'success');
                                } else {
                                    AlloIAKnowledgeGraph.showNotification('Export failed. Please check the logs.', 'error');
                                }
                                
                                // Refresh the page to show updated statistics
                                setTimeout(function() {
                                    location.reload();
                                }, 2000);
                            }
                        }
                    })
                    .fail(function() {
                        // If status check fails, stop checking
                        clearInterval(statusCheck);
                    });
            }, 10000);
        },

        showNotification: function(message, type) {
            // Remove existing notifications
            $('.alloia-notification').remove();
            
            // Create notification element
            var $notification = $('<div class="alloia-notification notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            
            // Insert at the top of the page
            $('.wrap h1').after($notification);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notification.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
            
            // Make dismissible
            $notification.find('.notice-dismiss').on('click', function() {
                $notification.remove();
            });
        },

        // Utility functions
        formatNumber: function(number) {
            return number.toLocaleString();
        },

        formatDate: function(dateString) {
            if (!dateString) return 'Never';
            var date = new Date(dateString);
            return date.toLocaleDateString();
        },

        formatCurrency: function(amount, currency) {
            currency = currency || 'USD';
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: currency
            }).format(amount);
        }
    };

    $(document).ready(function() {
        AlloIAKnowledgeGraph.init();
    });

    window.AlloIAKnowledgeGraph = AlloIAKnowledgeGraph;

})(jQuery);
