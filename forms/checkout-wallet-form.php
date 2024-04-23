<?php


add_filter('woocommerce_gateway_description', 'hook_checkout_fields', 10, 2);

/**
 * Form to display to the user upon checkout
 */
function hook_checkout_fields($description, $payment_gateway)
{
    if ($payment_gateway === 'custom_woo_gateway') {
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            
            $current_wallet_balance = get_user_meta($user_id, 'wallet_balance', true);
            ob_start();
?>
            <div class="woocommerce-additional-fields">
                <div class="woocommerce-additional-fields__field-wrapper">
                    <p class="form-row form-row-wide">
                        <!-- <label for="wallet_payment_amount"><?php //esc_html_e('Payment Amount', 'custom_woo_gateway'); ?> 
                        <span class="required">*</span></label>
                        <input type="text" class="input-text" name="wallet_payment_amount" id="wallet_payment_amount" placeholder="<?php // esc_attr_e('Enter payment amount', 'custom_woo_gateway'); ?>" value="" required> -->
                    </p>
                    <div class="form-row">
                        <label>Current Balance: $<?php echo number_format($current_wallet_balance, 2); ?></label>
                    </div>
                </div>
            </div>
<?php
            $payment_amount_field = ob_get_clean();

            $description .= $payment_amount_field;
        }
    }

    return $description;
}
