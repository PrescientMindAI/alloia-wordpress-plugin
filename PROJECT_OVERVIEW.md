# AlloIA WooCommerce Plugin - Project Overview

## Project Purpose

The AlloIA WooCommerce Plugin enables e-commerce stores to optimize their content for AI-powered search engines and recommendation systems. It provides both free optimization tools and premium AI analytics services through integration with the AlloIA ecosystem.

## System Architecture

### Core Components

**WordPress Plugin (`alloia-woocommerce`)**
- Main entry point and orchestration
- Free features: robots.txt optimization, LLMS.txt generation, AI-ready scoring
- Pro features: product export, analytics dashboard, AI bot tracking
- Manual API key entry for premium service activation

**External Services**
- `alloia.ai` - Marketing website and subscription management
- `alloia.io` - Knowledge Graph API and analytics backend
- `stripe.com` - Payment processing (handled by alloia.ai)

### Plugin Structure

**Main Classes:**
- `AlloIA_WooCommerce` - Main plugin orchestration
- `AlloIA_Core` - Core functionality (robots.txt, LLMS.txt, tracking)
- `AlloIA_Admin` - WordPress admin interface and settings
- `AlloIA_API_Client` - Communication with alloia.io services
- `AlloIA_Subscription` - Subscription management (minimal)
- `AlloIA_Knowledge_Graph` - Product export and graph operations

**File Organization:**
- `/includes/` - Core PHP classes
- `/admin/views/` - WordPress admin templates
- `/admin/assets/` - CSS and JavaScript files
- `/templates/` - Public-facing templates (robots.txt, LLMS.txt)
- `/docs/` - Documentation (to be replaced by this document)

## Feature Tiers

### Free Features
- **Robots.txt Optimization** - Configures AI bot permissions with compatibility for Yoast SEO and other plugins
- **LLMS.txt Generation** - Creates machine-readable content summaries
- **AI Training Permissions** - User control over AI model training access
- **AI-Ready Score** - Evaluates site optimization for AI discovery
- **Basic Dashboard** - Simple analytics and configuration interface

### Pro Features (Requires Subscription)
- **Product Export** - WooCommerce catalog integration with Knowledge Graph
- **Advanced Analytics** - AI bot traffic analysis and competitive insights
- **Priority Support** - Enhanced customer service
- **Automated Optimizations** - AI-driven content and metadata enhancements

## Integration Flows

### Subscription Flow
1. User clicks subscription link in plugin (redirects to alloia.ai)
2. alloia.ai handles payment processing via Stripe
3. alloia.ai creates client account in alloia.io Knowledge Graph
4. alloia.ai displays API key to user
5. User manually enters API key in WordPress plugin
6. Plugin validates API key with alloia.io and enables Pro features

### Content Optimization Flow
1. Plugin analyzes WooCommerce products and site structure
2. Generates optimized robots.txt with AI bot permissions
3. Creates LLMS.txt with structured content summaries
4. For Pro users: exports product data to Knowledge Graph
5. Displays analytics and recommendations in WordPress dashboard

### AI Bot Detection Flow
1. Plugin injects tracking code in site header
2. JavaScript detects AI bot visits based on user agent patterns
3. For Pro users: sends analytics data to alloia.io
4. Dashboard displays AI bot traffic and engagement metrics

## External Dependencies

### Required WordPress Dependencies
- WordPress 5.0+ (core functionality)
- WooCommerce 5.0+ (product features)
- PHP 7.4+ (language requirement)

### Optional Plugin Compatibility
- Yoast SEO (robots.txt integration)
- RankMath SEO (robots.txt integration)
- Other SEO plugins via WordPress hooks

### External Service Dependencies
- **alloia.ai** - Subscription management and user onboarding
- **alloia.io** - Knowledge Graph API, analytics, and pro features
- **stripe.com** - Payment processing (via alloia.ai integration)

## Data Architecture

### WordPress Options Storage
- `alloia_api_key` - User's API key for pro features
- `alloia_subdomain` - AI-optimized subdomain configuration
- `alloia_llm_training` - AI training permission setting
- `alloia_tracking_website_id` - AlloIA tracking service identifier
- `alloia_tracking_api_key` - AlloIA tracking API credentials

### Product Data Structure
- Standard WooCommerce product fields
- Category hierarchies and taxonomies
- Product relationships (cross-sells, upsells, variations)
- Metadata for AI optimization (descriptions, attributes, images)

### Knowledge Graph Export Format
- Product nodes with comprehensive metadata
- Category hierarchy relationships
- Brand/manufacturer entities
- Trust indicators (reviews, certifications)
- Semantic relationships between products

## Security Model

### Access Control
- WordPress capability checks (`manage_options`)
- Nonce verification for all form submissions
- Input sanitization using WordPress functions
- Output escaping for all dynamic content

### API Security
- Bearer token authentication with alloia.io
- API key storage in WordPress options (encrypted at rest)
- No sensitive credentials in plugin code
- Secure communication over HTTPS

### Content Security
- XSS prevention via `wp_kses()` for tracking code
- Path validation for file operations
- Host header injection protection
- SQL injection prevention via WordPress APIs

## Compatibility Strategy

### SEO Plugin Compatibility
- Multiple robots.txt hook strategies for maximum compatibility
- Yoast SEO specific integration (detects and appends to Yoast blocks)
- Standard WordPress `robots_txt` filter usage
- Non-conflicting priority levels for hooks

### WordPress Ecosystem
- Follows WordPress coding standards
- Uses WordPress APIs exclusively
- Proper plugin activation/deactivation hooks
- Multisite compatibility considerations

### Theme Compatibility
- No theme-specific dependencies
- Uses WordPress hooks for content injection
- Responsive admin interface
- Standard WordPress styling conventions

## Performance Considerations

### Optimization Strategies
- Conditional loading of pro features
- Lazy loading of analytics data
- Caching of API responses
- Efficient database queries via WordPress APIs

### Resource Management
- Minimal JavaScript footprint
- CSS loaded only on relevant admin pages
- Background processing for large product exports
- Rate limiting for external API calls

## Internationalization

### Language Support
- All user-facing strings wrapped in translation functions
- Text domain: `alloia-woocommerce`
- Ready for WordPress.org translation system
- French and English language priorities

### Regional Considerations
- Currency handling for different markets
- Timezone awareness for analytics
- Compliance with regional data protection laws
- Localized content and messaging
