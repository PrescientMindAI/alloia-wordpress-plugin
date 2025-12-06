# Installation Guide - AlloIA for WooCommerce

## Requirements

- **WordPress:** 5.0 or higher
- **WooCommerce:** 5.0 or higher  
- **PHP:** 7.4 or higher
- **MySQL:** 5.6 or higher

## Automatic Installation (Recommended)

1. **Log into your WordPress admin area**
2. **Navigate to Plugins → Add New**
3. **Search for "AlloIA for WooCommerce"**
4. **Click "Install Now"** next to the AlloIA plugin
5. **Click "Activate"** once installation is complete

## Manual Installation

### Method 1: Upload via WordPress Admin

1. **Download** the latest plugin ZIP file from [alloia.ai](https://alloia.ai)
2. **Log into your WordPress admin area**
3. **Navigate to Plugins → Add New → Upload Plugin**
4. **Choose the ZIP file** and click "Install Now"
5. **Click "Activate"** after installation

### Method 2: FTP Upload

1. **Download and extract** the plugin ZIP file
2. **Upload the `alloia-woocommerce` folder** to `/wp-content/plugins/`
3. **Log into WordPress admin**
4. **Navigate to Plugins** and activate "AlloIA for WooCommerce"

## Initial Setup

### 1. Access Plugin Settings
- **Go to AlloIA** in your WordPress admin menu
- **You'll see two tabs:** Free Features and Pro Features

### 2. Configure Free Features (No Account Required)
- **AI Training Permissions:** Choose whether to allow AI models to train on your content
- **Robots.txt Optimization:** Automatically configures AI bot access
- **LLMS.txt Generation:** Creates machine-readable content summaries

### 3. Upgrade to Pro (Optional)
- **Click subscription links** in the Pro Features tab
- **Complete payment** on alloia.ai
- **Enter your API key** in the plugin to activate Pro features

## Verification Steps

### Confirm Installation
1. **Check AlloIA menu** appears in WordPress admin
2. **Visit Free Features tab** - should load without errors
3. **Check your site's robots.txt** (yoursite.com/robots.txt) - should include AI bot directives

### Test Core Features
1. **Generate LLMS.txt:** Should create yoursite.com/llms.txt
2. **AI-Ready Score:** Should display in Free Features dashboard
3. **Pro Features:** Should show subscription options

## Troubleshooting

### Plugin Not Appearing
- **Verify WordPress version** (5.0+ required)
- **Check WooCommerce** is installed and activated
- **Ensure sufficient permissions** (admin or manage_options capability)

### Features Not Working
- **Clear any caching** (page cache, object cache, CDN)
- **Check for plugin conflicts** by temporarily deactivating other plugins
- **Verify file permissions** on wp-content/plugins/alloia-woocommerce/

### API Key Issues (Pro Features)
- **Verify API key format** (should be alphanumeric string)
- **Check subscription status** at alloia.ai
- **Ensure internet connectivity** for API validation

### SEO Plugin Conflicts
- **AlloIA is compatible** with Yoast SEO, RankMath, and other SEO plugins
- **If robots.txt issues occur,** temporarily deactivate other SEO plugins to isolate the problem
- **Check robots.txt priority** in plugin settings

## Uninstallation

### Temporary Deactivation
1. **Go to Plugins** in WordPress admin
2. **Find "AlloIA for WooCommerce"**
3. **Click "Deactivate"** (preserves settings and data)

### Complete Removal
1. **Deactivate the plugin** first
2. **Click "Delete"** next to the plugin
3. **Confirm deletion** - this removes all plugin files and settings

**Note:** Uninstalling removes all plugin data including API keys and configuration. Export settings first if you plan to reinstall.

## Support

- **Free Support:** WordPress.org plugin forums
- **Pro Support:** [support.alloia.ai](https://support.alloia.ai)
- **Documentation:** [docs.alloia.ai](https://docs.alloia.ai)
- **Community:** [community.alloia.ai](https://community.alloia.ai)

## Next Steps

After installation:
1. **Configure your AI optimization preferences**
2. **Review your AI-Ready Score** 
3. **Consider upgrading to Pro** for advanced analytics
4. **Monitor your AI bot traffic** in the dashboard

Welcome to the future of AI-optimized e-commerce! 🚀