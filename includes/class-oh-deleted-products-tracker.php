<?php
/**
 * Updated section_410_callback method for OH_Admin_UI class
 */

/**
 * 410 Section description callback
 */
public function section_410_callback() {
    // Get deleted products data
    $deleted_products = get_option('oh_doop_deleted_products', array());
    $count = count($deleted_products);
    
    ?>
    <div class="oh-doop-stats">
        <table class="widefat striped">
            <tr>
                <td><strong><?php esc_html_e('Tracked Deleted Products:', 'delete-old-outofstock-products'); ?></strong></td>
                <td><?php echo esc_html($count); ?></td>
            </tr>
        </table>
        <p class="description">
            <?php esc_html_e('Number of deleted products being tracked for 410 Gone status responses. Old records are automatically cleared after one year.', 'delete-old-outofstock-products'); ?>
        </p>
        
        <?php if ($count > 0) : ?>
            <h4><?php esc_html_e('Recent Deleted Products', 'delete-old-outofstock-products'); ?></h4>
            <div class="deleted-products-list-wrapper" style="max-height: 300px; overflow-y: auto; margin-bottom: 20px;">
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Product Slug', 'delete-old-outofstock-products'); ?></th>
                            <th><?php esc_html_e('Deleted Date', 'delete-old-outofstock-products'); ?></th>
                            <th><?php esc_html_e('Test Link', 'delete-old-outofstock-products'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Sort by timestamp (newest first)
                        uasort($deleted_products, function($a, $b) {
                            return $b['deleted_at'] - $a['deleted_at'];
                        });
                        
                        // Limit to 25 most recent products
                        $recent_products = array_slice($deleted_products, 0, 25, true);
                        
                        foreach ($recent_products as $slug => $data) : 
                            $test_url = isset($data['url']) ? $data['url'] : home_url('/product/' . $slug);
                            $deleted_date = isset($data['deleted_at']) ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $data['deleted_at']) : '—';
                        ?>
                            <tr>
                                <td><code><?php echo esc_html($slug); ?></code></td>
                                <td><?php echo esc_html($deleted_date); ?></td>
                                <td>
                                    <a href="<?php echo esc_url($test_url); ?>" target="_blank" class="button button-small">
                                        <?php esc_html_e('Test 410', 'delete-old-outofstock-products'); ?>
                                        <span class="dashicons dashicons-external" style="font-size: 14px; height: 14px; width: 14px; vertical-align: text-bottom;"></span>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($count > 25) : ?>
                <p class="description">
                    <?php 
                    printf(
                        esc_html__('Showing 25 most recent of %d tracked deleted products.', 'delete-old-outofstock-products'),
                        $count
                    ); 
                    ?>
                </p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
}
