TWI – Group By Series (SWOOF Aware)
A WooCommerce add-on that lets customers group products by Series on shop and archive pages.
Designed for use with WOOF Product Filters and SWOOF SEO URLs, this plugin preserves your existing widget markup and CSS while adding smart behavior for better navigation.

Features
Group by Series Toggle – Adds a checkbox widget to shop, category, and filtered (SWOOF) pages.

SWOOF-Aware Grid – Displays only Series that have products matching the current WOOF/SWOOF filters.

SEO-Friendly Links – Series links use /books/swoof/series-{slug}/... format and preserve existing filter paths.

Pagination-Safe – Strips /page/XXX from URLs when switching modes to prevent 404 errors.

Broad Archive Support – Works on shop, product category/tag archives, and WOOF /swoof/ virtual pages.

Same Markup & CSS – Output matches original theme code exactly, so existing styles still apply.

Shortcode
[attribute_term_cards attribute="pa_series" products_per_term="3"]

attribute – Taxonomy to display (e.g., pa_series)

products_per_term – Number of sample products to show for each series.

Requirements
WooCommerce

WOOF – WooCommerce Products Filter (for SWOOF SEO URLs)

Installation
Upload the plugin to wp-content/plugins/twi-group-by-series/.

Activate it in WordPress Admin → Plugins.

Add the Group By Series widget to your desired sidebar in Appearance → Widgets.

Purge any caches (W3TC, Varnish, etc.) after activation.
