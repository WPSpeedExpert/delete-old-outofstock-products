# Delete Old Out-of-Stock Products

Automatically delete WooCommerce products that are **out of stock** and **older than 1.5 years**, including their associated product images. This plugin helps keep your store optimized by removing outdated products and freeing up disk space.

---

## ✅ Features
- Automatically runs daily via WordPress cron.
- Deletes WooCommerce products that:
  - Are **out of stock**.
  - Were published more than **18 months ago**.
- Deletes the product’s **featured image** and **gallery images**.
- Requires **no configuration**—just install and activate.
- Fully compatible with the latest WooCommerce features, including HPOS.
- Clean activation and uninstallation, with scheduled events properly removed.

---

## 🔧 Installation
1. Upload the plugin to your `/wp-content/plugins/` directory:
    - Or install directly via the WordPress Plugins admin.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. The cleanup runs **automatically once daily** via WordPress cron.

---

## 📝 Requirements
- WordPress 5.6+
- WooCommerce 5.0+
- PHP 7.4+

---

## 🚀 How It Works
- On activation, a **daily cron event** is scheduled.
- Every day, the plugin:
  - Searches for **published products** older than **18 months**.
  - Deletes products that are marked as **out of stock**.
  - Deletes the product’s **featured image** and **gallery images**.
  - Permanently deletes the product from the database.

---

## ⚠️ Important Notes
- There are **no settings**. The plugin runs silently in the background.
- Works only if **WooCommerce is active**.
- Deactivation removes the scheduled cron job.
- Uninstallation removes the cron job entirely.

---

## 🗑️ Uninstalling
When the plugin is uninstalled:
- The cron job is unscheduled.
- No other data is deleted.

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
