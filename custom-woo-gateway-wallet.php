<?php

/**
 * Plugin Name: Custom Woo Gateway Wallet
 * Plugin URI:  
 * Author:      Roland Santos
 * Author URI:  https://github.com/weward
 * Description: This plugin adds wallet feature to WooCommerce
 * Version:     0.1.0
 * License:     GPL-2.0+
 * License URL: http://www.gnu.org/licenses/gpl-2.0.txt
 * text-domain: custom_woo_gateway
 */

define('_THIS_PAYMENT_GATEWAY', 'WC_Custom_Woo_Gateway_Wallet');
define('_THIS_ID', 'custom_woo_gateway');

// if (! in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_options('active_plugins')))) return;
if (!function_exists('is_woocommerce_activated')) {
    function is_woocommerce_activated()
    {
        if (class_exists('woocommerce')) {
            return true;
        } else {
            return false;
        }
    }
}

add_action('plugins_loaded', 'custom_woo_gateway_init', 11);


function custom_woo_gateway_init()
{

    if (class_exists('WC_Payment_Gateway')) {
        class WC_Custom_Woo_Gateway_Wallet extends WC_Payment_gateway
        {
            protected $user_id;
            protected $wallet_balance;
            protected $default_wallet_balance = 250000.00;

            public function __construct()
            {
                
                $this->user_id = get_current_user_id();
                $this->id = 'custom_woo_gateway';
                $this->icon = apply_filters('custom_woo_gateway', plugins_url('/assets/images/icon-wallet.png', __FILE__));
                $this->has_fields = false;
                $this->method_title = __('Wallet', 'custom_woo_gateway');
                $this->method_description = __('Pay using your WooShop Wallet.', 'custom_woo_gateway');
                
                $this->title = $this->get_option('title');
                $this->description = $this->get_option('description');
                $this->instructions = $this->get_option('instructions', $this->description);
                
                $this->init_wallet_balance();
                $this->init_form_fields();
                $this->hook_checkout_form_fields();
                $this->init_settings();  // super class

                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
                
            }

            /**
             * Load Wallet balance
             *  create if not existing
             */
            public function init_wallet_balance()
            {
                if (!get_user_meta($this->user_id, 'wallet_balance', true)) {
                    update_user_meta($this->user_id, 'wallet_balance', $this->default_wallet_balance);
                    $this->wallet_balance = $this->default_wallet_balance;

                    return;
                }

                $this->wallet_balance = get_user_meta($this->user_id, 'wallet_balance', true);
            }

            /**
             * Admin Form fields
             */
            public function init_form_fields()
            {
                $this->form_fields = apply_filters('custom_woo_gateway_fields', array(
                    'enabled' => array(
                        'title' => __('Enable/Disable', 'custom_woo_gateway'),
                        'type' => 'checkbox',
                        'label' => __('Enable or Disable Payments using the WooShop Wallet', 'custom_woo_gateway'),
                        'default' => 'yes'
                    ),
                    'title' => array(
                        'title' => __('WooShop Wallet Title', 'custom_woo_gateway'),
                        'type' => 'text',
                        'default' => __('WooShop Wallet', 'custom_woo_gateway'),
                        'desc_tip' => true,
                        'description' => __('Add a title for your WooShop Wallet that will be displayed to the customer checkout.', 'custom_woo_gateway')
                    ),
                    'description' => array(
                        'title' => __('WooShop Wallet Description', 'custom_woo_gateway'),
                        'type' => 'textarea',
                        'default' => __('Pay using your WooShop Wallet', 'custom_woo_gateway'),
                        'desc_tip' => true,
                        'description' => __('Add a description for your WooShop Wallet that will be displayed to the customer on checkout.', 'custom_woo_gateway')
                    ),
                    'instructions' => array(
                        'title' => __('Instructions', 'custom_woo_gateway'),
                        'type' => 'textarea',
                        'default' => __('test', 'custom_woo_gateway'),
                        'desc_tip' => true,
                        'description' => __('Instructions that will be added to the thank you page and order email', 'custom_woo_gateway')
                    ),
                    'default_balance' => array(
                        'title' => __('Default Balance', 'custom_woo_gateway'),
                        'type' => 'text',
                        'default' => $this->default_wallet_balance,
                        'desc_tip' => true,
                        'description' => __('Set the default WooShop Wallet Balance', 'custom_woo_gateway'),
                        'custom_attributes' => array('readonly' => 'readonly')
                    ),
                ));
            }

            /**
             * Add Form Fields in Plugin's Description in Checkout Page
             */
            public function hook_checkout_form_fields()
            {
                require_once dirname(__FILE__) . '/forms/checkout-wallet-form.php';
            }

            /**
             * @override
             */
            public function process_payment($order_id)
            {
                // global $woocommerce;
                $order = wc_get_order($order_id);
                error_log($order);
                
                // reduce wallet balance
                $currentBalance = get_user_meta($this->user_id, 'wallet_balance', true);
                $orderTotal = $order->get_total();
                
                if ($currentBalance >= $orderTotal) {
                    // reduce wallet balance
                    $newBalance = $currentBalance - $orderTotal;
                    update_user_meta($this->user_id, 'wallet_balance', $newBalance);

                    // Payment has been processed. Awaiting fulfillment (delivery, etc)
                    $order->update_status('processing',  __('Paid via WooShop Wallet', 'custom_woo_gateway'));
                    $order->add_order_note(__('Payment successful via WooShop Wallet.', 'custom_woo_gateway'));

                    $order->payment_complete();
                    // reduce stock --if applicable
                    $order->reduce_order_stock();   // per line item
                    // Reduce stock levels
                    // reduce_stock_on_order($order_id, $order);
                    $order->record_product_sales(); 

                    WC()->cart->empty_cart();
                } else {
                    $order->update_status('failed', __('Payment using WooShop Wallet failed.', 'custom_woo_gateway'));
                    wc_add_notice(__('Insufficient WooShop wallet balance!', 'custom_woo_gateway'), 'error');

                    return array(
                        'result'   => 'failure',
                        'redirect' => '',
                    );
                }

                wc_add_notice(__('Order was placed successfully!', 'custom_woo_gateway'), 'success');

                return array(
                    'result'   => 'success',
                    'redirect' => $this->get_return_url($order),
                );
            }
        }
    }
}

add_filter('woocommerce_payment_gateways', 'add_to_woo_custom_woo_gateway_wallet');

function add_to_woo_custom_woo_gateway_wallet($gateways)
{
    $gateways[] = _THIS_PAYMENT_GATEWAY;
    return $gateways;
}

