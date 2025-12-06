<?php
/**
 * Free Features Template
 * 
 * @package AlloIA_WooCommerce
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>

<form method="post" action="">
    <?php wp_nonce_field('alloia_settings', 'alloia_nonce'); ?>
    
    <!-- AI Readiness Analysis -->
    <div class="alloia-card" style="margin-top:10px;">
        <h3>AI Readiness Analysis</h3>
        <p>Get your AI readiness score and recommendations:</p>
        <a href="https://alloia.ai/geo/perspective" target="_blank" class="button button-primary">
            Analyze My Site
        </a>
    </div>

    <!-- Container for subsequent steps -->
    <div class="alloia-toggles">
        
        <!-- robots.txt generation + Training permission (default selector) -->
        <div class="alloia-card alloia-setting-group">
            <div class="alloia-toggle">
                <div class="alloia-setting-info">
                    <strong>Generate robots.txt</strong>
                    <p>Create or refresh an AI-friendly <code>robots.txt</code> based on your settings, and choose whether to allow AI training bots.</p>

                    <form method="post" action="" style="margin-top:10px;display:flex;gap:16px;align-items:center;flex-wrap:wrap;">
                        <?php wp_nonce_field('alloia_save_llm_training', 'alloia_llm_training_nonce'); ?>
                        <label><input type="radio" name="alloia_llm_training" value="allow" <?php checked('allow', get_option('alloia_llm_training', 'allow')); ?> /> Allow training</label>
                        <label><input type="radio" name="alloia_llm_training" value="disallow" <?php checked('disallow', get_option('alloia_llm_training', 'allow')); ?> /> Disallow training</label>
                        <input type="submit" class="button" value="Save policy" />
                    </form>

                    <div style="margin-top:10px; display:flex; gap:10px; flex-wrap:wrap;">
                        <form method="post" action="" style="display:inline;">
                            <?php wp_nonce_field('alloia_update_robots', 'alloia_update_robots_nonce'); ?>
                            <input type="hidden" name="alloia_update_robots_now" value="1" />
                            <input type="submit" class="button button-primary" value="Generate robots.txt" />
                            <a href="<?php echo esc_url(home_url('/robots.txt')); ?>" target="_blank" class="button">View robots.txt →</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- generic llms.txt generation (before enabling AlloIA OSS) -->
        <div class="alloia-card alloia-setting-group">
            <div class="alloia-toggle">
                <div class="alloia-setting-info">
                    <strong>Generate llms.txt</strong>
                    <p>Generate a comprehensive <code>llms.txt</code> file using the <a href="https://alloia.io/api/tools/llms-txt" target="_blank">AlloIA.io API</a> for optimal AI model consumption. This creates a detailed, site-specific llms.txt file that helps AI systems better understand your content.</p>
                    <div style="margin-top:10px; display:flex; gap:10px; flex-wrap:wrap;">
                        <form method="post" action="" style="display:inline;">
                            <?php wp_nonce_field('generate_llms_txt_action', 'generate_llms_txt_nonce'); ?>
                            <input type="hidden" name="generate_llms_txt" value="1" />
                            <input type="submit" class="button button-primary" value="Generate llms.txt" />
                            <a href="<?php echo esc_url(home_url('/llms.txt')); ?>" target="_blank" class="button">View llms.txt →</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- No global save button needed for one-time actions -->

</form> 