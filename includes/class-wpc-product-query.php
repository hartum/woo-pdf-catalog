<?php
/**
 * Queries WooCommerce products with the plugin's configured filters.
 *
 * @package Woo_PDF_Catalog
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPC_Product_Query
 *
 * Responsible for building the list of WC_Product objects that will appear
 * in the generated PDF, respecting status, forced and excluded product settings.
 */
class WPC_Product_Query {

	/** @var WPC_Settings */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param WPC_Settings $settings Plugin settings instance.
	 */
	public function __construct( WPC_Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Build and return the list of WC_Product objects for the PDF.
	 *
	 * Logic:
	 *  1. Run WC_Product_Query with the configured status.
	 *  2. Remove excluded product IDs.
	 *  3. Inject forced product IDs (if not already present).
	 *  4. Sort according to the chosen sort_by setting.
	 *  5. Return the final ordered array.
	 *
	 * @param array|null $override_settings Optional settings override for this query.
	 * @return WC_Product[] Array of WC_Product objects.
	 */
	public function get_products( $override_settings = null ) {
		$s = $override_settings ? wp_parse_args( $override_settings, $this->settings->get_all() ) : $this->settings->get_all();

		$status           = ( 'any' === $s['publication_status'] ) ? array( 'publish', 'draft', 'pending', 'private', 'future' ) : array( 'publish' );
		$excluded_ids     = ! empty( $s['excluded_products'] ) ? array_map( 'absint', (array) $s['excluded_products'] ) : array();
		$forced_ids       = ! empty( $s['forced_products'] )   ? array_map( 'absint', (array) $s['forced_products'] )   : array();

		// Determine query-level ordering based on sort_by.
		$sort_by = isset( $s['sort_by'] ) ? $s['sort_by'] : 'menu_order';
		switch ( $sort_by ) {
			case 'title':
				$orderby = 'title';
				break;
			case 'date':
				$orderby = 'date';
				break;
			default: // 'menu_order' and 'tag' (tag sorting is done post-query).
				$orderby = 'menu_order';
				break;
		}

		// ── Main query ───────────────────────────────────────────────────────
		$query_args = array(
			'status'         => $status,
			'limit'          => -1,
			'orderby'        => $orderby,
			'order'          => 'ASC',
			'return'         => 'objects',
			'exclude'        => $excluded_ids,
			// Exclude forced IDs from main query to re-add them at the front.
			'include'        => array(),
		);

		// If there are forced products, exclude them from the main query so we
		// can prepend them in a controlled order.
		if ( ! empty( $forced_ids ) ) {
			// Exclude forced IDs from main query to avoid duplicates.
			$query_args['exclude'] = array_unique( array_merge( $excluded_ids, $forced_ids ) );
		}

		$wc_query = new WC_Product_Query( $query_args );
		$products = $wc_query->get_products();

		// ── Forced products (always include, at the top) ──────────────────────
		if ( ! empty( $forced_ids ) ) {
			$forced_products = array();
			foreach ( $forced_ids as $id ) {
				// Skip excluded products even if they are also in forced_products.
				if ( in_array( $id, $excluded_ids, true ) ) {
					continue;
				}
				$product = wc_get_product( $id );
				if ( $product instanceof WC_Product ) {
					$forced_products[] = $product;
				}
			}
			// Forced products come first.
			$products = array_merge( $forced_products, $products );
		}

		// ── Post-query sort by tag ───────────────────────────────────────────
		if ( 'tag' === $sort_by ) {
			$products = $this->sort_by_tag( $products, $s );
		}

		/**
		 * Filter the final list of products that will appear in the PDF.
		 *
		 * @since 1.0.0
		 * @param WC_Product[] $products Array of WC_Product objects.
		 * @param array        $s        Resolved settings array.
		 */
		return apply_filters( 'wpc_pdf_products', $products, $s );
	}

	/**
	 * Sort products by a matching product tag.
	 *
	 * Groups products by the first tag whose name starts with `sort_tag_prefix`.
	 * Groups are ordered according to the user-defined `sort_tag_order` list;
	 * any tag not in that list appears after the listed ones in alphabetical order.
	 * Within each group, products keep their original (query) order.
	 *
	 * @param WC_Product[] $products Flat array of products.
	 * @param array        $s        Resolved settings.
	 * @return WC_Product[] Sorted array.
	 */
	private function sort_by_tag( array $products, array $s ) {
		$prefix     = isset( $s['sort_tag_prefix'] ) ? trim( $s['sort_tag_prefix'] ) : '';
		$tag_order  = isset( $s['sort_tag_order'] )  ? (array) $s['sort_tag_order']  : array();

		// Build a position map for user-defined order (slug → position).
		$order_map = array();
		foreach ( array_values( $tag_order ) as $pos => $slug ) {
			$order_map[ $slug ] = $pos;
		}

		// Bucket each product by its matching tag slug.
		$buckets   = array(); // tag_slug => [ products ]
		$no_tag    = array(); // products without a matching tag

		foreach ( $products as $product ) {
			$tag_slug = $this->get_matching_tag_slug( $product, $prefix );
			if ( $tag_slug ) {
				$buckets[ $tag_slug ][] = $product;
			} else {
				$no_tag[] = $product;
			}
		}

		// Sort bucket keys: user-defined order first, then alphabetical.
		$bucket_keys = array_keys( $buckets );
		$max_pos     = count( $order_map );
		usort( $bucket_keys, function ( $a, $b ) use ( $order_map, $max_pos ) {
			$pos_a = isset( $order_map[ $a ] ) ? $order_map[ $a ] : $max_pos + 1;
			$pos_b = isset( $order_map[ $b ] ) ? $order_map[ $b ] : $max_pos + 1;
			if ( $pos_a !== $pos_b ) {
				return $pos_a - $pos_b;
			}
			// Both unlisted — sort alphabetically.
			return strcasecmp( $a, $b );
		} );

		// Rebuild final array.
		$sorted = array();
		foreach ( $bucket_keys as $key ) {
			$sorted = array_merge( $sorted, $buckets[ $key ] );
		}
		// Products without a matching tag go last.
		$sorted = array_merge( $sorted, $no_tag );

		return $sorted;
	}

	/**
	 * Get the slug of the first product_tag that starts with the given prefix.
	 *
	 * @param WC_Product $product Product object.
	 * @param string     $prefix  Tag name prefix to match (e.g. "D.O.").
	 * @return string|null Tag slug, or null if none matches.
	 */
	private function get_matching_tag_slug( WC_Product $product, $prefix ) {
		$tags = wp_get_post_terms( $product->get_id(), 'product_tag', array( 'fields' => 'all' ) );
		if ( is_wp_error( $tags ) || empty( $tags ) ) {
			return null;
		}

		foreach ( $tags as $tag ) {
			if ( '' === $prefix || 0 === mb_stripos( $tag->name, $prefix ) ) {
				return $tag->slug;
			}
		}
		return null;
	}

	/**
	 * Return a lightweight array of [ id => title ] for Select2 searches.
	 *
	 * @param string $search Search string.
	 * @param int    $limit  Max results.
	 * @return array
	 */
	public function search_products( $search, $limit = 30 ) {
		$args = array(
			's'      => wc_clean( $search ),
			'limit'  => $limit,
			'status' => array( 'publish', 'draft', 'pending', 'private' ),
			'return' => 'objects',
		);

		$results = ( new WC_Product_Query( $args ) )->get_products();
		$out     = array();

		foreach ( $results as $product ) {
			$out[] = array(
				'id'   => $product->get_id(),
				'text' => sprintf( '#%d – %s', $product->get_id(), $product->get_name() ),
			);
		}

		return $out;
	}
}
