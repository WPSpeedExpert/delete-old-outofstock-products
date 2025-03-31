<?php
/**
 * Filename: templates/410.php
 * Template for displaying 410 Gone status for deleted products
 *
 * @package Delete_Old_Outofstock_Products
 * @version 2.4.8
 * @since 2.4.4
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// First, check if there's a 410-gone.php template in the theme
$theme_410_template = get_stylesheet_directory() . '/410-gone.php';
if (file_exists($theme_410_template)) {
    // Use the theme's 410 template
    include $theme_410_template;
    exit;
}

// If no theme template exists, use our fallback
get_header();

// Check if we're using GeneratePress or a theme with generate_ functions
$using_generatepress = function_exists('generate_do_attr');

// Start the content container
if ($using_generatepress) {
    ?>
    <div <?php generate_do_attr('content'); ?>>
        <main <?php generate_do_attr('main'); ?>>
            <?php do_action('generate_before_main_content'); ?>
    <?php
} else {
    ?>
    <div id="primary" class="content-area">
        <main id="main" class="site-main">
    <?php
}
?>

<div class="error-410 gone-product">
    <div class="<?php echo $using_generatepress ? 'gb-container' : 'container'; ?>" style="text-align: center; padding: 60px 20px 120px;">
        <h1 class="has-text-align-center"><?php esc_html_e('Product No Longer Available', 'delete-old-outofstock-products'); ?></h1>
        <p class="has-text-align-center">
            <?php esc_html_e('This product has been removed or is no longer available.', 'delete-old-outofstock-products'); ?>
        </p>
        <div class="<?php echo $using_generatepress ? 'gb-button gb-button-410' : 'button-container'; ?>" style="margin-top: 20px;">
            <a href="<?php echo esc_url(function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop')); ?>" class="button oh-410-button">
                <?php esc_html_e('Browse Other Products', 'delete-old-outofstock-products'); ?>
                <span class="<?php echo $using_generatepress ? 'gb-icon' : 'icon'; ?>">→</span>
            </a>
        </div>
    </div>
</div>

<?php
// Close the content container based on the theme
if ($using_generatepress) {
    ?>
            <?php do_action('generate_after_main_content'); ?>
        </main>
    </div>
    <?php 
    do_action('generate_after_primary_content_area');
    generate_construct_sidebars();
    ?>
    <?php
} else {
    ?>
        </main>
    </div>
    <?php get_sidebar(); ?>
    <?php
}
?>

<!-- Include the custom CSS but allow theme styling to take precedence -->
<style>
    .oh-410-button {
        display: inline-block;
        text-align: center;
        background-color: #444;
        color: #fff !important;
        padding: 12px 24px;
        border-radius: 4px;
        text-decoration: none !important;
        transition: background-color 0.3s ease;
        margin-top: 20px;
    }
    .oh-410-button:hover {
        background-color: var(--base, #0073aa) !important;
        color: #fff !important;
    }
    /* Only apply these styles if the theme doesn't override them */
    .error-410 h1 {
        margin-bottom: 20px;
    }
    .error-410 p {
        margin-bottom: 16px;
    }
</style>

<?php
get_footer();
