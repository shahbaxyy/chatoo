<?php
/**
 * WooCommerce integration class.
 *
 * Provides WooCommerce-related data for agent dashboards. Only loaded
 * when WooCommerce is active.
 *
 * @package MyHelpDesk_Chat
 * @since   1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class MHD_WooCommerce
 *
 * Retrieves customer order data, customer profiles, available coupons,
 * and product search results from WooCommerce.
 *
 * @since 1.0.0
 */
class MHD_WooCommerce {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Intentionally empty. Class is only instantiated when WooCommerce is active.
	}

	/**
	 * Get recent WooCommerce orders for a user.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id WordPress user ID.
	 * @param int $limit   Maximum number of orders to return. Default 5.
	 * @return array Array of order data arrays with keys: id, status, total,
	 *               currency, date_created, items.
	 */
	public function get_customer_orders( $user_id, $limit = 5 ) {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}

		$limit  = max( 1, (int) $limit );
		$orders = wc_get_orders( array(
			'customer_id' => (int) $user_id,
			'limit'       => $limit,
			'orderby'     => 'date',
			'order'       => 'DESC',
			'status'      => array_keys( wc_get_order_statuses() ),
		) );

		$result = array();
		foreach ( $orders as $order ) {
			$items = array();
			foreach ( $order->get_items() as $item ) {
				$items[] = array(
					'name'     => $item->get_name(),
					'quantity' => $item->get_quantity(),
					'total'    => $item->get_total(),
				);
			}

			$result[] = array(
				'id'           => $order->get_id(),
				'status'       => $order->get_status(),
				'total'        => $order->get_total(),
				'currency'     => $order->get_currency(),
				'date_created' => $order->get_date_created() ? $order->get_date_created()->format( 'Y-m-d H:i:s' ) : '',
				'items'        => $items,
			);
		}

		return $result;
	}

	/**
	 * Get a customer profile with spending grade.
	 *
	 * Grades customers based on order count thresholds stored in plugin options:
	 * - VIP: total orders >= mhd_woo_vip_threshold (default 10).
	 * - Regular: total orders >= mhd_woo_regular_threshold (default 3).
	 * - First-time: fewer orders than the regular threshold.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array {
	 *     Customer profile data.
	 *
	 *     @type string $grade       Customer grade: 'VIP', 'Regular', or 'First-time'.
	 *     @type int    $total_orders Number of completed/processing orders.
	 *     @type float  $total_spend  Lifetime spend amount.
	 * }
	 */
	public function get_customer_profile( $user_id ) {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array(
				'grade'        => 'First-time',
				'total_orders' => 0,
				'total_spend'  => 0.0,
			);
		}

		$orders = wc_get_orders( array(
			'customer_id' => (int) $user_id,
			'limit'       => -1,
			'status'      => array( 'wc-completed', 'wc-processing' ),
			'return'      => 'ids',
		) );

		$total_orders = count( $orders );
		$total_spend  = 0.0;

		foreach ( $orders as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( $order ) {
				$total_spend += (float) $order->get_total();
			}
		}

		$vip_threshold     = (int) get_option( 'mhd_woo_vip_threshold', 10 );
		$regular_threshold = (int) get_option( 'mhd_woo_regular_threshold', 3 );

		if ( $total_orders >= $vip_threshold ) {
			$grade = 'VIP';
		} elseif ( $total_orders >= $regular_threshold ) {
			$grade = 'Regular';
		} else {
			$grade = 'First-time';
		}

		return array(
			'grade'        => $grade,
			'total_orders' => $total_orders,
			'total_spend'  => round( $total_spend, 2 ),
		);
	}

	/**
	 * Get a list of active WooCommerce coupons.
	 *
	 * Returns coupons that have not expired and, if a usage limit is set,
	 * have not exceeded their maximum usage count.
	 *
	 * @since 1.0.0
	 *
	 * @return array Array of coupon data arrays with keys: id, code, discount_type,
	 *               amount, expiry_date.
	 */
	public function get_available_coupons() {
		$args = array(
			'post_type'      => 'shop_coupon',
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'OR',
				array(
					'key'     => 'date_expires',
					'value'   => '',
					'compare' => '=',
				),
				array(
					'key'     => 'date_expires',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => 'date_expires',
					'value'   => time(),
					'compare' => '>',
					'type'    => 'NUMERIC',
				),
			),
		);

		$query   = new WP_Query( $args );
		$coupons = array();

		foreach ( $query->posts as $post ) {
			$coupon = new WC_Coupon( $post->ID );

			// Skip coupons that have exceeded their usage limit.
			$usage_limit = $coupon->get_usage_limit();
			if ( $usage_limit > 0 && $coupon->get_usage_count() >= $usage_limit ) {
				continue;
			}

			$coupons[] = array(
				'id'            => $coupon->get_id(),
				'code'          => $coupon->get_code(),
				'discount_type' => $coupon->get_discount_type(),
				'amount'        => $coupon->get_amount(),
				'expiry_date'   => $coupon->get_date_expires() ? $coupon->get_date_expires()->format( 'Y-m-d' ) : '',
			);
		}

		return $coupons;
	}

	/**
	 * Search WooCommerce products by name.
	 *
	 * @since 1.0.0
	 *
	 * @param string $query Search query string.
	 * @return array Array of product data arrays with keys: id, name, price,
	 *               permalink, image.
	 */
	public function search_products( $query ) {
		if ( empty( $query ) ) {
			return array();
		}

		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => 10,
			's'              => sanitize_text_field( $query ),
		);

		$wp_query = new WP_Query( $args );
		$products = array();

		foreach ( $wp_query->posts as $post ) {
			$product = wc_get_product( $post->ID );

			if ( ! $product ) {
				continue;
			}

			$image_id  = $product->get_image_id();
			$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';

			$products[] = array(
				'id'        => $product->get_id(),
				'name'      => $product->get_name(),
				'price'     => $product->get_price(),
				'permalink' => get_permalink( $product->get_id() ),
				'image'     => $image_url,
			);
		}

		return $products;
	}
}
