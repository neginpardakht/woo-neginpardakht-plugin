<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Initialize the NeginPardakht gateway.
 *
 * When the internal hook 'plugins_loaded' is fired, this function would be
 * executed and after that, a Woocommerce hook (woocommerce_payment_gateways)
 * which defines a new gateway, would be triggered.
 *
 * Therefore whenever all plugins are loaded, the NeginPardakht gateway would be
 * initialized.
 *
 * Also another Woocommerce hooks would be fired in this process:
 *  - woocommerce_currencies
 *  - woocommerce_currency_symbol
 *
 * The two above hooks allows the gateway to define some currencies and their
 * related symbols.
 */
function wc_gateway_neginpardakht_init()
{
    if (class_exists('WC_Payment_Gateway')) {
        add_filter('woocommerce_payment_gateways', 'wc_add_neginpardakht_gateway');

        function wc_add_neginpardakht_gateway($methods)
        {
            // Registers class WC_NeginPardakht as a payment method.
            $methods[] = 'WC_NeginPardakht';

            return $methods;
        }

        // Allows the gateway to define some currencies.
        add_filter('woocommerce_currencies', 'wc_neginpardakht_currencies');

        function wc_neginpardakht_currencies($currencies)
        {
            $currencies['IRHR'] = __('Iranian hezar rial', 'woo-neginpardakht-gateway');
            $currencies['IRHT'] = __('Iranian hezar toman', 'woo-neginpardakht-gateway');

            return $currencies;
        }

        // Allows the gateway to define some currency symbols for the defined currency coeds.
        add_filter('woocommerce_currency_symbol', 'wc_neginpardakht_currency_symbol', 10, 2);

        function wc_neginpardakht_currency_symbol($currency_symbol, $currency)
        {
            switch ($currency) {

                case 'IRHR':
                    $currency_symbol = __('IRHR', 'woo-neginpardakht-gateway');
                    break;

                case 'IRHT':
                    $currency_symbol = __('IRHT', 'woo-neginpardakht-gateway');
                    break;
            }

            return $currency_symbol;
        }

        class WC_NeginPardakht extends WC_Payment_Gateway
        {

            /**
             * The Auth Token
             *
             * @var string
             */
            protected $auth_token;

            /**
             * The Merchant ID
             *
             * @var string
             */
            protected $merchant_id;

            /**
             * The payment success message.
             *
             * @var string
             */
            protected $success_message;

            /**
             * The payment failure message.
             *
             * @var string
             */
            protected $failed_message;

            /**
             * The payment endpoint
             *
             * @var string
             */
            protected $payment_endpoint;

            /**
             * The verify endpoint
             *
             * @var string
             */
            protected $verify_endpoint;

            /**
             * The Order Status
             *
             * @var string
             */
            protected $order_status;


            /**
             * Constructor for the gateway.
             */
            public function __construct()
            {
                $this->id = 'WC_NeginPardakht';
                $this->method_title = __('درگاه نگین پرداخت', 'woo-neginpardakht-gateway');
                $this->method_description = __('مشتریان را به درگاه نگین پرداخت هدایت و پرداخت های آنها را پردازش می کند.', 'woo-neginpardakht-gateway');
                $this->has_fields = FALSE;
                $this->icon = apply_filters('WC_NeginPardakht_logo', dirname(WP_PLUGIN_URL . '/' . plugin_basename(dirname(__FILE__))) . '/assets/images/logo.png');

                // Load the form fields.
                $this->init_form_fields();

                // Load the settings.
                $this->init_settings();

                // Get setting values.
                $this->title = $this->get_option('title');
                $this->description = $this->get_option('description');

                $this->auth_token = $this->get_option('auth_token');
                $this->merchant_id = $this->get_option('merchant_id');

                $this->order_status = $this->get_option('order_status');

                $this->payment_endpoint = 'https://api.neginpardakht.ir/v1/transaction/request';
                $this->verify_endpoint = 'https://api.neginpardakht.ir/v1/transaction/verify';
                // $this->payment_endpoint = 'https://localhost:8030/v1/transaction/request';
                // $this->verify_endpoint = 'https://localhost:8030/v1/transaction/verify';
    
                $this->success_message = $this->get_option('success_message');
                $this->failed_message = $this->get_option('failed_message');

                if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                    add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(
                        $this,
                        'process_admin_options',
                    ));
                } else {
                    add_action('woocommerce_update_options_payment_gateways', array(
                        $this,
                        'process_admin_options',
                    ));
                }

                add_action('woocommerce_receipt_' . $this->id, array(
                    $this,
                    'neginpardakht_checkout_receipt_page',
                ));
                add_action('woocommerce_api_' . strtolower(get_class($this)), array(
                    $this,
                    'neginpardakht_checkout_return_handler',
                ));
            }

            /**
             * Admin options for the gateway.
             */
            public function admin_options()
            {
                parent::admin_options();
            }

            /**
             * Processes and saves the gateway options in the admin page.
             *
             * @return bool|void
             */
            public function process_admin_options()
            {
                parent::process_admin_options();
            }

            /**
             * Initiate some form fields for the gateway settings.
             */
            public function init_form_fields()
            {
                // Populates the inherited property $form_fields.
                $this->form_fields = apply_filters('WC_NeginPardakht_Config', array(
                    'enabled' => array(
                        'title' => __('فعال/غیر فعال', 'woo-neginpardakht-gateway'),
                        'type' => 'checkbox',
                        'label' => 'فعال شدن درگاه نگین پرداخت',
                        'description' => '',
                        'default' => 'yes',
                    ),
                    'title' => array(
                        'title' => __('عنوان', 'woo-neginpardakht-gateway'),
                        'type' => 'text',
                        'description' => __('', 'woo-neginpardakht-gateway'),
                        'default' => __('درگاه نگین پرداخت', 'woo-neginpardakht-gateway'),
                    ),
                    'description' => array(
                        'title' => __('توضیحات', 'woo-neginpardakht-gateway'),
                        'type' => 'textarea',
                        'description' => __('این توضیحات درگاه زمانی نشان داده می شود که مشتری قصد دارد تسویه حساب کند.', 'woo-neginpardakht-gateway'),
                        'default' => __('مشتریان را به درگاه نگین پرداخت هدایت می کند.', 'woo-neginpardakht-gateway'),
                    ),
                    'webservice_config' => array(
                        'title' => __('تنظیمات وب سرویس', 'woo-neginpardakht-gateway'),
                        'type' => 'title',
                        'description' => '',
                    ),
                    'auth_token' => array(
                        'title' => __('توکن دسترسی', 'woo-neginpardakht-gateway'),
                        'type' => 'text',
                        'description' => __('با مراجعه به آدرس https://app.neginpardakht.ir/developers می توانید یک توکن دسترسی ایجاد کنید.', 'woo-neginpardakht-gateway'),
                        'default' => '',
                    ),
                    'merchant_id' => array(
                        'title' => __('شناسه درگاه پرداخت', 'woo-neginpardakht-gateway'),
                        'type' => 'text',
                        'description' => __('با مراجعه به آدرس https://app.neginpardakht.ir/gateways می توانید شناسه یکتای هر درگاه پرداخت را دریافت و استفاده کنید.', 'woo-neginpardakht-gateway'),
                        'default' => '',
                    ),
                    'order_status' => array(
                        'title' => __('وضعبت سفارش', 'woo-neginpardakht-gateway'),
                        'label' => __('انتخاب وضعبت سفارش', 'woo-neginpardakht-gateway'),
                        'description' => __('وضعیت سفارش بعد از انجام عملیات پرداخت  موفق را می توانید انتخاب کنید.', 'woo-neginpardakht-gateway'),
                        'type' => 'select',
                        'options' => $this->valid_order_statuses(),
                        'default' => 'completed',
                    ),
                    'message_config' => array(
                        'title' => __('پیام های پرداخت', 'woo-neginpardakht-gateway'),
                        'type' => 'title',
                        'description' => __('پیام هایی که هنگام بازگشت مشتری از درگاه پرداخت به سایت یا فروشگاه نمایش داده می شوند.', 'woo-neginpardakht-gateway'),
                    ),
                    'success_message' => array(
                        'title' => __('پیام در صورت موفقیت', 'woo-neginpardakht-gateway'),
                        'type' => 'textarea',
                        'description' => __('پیامی را که می خواهید پس از پرداخت موفقیت آمیز به مشتری نمایش دهید وارد کنید. همچنین می‌توانید این متغیرهای {track_id}، {order_id} را برای نمایش شناسه سفارش و شناسه پیگیری انتخاب کنید.', 'woo-neginpardakht-gateway'),
                        'default' => __('پرداخت شما با موفقیت انجام شد. شناسه تراکنش: {track_id}', 'woo-neginpardakht-gateway'),
                    ),
                    'failed_message' => array(
                        'title' => __('پیام در صورت عدم موفقیت', 'woo-neginpardakht-gateway'),
                        'type' => 'textarea',
                        'description' => __('پیامی را که می خواهید پس از عدم موفقیت در عملیات پرداخت به مشتری نمایش دهید، وارد کنید. همچنین می‌توانید این متغیرهای {track_id}، {order_id} را برای نمایش شناسه سفارش و شناسه پیگیری انتخاب کنید.', 'woo-neginpardakht-gateway'),
                        'default' => __('پرداخت شما انجام نشد. لطفا دوباره امتحان کنید یا در صورت بروز مجدد مشکل با مدیر سایت تماس بگیرید.', 'woo-neginpardakht-gateway'),
                    ),
                ));
            }

            /**
             * Process the payment and return the result.
             *
             * see process_order_payment() in the Woocommerce APIs
             *
             * @param int $order_id
             *
             * @return array
             */
            public function process_payment($order_id)
            {
                $order = new WC_Order($order_id);

                return array(
                    'result' => 'success',
                    'redirect' => $order->get_checkout_payment_url(TRUE),
                );
            }

            /**
             * Add NeginPardakht Checkout items to receipt page.
             */
            public function neginpardakht_checkout_receipt_page($order_id)
            {
                global $woocommerce;

                $order = new WC_Order($order_id);
                $currency = $order->get_currency();
                $currency = apply_filters('WC_NeginPardakht_Currency', $currency, $order_id);

                $auth_token = $this->auth_token;
                $merchant_id = $this->merchant_id;

                /** @var \WC_Customer $customer */
                $customer = $woocommerce->customer;

                // Customer information
                $phone = $customer->get_billing_phone();
                $mail = $customer->get_billing_email();
                $first_name = $customer->get_billing_first_name();
                $last_name = $customer->get_billing_last_name();
                $name = $first_name . ' ' . $last_name;

                $amount = wc_neginpardakht_get_amount(intval($order->get_total()), $currency);
                $desc = __('Woo Order number #', 'woo-neginpardakht-gateway') . $order->get_order_number();
                $callback = add_query_arg('wc_order', $order_id, WC()->api_request_url('wc_neginpardakht'));

                if (empty($amount)) {
                    $notice = __('Selected currency is not supported', 'woo-neginpardakht-gateway'); //todo
                    wc_add_notice($notice, 'error');
                    wp_redirect($woocommerce->cart->get_checkout_url());

                    exit;
                }

                $data = array(
                    'orderId' => $order_id,
                    'merchantId' => $merchant_id,
                    'amount' => $amount,
                    'callbackUrl' => $callback,
                    'description' => $desc
                );

                $headers = array(
                    'Content-Type' => 'application/json',
                    'AuthToken' => $auth_token,
                );

                $args = array(
                    'body' => json_encode($data),
                    'headers' => $headers,
                    'timeout' => 60,
                    
                    // only for development
                    'sslverify' => false,
                    'reject_unsafe_urls' => false
                );

                // var_dump($args);
                // die();

                $response = $this->call_gateway_endpoint($this->payment_endpoint, $args);
                
                if (is_wp_error($response)) {
                    $note = $response->get_error_message();
                    $order->add_order_note($note);
                    wp_redirect($woocommerce->cart->get_checkout_url());

                    exit;
                }

                
                $http_status = wp_remote_retrieve_response_code($response);
                $result = wp_remote_retrieve_body($response);
                $result = json_decode($result);
                
              
                if ($http_status != 200 || empty($result)) {
                    $note = '';
                    $note .= __('An error occurred while creating the transaction.', 'woo-neginpardakht-gateway');
                    $note .= '<br/>';
                    $note .= sprintf(__('error status: %s', 'woo-neginpardakht-gateway'), $http_status);

                    if (!empty($result->error_code) && !empty($result->error_message)) {
                        $note .= '<br/>';
                        $note .= sprintf(__('error code: %s', 'woo-neginpardakht-gateway'), $result->error_code);
                        $note .= '<br/>';
                        $note .= sprintf(__('error message: %s', 'woo-neginpardakht-gateway'), $result->error_message);
                        $order->add_order_note($note);
                        $notice = $result->error_message;
                        wc_add_notice($result->error_message . '<br/>کد خطا : ' . $result->error_code, 'error');
                    }

                    wp_redirect($woocommerce->cart->get_checkout_url());

                    exit;
                }

               
                if (!$result->status || !empty($result->error_code) || !empty($result->error_message)) {
                    wc_add_notice($result->error_message . '<br/>کد خطا : ' . $result->error_code, 'error');
                    wp_redirect($woocommerce->cart->get_checkout_url());

                    exit;
                }

                // Save ID of this transaction
                update_post_meta($order_id, 'neginpardakht_transaction_id', $result->trackingCode);

                // Set status of the transaction to 1 as it's primary value.
                update_post_meta($order_id, 'neginpardakht_transaction_status', 1);

                $note = sprintf(__('transaction id: %s', 'woo-neginpardakht-gateway'), $result->trackingCode);
                $order->add_order_note($note);

                wp_redirect($result->paymentUrl);

                exit;
            }

            /**
             * Handles the return from processing the payment.
             */
            public function neginpardakht_checkout_return_handler()
            {
                global $woocommerce;

                // Check method post or get
                $method = $_SERVER['REQUEST_METHOD'];
                if ($method == 'POST') {
                    $status = sanitize_text_field($_POST['status']);
                    $track_id = sanitize_text_field($_POST['trackingCode']);
                    $id = sanitize_text_field($_POST['trackingCode']);
                    $order_id = sanitize_text_field($_POST['orderId']);
                }
                elseif ($method == 'GET') {
                    $status = sanitize_text_field($_GET['status']);
                    $track_id = sanitize_text_field($_GET['trackingCode']);
                    $id = sanitize_text_field($_GET['trackingCode']);
                    $order_id = sanitize_text_field($_GET['orderId']);
                }

                if (empty($id) || empty($order_id)) {
                    $this->neginpardakht_display_invalid_order_message();
                    wp_redirect($woocommerce->cart->get_checkout_url());

                    exit;
                }

                $order = wc_get_order($order_id);

                if (empty($order)) {
                    $this->neginpardakht_display_invalid_order_message();
                    wp_redirect($woocommerce->cart->get_checkout_url());

                    exit;
                }

                if ($order->get_status() == 'completed' || $order->get_status() == 'processing') {
                    $this->neginpardakht_display_success_message($order_id);
                    wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));

                    exit;
                }


                if (get_post_meta($order_id, 'neginpardakht_transaction_status', TRUE) >= 100) {
                    $this->neginpardakht_display_success_message($order_id);
                    wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));

                    exit;
                }

                // Stores order's meta data.
                update_post_meta($order_id, 'neginpardakht_transaction_status', $status);
                update_post_meta($order_id, 'neginpardakht_track_id', $track_id);
                update_post_meta($order_id, 'neginpardakht_transaction_id', $id);
                update_post_meta($order_id, 'neginpardakht_transaction_order_id', $order_id);

                if ($status != 2) {
                    $order->update_status('failed');
                    $this->neginpardakht_display_failed_message($order_id, $status);
                    $order->add_order_note($this->otherStatusMessages($status));
                    wp_redirect($woocommerce->cart->get_checkout_url());

                    exit;
                }

               //Check Double Spending and Order valid status
               if ($this->double_spending_occurred($order_id, $id)) {
                   $this->neginpardakht_display_failed_message($order_id, 0);
                   $note = $this->otherStatusMessages(0);
                   $order->add_order_note($note);
                   wp_redirect($woocommerce->cart->get_checkout_url());

                   exit;
                }

                $auth_token = $this->auth_token;
                $merchant_id = $this->merchant_id;

                $data = array(
                    'merchantId' => $merchant_id,
                    'trackingCode' => $track_id,
                );

                $headers = array(
                    'Content-Type' => 'application/json',
                    'AuthToken' => $auth_token,
                );

                $args = array(
                    'body' => json_encode($data),
                    'headers' => $headers,
                    'timeout' => 60,

                    // only for development
                    'sslverify' => false,
                    'reject_unsafe_urls' => false
                );

                $response = $this->call_gateway_endpoint($this->verify_endpoint, $args);

                if (is_wp_error($response)) {
                    $note = $response->get_error_message();
                    $order->add_order_note($note);
                    wp_redirect($woocommerce->cart->get_checkout_url());

                    exit;
                }

                $http_status = wp_remote_retrieve_response_code($response);
                $result = wp_remote_retrieve_body($response);
                $result = json_decode($result);


                if ($http_status != 200) {
                    $note = '';
                    $note .= __('An error occurred while verifying the transaction.', 'woo-neginpardakht-gateway');
                    $note .= '<br/>';
                    $note .= sprintf(__('error status: %s', 'woo-neginpardakht-gateway'), $http_status);

                    if (!empty($result->error_code) && !empty($result->error_message)) {
                        $note .= '<br/>';
                        $note .= sprintf(__('error code: %s', 'woo-neginpardakht-gateway'), $result->error_code);
                        $note .= '<br/>';
                        $note .= sprintf(__('error message: %s', 'woo-neginpardakht-gateway'), $result->error_message);
                        wc_add_notice($result->error_message . '<br/>کد خطا : ' . $result->error_code, 'error');
                    }

                    $order->add_order_note($note);
                    $order->update_status('failed');
                    wp_redirect($woocommerce->cart->get_checkout_url());

                    exit;
                } else {

                    $verify_status = empty($result->status) ? NULL : $result->status;
                    $verify_track_id = empty($result->trackingCode) ? NULL : $result->trackingCode;
                    $verify_id = empty($result->id) ? NULL : $result->id;
                    $verify_order_id = empty($result->orderId) ? NULL : $result->orderId;
                    $verify_amount = empty($result->amount) ? NULL : $result->amount;
                    $verify_card_no = empty($result->payment->cardNumberMasked) ? NULL : $result->payment->cardNumberMasked;
                    $verify_hashed_card_no = empty($result->payment->cardNumberHash) ? NULL : $result->payment->cardNumberHash;
                    $verify_date = empty($result->payment->date) ? NULL : $result->payment->date;

                    // Check status
                    $status_helper = !empty($this->valid_order_statuses()[$this->order_status]) ? $this->order_status : 'completed';
                    $status = ($verify_status == true) ? $status_helper : 'failed';

                    // Completed
                    $note = sprintf(__('Transaction payment status: %s', 'woo-neginpardakht-gateway'), $verify_status);
                    $note .= '<br/>';
                    $note .= sprintf(__('NeginPardakht tracking id: %s', 'woo-neginpardakht-gateway'), $verify_track_id);
                    $note .= '<br/>';
                    $note .= sprintf(__('Payer card number: %s', 'woo-neginpardakht-gateway'), $verify_card_no);
                    $note .= '<br/>';
                    $note .= sprintf(__('Payer hashed card number: %s', 'woo-neginpardakht-gateway'), $verify_hashed_card_no);
                    $order->add_order_note($note);

                    // Updates order's meta data after verifying the payment.
                    update_post_meta($order_id, 'neginpardakht_transaction_status', $verify_status);
                    update_post_meta($order_id, 'neginpardakht_track_id', $verify_track_id);
                    update_post_meta($order_id, 'neginpardakht_transaction_id', $verify_id);
                    update_post_meta($order_id, 'neginpardakht_transaction_order_id', $verify_order_id);
                    update_post_meta($order_id, 'neginpardakht_transaction_amount', $verify_amount);
                    update_post_meta($order_id, 'neginpardakht_payment_card_no', $verify_card_no);
                    update_post_meta($order_id, 'neginpardakht_payment_date', $verify_date);

                    $currency = $order->get_currency();
                    $currency = apply_filters('WC_NeginPardakht_Currency', $currency, $order_id);
                    $amount = wc_neginpardakht_get_amount(intval($order->get_total()), $currency);

                    if (empty($verify_status) || empty($verify_track_id) || empty($verify_amount) || $verify_amount != $amount) {
                        $note = __('Error in transaction status or inconsistency with payment gateway information', 'woo-neginpardakht-gateway');
                        $order->add_order_note($note);
                        $status = 'failed';
                    }

                    if ($status == 'failed') {
                        $order->update_status($status);
                        $this->neginpardakht_display_failed_message($order_id);

                        wp_redirect($woocommerce->cart->get_checkout_url());

                        exit;
                    }

                    $order->payment_complete($verify_id);
                    $order->update_status($status);
                    $woocommerce->cart->empty_cart();
                    $this->neginpardakht_display_success_message($order_id);
                    wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));

                    exit;
                }
            }

            /**
             * Shows an invalid order message.
             *
             * @see neginpardakht_checkout_return_handler().
             */
            private function neginpardakht_display_invalid_order_message($msgNumber = null)
            {
                $msg = $this->otherStatusMessages($msgNumber);
                $notice = '';
                $notice .= __('سفارشی با این شناسه یافت نشد.', 'woo-neginpardakht-gateway');
                $notice .= '<br/>';
                $notice .= __('لطفا دوباره سعی کنید و در صورت عدم رفع مشکل با مدیر وب سایت تماس بگیرید.', 'woo-neginpardakht-gateway');
                $notice = $notice . "<br>" . $msg;
                wc_add_notice($result->error_message . '<br/>کد خطا : ' . $result->error_code, 'error');
            }

            /**
             * Shows a success message
             *
             * This message is configured at the admin page of the gateway.
             *
             * @see neginpardakht_checkout_return_handler()
             *
             * @param $order_id
             */
            private function neginpardakht_display_success_message($order_id)
            {
                $track_id = get_post_meta($order_id, 'neginpardakht_track_id', TRUE);
                $notice = wpautop(wptexturize($this->success_message));
                $notice = str_replace("{track_id}", $track_id, $notice);
                $notice = str_replace("{order_id}", $order_id, $notice);
                wc_add_notice($notice, 'success');
            }

            /**
             * Calls the gateway endpoints.
             *
             * Tries to get response from the gateway for 4 times.
             *
             * @param $url
             * @param $args
             *
             * @return array|\WP_Error
             */
            private function call_gateway_endpoint($url, $args)
            {
                $number_of_connection_tries = 4;
                while ($number_of_connection_tries) {
                    //$response = wp_safe_remote_post($url, $args);
                    $response = wp_remote_post($url, $args);
                    if (is_wp_error($response)) {
                        $number_of_connection_tries--;
                        continue;
                    } else {
                        break;
                    }
                }

                return $response;
            }

            /**
             * Shows a failure message for the unsuccessful payments.
             *
             * This message is configured at the admin page of the gateway.
             *
             * @see neginpardakht_checkout_return_handler()
             *
             * @param $order_id
             */
            private function neginpardakht_display_failed_message($order_id, $msgNumber = null)
            {
                $track_id = get_post_meta($order_id, 'neginpardakht_track_id', TRUE);
                $msg = $this->otherStatusMessages($msgNumber);
                $notice = wpautop(wptexturize($this->failed_message));
                $notice = str_replace("{track_id}", $track_id, $notice);
                $notice = str_replace("{order_id}", $order_id, $notice);
                $notice = $notice . "<br>" . $msg;
                wc_add_notice($notice, 'error');
            }

            /**
             * Checks if double-spending is occurred.
             *
             * @param $order_id
             * @param $remote_id
             *
             * @return bool
             */
            private function double_spending_occurred($order_id, $remote_id)
            {
                if (get_post_meta($order_id, 'neginpardakht_transaction_id', TRUE) != $remote_id) {
                    return TRUE;
                }

                return FALSE;
            }

            /**
             * @param null $msgNumber
             * @return string
             */
            public function otherStatusMessages($msgNumber = null)
            {
                switch ($msgNumber) {
                    case '0':
                        $msg = 'تراکنش در انتظار پرداخت است';
                        break;
                    case '1':
                        $msg = 'تراکنش توکن شده است';
                        break;
                    case '2':
                        $msg = 'تراکنش با موفقیت انجام شده است';
                        break;
                    case '3':
                        $msg = 'تراکنش تایید شده است';
                        break;
                    case '4':
                        $msg = 'وجه تراکنش بازگردانده شده است';
                        break;
                    case '5':
                        $msg = 'تسویه حساب شده است';
                        break;

                    case '7':
                        $msg = 'انصراف از انجام تراکنش توسط کاربر';
                        break;
                    case '8':
                        $msg = 'تراکنش منقضی شده است';
                        break;
                    case '9':
                        $msg = 'تراکنش انجام نشده است';
                        break;
                    
                    case null:
                        $msg = 'خطای غیر منتظره!';
                        $msgNumber = '1000';
                        break;
                }

                return $msg . '<br/> کد خطا : ' . $msgNumber;

            }

           /**
           * @return string[]
           */
            private function valid_order_statuses() {
                return [
                  'completed' => 'completed',
                  'processing' => 'processing',
                ];
            }
        }

    }
}


/**
 * Add a function when hook 'plugins_loaded' is fired.
 *
 * Registers the 'wc_gateway_neginpardakht_init' function to the
 * internal hook of Wordpress: 'plugins_loaded'.
 *
 * @see wc_gateway_neginpardakht_init()
 */
add_action('plugins_loaded', 'wc_gateway_neginpardakht_init');

