<?php
/**
 * Plugin Name: TWI – Group By Series (SWOOF aware)
 * Description: Exact port of your theme’s "Group by Series" (same markup/CSS), plus /page/XXX stripping, broader archive support, priority fix, WOOF SEO links, and SWOOF-aware filtering.
 * Version: 1.1.0
 * Author: Byron Iniotakis
 * Text Domain: woocommerce
 */

if (!defined('ABSPATH')) exit;

/* ---------------------------------
 * Widget (markup kept 1:1 as given)
 * --------------------------------- */
class WC_Widget_Group_By_Series extends WP_Widget {
    public function __construct() {
        parent::__construct(
            'group_by_series_widget',
            __('Group By Series', 'woocommerce'),
            ['description' => __('Show attribute terms (series) instead of products.', 'woocommerce')]
        );
    }

    public function widget( $args, $instance ) {
        echo $args['before_widget'];
        $checked = isset($_GET['group_by_series']) && $_GET['group_by_series'] == '1';

        // Build action URL = current path without /page/123 (prevents 404 when toggling on paged URLs)
        $req_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
        $path    = parse_url($req_uri, PHP_URL_PATH) ?: '/';
        $path    = preg_replace('#/page/\d+/?$#', '/', $path);
        $action  = esc_url( home_url( user_trailingslashit( ltrim($path, '/') ) ) );

        // === Form (only addition is action="..."); rest identical ===
        echo '<form method="get" id="group-by-series-form" action="' . $action . '">';
        echo '<span class="widget-group">Group by Series</span>';
        echo '<label class="group-by-checkbox">';
        echo '<input type="checkbox" name="group_by_series" value="1"' . checked( $checked, true, false ) . ' onchange="this.form.submit()"> ';
        echo '</label>';
        echo '</form>';

        // Preserve other GET parameters (kept where you had them)
        foreach ($_GET as $key => $val) {
            if ($key === 'group_by_series') continue;
            echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($val) . '">';
        }

        echo '</form>';
        echo $args['after_widget'];
    }

    public function form( $instance ) {
        echo '<p>' . esc_html__('Displays a checkbox to group by Series.', 'woocommerce') . '</p>';
    }

    public function update( $new_instance, $old_instance ) {
        return [];
    }
}
add_action('widgets_init', function() {
    register_widget('WC_Widget_Group_By_Series');
});

/* --------------------------------------------------
 * Grouping behavior (broadened + priority = 999)
 * -------------------------------------------------- */
add_action('pre_get_posts', function ($query) {
    if (
        !is_admin() &&
        $query->is_main_query() &&
        (
            (function_exists('is_shop') && is_shop()) ||
            is_post_type_archive('product') ||
            (function_exists('is_product_taxonomy') && is_product_taxonomy()) ||
            (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/swoof/') !== false)
        ) &&
        isset($_GET['group_by_series']) && $_GET['group_by_series'] === '1'
    ) {
        // Ensure WooCommerce templates still load, but return no products
        $query->set('post_type', 'product');
        $query->set('post__in', [0]); // impossible ID -> no results
        $query->set('no_found_rows', true);
    }
}, 999);

/* ------------------------------------------------------
 * No-products branch (group mode): toggle + (optional) WOOF + grid
 * ------------------------------------------------------ */

/* 0) Mobile toggle button (theme expects .filter-toggle markup) */
add_action('woocommerce_no_products_found', function () {
    if (isset($_GET['group_by_series']) && $_GET['group_by_series'] === '1') {
        echo '<a href="#" class="filter-toggle" aria-expanded="false">'
           . '<i class="bokifa-icon-filters"></i><span>' . esc_html__('Filter', 'woocommerce') . '</span>'
           . '</a>';
    }
}, 0);

/* 1) (Optional) WOOF panel above the grid — uncomment if you want it visible here */
// add_action('woocommerce_no_products_found', function () {
//     if (isset($_GET['group_by_series']) && $_GET['group_by_series'] === '1') {
//         echo do_shortcode('[woof]');
//     }
// }, 1);

/* 5) Series grid (unchanged) */
add_action('woocommerce_no_products_found', function () {
    if (isset($_GET['group_by_series']) && $_GET['group_by_series'] === '1') {
        echo do_shortcode('[attribute_term_cards attribute="pa_series" products_per_term="3"]');
    }
}, 5);

/* -----------------------------------------------------------------
 * Remove default "No products were found" message in grouping mode
 * ----------------------------------------------------------------- */
add_action('template_redirect', function () {
    if (
        (
            (function_exists('is_shop') && is_shop()) ||
            is_post_type_archive('product') ||
            (function_exists('is_product_taxonomy') && is_product_taxonomy()) ||
            (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/swoof/') !== false)
        ) &&
        isset($_GET['group_by_series']) && $_GET['group_by_series'] === '1'
    ) {
        remove_action('woocommerce_no_products_found', 'wc_no_products_found', 10);
    }
});

/* -------------------------------------------------------
 * SWOOF helpers + Shortcode (markup kept exactly the same)
 * ------------------------------------------------------- */

/**
 * Get current tax_query from main query, removing any existing 'pa_series' clause.
 * Keeps original relation when present.
 */
function twi_gbs_get_active_tax_query_without_series() {
    global $wp_query;
    if (!isset($wp_query)) return [];

    $tax_query = (array) $wp_query->get('tax_query');
    if (empty($tax_query)) return [];

    $clean = [];
    $relation = 'AND';
    foreach ($tax_query as $k => $clause) {
        if ($k === 'relation') { $relation = $clause; continue; }
        if (!is_array($clause)) continue;
        $tax = isset($clause['taxonomy']) ? $clause['taxonomy'] : '';
        if ($tax && stripos($tax, 'pa_series') !== false) {
            // drop any existing series filter
            continue;
        }
        $clean[] = $clause;
    }
    if (!empty($clean)) $clean['relation'] = $relation;
    return $clean;
}

/**
 * Shortcode (exact structure), now SWOOF-aware:
 * - Shows only series that have products under current SWOOF filters
 * - Series links are WOOF SEO: /books/swoof/series-{slug}/{existing-tail}/
 */
function show_attribute_terms_as_product_cards( $atts ) {
    $atts = shortcode_atts( [
        'attribute' => '', // e.g., 'pa_series'
        'products_per_term' => 3,
    ], $atts );

    $taxonomy = $atts['attribute'];
    if ( empty( $taxonomy ) ) return '';

    // Get all terms; we'll filter empties after applying active filters
    $terms = get_terms( [
        'taxonomy'   => $taxonomy,
        'hide_empty' => false,
    ] );

    if ( empty( $terms ) || is_wp_error( $terms ) ) return '';

    // Build base SWOOF tail from current request, stripping existing series and /page/xxx
    $req_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    $swoof_tail = '';
    if (preg_match('#/books/swoof/(.*)$#', $req_uri, $m)) {
        $swoof_tail = $m[1];
        $swoof_tail = preg_replace('#(^|/)series-[^/]+/#', '$1', $swoof_tail);
        $swoof_tail = preg_replace('#/page/\d+/?$#', '/', $swoof_tail);
        $swoof_tail = ltrim($swoof_tail, '/');
    }

    // Active filters from main query, without series
    $active_tax = twi_gbs_get_active_tax_query_without_series();

    ob_start();
    echo '<div class="attribute-term-grid">';

    foreach ( $terms as $term ) {
        // Merge active filters + this series
        $term_tax_query = $active_tax;
        if (!empty($term_tax_query) && !isset($term_tax_query['relation'])) {
            $term_tax_query['relation'] = 'AND';
        }
        $term_tax_query[] = [
            'taxonomy' => $taxonomy,
            'field'    => 'slug',
            'terms'    => $term->slug,
        ];

        // Pull a few sample products (also acts as existence check)
        $products = wc_get_products( [
            'status'    => 'publish',
            'limit'     => (int) $atts['products_per_term'],
            'tax_query' => $term_tax_query,
        ] );

        // Skip series with no products under current SWOOF filters
        if (empty($products)) continue;

        // Build WOOF SEO link: /books/swoof/series-{slug}/{existing-tail}/
        $woof_path = 'books/swoof/series-' . $term->slug . '/';
        if ($swoof_tail !== '') {
            $woof_path .= $swoof_tail . (substr($swoof_tail, -1) === '/' ? '' : '/');
        }
        $term_link = home_url('/' . $woof_path);

        echo '<div class="term-card">';
        echo '<a href="' . esc_url( $term_link ) . '">';
        echo '<div class="term-images">';
        foreach ( $products as $product ) {
            echo get_the_post_thumbnail( $product->get_id(), 'woocommerce_thumbnail' );
        }
        echo '</div>';
        echo '<h3 class="term-name">' . esc_html( $term->name ) . '</h3>';
        echo '</a>';

        // Show author with link (first product)
        $author_terms = get_the_terms($products[0]->get_id(), 'product_author');
        if ( ! empty( $author_terms ) && ! is_wp_error( $author_terms ) ) {
            $author = $author_terms[0];
            $author_link = get_term_link( $author );
            if ( ! is_wp_error( $author_link ) ) {
                echo '<div class="product-author"><a href="' . esc_url( $author_link ) . '">' . esc_html( $author->name ) . '</a></div>';
            }
        }

        // "View All" button
        echo '<div class="add_to_cart"><a class="button product_type_external btn-slip-effect" href="' . esc_url( $term_link ) . '"><span class="bokifa_btn_text">View All</span></a></div>';

        echo '</div>'; // .term-card
    }

    echo '</div>'; // .attribute-term-grid

    return ob_get_clean();
}
add_shortcode( 'attribute_term_cards', 'show_attribute_terms_as_product_cards' );
