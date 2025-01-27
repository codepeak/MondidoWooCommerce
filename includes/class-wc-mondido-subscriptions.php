<?php
/**
 * Mondido  Mondido subscriptions
 *
 * @author Mondido
 * @package mondido
 **/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly!

class WC_Mondido_Subscriptions {
	/**
	 * Constructor
	 */
	public function __construct() {
		// Mondido Subscriptions
		add_filter( 'woocommerce_product_data_tabs', __CLASS__ . '::add_product_tabs' );
		add_action( 'woocommerce_product_data_panels', __CLASS__ . '::subscription_options_product_tab_content' );
		add_action( 'woocommerce_process_product_meta', __CLASS__ . '::save_subscription_field' );
		add_filter( 'woocommerce_cart_needs_payment', __CLASS__ . '::cart_needs_payment', 10, 2 );
		add_filter( 'woocommerce_order_needs_payment', __CLASS__ . '::order_needs_payment', 10, 3 );
		add_filter( 'woocommerce_mondido_form_fields', array( $this, 'add_recurring_items' ), 9, 3 );
		add_filter( 'woocommerce_free_price_html', __CLASS__ . '::remove_free_price', 10, 2 );
	}

	/**
	 * Add Tab to Product editor
	 *
	 * @param array $tabs
	 *
	 * @return array
	 */
	public static function add_product_tabs( $tabs ) {
		$tabs['mondido_subscription'] = array(
			'label'  => __( 'Mondido Subscription', 'woocommerce-gateway-mondido' ),
			'target' => 'subscription_options',
			'class'  => array( 'show_if_simple' ),
		);

		return $tabs;
	}

	/**
	 * Tab Content
	 */
	public static function subscription_options_product_tab_content() {
		global $post;
		$plan_id = get_post_meta( get_the_ID(), '_mondido_plan_id', true );
		$gateway = new WC_Gateway_Mondido_HW();
		?>
		<div id='subscription_options' class='panel woocommerce_options_panel'>
			<div class='options_group'>
				<?php
				try {
					$plans   = $gateway->getSubscriptionPlans();
					$options    = array();
					$options[0] = __( 'No subscription', 'woocommerce-gateway-mondido' );
					if ( $plans ) {
						foreach ( $plans as $item ) {
							$options[ $item['id'] ] = __( $item['name'], 'woocommerce-gateway-mondido' );
						}
					}

					woocommerce_wp_select(
						array(
							'id'      => '_mondido_plan_id',
							'value'   => (string) $plan_id,
							'label'   => __( 'Subscription plan', 'woocommerce-gateway-mondido' ),
							'options' => $options,
						)
					);
				} catch (Exception $e) {
					?>
					<p class="form-field _mondido_plan_id_field ">
						<input type="hidden" name="_mondido_plan_id" value="<?php echo esc_attr( $plan_id ); ?>" />
						<span id="message" class="error">
					<?php echo sprintf( esc_html__( 'Mondido Error: %s', 'woocommerce-gateway-mondido' ), $e->getMessage() ); ?>
							<br />
							<?php esc_html_e( 'Please check Mondido settings.', 'woocommerce-gateway-mondido' ); ?>
				</span>
					</p>
					<?php
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Save Handler
	 */
	public static function save_subscription_field() {
		global $post_id;

		if ( empty( $post_id ) ) {
			return;
		}

		if ( isset( $_POST['_mondido_plan_id'] ) ) {
			update_post_meta( $post_id, '_mondido_plan_id', $_POST['_mondido_plan_id'] );
		}
	}

	/**
	 * Check is Cart need payment
	 *
	 * @param bool    $needs_payment
	 * @param WC_Cart $cart
	 *
	 * @return mixed
	 */
	public static function cart_needs_payment( $needs_payment, $cart ) {
		if ( $needs_payment === false ) {
			$products = $cart->get_cart();
			foreach ( $products as $id => $product ) {
				$plan_id = get_post_meta( $product['product_id'], '_mondido_plan_id', true );
				if ( (int) $plan_id > 0 ) {
					return true;
				}
			}
		}

		return $needs_payment;
	}

	/**
	 * Check is Order need payment
	 *
	 * @param bool     $needs_payment
	 * @param WC_Order $order
	 * @param array    $valid_order_statuses
	 *
	 * @return bool
	 */
	public static function order_needs_payment( $needs_payment, $order, $valid_order_statuses ) {
		if ( false === $needs_payment) {
			foreach ( $order->get_items( 'line_item' ) as $order_item ) {
				if ( version_compare( WC()->version, '3.0', '>=' ) ) {
					$plan_id = get_post_meta( $order_item->get_product_id(), '_mondido_plan_id', true );
				} else {
					$plan_id = get_post_meta( $order_item['product_id'], '_mondido_plan_id', true );
				}

				if ( (int) $plan_id > 0 ) {
					return true;
				}
			}
		}

		return $needs_payment;
	}

	/**
	 * Add Recurring fields
	 *
	 * @param array              $fields
	 * @param WC_Order           $order
	 * @param WC_Payment_Gateway $gateway
	 *
	 * @return mixed
	 */
	public function add_recurring_items( $fields, $order, $gateway ) {
		if ( ! $order ) {
			return $fields;
		}

		foreach ( $order->get_items( 'line_item' ) as $order_item ) {
			if ( version_compare( WC()->version, '3.0', '>=' ) ) {
				$plan_id = get_post_meta( $order_item->get_product_id(), '_mondido_plan_id', true );
			} else {
				$plan_id = get_post_meta( $order_item['product_id'], '_mondido_plan_id', true );
			}

			if ( (int) $plan_id > 0 ) {
				$fields['plan_id']               = $plan_id;
				$fields['subscription_quantity'] = $order_item['qty'];

				return $fields;
			}
		}

		return $fields;
	}

	/**
	 * Remove "Free" label
	 * @param string $price
	 * @param WC_Product $product
	 *
	 * @return string
	 */
	public static function remove_free_price( $price, $product ) {
		$plan_id = get_post_meta( $product->get_id(), '_mondido_plan_id', true );
		if ( (int) $plan_id > 0 ) {
			return '&nbsp;';
		}

		return $price;
	}
}

new WC_Mondido_Subscriptions();
