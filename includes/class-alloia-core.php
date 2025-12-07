<?php
/**
 * Core functionality for AlloIA WooCommerce plugin
 * 
 * @package AlloIA_WooCommerce
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class AlloIA_Core {
    
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize core hooks
     */
    public function init_hooks() {
        // Init hooks
        add_action('init', array($this, 'init'));
        // Cron task for audits
        add_action('alloia_hourly_audit', array($this, 'run_hourly_audit'));
        
        // llms.txt endpoint
        add_action('init', array($this, 'add_llms_txt_rewrite'));
        add_action('template_redirect', array($this, 'serve_llms_txt'));
        
        // Robots.txt hooks - comprehensive approach
        add_action('init', array($this, 'add_robots_txt_rewrite'));
        add_action('template_redirect', array($this, 'serve_robots_txt'));
        add_action('do_robots', array($this, 'inject_robots_txt'));
        add_filter('robots_txt', array($this, 'filter_robots_txt'), 10, 2);
        
        // Tracking code injection
        add_action('wp_head', array($this, 'inject_tracking_code'));

        // Optional AI bot redirection (PHP/WP mode)
        add_action('init', array($this, 'maybe_redirect_ai_bots'), 1);
    }

    // On plugin activation: flush rewrite rules and show admin notice
    public function activate() {
        // Add rewrite rules
        $this->add_llms_txt_rewrite();
        $this->add_robots_txt_rewrite();
        // Flush rewrite rules
        flush_rewrite_rules();
        add_option('alloia_llms_txt_flush_notice', true);

        // Schedule hourly audit if not scheduled
        if (!wp_next_scheduled('alloia_hourly_audit')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', 'alloia_hourly_audit');
        }
    }

    // On plugin deactivation: flush rewrite rules and clear scheduled events
    public function deactivate() {
        flush_rewrite_rules();
        
        // Clear all scheduled cron events
        wp_clear_scheduled_hook('alloia_hourly_audit');
        
        // Clear any other scheduled events that might be added in the future
        $scheduled_hooks = array(
            'alloia_daily_sync',
            'alloia_weekly_optimization'
        );
        
        foreach ($scheduled_hooks as $hook) {
            wp_clear_scheduled_hook($hook);
        }
    }

    // Main init logic (admin notices, etc.)
    public function init() {
        // Clean up old static llms.txt file (plugin serves dynamically)
        // This runs on every page load to ensure external tools don't recreate it
        $static_llms_txt = ABSPATH . 'llms.txt';
        if (file_exists($static_llms_txt)) {
            wp_delete_file($static_llms_txt);
        }
        
        // Check if we need to flush rewrite rules (version update)
        $installed_version = get_option('alloia_version', '0');
        if (version_compare($installed_version, ALLOIA_VERSION, '<')) {
            // Version updated - flush rewrite rules
            flush_rewrite_rules();
            update_option('alloia_version', ALLOIA_VERSION);
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("AlloIA Plugin: Updated to version " . ALLOIA_VERSION . ", flushed rewrite rules");
            }
        }
        
        if (is_admin() && get_option('alloia_llms_txt_flush_notice')) {
            add_action('admin_notices', array($this, 'llms_txt_flush_notice'));
        }
    }

    // Show admin notice to flush permalinks
    public function llms_txt_flush_notice() {
        echo '<div class="notice notice-success is-dismissible"><p><strong>AlloIA for WooCommerce:</strong> Please visit <a href="/wp-admin/options-permalink.php">Settings > Permalinks</a> and click "Save Changes" to enable the /llms.txt endpoint.</p></div>';
        delete_option('alloia_llms_txt_flush_notice');
    }

    // Add rewrite rule for /llms.txt
    public function add_llms_txt_rewrite() {
        add_rewrite_rule('^llms\.txt$', 'index.php?llms_txt=1', 'top');
        add_rewrite_tag('%llms_txt%', '1');
    }

    // Add rewrite rule for /robots.txt (ensure WordPress handles it)
    public function add_robots_txt_rewrite() {
        // Ensure WordPress recognizes robots.txt requests
        add_rewrite_rule('^robots\.txt$', 'index.php?robots=1', 'top');
        add_rewrite_tag('%robots%', '1');
    }

    // Serve llms.txt content dynamically
    public function serve_llms_txt() {
        if (get_query_var('llms_txt') == 1) {
            header('Content-Type: text/plain; charset=utf-8');
            echo $this->generate_llms_txt(); // Plain text output, no escaping needed
            exit;
        }
    }

    // Serve robots.txt content dynamically
    public function serve_robots_txt() {
        if (get_query_var('robots') == 1) {
            header('Content-Type: text/plain; charset=utf-8');
            echo esc_html($this->generate_robots_txt());
            exit;
        }
    }

    // Generate llms.txt content pointing to AlloIA Knowledge Graph
    private function generate_llms_txt() {
        $site_name = get_bloginfo('name');
        $site_url = home_url('/');
        $site_description = get_bloginfo('description');
        
        // Get client ID from API validation (if available)
        $api_key = get_option('alloia_api_key_encrypted', get_option('alloia_api_key', ''));
        $client_id = get_option('alloia_client_id', '');
        
        // Build llms.txt content
        $output = "# {$site_name}\n\n";
        $output .= "> {$site_description}\n\n";
        $output .= "This site uses AlloIA for AI-powered commerce optimization.\n\n";
        
        // If client has API key and products synced, point to AlloIA Knowledge Graph
        if (!empty($api_key) && !empty($client_id)) {
            $output .= "## AlloIA Knowledge Graph\n\n";
            $output .= "Products from this store are available in the AlloIA Knowledge Graph:\n\n";
            $output .= "- Product Catalog: https://www.alloia.io/api/v1/products?clientId={$client_id}\n";
            $output .= "- Knowledge API: https://www.alloia.io/api/v1/knowledge/client/{$client_id}\n\n";
        }
        
        // Add store information
        $output .= "## Store Information\n\n";
        $output .= "- Website: {$site_url}\n";
        
        // Add sitemap if available
        $sitemap_url = home_url('/sitemap.xml');
        if ($this->url_exists($sitemap_url)) {
            $output .= "- Sitemap: {$sitemap_url}\n";
        }
        
        $output .= "\n## Resources\n\n";
        $output .= "- [AlloIA Platform](https://alloia.io)\n";
        $output .= "- [Plugin Documentation](https://github.com/PrescientMindAI/alloia-wordpress-plugin)\n";
        
        return $output;
    }

    // Generate robots.txt content
    private function generate_robots_txt() {
        $llm_training = get_option('alloia_llm_training', 'allow');
        $subdomain = get_option('alloia_subdomain', '');
        
        // Debug: Log the training permission value
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('AlloIA: Generating robots.txt with training permission: ' . $llm_training);
        }
        
        // Start with basic WordPress robots.txt content
        $output = "User-agent: *\n";
        $output .= "Disallow: /wp-admin/\n";
        $output .= "Allow: /wp-admin/admin-ajax.php\n\n";
        
        // Add sitemap if available
        $sitemap_url = home_url('/sitemap.xml');
        if ($this->url_exists($sitemap_url)) {
            $output .= "Sitemap: $sitemap_url\n\n";
        }
        
        // Add AlloIA block
        $output .= "# Start AlloIA block\n";
        if ($subdomain) {
            $output .= "# Crawl $subdomain for content optimized for AI\n";
        }
        
        // AI Search/Browsing Bots - Always Allow (these help users find your content)
        $search_bots = [
            'ChatGPT-User',           // OpenAI - ChatGPT browsing
            'OAI-SearchBot',          // OpenAI - ChatGPT search
            'claude-web',             // Anthropic - Claude web interface
            'PerplexityBot',          // Perplexity AI search
            'YouBot',                 // You.com search
            'DuckAssistBot',          // DuckDuckGo AI assistant
            'meta-externalagent',     // Meta/Facebook
            'meta-externalfetcher',   // Meta/Facebook
            'facebookexternalhit',    // Meta/Facebook
            'Googlebot',              // Google search
            'Applebot'                // Apple Siri (regular)
        ];
        
        $output .= "# AI Search & Browsing Bots (Always Allowed)\n";
        foreach ($search_bots as $bot) {
            $output .= "User-agent: $bot\nAllow: /\n";
        }
        $output .= "\n";
        
        // AI Training Bots - Controlled by user setting
        $training_bots = [
            'GPTBot',                 // OpenAI - GPT training
            'ClaudeBot',              // Anthropic - Claude training  
            'anthropic-ai',           // Anthropic - AI training
            'CCBot',                  // Common Crawl
            'Google-Extended',        // Google - Gemini AI training
            'Googlebot-extended',     // Google - Extended AI features
            'GrokBot',                // xAI - Grok training
            'xAI-Grok',              // xAI - Grok AI
            'Grok-DeepSearch',        // xAI - Grok advanced search
            'Applebot-Extended',      // Apple Intelligence training
            'cohere-ai'               // Cohere AI training
        ];
        
        $output .= "# AI Training Bots (User Controlled)\n";
        foreach ($training_bots as $bot) {
            $output .= "User-agent: $bot\n";
            $output .= ($llm_training === 'allow') ? "Allow: /\n" : "Disallow: /\n";
        }
        
        $output .= "# End AlloIA block\n";
        
        return $output;
    }

    // Helper function to check if URL exists
    private function url_exists($url) {
        // Prefer HEAD, fall back to GET, accept 2xx/3xx
        $response = wp_remote_head($url, array('timeout' => 8, 'redirection' => 5));
        if (!is_wp_error($response)) {
            $code = wp_remote_retrieve_response_code($response);
            if ($code >= 200 && $code < 400) return true;
        }
        $response = wp_remote_get($url, array('timeout' => 8, 'redirection' => 5));
        if (!is_wp_error($response)) {
            $code = wp_remote_retrieve_response_code($response);
            if ($code >= 200 && $code < 400) return true;
        }
        // If URL maps to a known root file, check filesystem
        $path = wp_parse_url($url, PHP_URL_PATH);
        if ($path) {
            $basename = basename($path);
            if (in_array($basename, array('robots.txt', 'llms.txt'))) {
                return file_exists(ABSPATH . $basename);
            }
        }
        return false;
    }

    // Update physical robots.txt file when settings change
    public function update_physical_robots_txt() {
        $robots_content = $this->generate_robots_txt();
        $robots_path = wp_normalize_path(ABSPATH . 'robots.txt');

        // Debug: Always log the attempt (not just in WP_DEBUG mode)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('AlloIA: Attempting to update robots.txt at: ' . $robots_path);
        }
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('AlloIA: ABSPATH: ' . ABSPATH);
        }
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('AlloIA: Content length: ' . strlen($robots_content));
        }
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('AlloIA: Content preview: ' . substr($robots_content, 0, 200) . '...');
        }

        // Validate path is inside WordPress root
        if (strpos($robots_path, wp_normalize_path(ABSPATH)) !== 0) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('AlloIA: Invalid robots.txt path validation failed');
                }
            }
            add_action('admin_notices', function() {
                echo '<div class="notice notice-warning"><p><strong>AlloIA:</strong> Invalid robots.txt path. Update aborted.</p></div>';
            });
            return false;
        }

        // Check if we can write to the directory using WP_Filesystem
        global $wp_filesystem;
        if (!WP_Filesystem()) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('AlloIA: Cannot initialize WP_Filesystem');
                }
            }
            add_action('admin_notices', function() {
                echo '<div class="notice notice-warning"><p><strong>AlloIA:</strong> Cannot initialize filesystem. Cannot update robots.txt file.</p></div>';
            });
            return false;
        }
        
        if (!$wp_filesystem->is_writable(ABSPATH)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('AlloIA: ABSPATH is not writable: ' . ABSPATH);
                }
            }
            add_action('admin_notices', function() {
                echo '<div class="notice notice-warning"><p><strong>AlloIA:</strong> WordPress root directory is not writable. Cannot update robots.txt file.</p></div>';
            });
            return false;
        }

        // Check if file exists and is writable, or if we can create it
        if (file_exists($robots_path) && !$wp_filesystem->is_writable($robots_path)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('AlloIA: robots.txt exists but is not writable: ' . $robots_path);
                }
            }
            add_action('admin_notices', function() {
                echo '<div class="notice notice-warning"><p><strong>AlloIA:</strong> robots.txt file exists but is not writable. Please check file permissions.</p></div>';
            });
            return false;
        }

        // Validate content is not empty
        if (empty($robots_content)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('AlloIA: Empty robots.txt content generated');
                }
            }
            add_action('admin_notices', function() {
                echo '<div class="notice notice-warning"><p><strong>AlloIA:</strong> Empty robots.txt content. Update aborted.</p></div>';
            });
            return false;
        }

        // Try to write the file safely
        $result = @file_put_contents($robots_path, $robots_content);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AlloIA: File write result: ' . ($result === false ? 'FAILED' : 'SUCCESS (' . $result . ' bytes)'));
            }
        }

        if ($result === false) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-warning"><p><strong>AlloIA:</strong> Could not update robots.txt file. Please check file permissions or manually update the file.</p></div>';
            });
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p><strong>AlloIA:</strong> robots.txt file has been updated successfully!</p></div>';
            });
        }

        return $result !== false;
    }

    // Inject standardized AlloIA tracking script with dynamic websiteId and API key (Pro-only)
    public function inject_tracking_code() {
        // Gate to Pro license
        $license_key = get_option('alloia_api_key', '');
        if (empty($license_key)) {
            echo "\n<!-- AlloIA tracking disabled: Pro license required -->\n";
            return;
        }
        $website_id = get_option('alloia_tracking_website_id', '');
        $api_key = get_option('alloia_tracking_api_key', '');

        if (!empty($website_id) && !empty($api_key)) {
            $website_id_js = esc_js($website_id);
            $api_key_js = esc_js($api_key);

            $script = '<!-- AlloIA tracking script -->' . "\n" .
                '<script>' . "\n" .
                '  (function(){' . "\n" .
                '    function checkForAIBot(){' . "\n" .
                '      const userAgent = navigator.userAgent;' . "\n" .
                '      const AI_BOTS = [' . "\n" .
                '        { name: \'anthropic-ai\', pattern: /anthropic-ai/i },' . "\n" .
                '        { name: \'claudebot\', pattern: /ClaudeBot/i },' . "\n" .
                '        { name: \'claude-web\', pattern: /claude-web/i },' . "\n" .
                '        { name: \'perplexitybot\', pattern: /PerplexityBot/i },' . "\n" .
                '        { name: \'perplexity-user\', pattern: /Perplexity-User/i },' . "\n" .
                '        { name: \'grokbot\', pattern: /GrokBot(?!.*DeepSearch)/i },' . "\n" .
                '        { name: \'grok-search\', pattern: /xAI-Grok/i },' . "\n" .
                '        { name: \'grok-deepsearch\', pattern: /Grok-DeepSearch/i },' . "\n" .
                '        { name: \'deepseekbot\', pattern: /DeepSeekBot/i },' . "\n" .
                '        { name: \'GPTBot\', pattern: /GPTBot/i },' . "\n" .
                '        { name: \'chatgpt-user\', pattern: /ChatGPT-User/i },' . "\n" .
                '        { name: \'oai-searchbot\', pattern: /OAI-SearchBot/i },' . "\n" .
                '        { name: \'google-extended\', pattern: /Google-Extended/i },' . "\n" .
                '        { name: \'applebot\', pattern: /Applebot(?!-Extended)/i },' . "\n" .
                '        { name: \'applebot-extended\', pattern: /Applebot-Extended/i },' . "\n" .
                '        { name: \'meta-external\', pattern: /meta-externalagent/i },' . "\n" .
                '        { name: \'meta-externalfetcher\', pattern: /meta-externalfetcher/i },' . "\n" .
                '        { name: \'bingbot\', pattern: /Bingbot(?!.*AI)/i },' . "\n" .
                '        { name: \'bingpreview\', pattern: /bingbot.*Chrome/i },' . "\n" .
                '        { name: \'microsoftpreview\', pattern: /MicrosoftPreview/i },' . "\n" .
                '        { name: \'cohere-ai\', pattern: /cohere-ai/i },' . "\n" .
                '        { name: \'cohere-training-data-crawler\', pattern: /cohere-training-data-crawler/i },' . "\n" .
                '        { name: \'youbot\', pattern: /YouBot/i },' . "\n" .
                '        { name: \'duckassistbot\', pattern: /DuckAssistBot/i },' . "\n" .
                '        { name: \'semanticscholarbot\', pattern: /SemanticScholarBot/i },' . "\n" .
                '        { name: \'ccbot\', pattern: /CCBot/i },' . "\n" .
                '        { name: \'ai2bot\', pattern: /AI2Bot/i },' . "\n" .
                '        { name: \'ai2bot-dolma\', pattern: /AI2Bot-Dolma/i },' . "\n" .
                '        { name: \'aihitbot\', pattern: /aiHitBot/i },' . "\n" .
                '        { name: \'amazonbot\', pattern: /Amazonbot/i },' . "\n" .
                '        { name: \'novaact\', pattern: /NovaAct/i },' . "\n" .
                '        { name: \'brightbot\', pattern: /Brightbot/i },' . "\n" .
                '        { name: \'bytespider\', pattern: /Bytespider/i },' . "\n" .
                '        { name: \'tiktokspider\', pattern: /TikTokSpider/i },' . "\n" .
                '        { name: \'cotoyogi\', pattern: /Cotoyogi/i },' . "\n" .
                '        { name: \'crawlspace\', pattern: /Crawlspace/i },' . "\n" .
                '        { name: \'pangubot\', pattern: /PanguBot/i },' . "\n" .
                '        { name: \'petalbot\', pattern: /PetalBot/i },' . "\n" .
                '        { name: \'semrushbot-ocob\', pattern: /SemrushBot-OCOB/i },' . "\n" .
                '        { name: \'semrushbot-swa\', pattern: /SemrushBot-SWA/i },' . "\n" .
                '        { name: \'sidetrade-indexer\', pattern: /Sidetrade indexer bot/i },' . "\n" .
                '        { name: \'timpibot\', pattern: /Timpibot/i },' . "\n" .
                '        { name: \'velenpublicwebcrawler\', pattern: /VelenPublicWebCrawler/i },' . "\n" .
                '        { name: \'omgili\', pattern: /omgili/i },' . "\n" .
                '        { name: \'omgilibot\', pattern: /omgilibot/i },' . "\n" .
                '        { name: \'webzio-extended\', pattern: /Webzio-Extended/i }' . "\n" .
                '      ];' . "\n" .
                '      for (const bot of AI_BOTS) {' . "\n" .
                '        if (bot.pattern.test(userAgent)) {' . "\n" .
                '          try {' . "\n" .
                '            fetch(\'https://api.aiseotracker.com/track-ai-bot\', {' . "\n" .
                '              method: \'POST\',' . "\n" .
                '              headers: {' . "\n" .
                '                \'Content-Type\': \'application/json\',' . "\n" .
                '                \'x-api-key\': \'' . $api_key_js . '\'' . "\n" .
                '              },' . "\n" .
                '              body: JSON.stringify({' . "\n" .
                '                botName: bot.name,' . "\n" .
                '                userAgent: userAgent,' . "\n" .
                '                url: window.location.href,' . "\n" .
                '                referer: document.referrer || undefined,' . "\n" .
                '                websiteId: \'' . $website_id_js . '\'' . "\n" .
                '              })' . "\n" .
                '            }).catch(function(e){ console.error(\'Error tracking AI bot:\', e); });' . "\n" .
                '          } catch(e) { console.error(\'AlloIA tracking exception\', e); }' . "\n" .
                '          break;' . "\n" .
                '        }' . "\n" .
                '      }' . "\n" .
                '    }' . "\n" .
                '    window.addEventListener(\'load\', checkForAIBot);' . "\n" .
                '  })();' . "\n" .
                '</script>';
            echo "\n" . wp_kses($script, array(
                'script' => array(),
                'noscript' => array()
            )) . "\n";
        } else {
            $tracking_code = get_option('alloia_tracking_code', '');
            if ($tracking_code) {
                $allowed = array(
                    'script' => array(
                        'src' => array(),
                        'type' => array(),
                        'async' => array(),
                        'defer' => array(),
                        'data-site' => array(),
                        'data-key' => array(),
                    ),
                    'noscript' => array(),
                );
                $safe_tracking_code = wp_kses($tracking_code, $allowed);
                echo "\n<!-- AlloIA tracking code injected (custom) -->\n" . wp_kses($safe_tracking_code, $allowed) . "\n";
            } else {
                echo "\n<!-- AlloIA tracking code placeholder: set tracking website ID and API key to enable tracking -->\n";
            }
        }
    }

    /**
     * Injecte les directives AlloIA dans robots.txt, compatible avec les autres plugins SEO.
     */
    public function inject_robots_txt() {
        // Récupérer la valeur du switch depuis les options (par défaut allow)
        $llm_training = get_option('alloia_llm_training', 'allow');
        $subdomain = get_option('alloia_subdomain', '');
        global $wp_filter;
        // Bufferiser le robots.txt généré par les autres plugins (ex: Yoast)
        ob_start();
        if (isset($wp_filter['do_robots'])) {
            foreach ($wp_filter['do_robots']->callbacks as $priority => $callbacks) {
                foreach ($callbacks as $cb) {
                    if ($cb['function'] !== array($this, 'inject_robots_txt')) {
                        call_user_func($cb['function']);
                    }
                }
            }
        }
        $robots_content = ob_get_clean();
        // Chercher la fin du bloc YOAST
        $yoast_end = strpos($robots_content, '# END YOAST BLOCK');
        if ($yoast_end !== false) {
            $yoast_end += strlen('# END YOAST BLOCK');
            $before = substr($robots_content, 0, $yoast_end);
            $after = substr($robots_content, $yoast_end);
            echo esc_html($before) . "\n\n";
        } else {
            echo esc_html($robots_content);
            if (trim($robots_content) !== '') echo "\n\n";
        }
        // Bloc AlloIA
        echo "# Start AlloIA block\n";
        if ($subdomain) {
            echo "# Crawl " . esc_html($subdomain) . " for content optimized for AI\n";
        }
        
        // AI Search/Browsing Bots - Always Allow (these help users find your content)
        $search_bots = [
            'ChatGPT-User',           // OpenAI - ChatGPT browsing
            'OAI-SearchBot',          // OpenAI - ChatGPT search
            'claude-web',             // Anthropic - Claude web interface
            'PerplexityBot',          // Perplexity AI search
            'YouBot',                 // You.com search
            'DuckAssistBot',          // DuckDuckGo AI assistant
            'meta-externalagent',     // Meta/Facebook
            'meta-externalfetcher',   // Meta/Facebook
            'facebookexternalhit',    // Meta/Facebook
            'Googlebot',              // Google search
            'Applebot'                // Apple Siri (regular)
        ];
        
        echo "# AI Search & Browsing Bots (Always Allowed)\n";
        foreach ($search_bots as $bot) {
            echo "User-agent: " . esc_html($bot) . "\nAllow: /\n";
        }
        echo "\n";
        
        // AI Training Bots - Controlled by user setting
        $training_bots = [
            'GPTBot',                 // OpenAI - GPT training
            'ClaudeBot',              // Anthropic - Claude training  
            'anthropic-ai',           // Anthropic - AI training
            'CCBot',                  // Common Crawl
            'Google-Extended',        // Google - Gemini AI training
            'Googlebot-extended',     // Google - Extended AI features
            'GrokBot',                // xAI - Grok training
            'xAI-Grok',              // xAI - Grok AI
            'Grok-DeepSearch',        // xAI - Grok advanced search
            'Applebot-Extended',      // Apple Intelligence training
            'cohere-ai'               // Cohere AI training
        ];
        
        echo "# AI Training Bots (User Controlled)\n";
        foreach ($training_bots as $bot) {
            echo "User-agent: " . esc_html($bot) . "\n";
            echo ($llm_training === 'allow') ? "Allow: /\n" : "Disallow: /\n";
        }
        echo "# End AlloIA block\n";
    }

    /**
     * Filter robots.txt content using WordPress's built-in filter
     */
    public function filter_robots_txt($output, $public) {
        if ($public == '0') {
            return $output; // Don't modify if site is not public
        }

        $llm_training = get_option('alloia_llm_training', 'allow');
        $subdomain = get_option('alloia_subdomain', '');
        
        // Add AlloIA block to robots.txt
        $alloia_content = "\n# Start AlloIA block\n";
        if ($subdomain) {
            $alloia_content .= "# Crawl " . esc_html($subdomain) . " for content optimized for AI\n";
        }
        
        // AI Search/Browsing Bots - Always Allow (these help users find your content)
        $search_bots = [
            'ChatGPT-User',           // OpenAI - ChatGPT browsing
            'OAI-SearchBot',          // OpenAI - ChatGPT search
            'claude-web',             // Anthropic - Claude web interface
            'PerplexityBot',          // Perplexity AI search
            'YouBot',                 // You.com search
            'DuckAssistBot',          // DuckDuckGo AI assistant
            'meta-externalagent',     // Meta/Facebook
            'meta-externalfetcher',   // Meta/Facebook
            'facebookexternalhit',    // Meta/Facebook
            'Googlebot',              // Google search
            'Applebot'                // Apple Siri (regular)
        ];
        
        $alloia_content .= "# AI Search & Browsing Bots (Always Allowed)\n";
        foreach ($search_bots as $bot) {
            $alloia_content .= "User-agent: $bot\nAllow: /\n";
        }
        $alloia_content .= "\n";
        
        // AI Training Bots - Controlled by user setting
        $training_bots = [
            'GPTBot',                 // OpenAI - GPT training
            'ClaudeBot',              // Anthropic - Claude training  
            'anthropic-ai',           // Anthropic - AI training
            'CCBot',                  // Common Crawl
            'Google-Extended',        // Google - Gemini AI training
            'Googlebot-extended',     // Google - Extended AI features
            'GrokBot',                // xAI - Grok training
            'xAI-Grok',              // xAI - Grok AI
            'Grok-DeepSearch',        // xAI - Grok advanced search
            'Applebot-Extended',      // Apple Intelligence training
            'cohere-ai'               // Cohere AI training
        ];
        
        $alloia_content .= "# AI Training Bots (User Controlled)\n";
        foreach ($training_bots as $bot) {
            $alloia_content .= "User-agent: $bot\n";
            $alloia_content .= ($llm_training === 'allow') ? "Allow: /\n" : "Disallow: /\n";
        }
        $alloia_content .= "# End AlloIA block\n";
        
        return $output . $alloia_content;
    }

    // --- Analytics Integration Scaffolding ---

    // Fetch WooCommerce stats (basic implementation)
    private function get_woocommerce_stats($single_product_id = null) {
        if (!class_exists('WooCommerce')) {
            return array('orders' => 0, 'products' => 0, 'revenue' => 0.0);
        }
        $orders = 0;
        $revenue = 0.0;
        $products = 0;
        if ($single_product_id) {
            // Get stats for a single product
            $args = array(
                'status' => array('wc-completed'),
                'limit' => -1,
                'return' => 'ids',
            );
            $order_ids = wc_get_orders($args);
            foreach ($order_ids as $order_id) {
                $order = wc_get_order($order_id);
                foreach ($order->get_items() as $item) {
                    if ($item->get_product_id() == $single_product_id) {
                        $orders++;
                        $revenue += $item->get_total();
                    }
                }
            }
            $products = 1;
        } else {
            // Site-wide stats
            $args = array(
                'status' => array('wc-completed'),
                'limit' => -1,
                'return' => 'ids',
            );
            $order_ids = wc_get_orders($args);
            $orders = count($order_ids);
            foreach ($order_ids as $order_id) {
                $order = wc_get_order($order_id);
                $revenue += $order->get_total();
            }
            $products = wp_count_posts('product')->publish;
        }
        return array('orders' => $orders, 'products' => $products, 'revenue' => $revenue);
    }

    // Fetch AlloIA API analytics (scaffold)
    private function get_alloia_analytics() {
        $api_key = get_option('alloia_api_key', '');
        if (!$api_key) {
            return array('sessions' => 0, 'conversion_rate' => 0.0);
        }
        // Note: Analytics API integration available via unified API client
        // Example: $response = wp_remote_get('https://api.alloia.ai/analytics?key=' . urlencode($api_key));
        // Parse and return data as needed
        return array('sessions' => 0, 'conversion_rate' => 0.0);
    }

    // Redirect AI bots to ai.<domain> when enabled and method is php/wp
    public function maybe_redirect_ai_bots() {
        // Only on frontend
        if (is_admin()) return;
        if (defined('DOING_AJAX') && DOING_AJAX) return;

        $enabled = (bool) get_option('ai_redirect_enabled', false);
        $method = get_option('ai_server_type', '');
        if (!$enabled || !in_array($method, array('php', 'wp'), true)) return;

        // Avoid loops on ai. subdomain
        $current_host = isset($_SERVER['HTTP_HOST']) ? strtolower(sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST']))) : '';
        $root_host = wp_parse_url(home_url(), PHP_URL_HOST);
        $ai_host = 'ai.' . $root_host;
        if ($current_host === strtolower($ai_host)) return;

        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
        if ($ua === '') return;

        $bots = array_unique(array_merge($this->get_search_bots_list(), $this->get_training_bots_list()));
        foreach ($bots as $bot) {
            if (stripos($ua, $bot) !== false) {
                $request_uri = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '/';
                $target = 'https://' . $ai_host . $request_uri;
                wp_redirect($target, 302);
                exit;
            }
        }
    }

    // Add or remove Apache .htaccess redirect rules
    public function update_apache_htaccess_rules($method) {
        $enabled = (get_option('ai_redirect_enabled', false) && $method === 'apache');
        $htaccess = ABSPATH . '.htaccess';
        if (!file_exists($htaccess)) {
            if ($enabled) {
                add_action('admin_notices', function(){
                    echo '<div class="notice notice-warning is-dismissible"><p>.htaccess not found. Please add the Apache rules manually.</p></div>';
                });
            }
            return false;
        }
        $contents = @file_get_contents($htaccess);
        if ($contents === false) return false;

        $marker_start = "# BEGIN ALLOIA REDIRECT";
        $marker_end   = "# END ALLOIA REDIRECT";
        // Remove existing block
        $pattern = '/' . preg_quote($marker_start,'/') . '[\s\S]*?' . preg_quote($marker_end,'/') . '/';
        $contents = preg_replace($pattern, '', $contents);

        if ($enabled) {
            $root_host = wp_parse_url(home_url(), PHP_URL_HOST);
            $ai_host = 'ai.' . $root_host;
            $bots = array_unique(array_merge($this->get_search_bots_list(), $this->get_training_bots_list()));
            $bots_regex = implode('|', array_map(function($b){ return preg_quote($b, '/'); }, $bots));
            $block  = $marker_start . "\n";
            $block .= "RewriteEngine On\n";
            $block .= "RewriteCond %{HTTP_HOST} !^" . str_replace('.', '\\.', $ai_host) . "$ [NC]\n";
            $block .= "RewriteCond %{HTTP_USER_AGENT} (" . $bots_regex . ") [NC]\n";
            $block .= "RewriteRule ^(.*)$ https://" . $ai_host . "/$1 [R=302,L]\n";
            $block .= $marker_end . "\n";

            // Prepend block so it runs early
            $contents = $block . $contents;
        }

        @file_put_contents($htaccess, $contents);
        return true;
    }

    // --- Audits and AI-Ready Score ---

    /**
     * Public accessor: compute and return current AI-ready score with breakdown
     */
    public function get_ai_ready_score() {
        return $this->compute_ai_ready_score();
    }

    /**
     * Public accessor: run robots/sitemap audit and return results
     */
    public function get_robots_audit() {
        return $this->audit_robots_and_sitemap();
    }

    /**
     * Hourly cron task to refresh audits and cache score
     */
    public function run_hourly_audit() {
        $audit = $this->audit_robots_and_sitemap();
        update_option('alloia_last_robots_audit', $audit);
        $score = $this->compute_ai_ready_score($audit);
        update_option('alloia_ai_ready_score', $score);
    }

    /**
     * Compute AI-ready score based on simple rules. Accepts optional $audit to avoid duplicate network calls.
     */
    private function compute_ai_ready_score($audit = null) {
        $breakdown = array();

        if ($audit === null) {
            $audit = $this->audit_robots_and_sitemap();
        }

        // Tests considered for the score
        $tests = array(
            'sitemap' => !empty($audit['sitemap_exists']),
            'robots' => !empty($audit['robots_exists']),
            'llms' => !empty($audit['llms_exists']),
            'alloia_block' => !empty($audit['alloia_block_present']),
            // robots allows AI search/browsing bots
            'robots_ai_allow' => (isset($audit['search_bots_allowed'], $audit['search_bots_total']) && $audit['search_bots_total'] > 0) ? ($audit['search_bots_allowed'] === $audit['search_bots_total']) : false,
            // Graph enabled if OSS (ai.) or Pro (xseek site id)
            'graph' => !empty($audit['graph_enabled'])
        );

        $passes = 0;
        foreach ($tests as $key => $ok) {
            if ($ok) { $passes++; }
            $breakdown[$key] = array('ok' => (bool) $ok);
        }
        $total = count($tests);
        $percentage = $total > 0 ? round(($passes / $total) * 100) : 0;

        // Include robots AI block summary in breakdown
        if (isset($audit['training_bots_blocked'], $audit['training_bots_total'])) {
            $breakdown['robots_ai_blocked'] = array(
                'blocked' => intval($audit['training_bots_blocked']),
                'total' => intval($audit['training_bots_total'])
            );
        }

        return array(
            'score' => $percentage,
            'max' => 100,
            'percentage' => $percentage,
            'breakdown' => $breakdown,
            'timestamp' => current_time('mysql')
        );
    }

    /**
     * Fetch and audit robots.txt and sitemap existence; detect AlloIA block presence.
     */
    private function audit_robots_and_sitemap() {
        $home = home_url('/');
        $robots_url = home_url('/robots.txt');
        $sitemap_url = home_url('/sitemap.xml');
        $llms_url = home_url('/llms.txt');

        $robots_resp = wp_remote_get($robots_url, array('timeout' => 10, 'redirection' => 5));
        $robots_body = (is_wp_error($robots_resp)) ? '' : wp_remote_retrieve_body($robots_resp);
        $robots_ok_http = !is_wp_error($robots_resp) && (wp_remote_retrieve_response_code($robots_resp) >= 200 && wp_remote_retrieve_response_code($robots_resp) < 400);
        $robots_ok_file = file_exists(ABSPATH . 'robots.txt');
        $robots_ok = $robots_ok_http || $robots_ok_file;
        $sitemap_ok = $this->url_exists($sitemap_url);
        $llms_ok_http = $this->url_exists($llms_url);
        $llms_ok_file = file_exists(ABSPATH . 'llms.txt');
        $llms_ok = $llms_ok_http || $llms_ok_file;

        $alloia_block = false;
        if ($robots_body === '' && $robots_ok_file) {
            // Read from filesystem if not via HTTP
            $robots_body = @file_get_contents(ABSPATH . 'robots.txt');
        }
        if (is_string($robots_body) && $robots_body !== '') {
            $alloia_block = (strpos($robots_body, '# Start AlloIA block') !== false);
        }

        // Count training bots explicitly disallowed in robots.txt
        $training_bots = $this->get_training_bots_list();
        $blocked = 0;
        if ($robots_ok && $robots_body) {
            foreach ($training_bots as $bot) {
                // Look for a user-agent section that disallows all
                $pattern = '/User-agent:\s*' . preg_quote($bot, '/') . '\s*(?:\r?\n)+[^U]*?(Disallow:\s*\/)/i';
                if (preg_match($pattern, $robots_body)) {
                    $blocked++;
                }
            }
        }

        // Count search/browsing bots explicitly allowed in robots.txt
        $search_bots = $this->get_search_bots_list();
        $allowed = 0; $search_total = count($search_bots);
        if ($robots_ok && $robots_body) {
            foreach ($search_bots as $bot) {
                $patternAllow = '/User-agent:\s*' . preg_quote($bot, '/') . '\s*(?:\r?\n)+[^U]*?(Allow:\s*\/)/i';
                if (preg_match($patternAllow, $robots_body)) {
                    $allowed++;
                }
            }
        }

        // Graph enabled if ai. subdomain set or Pro site id exists
        $graph_enabled = (!empty(get_option('alloia_subdomain', '')) || !empty(get_option('alloia_tracking_website_id', '')));

        return array(
            'home' => $home,
            'robots_url' => $robots_url,
            'robots_exists' => $robots_ok,
            'sitemap_url' => $sitemap_url,
            'sitemap_exists' => $sitemap_ok,
            'llms_url' => $llms_url,
            'llms_exists' => $llms_ok,
            'alloia_block_present' => $alloia_block,
            'robots_sample' => substr($robots_body, 0, 1000),
            'training_bots_blocked' => $blocked,
            'training_bots_total' => count($training_bots),
            'search_bots_allowed' => $allowed,
            'search_bots_total' => $search_total,
            'graph_enabled' => $graph_enabled,
        );
    }

    // Returns list of AI training bot user-agents we track
    private function get_training_bots_list() {
        return array(
            'GPTBot',
            'ClaudeBot',
            'anthropic-ai',
            'CCBot',
            'Google-Extended',
            'Googlebot-extended',
            'GrokBot',
            'xAI-Grok',
            'Grok-DeepSearch',
            'Applebot-Extended',
            'cohere-ai'
        );
    }

    // Returns list of AI search/browsing bot user-agents we allow by default
    private function get_search_bots_list() {
        return array(
            'ChatGPT-User',
            'OAI-SearchBot',
            'claude-web',
            'PerplexityBot',
            'YouBot',
            'DuckAssistBot',
            'meta-externalagent',
            'meta-externalfetcher',
            'facebookexternalhit',
            'Googlebot',
            'Applebot'
        );
    }
} 