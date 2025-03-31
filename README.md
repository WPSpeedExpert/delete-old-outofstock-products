# Delete Old Out-of-Stock Products

Automatically delete WooCommerce products that are **out of stock** and **older than a configurable period** (default 18 months), including their associated product images. This plugin helps keep your store optimized by removing outdated products and freeing up disk space.

---

## ✅ Features
- Automatically runs daily via WordPress cron.
- Deletes WooCommerce products that:
  - Are **out of stock**.
  - Were published more than **X months ago** (configurable, default 18 months).
- Optionally deletes the product's **featured image** and **gallery images**.
- **Returns 410 Gone status** for deleted product URLs, improving SEO by telling search engines that products have been permanently removed.
- **Protects WooCommerce placeholder images** from being deleted.
- **Preserves images used by multiple products** or posts.
- Shows helpful **statistics** about eligible products for deletion.
- Includes option to **manually run** cleanup with status feedback.
- Simple configuration—just install, activate, and set your preferences.
- Fully compatible with the latest WooCommerce features, including HPOS.
- Clean activation and uninstallation, with scheduled events properly removed.
- Resource-efficient batch processing for minimal server impact.

---

## 🔧 Installation
1. Upload the plugin to your `/wp-content/plugins/` directory:
    - Or install directly via the WordPress Plugins admin.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Configure the settings in **WooCommerce → Delete Old Products**.
4. The cleanup runs **automatically once daily** via WordPress cron.

---

## 📝 Requirements
- WordPress 5.6+
- WooCommerce 5.0+
- PHP 7.4+

---

## 🚀 How It Works
- On activation, a **daily cron event** is scheduled.
- Every day, the plugin:
  - Searches for **published products** older than your configured time period.
  - Deletes products that are marked as **out of stock**.
  - Optionally deletes the product's **featured image** and **gallery images** (while preserving WooCommerce placeholder images).
  - Permanently deletes the product from the database.
  - Tracks deleted product URLs to return a **410 Gone status** when accessed.

---

## ⚙️ Configuration
Navigate to **WooCommerce → Delete Old Products** to configure:

- **Product Age (months)**: Products older than this will be considered for deletion (if out of stock).
- **Delete Product Images**: Choose whether to delete product images or keep them when deleting products.
- **Enable 410 Gone Status**: Choose whether to track deleted products and return a 410 Gone HTTP status when their URLs are accessed.

The settings page also shows helpful statistics:
- Total number of products in your store
- Number of out-of-stock products
- Number of products older than your configured threshold
- Number of products eligible for deletion (both out of stock AND old)
- Number of tracked deleted products (when 410 feature is enabled)

You can also trigger a manual cleanup by clicking the "Run Product Cleanup Now" button at the bottom of the settings page.

---

## 📱 SEO Benefits of 410 Gone Status
When a product is deleted, the plugin (if enabled) will:
1. Track the product's URL
2. Return a proper **410 Gone** status code when someone tries to access the deleted product URL
3. Display a user-friendly message indicating the product is no longer available
4. Provide navigation back to the shop

This is better for SEO than a standard 404 page because:
- It tells search engines the product has been **intentionally removed** (not just missing)
- Search engines will remove the URL from their index faster
- It reduces "crawl budget" waste on pages that no longer exist
- It provides a better user experience for visitors following old links

---

## ⚠️ Important Notes
- Works only if **WooCommerce is active**.
- Only deletes products that are **both out of stock AND older than the configured age**.
- WooCommerce placeholder images are **protected from deletion**.
- Images used in multiple products or in post content are **preserved**.
- Uses **memory-efficient batch processing** to handle large stores.
- The 410 tracking system automatically cleans up records older than 1 year.
- Deactivation removes the scheduled cron job.
- Uninstallation removes the cron job, all plugin settings, and 410 tracking data.

---

## 🗑️ Uninstalling
When the plugin is uninstalled:
- The cron job is unscheduled.
- Plugin settings are deleted.
- 410 tracking data is removed.
- No product data is deleted during uninstallation.

---

## 🧑‍💻 Credits
Developed by [OctaHexa](https://octahexa.com).

---

## 📄 License
- License: [GNU General Public License v3.0](https://www.gnu.org/licenses/gpl-3.0.html)

---

## 📦 GitHub
- Repository: [https://github.com/WPSpeedExpert/delete-old-outofstock-products](https://github.com/WPSpeedExpert/delete-old-outofstock-products)
- Branch: `main`
