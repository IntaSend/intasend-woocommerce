<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Automattic\WooCommerce\StoreApi\Payments\PaymentContext;
use Automattic\WooCommerce\StoreApi\Payments\PaymentResult;

// #[\AllowDynamicProperties]
final class WC_Intasend_Blocks_Support extends AbstractPaymentMethodType {

	/**
	 * Payment method name/id/slug.
	 *
	 * @var string
	 */
	protected $name = 'intasend';

	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_intasend_settings', array() );
		$this->gateway  = new WC_IntaSend_Gateway();
		// add_action( 'woocommerce_rest_checkout_process_payment_with_context', array( $this, 'failed_payment_notice' ), 8, 2 );

	}

	// /**
	//  * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	//  *
	//  * @return boolean
	//  */
	// public function is_active() {
	// 	return $this->gateway->is_available();
	// }


	/**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @return boolean
	 */
	public function is_active() {
		$payment_gateways_class = WC()->payment_gateways();
		$payment_gateways       = $payment_gateways_class->payment_gateways();
		return $payment_gateways['intasend']->is_available();
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		$script_asset_path = plugins_url('/assets/blocks/frontend/blocks.asset.php', WC_INTASEND_MAIN_FILE);
		$script_asset      = file_exists( $script_asset_path )
			? require( $script_asset_path )
			: array(
				'dependencies' => array(),
				'version'      => '1.2.0'
			);
		$script_url = plugins_url('/assets/blocks/frontend/blocks.js', WC_INTASEND_MAIN_FILE );

		wp_register_script(
			'wc-intasend-payments-blocks',
			$script_url,
			$script_asset[ 'dependencies' ],
			$script_asset[ 'version' ],
			true
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'wc-intasend-payments-blocks', 'woocommerce-gateway-intasend');
		}

		return [ 'wc-intasend-payments-blocks' ];
	}


	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {

		$payment_gateways_class = WC()->payment_gateways();
		$payment_gateways       = $payment_gateways_class->payment_gateways();
		$gateway                = $payment_gateways['intasend'];

		$plugin_path = plugin_dir_url(__FILE__);
		$banner = $plugin_path . "assets/images/badgeWithCardOnly.png";
		if($payment_gateways['intasend']->trust_badge){
			$banner = $plugin_path . "assets/images/badgeWithCardnMpesa.png";
		}

		return [
			// 'logo_url'          => array( $payment_gateways['intasend']->banner ),
			'logo_url'          => $banner,
			'title'       => $this->get_setting( 'title' ),
			'description' => $this->get_setting( 'description' ),
			// 'supports'    => array_filter( $this->gateway->supports, [ $this->gateway, 'supports' ] )
		];
	}

	/**
	 * Add failed payment notice to the payment details.
	 *
	 * @param PaymentContext $context Holds context for the payment.
	 * @param PaymentResult  $result  Result object for the payment.
	 */
	// public function failed_payment_notice( PaymentContext $context, PaymentResult &$result ) {
	// 	if ( 'intasend' === $context->payment_method ) {
	// 		add_action(
	// 			'wc_gateway_intasend_process_payment_error',
	// 			function( $failed_notice ) use ( &$result ) {
	// 				$payment_details                 = $result->payment_details;
	// 				$payment_details['errorMessage'] = wp_strip_all_tags( $failed_notice );
	// 				$result->set_payment_details( $payment_details );
	// 			}
	// 		);
	// 	}
	// }

}
