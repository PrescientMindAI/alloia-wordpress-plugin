<?php
/**
 * AlloIA Settings Page Template
 * 
 * @package AlloIA_WooCommerce
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap alloia-settings" style="max-width:1000px;">
    <h1 style="display:flex;align-items:center;">
        <img src="<?php echo esc_url(plugin_dir_url(dirname(__DIR__)) . 'admin/assets/alloia-logo.png'); ?>" alt="AlloIA" style="height:40px;margin-right:12px;">
        for WooCommerce
    </h1>
    <p>Welcome to AlloIA! Transform your WooCommerce store for the AI era.</p>
    
    <!-- Navigation Tabs -->
    <h2 class="nav-tab-wrapper">
        <a href="?page=alloia-settings&tab=free-tools" 
           class="nav-tab <?php echo ($data['active_tab'] == 'free-tools' || $data['active_tab'] == 'free') ? 'nav-tab-active' : ''; ?>">
            Free Tools
        </a>
        <a href="?page=alloia-settings&tab=ai-commerce" 
           class="nav-tab <?php echo ($data['active_tab'] == 'ai-commerce' || $data['active_tab'] == 'pro') ? 'nav-tab-active' : ''; ?>">
            AI Commerce Platform
        </a>
    </h2>
    
    <!-- Tab Content -->
    <div class="tab-content" style="padding:20px 0;">
        <?php 
        $__tab = isset($data['active_tab']) ? strtolower($data['active_tab']) : 'free-tools';
        // Map new tab names to old template files for backward compatibility
        if ($__tab === 'ai-commerce' || $__tab === 'pro') { 
            include ALLOIA_PLUGIN_PATH . 'admin/views/pro-features.php';
        } else {
            include ALLOIA_PLUGIN_PATH . 'admin/views/free-features.php';
        } ?>
    </div>
</div> 