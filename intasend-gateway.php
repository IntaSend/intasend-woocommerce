<?php

/**
 * Plugin Name: IntaSend Payment for Woocomerce
 * Plugin URI: https://intasend.com
 * Author Name: Felix Cheruiyot
 * Author URI: https://github.com/felixcheruiyot
 * Description: Collect M-Pesa and card payments payments using IntaSend Payment Gateway
 * Version: 1.0.0
 */


/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter('woocommerce_payment_gateways', 'intasend_add_gateway_class');
function intasend_add_gateway_class($gateways)
{
    $gateways[] = 'WC_IntaSend_Gateway'; // your class name is here
    return $gateways;
}

/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action('plugins_loaded', 'intasend_init_gateway_class');
function intasend_init_gateway_class()
{

    class WC_IntaSend_Gateway extends WC_Payment_Gateway
    {

        /**
         * Class constructor, more about it in Step 3
         */
        public function __construct()
        {

            $this->id = 'intasend'; // payment gateway plugin ID
            $this->icon = ''; // URL of the icon that will be displayed on checkout page near your gateway name
            $this->has_fields = true; // in case you need a custom form
            $this->method_title = 'IntaSend Gateway';
            $this->method_description = 'Collect M-Pesa and card payments payments using IntaSend Payment Gateway'; // will be displayed on the options page
            $this->api_ref = uniqid("INTASEND_WCREF_"); // For tracking and reconcilliation purposes

            // gateways can support subscriptions, refunds, saved payment methods,
            // but in this tutorial we begin with simple payments
            $this->supports = array(
                'products'
            );

            // Method with all the options fields
            $this->init_form_fields();

            // Load the settings.
            $this->init_settings();
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->enabled = $this->get_option('enabled');
            $this->testmode = 'yes' === $this->get_option('testmode');
            $this->public_key = $this->testmode ? $this->get_option('test_public_key') : $this->get_option('live_public_key');

            // This action hook saves the settings
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            // We need custom JavaScript to obtain a token
            add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));

            // You can also register a webhook here
            // add_action( 'woocommerce_api_{webhook name}', array( $this, 'webhook' ) );

        }

        /**
         * Plugin options, we deal with it in Step 3 too
         */
        public function init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title'       => 'Enable/Disable',
                    'label'       => 'Enable IntaSend Gateway',
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no'
                ),
                'title' => array(
                    'title'       => 'Title',
                    'type'        => 'text',
                    'description' => 'This controls the title which the user sees during checkout.',
                    'default'     => 'Lipa na MPesa, Visa, and MasterCard (card payments)',
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => 'Description',
                    'type'        => 'textarea',
                    'description' => 'This controls the description which the user sees during checkout.',
                    'default'     => 'Pay with MPesa or card securely using IntaSend Gateway.',
                ),
                'testmode' => array(
                    'title'       => 'Test mode',
                    'label'       => 'Enable Test Mode',
                    'type'        => 'checkbox',
                    'description' => 'Place the payment gateway in test mode using test API keys.',
                    'default'     => 'yes',
                    'desc_tip'    => true,
                ),
                'test_public_key' => array(
                    'title'       => 'Test Public Key',
                    'type'        => 'text'
                ),
                'live_public_key' => array(
                    'title'       => 'Live Public Key',
                    'type'        => 'text',
                )
            );
        }

        /**
         * You will need it if you want your custom form, Step 4 is about it
         */
        public function payment_fields()
        {
            echo wpautop(wp_kses_post("<img src='https://intasend.com/static/img/logo.cd2ba004a5ab.png' alt='intasend-payment'>"));
            if ($this->description) {
                if ($this->testmode) {
                    $this->description .= ' TEST MODE ENABLED. In test mode, you can use the card numbers listed in <a href="https://developers.intasend.com/sandbox-and-live-environments#test-details-for-sandbox-environment" target="_blank" rel="noopener noreferrer">documentation</a>.';
                    $this->description  = trim($this->description);
                }
                echo wpautop(wp_kses_post($this->description));
            } else {
                echo wpautop(wp_kses_post($this->description));
            }
        }

        /*
		 * Custom CSS and JS, in most cases required only when you decided to go with a custom form
		 */
        public function payment_scripts()
        {
            global $woocommerce;

            // we need JavaScript to process a token only on cart/checkout pages, right?
            if (!is_cart() && !is_checkout() && !isset($_GET['pay_for_order'])) {
                return;
            }

            // if our payment gateway is disabled, we do not have to enqueue JS too
            if ('no' === $this->enabled) {
                return;
            }

            // no reason to enqueue JavaScript if API keys are not set
            if (empty($this->public_key)) {
                wc_add_notice('This transaction will fail to process. IntaSend public key is required', 'error');
                return;
            }

            // // do not work with card detailes without SSL unless your website is in a test mode
            if (!$this->testmode && !is_ssl()) {
                wc_add_notice('This transaction will fail to process. SSL must be enabled to use IntaSend plugin. Enable testmode instead if you are in development mode.', 'error');
                return;
            }

            // // let's suppose it is our payment processor JavaScript that allows to obtain a token
            wp_enqueue_script('intasend_js', 'https://unpkg.com/intasend-inlinejs-sdk@2.0.4/build/intasend-inline.js');

            // // and this is our custom JS in your plugin directory that works with token.js
            wp_register_script('woocommerce_intasend', plugins_url('intasend.js', __FILE__), array('jquery', 'intasend_js'));

            // // in most payment processors you have to use PUBLIC KEY to obtain a token
            $currency = "KES";
            if (get_woocommerce_currency() == 'USD') {
                $currency = "USD";
            }


            wp_localize_script('woocommerce_intasend', 'intasend_params', array(
                'public_key' => $this->public_key,
                'testmode' => $this->testmode,
                'total' => $woocommerce->cart->total,
                'currency' => $currency,
                'api_ref' => $this->api_ref
            ));

            wp_enqueue_script('woocommerce_intasend');
        }

        /*
 		 * Fields validation, more in Step 5
		 */
        public function validate_fields()
        {
            if (empty($_POST['billing_first_name'])) {
                wc_add_notice('First name is required!', 'error');
                return false;
            }
            if (empty($_POST['billing_email'])) {
                wc_add_notice('Email is required!', 'error');
                return false;
            }
            if (empty($_POST['billing_phone'])) {
                wc_add_notice('Phone number is required!', 'error');
                return false;
            }
            return true;
        }

        /*
		 * Check if payment is successful and complete transaction
		 */
        public function process_payment($order_id)
        {
            global $woocommerce;

            // Validate SSL
            if (!$this->testmode && !is_ssl()) {
                if (!$this->testmode && !is_ssl()) {
                    wc_add_notice('Failed to place order. SSL must be enabled to use IntaSend plugin. Enable testmode instead if you are in development mode.', 'error');
                    return;
                }
            }

            // Ensure you have public key
            if (empty($this->public_key)) {
                wc_add_notice('This transaction will fail to process. IntaSend public key is required', 'error');
                return;
            }

            if (empty($_POST['intasend_tracking_id'])) {
                wc_add_notice('Problem experienced while processing your request. Failed to obtain tracking id. Please contact support for assistance.', 'error');
                return;
            }

            // Get order details
            $order = wc_get_order($order_id);
            $order->update_status('on-hold', __('Validating payment status', 'wc-gateway-offline'));

            /*
              * Array with parameters for API interaction
             */
            $intasend_lookup_id = $_POST['intasend_lookup_id'];
            $args = array(
                'public_key' => $this->public_key,
                'invoice_id' => $intasend_lookup_id
            );

            /*
             * Your API interaction could be built with wp_remote_post()
              */
            $url = "https://payment.intasend.com/api/v1/payment/status/";
            if ($this->testmode) {
                $url = "https://sandbox.intasend.com/api/v1/payment/status/";
            }
            $response = wp_remote_post($url, $args);

            if (!is_wp_error($response)) {
                try {

                    $body = json_decode($response['body'], true);
                    $state = $body['response']['invoice']['state'];
                    $invoice = $body['response']['invoice']['id'];
                    $provider = $body['response']['invoice']['provider'];
                    $value = $body['response']['invoice']['value'];
                    $api_ref = $body['response']['invoice']['api_ref'];

                    if ($api_ref != $this->api_ref) {
                        wc_add_notice('Problem experienced while validating your payment. Validation items do not match. Please contact support.', 'error');
                        return;
                    }

                    if ($woocommerce->cart->total != $value) {
                        wc_add_notice('Problem experienced while validating your payment. Validation items do not match on actual paid amount. Please contact support.', 'error');
                        return;
                    }

                    if ($state == 'COMPLETE') {
                        // we received the payment
                        $order->payment_complete();
                        $order->reduce_order_stock();

                        // some notes to customer (replace true with false to make it private)
                        $api_ref = (string)$this->api_ref;
                        $order->add_order_note('Hey, your order is paid! Thank you!', true);
                        $order->add_order_note('IntaSend Invoice #' . $invoice, false);
                        $order->add_order_node('IntaSend Tracking ref ' . $api_ref, false);
                        $order->add_order_note('IntaSend Lookup ref ' . $intasend_lookup_id, false);
                        $order->add_order_note('Payment Method - ' . $provider, false);
                        $order->add_order_note('Status - ' . $state, false);

                        // Empty cart
                        $woocommerce->cart->empty_cart();

                        // Redirect to the thank you page
                        return array(
                            'result' => 'success',
                            'redirect' => $this->get_return_url($order)
                        );
                    } else {
                        wc_add_notice('Problem experienced while validating your payment. Please contact support.', 'error');
                        return;
                    }
                } catch (Exception $e) {
                    $error_message = $e->getMessage();
                    $error_message = 'Problem experienced while validating your payment. Please contact support. Details: ' . $error_message;
                    wc_add_notice($error_message, 'error');
                }
            } else {
                wc_add_notice('Connection error experienced while validating your payment. Please contact support.', 'error');
                return;
            }
        }

        /*
		 * In case you need a webhook, like PayPal IPN etc
		 */
        public function webhook()
        {
            $order = wc_get_order($_GET['id']);
            $order->payment_complete();
            $order->reduce_order_stock();

            update_option('webhook_debug', $_GET);
        }
    }
}
