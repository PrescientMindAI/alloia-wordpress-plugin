<?php
/**
 * AlloIA Knowledge Graph Exporter for WooCommerce plugin
 * 
 * @package AlloIA_WooCommerce
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AlloIA Knowledge Graph Exporter Class
 * 
 * Handles exporting WooCommerce products to AlloIA Knowledge Graph
 */
class AlloIA_Knowledge_Graph_Exporter {
    
    /**
     * API client instance
     */
    private $api_client;
    
    /**
     * Batch size for processing
     */
    private $batch_size = 50;
    
    /**
     * Constructor
     * 
     * @param AlloIA_API $api_client API client instance
     */
    public function __construct($api_client = null) {
        $this->api_client = $api_client ?: new AlloIA_API();
        
        // Get batch size from options
        $this->batch_size = get_option('alloia_export_batch_size', 50);
    }
    
    /**
     * Export products to knowledge graph via AlloIA.io relay
     * 
     * @param array $filters Export filters
     * @param bool $background Whether to run in background
     * @return array Export results
     */
    public function export_products($filters = array(), $background = false) {
        // Validate domain for sync
        $domain_validation = $this->api_client->validate_domain_for_sync();
        
        if (!$domain_validation['valid']) {
            $error_message = isset($domain_validation['error']) ? $domain_validation['error'] : 'Domain validation failed';
            $dashboard_url = 'https://alloia.io/dashboard/domain-settings';
            
            // Story 1.2 (AC #7): User-friendly error messages with dashboard links
            if (strpos($error_message, 'Invalid API key') !== false) {
                $error_message = 'Invalid API key. Please check your AlloIA API key in plugin settings.';
            } else {
                // Default domain validation error with dashboard link
                $error_message = 'Domain validation required. Please verify your domain in the AlloIA dashboard: ' . $dashboard_url;
            }
            
            throw new Exception($error_message);
        }
        
        // Extract products from WooCommerce
        $products = $this->extract_woocommerce_products($filters);
        
        if (empty($products)) {
            return array(
                'success' => true,
                'message' => 'No products found to export',
                'exported_count' => 0,
                'total_count' => 0
            );
        }
        
        // Convert to knowledge graph format
        $graph_data = $this->convert_products_to_graph_format($products);
        
        // Validate export data
        $validation_result = $this->validate_export_data($graph_data);
        if (!$validation_result['valid']) {
            throw new Exception('Export data validation failed: ' . esc_html(implode(', ', $validation_result['errors'])));
        }
        
        if ($background) {
            // Schedule background export
            return $this->schedule_background_export($graph_data, $filters);
        } else {
            // Export immediately via AlloIA.io relay
            return $this->export_products_immediately($graph_data);
        }
    }
    
    /**
     * Extract WooCommerce products
     * 
     * @param array $filters Export filters
     * @return array WooCommerce products
     */
    public function extract_woocommerce_products($filters = array()) {
        // Start with all products - default to include everything
        $post_statuses = array('publish');
        
        // Include inactive products if requested
        if (!empty($filters['include_inactive'])) {
            $post_statuses = array_merge($post_statuses, array('draft', 'private', 'pending'));
        }
        
        $args = array(
            'post_type' => 'product',
            'post_status' => $post_statuses,
            'posts_per_page' => -1,
            'meta_query' => array(),
            'tax_query' => array()
        );
        
        // Exclude categories if specified
        if (!empty($filters['exclude_category']) && is_array($filters['exclude_category'])) {
            $args['tax_query'][] = array(
                'taxonomy' => 'product_cat',
                'field' => 'term_id',
                'terms' => $filters['exclude_category'],
                'operator' => 'NOT IN'
            );
        }
        
        $posts = get_posts($args);
        $products = array();
        
        // Debug: Log the query args and results
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('AlloIA Product Query Args: ' . print_r($args, true));
            error_log('AlloIA Found Posts: ' . count($posts));
        }
        
        foreach ($posts as $post) {
            $wc_product = wc_get_product($post->ID);
            if ($wc_product) {
                // Apply product type filters
                $should_include = true;
                
                // HOTFIX DEBUG: Log product details
                error_log("PRODUCT DEBUG - ID: {$post->ID}, Name: {$wc_product->get_name()}");
                error_log("PRODUCT DEBUG - Is virtual: " . ($wc_product->is_virtual() ? 'YES' : 'NO'));
                error_log("PRODUCT DEBUG - Is in stock: " . ($wc_product->is_in_stock() ? 'YES' : 'NO'));
                error_log("PRODUCT DEBUG - Filter include_virtual: " . (isset($filters['include_virtual']) ? $filters['include_virtual'] : 'NOT SET'));
                error_log("PRODUCT DEBUG - Filter include_out_of_stock: " . (isset($filters['include_out_of_stock']) ? $filters['include_out_of_stock'] : 'NOT SET'));
                
                // Check virtual products filter
                if (empty($filters['include_virtual']) && $wc_product->is_virtual()) {
                    error_log("PRODUCT DEBUG - EXCLUDED: Virtual product and include_virtual not set");
                    $should_include = false;
                }
                
                // Check out of stock products filter
                if (empty($filters['include_out_of_stock']) && !$wc_product->is_in_stock()) {
                    error_log("PRODUCT DEBUG - EXCLUDED: Out of stock and include_out_of_stock not set");
                    $should_include = false;
                }
                
                error_log("PRODUCT DEBUG - Should include: " . ($should_include ? 'YES' : 'NO'));
                
                if ($should_include) {
                    $products[] = $wc_product;
                }
            } else {
                // Debug: Log failed product creation
                error_log('AlloIA: Failed to create WC_Product for post ID: ' . $post->ID);
            }
        }
        
        // Debug: Log final product count
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('AlloIA Final Products Count: ' . count($products));
        }
        
        return $products;
    }
    
    /**
     * Convert WooCommerce products to knowledge graph format
     * 
     * @param array $wc_products WooCommerce products
     * @return array Knowledge graph data
     */
    private function convert_products_to_graph_format($wc_products) {
        $graph_data = array(
            'nodes' => array(),
            'edges' => array()
        );
        
        foreach ($wc_products as $wc_product) {
            $node = $this->convert_product_to_node($wc_product);
            $graph_data['nodes'][] = $node;
            
            // Add category relationships
            $category_edges = $this->create_category_relationships($wc_product);
            $graph_data['edges'] = array_merge($graph_data['edges'], $category_edges);
            
            // Add manufacturer relationships
            $manufacturer_edges = $this->create_manufacturer_relationships($wc_product);
            $graph_data['edges'] = array_merge($graph_data['edges'], $manufacturer_edges);
        }
        
        return $graph_data;
    }
    
    /**
     * Convert single product to knowledge graph node
     * 
     * @param WC_Product $wc_product WooCommerce product
     * @return array Knowledge graph node
     */
    private function convert_product_to_node($wc_product) {
        $product_id = $wc_product->get_id();
        $product_type = $wc_product->get_type();
        
        $node = array(
            'id' => 'product-' . $product_id,
            'type' => 'product',
            'labels' => array(
                $wc_product->get_name(),
                $this->get_product_categories($wc_product),
                $this->extract_manufacturer($wc_product)
            ),
            'properties' => array(
                'clientId' => wp_parse_url(home_url(), PHP_URL_HOST),
                'name' => $wc_product->get_name(),
                'description' => $wc_product->get_description(),
                'short_description' => $wc_product->get_short_description(),
                'sku' => $wc_product->get_sku(),
                'category' => $this->get_product_categories($wc_product),
                'price' => $wc_product->get_price(),
                'regular_price' => $wc_product->get_regular_price(),
                'sale_price' => $wc_product->get_sale_price(),
                'currency' => get_woocommerce_currency(),
                'availability' => $wc_product->is_in_stock(),
                'stock_quantity' => $wc_product->get_stock_quantity(),
                'manufacturer' => $this->extract_manufacturer($wc_product),
                'model' => $wc_product->get_sku(),
                'images' => $this->get_product_images($wc_product),
                'tags' => $this->get_product_tags($wc_product),
                'attributes' => $this->get_product_attributes($wc_product),
                'dimensions' => array(
                    'length' => $wc_product->get_length(),
                    'width' => $wc_product->get_width(),
                    'height' => $wc_product->get_height(),
                    'weight' => $wc_product->get_weight()
                ),
                'createdAt' => $wc_product->get_date_created()->format('c'),
                'updatedAt' => $wc_product->get_date_modified()->format('c'),
                'woocommerce_id' => $product_id,
                'permalink' => get_permalink($product_id),
                'slug' => $this->extract_url_slug($product_id),
                'product_type' => $product_type
            )
        );
        
        // Add variants for variable products
        if ($product_type === 'variable') {
            $variants = $this->extract_product_variants($wc_product);
            if (!empty($variants)) {
                $node['properties']['variants'] = $variants;
                $node['properties']['has_variations'] = true;
                $node['properties']['variation_count'] = count($variants);
                
                // Calculate price range from variants
                $prices = array_column($variants, 'price');
                if (!empty($prices)) {
                    $node['properties']['price_range'] = array(
                        'min' => min($prices),
                        'max' => max($prices),
                        'currency' => get_woocommerce_currency()
                    );
                }
            }
        }
        
        return $node;
    }
    
    /**
     * Extract URL slug from product permalink
     * 
     * @param int $product_id Product ID
     * @return string Product URL slug
     */
    private function extract_url_slug($product_id) {
        $permalink = get_permalink($product_id);
        if (!$permalink) {
            return '';
        }
        
        // Parse URL and get the path
        $path = parse_url($permalink, PHP_URL_PATH);
        if (!$path) {
            return '';
        }
        
        // Remove trailing slash and get the last segment (the slug)
        $path = rtrim($path, '/');
        $slug = basename($path);
        
        return $slug;
    }
    
    /**
     * Get product categories
     * 
     * @param WC_Product $wc_product WooCommerce product
     * @return array Product categories
     */
    private function get_product_categories($wc_product) {
        $categories = array();
        $category_ids = $wc_product->get_category_ids();
        
        foreach ($category_ids as $cat_id) {
            $term = get_term($cat_id, 'product_cat');
            if ($term && !is_wp_error($term)) {
                $categories[] = $term->name;
            }
        }
        
        return $categories;
    }
    
    /**
     * Extract manufacturer from product
     * 
     * @param WC_Product $wc_product WooCommerce product
     * @return string|null Manufacturer name
     */
    private function extract_manufacturer($wc_product) {
        // Try to get manufacturer from product attributes
        $attributes = $wc_product->get_attributes();
        
        foreach ($attributes as $attribute) {
            if (stripos($attribute->get_name(), 'manufacturer') !== false || 
                stripos($attribute->get_name(), 'brand') !== false) {
                $terms = wc_get_product_terms($wc_product->get_id(), $attribute->get_name());
                if (!empty($terms) && !is_wp_error($terms)) {
                    return $terms[0]->name;
                }
            }
        }
        
        // Try to get from product meta
        $manufacturer = get_post_meta($wc_product->get_id(), '_manufacturer', true);
        if (!empty($manufacturer)) {
            return $manufacturer;
        }
        
        return null;
    }
    
    /**
     * Get product images
     * 
     * @param WC_Product $wc_product WooCommerce product
     * @return array Product images (limited to 10 maximum)
     */
    private function get_product_images($wc_product) {
        $images = array();
        $max_images = 10; // API validation limit
        
        // Main product image - highest priority
        $main_image_id = $wc_product->get_image_id();
        if ($main_image_id) {
            $main_image_url = wp_get_attachment_image_url($main_image_id, 'full');
            if ($main_image_url) {
                $images[] = $main_image_url;
            }
        }
        
        // Gallery images - up to remaining slots
        $remaining_slots = $max_images - count($images);
        if ($remaining_slots > 0) {
            $gallery_ids = $wc_product->get_gallery_image_ids();
            
            // Debug: Log gallery image count for variable products
            if (defined('WP_DEBUG') && WP_DEBUG && count($gallery_ids) > $remaining_slots) {
                error_log(sprintf(
                    'AlloIA: Product %s (ID: %d) has %d gallery images, limiting to %d (max %d total)',
                    $wc_product->get_name(),
                    $wc_product->get_id(),
                    count($gallery_ids),
                    $remaining_slots,
                    $max_images
                ));
            }
            
            // Limit gallery images to remaining slots
            $gallery_ids_limited = array_slice($gallery_ids, 0, $remaining_slots);
            
            foreach ($gallery_ids_limited as $gallery_id) {
                $gallery_url = wp_get_attachment_image_url($gallery_id, 'full');
                if ($gallery_url) {
                    $images[] = $gallery_url;
                }
            }
        }
        
        return $images;
    }
    
    /**
     * Get product tags
     * 
     * @param WC_Product $wc_product WooCommerce product
     * @return array Product tags
     */
    private function get_product_tags($wc_product) {
        $tags = array();
        $tag_ids = $wc_product->get_tag_ids();
        
        foreach ($tag_ids as $tag_id) {
            $term = get_term($tag_id, 'product_tag');
            if ($term && !is_wp_error($term)) {
                $tags[] = $term->name;
            }
        }
        
        return $tags;
    }
    
    /**
     * Get product attributes
     * 
     * @param WC_Product $wc_product WooCommerce product
     * @return array Product attributes
     */
    private function get_product_attributes($wc_product) {
        $attributes = array();
        $product_attributes = $wc_product->get_attributes();
        
        foreach ($product_attributes as $attribute) {
            if ($attribute->is_taxonomy()) {
                $terms = wc_get_product_terms($wc_product->get_id(), $attribute->get_name());
                if (!empty($terms) && !is_wp_error($terms)) {
                    $attribute_values = array();
                    foreach ($terms as $term) {
                        $attribute_values[] = $term->name;
                    }
                    $attributes[$attribute->get_name()] = $attribute_values;
                }
            } else {
                $attributes[$attribute->get_name()] = $attribute->get_options();
            }
        }
        
        return $attributes;
    }
    
    /**
     * Create category relationships
     * 
     * @param WC_Product $wc_product WooCommerce product
     * @return array Category edges
     */
    private function create_category_relationships($wc_product) {
        $edges = array();
        $category_ids = $wc_product->get_category_ids();
        
        foreach ($category_ids as $cat_id) {
            $term = get_term($cat_id, 'product_cat');
            if ($term && !is_wp_error($term)) {
                $edges[] = array(
                    'source' => 'product-' . $wc_product->get_id(),
                    'target' => 'category-' . $cat_id,
                    'type' => 'BELONGS_TO',
                    'properties' => array(
                        'category_name' => $term->name,
                        'category_slug' => $term->slug
                    )
                );
            }
        }
        
        return $edges;
    }
    
    /**
     * Create manufacturer relationships
     * 
     * @param WC_Product $wc_product WooCommerce product
     * @return array Manufacturer edges
     */
    private function create_manufacturer_relationships($wc_product) {
        $edges = array();
        $manufacturer = $this->extract_manufacturer($wc_product);
        
        if ($manufacturer) {
            $edges[] = array(
                'source' => 'product-' . $wc_product->get_id(),
                'target' => 'manufacturer-' . sanitize_title($manufacturer),
                'type' => 'MANUFACTURED_BY',
                'properties' => array(
                    'manufacturer_name' => $manufacturer
                )
            );
        }
        
        return $edges;
    }
    
    /**
     * Validate export data
     * 
     * @param array $graph_data Knowledge graph data
     * @return array Validation result
     */
    public function validate_export_data($graph_data) {
        $errors = array();
        
        if (empty($graph_data['nodes'])) {
            $errors[] = 'No product nodes found';
        }
        
        foreach ($graph_data['nodes'] as $index => $node) {
            // Check required fields - only name is truly required for knowledge graph
            if (empty($node['properties']['name'])) {
                $errors[] = "Node {$index}: Missing product name";
            }
            
            // SKU is optional for knowledge graph - just validate if present
            if (!empty($node['properties']['sku']) && strlen($node['properties']['sku']) > 100) {
                $errors[] = "Node {$index}: SKU too long (max 100 characters)";
            }
            
            // Price is optional - some products might not have prices set
            // We'll include products even without prices for comprehensive knowledge graph
            
            // Check field constraints
            if (strlen($node['properties']['name']) > 255) {
                $errors[] = "Node {$index}: Product name too long (max 255 characters)";
            }
            
            if (count($node['properties']['images']) > 10) {
                $errors[] = "Node {$index}: Too many images (max 10)";
            }
            
            if (count($node['properties']['attributes']) > 20) {
                $errors[] = "Node {$index}: Too many attributes (max 20)";
            }
        }
        
        return array(
            'valid' => empty($errors),
            'errors' => $errors
        );
    }
    
    /**
     * Export products immediately
     * 
     * @param array $graph_data Knowledge graph data
     * @return array Export results
     */
    private function export_products_immediately($graph_data) {
        $exported_count = 0;
        $failed_count = 0;
        $errors = array();
        
        // Process in batches
        $batches = array_chunk($graph_data['nodes'], $this->batch_size);
        
        foreach ($batches as $batch_index => $batch) {
            try {
                $batch_result = $this->export_batch($batch);
                $exported_count += $batch_result['exported_count'];
                $failed_count += $batch_result['failed_count'];
                $errors = array_merge($errors, $batch_result['errors']);
                
                // Add delay between batches to respect rate limits
                if ($batch_index < count($batches) - 1) {
                    sleep(1);
                }
            } catch (Exception $e) {
                $failed_count += count($batch);
                $errors[] = 'Batch ' . ($batch_index + 1) . ': ' . $e->getMessage();
            }
        }
        
        // Store export statistics
        $this->update_export_statistics($exported_count, $failed_count);
        
        // Generate export ID for tracking
        $export_id = 'export-' . time() . '-' . wp_generate_password(8, false);
        update_option('alloia_last_export_id', $export_id);
        
        return array(
            'success' => $failed_count === 0,
            'exported_count' => $exported_count,
            'failed_count' => $failed_count,
            'total_count' => count($graph_data['nodes']),
            'total_products' => count($graph_data['nodes']), // Add total_products field for UI compatibility
            'export_id' => $export_id, // Add export ID for tracking
            'errors' => $errors,
            'message' => "Exported {$exported_count} products successfully" . ($failed_count > 0 ? ", {$failed_count} failed" : '')
        );
    }
    
    /**
     * Export a batch of products via AlloIA.io WooCommerce endpoint
     * 
     * @param array $batch Product batch
     * @return array Batch export results
     */
    private function export_batch($batch) {
        $exported_count = 0;
        $failed_count = 0;
        $errors = array();
        
        try {
            // Prepare all products in the batch
            $products_data = array();
            $woocommerce_ids = array();
            
            foreach ($batch as $node) {
                $product_data = $this->prepare_product_data($node);
                $products_data[] = $product_data;
                $woocommerce_ids[] = $node['properties']['woocommerce_id'];
            }
            
            // Bulk export using WooCommerce-specific endpoint
            $response = $this->api_client->bulk_ingest_woocommerce($products_data);
            
            // Process response and update product metadata
            if (isset($response['results']) && is_array($response['results'])) {
                foreach ($response['results'] as $index => $result) {
                    $woocommerce_id = $woocommerce_ids[$index];
                    
                    if (isset($result['success']) && $result['success']) {
                        // Successful export
                        update_post_meta($woocommerce_id, '_alloia_export_id', $result['product_id'] ?? '');
                        update_post_meta($woocommerce_id, '_alloia_export_timestamp', current_time('mysql'));
                        update_post_meta($woocommerce_id, '_alloia_export_status', 'exported');
                        $exported_count++;
                    } else {
                        // Failed export
                        $error_msg = $result['error'] ?? 'Unknown error';
                        update_post_meta($woocommerce_id, '_alloia_export_status', 'failed');
                        update_post_meta($woocommerce_id, '_alloia_export_error', $error_msg);
                        $failed_count++;
                        $errors[] = 'Product ' . ($batch[$index]['properties']['name'] ?? 'Unknown') . ': ' . $error_msg;
                    }
                }
            } else {
                // Fallback: assume all succeeded if no detailed results
                foreach ($woocommerce_ids as $woocommerce_id) {
                    update_post_meta($woocommerce_id, '_alloia_export_timestamp', current_time('mysql'));
                    update_post_meta($woocommerce_id, '_alloia_export_status', 'exported');
                    $exported_count++;
                }
            }
            
        } catch (Exception $e) {
            // Entire batch failed
            $failed_count = count($batch);
            $error_message = $e->getMessage();
            
            // Story 1.2 (AC #7): Handle new API error codes with user-friendly messages
            $dashboard_url = 'https://alloia.io/dashboard/domain-settings';
            
            // Check for domain mismatch error (AC #7)
            if (strpos($error_message, 'DOMAIN_MISMATCH') !== false || 
                strpos($error_message, 'Domain mismatch') !== false ||
                strpos($error_message, 'domain does not match') !== false) {
                $error_message = 'Domain mismatch: Your WordPress domain doesn\'t match the domain registered in your AlloIA account. ' .
                                'Please verify your domain in the AlloIA dashboard: ' . $dashboard_url;
            }
            // Check for email not verified error (AC #7)
            elseif (strpos($error_message, 'EMAIL_NOT_VERIFIED') !== false || 
                    strpos($error_message, 'Email verification required') !== false ||
                    strpos($error_message, 'domain not verified') !== false) {
                $error_message = 'Email verification required: Please verify your domain email address in the AlloIA dashboard to sync products. ' .
                                'Visit: ' . $dashboard_url;
            }
            // Check for domain not set error
            elseif (strpos($error_message, 'DOMAIN_NOT_SET') !== false) {
                $error_message = 'Domain not configured: Please set up your domain in the AlloIA dashboard first. ' .
                                'Visit: ' . $dashboard_url;
            }
            
            $errors[] = 'Batch export failed: ' . $error_message;
            
            // Update all products in batch as failed
            foreach ($batch as $node) {
                $woocommerce_id = $node['properties']['woocommerce_id'];
                update_post_meta($woocommerce_id, '_alloia_export_status', 'failed');
                update_post_meta($woocommerce_id, '_alloia_export_error', $error_message);
            }
        }
        
        return array(
            'exported_count' => $exported_count,
            'failed_count' => $failed_count,
            'errors' => $errors
        );
    }
    
    /**
     * Prepare product data for API submission (simple format per documentation)
     * 
     * @param array $node Knowledge graph node
     * @return array Prepared product data
     */
    private function prepare_product_data($node) {
        $properties = $node['properties'];
        
        // Use simple format as per API documentation
        $product_data = array(
            'name' => $properties['name'],
            'description' => $properties['description'] ?: $properties['short_description'],
            'category' => is_array($properties['category']) ? implode(', ', $properties['category']) : $properties['category']
        );
        
        // Add optional fields only if they have values
        if (!empty($properties['sku'])) {
            $product_data['sku'] = $properties['sku'];
        }
        
        if (!empty($properties['price'])) {
            $product_data['price'] = floatval($properties['price']);
        }
        
        // Add optional fields if present
        if (!empty($properties['manufacturer'])) {
            $product_data['manufacturer'] = $properties['manufacturer'];
        }
        
        if (!empty($properties['images']) && is_array($properties['images'])) {
            $product_data['images'] = $properties['images'];
        }
        
        if (!empty($properties['attributes']) && is_array($properties['attributes'])) {
            $product_data['attributes'] = $properties['attributes'];
        }
        
        if (!empty($properties['currency'])) {
            $product_data['currency'] = $properties['currency'];
        }
        
        // Add WooCommerce specific metadata
        if (!empty($properties['woocommerce_id'])) {
            $product_data['woocommerce_id'] = $properties['woocommerce_id'];
            // HOTFIX: Also send as external_id for API upsert logic
            $product_data['external_id'] = strval($properties['woocommerce_id']);
        }
        
        if (!empty($properties['permalink'])) {
            $product_data['permalink'] = $properties['permalink'];
        }
        
        if (!empty($properties['slug'])) {
            $product_data['slug'] = $properties['slug'];
        }
        
        if (!empty($properties['stock_quantity'])) {
            $product_data['stock_quantity'] = $properties['stock_quantity'];
        }
        
        if (isset($properties['availability'])) {
            $product_data['in_stock'] = $properties['availability'];
        }
        
        return $product_data;
    }
    
    /**
     * Schedule background export
     * 
     * @param array $graph_data Knowledge graph data
     * @param array $filters Export filters
     * @return array Export scheduling result
     */
    private function schedule_background_export($graph_data, $filters) {
        $export_id = uniqid('export_');
        
        // Store export data for background processing
        update_option('alloia_background_export_' . $export_id, array(
            'graph_data' => $graph_data,
            'filters' => $filters,
            'status' => 'pending',
            'created_at' => current_time('mysql'),
            'total_count' => count($graph_data['nodes'])
        ));
        
        // Schedule background job
        wp_schedule_single_event(time() + 60, 'alloia_process_background_export', array($export_id));
        
        return array(
            'success' => true,
            'export_id' => $export_id,
            'message' => 'Export scheduled for background processing',
            'total_count' => count($graph_data['nodes'])
        );
    }
    
    /**
     * Get export status
     * 
     * @param string $export_id Export ID
     * @return array Export status
     */
    public function get_export_status($export_id) {
        $export_data = get_option('alloia_background_export_' . $export_id, array());
        
        if (empty($export_data)) {
            return array(
                'status' => 'not_found',
                'message' => 'Export not found'
            );
        }
        
        return array(
            'status' => $export_data['status'],
            'total_count' => $export_data['total_count'],
            'processed_count' => $export_data['processed_count'] ?? 0,
            'exported_count' => $export_data['exported_count'] ?? 0,
            'failed_count' => $export_data['failed_count'] ?? 0,
            'created_at' => $export_data['created_at'],
            'completed_at' => $export_data['completed_at'] ?? null,
            'errors' => $export_data['errors'] ?? array()
        );
    }
    
    /**
     * Update export statistics
     * 
     * @param int $exported_count Exported products count
     * @param int $failed_count Failed products count
     */
    private function update_export_statistics($exported_count, $failed_count) {
        $current_exported = get_option('alloia_products_exported', 0);
        $current_failed = get_option('alloia_products_export_failed', 0);
        
        error_log("STATS UPDATE - Current: $current_exported, Adding: $exported_count, New Total: " . ($current_exported + $exported_count));
        
        update_option('alloia_products_exported', $current_exported + $exported_count);
        update_option('alloia_products_export_failed', $current_failed + $failed_count);
        update_option('alloia_last_export_timestamp', current_time('mysql'));
        
        // Verify it was saved
        $saved = get_option('alloia_products_exported', 0);
        error_log("STATS UPDATE - Verified saved value: $saved");
    }
    
    /**
     * Get export statistics
     * 
     * Fetches product count from AlloIA API (graph database) instead of local WordPress options
     * 
     * @return array Export statistics
     */
    public function get_export_statistics() {
        $local_exported = get_option('alloia_products_exported', 0);
        $local_failed = get_option('alloia_products_export_failed', 0);
        $last_export = get_option('alloia_last_export_timestamp', '');
        $last_export_id = get_option('alloia_last_export_id', '');
        
        // Try to get actual count from AlloIA API/Graph
        $api_product_count = $this->get_synced_products_count_from_api();
        
        // If API returns a valid count, use it; otherwise fall back to local count
        $total_exported = ($api_product_count !== null) ? $api_product_count : $local_exported;
        
        return array(
            'total_exported' => $total_exported,
            'total_failed' => $local_failed,
            'last_export' => $last_export,
            'last_export_id' => $last_export_id,
            'batch_size' => $this->batch_size,
            'source' => ($api_product_count !== null) ? 'api' : 'local' // Indicate data source
        );
    }
    
    /**
     * Get synced product count from AlloIA API/Graph
     * 
     * Queries the AlloIA API to get the actual count of synced products in the knowledge graph
     * 
     * @return int|null Product count from API, or null on failure
     */
    private function get_synced_products_count_from_api() {
        try {
            // Get client ID for this domain
            $client_id = get_option('alloia_client_id', '');
            if (empty($client_id)) {
                // Try to get it from domain validation
                $domain_validation = $this->api_client->validate_domain_for_sync();
                if (isset($domain_validation['client_id'])) {
                    $client_id = $domain_validation['client_id'];
                    update_option('alloia_client_id', $client_id);
                }
            }
            
            if (empty($client_id)) {
                return null; // Can't query without client ID
            }
            
            // Query AlloIA API for product count
            $response = $this->api_client->get_products_count($client_id);
            
            if (is_wp_error($response)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('AlloIA: Failed to get product count from API: ' . $response->get_error_message());
                }
                return null;
            }
            
            // Extract count from response
            if (isset($response['count']) && is_numeric($response['count'])) {
                // Cache the result for 5 minutes
                set_transient('alloia_api_product_count', $response['count'], 5 * MINUTE_IN_SECONDS);
                return intval($response['count']);
            }
            
            return null;
            
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AlloIA: Exception getting product count from API: ' . $e->getMessage());
            }
            return null;
        }
    }
    
    /**
     * Set batch size
     * 
     * @param int $batch_size Batch size
     */
    public function set_batch_size($batch_size) {
        $this->batch_size = intval($batch_size);
        update_option('alloia_export_batch_size', $this->batch_size);
    }
    
    /**
     * Get batch size
     * 
     * @return int Batch size
     */
    public function get_batch_size() {
        return $this->batch_size;
    }
    
    /**
     * Test export functionality with a single product
     * 
     * @return array Test results
     */
    public function test_export() {
        try {
            // Get one published product for testing
            $products = $this->extract_woocommerce_products(array(
                'limit' => 1,
                'status' => 'publish'
            ));
            
            if (empty($products)) {
                return array(
                    'success' => false,
                    'message' => 'No published products found for testing'
                );
            }
            
            // Convert to knowledge graph format
            $graph_data = $this->convert_products_to_graph_format($products);
            
            if (empty($graph_data['nodes'])) {
                return array(
                    'success' => false,
                    'message' => 'Failed to convert product to graph format'
                );
            }
            
            // Test with single product
            $test_product = $this->prepare_product_data($graph_data['nodes'][0]);
            $response = $this->api_client->bulk_ingest_woocommerce(array($test_product));
            
            return array(
                'success' => true,
                'message' => 'Export test successful',
                'product_tested' => $products[0]->get_name(),
                'response' => $response
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Export test failed: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Extract all variants for a variable product
     * 
     * @param WC_Product_Variable $product Variable product instance
     * @return array Array of variant data
     */
    private function extract_product_variants($product) {
        $variants = array();
        $variation_ids = $product->get_children();
        
        foreach ($variation_ids as $variation_id) {
            $variation = wc_get_product($variation_id);
            
            if (!$variation || !$variation->exists()) {
                continue;
            }
            
            $variant_data = $this->extract_single_variant($variation, $product);
            if ($variant_data) {
                $variants[] = $variant_data;
            }
        }
        
        return $variants;
    }
    
    /**
     * Extract single variant data
     * 
     * @param WC_Product_Variation $variation Variation instance
     * @param WC_Product_Variable $parent Parent product instance
     * @return array|null Variant data or null if invalid
     */
    private function extract_single_variant($variation, $parent) {
        try {
            // Basic identification
            $variant_data = array(
                'id' => (string) $variation->get_id(),
                'parent_product_id' => (string) $parent->get_id(),
                'sku' => $variation->get_sku() ?: 'VAR-' . $variation->get_id(),
                'title' => $variation->get_name()
            );
            
            // Pricing
            $variant_data['price'] = (float) $variation->get_price();
            $variant_data['regular_price'] = (float) $variation->get_regular_price();
            $sale_price = $variation->get_sale_price();
            $variant_data['sale_price'] = $sale_price ? (float) $sale_price : null;
            $variant_data['currency'] = get_woocommerce_currency();
            
            // Attributes - normalized and raw
            $raw_attributes = $variation->get_attributes();
            $variant_data['attributes'] = $this->normalize_variant_attributes($raw_attributes, $parent);
            $variant_data['attributes_raw'] = array(
                'woocommerce' => $raw_attributes
            );
            
            // Inventory
            $variant_data['in_stock'] = $variation->is_in_stock();
            $variant_data['inventory_quantity'] = $variation->get_stock_quantity();
            $variant_data['inventory_policy'] = $variation->backorders_allowed() ? 'backorder' : 'deny';
            
            // Images
            $variant_data['images'] = $this->get_variant_images($variation, $parent);
            
            // Checkout URL
            $variant_data['checkout_url'] = $this->generate_variant_checkout_url($variation);
            
            // Physical attributes
            $variant_data['weight'] = (float) $variation->get_weight();
            $variant_data['weight_unit'] = get_option('woocommerce_weight_unit', 'kg');
            $variant_data['dimensions'] = array(
                'length' => (float) $variation->get_length(),
                'width' => (float) $variation->get_width(),
                'height' => (float) $variation->get_height(),
                'unit' => get_option('woocommerce_dimension_unit', 'cm')
            );
            
            // Metadata
            $variant_data['created_at'] = $variation->get_date_created() ? $variation->get_date_created()->format('c') : null;
            $variant_data['updated_at'] = $variation->get_date_modified() ? $variation->get_date_modified()->format('c') : null;
            
            return $variant_data;
            
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AlloIA: Error extracting variant ' . $variation->get_id() . ': ' . $e->getMessage());
            }
            return null;
        }
    }
    
    /**
     * Normalize variant attributes for consistent AI querying
     * 
     * @param array $raw_attributes Raw WooCommerce attributes
     * @param WC_Product_Variable $parent Parent product for attribute labels
     * @return array Normalized attributes (lowercase keys, clean values)
     */
    private function normalize_variant_attributes($raw_attributes, $parent) {
        $normalized = array();
        
        foreach ($raw_attributes as $attribute_name => $attribute_value) {
            // Remove 'attribute_' prefix and 'pa_' taxonomy prefix
            $clean_name = str_replace(array('attribute_', 'pa_'), '', $attribute_name);
            $clean_name = strtolower(trim($clean_name));
            
            // Get human-readable label from parent product
            $parent_attributes = $parent->get_attributes();
            $label = $clean_name;
            
            foreach ($parent_attributes as $attr) {
                if ($attr->get_name() === $attribute_name || 
                    $attr->get_name() === str_replace('attribute_', '', $attribute_name)) {
                    $label = strtolower($attr->get_name());
                    break;
                }
            }
            
            // Clean and store value
            $normalized[$label] = sanitize_text_field($attribute_value);
        }
        
        return $normalized;
    }
    
    /**
     * Get images for a variant
     * 
     * @param WC_Product_Variation $variation Variation instance
     * @param WC_Product_Variable $parent Parent product for fallback
     * @return array Array of image URLs (max 5 per variant)
     */
    private function get_variant_images($variation, $parent) {
        $images = array();
        $max_variant_images = 5;
        
        // Get variation-specific image
        $variation_image_id = $variation->get_image_id();
        if ($variation_image_id) {
            $variation_image_url = wp_get_attachment_image_url($variation_image_id, 'full');
            if ($variation_image_url) {
                $images[] = $variation_image_url;
            }
        }
        
        // If no variation image, use parent's main image as fallback
        if (empty($images)) {
            $parent_image_id = $parent->get_image_id();
            if ($parent_image_id) {
                $parent_image_url = wp_get_attachment_image_url($parent_image_id, 'full');
                if ($parent_image_url) {
                    $images[] = $parent_image_url;
                }
            }
        }
        
        // Add up to 4 more images from parent gallery (if space available)
        $remaining_slots = $max_variant_images - count($images);
        if ($remaining_slots > 0) {
            $gallery_ids = $parent->get_gallery_image_ids();
            $gallery_ids_limited = array_slice($gallery_ids, 0, $remaining_slots);
            
            foreach ($gallery_ids_limited as $gallery_id) {
                $gallery_url = wp_get_attachment_image_url($gallery_id, 'full');
                if ($gallery_url) {
                    $images[] = $gallery_url;
                }
            }
        }
        
        return $images;
    }
    
    /**
     * Generate checkout URL for a specific variant
     * 
     * @param WC_Product_Variation $variation Variation instance
     * @return string Direct add-to-cart URL for this variant
     */
    private function generate_variant_checkout_url($variation) {
        $product_id = $variation->get_parent_id();
        $variation_id = $variation->get_id();
        
        // Get cart URL
        $cart_url = wc_get_cart_url();
        
        // Build add-to-cart URL with variation parameters
        $checkout_url = add_query_arg(array(
            'add-to-cart' => $product_id,
            'variation_id' => $variation_id,
            'quantity' => 1
        ), $cart_url);
        
        // Add variation attributes to URL
        $attributes = $variation->get_attributes();
        foreach ($attributes as $attr_key => $attr_value) {
            $checkout_url = add_query_arg(
                'attribute_' . sanitize_title($attr_key),
                $attr_value,
                $checkout_url
            );
        }
        
        return esc_url_raw($checkout_url);
    }
}
