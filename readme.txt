=== AlloIA for WooCommerce - AI-Powered Commerce ===
Contributors: alloia, prescientmind
Tags: ai, woocommerce, seo, robots-txt, ai-optimization
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.7.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Transform your WooCommerce store for the AI era. AI-ready product catalog, smart robots.txt management, and seamless AlloIA platform integration.

== Description ==

**AlloIA for WooCommerce** helps e-commerce stores optimize their content for AI search engines and recommendation systems. The plugin provides free optimization tools and seamless integration with the AlloIA AI Commerce platform.

= 🆓 Free Features =

* **AI Readiness Analysis** - Get your AI optimization score and recommendations
* **Smart robots.txt Generation** - Manage AI bot permissions with one click
* **AI Training Control** - Choose whether AI models can train on your content
* **SEO Plugin Compatible** - Works with Yoast SEO, RankMath, and others

= ⭐ AI Commerce Platform =

* **Knowledge Graph Integration** - Sync your product catalog to the AlloIA Knowledge Graph for enhanced AI search visibility
* **Product Synchronization** - Automatic product export and updates
* **Real-time Analytics** - Track AI bot visits and engagement
* **Priority Support** - Dedicated technical assistance

= How It Works =

1. **Install & Activate** - Free features work immediately
2. **Configure AI Permissions** - Set which AI bots can access your content  
3. **Get Your API Key** - Sign up at [alloia.ai](https://alloia.ai) for AI Commerce features
4. **Sync Your Products** - Export your catalog to the AlloIA Knowledge Graph with one click
5. **Monitor Performance** - Track how AI systems interact with your store

= AI Bot Compatibility =

The plugin optimizes for major AI systems including:
* ChatGPT (OpenAI) - Search and browsing
* Claude (Anthropic) - Web interface
* Perplexity AI - AI search engine
* Google Gemini - AI assistant
* Bing Copilot - Microsoft AI
* And 20+ more AI crawlers

= SEO Plugin Compatibility =

Seamlessly integrates with:
* Yoast SEO
* RankMath
* All in One SEO
* SEOPress
* And more

= Use Cases =

* **E-commerce SEO** - Optimize for AI-powered search engines
* **Product Discovery** - Make your products findable by AI shopping assistants
* **Content Protection** - Control which AI models can train on your content
* **Analytics** - Understand how AI bots interact with your store
* **Future-Proofing** - Prepare for the AI-driven commerce era

= Technical Requirements =

* WordPress 5.8 or higher
* WooCommerce 5.0 or higher
* PHP 7.4 or higher
* HTTPS recommended for API communication

= Privacy & Data =

* Free features run entirely on your server
* AI Commerce features send product data to AlloIA platform via secure API
* No personal customer data is transmitted
* GDPR and CCPA compliant
* Full data control and deletion options

= Support & Documentation =

* [Plugin Documentation](https://github.com/PrescientMindAI/alloia-wordpress-plugin)
* [AlloIA Platform](https://alloia.ai)
* [API Documentation](https://www.alloia.io/api/docs)
* [Support Forum](https://alloia.ai/support)

== Installation ==

= Automatic Installation =

1. Log in to your WordPress admin panel
2. Navigate to Plugins > Add New
3. Search for "AlloIA for WooCommerce"
4. Click "Install Now" and then "Activate"

= Manual Installation =

1. Download the plugin ZIP file
2. Log in to your WordPress admin panel
3. Navigate to Plugins > Add New > Upload Plugin
4. Choose the ZIP file and click "Install Now"
5. Activate the plugin

= After Activation =

1. Go to **AlloIA > Free Tools** to configure AI bot permissions
2. Click "Generate robots.txt" to optimize for AI crawlers
3. (Optional) Visit [alloia.ai](https://alloia.ai) to get your API key
4. Go to **AlloIA > AI Commerce Platform** and enter your API key
5. Click "Sync Products" to export your catalog to the Knowledge Graph

== Frequently Asked Questions ==

= Is this plugin free? =

Yes! Core features (robots.txt management, AI permissions) are completely free. The AI Commerce Platform features (Knowledge Graph integration, analytics) require an AlloIA account.

= Do I need an AlloIA account? =

No, the free features work without an account. However, to sync your products to the AlloIA Knowledge Graph and access analytics, you'll need to sign up at [alloia.ai](https://alloia.ai).

= What is the AlloIA Knowledge Graph? =

The AlloIA Knowledge Graph is an AI-ready product database that makes your products discoverable by AI shopping assistants, search engines, and recommendation systems.

= Will this affect my existing SEO? =

No! The plugin is designed to complement your existing SEO setup. It works alongside Yoast SEO, RankMath, and other SEO plugins without conflicts.

= What data is sent to AlloIA? =

Only product information (name, description, price, images, SKU) is sent when you sync products. No customer data, orders, or personal information is transmitted.

= Can I delete my data from AlloIA? =

Yes! You can delete all your data from the AlloIA platform at any time through your account settings or by contacting support.

= Does this work with variable products? =

Yes! The plugin syncs all product types including simple, variable, grouped, and virtual products.

= How often are products synced? =

Products are synced when you click "Sync Products" or automatically when products are created/updated (if auto-sync is enabled in settings).

= Is this GDPR compliant? =

Yes! The plugin is designed with privacy in mind and complies with GDPR, CCPA, and other data protection regulations.

= What happens if I deactivate the plugin? =

Free features will stop working immediately. Your products will remain in the AlloIA Knowledge Graph until you manually delete them from your AlloIA account.

= Can I use this on multiple stores? =

Yes! Each store needs its own API key. Contact sales for multi-store pricing.

== Screenshots ==

1. Free Tools - Manage AI bot permissions and generate robots.txt
2. AI Commerce Platform - Product synchronization dashboard
3. API Key Management - Connect to AlloIA platform
4. Product Sync Status - Real-time sync monitoring

== Changelog ==

= 1.7.1 - 2025-12-06 =
* **Major Cleanup:** Removed 2,379 lines of legacy code
* **Added:** GitHub auto-update system for seamless updates
* **Added:** Auto-delete static llms.txt file (enables dynamic serving)
* **Added:** Auto-flush rewrite rules on version update
* **Fixed:** Product sync now correctly uses SKU as primary identifier
* **Fixed:** API validation and domain checks streamlined
* **Improved:** Plugin now serves llms.txt dynamically
* **Removed:** Legacy subscription UI (moved to alloia.ai portal)
* **Removed:** Debug logging from production UI
* **Performance:** Faster, cleaner codebase (-21% lines)

= 1.7.0 - 2025-12-05 =
* **Emergency Hotfix:** Domain validation bypass for immediate client use
* **Fixed:** Product sync for WooCommerce and Shopify
* **Fixed:** API authentication and error handling
* **Fixed:** Product upsert logic (SKU-first identification)
* **Improved:** Error logging and debugging capabilities

= 1.2.0 - 2024-09-26 =
* Initial public release
* Free tools: robots.txt generation, AI permissions
* AI Commerce platform integration
* Product synchronization to Knowledge Graph
* Real-time analytics dashboard

== Upgrade Notice ==

= 1.7.1 =
Major cleanup and improvements! Auto-update system added. Please re-save your API key after updating to ensure full functionality.

= 1.7.0 =
Emergency hotfix for product sync. Update recommended for all users.

== Third-Party Services ==

This plugin connects to the following third-party services:

= AlloIA API (alloia.io) =
* Used for: Product synchronization, API key validation, analytics
* Terms of Service: https://alloia.ai/terms
* Privacy Policy: https://alloia.ai/privacy
* Data sent: Product information (name, description, price, SKU, images)
* When: Only when you explicitly sync products

= GitHub API (github.com) =
* Used for: Plugin auto-updates
* Only when: WordPress checks for plugin updates
* No personal data transmitted

No third-party services are contacted without your explicit action (clicking "Sync Products" or WordPress auto-update checks).
