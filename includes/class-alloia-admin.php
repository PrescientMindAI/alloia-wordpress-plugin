<?php
/**
 * Admin interface for AlloIA WooCommerce plugin
 * 
 * @package AlloIA_WooCommerce
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class AlloIA_Admin {
    
    private $core;
    private $api_client;
    private $knowledge_graph_exporter;
    
    public function __construct($core) {
        $this->core = $core;
        $this->init_hooks();
        $this->init_services();
    }
    
    /**
     * Initialize admin hooks
     */
    public function init_hooks() {
        // Admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Admin assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // AJAX handlers for knowledge graph export
        add_action('wp_ajax_alloia_export_products', array($this, 'ajax_export_products'));
        add_action('wp_ajax_alloia_get_export_status', array($this, 'ajax_get_export_status'));
        add_action('wp_ajax_alloia_update_batch_size', array($this, 'ajax_update_batch_size'));
        
        // Auto-sync hooks for product changes
        if (class_exists('WooCommerce')) {
            add_action('woocommerce_update_product', array($this, 'auto_sync_product'), 10, 1);
            add_action('woocommerce_new_product', array($this, 'auto_sync_product'), 10, 1);
            add_action('woocommerce_product_meta_updated', array($this, 'auto_sync_product'), 10, 1);
            add_action('alloia_auto_sync_single_product', array($this, 'process_auto_sync_product'), 10, 1);
        }
        
        // AJAX handler for sync all products
        add_action('wp_ajax_alloia_sync_all_products', array($this, 'ajax_sync_all_products'));
        
        // AJAX handler for API key validation
        add_action('wp_ajax_alloia_validate_api_key', array($this, 'ajax_validate_api_key'));
    }
    
    /**
     * Initialize service classes
     */
    private function init_services() {
        // Initialize API client if API key exists (check encrypted first, then regular)
        $api_key = get_option('alloia_api_key_encrypted', get_option('alloia_api_key', ''));
        if (!empty($api_key)) {
            $this->api_client = new AlloIA_API($api_key);
            $this->knowledge_graph_exporter = new AlloIA_Knowledge_Graph_Exporter($this->api_client);
        }
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our admin pages
        if (strpos($hook, 'alloia') === false) {
            return;
        }
        
        // Common admin styles
        wp_enqueue_style(
            'alloia-admin-css',
            ALLOIA_PLUGIN_URL . 'admin/assets/admin.css',
            array(),
            ALLOIA_VERSION
        );
        
        wp_enqueue_script(
            'alloia-admin-js',
            ALLOIA_PLUGIN_URL . 'admin/assets/admin.js',
            array('jquery'),
            ALLOIA_VERSION,
            true
        );
        
        // Knowledge Graph page specific assets
        if ($hook === 'alloia-settings_page_alloia-knowledge-graph') {
            wp_enqueue_script(
                'alloia-knowledge-graph-js',
                ALLOIA_PLUGIN_URL . 'admin/assets/js/knowledge-graph.js',
                array('jquery'),
                ALLOIA_VERSION,
                true
            );
            
            // Localize script for AJAX
            wp_localize_script('alloia-knowledge-graph-js', 'alloia_kg_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('alloia_kg_nonce'),
                'strings' => array(
                    'export_started' => __('Export started successfully!', 'geo-ia-optimisation-alloia'),
                    'export_failed' => __('Export failed. Please try again.', 'geo-ia-optimisation-alloia'),
                    'batch_size_updated' => __('Batch size updated successfully!', 'geo-ia-optimisation-alloia')
                )
            ));
        }
    }

    /**
     * Add AlloIA menu and dashboard to admin
     */
    public function add_admin_menu() {
        // Main AlloIA page
        add_menu_page(
            'AlloIA',
            'AlloIA',
            'manage_options',
            'alloia-settings',
            array($this, 'render_settings_page'),
            $this->get_menu_icon(),
            56
        );
        
        // No submenu pages - all content consolidated in main settings page tabs
    }

    /**
     * Get menu icon
     */
    private function get_menu_icon() {
        return 'data:image/svg+xml;base64,' . base64_encode(file_get_contents(ALLOIA_PLUGIN_PATH . 'admin/assets/alloia_svg.svg'));
    }

    /**
     * Render the AlloIA settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'geo-ia-optimisation-alloia'));
        }
        
        // Process form submissions
        $this->process_form_submissions();
        
        // Prepare data for templates
        $data = $this->prepare_template_data();
        
        // Render main template
        include ALLOIA_PLUGIN_PATH . 'admin/views/settings-page.php';
    }
    
    /**
     * Prepare data for template rendering
     */
    private function prepare_template_data() {
        // Get current settings
        $api_key = get_option('alloia_api_key', '');
        $subdomain = get_option('alloia_subdomain', '');
        $current_domain = wp_parse_url(home_url(), PHP_URL_HOST);
        $ai_subdomain = $subdomain ? $subdomain : 'ai.' . $current_domain;
        
        // Determine active tab - default to 'free-tools'
        $active_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'free-tools';
        
        // Map old tab names to new ones for backward compatibility
        if ($active_tab === 'free') {
            $active_tab = 'free-tools';
        } elseif ($active_tab === 'pro') {
            $active_tab = 'ai-commerce';
        }
        
        // Get store statistics
        $product_count = wp_count_posts('product');
        $product_count = $product_count ? $product_count->publish : 0;
        
        $category_count = wp_count_terms('product_cat');
        $category_count = is_wp_error($category_count) ? 0 : $category_count;
        
        // AI bot visit data (mock data - in real implementation from database)
        $ai_bot_visits = get_option('alloia_bot_visits', wp_rand(50, 500));
        $recent_bots = get_option('alloia_recent_bots', array(
            array('bot' => 'ChatGPT', 'time' => '2 minutes ago'),
            array('bot' => 'Claude', 'time' => '5 minutes ago'),
            array('bot' => 'Perplexity', 'time' => '12 minutes ago'),
            array('bot' => 'Googlebot', 'time' => '1 hour ago'),
        ));
        
        // Pro features data
        $is_pro = !empty($api_key);
        $license_expiry = get_option('alloia_license_expiry', wp_date('Y-m-d', strtotime('+1 year')));
        
        // Pro analytics data (mock data for demo)
        $ai_revenue = $is_pro ? '1,249' : '***';
        $revenue_change = $is_pro ? '23' : '**';
        $conversion_rate = $is_pro ? '3.2' : '*.*';
        $conversion_change = $is_pro ? '0.8' : '*.*';
        $recommendations_served = $is_pro ? '1,847' : '***';
        
        // Feature settings
        $is_enabled = true; // Deprecated master toggle removed from UI
        $llms_txt_enabled = get_option('alloia_llms_txt_enabled', true);
        $ai_bot_tracking = get_option('alloia_ai_bot_tracking', true);
        $robots_txt_enabled = get_option('alloia_robots_txt_enabled', true);
        
        // Pro feature settings
        $advanced_analytics = get_option('alloia_advanced_analytics', false);
        $api_integration = get_option('alloia_api_integration', false);
        $smart_recommendations = get_option('alloia_smart_recommendations', false);
        $premium_support = get_option('alloia_premium_support', false);
        
		// AlloIA tracking credentials
		$alloia_tracking_website_id = get_option('alloia_tracking_website_id', '');
		$alloia_tracking_api_key = get_option('alloia_tracking_api_key', '');
        
		// AI-ready score and audit
		$ai_ready_score = get_option('alloia_ai_ready_score', array());
		if (empty($ai_ready_score) && method_exists($this->core, 'get_ai_ready_score')) {
			$ai_ready_score = $this->core->get_ai_ready_score();
		}
		$robots_audit = get_option('alloia_last_robots_audit', array());
		if (empty($robots_audit) && method_exists($this->core, 'get_robots_audit')) {
			$robots_audit = $this->core->get_robots_audit();
		}

		// WooCommerce products preview for Pro optimisation - only load on Pro tab
		$current_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'free';
		$wc_products = array();
		
		// Only load product data if we're on pro tab and have pro access
		if ($current_tab === 'pro' && $is_pro && class_exists('WooCommerce')) {
			$wc_products = $this->get_cached_products_preview();
		}

		$optimisation_products = get_option('alloia_optimisation_products', array());
        
        // Prepare data for AI Commerce Platform tab
        $domain_validation = null;
        $has_access = false;
        $api_key_validation_status = false;
        
        // HOTFIX: Load actual export statistics from WordPress options
        $export_stats = array(
            'total_exported' => 0,
            'total_failed' => 0,
            'last_export' => null
        );
        if ($this->knowledge_graph_exporter) {
            $export_stats = $this->knowledge_graph_exporter->get_export_statistics();
        }
        
        // If on ai-commerce tab, prepare domain validation and sync data
        if ($active_tab === 'ai-commerce' || $active_tab === 'pro') {
            // Get API key validation status
            $api_key_to_check = get_option('alloia_api_key_encrypted', get_option('alloia_api_key', ''));
            
            // Ensure API client is initialized if API key exists
            if (!empty($api_key_to_check) && !$this->api_client) {
                $this->api_client = new AlloIA_API($api_key_to_check);
            }
            
            if (!empty($api_key_to_check) && $this->api_client) {
                try {
                    $validation_result = $this->api_client->validate_api_key();
                    // API already validates subscription status internally
                    $api_key_validation_status = ($validation_result['success'] ?? false) && ($validation_result['valid'] ?? false);
                } catch (Exception $e) {
                    error_log('AlloIA Admin: API Key validation failed: ' . $e->getMessage());
                    $api_key_validation_status = false;
                }
            }
            
            // Prepare domain validation data
            if ($this->api_client) {
                try {
                    $domain_validation = $this->api_client->validate_domain_for_sync();
                    
                    // Debug logging
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('AlloIA Admin: Domain validation result: ' . json_encode($domain_validation));
                    }
                    
                    $domain_validation['checks']['api_key_valid'] = $api_key_validation_status;
                    $has_access = $domain_validation['valid'];
                } catch (Exception $e) {
                    error_log('AlloIA Admin: Domain validation failed: ' . $e->getMessage());
                    error_log('AlloIA Admin: Exception trace: ' . $e->getTraceAsString());
                    $has_access = false;
                    $domain_validation = array(
                        'valid' => false,
                        'error' => $e->getMessage(),
                        'domain' => $current_domain,
                        'checks' => array(
                            'api_key_valid' => $api_key_validation_status,
                            'domain_associated' => false,
                            'domain_validated' => false
                        )
                    );
                }
            } else {
                error_log('AlloIA Admin: API client not initialized. API key exists: ' . (!empty($api_key_to_check) ? 'YES' : 'NO'));
                $domain_validation = array(
                    'valid' => false,
                    'error' => 'API client not initialized. Please ensure API key is set.',
                    'domain' => $current_domain,
                    'checks' => array(
                        'api_key_valid' => $api_key_validation_status,
                        'domain_associated' => false,
                        'domain_validated' => false
                    )
                );
            }
            
            // HOTFIX: Removed duplicate export_stats code that used wrong option names
            // Stats are already correctly loaded above at lines 279-286 via get_export_statistics()
        }
        
        return array(
            'active_tab' => $active_tab,
            'api_key' => $api_key,
            'subdomain' => $subdomain,
            'current_domain' => $current_domain,
            'ai_subdomain' => $ai_subdomain,
            'product_count' => $product_count,
            'category_count' => $category_count,
            'ai_bot_visits' => $ai_bot_visits,
            'recent_bots' => $recent_bots,
            'is_pro' => $is_pro,
            'license_expiry' => $license_expiry,
            'ai_revenue' => $ai_revenue,
            'revenue_change' => $revenue_change,
            'conversion_rate' => $conversion_rate,
            'conversion_change' => $conversion_change,
            'recommendations_served' => $recommendations_served,
            'is_enabled' => $is_enabled,
            'llms_txt_enabled' => $llms_txt_enabled,
            'ai_bot_tracking' => $ai_bot_tracking,
            'robots_txt_enabled' => $robots_txt_enabled,
            'advanced_analytics' => $advanced_analytics,
            'api_integration' => $api_integration,
            'smart_recommendations' => $smart_recommendations,
			'premium_support' => $premium_support,
			'ai_ready_score' => $ai_ready_score,
			'robots_audit' => $robots_audit,
			'alloia_tracking_website_id' => $alloia_tracking_website_id,
			'alloia_tracking_api_key' => $alloia_tracking_api_key,
			'wc_products' => $wc_products,
			'optimisation_products' => is_array($optimisation_products) ? $optimisation_products : array(),
			'domain_validation' => $domain_validation,
			'has_access' => $has_access,
			'api_key_validation_status' => $api_key_validation_status,
			'export_stats' => $export_stats
        );
    }
    
    /**
     * Process all form submissions
     */
    private function process_form_submissions() {
        // Training permission visual toggle
        if (isset($_POST['alloia_llm_training_toggle_nonce']) && check_admin_referer('alloia_llm_training_toggle', 'alloia_llm_training_toggle_nonce')) {
            if (current_user_can('manage_options')) {
                $val = (isset($_POST['alloia_llm_training_toggle']) && $_POST['alloia_llm_training_toggle'] == '1') ? 'allow' : 'disallow';
                update_option('alloia_llm_training', $val);
                $this->core->update_physical_robots_txt();
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success is-dismissible"><p>AI training permission updated.</p></div>';
                });
            }
        }

        // One-time robots.txt update
        if (isset($_POST['alloia_update_robots_now']) && check_admin_referer('alloia_update_robots', 'alloia_update_robots_nonce')) {
            if (current_user_can('manage_options')) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('AlloIA: robots.txt update form submitted');
                }
                $ok = $this->core->update_physical_robots_txt();
                add_action('admin_notices', function() use ($ok) {
                    echo '<div class="notice ' . ($ok ? 'notice-success' : 'notice-warning') . ' is-dismissible"><p>robots.txt ' . ($ok ? 'updated' : 'could not be updated') . '.</p></div>';
                });
            }
        }

        // Toggle AlloIA open-source ai. subdomain
        if (isset($_POST['alloia_ai_subdomain_toggle_nonce']) && check_admin_referer('alloia_ai_subdomain_toggle', 'alloia_ai_subdomain_toggle_nonce')) {
            if (current_user_can('manage_options')) {
                if (isset($_POST['alloia_ai_subdomain_toggle']) && $_POST['alloia_ai_subdomain_toggle'] == '1') {
                    $current_domain = wp_parse_url(home_url(), PHP_URL_HOST);
                    $ai_sub = 'ai.' . $current_domain;
                    update_option('alloia_subdomain', $ai_sub);
                } else {
                    update_option('alloia_subdomain', '');
                }
                $this->core->update_physical_robots_txt();
                // Force refresh of cached audit/score after change
                if (method_exists($this->core, 'run_hourly_audit')) {
                    $this->core->run_hourly_audit();
                }
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success is-dismissible"><p>AlloIA open-source subdomain settings updated.</p></div>';
                });
            }
        }
        // API settings save
        if (isset($_POST['alloia_save_api']) && check_admin_referer('alloia_api_settings', 'alloia_api_settings_nonce')) {
            if (current_user_can('manage_options')) {
                if (isset($_POST['alloia_api_base_url'])) {
                    update_option('alloia_api_base_url', esc_url_raw(wp_unslash($_POST['alloia_api_base_url'])));
                }
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success is-dismissible"><p>API settings updated.</p></div>';
                });
            }
        }

        // Site registration via API relay
        if (isset($_POST['alloia_register_site']) && check_admin_referer('alloia_site_register', 'alloia_site_register_nonce')) {
            if (current_user_can('manage_options')) {
                $website_api = new AlloIA_API();
                $domain = wp_parse_url(home_url(), PHP_URL_HOST);
                $site_url = home_url('/');
                $resp = $website_api->register_site($site_url, $domain);
                if (is_wp_error($resp)) {
                    add_action('admin_notices', function() use ($resp) {
                        $msg = esc_html($resp->get_error_message());
                        echo '<div class="notice notice-error is-dismissible"><p>Site registration failed: ' . esc_html($msg) . '</p></div>';
                    });
                } else {
                    $code = isset($resp['code']) ? intval($resp['code']) : 0;
                    $website_id = isset($resp['data']['websiteId']) ? sanitize_text_field($resp['data']['websiteId']) : '';
                    if ($code >= 200 && $code < 300 && $website_id) {
                        update_option('alloia_tracking_website_id', $website_id);
                        add_action('admin_notices', function() use ($website_id) {
                            echo '<div class="notice notice-success is-dismissible"><p>Site registered successfully. Website ID: <code>' . esc_html($website_id) . '</code></p></div>';
                        });
                    } else {
                        $raw = isset($resp['raw']) ? $resp['raw'] : '';
                        add_action('admin_notices', function() use ($raw) {
                            echo '<div class="notice notice-error is-dismissible"><p>Site registration responded unexpectedly. ' . esc_html($raw) . '</p></div>';
                        });
                    }
                }
            }
        }

        // Provision tracking via API relay
        if (isset($_POST['alloia_do_provision_tracking']) && check_admin_referer('alloia_provision_tracking', 'alloia_provision_tracking_nonce')) {
            if (current_user_can('manage_options')) {
                $website_id = get_option('alloia_tracking_website_id', '');
                if (!$website_id) {
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-error is-dismissible"><p>Website is not registered yet.</p></div>';
                    });
                } else {
                    $website_api = new AlloIA_API();
                    $resp = $website_api->provision_tracking($website_id);
                    if (is_wp_error($resp)) {
                        add_action('admin_notices', function() use ($resp) {
                            $msg = esc_html($resp->get_error_message());
                            echo '<div class="notice notice-error is-dismissible"><p>Provisioning failed: ' . esc_html($msg) . '</p></div>';
                        });
                    } else {
                        $code = isset($resp['code']) ? intval($resp['code']) : 0;
                        $data = isset($resp['data']) ? $resp['data'] : array();
                        if ($code >= 200 && $code < 300 && !empty($data['api_key'])) {
                            update_option('alloia_tracking_api_key', sanitize_text_field($data['api_key']));
                            // Optionally store script snippet and inject via alloia_tracking_code
                            if (!empty($data['script'])) {
                                update_option('alloia_tracking_code', wp_kses_post($data['script']));
                            }
                            add_action('admin_notices', function() {
                                echo '<div class="notice notice-success is-dismissible"><p>Tracking provisioned and script configured.</p></div>';
                            });
                        } else {
                            $raw = isset($resp['raw']) ? $resp['raw'] : '';
                            add_action('admin_notices', function() use ($raw) {
                                echo '<div class="notice notice-error is-dismissible"><p>Provisioning responded unexpectedly. ' . esc_html($raw) . '</p></div>';
                            });
                        }
                    }
                }
            }
        }
		// Run audit now
		if (isset($_POST['alloia_run_audit']) && check_admin_referer('alloia_run_audit_action', 'alloia_run_audit_nonce')) {
			if (current_user_can('manage_options')) {
				if (method_exists($this->core, 'run_hourly_audit')) {
					$this->core->run_hourly_audit();
				}
				add_action('admin_notices', function() {
					echo '<div class="notice notice-success is-dismissible"><p>Audit executed successfully. AI-ready score and robots analysis updated.</p></div>';
				});
			}
		}
        // Free features form
        if (isset($_POST['submit_free']) && check_admin_referer('alloia_settings', 'alloia_nonce')) {
            if (current_user_can('manage_options')) {
                // Update individual features
                update_option('alloia_llms_txt_enabled', isset($_POST['llms_txt_enabled']) ? 1 : 0);
                update_option('alloia_ai_bot_tracking', 0); // Pro-only
                update_option('alloia_robots_txt_enabled', isset($_POST['robots_txt_enabled']) ? 1 : 0);
                
                // Update physical files if needed
                $this->core->update_physical_robots_txt();
                $this->generate_llms_txt();
                
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success is-dismissible"><p>Free features settings saved successfully!</p></div>';
                });
            }
        }
        
        // Pro features form
        if (isset($_POST['submit_pro']) && check_admin_referer('alloia_pro_settings', 'alloia_pro_nonce')) {
            if (current_user_can('manage_options')) {
                $api_key = get_option('alloia_api_key', '');
                if ($api_key) {
                    update_option('alloia_advanced_analytics', isset($_POST['advanced_analytics']) ? 1 : 0);
                    update_option('alloia_api_integration', isset($_POST['api_integration']) ? 1 : 0);
                    update_option('alloia_smart_recommendations', isset($_POST['smart_recommendations']) ? 1 : 0);
                    update_option('alloia_premium_support', isset($_POST['premium_support']) ? 1 : 0);
                    
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-success is-dismissible"><p>Pro features settings saved successfully!</p></div>';
                    });
                } else {
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-error is-dismissible"><p>API key required for Pro features. Please enter your API key first.</p></div>';
                    });
                }
            }
        }
        
        // License activation form
        if (isset($_POST['activate_license']) && check_admin_referer('alloia_license_activation', 'alloia_license_nonce')) {
            if (current_user_can('manage_options')) {
                $license_key = isset($_POST['license_key']) ? sanitize_text_field(wp_unslash($_POST['license_key'])) : '';
                
                // Basic validation
                if (strlen($license_key) >= 20 && strpos($license_key, 'ak_') === 0) {
                    // Try to validate the API key against AlloIA API
                    try {
                        $api_client = new AlloIA_API($license_key);
                        $validation_result = $api_client->validate_api_key();
                        
                        // API already validates subscription status internally
                        $is_valid = ($validation_result['success'] ?? false) && 
                                   ($validation_result['valid'] ?? false);
                        
                        if ($is_valid) {
                            // Store the API key (both encrypted and regular for backward compatibility)
                            update_option('alloia_api_key_encrypted', $license_key);
                            update_option('alloia_api_key', $license_key);
                            update_option('alloia_license_expiry', wp_date('Y-m-d', strtotime('+1 year')));
                            
                            // Reinitialize API client with new key
                            $this->api_client = new AlloIA_API($license_key);
                            
                            // Store client information
                            if (isset($validation_result['client'])) {
                                update_option('alloia_client_info', $validation_result['client']);
                            }
                            
                            add_action('admin_notices', function() {
                                echo '<div class="notice notice-success is-dismissible"><p>API key validated and activated successfully! Pro features are now available.</p></div>';
                            });
                        } else {
                            add_action('admin_notices', function() {
                                echo '<div class="notice notice-error is-dismissible"><p>Invalid API key. Please check your key and try again.</p></div>';
                            });
                        }
                    } catch (Exception $e) {
                        // If API validation fails, still store the key but show warning
                        update_option('alloia_api_key', $license_key);
                        update_option('alloia_license_expiry', wp_date('Y-m-d', strtotime('+1 year')));
                        
                        add_action('admin_notices', function() use ($e) {
                            echo '<div class="notice notice-warning is-dismissible"><p>API key stored but could not be validated. Error: ' . esc_html($e->getMessage()) . '</p></div>';
                        });
                    }
                } else {
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-error is-dismissible"><p>Invalid API key format. API keys should start with "ak_" and be at least 20 characters long.</p></div>';
                    });
                }
            }
        }
        
        // Company Settings Form Handler
        if (isset($_POST['save_company_settings']) && check_admin_referer('alloia_company_settings', 'company_settings_nonce')) {
            if (current_user_can('manage_options')) {
                $company_name = isset($_POST['company_name']) ? sanitize_text_field(wp_unslash($_POST['company_name'])) : '';
                $website_description = isset($_POST['website_description']) ? sanitize_textarea_field(wp_unslash($_POST['website_description'])) : '';
                $business_category = isset($_POST['business_category']) ? sanitize_text_field(wp_unslash($_POST['business_category'])) : '';
                $contact_email = isset($_POST['contact_email']) ? sanitize_email(wp_unslash($_POST['contact_email'])) : '';
                
                // Save to WordPress options
                update_option('alloia_company_name', $company_name);
                update_option('alloia_website_description', $website_description);
                update_option('alloia_business_category', $business_category);
                update_option('alloia_contact_email', $contact_email);
                
                // Try to sync to AlloIA API if possible
                $this->sync_company_settings_to_api($company_name, $website_description, $business_category, $contact_email);
                
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success is-dismissible"><p>Company settings saved successfully!</p></div>';
                });
            }
        }
        
        // Competitor Settings Form Handler
        if (isset($_POST['save_competitor_settings']) && check_admin_referer('alloia_competitor_settings', 'competitor_settings_nonce')) {
            if (current_user_can('manage_options')) {
                $competitors_text = isset($_POST['competitors']) ? sanitize_textarea_field(wp_unslash($_POST['competitors'])) : '';
                $competitors = array_filter(array_map('trim', explode("\n", $competitors_text)));
                
                // Save to WordPress options
                update_option('alloia_competitors', $competitors);
                
                // Try to sync to AlloIA API if possible
                $this->sync_competitors_to_api($competitors);
                
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success is-dismissible"><p>Competitor settings saved successfully!</p></div>';
                });
            }
        }
        
        // API Settings Form Handler
        if (isset($_POST['save_api_settings']) && check_admin_referer('alloia_api_settings', 'api_settings_nonce')) {
            if (current_user_can('manage_options')) {
                $new_api_key = isset($_POST['new_api_key']) ? sanitize_text_field(wp_unslash($_POST['new_api_key'])) : '';
                
                if (!empty($new_api_key)) {
                    // Validate new API key
                    if (strlen($new_api_key) >= 20 && strpos($new_api_key, 'ak_') === 0) {
                        try {
                            $api_client = new AlloIA_API($new_api_key);
                            $validation_result = $api_client->validate_api_key();
                            
                            if ($validation_result['valid']) {
                                update_option('alloia_api_key', $new_api_key);
                                
                                // Store updated client information
                                if (isset($validation_result['client'])) {
                                    update_option('alloia_client_info', $validation_result['client']);
                                }
                                
                                add_action('admin_notices', function() {
                                    echo '<div class="notice notice-success is-dismissible"><p>API key updated and validated successfully!</p></div>';
                                });
                            } else {
                                add_action('admin_notices', function() {
                                    echo '<div class="notice notice-error is-dismissible"><p>Invalid API key. Please check your key and try again.</p></div>';
                                });
                            }
                        } catch (Exception $e) {
                            add_action('admin_notices', function() use ($e) {
                                echo '<div class="notice notice-warning is-dismissible"><p>API key could not be validated: ' . esc_html($e->getMessage()) . '</p></div>';
                            });
                        }
                    } else {
                        add_action('admin_notices', function() {
                            echo '<div class="notice notice-error is-dismissible"><p>Invalid API key format. API keys should start with "ak_" and be at least 20 characters long.</p></div>';
                        });
                    }
                }
            }
        }
        
        // Test API Connection Handler (from settings forms)
        if (isset($_POST['test_api_connection']) && check_admin_referer('alloia_api_settings', 'api_settings_nonce')) {
            if (current_user_can('manage_options')) {
                $api_key = get_option('alloia_api_key', '');
                
                if (!empty($api_key) && $this->api_client) {
                    try {
                        $health_result = $this->api_client->health_check();
                        
                        if ($health_result) {
                            add_action('admin_notices', function() {
                                echo '<div class="notice notice-success is-dismissible"><p>API connection test successful! Your connection to AlloIA is working properly.</p></div>';
                            });
                        } else {
                            add_action('admin_notices', function() {
                                echo '<div class="notice notice-warning is-dismissible"><p>API connection test failed. Please check your API key and try again.</p></div>';
                            });
                        }
                    } catch (Exception $e) {
                        add_action('admin_notices', function() use ($e) {
                            echo '<div class="notice notice-error is-dismissible"><p>API connection test failed: ' . esc_html($e->getMessage()) . '</p></div>';
                        });
                    }
                } else {
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-warning is-dismissible"><p>No API key configured. Please enter an API key first.</p></div>';
                    });
                }
            }
        }
        
        // Test API Connection Handler (from license activation form) 
        if (isset($_POST['test_api_connection']) && check_admin_referer('alloia_license_activation', 'alloia_license_nonce')) {
            if (current_user_can('manage_options')) {
                $api_key = get_option('alloia_api_key', '');
                
                if (!empty($api_key) && $this->api_client) {
                    try {
                        $health_result = $this->api_client->health_check();
                        
                        if ($health_result) {
                            add_action('admin_notices', function() {
                                echo '<div class="notice notice-success is-dismissible"><p>API connection test successful! Your connection to AlloIA is working properly.</p></div>';
                            });
                        } else {
                            add_action('admin_notices', function() {
                                echo '<div class="notice notice-warning is-dismissible"><p>API connection test failed. Please check your API key and try again.</p></div>';
                            });
                        }
                    } catch (Exception $e) {
                        add_action('admin_notices', function() use ($e) {
                            echo '<div class="notice notice-error is-dismissible"><p>API connection test failed: ' . esc_html($e->getMessage()) . '</p></div>';
                        });
                    }
                } else {
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-warning is-dismissible"><p>No API key configured. Please enter an API key first.</p></div>';
                    });
                }
            }
        }
        
        // Legacy form handlers for backwards compatibility
        $this->handle_legacy_forms();
    }
    
    /**
     * Handle legacy form submissions for backwards compatibility
     */
    private function handle_legacy_forms() {
        // Handle subdomain save
        if (isset($_POST['alloia_subdomain']) && check_admin_referer('alloia_save_subdomain', 'alloia_subdomain_nonce')) {
            if (current_user_can('manage_options')) {
                $subdomain = isset($_POST['alloia_subdomain']) ? sanitize_text_field(wp_unslash($_POST['alloia_subdomain'])) : '';
                update_option('alloia_subdomain', $subdomain);
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success is-dismissible"><p>Subdomain updated successfully!</p></div>';
                });
                $this->core->update_physical_robots_txt();
            }
        }
        
        // Handle LLM training toggle
        if (isset($_POST['alloia_llm_training_nonce']) && check_admin_referer('alloia_save_llm_training', 'alloia_llm_training_nonce')) {
            if (current_user_can('manage_options')) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('AlloIA: Training permission form submitted');
                }
                }
                $val = (isset($_POST['alloia_llm_training']) && $_POST['alloia_llm_training'] === 'allow') ? 'allow' : 'disallow';
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('AlloIA: Setting training permission to: ' . $val);
                }
                }
                update_option('alloia_llm_training', $val);
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success is-dismissible"><p>Robots.txt settings updated successfully!</p></div>';
                });
                $this->core->update_physical_robots_txt();
            }
        }
        
        // Generate llms.txt
        if (isset($_POST['generate_llms_txt']) && check_admin_referer('generate_llms_txt_action', 'generate_llms_txt_nonce')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AlloIA: llms.txt generation form submitted');
            }
            }
            $this->generate_llms_txt();
        }
        
        // Handle redirect configuration
        if (isset($_POST['ai_redirect_nonce']) && check_admin_referer('ai_save_redirect', 'ai_redirect_nonce')) {
            $method_val = isset($_POST['ai_server_type']) ? sanitize_text_field(wp_unslash($_POST['ai_server_type'])) : '';
            update_option('ai_server_type', $method_val);
            // Auto-enable when a method is chosen; disable if empty
            $enable = in_array($method_val, array('apache', 'nginx', 'php', 'wp', 'waf', 'edge'), true);
            update_option('ai_redirect_enabled', $enable);

            // If Apache selected, attempt to insert .htaccess rules
            if ($enable && $method_val === 'apache' && method_exists($this->core, 'update_apache_htaccess_rules')) {
                $this->core->update_apache_htaccess_rules('apache');
            }
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>Redirect configuration updated successfully!</p></div>';
            });
        }
    }
    
    /**
     * Generate llms.txt file using AlloIA.io API
     */
    private function generate_llms_txt() {
        $site_url = home_url('/');
        
        // Use AlloIA.io API to generate llms.txt
        $api_url = 'https://www.alloia.io/api/tools/llms-txt?url=' . urlencode($site_url);
        
        // Debug: Always log the API call
        error_log('AlloIA: Making API call to: ' . $api_url);
        error_log('AlloIA: Site URL: ' . $site_url);
        
        // Make API request
        $response = wp_remote_get($api_url, array(
            'timeout' => 30,
            'user-agent' => 'AlloIA-WooCommerce-Plugin/' . ALLOIA_VERSION,
            'headers' => array(
                'Accept' => 'text/plain',
            ),
        ));
        
        // Check for errors
        if (is_wp_error($response)) {
            error_log('AlloIA: Failed to generate llms.txt via API: ' . $response->get_error_message());
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible"><p><strong>Error:</strong> Failed to generate llms.txt via AlloIA.io API. Please try again later.</p></div>';
            });
            return;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('AlloIA: API response code: ' . $response_code);
        }
        
        if ($response_code !== 200) {
            error_log('AlloIA: API returned error code ' . $response_code . ' for llms.txt generation, generating basic content');
            $llms_content = $this->generate_basic_llms_txt($site_url);
        } else {
            $llms_content = wp_remote_retrieve_body($response);
        }
        
        // Debug: Log content length
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('AlloIA: API returned content length: ' . strlen($llms_content));
        }
        
        // Validate content
        if (empty($llms_content) || strlen($llms_content) < 50) {
            error_log('AlloIA: API returned invalid or empty llms.txt content (length: ' . strlen($llms_content) . ')');
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible"><p><strong>Error:</strong> AlloIA.io API returned invalid content. Please try again later.</p></div>';
            });
            return;
        }
        
        // Save the generated content
        $llms_path = wp_normalize_path(ABSPATH . 'llms.txt');
        if (strpos($llms_path, wp_normalize_path(ABSPATH)) !== 0) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible"><p><strong>Error:</strong> Invalid llms.txt path. File not created.</p></div>';
            });
            return;
        }
        
        // Check if directory is writable using WP_Filesystem
        global $wp_filesystem;
        if (!WP_Filesystem()) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible"><p><strong>Error:</strong> Cannot initialize filesystem. Cannot create llms.txt file.</p></div>';
            });
            return;
        }
        
        if (!$wp_filesystem->is_writable(ABSPATH)) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible"><p><strong>Error:</strong> WordPress root directory is not writable. Cannot create llms.txt file.</p></div>';
            });
            return;
        }
        
        $result = @file_put_contents($llms_path, $llms_content);
        if ($result === false) {
            error_log('AlloIA: Failed to write llms.txt file to ' . $llms_path);
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible"><p><strong>Error:</strong> Failed to write llms.txt file. Please check file permissions.</p></div>';
            });
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p><strong>Success:</strong> llms.txt file has been generated successfully using AlloIA.io API!</p></div>';
            });
        }
    }
    
    
    
    /**
     * Render the Knowledge Graph page
     */
    public function render_knowledge_graph_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'geo-ia-optimisation-alloia'));
        }
        
        // Check if API client is available
        if (!$this->api_client) {
            $this->render_api_key_required_page('Knowledge Graph Export');
            return;
        }
        
        // Check WooCommerce dependency
        if (!class_exists('WooCommerce')) {
            $this->render_woocommerce_required_page('Knowledge Graph Export');
            return;
        }
        
        // Process form submissions
        $this->process_knowledge_graph_forms();
        
        // Prepare KG data
        $data = $this->prepare_knowledge_graph_data();
        
        // Render KG template
        include ALLOIA_PLUGIN_PATH . 'admin/views/knowledge-graph-page.php';
    }
    
    
    
    /**
     * Run comprehensive API tests
     */
    private function run_api_tests() {
        $results = array(
            'timestamp' => current_time('mysql'),
            'api_client_config' => array(),
            'website_api_config' => array(),
            'connection_tests' => array(),
            'wordpress_options' => array()
        );
        
        // Test 1: API Client Configuration
        $api_key = get_option('alloia_api_key', '');
        if (!empty($api_key)) {
            $api_client = new AlloIA_API($api_key);
            $results['api_client_config'] = $api_client->get_client_info();
            
            // Run connection tests
            try {
                $results['connection_tests'] = $api_client->test_api_connection();
            } catch (Exception $e) {
                $results['connection_tests'] = array(
                    'error' => 'Failed to run API tests: ' . $e->getMessage()
                );
            }
        } else {
            $results['api_client_config'] = array('error' => 'No API key configured');
        }
        
        // Test 2: Website API Configuration
        if (class_exists('AlloIA_API')) {
            $website_api = new AlloIA_API();
            $api_base_url = get_option('alloia_api_base_url', 'default');
            
            $results['website_api_config'] = array(
                'api_base_url' => $api_base_url,
                'api_key_set' => !empty(get_option('alloia_api_key', '')),
                'api_key_preview' => !empty(get_option('alloia_api_key', '')) ? substr(get_option('alloia_api_key', ''), 0, 6) . '...' : 'Not set'
            );
        }
        
        // Test 3: WordPress Options Check
        $options_to_check = array(
            'alloia_api_key' => 'Main API Key',
            'alloia_api_base_url' => 'Custom API Base URL'
        );
        
        foreach ($options_to_check as $option => $description) {
            $value = get_option($option, '');
            $results['wordpress_options'][$option] = array(
                'description' => $description,
                'status' => !empty($value) ? 'Set' : 'Not Set',
                'preview' => !empty($value) ? (strlen($value) > 20 ? substr($value, 0, 20) . '...' : $value) : 'Not set'
            );
        }
        
        return $results;
    }
    
    /**
     * Display test results in a formatted way
     */
    private function display_test_results($results) {
        ?>
        <div class="test-results">
            
            <!-- API Client Configuration -->
            <div class="postbox">
                <h3 class="hndle">1. API Client Configuration</h3>
                <div class="inside">
                    <?php if (isset($results['api_client_config']['error'])): ?>
                        <div class="notice notice-error inline"><p><?php echo esc_html($results['api_client_config']['error']); ?></p></div>
                    <?php else: ?>
                        <table class="form-table">
                            <?php foreach ($results['api_client_config'] as $key => $value): ?>
                                <tr>
                                    <th><?php echo esc_html(ucwords(str_replace('_', ' ', $key))); ?></th>
                                    <td><?php echo esc_html(is_bool($value) ? ($value ? 'Yes' : 'No') : $value); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Website API Configuration -->
            <?php if (!empty($results['website_api_config'])): ?>
            <div class="postbox">
                <h3 class="hndle">2. Website API Configuration</h3>
                <div class="inside">
                    <table class="form-table">
                        <?php foreach ($results['website_api_config'] as $key => $value): ?>
                            <tr>
                                <th><?php echo esc_html(ucwords(str_replace('_', ' ', $key))); ?></th>
                                <td><?php echo esc_html(is_bool($value) ? ($value ? 'Yes' : 'No') : $value); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Connection Tests -->
            <div class="postbox">
                <h3 class="hndle">3. API Connection Tests</h3>
                <div class="inside">
                    <?php if (isset($results['connection_tests']['error'])): ?>
                        <div class="notice notice-error inline"><p><?php echo esc_html($results['connection_tests']['error']); ?></p></div>
                    <?php elseif (isset($results['connection_tests']['tests'])): ?>
                        <?php foreach ($results['connection_tests']['tests'] as $test_name => $test_result): ?>
                            <div class="test-result-item" style="margin-bottom: 15px; padding: 10px; border: 1px solid #ddd;">
                                <h4><?php echo esc_html(ucwords(str_replace('_', ' ', $test_name))); ?></h4>
                                <p><strong>Status:</strong> 
                                    <span class="status-<?php echo esc_attr($test_result['status']); ?>">
                                        <?php echo esc_html(ucfirst($test_result['status'])); ?>
                                    </span>
                                </p>
                                
                                <?php if (isset($test_result['error'])): ?>
                                    <p><strong>Error:</strong> <code><?php echo esc_html($test_result['error']); ?></code></p>
                                <?php elseif (isset($test_result['response'])): ?>
                                    <details>
                                        <summary>Response Data</summary>
                                        <pre style="background: #f8f9fa; padding: 10px; overflow-x: auto;"><?php echo esc_html(print_r($test_result['response'], true)); ?></pre>
                                    </details>
                                <?php elseif (isset($test_result['reason'])): ?>
                                    <p><strong>Reason:</strong> <?php echo esc_html($test_result['reason']); ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- WordPress Options -->
            <div class="postbox">
                <h3 class="hndle">4. WordPress Options Check</h3>
                <div class="inside">
                    <table class="wp-list-table widefat striped">
                        <thead>
                            <tr>
                                <th>Option</th>
                                <th>Description</th>
                                <th>Status</th>
                                <th>Value Preview</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results['wordpress_options'] as $option => $data): ?>
                                <tr>
                                    <td><code><?php echo esc_html($option); ?></code></td>
                                    <td><?php echo esc_html($data['description']); ?></td>
                                    <td><?php echo esc_html($data['status']); ?></td>
                                    <td><code><?php echo esc_html($data['preview']); ?></code></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
        </div>
        
        <style>
            .status-success { color: #00a32a; font-weight: bold; }
            .status-error { color: #d63638; font-weight: bold; }
            .status-warning { color: #dba617; font-weight: bold; }
            .status-skipped { color: #646970; font-style: italic; }
            .test-results .postbox { margin-bottom: 20px; }
        </style>
        <?php
    }
    
    /**
     * Render Pro required page for features that need license
     */
    private function render_pro_required_page($feature_name) {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html($feature_name) . ' - AlloIA Pro Required</h1>';
        echo '<div class="notice notice-warning" style="padding:20px;margin:20px 0;">';
        echo '<h2>🔒 Pro License Required</h2>';
        echo '<p>The ' . esc_html($feature_name) . ' feature requires an active AlloIA Pro license.</p>';
        echo '<p><a href="' . esc_url(admin_url('admin.php?page=alloia-settings&tab=ai-commerce')) . '" class="button button-primary">Configure AI Commerce</a></p>';
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Render WooCommerce required page for features that need it
     */
    private function render_woocommerce_required_page($feature_name) {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html($feature_name) . ' - WooCommerce Required</h1>';
        echo '<div class="notice notice-info" style="padding:20px;margin:20px 0;">';
        echo '<h2>🛒 WooCommerce Required</h2>';
        echo '<p>The ' . esc_html($feature_name) . ' feature requires WooCommerce to manage your products.</p>';
        echo '<p>';
        echo '<a href="' . esc_url(admin_url('plugin-install.php?s=woocommerce&tab=search&type=term')) . '" class="button button-primary">Install WooCommerce</a> ';
        echo '<a href="' . esc_url(admin_url('admin.php?page=alloia-settings')) . '" class="button button-secondary">Back to AlloIA Settings</a>';
        echo '</p>';
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Render API key required page for features that need API key
     */
    private function render_api_key_required_page($feature_name) {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html($feature_name) . ' - API Key Required</h1>';
        echo '<div class="notice notice-warning" style="padding:20px;margin:20px 0;">';
        echo '<h2>🔑 API Key Required</h2>';
        echo '<p>The ' . esc_html($feature_name) . ' feature requires a valid AlloIA API key.</p>';
        echo '<p><a href="' . esc_url(admin_url('admin.php?page=alloia-settings&tab=pro')) . '" class="button button-primary">Enter API Key</a></p>';
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Prepare data for Dashboard page
     */
    private function prepare_dashboard_data() {
        // CLEANUP: Removed non-existent AlloIA_Dashboard_API_Connector
        // Dashboard now uses demo data only
        // Real analytics will be available via AlloIA web portal
        
        $demo_data = $this->get_demo_dashboard_data();
        
        // Try to get company name from stored client info
        $client_info = get_option('alloia_client_info', array());
        if (isset($client_info['name']) && !empty($client_info['name'])) {
            $demo_data['company_name'] = $client_info['name'];
        } elseif ($saved_company_name = get_option('alloia_company_name', '')) {
            $demo_data['company_name'] = $saved_company_name;
        }
        
        return $demo_data;
    }
    
    /**
     * Prepare data for Knowledge Graph page
     */
    private function prepare_knowledge_graph_data() {
        $data = array(
            'exporter' => $this->knowledge_graph_exporter,
            'has_access' => false,
            'domain_validation' => null,
            'export_stats' => array(
                'total_exported' => 0,
                'total_failed' => 0,
                'last_export' => null
            ),
            'batch_size' => get_option('alloia_export_batch_size', 50)
        );
        
        // Check if user has access to Knowledge Graph features via domain validation
        // Check for encrypted API key first, then fallback to regular API key
        $api_key = get_option('alloia_api_key_encrypted', '');
        if (empty($api_key)) {
            $api_key = get_option('alloia_api_key', '');
        }
        $api_key_valid = false;
        
        if ($this->api_client) {
            // Always check API key status independently for better user feedback
            try {
                $validation_result = $this->api_client->validate_api_key();
                
                // API already validates subscription status internally
                $api_key_valid = (
                    isset($validation_result['success']) && $validation_result['success'] === true &&
                    isset($validation_result['valid']) && $validation_result['valid'] === true
                );
                
                // Log for debugging
                if (defined('WP_DEBUG') && WP_DEBUG && !$api_key_valid) {
                    error_log('AlloIA API Key Validation Failed: ' . json_encode($validation_result));
                }
            } catch (Exception $api_error) {
                // Log the error for debugging
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('AlloIA API Key Validation Error: ' . $api_error->getMessage());
                    $api_key = get_option('alloia_api_key_encrypted', '') ?: get_option('alloia_api_key', '');
                    error_log('AlloIA API Key: ' . substr($api_key, 0, 20) . '...');
                }
                // API key is invalid or API call failed
                $api_key_valid = false;
            }
            
            // Now check domain validation
            try {
                $domain_validation = $this->api_client->validate_domain_for_sync();
                
                // Override API key check result with the independent check we just did
                // This ensures API key shows green even if domain validation fails
                $domain_validation['checks']['api_key_valid'] = $api_key_valid;
                
                $data['domain_validation'] = $domain_validation;
                $data['has_access'] = $domain_validation['valid'];
            } catch (Exception $e) {
                // If domain validation throws exception, still show API key status
                $data['has_access'] = false;
                
                $data['domain_validation'] = array(
                    'valid' => false,
                    'error' => $e->getMessage(),
                    'domain' => wp_parse_url(home_url(), PHP_URL_HOST),
                    'checks' => array(
                        'api_key_valid' => $api_key_valid, // Use the independent check
                        'domain_associated' => false,
                        'domain_validated' => false
                    )
                );
            }
        } else {
            // No API client available - check if API key exists
            $encrypted_key = get_option('alloia_api_key_encrypted', '');
            $regular_key = get_option('alloia_api_key', '');
            $api_key_to_check = !empty($encrypted_key) ? $encrypted_key : $regular_key;
            
            if (!empty($api_key_to_check)) {
                // API key exists but client not initialized - try to validate it
                try {
                    $temp_api_client = new AlloIA_API($api_key_to_check);
                    $validation_result = $temp_api_client->validate_api_key();
                    
                    // Check actual API response structure
                    $api_key_valid = (
                        isset($validation_result['success']) && $validation_result['success'] === true &&
                        isset($validation_result['valid']) && $validation_result['valid'] === true &&
                        // API validates subscription internally
                        true
                    );
                } catch (Exception $e) {
                    $api_key_valid = false;
                }
            } else {
                $api_key_valid = false;
            }
            
            $data['domain_validation'] = array(
                'valid' => false,
                'error' => empty($api_key_to_check) ? 'API key not configured' : 'API client not initialized',
                'domain' => wp_parse_url(home_url(), PHP_URL_HOST),
                'checks' => array(
                    'api_key_valid' => $api_key_valid,
                    'domain_associated' => false,
                    'domain_validated' => false
                )
            );
        }
        
        // Get export statistics if exporter is available
        if ($this->knowledge_graph_exporter) {
            try {
                $data['export_stats'] = $this->knowledge_graph_exporter->get_export_statistics();
            } catch (Exception $e) {
                // Keep default values if API call fails
            }
        }
        
        // Get WooCommerce data
        if (class_exists('WooCommerce')) {
            $data['total_products'] = wp_count_posts('product')->publish ?? 0;
            $data['product_categories'] = get_terms(array(
                'taxonomy' => 'product_cat',
                'hide_empty' => false,
                'fields' => 'names'
            ));
        } else {
            $data['total_products'] = 0;
            $data['product_categories'] = array();
        }
        
        return $data;
    }
    
    /**
     * Get cached products preview for admin dashboard
     * Uses transient caching to avoid repeated queries
     */
    private function get_cached_products_preview($page = 1, $per_page = 20) {
        $cache_key = 'alloia_products_preview_' . $page . '_' . $per_page;
        $cached_products = get_transient($cache_key);
        
        if ($cached_products !== false) {
            return $cached_products;
        }
        
        $products = $this->load_products_optimized($per_page, true, $page);
        
        // Cache for 15 minutes
        set_transient($cache_key, $products, 15 * MINUTE_IN_SECONDS);
        
        return $products;
    }
    
    /**
     * Optimized product loading with caching and performance improvements
     */
    private function load_products_optimized($limit = 50, $preview_mode = false, $page = 1) {
        if (!class_exists('WooCommerce')) {
            return array();
        }
        
        $cache_key = 'alloia_products_' . $limit . '_' . ($preview_mode ? 'preview' : 'full') . '_page_' . $page;
        $cached_products = get_transient($cache_key);
        
        if ($cached_products !== false) {
            return $cached_products;
        }
        
        // Calculate offset for pagination
        $offset = ($page - 1) * $limit;
        
        // Optimized query arguments
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'offset' => $offset,
            'fields' => 'ids', // Only get IDs first for better performance
            'no_found_rows' => true, // Skip pagination counting
            'update_post_meta_cache' => false, // Skip meta cache
            'update_post_term_cache' => false, // Skip term cache
            'orderby' => 'date',
            'order' => 'DESC'
        );
        
        $product_ids = get_posts($args);
        $products = array();
        
        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);
            if ($product) {
                $product_data = array(
                    'id' => $product_id,
                    'title' => $product->get_name(),
                    'price' => $product->get_price_html()
                );
                
                if (!$preview_mode) {
                    $selected_products = get_option('alloia_optimisation_products', array());
                    $product_data['selected'] = in_array($product_id, $selected_products);
                }
                
                $products[] = $product_data;
            }
        }
        
        // Cache for 10 minutes for optimization data, 15 for preview
        $cache_time = $preview_mode ? 15 * MINUTE_IN_SECONDS : 10 * MINUTE_IN_SECONDS;
        set_transient($cache_key, $products, $cache_time);
        
        return $products;
    }

    /**
     * Prepare data for Optimization page with caching
     */
    private function prepare_optimization_data() {
        $selected_products = get_option('alloia_optimisation_products', array());
        $wc_products = $this->load_products_optimized(50, false);
        
        return array(
            'products' => $wc_products,
            'selected_count' => count($selected_products),
            'estimated_monthly_cost' => count($selected_products) * 0.5,
            'optimization_status' => 'inactive', // inactive, running, completed
            'last_optimization' => null,
        );
    }
    
    /**
     * Process knowledge graph form submissions
     */
    private function process_knowledge_graph_forms() {
        // Handle export products
        if (isset($_POST['alloia_export_products']) && check_admin_referer('alloia_export_products', 'alloia_export_products_nonce')) {
            if (current_user_can('manage_options')) {
                try {
                    $filters = array();
                    
                    // Collect filter values
                    if (!empty($_POST['category'])) {
                        $filters['category'] = isset($_POST['category']) ? sanitize_text_field(wp_unslash($_POST['category'])) : '';
                    }
                    if (!empty($_POST['min_price'])) {
                        $filters['min_price'] = floatval($_POST['min_price']);
                    }
                    if (!empty($_POST['max_price'])) {
                        $filters['max_price'] = floatval($_POST['max_price']);
                    }
                    if (!empty($_POST['in_stock'])) {
                        $filters['in_stock'] = isset($_POST['in_stock']) ? sanitize_text_field(wp_unslash($_POST['in_stock'])) : '';
                    }
                    if (!empty($_POST['date_from'])) {
                        $filters['date_from'] = isset($_POST['date_from']) ? sanitize_text_field(wp_unslash($_POST['date_from'])) : '';
                    }
                    if (!empty($_POST['date_to'])) {
                        $filters['date_to'] = isset($_POST['date_to']) ? sanitize_text_field(wp_unslash($_POST['date_to'])) : '';
                    }
                    
                    $background_export = isset($_POST['background_export']) && $_POST['background_export'] == '1';
                    
                    $result = $this->knowledge_graph_exporter->export_products($filters, $background_export);
                    
                    if ($result) {
                        add_action('admin_notices', function() {
                            echo '<div class="notice notice-success is-dismissible"><p>Product export started successfully!</p></div>';
                        });
                    } else {
                        add_action('admin_notices', function() {
                            echo '<div class="notice notice-error is-dismissible"><p>Failed to start export. Please try again.</p></div>';
                        });
                    }
                } catch (Exception $e) {
                    add_action('admin_notices', function() use ($e) {
                        echo '<div class="notice notice-error is-dismissible"><p>Export error: ' . esc_html($e->getMessage()) . '</p></div>';
                    });
                }
            }
        }
        
        // Handle batch size update
        if (isset($_POST['alloia_update_batch_size']) && check_admin_referer('alloia_update_batch_size', 'alloia_update_batch_size_nonce')) {
            if (current_user_can('manage_options')) {
                $batch_size = isset($_POST['batch_size']) ? intval(wp_unslash($_POST['batch_size'])) : 50;
                if ($batch_size >= 10 && $batch_size <= 1000) {
                    $this->knowledge_graph_exporter->set_batch_size($batch_size);
                    update_option('alloia_export_batch_size', $batch_size);
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-success is-dismissible"><p>Batch size updated successfully!</p></div>';
                    });
                } else {
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-error is-dismissible"><p>Invalid batch size. Must be between 10 and 1000.</p></div>';
                    });
                }
            }
        }
    }

    /**
     * AJAX handler for exporting products
     */
    public function ajax_export_products() {
        check_ajax_referer('alloia_kg_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $filters_raw = isset($_POST['filters']) ? wp_unslash($_POST['filters']) : '{}';
        $filters = json_decode($filters_raw, true);
        $background = isset($_POST['background']) && sanitize_text_field(wp_unslash($_POST['background'])) === 'true';
        
        try {
            $result = $this->knowledge_graph_exporter->export_products($filters, $background);
            wp_send_json_success($result);
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * AJAX handler for getting export status
     */
    public function ajax_get_export_status() {
        check_ajax_referer('alloia_kg_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
                $export_id = isset($_POST['export_id']) ? sanitize_text_field(wp_unslash($_POST['export_id'])) : '';
        
        try {
            $status = $this->knowledge_graph_exporter->get_export_status($export_id);
            wp_send_json_success($status);
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * AJAX handler for updating batch size
     */
    public function ajax_update_batch_size() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'alloia_kg_nonce')) {
            wp_send_json_error(array('message' => 'Invalid nonce'));
            return;
        }
        
        // Verify user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
            return;
        }
        
        // Validate input
        if (!isset($_POST['batch_size'])) {
            wp_send_json_error(array('message' => 'Missing batch size parameter'));
            return;
        }
        
        $batch_size = isset($_POST['batch_size']) ? intval(wp_unslash($_POST['batch_size'])) : 50;
        
        if ($batch_size >= 10 && $batch_size <= 1000) {
            $this->knowledge_graph_exporter->set_batch_size($batch_size);
            update_option('alloia_export_batch_size', $batch_size);
            wp_send_json_success(array(
                'message' => 'Batch size updated successfully!',
                'batch_size' => $batch_size
            ));
        } else {
            wp_send_json_error(array('message' => 'Invalid batch size. Must be between 10 and 1000.'));
        }
    }

    /**
     * Auto-sync product when it's created or updated
     * Schedules the sync to run in background
     */
    public function auto_sync_product($product_id) {
        // Schedule sync in background to avoid blocking product save
        wp_schedule_single_event(time() + 5, 'alloia_auto_sync_single_product', array($product_id));
    }
    
    /**
     * Process auto-sync for a single product
     */
    public function process_auto_sync_product($product_id) {
        // Check if domain is validated
        if (!$this->api_client) {
            return;
        }
        
        try {
            $domain_validation = $this->api_client->validate_domain_for_sync();
            if (!$domain_validation['valid']) {
                // Domain not validated, skip sync
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('AlloIA Auto-sync: Domain not validated for product ' . $product_id);
                }
                return;
            }
        } catch (Exception $e) {
            // Error validating domain, skip sync
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AlloIA Auto-sync: Domain validation failed - ' . $e->getMessage());
            }
            return;
        }
        
        // Get product
        $product = wc_get_product($product_id);
        if (!$product) {
            return;
        }
        
        // Only sync published products
        if ($product->get_status() !== 'publish') {
            return;
        }
        
        // Exclude private products
        if ($product->get_catalog_visibility() === 'hidden' || $product->get_catalog_visibility() === 'private') {
            return;
        }
        
        // Sync single product via exporter
        try {
            $filters = array('include' => array($product_id));
            $this->knowledge_graph_exporter->export_products($filters, true); // Background mode
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AlloIA Auto-sync: Failed to sync product ' . $product_id . ' - ' . $e->getMessage());
            }
        }
    }
    
    /**
     * AJAX handler for syncing all products
     * HOTFIX DEBUG VERSION - Shows detailed sync information
     */
    public function ajax_sync_all_products() {
        check_ajax_referer('alloia_kg_nonce', 'nonce');
        
        $debug_info = array();
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
            return;
        }
        
        $debug_info[] = '✓ Permission check passed';
        
        // Check domain validation
        if (!$this->api_client) {
            wp_send_json_error(array('message' => 'API client not initialized. Please configure your API key.'));
            return;
        }
        
        $debug_info[] = '✓ API client initialized';
        
        try {
            $domain_validation = $this->api_client->validate_domain_for_sync();
            $debug_info[] = '✓ Domain validation result: ' . json_encode($domain_validation);
            
            if (!$domain_validation['valid']) {
                wp_send_json_error(array(
                    'message' => 'Domain validation required: ' . ($domain_validation['error'] ?? 'Unknown error'),
                    'validation' => $domain_validation,
                    'debug' => $debug_info
                ));
                return;
            }
        } catch (Exception $e) {
            $debug_info[] = '✗ Exception during domain validation: ' . $e->getMessage();
            wp_send_json_error(array('message' => 'Domain validation failed: ' . $e->getMessage(), 'debug' => $debug_info));
            return;
        }
        
        $debug_info[] = '✓ Domain validation passed - ready to sync';
        
        // Check if exporter exists
        if (!$this->knowledge_graph_exporter) {
            $debug_info[] = '✗ Knowledge graph exporter not initialized';
            wp_send_json_error(array('message' => 'Export service not available', 'debug' => $debug_info));
            return;
        }
        
        $debug_info[] = '✓ Knowledge graph exporter ready';
        
        // Export all products
        try {
            // HOTFIX: Include virtual and out-of-stock products by default
            // (matches old UI behavior where checkboxes were checked by default)
            $filters = array(
                'include_virtual' => 1,
                'include_out_of_stock' => 1
            );
            $debug_info[] = '→ Calling export_products() with filters: ' . json_encode($filters);
            
            $result = $this->knowledge_graph_exporter->export_products($filters, false); // Immediate mode for testing
            
            $debug_info[] = '✓ Export completed - Result: ' . json_encode($result);
            
            // Get updated statistics
            $stats = $this->knowledge_graph_exporter->get_export_statistics();
            $debug_info[] = '→ Current statistics after export: ' . json_encode($stats);
            
            wp_send_json_success(array(
                'message' => 'Product sync completed!',
                'export_id' => $result['export_id'] ?? null,
                'total_products' => $result['total_products'] ?? 0,
                'success' => $result['success'] ?? false,
                'detailed_message' => $result['message'] ?? 'No detailed message',
                'debug' => $debug_info,
                'full_result' => $result,
                'updated_stats' => $stats
            ));
        } catch (Exception $e) {
            $debug_info[] = '✗ Exception during export: ' . $e->getMessage();
            $debug_info[] = '✗ Stack trace: ' . $e->getTraceAsString();
            wp_send_json_error(array('message' => $e->getMessage(), 'debug' => $debug_info));
        }
    }
    
    /**
     * AJAX handler for validating API key
     */
    public function ajax_validate_api_key() {
        check_ajax_referer('alloia_kg_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
            return;
        }
        
        $api_key = isset($_POST['api_key']) ? sanitize_text_field(wp_unslash($_POST['api_key'])) : '';
        
        if (empty($api_key)) {
            wp_send_json_error(array('message' => 'API key is required'));
            return;
        }
        
        try {
            $api_client = new AlloIA_API($api_key);
            $validation_result = $api_client->validate_api_key();
            
            $is_valid = ($validation_result['success'] ?? false) && 
                       ($validation_result['valid'] ?? false) && 
                       true; // API validates subscription internally
            
            wp_send_json_success(array(
                'valid' => $is_valid,
                'message' => $is_valid ? 'API key is valid' : 'API key is invalid',
                'details' => $validation_result
            ));
        } catch (Exception $e) {
            wp_send_json_error(array('message' => 'Validation failed: ' . $e->getMessage()));
        }
    }
    
    /**
     * AJAX handler for quick subscribe
     */
    public function ajax_quick_subscribe() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['alloia_quick_subscribe_nonce'], 'alloia_quick_subscribe')) {
            wp_die('Security check failed');
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        // Validate input
        $customer_email = isset($_POST['customer_email']) ? sanitize_email(wp_unslash($_POST['customer_email'])) : '';
        $company_name = isset($_POST['company_name']) ? sanitize_text_field(wp_unslash($_POST['company_name'])) : '';
        
        if (empty($customer_email)) {
            wp_send_json_error('Email address is required');
        }
        
        // Prepare customer data
        $customer_data = array(
            'email' => $customer_email,
            'company_name' => $company_name
        );
        
        // Handle checkout redirect
        $result = $this->handle_checkout_redirect('graph_auto', $customer_data);
        
        if ($result['success']) {
            wp_send_json_success(array(
                'redirect_url' => $result['redirect_url'],
                'message' => $result['message']
            ));
        } else {
            wp_send_json_error($result['error']);
        }
    }
    
    // CLEANUP: Removed handle_checkout_redirect() method
    // Subscription system no longer used (product-based pricing via alloia.io)
    
    /**
     * Clear all cached dashboard data
     */
    public function clear_cache() {
        wp_cache_delete_group('alloia_dashboard');
        
        // Clear product caches too
        delete_transient('alloia_products_preview');
        $cache_keys = array('alloia_products_20_preview', 'alloia_products_50_full');
        foreach ($cache_keys as $key) {
            delete_transient($key);
        }
    }
    
    /**
     * AJAX handler for lazy loading widget data
     */
    public function ajax_load_widget_data() {
        // Verify nonce
        if (!check_ajax_referer('alloia_ajax', 'nonce', false)) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $widget_type = isset($_POST['widget_type']) ? sanitize_text_field(wp_unslash($_POST['widget_type'])) : '';
        
        if (empty($widget_type)) {
            wp_send_json_error('Widget type not specified');
            return;
        }
        
        try {
            $html = '';
            $data = array();
            
            switch ($widget_type) {
                case 'analytics':
                    $analytics_data = $this->dashboard_api->get_real_time_analytics();
                    $html = $this->render_analytics_widget($analytics_data);
                    $data['analytics'] = $analytics_data;
                    break;
                    
                case 'products_preview':
                    if ($this->has_valid_api_key()) {
                        $products = $this->get_cached_products_preview();
                        $html = $this->render_products_preview_widget($products);
                        $data['products'] = $products;
                    } else {
                        $html = '<div class="notice notice-warning"><p>API key required for product data</p></div>';
                    }
                    break;
                    
                default:
                    wp_send_json_error('Unknown widget type');
                    return;
            }
            
            wp_send_json_success(array(
                'html' => $html,
                'data' => $data,
                'widget_type' => $widget_type
            ));
            
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Widget loading failed: ' . $e->getMessage());
            }
            wp_send_json_error('Failed to load widget data: ' . $e->getMessage());
        }
    }
    
    /**
     * Render analytics widget HTML
     */
    private function render_analytics_widget($data) {
        if (!$data || !$data['success']) {
            return '<div class="notice notice-warning"><p>Analytics data not available</p></div>';
        }
        
        $visits = $data['data']['total_visits'] ?? 0;
        $recent = count($data['data']['recent_visits'] ?? array());
        
        return sprintf(
            '<div class="analytics-summary">
                <div class="metric-row">
                    <span class="metric-value">%s</span>
                    <span class="metric-label">Total AI Visits</span>
                </div>
                <div class="metric-row">
                    <span class="metric-value">%d</span>
                    <span class="metric-label">Recent Visits</span>
                </div>
            </div>',
            number_format($visits),
            $recent
        );
    }
    
    /**
     * Render products preview widget HTML  
     */
    private function render_products_preview_widget($products) {
        if (empty($products)) {
            return '<div class="notice notice-info"><p>No products found</p></div>';
        }
        
        $html = '<div class="products-preview">';
        $count = 0;
        foreach ($products as $product) {
            if ($count >= 5) break; // Limit to 5 for preview
            
            $html .= sprintf(
                '<div class="product-preview-item">
                    <strong>%s</strong>
                    <span class="price">%s</span>
                </div>',
                esc_html($product['title']),
                $product['price']
            );
            $count++;
        }
        $html .= '</div>';
        
        if (count($products) > 5) {
            $html .= '<p class="more-products">...and ' . (count($products) - 5) . ' more products</p>';
        }
        
        return $html;
    }
    
    /**
     * Check if user has valid API key
     */
    private function has_valid_api_key() {
        $api_key = get_option('alloia_api_key', '');
        return !empty($api_key);
    }
    
    
    /**
     * Build the dashboard data structure (used for both real and demo data)
     */
    private function build_dashboard_data_structure($ai_visits, $brand_comparison, $prompts_analytics, $robots_analysis, $is_pro = false, $raw_ai_visits = null, $raw_prompts = null, $raw_leaderboard = null, $overview_data = null) {
        // Handle demo data format vs API data format
        if (!$is_pro && $overview_data) {
            // Demo data format
            return array(
                'is_pro' => $is_pro,
                // Legacy data for existing views (backward compatibility)
                'ai_bot_visits_7days' => $overview_data['ai_visits']['total'],
                'total_ai_visits' => $overview_data['ai_visits']['total'],
                'indexed_pages' => count($overview_data['top_prompts']),
                'brand_mentions' => array(
                    'current_week' => isset($raw_leaderboard['data'][0]['mentions']) ? $raw_leaderboard['data'][0]['mentions'] : 0,
                    'growth' => isset($raw_leaderboard['data'][0]['change_percentage']) ? $raw_leaderboard['data'][0]['change_percentage'] : '0%',
                    'competitors' => isset($raw_leaderboard['data']) ? array_map(function($company) {
                        return array(
                            'name' => isset($company['company']) ? $company['company'] : 'Unknown',
                            'mentions' => isset($company['mentions']) ? $company['mentions'] : 0,
                            'change' => isset($company['change_percentage']) ? $company['change_percentage'] : '0%'
                        );
                    }, $raw_leaderboard['data']) : array()
                ),
                'prompt_runs' => isset($raw_prompts['total_runs']) ? $raw_prompts['total_runs'] : 0,
                'avg_mentions' => '2.5',
                'active_prompts' => isset($raw_prompts['active_prompts']) ? $raw_prompts['active_prompts'] : 0,
                
                // New 5-view dashboard data structure
                'api_data' => array(
                    'ai_visits' => $raw_ai_visits,
                    'prompts' => $raw_prompts,
                    'leaderboard' => $raw_leaderboard,
                    'website' => AlloIA_Mock_Data::get_website()
                ),
                
                // Aggregated data for the new dashboard views
                'ai_visits' => $overview_data['ai_visits'],
                'brand_comparison' => $overview_data['brand_comparison'],
                'prompts_analytics' => $overview_data['prompts_analytics'],
                'top_pages' => $overview_data['top_pages'],
                'company_name' => 'Your Company' // Always use generic name for demo data
            );
        } else {
            // API data format
            return array(
                'is_pro' => $is_pro,
                // Legacy data for existing views (backward compatibility)
                'ai_bot_visits_7days' => $ai_visits['total'],
                'total_ai_visits' => $ai_visits['total'],
                'indexed_pages' => count($ai_visits['top_pages']),
                'brand_mentions' => array(
                    'current_week' => $brand_comparison['your_mentions'],
                    'growth' => $brand_comparison['growth'] . '%',
                    'competitors' => $brand_comparison['competitors']
                ),
                'prompt_runs' => $prompts_analytics['total_runs'],
                'avg_mentions' => $prompts_analytics['avg_mentions'],
                'active_prompts' => $prompts_analytics['active_prompts'],
                
                // New 5-view dashboard data structure (compatible format)
                'api_data' => array(
                    'ai_visits' => array(
                        'data' => array(
                            'total_visits' => $ai_visits['total'],
                            'recent_visits' => $ai_visits['daily_counts'],
                            'bot_types' => isset($ai_visits['bot_types']) ? $ai_visits['bot_types'] : array()
                        )
                    ),
                    'prompts' => array(
                        'data' => $prompts_analytics['raw_data']
                    ),
                    'leaderboard' => array(
                        'data' => $brand_comparison['leaderboard_data']
                    ),
                    'website' => array(
                        'competitors' => array_column($brand_comparison['competitors'], 'name')
                    )
                ),
                
                // Aggregated data for the new dashboard views
                'ai_visits' => $ai_visits,
                'brand_comparison' => $brand_comparison,
                'prompts_analytics' => $prompts_analytics,
                'top_pages' => $ai_visits['top_pages'],
                'company_name' => isset($brand_comparison['company_name']) ? $brand_comparison['company_name'] : 'Your Company'
            );
        }
    }
    
    /**
     * Transform AI visits data from API response to dashboard format
     */
    private function transform_ai_visits_data($api_data) {
        if (!isset($api_data['success']) || !$api_data['success']) {
            return array('total' => 0, 'daily_counts' => array(), 'top_pages' => array(), 'bot_types' => array());
        }
        
        $data = $api_data['data'];
        
        // Extract daily counts for the last 7 days
        $daily_counts = array();
        if (isset($data['recent_visits']) && is_array($data['recent_visits'])) {
            // Group visits by date and count them
            $visits_by_date = array();
            foreach ($data['recent_visits'] as $visit) {
                $date = wp_date('Y-m-d', strtotime($visit['timestamp']));
                if (!isset($visits_by_date[$date])) {
                    $visits_by_date[$date] = 0;
                }
                $visits_by_date[$date]++;
            }
            
            // Get last 7 days
            for ($i = 6; $i >= 0; $i--) {
                $date = wp_date('Y-m-d', strtotime("-$i days"));
                $daily_counts[] = isset($visits_by_date[$date]) ? $visits_by_date[$date] : 0;
            }
        } else {
            $daily_counts = array(0, 0, 0, 0, 0, 0, 0);
        }
        
        // Extract top pages from recent visits
        $top_pages = array();
        if (isset($data['recent_visits']) && is_array($data['recent_visits'])) {
            $pages_count = array();
            foreach ($data['recent_visits'] as $visit) {
                $page = isset($visit['page']) ? $visit['page'] : '/';
                if (!isset($pages_count[$page])) {
                    $pages_count[$page] = 0;
                }
                $pages_count[$page]++;
            }
            
            // Sort by count and take top 5
            arsort($pages_count);
            $top_pages = array_slice($pages_count, 0, 5, true);
        }
        
        return array(
            'total' => isset($data['total_visits']) ? $data['total_visits'] : 0,
            'daily_counts' => $daily_counts,
            'top_pages' => $top_pages,
            'bot_types' => isset($data['bot_types']) ? $data['bot_types'] : array()
        );
    }
    
    /**
     * Transform brand comparison data from API response to dashboard format
     */
    private function transform_brand_comparison_data($api_data) {
        if (!isset($api_data['success']) || !$api_data['success']) {
            return array('your_mentions' => 0, 'your_rank' => 0, 'growth' => 0, 'competitors' => array(), 'leaderboard_data' => array());
        }
        
        $leaderboard = isset($api_data['data']['leaderboard']) ? $api_data['data']['leaderboard'] : array();
        
        // Get company name from client info first, then from options, then default
        $client_info = get_option('alloia_client_info', array());
        $company_name = 'Your Company'; // Default fallback
        
        if (isset($client_info['name']) && !empty($client_info['name'])) {
            $company_name = $client_info['name'];
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AlloIA: Using company name from client info: ' . $company_name);
            }
        } elseif ($saved_company_name = get_option('alloia_company_name', '')) {
            $company_name = $saved_company_name;
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AlloIA: Using company name from settings: ' . $company_name);
            }
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AlloIA: No company name found, using default: ' . $company_name);
            }
        }
        
        // Find your company in the leaderboard
        $your_mentions = 0;
        $your_rank = 0;
        $growth = 0;
        $competitors = array();
        
        foreach ($leaderboard as $index => $company) {
            if (isset($company['company']) && $company['company'] === $company_name) {
                $your_mentions = isset($company['mentions']) ? $company['mentions'] : 0;
                $your_rank = isset($company['rank']) ? $company['rank'] : ($index + 1);
                // Calculate growth (mock for now, would need historical data)
                $growth = wp_rand(-5, 15); // Placeholder
            } else {
                // Add to competitors list
                $competitors[] = array(
                    'name' => isset($company['company']) ? $company['company'] : 'Unknown',
                    'mentions' => isset($company['mentions']) ? $company['mentions'] : 0,
                    'change' => isset($company['change_percentage']) ? $company['change_percentage'] : '0%'
                );
            }
        }
        
        return array(
            'your_mentions' => $your_mentions,
            'your_rank' => $your_rank,
            'growth' => $growth,
            'competitors' => $competitors,
            'leaderboard_data' => $leaderboard,
            'company_name' => $company_name
        );
    }
    
    /**
     * Transform prompts data from API response to dashboard format
     */
    private function transform_prompts_data($api_data) {
        if (!isset($api_data['success']) || !$api_data['success']) {
            return array('total_runs' => 0, 'avg_mentions' => '0', 'active_prompts' => 0, 'raw_data' => array());
        }
        
        $prompts = isset($api_data['data']['prompts']) ? $api_data['data']['prompts'] : array();
        
        $total_runs = 0;
        $total_mentions = 0;
        $active_prompts = 0;
        
        foreach ($prompts as $prompt) {
            if (isset($prompt['performance'])) {
                $mentions = isset($prompt['performance']['mentions']) ? $prompt['performance']['mentions'] : 0;
                $total_mentions += $mentions;
                $total_runs++;
                if ($mentions > 0) {
                    $active_prompts++;
                }
            }
        }
        
        $avg_mentions = $total_runs > 0 ? round($total_mentions / $total_runs, 1) : 0;
        
        return array(
            'total_runs' => $total_runs,
            'avg_mentions' => (string)$avg_mentions,
            'active_prompts' => $active_prompts,
            'raw_data' => $prompts
        );
    }
    
    /**
     * Transform robots data from API response to dashboard format
     */
    private function transform_robots_data($api_data) {
        if (!isset($api_data['success']) || !$api_data['success']) {
            return array('status' => 'unknown', 'score' => 0);
        }
        
        $data = isset($api_data['data']) ? $api_data['data'] : array();
        $score = isset($data['score']) ? $data['score'] : 0;
        
        $status = 'good';
        if ($score < 50) {
            $status = 'poor';
        } elseif ($score < 75) {
            $status = 'fair';
        }
        
        return array(
            'status' => $status,
            'score' => $score
        );
    }
    
    /**
     * Sync company settings to AlloIA API
     */
    private function sync_company_settings_to_api($company_name, $website_description, $business_category, $contact_email) {
        if (!$this->api_client) {
            return;
        }
        
        try {
            // For now, we'll just log this. In the future, we can add a specific API endpoint
            // to update company settings on the AlloIA side
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AlloIA Settings: Company settings updated - ' . $company_name);
            }
            
            // TODO: Implement API call when endpoint becomes available
            // $this->api_client->update_company_settings($company_name, $website_description, $business_category, $contact_email);
            
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AlloIA Settings: Failed to sync company settings - ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Sync competitor settings to AlloIA API
     */
    private function sync_competitors_to_api($competitors) {
        if (!$this->api_client) {
            return;
        }
        
        try {
            // For now, we'll just log this. In the future, we can add a specific API endpoint
            // to update competitor tracking settings on the AlloIA side
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AlloIA Settings: Competitor settings updated - ' . implode(', ', $competitors));
            }
            
            // TODO: Implement API call when endpoint becomes available
            // $this->api_client->update_competitors($competitors);
            
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AlloIA Settings: Failed to sync competitor settings - ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Generate basic llms.txt content when API is unavailable
     */
    private function generate_basic_llms_txt($site_url) {
        $site_name = get_bloginfo('name');
        $site_description = get_bloginfo('description');
        
        $content = "# LLMs.txt for {$site_name}\n";
        $content .= "# Generated by AlloIA WooCommerce Plugin\n\n";
        
        if ($site_description) {
            $content .= "# Site Description: {$site_description}\n\n";
        }
        
        $content .= "# Site URL: {$site_url}\n";
        $content .= "# Generated: " . wp_date('Y-m-d H:i:s') . "\n\n";
        
        $content .= "# AI Training Data Sources\n";
        $content .= "llm-graph: {$site_url}/wp-json/wp/v2/posts\n";
        $content .= "llm-graph: {$site_url}/wp-json/wp/v2/pages\n";
        
        // Add WooCommerce specific endpoints if WooCommerce is active
        if (class_exists('WooCommerce')) {
            $content .= "llm-graph: {$site_url}/wp-json/wc/v3/products\n";
            $content .= "llm-graph: {$site_url}/wp-json/wc/v3/categories\n";
        }
        
        $content .= "\n# Sitemap\n";
        $content .= "llm-sitemap: {$site_url}/sitemap.xml\n";
        
        $content .= "\n# Contact Information\n";
        $content .= "llm-contact: {$site_url}/contact\n";
        
        return $content;
    }
} 