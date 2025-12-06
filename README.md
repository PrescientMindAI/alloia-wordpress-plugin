# AI GEO optimisation by AlloIA

## Description

This plug-in allow optimisation to be more present in AI recommendations. It provides advanced AI-powered optimization tools for WooCommerce stores, including knowledge graph integration, AI bot tracking, and intelligent product recommendations.

## Features

### 🆓 Free Features
- **AI Bot Tracking**: Monitor and analyze AI bot visits to your store
- **llms.txt Generation**: Create AI-optimized content files for better AI indexing
- **Robots.txt Management**: Optimize your robots.txt for AI crawlers
- **Basic Analytics**: Track AI bot interactions and performance metrics

### 🚀 Pro Features (Requires Subscription)
- **Knowledge Graph Export**: Export your products to AlloIA's AI knowledge graph
- **Advanced Analytics**: Detailed insights into AI-driven traffic and conversions
- **Competitor Analysis**: Monitor your competitors' AI presence
- **Prompt Management**: Optimize AI prompts for better product discovery
- **Smart Recommendations**: AI-powered product suggestions for customers

## Requirements

- **WordPress**: 5.0 or higher
- **PHP**: 7.4 or higher  
- **WooCommerce**: 5.0 or higher (for Pro features)
- **Browser**: Modern browser with JavaScript enabled

## Security

### Latest Security Updates (v1.0.1)
- ✅ **XSS Protection**: All output properly escaped with WordPress security functions
- ✅ **SQL Injection Prevention**: Database queries use proper escaping and prepared statements
- ✅ **CSRF Protection**: All AJAX endpoints secured with nonce verification
- ✅ **File Security**: Comprehensive validation for file operations
- ✅ **Input Validation**: Enhanced sanitization for all user inputs
- ✅ **Error Handling**: Secure error handling without information leakage

## Installation

### Method 1: WordPress Admin (Recommended)

1. Download the plugin ZIP file
2. Go to **WordPress Admin → Plugins → Add New**
3. Click **Upload Plugin** and select the ZIP file
4. Click **Install Now** and then **Activate Plugin**

### Method 2: Manual Installation

1. Extract the plugin ZIP file
2. Upload the `alloia-woocommerce` folder to `/wp-content/plugins/`
3. Go to **WordPress Admin → Plugins**
4. Find "AI GEO optimisation by AlloIA" and click **Activate**

## Configuration

### Initial Setup

1. **Activate the Plugin**: The plugin will automatically create necessary files and settings
2. **Access Settings**: Go to **WordPress Admin → AlloIA** in the main menu
3. **Configure Free Features**: Enable/disable AI bot tracking, llms.txt generation, and robots.txt management

### Pro Features Setup

1. **Get API Key**: Visit [AlloIA.ai](https://alloia.ai) to subscribe and get your API key
2. **Enter API Key**: Go to **AlloIA → Settings → Pro** tab and enter your API key
3. **Access Pro Features**: Once activated, you'll have access to Knowledge Graph, Analytics, and more

## Usage

### Dashboard

The main dashboard provides an overview of:
- AI bot visits and interactions
- Product performance metrics
- Recent AI activity
- Quick access to all features

### Subscription Management

- **View Plans**: See available subscription tiers
- **Manage Subscription**: Cancel, upgrade, or modify your plan
- **Usage Tracking**: Monitor API calls and product exports

### Knowledge Graph Export

- **Configure Filters**: Set product categories, price ranges, and stock status
- **Batch Processing**: Choose batch size for optimal performance
- **Background Export**: Run large exports without blocking your browser
- **Export History**: Track export status and results

### AI Bot Tracking

- **Real-time Monitoring**: See AI bots visiting your site
- **Performance Metrics**: Track AI-driven traffic patterns
- **Optimization Insights**: Get recommendations for better AI visibility

## API Integration

The plugin integrates with AlloIA's AI services through:

- **Direct API Calls**: Clean API integration
- **Rate Limiting**: Built-in protection against API limits
- **Error Handling**: Graceful fallbacks and user notifications
- **Authentication**: Secure API key management

## Support

### Documentation
- **Plugin Guide**: [AlloIA.ai/docs](https://alloia.ai/docs)
- **API Reference**: [AlloIA.ai/api](https://alloia.ai/api)

### Support Channels
- **Technical Support**: [support@alloia.ai](mailto:support@alloia.ai)
- **Community Forum**: [AlloIA.ai/community](https://alloia.ai/community)
- **Live Chat**: Available on [AlloIA.ai](https://alloia.ai)

## Changelog

### Version 1.0.1 (Latest)
- **Security fixes**: XSS, SQL injection, and CSRF protection
- **Performance improvements**: Database query optimization with pagination
- **Error handling**: Enhanced error management and user feedback
- **Code quality**: Improved validation and sanitization throughout

### Version 1.0.0
- Initial release
- Free features: AI bot tracking, llms.txt, robots.txt management
- Pro features: Knowledge graph export, advanced analytics
- WordPress 5.0+ compatibility
- WooCommerce integration

## Development

### File Structure
```
alloia-woocommerce/
├── admin/                 # Admin interface files
│   ├── assets/           # CSS, JS, and images
│   └── views/            # Admin page templates
├── includes/             # Core plugin classes
├── templates/            # Frontend templates
├── docs/                 # Documentation
├── alloia-woocommerce.php # Main plugin file
├── uninstall.php         # Uninstall script
└── README.md             # This file
```

### Hooks and Filters
The plugin provides various WordPress hooks and filters for customization:

```php
// Modify AI bot tracking settings
add_filter('alloia_bot_tracking_settings', 'my_custom_settings');

// Hook into export completion
add_action('alloia_export_completed', 'my_export_handler');

// Customize knowledge graph data
add_filter('alloia_kg_product_data', 'my_product_modifier');
```

## License

This plugin is licensed under the GPL v2 or later.

## Credits

Developed by [AlloIA](https://alloia.ai) - AI-powered optimization for e-commerce.

---

**Need help?** Contact us at [support@alloia.ai](mailto:support@alloia.ai) or visit [AlloIA.ai](https://alloia.ai) 