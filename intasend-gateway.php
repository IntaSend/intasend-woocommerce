<?php

/**
 * Plugin Name: IntaSend Payment
 * Plugin URI: https://intasend.com
 * Author Name: Felix Cheruiyot (IntaSend Support)
 * Author URI: https://github.com/intasend
 * Description: Collect Mobile and card payments payments using IntaSend Payment Gateway from your WooCommerce store
 * Version: 1.0.2
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
            $this->icon = plugin_dir_url(__FILE__) . 'assets/images/IntaSend-icon.png';
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
                    'default'     => 'Pay with IntaSend',
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => 'Description',
                    'type'        => 'textarea',
                    'description' => 'This controls the description which the user sees during checkout.',
                    'default'     => 'Secure mobile and card payments.',
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
         * Show banner and powered by message in place of fields
         */
        public function payment_fields()
        {
            $plugin_path = plugin_dir_url(__FILE__);
            $banner = $plugin_path . "assets/images/guranteedCheckoutWithMpesaLight.png";
            echo wpautop(wp_kses_post("<div style='margin-bottom: 10px;'><img src=" . $banner . " alt='intasend-payment' style='max-height: 217px !important;'></div>"));
            if ($this->description) {
                if ($this->testmode) {
                    $this->description .= '</p>TEST MODE ENABLED. In test mode, you can use the card numbers listed in <a href="https://developers.intasend.com/sandbox-and-live-environments#test-details-for-sandbox-environment" target="_blank" rel="noopener noreferrer">documentation</a>.</p>';
                    $this->description  = trim($this->description);
                }
                echo wpautop(wp_kses_post($this->description));
            } else {
                echo wpautop(wp_kses_post($this->description));
            }
        }

        /*
 		 * Fields validation
		 */
        public function validate_fields()
        {
            $billing_first_name = sanitize_text_field($_POST['billing_first_name']);
            $billing_last_name = sanitize_text_field($_POST['billing_last_name']);
            $billing_email = sanitize_email($_POST['billing_email']);
            $billing_phone = sanitize_text_field($_POST['billing_phone']);

            if (empty($billing_first_name)) {
                wc_add_notice('First name is required!', 'error');
                return false;
            }
            if (empty($billing_last_name)) {
                wc_add_notice('Last name is required!', 'error');
                return false;
            }
            if (empty($billing_email)) {
                wc_add_notice('Email is required!', 'error');
                return false;
            }
            if (empty($billing_phone)) {
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

            $intasend_base_url = "https://payment.intasend.com";
            if ($this->testmode) {
                $intasend_base_url = "https://sandbox.intasend.com";
            }
            // Add order collected fields as per the reference - https://woocommerce.wp-a2z.org/oik_class/wc_order/
            $currency = get_woocommerce_currency();
            $args = array(
                'public_key' => $this->public_key,
                'api_ref' => $order_id,
                'amount' => $order->total,
                'email' => $order->get_billing_email(),
                'phone_number' => $order->get_billing_phone(),
                'first_name' => $order->get_billing_first_name(),
                'last_name' => $order->get_billing_last_name(),
                'country' => $order->get_billing_country(),
                'city' => $order->get_billing_city(),
                'zipcode' => $order->get_billing_postcode(),
                'state' => $order->get_billing_state(),
                'address' => $order->get_billing_address_1(),
                'comment' => $order->get_customer_note(),
                'currency' => $currency,
                'redirect_url' => $redirect_url,
                'host' => $intasend_base_url
            );

            $request_url = $intasend_base_url . "/api/v1/checkout/";
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
                    $response_url = $body['url'];
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
            wp_safe_redirect(site_url());
            exit();
        }

        public function complete_callback()
        {

            $ref_id = sanitize_text_field($_GET['ref_id']);
            $tracking_id = sanitize_text_field($_GET['tracking_id']);
            $signature = sanitize_text_field($_GET['signature']);
            $checkout_id = sanitize_text_field($_GET['checkout_id']);

            if (empty($ref_id)) {
                wc_add_notice('Problems experienced while confirming your order. Error details: Missing reference id. Please share with support for assistance.', 'error');
                return;
            }
            if (empty($tracking_id)) {
                wc_add_notice('Problems experienced while confirming your order. Error details: Missing tracking id. Please share with support for assistance.', 'error');
                return;
            }
            if (empty($signature)) {
                wc_add_notice('Problems experienced while confirming your order. Error details: Missing signature. Please share with support for assistance.', 'error');
                return;
            }
            if (empty($checkout_id)) {
                wc_add_notice('Problems experienced while confirming your order. Error details: Missing checkout id. Please share with support for assistance.', 'error');
                return;
            }


            $order = wc_get_order($ref_id);

            $order_id = $order->id;

            $intasend_base_url = "https://payment.intasend.com";
            if ($this->testmode) {
                $intasend_base_url = "https://sandbox.intasend.com";
            }

            $url = $intasend_base_url . "/api/v1/payment/status/";
            $args = array(
                'invoice_id' => $tracking_id,
                'signature' => $signature,
                'checkout_id' => $checkout_id
            );

            $response = wp_remote_post($url, array(
                'headers'     => array('Content-Type' => 'application/json; charset=utf-8'),
                'body'        => json_encode($args),
                'method'      => 'POST',
                'data_format' => 'body'
            ));

            if (!is_wp_error($response)) {
                try {
                    $body = json_decode(wp_remote_retrieve_body($response), true);
                    $state = $body['invoice']['state'];
                    $invoice = $body['invoice']['id'];
                    $provider = $body['invoice']['provider'];
                    $value = $body['invoice']['value'];
                    $api_ref = $body['invoice']['api_ref'];
                    $completed_time = $body['invoice']['updated_at'];

                    update_post_meta($post->ID, 'current_ref', $order_id);

                    if ($api_ref != $order_id) {
                        wc_add_notice('Problem experienced while validating your payment. Validation items do not match. Please contact support', 'error');
                        $this->redirect_to_site();
                    }

                    if ($order->total != $value) {
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
                        WC()->cart->empty_cart();

                        // Redirect to the thank you page
                        $thank_you_page = $this->get_return_url($order);

                        wp_safe_redirect($thank_you_page);
                        exit();
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
