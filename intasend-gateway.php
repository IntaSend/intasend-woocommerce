<?php

/**
 * Plugin Name: IntaSend Payment for Woocomerce
 * Plugin URI: https://intasend.com
 * Author Name: Felix Cheruiyot
 * Author URI: https://github.com/felixcheruiyot
 * Description: Collect M-Pesa and card payments payments using IntaSend Payment Gateway
 * Version: 1.1
 */

add_filter('woocommerce_payment_gateways', 'intasend_add_gateway_class');
function intasend_add_gateway_class($gateways)
{
    $gateways[] = 'WC_IntaSend_Gateway';
    return $gateways;
}

add_action('plugins_loaded', 'intasend_init_gateway_class');
function intasend_init_gateway_class()
{

    class WC_IntaSend_Gateway extends WC_Payment_Gateway
    {
        public function __construct()
        {

            $this->id = 'intasend';
            $this->icon = '';
            $this->has_fields = true;
            $this->method_title = 'IntaSend Gateway';
            $this->method_description = 'Make secure payment (Card and mobile payments)';
            $this->api_ref = uniqid("INTASEND_WCREF_");

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

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_api_' . $this->id, array($this, 'complete_callback'));
        }

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
            echo wpautop(wp_kses_post("<img src='/images/Intasend-PaymentBanner.png' alt='intasend-payment'>"));
            if ($this->description) {
                if ($this->testmode) {
                    $this->description .= 'TEST MODE ENABLED. In test mode, you can use the card numbers listed in <a href="https://developers.intasend.com/sandbox-and-live-environments#test-details-for-sandbox-environment" target="_blank" rel="noopener noreferrer">documentation</a>.';
                    $this->description  = trim($this->description);
                }
                echo wpautop(wp_kses_post($this->description));
            } else {
                echo wpautop(wp_kses_post($this->description));
            }
            echo wpautop(wp_kses_post("<div>Powered by <a href='https://intasend.com' target='_blank'>IntaSend Solutions</a>.</div>"));
        }

        /*
 		 * Fields validation
		 */
        public function validate_fields()
        {
            if (empty($_POST['billing_first_name'])) {
                wc_add_notice('First name is required!', 'error');
                return false;
            }
            if (empty($_POST['billing_last_name'])) {
                wc_add_notice('Last name is required!', 'error');
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

            // Get order details
            $order = wc_get_order($order_id);
            $order->update_status('on-hold', __('Awaiting payment', 'wc-gateway-offline'));

            $base_url = site_url();
            $redirect_url = $base_url . "/wc-api/intasend?ref_id=" . $order_id;

            $args = array(
                'public_key' => $this->public_key,
                'api_ref' => $order_id,
                'amount' => $woocommerce->cart->total,
                'email' => 'wooclient@gmail.com',
                'currency' => 'USD',
                'redirect_url' => $redirect_url
            );

            $intasend_base_url = "https://payment.intasend.com";
            if ($this->testmode) {
                $intasend_base_url = "https://sandbox.intasend.com";
            }
            $request_url = $intasend_base_url . "/api/v1/expresslinks/";
            $response = wp_remote_post($request_url, array(
                'headers'     => array('Content-Type' => 'application/json; charset=utf-8'),
                'body'        => json_encode($args),
                'method'      => 'POST',
                'data_format' => 'body'
            ));

            if (!is_wp_error($response)) {
                try {
                    $body = json_decode(wp_remote_retrieve_body($response), true);
                    error_log(print_r($body, true));
                    $response_url = $intasend_base_url . "/" . $body['url'];
                    return array(
                        'result' => 'success',
                        'redirect' => $response_url
                    );
                } catch (Exception $e) {
                    $error_message = $e->getMessage();
                    $error_message = 'Problem experienced while completing your payment.' . $error_message;
                    wc_add_notice($error_message, 'error');
                }
            }
        }

        public function redirect_to_site()
        {
            header("Location: " . site_url());
        }

        public function complete_callback()
        {
            update_option('webhook_debug', $_GET);
            $order = wc_get_order($_GET['ref_id']);
            $tracking_id = $_GET['tracking_id'];
            $order_id = $order->id;

            $intasend_base_url = "https://payment.intasend.com";
            if ($this->testmode) {
                $intasend_base_url = "https://sandbox.intasend.com";
            }

            $url = $intasend_base_url . "/api/v1/payment/status/";
            $args = array(
                'public_key' => $this->public_key,
                'invoice_id' => $tracking_id
            );

            $response = wp_remote_post($url, array(
                'headers'     => array('Content-Type' => 'application/json; charset=utf-8'),
                'body'        => json_encode($args),
                'method'      => 'POST',
                'data_format' => 'body'
            ));

            if (!is_wp_error($response)) {
                try {
                    $body = json_decode($response['body'], true);
                    $state = $body['invoice']['state'];
                    $invoice = $body['invoice']['id'];
                    $provider = $body['invoice']['provider'];
                    $value = $body['invoice']['value'];
                    $api_ref = $body['invoice']['api_ref'];
                    $completed_time = $body['invoice']['updated_at'];

                    update_post_meta($post->ID, 'current_ref', $order_id);

                    if ($api_ref != $order_id) {
                        wc_add_notice('Problem experienced while validating your payment. Validation items do not match. Please contact support.', 'error');
                        $this->redirect_to_site();
                    }

                    if ($woocommerce->cart->total != $value) {
                        wc_add_notice('Problem experienced while validating your payment. Validation items do not match on actual paid amount. Please contact support.', 'error');
                        $this->redirect_to_site();
                    }

                    if ($state == 'COMPLETE') {
                        // we received the payment
                        $order->payment_complete();
                        $order->reduce_order_stock();

                        // some notes to customer (replace true with false to make it private)
                        $order->add_order_note('Hey, your order is paid! Thank you!', true);
                        $order->add_order_note('IntaSend Invoice #' . $invoice . ' with tracking ref # ' . $api_ref . '. ' . $provider . ' completed on ' . $completed_time, false);

                        // Empty cart
                        $woocommerce->cart->empty_cart();

                        // Redirect to the thank you page
                        header("Location: " . $this->get_return_url($order));
                    } else {
                        wc_add_notice('Problem experienced while validating your payment. Please contact support.', 'error');
                        $this->redirect_to_site();
                    }
                } catch (Exception $e) {
                    $error_message = $e->getMessage();
                    $error_message = 'Problem experienced while validating your payment. Please contact support. Details: ' . $error_message;
                    wc_add_notice($error_message, 'error');
                    $this->redirect_to_site();
                }
            } else {
                wc_add_notice('Connection error experienced while validating your payment. Please contact support.', 'error');
                $this->redirect_to_site();
            }
        }
    }
}
