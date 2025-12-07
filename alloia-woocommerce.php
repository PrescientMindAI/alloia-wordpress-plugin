<?php
/**
 * Plugin Name: GEO AI optimisation par AlloIA
 * Plugin URI: https://alloia.ai/plugins/woocommerce
 * Description: GEO AI optimisation pour WooCommerce. Gérez les permissions IA, optimisez pour les moteurs de recherche IA et débloquez des analytics avancés avec AlloIA.
 * Version: 1.7.1
 * Author: AlloIA Team
 * Author URI: https://alloia.ai
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: alloia-woocommerce
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 * WooCommerce HPOS Compatible: true
 * 
 * @package AlloIA_WooCommerce
 * @version 1.0.1
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}


// Define plugin constants
define('ALLOIA_VERSION', '1.7.1');
define('ALLOIA_PLUGIN_FILE', __FILE__);
define('ALLOIA_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('ALLOIA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ALLOIA_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Minimum requirements
define('ALLOIA_MIN_WP_VERSION', '5.0');
define('ALLOIA_MIN_PHP_VERSION', '7.4');
define('ALLOIA_MIN_WC_VERSION', '5.0');

/**
 * Main AlloIA WooCommerce Plugin Class
 * 
 * @since 1.0.0
 */
class AlloIA_WooCommerce {
    
    /**
     * Plugin version
     * @var string
     */
    public $version = ALLOIA_VERSION;
    
    /**
     * Core instance
     * @var AlloIA_Core
     */
    public $core;
    
    /**
     * Admin instance
     * @var AlloIA_Admin
     */
    public $admin;
    
    /**
     * Plugin instance
     * @var AlloIA_WooCommerce
     */
    private static $instance = null;
    
    /**
     * Get plugin instance (singleton pattern)
     * 
     * @return AlloIA_WooCommerce
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Check requirements before initializing
        if (!$this->check_requirements()) {
            return;
        }
        
        $this->init_hooks();
        $this->load_dependencies();
        $this->init_components();
        
        // Use a delayed check to ensure WordPress is fully loaded
        add_action('admin_init', array($this, 'check_woocommerce_notice'));
        
        // Also check after plugins are loaded as a fallback
        add_action('plugins_loaded', array($this, 'check_woocommerce_notice'), 20);
    }
    
    /**
     * Check if plugin requirements are met
     * 
     * @return bool
     */
    private function check_requirements() {
        // Check WordPress version
        if (version_compare(get_bloginfo('version'), ALLOIA_MIN_WP_VERSION, '<')) {
            add_action('admin_notices', array($this, 'wordpress_version_notice'));
            return false;
        }
        
        // Check PHP version
        if (version_compare(PHP_VERSION, ALLOIA_MIN_PHP_VERSION, '<')) {
            add_action('admin_notices', array($this, 'php_version_notice'));
            return false;
        }
        
        // WooCommerce check is now handled in the constructor after full initialization
        // to ensure proper detection timing
        
        return true;
    }
    
    /**
     * Show WordPress version notice
     */
    public function wordpress_version_notice() {
        $message = sprintf(
            /* translators: 1: Plugin name 2: Required WordPress version */
            esc_html__('%1$s requires WordPress %2$s or higher. Please upgrade WordPress to use this plugin.', 'alloia-woocommerce'),
            '<strong>AI GEO optimisation by AlloIA</strong>',
            ALLOIA_MIN_WP_VERSION
        );
        
        printf('<div class="notice notice-error"><p>%s</p></div>', wp_kses_post($message));
    }
    
    /**
     * Show PHP version notice
     */
    public function php_version_notice() {
        $message = sprintf(
            /* translators: 1: Plugin name 2: Required PHP version */
            esc_html__('%1$s requires PHP %2$s or higher. Please contact your hosting provider to upgrade PHP.', 'alloia-woocommerce'),
            '<strong>AI GEO optimisation by AlloIA</strong>',
            ALLOIA_MIN_PHP_VERSION
        );
        
        printf('<div class="notice notice-error"><p>%s</p></div>', wp_kses_post($message));
    }
    
    /**
     * Show admin notice when WooCommerce is missing (for Pro features only)
     */
    public function woocommerce_missing_notice() {
        $message = sprintf(
            /* translators: 1: Plugin name 2: WooCommerce */
            esc_html__('%1$s Pro features (Knowledge Graph & IA Optimisation) require %2$s for product import and optimization. Dashboards work without WooCommerce.', 'alloia-woocommerce'),
            '<strong>AI GEO optimisation by AlloIA</strong>',
            '<strong>WooCommerce</strong>'
        );
        
        printf('<div class="notice notice-info is-dismissible"><p>%s <a href="%s">%s</a></p></div>', 
            wp_kses_post($message),
            esc_url(admin_url('plugin-install.php?s=woocommerce&tab=search&type=term')),
            esc_html__('Install WooCommerce', 'alloia-woocommerce')
        );
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Load text domain for internationalization
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Add settings link to plugins page
        add_filter('plugin_action_links_' . ALLOIA_PLUGIN_BASENAME, array($this, 'plugin_action_links'));
        
        // Add meta links to plugins page
        add_filter('plugin_row_meta', array($this, 'plugin_row_meta'), 10, 2);
        
        // Declare HPOS compatibility
        add_action('before_woocommerce_init', array($this, 'declare_hpos_compatibility'));
    }
    
    /**
     * Load plugin textdomain
     * Note: WordPress.org automatically handles translations for hosted plugins
     */
    public function load_textdomain() {
        // Removed load_plugin_textdomain() as it's discouraged since WP 4.6
        // WordPress.org automatically loads translations for hosted plugins
    }
    
    /**
     * Add action links to plugins page
     * 
     * @param array $links
     * @return array
     */
    public function plugin_action_links($links) {
        $plugin_links = array(
            '<a href="' . admin_url('admin.php?page=alloia-settings') . '">' . __('Settings', 'alloia-woocommerce') . '</a>',
        );
        
        return array_merge($plugin_links, $links);
    }
    
    /**
     * Add meta links to plugins page
     * 
     * @param array $links
     * @param string $file
     * @return array
     */
    public function plugin_row_meta($links, $file) {
        if (ALLOIA_PLUGIN_BASENAME === $file) {
            $row_meta = array(
                'docs' => sprintf(
                    '<a href="%s" aria-label="%s">%s</a>',
                    esc_url('https://alloia.ai/docs'),
                    esc_attr__('View AI GEO optimisation by AlloIA documentation', 'alloia-woocommerce'),
                    esc_html__('Documentation', 'alloia-woocommerce')
                ),
                'support' => sprintf(
                    '<a href="%s" aria-label="%s">%s</a>',
                    esc_url('https://alloia.ai/support'),
                    esc_attr__('Visit AI GEO optimisation by AlloIA support', 'alloia-woocommerce'),
                    esc_html__('Support', 'alloia-woocommerce')
                ),
            );
            
            return array_merge($links, $row_meta);
        }
        
        return $links;
    }
    
    /**
     * Load required class files
     */
    private function load_dependencies() {
        try {
            // Core functionality
            require_once ALLOIA_PLUGIN_PATH . 'includes/class-alloia-core.php';
            
            // Unified API integration
            require_once ALLOIA_PLUGIN_PATH . 'includes/class-alloia-api.php';
            require_once ALLOIA_PLUGIN_PATH . 'includes/class-alloia-knowledge-graph.php';
            
            // Admin interface (only in admin)
            if (is_admin()) {
                require_once ALLOIA_PLUGIN_PATH . 'includes/class-alloia-admin.php';
                
                // GitHub auto-update system (only for GitHub installations, not WordPress.org)
                // WordPress.org has its own update system
                $updater_file = ALLOIA_PLUGIN_PATH . 'includes/class-alloia-updater.php';
                if (file_exists($updater_file)) {
                    require_once $updater_file;
                    new AlloIA_Plugin_Updater(
                        plugin_basename(__FILE__), // 'alloia-woocommerce/alloia-woocommerce.php'
                        'PrescientMindAI/alloia-wordpress-plugin', // GitHub repo
                        ALLOIA_VERSION // Current version
                    );
                }
            }
            
            // Website API functionality is now integrated into the main API class
        } catch (Exception $e) {
            // Log error but don't crash the site
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AlloIA Plugin: Failed to load dependencies: ' . $e->getMessage());
            }
            return;
        }
    }
    
    /**
     * Check if Website API client should be loaded (for website registration and tracking)
     */
    private function should_load_website_api() {
        // Only load Website API if:
        // 1. User has API configuration
        // 2. User is on admin page that might need tracking features
        // 3. Site registration or tracking provisioning is needed
        // 4. Has pro subscription features enabled
        
        $has_api_config = !empty(get_option('alloia_api_base_url', '')) || !empty(get_option('alloia_api_key', ''));
        $is_admin_page = is_admin() && isset($_GET['page']) && $_GET['page'] === 'alloia-settings';
        $needs_registration = empty(get_option('alloia_tracking_website_id', ''));
        $has_pro_features = !empty(get_option('alloia_api_key', ''));
        
        return $has_api_config || ($is_admin_page && $needs_registration) || $has_pro_features;
    }
    
    /**
     * Initialize plugin components
     */
    private function init_components() {
        try {
            // Initialize core functionality
            $this->core = new AlloIA_Core();
            
            // Initialize admin interface (only in admin)
            if (is_admin()) {
                $this->admin = new AlloIA_Admin($this->core);
            }
        } catch (Exception $e) {
            // Log error but don't crash the site
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AlloIA Plugin: Failed to initialize components: ' . $e->getMessage());
            }
            return;
        }
    }
    
    /**
     * Get core instance
     */
    public function get_core() {
        return $this->core;
    }
    
    /**
     * Get admin instance
     */
    public function get_admin() {
        return $this->admin;
    }
    
    /**
     * Declare HPOS compatibility
     */
    public function declare_hpos_compatibility() {
        if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        }
    }
    
    /**
     * Check if WooCommerce is active and properly loaded
     * 
     * @return bool
     */
    private function is_woocommerce_active() {
        // Add debugging for development
        if (defined('WP_DEBUG') && WP_DEBUG) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AlloIA: Checking WooCommerce status...');
            }
        }
        
        // Multiple methods to check if WooCommerce is active
        if (class_exists('WooCommerce')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('AlloIA: WooCommerce class exists - returning true');
                }
            }
            return true;
        }
        
        // Check if WooCommerce constants are defined
        if (defined('WC_PLUGIN_FILE') || defined('WC_VERSION')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('AlloIA: WooCommerce constants defined - returning true');
                }
            }
            return true;
        }
        
        // Check if WooCommerce functions exist
        if (function_exists('wc_get_order') || function_exists('wc_get_product')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('AlloIA: WooCommerce functions exist - returning true');
                }
            }
            return true;
        }
        
        // Check if WooCommerce plugin is active
        if (function_exists('is_plugin_active')) {
            $is_active = is_plugin_active('woocommerce/woocommerce.php');
            if (defined('WP_DEBUG') && WP_DEBUG) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('AlloIA: WooCommerce plugin active check: ' . ($is_active ? 'true' : 'false'));
                }
            }
            if ($is_active) {
                return true;
            }
        }
        
        // Check if WooCommerce is in active plugins list
        $active_plugins = get_option('active_plugins', array());
        foreach ($active_plugins as $plugin) {
            if (strpos($plugin, 'woocommerce') !== false) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('AlloIA: WooCommerce found in active plugins: ' . $plugin);
                }
                return true;
            }
        }
        
        // Check network plugins if multisite
        if (is_multisite()) {
            $network_plugins = get_site_option('active_sitewide_plugins', array());
            foreach ($network_plugins as $plugin => $time) {
                if (strpos($plugin, 'woocommerce') !== false) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('AlloIA: WooCommerce plugin found in network plugins: ' . $plugin);
                    }
                    return true;
                }
            }
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('AlloIA: WooCommerce not detected by any method');
        }
        
        return false;
    }
    
    /**
     * Check if we should show the WooCommerce missing notice
     * Only show on Pro features pages when user has a Pro license
     * 
     * @return bool
     */
    private function should_show_woocommerce_notice() {
        // Only show on admin pages
        if (!is_admin()) {
            return false;
        }
        
        // Only show if user can activate plugins
        if (!current_user_can('activate_plugins')) {
            return false;
        }
        
        // Only show if user has a Pro license
        $has_pro_license = !empty(get_option('alloia_api_key', ''));
        if (!$has_pro_license) {
            return false;
        }
        
        // Only show on Pro features pages
        $is_pro_tab = isset($_GET['page']) && $_GET['page'] === 'alloia-settings' && 
                      isset($_GET['tab']) && $_GET['tab'] === 'pro';
        
        return $is_pro_tab;
    }
    
    /**
     * Check and display WooCommerce missing notice if needed
     * This runs after WordPress is fully loaded to ensure proper detection
     */
    public function check_woocommerce_notice() {
        if (!$this->is_woocommerce_active() && $this->should_show_woocommerce_notice()) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
        }
    }
}

/**
 * Plugin activation hook
 */
function alloia_activate() {
    try {
        // Create default options
        add_option('alloia_version', ALLOIA_VERSION);
        add_option('alloia_llms_txt_enabled', true);
        add_option('alloia_ai_bot_tracking', false);
        add_option('alloia_robots_txt_enabled', true);
        add_option('alloia_export_batch_size', 50);
        
        // Set default AI training permission
        add_option('alloia_llm_training', 'allow');
        
        // Create necessary database tables if needed
        alloia_create_tables();
        
        // Delete old static files (plugin now serves these dynamically)
        $abspath = rtrim(ABSPATH, '/');
        $old_llms_txt = $abspath . '/llms.txt';
        $old_robots_txt = $abspath . '/robots.txt';
        
        if (file_exists($old_llms_txt)) {
            wp_delete_file($old_llms_txt);
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AlloIA Plugin: Deleted old static llms.txt file');
            }
        }
        
        // Note: We don't delete robots.txt automatically as it may be user-customized
        // Users should use the "Generate robots.txt" button instead
        
        // Flush rewrite rules to activate dynamic serving
        flush_rewrite_rules();
        
        // Set activation flag
        add_option('alloia_activated', true);
    } catch (Exception $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('AlloIA Plugin: Activation failed: ' . $e->getMessage());
        }
    }
}

/**
 * Plugin deactivation hook
 */
function alloia_deactivate() {
    try {
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Clear any scheduled events
        wp_clear_scheduled_hook('alloia_hourly_audit');
        
        // Set deactivation flag
        add_option('alloia_deactivated', true);
    } catch (Exception $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('AlloIA Plugin: Deactivation failed: ' . $e->getMessage());
        }
    }
}

/**
 * Create necessary database tables
 */
function alloia_create_tables() {
    global $wpdb;
    
    try {
        $charset_collate = $wpdb->get_charset_collate();
        
        // Export history table
        $table_name = $wpdb->prefix . 'alloia_exports';
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            export_id varchar(50) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            filters longtext,
            total_products int(11) DEFAULT 0,
            exported_products int(11) DEFAULT 0,
            failed_products int(11) DEFAULT 0,
            started_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime NULL,
            error_message text,
            PRIMARY KEY (id),
            UNIQUE KEY export_id (export_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    } catch (Exception $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('AlloIA Plugin: Failed to create tables: ' . $e->getMessage());
        }
    }
}

/**
 * Initialize the plugin
 */
function alloia_init() {
    try {
        return AlloIA_WooCommerce::get_instance();
    } catch (Exception $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('AlloIA Plugin: Failed to initialize: ' . $e->getMessage());
        }
        return null;
    }
}

// Register activation and deactivation hooks
register_activation_hook(__FILE__, 'alloia_activate');
register_deactivation_hook(__FILE__, 'alloia_deactivate');

// Initialize the plugin safely
add_action('plugins_loaded', 'alloia_init', 10);

// Global variable for backward compatibility (only set after successful initialization)
add_action('plugins_loaded', function() {
    global $alloia_plugin;
    $alloia_plugin = alloia_init();
}, 20); 
