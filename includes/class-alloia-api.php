<?php
/**
 * AlloIA Unified API Client for WooCommerce plugin
 * 
 * @package AlloIA_WooCommerce
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AlloIA Unified API Client Class
 * 
 * Handles all API communication with AlloIA.io services
 * Consolidates functionality from AlloIA_API_Client and AlloIA_Website_API
 */
class AlloIA_API {
    
    /**
     * API base URL - AlloIA.io service
     */
    private $base_url = 'https://www.alloia.io/api/v1';
    
    /**
     * API version
     */
    private $api_version = 'v1';
    
    /**
     * API key for authentication
     */
    private $api_key;
    
    
    /**
     * Constructor
     * 
     * @param string $api_key The API key for authentication
     */
    public function __construct($api_key = null) {
        // Check for encrypted API key first, then fallback to regular API key
        if ($api_key === null) {
            $this->api_key = get_option('alloia_api_key_encrypted', '');
            if (empty($this->api_key)) {
                $this->api_key = get_option('alloia_api_key', '');
            }
        } else {
            $this->api_key = $api_key;
        }
        
        // Set base URL from options if available (for development/testing)
        $custom_base_url = get_option('alloia_api_base_url', '');
        if (!empty($custom_base_url)) {
            // Ensure custom base URL includes /v1 if not present
            $custom_base_url = rtrim($custom_base_url, '/');
            if (substr($custom_base_url, -3) !== '/v1') {
                $custom_base_url .= '/v1';
            }
            $this->base_url = $custom_base_url;
        }
    }
    
    /**
     * Make an API request to AlloIA.io
     * 
     * @param string $endpoint The API endpoint
     * @param string $method HTTP method (GET, POST, PUT, DELETE)
     * @param array $data Request data for POST/PUT requests
     * @param array $custom_headers Additional headers to send with request
     * @return array Response data
     * @throws Exception On API errors
     */
    private function make_request($endpoint, $method = 'GET', $data = null, $custom_headers = array()) {
        
        // Build full URL
        $url = $this->base_url . $endpoint;
        
        // Prepare default headers
        $headers = array(
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type' => 'application/json',
            'User-Agent' => 'AlloIA-WooCommerce-Plugin/' . ALLOIA_VERSION,
            'Accept' => 'application/json'
        );
        
        // Merge custom headers
        if (!empty($custom_headers)) {
            $headers = array_merge($headers, $custom_headers);
        }
        
        // Prepare request arguments
        $args = array(
            'method' => strtoupper($method),
            'headers' => $headers,
            'timeout' => 30,
            'sslverify' => true,
            'redirection' => 5 // Follow redirects (WordPress default is 5)
        );
        
        // Add body for POST/PUT requests only
        if ($data && in_array(strtoupper($method), array('POST', 'PUT'))) {
            $args['body'] = json_encode($data);
        }
        
        // Make the request
        $response = wp_remote_request($url, $args);
        
        // Handle WordPress HTTP API errors
        if (is_wp_error($response)) {
            throw new Exception('HTTP request failed: ' . esc_html($response->get_error_message()));
        }
        
        // Get response code and body
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        
        // Parse response body - decode JSON to associative array
        $response_data = json_decode($response_body, true);
        
        // Handle non-JSON responses
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from API: ' . json_last_error_msg());
        }
        
        // Ensure response_data is an array (not an object)
        if (!is_array($response_data)) {
            // If somehow we got an object, convert it
            if (is_object($response_data)) {
                $response_data = json_decode(json_encode($response_data), true);
            } else {
                throw new Exception('API response is not a valid array or object');
            }
        }
        
        // Log response structure for debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('AlloIA API Response Type: ' . gettype($response_data));
            error_log('AlloIA API Response Keys: ' . implode(', ', array_keys($response_data)));
        }
        
        // Handle error responses
        if ($response_code >= 400) {
            $error_message = isset($response_data['error']['message']) 
                ? $response_data['error']['message'] 
                : 'API request failed with status ' . $response_code;
            
            // Log detailed error information for debugging
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("AlloIA API Error: URL: $url, Status: $response_code, Response: " . $response_body);
            }
            
            throw new Exception(esc_html($error_message));
        }
        
        return $response_data;
    }
    
    /**
     * Make a simple request for website API functionality
     * Returns array with code, data, and raw response for backward compatibility
     */
    private function make_simple_request($endpoint, $method = 'GET', $data = null) {
        try {
            $response_data = $this->make_request($endpoint, $method, $data);
            return array(
                'code' => 200,
                'data' => $response_data,
                'raw' => json_encode($response_data)
            );
        } catch (Exception $e) {
            return new WP_Error('api_error', $e->getMessage());
        }
    }
    
    // ===========================================
    // AUTHENTICATION & VALIDATION
    // ===========================================
    
    /**
     * Validate API key and get client information
     * 
     * API Response Structure:
     * Success: {"success": true, "valid": true, "client": {"subscription_status": "active", ...}}
     * Error: {"success": false, "error": {"code": "...", "message": "..."}}
     * 
     * @return array Client validation response as array (decoded from JSON)
     * @throws Exception On validation failure (network errors, invalid JSON, etc.)
     */
    public function validate_api_key() {
        // Make request - wp_remote_request returns array, make_request decodes JSON to array
        $response = $this->make_request('/clients/validate', 'GET');
        
        // Response is already an array from json_decode($response_body, true)
        // Verify it has the expected structure
        if (!is_array($response)) {
            throw new Exception('Invalid API response format - expected array');
        }
        
        // If API returned error structure, ensure we have success field
        if (!isset($response['success']) && isset($response['error'])) {
            $response['success'] = false;
        }
        
        // If success is true but valid is missing, infer it from success
        if (isset($response['success']) && $response['success'] === true && !isset($response['valid'])) {
            $response['valid'] = true;
        }
        
        return $response;
    }
    
    /**
     * Validate domain for Knowledge Graph sync
     * 
     * EMERGENCY HOTFIX (2025-12-05): Domain validation temporarily bypassed
     * - Only validates API key
     * - Domain ownership/validation must be verified manually in client portal
     * - Technical debt tracked in story: HOTFIX-2025-12-05
     * 
     * @param string $domain Optional domain to validate (defaults to current site domain)
     * @return array Validation result with 'valid' boolean and details
     * @throws Exception On API failure
     */
    public function validate_domain_for_sync($domain = null) {
        try {
            // Step 1: Validate API key only
            $client_data = $this->validate_api_key();
            
            // HOTFIX DEBUG: Log what we got from validate_api_key
            error_log("HOTFIX DEBUG: validate_api_key returned: " . json_encode($client_data));
            
            // Convert to boolean explicitly (API may return integers 0/1)
            $success = isset($client_data['success']) ? (bool)$client_data['success'] : false;
            $valid = isset($client_data['valid']) ? (bool)$client_data['valid'] : false;
            
            error_log("HOTFIX DEBUG: success=$success, valid=$valid");
            
            if (!$success || !$valid) {
                error_log("HOTFIX DEBUG: API key validation FAILED - returning false");
                return array(
                    'valid' => false,
                    'error' => isset($client_data['error']['message']) 
                        ? $client_data['error']['message'] 
                        : 'Invalid API key',
                    'domain' => $domain,
                    'checks' => array(
                        'api_key_valid' => false,
                        'domain_associated' => false,
                        'domain_validated' => false
                    )
                );
            }
            
            error_log("HOTFIX DEBUG: API key validation PASSED - will bypass domain checks");
            
            // Step 2: Extract domain for logging/tracking
            if (empty($domain)) {
                $home_url = home_url();
                $domain = wp_parse_url($home_url, PHP_URL_HOST);
                
                // Fallback: if wp_parse_url fails, try manual extraction
                if (empty($domain) && !empty($home_url)) {
                    $parsed = parse_url($home_url);
                    $domain = isset($parsed['host']) ? $parsed['host'] : '';
                }
            }
            
            $domain = trim($domain);
            if (empty($domain)) {
                $domain = 'unknown';
            }
            
            // Step 3: EMERGENCY BYPASS - Trust API key holder
            // Domain validation will be handled manually in client portal
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("AlloIA Domain Validation BYPASS - Domain: $domain, Client ID: " . $client_data['client']['id']);
            }
            
            return array(
                'valid' => true,
                'domain' => $domain,
                'client_id' => $client_data['client']['id'],
                'checks' => array(
                    'api_key_valid' => true,
                    'domain_associated' => true,  // Bypassed
                    'domain_validated' => true     // Bypassed
                ),
                'bypass_notice' => '⚠️ Domain validation bypassed - Manual verification required in client portal'
            );
            
        } catch (Exception $e) {
            return array(
                'valid' => false,
                'error' => 'API validation failed: ' . $e->getMessage(),
                'domain' => $domain ?? 'unknown',
                'checks' => array(
                    'api_key_valid' => false,
                    'domain_associated' => false,
                    'domain_validated' => false
                )
            );
        }
    }
    
    /**
     * Get health check status
     * 
     * @return array Health check response
     * @throws Exception On API failure
     */
    public function health_check() {
        // Use documented client validation endpoint as health check
        // since /health endpoint doesn't exist according to documentation
        try {
            $validation = $this->validate_api_key();
            return array(
                'status' => 'healthy',
                'api_connected' => true,
                'client_valid' => $validation['valid'] ?? false,
                'message' => 'API connection successful via client validation'
            );
        } catch (Exception $e) {
            return array(
                'status' => 'unhealthy',
                'api_connected' => false,
                'client_valid' => false,
                'message' => 'API connection failed: ' . $e->getMessage()
            );
        }
    }
    
    // ===========================================
    // ANALYTICS & DASHBOARD
    // ===========================================
    
    /**
     * Get AI bot visits analytics
     * 
     * @param array $params Query parameters
     * @return array AI visits data
     * @throws Exception On API failure
     */
    public function get_ai_visits($params = array()) {
        $endpoint = '/analytics/ai-visits';
        if (!empty($params)) {
            $endpoint .= '?' . http_build_query($params);
        }
        return $this->make_request($endpoint, 'GET');
    }
    
    /**
     * Get robots.txt scan results
     * 
     * @return array Robots.txt scan data
     * @throws Exception On API failure
     */
    public function get_robots_scan() {
        return $this->make_request('/robots/scan-results', 'GET');
    }
    
    /**
     * Get prompt management data
     * 
     * @param array $params Query parameters
     * @return array Prompt data
     * @throws Exception On API failure
     */
    public function get_prompts($params = array()) {
        $endpoint = '/prompts';
        if (!empty($params)) {
            $endpoint .= '?' . http_build_query($params);
        }
        return $this->make_request($endpoint, 'GET');
    }
    
    /**
     * Get prompt leaderboard
     * 
     * @return array Leaderboard data
     * @throws Exception On API failure
     */
    public function get_prompt_leaderboard() {
        return $this->make_request('/prompts/leaderboard', 'GET');
    }
    
    // ===========================================
    // SUBSCRIPTION MANAGEMENT
    // ===========================================
    
    /**
     * Get subscription status via client validation (documented approach)
     * Note: Subscription status is included in /clients/validate response
     * 
     * @return array Subscription status data from client validation
     * @throws Exception On API failure
     */
    public function get_subscription_status() {
        $client_data = $this->validate_api_key();
        
        if (isset($client_data['client'])) {
            return array(
                'status' => $client_data['client']['subscription_status'] ?? 'inactive',
                'plan' => $client_data['client']['subscription_tier'] ?? null,
                'subscription_plan' => $client_data['client']['subscription_plan'] ?? $client_data['client']['subscription_tier'] ?? null,
                'features' => $client_data['client']['features'] ?? array(),
                'limits' => $client_data['client']['limits'] ?? array(),
                'is_active' => ($client_data['client']['subscription_status'] ?? '') === 'active',
                'client' => $client_data['client'] // Pass through the full client data
            );
        }
        
        return array('status' => 'inactive', 'plan' => null, 'is_active' => false);
    }
    
    /**
     * Get usage information via client validation (documented approach)
     * Note: Usage data is included in /clients/validate response under limits
     * 
     * @return array Usage data from client validation
     * @throws Exception On API failure
     */
    public function get_usage_info() {
        $client_data = $this->validate_api_key();
        
        if (isset($client_data['client']['limits'])) {
            return array(
                'monthly_queries' => $client_data['client']['limits']['monthly_queries'] ?? 0,
                'current_usage' => $client_data['client']['limits']['current_usage'] ?? 0,
                'max_products' => $client_data['client']['limits']['max_products'] ?? 0
            );
        }
        
        return array('monthly_queries' => 0, 'current_usage' => 0, 'max_products' => 0);
    }
    
    /**
     * Create checkout session for subscription (redirect to documented billing page)
     * Note: According to docs, redirect to alloia.ai billing page instead of API call
     * 
     * @param array $checkout_data Checkout information
     * @return array Redirect URL for alloia.ai billing page
     */
    public function create_checkout_session($checkout_data) {
        // According to documentation, redirect to alloia.ai billing page
        $entities = $checkout_data['entities'] ?? 0;
        $domain = $checkout_data['domain'] ?? '';
        $redirect = $checkout_data['redirect'] ?? 'wp-admin/plugins.php';
        $plugin_version = $checkout_data['plugin_version'] ?? '1.0.0';
        
        $billing_url = "https://alloia.ai/dashboard/billing?plan=graph_auto&entities={$entities}&domain={$domain}&redirect={$redirect}&plugin_version={$plugin_version}";
        
        return array(
            'success' => true,
            'redirect_url' => $billing_url,
            'message' => 'Redirect to billing page for subscription'
        );
    }
    
    /**
     * Cancel subscription (redirect to documented customer portal)
     * Note: According to docs, use alloia.ai customer portal instead of API call
     * 
     * @return array Redirect URL for customer portal
     */
    public function cancel_subscription() {
        // According to documentation, use alloia.ai customer portal
        $portal_url = "https://alloia.ai/api/stripe/customer-portal";
        
        return array(
            'success' => true,
            'redirect_url' => $portal_url,
            'message' => 'Redirect to customer portal for subscription management'
        );
    }
    
    // ===========================================
    // KNOWLEDGE GRAPH & PRODUCTS
    // ===========================================
    
    /**
     * Submit product to knowledge graph using documented WooCommerce ingest endpoint
     * Note: /products endpoint not documented, use /ingest/woocommerce instead
     * 
     * @param array $product_data Product information
     * @return array Submission response
     * @throws Exception On API failure
     */
    public function submit_product($product_data) {
        // Use documented endpoint instead of undocumented /products
        return $this->bulk_ingest_woocommerce(array($product_data));
    }
    
    /**
     * Bulk ingest WooCommerce products using documented batch ingest endpoint
     * 
     * @param array $products Array of WooCommerce product data
     * @return array Ingest response
     * @throws Exception On API failure
     */
    public function bulk_ingest_woocommerce($products) {
        $data = array(
            'products' => $products
        );
        
        // Add WooCommerce platform header as per documentation
        $headers = array(
            'X-Platform' => 'woocommerce'
        );
        
        try {
            return $this->make_request('/ingest', 'POST', $data, $headers);
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Bulk ingest WooCommerce failed: ' . $e->getMessage());
            }
            throw new Exception('Failed to ingest WooCommerce products: ' . esc_html($e->getMessage()));
        }
    }
    
    /**
     * Update product in knowledge graph
     * Note: Individual product update endpoints not documented, use bulk ingest instead
     * 
     * @param string $product_id Product identifier (for reference only)
     * @param array $product_data Updated product information
     * @return array Update response via bulk ingest
     * @throws Exception On API failure
     */
    public function update_product($product_id, $product_data) {
        // Use documented bulk ingest endpoint for updates
        return $this->bulk_ingest_woocommerce(array($product_data));
    }
    
    /**
     * Delete product from knowledge graph
     * Note: Individual product deletion endpoints not documented
     * 
     * @param string $product_id Product identifier
     * @return array Information about deletion limitation
     */
    public function delete_product($product_id) {
        // Product deletion endpoints not documented in API
        return array(
            'success' => false,
            'message' => 'Individual product deletion not supported by documented API. Use bulk re-sync instead.',
            'recommendation' => 'Re-sync all products to update knowledge graph'
        );
    }
    
    /**
     * Get product status
     * Note: Individual product status endpoints not documented
     * 
     * @param string $product_id Product identifier
     * @return array Information about status limitation
     */
    public function get_product_status($product_id) {
        // Product status endpoints not documented in API
        return array(
            'success' => false,
            'message' => 'Individual product status not supported by documented API.',
            'recommendation' => 'Use client validation endpoint for general status information'
        );
    }
    
    /**
     * Get export status
     * Note: Export status endpoints not documented
     * 
     * @param string $export_id Export identifier
     * @return array Information about export status limitation
     */
    public function get_export_status($export_id) {
        // Export status endpoints not documented in API
        return array(
            'success' => false,
            'message' => 'Export status endpoints not supported by documented API.',
            'recommendation' => 'Use client validation endpoint for subscription and usage limits'
        );
    }
    
    /**
     * Search products in knowledge graph
     * 
     * @param string $search_keyword Search keyword
     * @param int $limit Maximum results to return
     * @param int $offset Results offset for pagination
     * @return array Search results
     * @throws Exception On API failure
     */
    public function search_products($search_keyword = '', $limit = 50, $offset = 0) {
        $params = array();
        
        if (!empty($search_keyword)) {
            $params['search'] = $search_keyword;
        }
        
        if ($limit > 0) {
            $params['limit'] = $limit;
        }
        
        if ($offset > 0) {
            $params['offset'] = $offset;
        }
        
        $query_string = !empty($params) ? '?' . http_build_query($params) : '';
        
        return $this->make_request('/products' . $query_string, 'GET');
    }
    
    /**
     * Get enhanced product data from LLM endpoint
     * 
     * @param string $product_id Product identifier
     * @return array Enhanced product data
     * @throws Exception On API failure
     */
    public function get_enhanced_product($product_id) {
        return $this->make_request('/llm/products/' . urlencode($product_id), 'GET');
    }
    
    /**
     * Get basic product data
     * 
     * @param string $product_id Product identifier
     * @return array Basic product data
     * @throws Exception On API failure
     */
    public function get_product($product_id) {
        return $this->make_request('/products/' . urlencode($product_id), 'GET');
    }
    
    // ===========================================
    // WEBSITE REGISTRATION & TRACKING
    // ===========================================
    
    /**
     * Register website with AlloIA
     * 
     * @param string $site_url Website URL
     * @param string $domain Website domain
     * @return array Registration response
     */
    public function register_site($site_url, $domain) {
        $payload = array(
            'url' => $site_url,
            'domain' => $domain,
        );
        return $this->make_simple_request('/websites', 'POST', $payload);
    }
    
    /**
     * Get latest robots scan for website
     * 
     * @param string $website_id Website ID
     * @return array Robots scan data
     */
    public function get_latest_robots_scan($website_id) {
        $endpoint = '/websites/' . rawurlencode($website_id) . '/robots-scans/latest';
        return $this->make_simple_request($endpoint, 'GET');
    }
    
    /**
     * Provision tracking for website
     * 
     * @param string $website_id Website ID
     * @return array Tracking provision response
     */
    public function provision_tracking($website_id) {
        $payload = array('websiteId' => $website_id);
        return $this->make_simple_request('/tracking/provision', 'POST', $payload);
    }
    
    // ===========================================
    // UTILITY & INFO METHODS
    // ===========================================
    
    /**
     * Get API client information
     * 
     * @return array Client information
     */
    public function get_client_info() {
        return array(
            'base_url' => $this->base_url,
            'api_version' => $this->api_version,
            'has_api_key' => !empty($this->api_key),
            'api_key_preview' => !empty($this->api_key) ? substr($this->api_key, 0, 6) . '...' : 'Not set'
        );
    }
    
    /**
     * Test API connection and endpoints
     * 
     * @return array Test results
     */
    public function test_api_connection() {
        $results = array(
            'base_url' => $this->base_url,
            'api_key_set' => !empty($this->api_key),
            'tests' => array()
        );
        
        
        // Test 1: Client Validation (Core Authentication)
        if (!empty($this->api_key)) {
            try {
                $validation = $this->make_request('/clients/validate', 'GET', null, array());
                $results['tests']['client_validation'] = array(
                    'status' => 'success',
                    'response' => $validation
                );
            } catch (Exception $e) {
                $results['tests']['client_validation'] = array(
                    'status' => 'error',
                    'error' => $e->getMessage()
                );
            }
        } else {
            $results['tests']['client_validation'] = array(
                'status' => 'skipped',
                'reason' => 'No API key provided'
            );
        }
        
        // Test 2: Subscription Status (via documented client validation approach)
        try {
            $subscription = $this->get_subscription_status(); // Uses client validation internally
            $results['tests']['subscription_status'] = array(
                'status' => 'success',
                'response' => array(
                    'status' => $subscription['status'],
                    'plan' => $subscription['plan'],
                    'is_active' => $subscription['is_active'],
                    'message' => 'Retrieved via documented client validation endpoint'
                )
            );
        } catch (Exception $e) {
            $results['tests']['subscription_status'] = array(
                'status' => 'error',
                'error' => $e->getMessage()
            );
        }
        
        // Test 3: Subscription Usage (via documented client validation approach)
        try {
            $usage = $this->get_usage_info(); // Uses client validation internally
            $results['tests']['subscription_usage'] = array(
                'status' => 'success',
                'response' => array(
                    'monthly_queries' => $usage['monthly_queries'],
                    'current_usage' => $usage['current_usage'],
                    'max_products' => $usage['max_products'],
                    'message' => 'Retrieved via documented client validation endpoint'
                )
            );
        } catch (Exception $e) {
            $results['tests']['subscription_usage'] = array(
                'status' => 'error',
                'error' => $e->getMessage()
            );
        }
        
        // Test 4: Health Check (via documented client validation)
        try {
            $health = $this->health_check(); // Uses client validation internally
            $results['tests']['health_check'] = array(
                'status' => $health['status'] === 'healthy' ? 'success' : 'info',
                'response' => array(
                    'status' => $health['status'],
                    'api_connected' => $health['api_connected'],
                    'client_valid' => $health['client_valid'],
                    'message' => $health['message']
                )
            );
        } catch (Exception $e) {
            $results['tests']['health_check'] = array(
                'status' => 'error',
                'error' => $e->getMessage()
            );
        }
        
        // Test 5: Analytics/AI Visits (if available)
        try {
            $analytics = $this->make_request('/analytics/ai-visits', 'GET', null, array());
            $results['tests']['analytics_ai_visits'] = array(
                'status' => 'success',
                'response' => $analytics
            );
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Endpoint not found') !== false) {
                $results['tests']['analytics_ai_visits'] = array(
                    'status' => 'info',
                    'message' => 'Analytics endpoint not available or requires specific subscription'
                );
            } else {
                $results['tests']['analytics_ai_visits'] = array(
                    'status' => 'error',
                    'error' => $e->getMessage()
                );
            }
        }
        
        // Test 6: Knowledge Graph Connectivity (Correct Endpoint)
        try {
            // Test with correct endpoint and headers per documentation
            $test_data = array(
                'products' => array(
                    array(
                        'name' => 'Test Product',
                        'description' => 'Test product for API validation',
                        'sku' => 'TEST-' . time(),
                        'price' => 99.99,
                        'category' => 'Test Category'
                    )
                )
            );
            $headers = array('X-Platform' => 'woocommerce');
            $kg_response = $this->make_request('/ingest', 'POST', $test_data, $headers);
            $results['tests']['knowledge_graph_ingest'] = array(
                'status' => 'success',
                'response' => 'Knowledge Graph endpoint accessible'
            );
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Endpoint not found') !== false) {
                $results['tests']['knowledge_graph_ingest'] = array(
                    'status' => 'info',
                    'message' => 'Knowledge Graph endpoint exists but may require valid product data'
                );
            } else {
                $results['tests']['knowledge_graph_ingest'] = array(
                    'status' => 'error',
                    'error' => $e->getMessage()
                );
            }
        }
        
        return $results;
    }
}
