# Changelog - AlloIA for WooCommerce

All notable changes to this plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.1] - 2024-12-19

### Fixed
- **Critical Security Issues**:
  - Removed duplicate class file that caused fatal PHP errors
  - Fixed XSS vulnerabilities with proper output escaping
  - Fixed SQL injection vulnerability in rate limiter
  - Added comprehensive file permission validation
  - Enhanced CSRF protection for all AJAX endpoints

### Improved
- **Error Handling**: Added comprehensive error handling throughout codebase
- **Debug Logging**: All debug statements now wrapped in WP_DEBUG checks
- **Rate Limiting**: Fixed logic to avoid side effects when checking limits
- **Database Performance**: Added pagination support for product queries
- **Code Quality**: Enhanced input validation and data sanitization

### Updated
- **Development Plan**: Added security requirements and validation needs
- **Documentation**: Updated with security improvements and best practices

## [1.0.0] - 2024-12-19

### Added
- **Initial Release** - Complete plugin with all core features
- **Free Features**:
  - AI bot tracking and monitoring
  - llms.txt file generation and management
  - Robots.txt optimization for AI crawlers
  - Basic analytics dashboard
  - WordPress admin integration

- **Pro Features**:
  - Knowledge Graph product export
  - Advanced analytics and insights
  - Competitor analysis tools
  - Prompt management system
  - Smart product recommendations
  - Subscription management

- **Technical Features**:
  - Direct API integration with AlloIA services
  - Rate limiting and error handling
  - WooCommerce product integration
  - Background export processing
  - Comprehensive admin interface
  - AJAX-powered interactions

### Technical Details
- **WordPress Compatibility**: 5.0+
- **PHP Requirements**: 7.4+
- **WooCommerce Integration**: 5.0+
- **Database Tables**: Export history tracking
- **File Management**: llms.txt and robots.txt generation
- **Security**: Nonce verification, capability checks, input sanitization

### Architecture
- **Core Classes**: AlloIA_Core, AlloIA_Admin
- **API Integration**: AlloIA_API_Client
- **Business Logic**: AlloIA_Subscription_Manager, AlloIA_Knowledge_Graph_Exporter
- **UI Components**: Dashboard, Subscription, Knowledge Graph pages
- **Unified API**: Consolidated API client for all services

---

## Version History

### 1.0.0 (Current)
- Complete plugin with all features
- Ready for production use
- Comprehensive documentation
- WordPress standards compliance

---

## Future Versions

### Planned for 1.1.0
- Enhanced export history tracking
- More detailed analytics
- Additional AI optimization tools
- Performance improvements

### Planned for 1.2.0
- Advanced competitor analysis
- AI prompt optimization
- Machine learning recommendations
- API rate limit improvements

---

## Support & Updates

- **Documentation**: [AlloIA.ai/docs](https://alloia.ai/docs)
- **Support**: [support@alloia.ai](mailto:support@alloia.ai)
- **Updates**: Automatic WordPress plugin updates
- **Compatibility**: Tested with latest WordPress and WooCommerce versions

---

**AI GEO optimisation by AlloIA** - Making your store more visible to AI systems 🚀
