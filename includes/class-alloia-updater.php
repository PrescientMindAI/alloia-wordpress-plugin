<?php
/**
 * GitHub-based plugin updater for AlloIA WooCommerce plugin
 * 
 * Checks for updates from GitHub releases and provides automatic updates
 * Compatible with WordPress.org updates (dual-source capability)
 * 
 * @package AlloIA_WooCommerce
 * @since 1.7.1
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class AlloIA_Plugin_Updater {
    
    private $plugin_slug;
    private $plugin_basename;
    private $github_repo;
    private $current_version;
    private $cache_key;
    private $cache_expiration = 43200; // 12 hours
    
    /**
     * Constructor
     * 
     * @param string $plugin_basename Plugin basename (e.g., 'alloia-woocommerce/alloia-woocommerce.php')
     * @param string $github_repo GitHub repository (e.g., 'PrescientMindAI/alloia-wordpress-plugin')
     * @param string $current_version Current plugin version
     */
    public function __construct($plugin_basename, $github_repo, $current_version) {
        $this->plugin_basename = $plugin_basename;
        $this->plugin_slug = dirname($plugin_basename);
        $this->github_repo = $github_repo;
        $this->current_version = $current_version;
        $this->cache_key = 'alloia_update_' . md5($this->github_repo);
        
        // Hook into WordPress update system
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_update'));
        add_filter('plugins_api', array($this, 'plugin_info'), 20, 3);
        add_filter('upgrader_source_selection', array($this, 'rename_github_zip'), 10, 4);
        
        // Clear cache on plugin update
        add_action('upgrader_process_complete', array($this, 'clear_cache'), 10, 2);
    }
    
    /**
     * Check for plugin updates from GitHub
     * 
     * @param object $transient WordPress update transient
     * @return object Modified transient
     */
    public function check_for_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }
        
        // Get update info from cache or GitHub API
        $update_info = $this->get_update_info();
        
        if ($update_info && version_compare($this->current_version, $update_info->version, '<')) {
            // New version available
            $plugin_data = new stdClass();
            $plugin_data->slug = $this->plugin_slug;
            $plugin_data->new_version = $update_info->version;
            $plugin_data->url = $update_info->url;
            $plugin_data->package = $update_info->download_url;
            $plugin_data->tested = $update_info->tested ?? '6.4';
            $plugin_data->requires = $update_info->requires ?? '5.8';
            $plugin_data->requires_php = $update_info->requires_php ?? '7.4';
            
            $transient->response[$this->plugin_basename] = $plugin_data;
        }
        
        return $transient;
    }
    
    /**
     * Get update information from GitHub releases
     * 
     * @return object|false Update information or false
     */
    private function get_update_info() {
        // Check cache first
        $cached = get_transient($this->cache_key);
        if ($cached !== false) {
            return $cached;
        }
        
        // Fetch from GitHub releases API
        $api_url = "https://api.github.com/repos/{$this->github_repo}/releases/latest";
        
        $response = wp_remote_get($api_url, array(
            'timeout' => 15,
            'headers' => array(
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'AlloIA-WordPress-Plugin-Updater'
            )
        ));
        
        if (is_wp_error($response)) {
            // Cache failure for shorter time (1 hour)
            set_transient($this->cache_key, false, 3600);
            return false;
        }
        
        $release = json_decode(wp_remote_retrieve_body($response));
        
        if (!isset($release->tag_name)) {
            set_transient($this->cache_key, false, 3600);
            return false;
        }
        
        // Parse release information
        $update_info = new stdClass();
        $update_info->version = ltrim($release->tag_name, 'v'); // Remove 'v' prefix if present
        $update_info->url = $release->html_url;
        $update_info->download_url = $release->zipball_url;
        $update_info->body = $release->body ?? '';
        
        // Parse WordPress metadata from release body if available
        if (preg_match('/Tested up to:\s*(\d+\.\d+)/i', $update_info->body, $matches)) {
            $update_info->tested = $matches[1];
        }
        
        if (preg_match('/Requires at least:\s*(\d+\.\d+)/i', $update_info->body, $matches)) {
            $update_info->requires = $matches[1];
        }
        
        if (preg_match('/Requires PHP:\s*(\d+\.\d+)/i', $update_info->body, $matches)) {
            $update_info->requires_php = $matches[1];
        }
        
        // Cache for 12 hours
        set_transient($this->cache_key, $update_info, $this->cache_expiration);
        
        return $update_info;
    }
    
    /**
     * Provide plugin information for the update screen
     * 
     * @param false|object|array $result The result object or array
     * @param string $action The type of information being requested
     * @param object $args Plugin API arguments
     * @return false|object Modified result
     */
    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }
        
        if ($args->slug !== $this->plugin_slug) {
            return $result;
        }
        
        $update_info = $this->get_update_info();
        
        if (!$update_info) {
            return $result;
        }
        
        $plugin_info = new stdClass();
        $plugin_info->name = 'AlloIA for WooCommerce';
        $plugin_info->slug = $this->plugin_slug;
        $plugin_info->version = $update_info->version;
        $plugin_info->author = '<a href="https://alloia.ai">AlloIA Team</a>';
        $plugin_info->homepage = 'https://alloia.ai';
        $plugin_info->download_link = $update_info->download_url;
        $plugin_info->sections = array(
            'description' => 'Transform your WooCommerce store for the AI era. AI-ready product catalog, smart robots.txt management, and seamless integration with the AlloIA platform.',
            'changelog' => $this->parse_changelog($update_info->body)
        );
        
        return $plugin_info;
    }
    
    /**
     * Parse changelog from release notes
     * 
     * @param string $body Release body text
     * @return string Formatted changelog HTML
     */
    private function parse_changelog($body) {
        if (empty($body)) {
            return '<p>See <a href="https://github.com/' . $this->github_repo . '/releases" target="_blank">GitHub releases</a> for changelog.</p>';
        }
        
        // Convert markdown to HTML (basic)
        $html = wpautop($body);
        $html = str_replace('###', '<h4>', $html);
        $html = str_replace('##', '<h3>', $html);
        
        return $html;
    }
    
    /**
     * Rename the GitHub zip folder to match plugin slug
     * 
     * GitHub zips are named 'PrescientMindAI-alloia-wordpress-plugin-abc123'
     * WordPress expects 'alloia-woocommerce'
     * 
     * @param string $source File source location
     * @param string $remote_source Remote file source location
     * @param WP_Upgrader $upgrader WP_Upgrader instance
     * @param array $hook_extra Extra arguments passed to hooked filters
     * @return string|WP_Error Modified source location
     */
    public function rename_github_zip($source, $remote_source, $upgrader, $hook_extra) {
        global $wp_filesystem;
        
        // Only for our plugin
        if (!isset($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->plugin_basename) {
            return $source;
        }
        
        // Get the actual folder name inside the zip
        $source_files = $wp_filesystem->dirlist($remote_source);
        if (!$source_files) {
            return $source;
        }
        
        // GitHub creates a single folder inside the zip
        $source_folder = key($source_files);
        $new_source = trailingslashit($remote_source) . trailingslashit($this->plugin_slug);
        
        // Rename to expected plugin slug
        $wp_filesystem->move($source, $new_source);
        
        return $new_source;
    }
    
    /**
     * Clear update cache after plugin update
     * 
     * @param WP_Upgrader $upgrader WP_Upgrader instance
     * @param array $hook_extra Extra arguments passed to hooked filters
     */
    public function clear_cache($upgrader, $hook_extra) {
        if (isset($hook_extra['plugin']) && $hook_extra['plugin'] === $this->plugin_basename) {
            delete_transient($this->cache_key);
        }
    }
}

