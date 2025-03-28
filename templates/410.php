<?php
/**
 * Filename: templates/410.php
 * Template for displaying 410 Gone status for deleted products
 *
 * @package Delete_Old_Outofstock_Products
 * @version 2.4.1
 * @since 2.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Output template with consistent styling
$template = apply_filters('oh_doop_410_template', '
<!DOCTYPE html>
<html>
<head>
    <meta charset="' . get_bloginfo('charset') . '">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>' . esc_html__('Product No Longer Available', 'delete-old-outofstock-products') . ' - ' . get_bloginfo('name') . '</title>
    <style>
        body {
            background-color: #f9f9f9;
            color: #222;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 720px;
            margin: 0 auto;
            padding: 80px 20px 120px;
            text-align: center;
        }
        h1 {
            font-size: 28px;
            margin-bottom: 20px;
        }
        p {
            font-size: 16px;
            margin-bottom: 16px;
        }
        .oh-410-button {
            display: inline-block;
            background-color: #444;
            color: #fff;
            padding: 12px 24px;
            border-radius: 4px;
            text-decoration: none;
            transition: background-color 0.3s ease;
            margin-top: 20px;
        }
        .oh-410-button:hover {
            background-color: var(--base) !important;
            color: #fff !important;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>' . esc_html__('Product No Longer Available', 'delete-old-outofstock-products') . '</h1>
        <p>' . esc_html__('We\'re sorry, but the product you\'re looking for has been discontinued and is no longer available.', 'delete-old-outofstock-products') . '</p>
        <p>' . esc_html__('Please browse our current products for alternatives.', 'delete-old-outofstock-products') . '</p>
        <a href="' . esc_url(wc_get_page_permalink('shop')) . '" class="oh-410-button">' . esc_html__('Browse Other Products', 'delete-old-outofstock-products') . '</a>
    </div>
</body>
</html>
');

echo $template;
