# AlloIA WordPress Plugin v1.7.3

**Release Date:** December 9, 2025

## 🚀 Major Enhancement: Variable Product Support

This release adds comprehensive support for WooCommerce variable products with complete variant details, enabling AI agents to accurately query and recommend specific product variations.

---

## ✨ New Features

### Variable Product Sync
- **Complete Variant Details**: Each product variation is now synced with full data including:
  - Unique variant ID and parent product reference
  - Variant-specific SKU, title, and pricing
  - Normalized and raw attribute data (colors, sizes, etc.)
  - Inventory status and quantity per variant
  - Individual variant images with fallback to parent images
  - Direct add-to-cart URLs with variant pre-selection
  - Physical properties (weight, dimensions)
  - Timestamps (created/updated dates)

### Enhanced Product Data Structure
- **Product Type Detection**: Identifies variable vs. simple products
- **Price Ranges**: Calculates min/max prices for variable products
- **Variation Count**: Tracks total number of variants
- **Attribute Aggregation**: Parent product shows all available options

### International Support
- **Accented Characters**: Full support for international variant names (e.g., "Avec un Accentégu")
- **UTF-8 Encoding**: Proper handling of special characters in all fields

---

## 🐛 Bug Fixes

### Image Collection
- **Fixed**: "Too many images (max 10)" validation error
- **Implementation**: Smart image limiting that prioritizes main product image
- **Benefit**: Variable products with many gallery images now sync successfully

### Code Quality
- **Fixed**: Parse error in `class-alloia-core.php` (escaped dollar sign)
- **Fixed**: Parse error in `class-alloia-admin.php` (unclosed if statement)
- **Fixed**: PHP warnings for undefined properties during WooCommerce initialization
- **Improved**: Error handling for products and categories when WooCommerce isn't fully loaded

### Headers Already Sent Warnings
- **Fixed**: Translation loading timing issues
- **Implementation**: Added error suppression for non-critical initialization warnings
- **Benefit**: Clean plugin activation and WooCommerce installation

---

## 🗑️ Removed

### Deprecated llms.txt Feature
- **Removed**: All llms.txt generation and serving functionality
- **Rationale**: Feature was deprecated and causing errors
- **Cleanup**: 
  - Removed rewrite rules and serving endpoints
  - Removed admin UI controls
  - Removed options from database during uninstall
  - Removed all related code and comments

---

## 🤖 AI Agent Benefits

### Precise Variant Queries
AI agents can now:
- Find specific variants by attributes (e.g., "Show me the blue size M variant")
- Get variant-specific pricing and availability
- Display correct images for each variant
- Provide direct purchase links for specific variations

### Example Queries Supported
```
"What colors does this shirt come in?"
"Show me the red variant"
"Is the large size in stock?"
"Add the blue medium to cart"
"What's the price difference between sizes?"
"Show me images of each color"
```

### Data Structure
```json
{
  "product_type": "variable",
  "has_variations": true,
  "variation_count": 3,
  "variants": [
    {
      "id": "123",
      "title": "Product Name - Blue",
      "attributes": { "color": "blue" },
      "price": 29.99,
      "images": ["..."],
      "checkout_url": "...",
      "in_stock": true
    }
  ]
}
```

---

## 📊 Technical Details

### New Methods
- `extract_variable_product()`: Processes parent product and all variations
- `extract_product_variant()`: Extracts individual variant data
- `normalize_variant_attributes()`: Standardizes attribute names
- `get_variant_images()`: Collects variant-specific images
- `generate_variant_checkout_url()`: Creates direct add-to-cart links

### Modified Methods
- `convert_product_to_node()`: Detects variable products and routes to variant extraction
- `get_product_images()`: Enforces 10-image limit with smart prioritization

### Validation
- Comprehensive test scripts included in `dev wordpress/`
- Validated with 3-variant product including international characters
- All variant data correctly synced to knowledge graph

---

## 🧪 Testing

**Test Environment:** Docker WordPress with WooCommerce  
**Test Product:** "test variable 3" with 3 color variants  
**Results:** ✅ All variants synced correctly with complete data

### Validated Features
✅ Variant-specific images  
✅ International characters (accents)  
✅ Direct checkout URLs  
✅ Attribute normalization  
✅ Price ranges  
✅ Stock status per variant  
✅ Parent-child relationships  

---

## 📦 Installation

1. Download `alloia-woocommerce-plugin.zip`
2. Go to WordPress Admin → Plugins → Add New → Upload Plugin
3. Choose the zip file and click "Install Now"
4. Activate the plugin
5. Configure AlloIA settings with your API key

---

## 🔄 Upgrade Notes

**From v1.7.2 or earlier:**
- No database migrations required
- Plugin will automatically sync new variant data on next product sync
- Existing simple products remain unchanged
- Variable products will include full variant details

**Breaking Changes:** None

**Recommended Actions:**
1. Re-sync all variable products to populate variant data
2. Test AI agent queries for variant-specific information
3. Verify checkout URLs work correctly

---

## 📝 Changelog

### Added
- Complete variable product variant support with 10+ data fields per variant
- Variant-specific image handling with parent fallback
- Direct add-to-cart URLs for each variant
- Price range calculation for variable products
- International character support for variant names

### Fixed
- Image count validation errors for products with many variations
- Parse errors in core and admin classes
- PHP warnings during WooCommerce initialization
- Headers already sent warnings during plugin activation

### Removed
- Deprecated llms.txt feature and all related code

---

## 🔗 Resources

- **Repository:** https://github.com/PrescientMindAI/alloia-wordpress-plugin
- **Documentation:** See `docs/WOOCOMMERCE-VARIANT-IMPLEMENTATION-GUIDE.md`
- **Validation:** See `dev wordpress/VARIANT-SYNC-VALIDATION-SUCCESS.md`
- **Support:** Contact AlloIA support team

---

## 👥 Contributors

- AlloIA Development Team
- Tested with real-world WooCommerce stores

---

**Version:** 1.7.3  
**Build Date:** December 9, 2025  
**Compatibility:** WordPress 5.9+, WooCommerce 5.0+  
**License:** Proprietary

