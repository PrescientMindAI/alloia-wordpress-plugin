<?php
/**
 * Uninstall script for AI GEO optimisation by AlloIA
 * 
 * This file is executed when the plugin is deleted from WordPress.
 * It removes all plugin data, options, and database tables.
 * 
 * @package AlloIA_WooCommerce
 */

// Exit if accessed directly
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Exit if WordPress constants are not available
if (!defined('ABSPATH') || !defined('WPINC')) {
    exit;
}

// Check if user has permission to uninstall
if (!current_user_can('activate_plugins')) {
    return;
}

// Global WordPress database object
global $wpdb;

// Get plugin path to identify our plugin
$plugin_path = plugin_dir_path(__FILE__);
$plugin_basename = plugin_basename($plugin_path);

// Only proceed if this is our plugin being uninstalled
if (strpos($plugin_basename, 'alloia') === false) {
    return;
}

// Remove all plugin options
$options_to_remove = array(
    'alloia_version',
    'alloia_api_key',
    'alloia_api_key_encrypted',
    'alloia_subdomain',
    'alloia_llms_txt_enabled',
    'alloia_ai_bot_tracking',
    'alloia_robots_txt_enabled',
    'alloia_advanced_analytics',
    'alloia_api_integration',
    'alloia_smart_recommendations',
    'alloia_premium_support',
    'alloia_llm_training',
    'alloia_export_batch_size',
    'alloia_export_total_exported',
    'alloia_export_total_failed',
    'alloia_export_last_export',
    'alloia_activated',
    'alloia_deactivated',
    'alloia_checkout_session',
    'alloia_last_audit',
    'alloia_ai_ready_score',
    'alloia_last_robots_audit',
    'alloia_optimisation_products',
    'alloia_tracking_code',
    'alloia_bot_visits',
    'alloia_recent_bots',
    'alloia_license_expiry',
    'alloia_api_base_url',
    'alloia_tracking_website_id',
    'alloia_tracking_api_key',
    'alloia_client_info',
    'ai_server_type',
    'ai_redirect_enabled'
);

foreach ($options_to_remove as $option) {
    delete_option($option);
}


// Remove user meta data (with error handling)
try {
    if ($wpdb && method_exists($wpdb, 'prepare') && method_exists($wpdb, 'query')) {
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
                'alloia_%'
            )
        );

        // Remove post meta data
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
                'alloia_%'
            )
        );

        // Remove term meta data
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->termmeta} WHERE meta_key LIKE %s",
                'alloia_%'
            )
        );
    }
} catch (Exception $e) {
    // Log error but don't fail uninstall
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('AlloIA Uninstall: Failed to remove meta data: ' . $e->getMessage());
    }
}

// Drop custom database tables (with error handling)
try {
    if ($wpdb && isset($wpdb->prefix)) {
        $tables_to_drop = array(
            $wpdb->prefix . 'alloia_exports'
        );

        foreach ($tables_to_drop as $table) {
            // Validate table name contains our prefix
            if (strpos($table, 'alloia') !== false) {
                $wpdb->query("DROP TABLE IF EXISTS `{$table}`");
            }
        }
    }
} catch (Exception $e) {
    // Log error but don't fail uninstall
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('AlloIA Uninstall: Failed to drop tables: ' . $e->getMessage());
    }
}

// Remove scheduled events
wp_clear_scheduled_hook('alloia_hourly_audit');
wp_clear_scheduled_hook('alloia_auto_sync_single_product');

// Remove physical files created by the plugin (with better error handling)
if (defined('ABSPATH') && is_readable(ABSPATH)) {
    $files_to_remove = array(
        ABSPATH . 'llms.txt',
        // Note: We should NOT remove robots.txt as it may contain other important rules
        // ABSPATH . 'robots.txt' // Only if it was created by our plugin
    );
    
    foreach ($files_to_remove as $file) {
        try {
            if (file_exists($file) && is_writable($file)) {
                // Check if the file contains our plugin's signature before removing
                $content = @file_get_contents($file);
                if ($content !== false && (strpos($content, 'AlloIA') !== false || strpos($content, 'alloia') !== false)) {
                    wp_delete_file($file);
                }
            }
        } catch (Exception $e) {
            // Log error but continue with uninstall
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AlloIA Uninstall: Failed to remove file ' . $file . ': ' . $e->getMessage());
            }
        }
    }
}

// Clear any cached data
if (function_exists('wp_cache_flush')) {
    wp_cache_flush();
}

// Clear object cache if available
if (function_exists('wp_cache_flush_group')) {
    wp_cache_flush_group('alloia');
}

// Log uninstallation for debugging (optional)
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('AI GEO optimisation by AlloIA plugin uninstalled successfully');
}
