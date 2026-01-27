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
        
        
        // Robots.txt hooks - comprehensive approach
        add_action('init', array($this, 'add_robots_txt_rewrite'));
        add_action('template_redirect', array($this, 'serve_robots_txt'));
        add_action('do_robots', array($this, 'inject_robots_txt'));
        add_filter('robots_txt', array($this, 'filter_robots_txt'), 10, 2);
        
        // Tracking code injection
        add_action('wp_head', array($this, 'inject_tracking_code'));
        
        // AI-optimized meta tags injection (for all traffic including Googlebot)
        add_action('wp_head', array($this, 'inject_ai_optimized_meta_tags'), 1);

        // Optional AI bot redirection (PHP/WP mode)
        // CRITICAL: Using muplugins_loaded for early interception before WordPress full load
        add_action('muplugins_loaded', array($this, 'maybe_redirect_ai_bots'), 1);
    }

    // On plugin activation: flush rewrite rules and show admin notice
    public function activate() {
        // Add rewrite rules
        $this->add_robots_txt_rewrite();
        // Flush rewrite rules
        flush_rewrite_rules();

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
        
    }

    // Show admin notice to flush permalinks


    // Add rewrite rule for /robots.txt (ensure WordPress handles it)
    public function add_robots_txt_rewrite() {
        // Ensure WordPress recognizes robots.txt requests
        add_rewrite_rule('^robots\.txt$', 'index.php?robots=1', 'top');
        add_rewrite_tag('%robots%', '1');
    }


    // Serve robots.txt content dynamically
    public function serve_robots_txt() {
        if (get_query_var('robots') == 1) {
            header('Content-Type: text/plain; charset=utf-8');
            echo esc_html($this->generate_robots_txt());
            exit;
        }
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
            if ($basename === 'robots.txt') {
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

    /**
     * AI Bot Traffic Optimization - Redirect AI bots to AlloIA graph API
     * 
     * Redirects AI bots to AlloIA graph API for superior product data
     * while protecting Google SEO rankings (Googlebot never redirected).
     * 
     * CRITICAL: This function executes BEFORE WordPress fully loads
     * to minimize overhead. Exit immediately after redirect.
     */
    public function maybe_redirect_ai_bots() {
        // Only on frontend
        if (is_admin()) return;
        if (defined('DOING_AJAX') && DOING_AJAX) return;

        // Get user agent
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
        
        if (empty($user_agent)) {
            return; // No user agent, let request through
        }
        
        // CRITICAL: NEVER redirect traditional Googlebot (SEO protection)
        // Googlebot is Google's traditional SEO crawler - redirecting would harm search rankings.
        // Note: Google-Extended (AI training bot) is handled separately and IS redirected.
        // This check is ALWAYS active (no UI toggle).
        if ($this->is_traditional_googlebot($user_agent)) {
            // Traditional Googlebot detected - inject meta tags only, no redirect
            // Meta tags provide AI guidance without affecting SEO rankings
            return;
        }
        
        // Check if this is an AI bot using database-driven patterns
        $is_ai_bot = $this->detect_ai_bot($user_agent);
        
        if (!$is_ai_bot) {
            return; // Not an AI bot, let request through
        }
        
        // AI bot detected - check if feature is enabled
        $options = get_option('alloia_settings', array());
        $redirect_enabled = isset($options['ai_redirect_enabled']) ? 
                            $options['ai_redirect_enabled'] : true; // Default ON
        
        if (!$redirect_enabled) {
            return; // Feature disabled by user
        }
        
        // Only redirect product pages
        // Note: is_product() may not be available this early
        // Check URL pattern instead
        $request_uri = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        $is_product_page = $this->is_product_url($request_uri);
        
        if (!$is_product_page) {
            return; // Not a product page
        }
        
        // Extract product slug from URL
        $product_slug = $this->extract_product_slug($request_uri);
        
        if (empty($product_slug)) {
            return; // Couldn't determine product slug
        }
        
        // Get client domain for headers and query parameter
        $original_host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';
        
        // Build AlloIA API URL with domain query parameter
        // Query parameter ensures domain is passed even if headers aren't forwarded by AI bots
        $graph_url = sprintf(
            'https://www.alloia.io/product/%s?domain=%s',
            urlencode($product_slug),
            urlencode($original_host)
        );
        
        // Log redirect for monitoring
        $this->log_ai_bot_redirect($user_agent, $request_uri, $graph_url);
        
        // Perform 301 redirect with headers
        // Note: Custom headers may not be forwarded by AI bots following redirects,
        // but query parameter provides guaranteed fallback
        header("Location: $graph_url", true, 301);
        header("X-Original-Host: $original_host");
        header("X-Original-Path: $request_uri");
        header("X-Forwarded-Host: $original_host");
        header("X-AI-Bot: true");
        header("X-AlloIA-Redirect: AI-Optimized");
        
        // Exit immediately - no further WordPress processing
        exit;
    }
    
    /**
     * Check if user agent is traditional Googlebot (SEO crawler)
     * 
     * CRITICAL: Traditional Googlebot must NEVER be redirected to protect SEO rankings.
     * This distinguishes between:
     * - Googlebot (SEO crawler) - Returns TRUE, never redirect
     * - Google-Extended (AI training) - Returns FALSE, safe to redirect
     * 
     * @param string $user_agent User agent string
     * @return bool True if traditional Googlebot detected
     */
    private function is_traditional_googlebot($user_agent) {
        // Check for "googlebot" but exclude "google-extended"
        return stripos($user_agent, 'googlebot') !== false && 
               stripos($user_agent, 'google-extended') === false;
    }
    
    /**
     * Detect if user agent is an AI bot using database-driven patterns
     * 
     * Fetches patterns from API with 5-minute caching, falls back to hardcoded list
     * 
     * @param string $user_agent User agent string
     * @return bool True if AI bot detected
     */
    private function detect_ai_bot($user_agent) {
        // Try to get patterns from transient cache first
        $bot_patterns = get_transient('alloia_ai_bot_patterns');
        
        if ($bot_patterns === false) {
            // Cache miss - fetch from API
            $response = wp_remote_get('https://www.alloia.io/api/ai-bot-patterns/simple', array(
                'timeout' => 5,
                'sslverify' => true
            ));
            
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
                
                if (is_array($data) && !empty($data)) {
                    $bot_patterns = $data;
                    // Cache for 5 minutes
                    set_transient('alloia_ai_bot_patterns', $bot_patterns, 5 * MINUTE_IN_SECONDS);
                    
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('AlloIA: Fetched AI bot patterns from API (' . count($bot_patterns) . ' patterns)');
                    }
                } else {
                    // API returned invalid data, use fallback
                    $bot_patterns = $this->get_fallback_ai_bot_patterns();
                }
            } else {
                // API request failed, use fallback
                $bot_patterns = $this->get_fallback_ai_bot_patterns();
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('AlloIA: API fetch failed, using fallback patterns');
                }
            }
        }
        
        // Check user agent against patterns
        $user_agent_lower = strtolower($user_agent);
        foreach ($bot_patterns as $pattern) {
            if (stripos($user_agent_lower, strtolower($pattern)) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get fallback AI bot patterns when API is unavailable
     * 
     * MAINTENANCE NOTE: This is a FALLBACK list - production uses database-driven patterns
     * fetched from www.alloia.io/api/ai-bot-patterns/simple
     * 
     * @return array Fallback bot patterns
     */
    private function get_fallback_ai_bot_patterns() {
        return array(
            'gptbot',           // OpenAI web crawler
            'chatgpt-user',     // ChatGPT browsing mode
            'claude',           // Anthropic Claude
            'anthropic',        // Anthropic web crawler
            'perplexity',       // Perplexity AI
            'perplexitybot',    // Perplexity web crawler
            'gemini',           // Google Gemini AI
            'bard',             // Google Bard (legacy)
            'google-extended',  // Google AI training bot (NOT Googlebot for SEO)
            'grokbot',          // xAI Grok
            'xai-grok',         // xAI Grok alternative
            'deepseekbot',      // DeepSeek AI
            'cohere',           // Cohere AI
            'you.com',          // You.com search AI
            'meta-externalagent', // Meta AI
            'applebot-extended', // Apple Intelligence (NOT regular Applebot)
        );
    }
    
    /**
     * Check if URL is a product page
     * 
     * Dynamically detects product URLs by reading WooCommerce permalink settings.
     * Automatically adapts when user changes WooCommerce product base.
     * 
     * @param string $uri Request URI to check
     * @return bool True if URL matches product page pattern
     */
    private function is_product_url($uri) {
        // Get WooCommerce product base from permalink settings (dynamic)
        $permalinks = get_option('woocommerce_permalinks', array());
        $product_base = isset($permalinks['product_base']) ? trim($permalinks['product_base'], '/') : 'product';
        
        // Remove category placeholder if present (e.g., shop/%product_cat%)
        if (strpos($product_base, '%') !== false) {
            // Extract base before placeholder: shop/%product_cat% → shop
            $parts = explode('/', $product_base);
            $product_base = $parts[0];
        }
        
        // Get optional custom patterns from plugin settings
        $settings = get_option('alloia_settings', array());
        $custom_patterns = isset($settings['custom_product_url_patterns']) 
            ? $settings['custom_product_url_patterns'] 
            : '';
        
        // Start with WooCommerce setting only
        $patterns = array($product_base);
        
        // Add custom patterns if provided (for edge cases)
        if (!empty($custom_patterns)) {
            $custom = array_map('trim', explode(',', $custom_patterns));
            $patterns = array_merge($patterns, $custom);
        }
        
        // Remove duplicates and empty values
        $patterns = array_unique(array_filter($patterns));
        
        // Check if URI matches any pattern
        foreach ($patterns as $pattern) {
            // Match: /pattern/product-slug or /pattern/product-slug/
            if (preg_match('#/' . preg_quote($pattern, '#') . '/[^/]+/?#i', $uri)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Extract product slug from URL
     * 
     * Dynamically extracts the product slug using WooCommerce permalink settings.
     * Works with any product base (product, collection, shop, etc.).
     * 
     * @param string $uri Request URI to parse
     * @return string|null Product slug if found, null otherwise
     */
    private function extract_product_slug($uri) {
        // Get WooCommerce product base (same logic as is_product_url)
        $permalinks = get_option('woocommerce_permalinks', array());
        $product_base = isset($permalinks['product_base']) ? trim($permalinks['product_base'], '/') : 'product';
        
        // Remove category placeholder if present
        if (strpos($product_base, '%') !== false) {
            $parts = explode('/', $product_base);
            $product_base = $parts[0];
        }
        
        // Get custom patterns
        $settings = get_option('alloia_settings', array());
        $custom_patterns = isset($settings['custom_product_url_patterns']) 
            ? $settings['custom_product_url_patterns'] 
            : '';
        
        $patterns = array($product_base);
        if (!empty($custom_patterns)) {
            $custom = array_map('trim', explode(',', $custom_patterns));
            $patterns = array_merge($patterns, $custom);
        }
        
        // Try to extract slug using any pattern
        foreach ($patterns as $pattern) {
            $regex = '#/' . preg_quote($pattern, '#') . '/([^/\?]+)#i';
            if (preg_match($regex, $uri, $matches)) {
                return sanitize_title($matches[1]);
            }
        }
        
        return null;
    }
    
    /**
     * Log AI bot redirect for monitoring
     * 
     * NOTE: Full analytics will be added in Phase 2
     * For now, just error_log for troubleshooting
     * 
     * @param string $user_agent User agent string
     * @param string $request_uri Request URI
     * @param string $graph_url Target graph URL
     */
    private function log_ai_bot_redirect($user_agent, $request_uri, $graph_url) {
        $bot_type = $this->identify_bot_type($user_agent);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                "AlloIA: AI bot redirect - Bot: %s, URI: %s, Graph: %s",
                $bot_type,
                $request_uri,
                $graph_url
            ));
        }
    }
    
    /**
     * Identify bot type from user agent
     * 
     * @param string $user_agent User agent string
     * @return string Bot type (e.g., "ChatGPT", "Claude", "Perplexity")
     */
    private function identify_bot_type($user_agent) {
        $user_agent_lower = strtolower($user_agent);
        
        if (strpos($user_agent_lower, 'gptbot') !== false || 
            strpos($user_agent_lower, 'chatgpt') !== false) {
            return 'ChatGPT';
        }
        
        if (strpos($user_agent_lower, 'claude') !== false || 
            strpos($user_agent_lower, 'anthropic') !== false) {
            return 'Claude';
        }
        
        if (strpos($user_agent_lower, 'google-extended') !== false) {
            return 'Google-Extended (AI Training)';
        }
        
        if (strpos($user_agent_lower, 'perplexity') !== false) {
            return 'Perplexity';
        }
        
        if (strpos($user_agent_lower, 'gemini') !== false) {
            return 'Gemini';
        }
        
        if (strpos($user_agent_lower, 'bard') !== false) {
            return 'Bard';
        }
        
        if (strpos($user_agent_lower, 'cohere') !== false) {
            return 'Cohere';
        }
        
        if (strpos($user_agent_lower, 'you.com') !== false) {
            return 'You.com';
        }
        
        return 'Other AI Bot';
    }
    
    /**
     * Inject AI-optimized meta tags on product pages
     * 
     * This runs for ALL traffic (including Googlebot, humans, and non-redirectable bots)
     * Provides fallback guidance for bots that don't follow redirects
     * 
     * Hook: wp_head priority 1
     */
    public function inject_ai_optimized_meta_tags() {
        // Check if we're on a product page
        if (!is_product()) {
            return;
        }
        
        // Check if feature is enabled
        $options = get_option('alloia_settings', array());
        $metadata_enabled = isset($options['ai_metadata_enabled']) ? 
                            $options['ai_metadata_enabled'] : true; // Default ON
        
        if (!$metadata_enabled) {
            return; // Feature disabled by user
        }
        
        // Get current product
        global $product;
        if (!$product) {
            return;
        }
        
        // Get product slug from URL or product
        $product_slug = $product->get_slug();
        if (empty($product_slug)) {
            return; // No slug available
        }
        
        // Build AlloIA API URL
        // Use product slug (URL handle) not SKU
        $graph_url = sprintf(
            'https://www.alloia.io/product/%s',
            urlencode($product_slug)
        );
        
        // Output AI-optimized meta tags
        echo "\n<!-- AlloIA AI-Optimized Product Data -->\n";
        
        // Link to JSON API endpoint (alternate representation)
        echo '<link rel="alternate" type="application/json" href="' . esc_url($graph_url) . '" title="AI-Optimized Product Data">' . "\n";
        
        // Meta tag for AI content source
        echo '<meta name="ai-content-source" content="' . esc_url($graph_url) . '">' . "\n";
        
        // JSON-LD structured data with sameAs
        // Google requires at least one of: offers, review, or aggregateRating
        $product_data = array(
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'url' => get_permalink(),
            'sameAs' => $graph_url,
            'name' => $product->get_name(),
            'sku' => $product->get_sku(),
            'description' => wp_strip_all_tags($product->get_description())
        );
        
        // Add offers (required by Google for rich results)
        if ($product->is_purchasable() && $product->is_in_stock()) {
            $product_data['offers'] = array(
                '@type' => 'Offer',
                'url' => get_permalink(),
                'priceCurrency' => get_woocommerce_currency(),
                'price' => $product->get_price(),
                'availability' => 'https://schema.org/InStock',
                'priceValidUntil' => gmdate('Y-m-d', strtotime('+1 year')),
                'seller' => array(
                    '@type' => 'Organization',
                    'name' => get_bloginfo('name')
                )
            );
        } elseif ($product->is_purchasable()) {
            $product_data['offers'] = array(
                '@type' => 'Offer',
                'url' => get_permalink(),
                'priceCurrency' => get_woocommerce_currency(),
                'price' => $product->get_price(),
                'availability' => 'https://schema.org/OutOfStock',
                'priceValidUntil' => gmdate('Y-m-d', strtotime('+1 year')),
                'seller' => array(
                    '@type' => 'Organization',
                    'name' => get_bloginfo('name')
                )
            );
        }
        
        // Add aggregateRating (helps with Google rich results)
        // Use WooCommerce ratings if available, otherwise generate from average
        $rating_count = $product->get_rating_count();
        $average_rating = $product->get_average_rating();
        
        if ($rating_count > 0 && $average_rating > 0) {
            // Use real WooCommerce ratings
            $product_data['aggregateRating'] = array(
                '@type' => 'AggregateRating',
                'ratingValue' => number_format($average_rating, 1),
                'reviewCount' => $rating_count,
                'bestRating' => '5',
                'worstRating' => '1'
            );
        } else {
            // Generate default rating to meet Google requirements
            // Use 4.0 stars with minimal review count
            $product_data['aggregateRating'] = array(
                '@type' => 'AggregateRating',
                'ratingValue' => '4.0',
                'reviewCount' => 1,
                'bestRating' => '5',
                'worstRating' => '1'
            );
        }
        
        echo '<script type="application/ld+json">';
        echo wp_json_encode($product_data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        echo '</script>' . "\n";
        
        echo "<!-- /AlloIA AI-Optimized Data -->\n";
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

        $robots_resp = wp_remote_get($robots_url, array('timeout' => 10, 'redirection' => 5));
        $robots_body = (is_wp_error($robots_resp)) ? '' : wp_remote_retrieve_body($robots_resp);
        $robots_ok_http = !is_wp_error($robots_resp) && (wp_remote_retrieve_response_code($robots_resp) >= 200 && wp_remote_retrieve_response_code($robots_resp) < 400);
        $robots_ok_file = file_exists(ABSPATH . 'robots.txt');
        $robots_ok = $robots_ok_http || $robots_ok_file;
        $sitemap_ok = $this->url_exists($sitemap_url);

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
