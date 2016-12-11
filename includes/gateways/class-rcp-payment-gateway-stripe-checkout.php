<?php
/**
 * Payment Gateway For Stripe Checkout
 *
 * @package     Restrict Content Pro
 * @subpackage  Classes/Roles
 * @copyright   Copyright (c) 2012, Pippin Williamson
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.5
*/

class RCP_Payment_Gateway_Stripe_Checkout extends RCP_Payment_Gateway_Stripe {

	/**
	 * Process registration
	 *
	 * @since 2.5
	 */
	public function process_signup() {

		if( ! empty( $_POST['rcp_stripe_checkout'] ) ) {

			$this->auto_renew = ( '2' === rcp_get_auto_renew_behavior() || '0' === $this->length ) ? false : true;

		}

		parent::process_signup();

	}

	/**
	 * Print fields for this gateway
	 *
	 * @return string
	 */
	public function fields() {
		global $rcp_options;

		if( is_user_logged_in() ) {
			$email = wp_get_current_user()->user_email;
		} else {
			$email = false;
		}

		$data = apply_filters( 'rcp_stripe_checkout_form_data', array(
			'key'               => $this->publishable_key,
			'locale'            => 'auto',
			'allowRememberMe'   => true,
			'email'             => $email,
			'currency'          => rcp_get_currency(),
			'alipay'            => isset( $rcp_options['stripe_alipay'] ) && '1' === $rcp_options['stripe_alipay'] && 'USD' === rcp_get_currency() ? 'true' : 'false'
		) );

		$subscriptions = array();
		foreach ( rcp_get_subscription_levels( 'active' ) as $subscription ) {
			$subscriptions[ $subscription->id ] = array(
				'description' => $subscription->description,
				'name'        => $subscription->name,
				'panelLabel'  => __( 'Register', 'rcp' ),
			);
		}

		$subscriptions = apply_filters( 'rcp_stripe_checkout_subscription_data', $subscriptions );

		ob_start(); ?>

		<script>
			var rcp_script_options;
			var rcpSubscriptions = <?php echo json_encode( $subscriptions ); ?>;
			var checkoutArgs     = <?php echo json_encode( $data ); ?>;

			// define the token function
			checkoutArgs.token = function(token){ jQuery('body').trigger('rcp_stripe_checkout_submit', token); };

			if( ! checkoutArgs.email ) {
				checkoutArgs.email = jQuery('#rcp_registration_form #rcp_user_email' ).val();
			}

			jQuery('#rcp_registration_form #rcp_submit').val( rcp_script_options.pay_now );

			jQuery('body').on('rcp_level_change', function(event, target) {
				jQuery('#rcp_registration_form #rcp_submit').val(
					jQuery(target).attr('rel') > 0 ? rcp_script_options.pay_now : rcp_script_options.register
				);
			});

			jQuery('body').on('rcp_stripe_checkout_submit', function(e, token){
				jQuery('#rcp_registration_form').append('<input type="hidden" name="stripeToken" value="' + token.id + '" />').submit();
			});

			jQuery('#rcp_registration_form #rcp_user_email' ).focusout(function() {
				checkoutArgs.email = jQuery('#rcp_registration_form #rcp_user_email' ).val();
			});

			var rcpStripeCheckout = StripeCheckout.configure(checkoutArgs);

			jQuery('#rcp_registration_form #rcp_submit').on('click', function(e) {
				var $form = jQuery(this).closest('form');
				var $level = $form.find('input[name=rcp_level]:checked');

				var $price = $level.parent().find('.rcp_price').attr('rel') * <?php echo rcp_stripe_get_currency_multiplier(); ?>;
				if ( ! $level.length ) {
					$level = $form.find('input[name=rcp_level]');
					$price = $form.find('.rcp_level').attr('rel') * <?php echo rcp_stripe_get_currency_multiplier(); ?>;
				}

				if( jQuery('.rcp_gateway_fields').hasClass('rcp_discounted_100') ) {
					return true;
				}

				// Open Checkout with further options
				if ( $price > 0 ) {
					rcpStripeCheckout.open(rcpSubscriptions[$level.val()]);
					e.preventDefault();

					return false;
				}
			});

			// Close Checkout on page navigation
			jQuery(window).on('popstate', function() {
				rcpStripeCheckout.close();
			});
		</script>

		<?php
		return ob_get_clean();
	}

	/**
	 * Load Stripe JS
	 *
	 * @since 2.5
	 */
	public function scripts() {
		parent::scripts();
		wp_enqueue_script( 'stripe-checkout', 'https://checkout.stripe.com/checkout.js', array( 'jquery' ) );

	}

	/**
	 * Validate fields
	 *
	 * @since 2.5
	 */
	public function validate_fields() {}

}
