<?php
/**
 * Pro Features Template
 * 
 * @package AlloIA_WooCommerce
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get WooCommerce product count for plan calculation
$product_count = 0;
if (class_exists('WooCommerce')) {
    $args = array(
        'post_type' => 'product',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids'
    );
    $products = get_posts($args);
    $product_count = count($products);
}

// Calculate recommended plan
$recommended_plan = '';
$plan_price = '';
$plan_description = '';
if ($product_count <= 1000) {
    $recommended_plan = 'Knowledge Graph 1K';
    $plan_price = '$250 CAD/month';
    $plan_description = 'Perfect for stores with up to 1,000 products';
} elseif ($product_count <= 5000) {
    $recommended_plan = 'Knowledge Graph 5K';
    $plan_price = '$400 CAD/month';
    $plan_description = 'Ideal for stores with up to 5,000 products';
} elseif ($product_count <= 10000) {
    $recommended_plan = 'Knowledge Graph 10K';
    $plan_price = '$700 CAD/month';
    $plan_description = 'Best for stores with up to 10,000 products';
} else {
    $recommended_plan = 'Knowledge Graph Enterprise';
    $plan_price = '$700 CAD/month + $140/month per 10K additional';
    $plan_description = 'Custom pricing for large catalogs';
}
?>

<div class="alloia-pro-section">
    <?php if (!class_exists('WooCommerce')): ?>
        <div class="notice notice-warning" style="margin-bottom: 20px;">
            <p><strong>⚠️ WooCommerce Required:</strong> The Pro Features require WooCommerce to manage your products and provide AI optimization capabilities. 
            <a href="<?php echo esc_url(admin_url('plugin-install.php?s=woocommerce&tab=search&type=term')); ?>" class="button button-primary" style="margin-left: 10px;">Install WooCommerce</a></p>
        </div>
    <?php endif; ?>
    
    <div class="alloia-card alloia-pro-header">
        <h2>Advanced e-commerce AI capabilities for serious merchants</h2>
        <p>Get detailed analytics, AI ready graph and optimisation + Always priority support.</p>
    </div>


    <!-- API Key Management - Always visible -->
    <div class="alloia-card alloia-setting-group" style="margin-top:10px;">
        <div class="alloia-setting-info">
            <h3><?php esc_html_e('API Key Management', 'geo-ia-optimisation-alloia'); ?></h3>
            <p><?php esc_html_e('Enter your AlloIA API key to enable product synchronization.', 'geo-ia-optimisation-alloia'); ?></p>
            <?php 
            $api_key = get_option('alloia_api_key_encrypted', get_option('alloia_api_key', ''));
            $api_key_valid = false;
            if (!empty($api_key) && isset($data['api_key_validation_status'])) {
                $api_key_valid = $data['api_key_validation_status'];
            }
            ?>
            <form method="post" action="" style="margin-top:10px;">
                <?php wp_nonce_field('alloia_license_activation', 'alloia_license_nonce'); ?>
                <div style="position: relative; display: inline-block; width: 100%; max-width: 500px;">
                    <input type="text" 
                           name="license_key" 
                           id="alloia_api_key_input"
                           value="<?php echo esc_attr($api_key); ?>" 
                           class="regular-text" 
                           style="width: 100%; padding-right: 40px;" 
                           placeholder="<?php esc_attr_e('Enter your API key...', 'geo-ia-optimisation-alloia'); ?>" />
                    <span id="api_key_status" class="api-key-status" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); pointer-events: none;">
                        <?php if ($api_key_valid): ?>
                            <span class="dashicons dashicons-yes-alt" style="color: #46b450; font-size: 20px;"></span>
                        <?php elseif (!empty($api_key)): ?>
                            <span class="dashicons dashicons-dismiss" style="color: #dc3232; font-size: 20px;"></span>
                        <?php endif; ?>
                    </span>
                </div>
                <div style="margin-top: 10px;">
                    <input type="submit" name="activate_license" value="<?php esc_attr_e('Save API Key', 'geo-ia-optimisation-alloia'); ?>" class="button button-primary" />
                </div>
            </form>
        </div>
    </div>

    <!-- Product Sync Section -->
    <?php
    $domain_validation = isset($data['domain_validation']) ? $data['domain_validation'] : null;
    $has_access = isset($data['has_access']) ? $data['has_access'] : false;
    $checks = $domain_validation['checks'] ?? array();
    $current_domain = $domain_validation['domain'] ?? wp_parse_url(home_url(), PHP_URL_HOST);
    $export_stats = isset($data['export_stats']) ? $data['export_stats'] : array('total_exported' => 0, 'total_failed' => 0, 'last_export' => null);
    
    // Get total products count
    $total_products = 0;
    if (class_exists('WooCommerce')) {
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids'
        );
        $products = get_posts($args);
        $total_products = count($products);
    }
    ?>
    <div class="alloia-card alloia-setting-group" style="margin-top:20px;">
        <h3><?php esc_html_e('Product Sync', 'geo-ia-optimisation-alloia'); ?></h3>
        
        <?php if (!$has_access): ?>
            <div class="notice notice-warning" style="margin: 15px 0;">
                <p><strong><?php esc_html_e('Domain Validation Required', 'geo-ia-optimisation-alloia'); ?></strong></p>
                <p><?php esc_html_e('To sync products, your domain must be validated. Please complete the following steps:', 'geo-ia-optimisation-alloia'); ?></p>
                
                <ul style="margin-left: 20px; margin-top: 10px;">
                    <li style="<?php echo ($checks['api_key_valid'] ?? false) ? 'color: green;' : 'color: red;'; ?>">
                        <?php if ($checks['api_key_valid'] ?? false): ?>
                            ✓ <?php esc_html_e('API Key is valid', 'geo-ia-optimisation-alloia'); ?>
                        <?php else: ?>
                            ✗ <?php esc_html_e('API Key is invalid or missing', 'geo-ia-optimisation-alloia'); ?>
                        <?php endif; ?>
                    </li>
                    <li style="<?php echo ($checks['domain_associated'] ?? false) ? 'color: green;' : 'color: red;'; ?>">
                        <?php if ($checks['domain_associated'] ?? false): ?>
                            ✓ <?php printf(esc_html__('Domain "%s" is associated with your account', 'geo-ia-optimisation-alloia'), esc_html($current_domain)); ?>
                        <?php else: ?>
                            ✗ <?php printf(esc_html__('Domain "%s" is not associated with your account', 'geo-ia-optimisation-alloia'), esc_html($current_domain)); ?>
                        <?php endif; ?>
                    </li>
                    <li style="<?php echo ($checks['domain_validated'] ?? false) ? 'color: green;' : 'color: red;'; ?>">
                        <?php if ($checks['domain_validated'] ?? false): ?>
                            ✓ <?php esc_html_e('Domain is validated', 'geo-ia-optimisation-alloia'); ?>
                        <?php else: ?>
                            ✗ <?php esc_html_e('Domain validation pending', 'geo-ia-optimisation-alloia'); ?>
                        <?php endif; ?>
                    </li>
                </ul>
                
                <?php if (!($checks['api_key_valid'] ?? false)): ?>
                    <p style="margin-top: 15px;">
                        <span style="color: #666;">
                            <?php esc_html_e('Please configure your API key above.', 'geo-ia-optimisation-alloia'); ?>
                        </span>
                    </p>
                <?php elseif (!($checks['domain_associated'] ?? false) || !($checks['domain_validated'] ?? false)): ?>
                    <p style="margin-top: 15px;">
                        <a href="https://alloia.ai/dashboard/domains" target="_blank" class="button button-primary">
                            <?php esc_html_e('Manage Domains in AlloIA Dashboard', 'geo-ia-optimisation-alloia'); ?>
                        </a>
                        <span style="margin-left: 10px; color: #666;">
                            <?php esc_html_e('Add and validate your domain to enable product sync', 'geo-ia-optimisation-alloia'); ?>
                        </span>
                    </p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div style="background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; padding: 12px 16px; margin: 15px 0; display: inline-block;">
                <span style="font-size: 16px; color: #646970;">
                    <strong style="color: #0073aa;"><?php echo esc_html($export_stats['total_exported']); ?> products synced</strong> / <?php echo esc_html($total_products); ?> products in store
                </span>
            </div>
            
            <div style="margin-top: 15px;">
                <button type="button" id="sync_all_products" class="button button-primary">
                    <?php esc_html_e('Sync Products', 'geo-ia-optimisation-alloia'); ?>
                </button>
                <span id="sync_status" style="margin-left: 10px;"></span>
            </div>
            
            <?php if ($export_stats['last_export']): ?>
                <p style="margin-top: 10px; color: #646970; font-size: 14px;">
                    <?php printf(esc_html__('Last sync: %s', 'geo-ia-optimisation-alloia'), esc_html($export_stats['last_export'])); ?>
                </p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<style>
.alloia-settings-form {
    margin-top: 20px;
}

.alloia-features-status .feature-item {
    display: flex;
    align-items: flex-start;
    margin-bottom: 15px;
    padding: 10px 0;
    border-bottom: 1px solid #f0f0f1;
}

.alloia-features-status .feature-item:last-child {
    border-bottom: none;
}

.alloia-features-status .feature-item .dashicons {
    margin-right: 10px;
    margin-top: 2px;
    flex-shrink: 0;
}

.alloia-features-status .feature-item strong {
    display: block;
    margin-bottom: 5px;
}

.alloia-features-status .feature-item p {
    margin: 0;
    color: #646970;
    font-size: 14px;
}

.alloia-card {
    margin-bottom: 25px;
}

.alloia-card h3 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.alloia-card .description {
    color: #646970;
    font-style: italic;
    margin-bottom: 20px;
}

.notice.inline {
    display: inline-block;
    margin: 0;
    padding: 5px 10px;
    font-size: 14px;
}

.notice.inline p {
    margin: 0;
}
</style>

<!-- Subscription Modal -->
<div id="subscription-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2><?php esc_html_e('Subscribe to AlloIA', 'geo-ia-optimisation-alloia'); ?></h2>
        
        <form method="post" id="subscription-form">
            <?php wp_nonce_field('alloia_subscription', 'alloia_subscription_nonce'); ?>
            <input type="hidden" name="plan_id" id="selected_plan_id">
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="customer_email"><?php esc_html_e('Email Address', 'geo-ia-optimisation-alloia'); ?></label>
                    </th>
                    <td>
                        <input type="email" name="customer_email" id="customer_email" class="regular-text" required>
                        <p class="description"><?php esc_html_e('We\'ll use this email for your AlloIA account and billing communications.', 'geo-ia-optimisation-alloia'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="company_name"><?php esc_html_e('Company Name', 'geo-ia-optimisation-alloia'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="company_name" id="company_name" class="regular-text">
                        <p class="description"><?php esc_html_e('Optional: Your company or organization name.', 'geo-ia-optimisation-alloia'); ?></p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="alloia_subscribe" class="button button-primary" value="<?php esc_attr_e('Proceed to Checkout', 'geo-ia-optimisation-alloia'); ?>">
            </p>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Subscribe buttons are now direct links, no JavaScript handling needed
    
    // Close modal
    $('.close').on('click', function() {
        $('#subscription-modal').hide();
    });
    
    // Close modal when clicking outside
    $(window).on('click', function(e) {
        if ($(e.target).is('#subscription-modal')) {
            $('#subscription-modal').hide();
        }
    });
    
    // Handle subscription form submission
    $('#subscription-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $submitButton = $form.find('input[type="submit"]');
        var originalText = $submitButton.val();
        
        // Disable submit button and show loading
        $submitButton.prop('disabled', true).val('Redirecting...');
        
        // Get form data
        var formData = new FormData(this);
        formData.append('action', 'alloia_create_checkout_session');
        formData.append('alloia_subscription_nonce', '<?php echo esc_attr(wp_create_nonce("alloia_subscription")); ?>');
        
        // Submit form via AJAX
        $.ajax({
            url: '<?php echo esc_url(admin_url("admin-ajax.php")); ?>',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    // Show success message
                    $('<div class="notice notice-success is-dismissible"><p>' + response.data.message + '</p></div>')
                        .insertBefore($form);
                    
                    // Redirect to AlloIA.ai billing page
                    setTimeout(function() {
                        window.location.href = response.data.redirect_url;
                    }, 2000);
                } else {
                    // Show error message
                    $('<div class="notice notice-error is-dismissible"><p>Error: ' + response.data + '</p></div>')
                        .insertBefore($form);
                }
            },
            error: function() {
                // Show generic error message
                $('<div class="notice notice-error is-dismissible"><p>An error occurred. Please try again.</p></div>')
                    .insertBefore($form);
            },
            complete: function() {
                // Re-enable submit button
                $submitButton.prop('disabled', false).val(originalText);
            }
        });
    });
    
    // Partner Code functionality
    var partnerCode = '';
    
    // Handle Apply Code button for non-pro users
    $('#apply_partner_code').on('click', function() {
        var code = $('#partner_code').val().trim();
        if (code) {
            partnerCode = code;
            $('#partner_code_status').html('<div class="notice notice-success inline"><p><strong>Partner code applied:</strong> ' + code + '</p></div>');
            updateSubscriptionLinks();
        } else {
            $('#partner_code_status').html('<div class="notice notice-error inline"><p>Please enter a partner code.</p></div>');
        }
    });
    
    // Handle Apply Code button for pro users
    $('#apply_partner_code_pro').on('click', function() {
        var code = $('#partner_code_pro').val().trim();
        if (code) {
            partnerCode = code;
            $('#partner_code_status_pro').html('<div class="notice notice-success inline"><p><strong>Partner code applied:</strong> ' + code + '</p></div>');
            // For pro users, we could save this to database or show confirmation
        } else {
            $('#partner_code_status_pro').html('<div class="notice notice-error inline"><p>Please enter a partner code.</p></div>');
        }
    });
    
    // Function to update subscription links with partner code
    function updateSubscriptionLinks() {
        $('a[href*="alloia.ai/dashboard/billing"]').each(function() {
            var href = $(this).attr('href');
            var url = new URL(href);
            
            if (partnerCode) {
                url.searchParams.set('ref', partnerCode);
            } else {
                url.searchParams.delete('ref');
            }
            
            $(this).attr('href', url.toString());
        });
    }
    
    // API Key Validation
    var validationTimeout;
    $('#alloia_api_key_input').on('input', function() {
        var $input = $(this);
        var $status = $('#api_key_status');
        var apiKey = $input.val().trim();
        
        // Clear previous timeout
        clearTimeout(validationTimeout);
        
        // Remove status icon if input is empty
        if (apiKey.length === 0) {
            $status.html('');
            return;
        }
        
        // Wait 500ms after user stops typing before validating
        validationTimeout = setTimeout(function() {
            // Show loading indicator
            $status.html('<span class="dashicons dashicons-update" style="color: #666; font-size: 20px; animation: spin 1s linear infinite;"></span>');
            
            $.ajax({
                url: '<?php echo esc_url(admin_url("admin-ajax.php")); ?>',
                type: 'POST',
                data: {
                    action: 'alloia_validate_api_key',
                    api_key: apiKey,
                    nonce: '<?php echo esc_attr(wp_create_nonce("alloia_kg_nonce")); ?>'
                },
                success: function(response) {
                    if (response.success && response.data.valid) {
                        $status.html('<span class="dashicons dashicons-yes-alt" style="color: #46b450; font-size: 20px;"></span>');
                    } else {
                        $status.html('<span class="dashicons dashicons-dismiss" style="color: #dc3232; font-size: 20px;"></span>');
                    }
                },
                error: function() {
                    $status.html('<span class="dashicons dashicons-dismiss" style="color: #dc3232; font-size: 20px;"></span>');
                }
            });
        }, 500);
    });
    
    // Sync Products Button - HOTFIX DEBUG VERSION
    $('#sync_all_products').on('click', function() {
        var $button = $(this);
        var $status = $('#sync_status');
        var originalText = $button.text();
        
        // Disable button and show loading
        $button.prop('disabled', true).text('<?php esc_html_e('Syncing...', 'geo-ia-optimisation-alloia'); ?>');
        $status.html('<span style="color: #666;"><?php esc_html_e('Starting sync...', 'geo-ia-optimisation-alloia'); ?></span>');
        
        $.ajax({
            url: '<?php echo esc_url(admin_url("admin-ajax.php")); ?>',
            type: 'POST',
            data: {
                action: 'alloia_sync_all_products',
                nonce: '<?php echo esc_attr(wp_create_nonce("alloia_kg_nonce")); ?>'
            },
            success: function(response) {
                console.log('SYNC RESPONSE:', response);
                
                if (response.success) {
                    // Build detailed status message
                    var statusHtml = '<div style="background: #f0f8ff; border: 1px solid #0073aa; border-radius: 4px; padding: 15px; margin-top: 10px;">';
                    statusHtml += '<strong style="color: #46b450;">✓ ' + (response.data.message || 'Sync completed!') + '</strong><br>';
                    statusHtml += '<strong>Total Products:</strong> ' + (response.data.total_products || 0) + '<br>';
                    statusHtml += '<strong>Export ID:</strong> ' + (response.data.export_id || 'N/A') + '<br>';
                    statusHtml += '<strong>Status:</strong> ' + (response.data.success ? 'SUCCESS' : 'UNKNOWN') + '<br>';
                    
                    if (response.data.detailed_message) {
                        statusHtml += '<strong>Details:</strong> ' + response.data.detailed_message + '<br>';
                    }
                    
                    if (response.data.debug) {
                        statusHtml += '<br><strong>Debug Log:</strong><br>';
                        statusHtml += '<pre style="background: #fff; padding: 10px; overflow: auto; max-height: 300px; font-size: 11px;">';
                        response.data.debug.forEach(function(line) {
                            statusHtml += line + '\n';
                        });
                        statusHtml += '</pre>';
                    }
                    
                    if (response.data.full_result) {
                        statusHtml += '<br><strong>Full Result:</strong><br>';
                        statusHtml += '<pre style="background: #fff; padding: 10px; overflow: auto; max-height: 200px; font-size: 11px;">';
                        statusHtml += JSON.stringify(response.data.full_result, null, 2);
                        statusHtml += '</pre>';
                    }
                    
                    if (response.data.updated_stats) {
                        statusHtml += '<br><strong>📊 Updated Statistics:</strong><br>';
                        statusHtml += '<pre style="background: #f0fff0; padding: 10px; overflow: auto; font-size: 11px; border: 2px solid #46b450;">';
                        statusHtml += JSON.stringify(response.data.updated_stats, null, 2);
                        statusHtml += '</pre>';
                    }
                    
                    statusHtml += '</div>';
                    $status.html(statusHtml);
                    $button.prop('disabled', false).text(originalText);
                    
                    // Reload page after 3 seconds to show updated stats
                    if (response.data.exported_count > 0) {
                        setTimeout(function() {
                            window.location.reload();
                        }, 3000);
                    }
                } else {
                    // Build error message
                    var errorHtml = '<div style="background: #fff8e5; border: 1px solid #dc3232; border-radius: 4px; padding: 15px; margin-top: 10px;">';
                    errorHtml += '<strong style="color: #dc3232;">✗ ' + (response.data.message || '<?php esc_html_e('Sync failed', 'geo-ia-optimisation-alloia'); ?>') + '</strong><br>';
                    
                    if (response.data.debug) {
                        errorHtml += '<br><strong>Debug Log:</strong><br>';
                        errorHtml += '<pre style="background: #fff; padding: 10px; overflow: auto; max-height: 300px; font-size: 11px;">';
                        response.data.debug.forEach(function(line) {
                            errorHtml += line + '\n';
                        });
                        errorHtml += '</pre>';
                    }
                    
                    if (response.data.validation) {
                        errorHtml += '<br><strong>Validation:</strong><br>';
                        errorHtml += '<pre style="background: #fff; padding: 10px; overflow: auto; font-size: 11px;">';
                        errorHtml += JSON.stringify(response.data.validation, null, 2);
                        errorHtml += '</pre>';
                    }
                    
                    errorHtml += '</div>';
                    $status.html(errorHtml);
                    $button.prop('disabled', false).text(originalText);
                }
            },
            error: function(xhr, status, error) {
                console.log('SYNC ERROR:', xhr, status, error);
                var errorHtml = '<div style="background: #fff8e5; border: 1px solid #dc3232; border-radius: 4px; padding: 15px; margin-top: 10px;">';
                errorHtml += '<strong style="color: #dc3232;">✗ AJAX Error</strong><br>';
                errorHtml += 'Status: ' + status + '<br>';
                errorHtml += 'Error: ' + error + '<br>';
                errorHtml += 'Response: ' + xhr.responseText;
                errorHtml += '</div>';
                $status.html(errorHtml);
                $button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Add CSS for spinning animation
    if (!$('#alloia-spin-style').length) {
        $('<style id="alloia-spin-style">@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }</style>').appendTo('head');
    }

});
</script>