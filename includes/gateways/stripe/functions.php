<?php

/**
 * Determine if a member is a Stripe subscriber
 *
 * @since       v2.1
 * @access      public
 * @param       $user_id INT the ID of the user to check
 * @return      bool
*/
function rcp_is_stripe_subscriber( $user_id = 0 ) {

	if( empty( $user_id ) ) {
		$user_id = get_current_user_id();
	}

	$ret = false;

	$member = new RCP_Member( $user_id );

	$profile_id = $member->get_payment_profile_id();

	// Check if the member is a Stripe customer
	if( false !== strpos( $profile_id, 'cus_' ) ) {

		$ret = true;

	}

	return (bool) apply_filters( 'rcp_is_stripe_subscriber', $ret, $user_id );
}

/**
 * Add JS to the update card form
 *
 * @access      private
 * @since       2.1
 */
function rcp_stripe_update_card_form_js() {
	global $rcp_options;

	if( ! rcp_is_gateway_enabled( 'stripe' ) && ! rcp_is_gateway_enabled( 'stripe_checkout' ) ) {
		return;
	}

	if( rcp_is_sandbox() ) {
		$key = trim( $rcp_options['stripe_test_publishable'] );
	} else {
		$key = trim( $rcp_options['stripe_live_publishable'] );
	}

	if( empty( $key ) ) {
		return;
	}

	wp_enqueue_script( 'stripe', 'https://js.stripe.com/v2/', array( 'jquery' ) );
?>
	<script type="text/javascript">

		function rcp_stripe_response_handler(status, response) {
			if (response.error) {

				// re-enable the submit button
				jQuery('#rcp_update_card_form #rcp_submit').attr("disabled", false);

				jQuery('#rcp_ajax_loading').hide();

				// show the errors on the form
				jQuery(".rcp_message.error").html( '<p class="rcp_error"><span>' + response.error.message + '</span></p>');

			} else {

				var form$ = jQuery("#rcp_update_card_form");
				// token contains id, last4, and card type
				var token = response['id'];
				// insert the token into the form so it gets submitted to the server
				form$.append("<input type='hidden' name='stripeToken' value='" + token + "' />");

				// and submit
				form$.get(0).submit();

			}
		}

		jQuery(document).ready(function($) {

			Stripe.setPublishableKey('<?php echo trim( $key ); ?>');

			$("#rcp_update_card_form").on('submit', function(event) {

				event.preventDefault();

				// disable the submit button to prevent repeated clicks
				$('#rcp_update_card_form #rcp_submit').attr("disabled", "disabled");

				// createToken returns immediately - the supplied callback submits the form if there are no errors
				Stripe.createToken({
					number: $('.card-number').val(),
					name: $('.card-name').val(),
					cvc: $('.card-cvc').val(),
					exp_month: $('.card-expiry-month').val(),
					exp_year: $('.card-expiry-year').val(),
					address_zip: $('.card-zip').val()
				}, rcp_stripe_response_handler);

				return false;
			});
		});
	</script>
<?php
}
add_action( 'rcp_before_update_billing_card_form', 'rcp_stripe_update_card_form_js' );

/**
 * Process an update card form request
 *
 * @access      private
 * @since       2.1
 */
function rcp_stripe_update_billing_card( $member_id = 0, $member_obj ) {

	if( empty( $member_id ) ) {
		return;
	}

	if( ! is_a( $member_obj, 'RCP_Member' ) ) {
		return;
	}

	if( ! rcp_is_stripe_subscriber( $member_id ) ) {
		return;
	}

	if( empty( $_POST['stripeToken'] ) ) {
		wp_die( __( 'Missing Stripe token', 'rcp' ), __( 'Error', 'rcp' ), array( 'response' => 400 ) );
	}

	$customer_id = $member_obj->get_payment_profile_id();

	global $rcp_options;

	if ( rcp_is_sandbox() ) {
		$secret_key = trim( $rcp_options['stripe_test_secret'] );
	} else {
		$secret_key = trim( $rcp_options['stripe_live_secret'] );
	}

	if( ! class_exists( 'Stripe\Stripe' ) ) {
		require_once RCP_PLUGIN_DIR . 'includes/libraries/stripe/init.php';
	}

	\Stripe\Stripe::setApiKey( $secret_key );

	try {

		$customer = \Stripe\Customer::retrieve( $customer_id );

		$customer->card = $_POST['stripeToken']; // obtained with stripe.js
		$customer->save();


	} catch ( \Stripe\Error\Card $e ) {

		$body = $e->getJsonBody();
		$err  = $body['error'];

		$error = '<h4>' . __( 'An error occurred', 'rcp' ) . '</h4>';
		if( isset( $err['code'] ) ) {
			$error .= '<p>' . sprintf( __( 'Error code: %s', 'rcp' ), $err['code'] ) . '</p>';
		}
		$error .= "<p>Status: " . $e->getHttpStatus() ."</p>";
		$error .= "<p>Message: " . $err['message'] . "</p>";

		wp_die( $error, __( 'Error', 'rcp' ), array( 'response' => '401' ) );

		exit;

	} catch (\Stripe\Error\InvalidRequest $e) {

		// Invalid parameters were supplied to Stripe's API
		$body = $e->getJsonBody();
		$err  = $body['error'];

		$error = '<h4>' . __( 'An error occurred', 'rcp' ) . '</h4>';
		if( isset( $err['code'] ) ) {
			$error .= '<p>' . sprintf( __( 'Error code: %s', 'rcp' ), $err['code'] ) . '</p>';
		}
		$error .= "<p>Status: " . $e->getHttpStatus() ."</p>";
		$error .= "<p>Message: " . $err['message'] . "</p>";

		wp_die( $error, __( 'Error', 'rcp' ), array( 'response' => '401' ) );

	} catch (\Stripe\Error\Authentication $e) {

		// Authentication with Stripe's API failed
		// (maybe you changed API keys recently)

		$body = $e->getJsonBody();
		$err  = $body['error'];

		$error = '<h4>' . __( 'An error occurred', 'rcp' ) . '</h4>';
		if( isset( $err['code'] ) ) {
			$error .= '<p>' . sprintf( __( 'Error code: %s', 'rcp' ), $err['code'] ) . '</p>';
		}
		$error .= "<p>Status: " . $e->getHttpStatus() ."</p>";
		$error .= "<p>Message: " . $err['message'] . "</p>";

		wp_die( $error, __( 'Error', 'rcp' ), array( 'response' => '401' ) );

	} catch (\Stripe\Error\ApiConnection $e) {

		// Network communication with Stripe failed

		$body = $e->getJsonBody();
		$err  = $body['error'];

		$error = '<h4>' . __( 'An error occurred', 'rcp' ) . '</h4>';
		if( isset( $err['code'] ) ) {
			$error .= '<p>' . sprintf( __( 'Error code: %s', 'rcp' ), $err['code'] ) . '</p>';
		}
		$error .= "<p>Status: " . $e->getHttpStatus() ."</p>";
		$error .= "<p>Message: " . $err['message'] . "</p>";

		wp_die( $error, __( 'Error', 'rcp' ), array( 'response' => '401' ) );

	} catch (\Stripe\Error\Base $e) {

		// Display a very generic error to the user

		$body = $e->getJsonBody();
		$err  = $body['error'];

		$error = '<h4>' . __( 'An error occurred', 'rcp' ) . '</h4>';
		if( isset( $err['code'] ) ) {
			$error .= '<p>' . sprintf( __( 'Error code: %s', 'rcp' ), $err['code'] ) . '</p>';
		}
		$error .= "<p>Status: " . $e->getHttpStatus() ."</p>";
		$error .= "<p>Message: " . $err['message'] . "</p>";

		wp_die( $error, __( 'Error', 'rcp' ), array( 'response' => '401' ) );

	} catch (Exception $e) {

		// Something else happened, completely unrelated to Stripe

		$error = '<p>' . __( 'An unidentified error occurred.', 'rcp' ) . '</p>';
		$error .= print_r( $e, true );

		wp_die( $error, __( 'Error', 'rcp' ), array( 'response' => '401' ) );

	}

	wp_redirect( add_query_arg( 'card', 'updated' ) ); exit;

}
add_action( 'rcp_update_billing_card', 'rcp_stripe_update_billing_card', 10, 2 );

/**
 * Create discount code in Stripe when one is created in RCP
 *
 * @access      private
 * @since       2.1
 */
function rcp_stripe_create_discount() {

	if( ! is_admin() ) {
		return;
	}

	if( function_exists( 'rcp_stripe_add_discount' ) ) {
		return; // Old Stripe gateway is active
	}

	if( ! rcp_is_gateway_enabled( 'stripe' ) && ! rcp_is_gateway_enabled( 'stripe_checkout' ) ) {
		return;
	}

	global $rcp_options;

	if( ! class_exists( 'Stripe\Stripe' ) ) {
		require_once RCP_PLUGIN_DIR . 'includes/libraries/stripe/init.php';
	}

	if ( rcp_is_sandbox() ) {
		$secret_key = isset( $rcp_options['stripe_test_secret'] ) ? trim( $rcp_options['stripe_test_secret'] ) : '';
	} else {
		$secret_key = isset( $rcp_options['stripe_live_secret'] ) ? trim( $rcp_options['stripe_live_secret'] ) : '';
	}

	if( empty( $secret_key ) ) {
		return;
	}

	\Stripe\Stripe::setApiKey( $secret_key );

	try {

		if ( $_POST['unit'] == '%' ) {
			\Stripe\Coupon::create( array(
					"percent_off" => sanitize_text_field( $_POST['amount'] ),
					"duration"    => "forever",
					"id"          => sanitize_text_field( $_POST['code'] ),
					"currency"   => strtolower( rcp_get_currency() )
				)
			);
		} else {
			\Stripe\Coupon::create( array(
					"amount_off" => sanitize_text_field( $_POST['amount'] ) * rcp_stripe_get_currency_multiplier(),
					"duration"   => "forever",
					"id"         => sanitize_text_field( $_POST['code'] ),
					"currency"   => strtolower( rcp_get_currency() )
				)
			);
		}

	} catch ( \Stripe\Error\Card $e ) {

			$body = $e->getJsonBody();
			$err  = $body['error'];

			$error = '<h4>' . __( 'An error occurred', 'rcp' ) . '</h4>';
			if( isset( $err['code'] ) ) {
				$error .= '<p>' . sprintf( __( 'Error code: %s', 'rcp' ), $err['code'] ) . '</p>';
			}
			$error .= "<p>Status: " . $e->getHttpStatus() ."</p>";
			$error .= "<p>Message: " . $err['message'] . "</p>";

			wp_die( $error, __( 'Error', 'rcp' ), array( 'response' => 401 ) );

			exit;

	} catch (\Stripe\Error\InvalidRequest $e) {

		// Invalid parameters were supplied to Stripe's API
		$body = $e->getJsonBody();
		$err  = $body['error'];

		$error = '<h4>' . __( 'An error occurred', 'rcp' ) . '</h4>';
		if( isset( $err['code'] ) ) {
			$error .= '<p>' . sprintf( __( 'Error code: %s', 'rcp' ), $err['code'] ) . '</p>';
		}
		$error .= "<p>Status: " . $e->getHttpStatus() ."</p>";
		$error .= "<p>Message: " . $err['message'] . "</p>";

		wp_die( $error, __( 'Error', 'rcp' ), array( 'response' => 401 ) );

	} catch (\Stripe\Error\Authentication $e) {

		// Authentication with Stripe's API failed
		// (maybe you changed API keys recently)

		$body = $e->getJsonBody();
		$err  = $body['error'];

		$error = '<h4>' . __( 'An error occurred', 'rcp' ) . '</h4>';
		if( isset( $err['code'] ) ) {
			$error .= '<p>' . sprintf( __( 'Error code: %s', 'rcp' ), $err['code'] ) . '</p>';
		}
		$error .= "<p>Status: " . $e->getHttpStatus() ."</p>";
		$error .= "<p>Message: " . $err['message'] . "</p>";

		wp_die( $error, __( 'Error', 'rcp' ), array( 'response' => 401 ) );

	} catch (\Stripe\Error\ApiConnection $e) {

		// Network communication with Stripe failed

		$body = $e->getJsonBody();
		$err  = $body['error'];

		$error = '<h4>' . __( 'An error occurred', 'rcp' ) . '</h4>';
		if( isset( $err['code'] ) ) {
			$error .= '<p>' . sprintf( __( 'Error code: %s', 'rcp' ), $err['code'] ) . '</p>';
		}
		$error .= "<p>Status: " . $e->getHttpStatus() ."</p>";
		$error .= "<p>Message: " . $err['message'] . "</p>";

		wp_die( $error, __( 'Error', 'rcp' ), array( 'response' => 401 ) );

	} catch (\Stripe\Error\Base $e) {

		// Display a very generic error to the user

		$body = $e->getJsonBody();
		$err  = $body['error'];

		$error = '<h4>' . __( 'An error occurred', 'rcp' ) . '</h4>';
		if( isset( $err['code'] ) ) {
			$error .= '<p>' . sprintf( __( 'Error code: %s', 'rcp' ), $err['code'] ) . '</p>';
		}
		$error .= "<p>Status: " . $e->getHttpStatus() ."</p>";
		$error .= "<p>Message: " . $err['message'] . "</p>";

		wp_die( $error, __( 'Error', 'rcp' ), array( 'response' => 401 ) );

	} catch (Exception $e) {

		// Something else happened, completely unrelated to Stripe

		$error = '<p>' . __( 'An unidentified error occurred.', 'rcp' ) . '</p>';
		$error .= print_r( $e, true );

		wp_die( $error, __( 'Error', 'rcp' ), array( 'response' => 401 ) );

	}

}
add_action( 'rcp_pre_add_discount', 'rcp_stripe_create_discount' );

/**
 * Update a discount in Stripe when a local code is updated
 *
 * @access      private
 * @param       $discount_id int the id of the discount being updated
 * @param       $args array the array of discount args
 *              array(
 *					'name',
 *					'description',
 *					'amount',
 *					'unit',
 *					'code',
 *					'status',
 *					'expiration',
 *					'max_uses',
 *					'subscription_id'
 *				)
 * @since       2.1
 */
function rcp_stripe_update_discount( $discount_id, $args ) {

	if( ! is_admin() ) {
		return;
	}

	// bail if the discount id or args are empty
	if ( empty( $discount_id ) || empty( $args )  )
		return;

	if( function_exists( 'rcp_stripe_add_discount' ) ) {
		return; // Old Stripe gateway is active
	}

	if( ! rcp_is_gateway_enabled( 'stripe' ) && ! rcp_is_gateway_enabled( 'stripe_checkout' ) ) {
		return;
	}

	global $rcp_options;

	if( ! class_exists( 'Stripe\Stripe' ) ) {
		require_once RCP_PLUGIN_DIR . 'includes/libraries/stripe/init.php';
	}

	if ( ! empty( $_REQUEST['deactivate_discount'] ) || ! empty( $_REQUEST['activate_discount'] ) ) {
		return;
	}

	if ( rcp_is_sandbox() ) {
		$secret_key = isset( $rcp_options['stripe_test_secret'] ) ? trim( $rcp_options['stripe_test_secret'] ) : '';
	} else {
		$secret_key = isset( $rcp_options['stripe_live_secret'] ) ? trim( $rcp_options['stripe_live_secret'] ) : '';
	}

	if( empty( $secret_key ) ) {
		return;
	}

	\Stripe\Stripe::setApiKey( $secret_key );

	$discount_details = rcp_get_discount_details( $discount_id );
	$discount_name    = $discount_details->code;

	if ( ! rcp_stripe_does_coupon_exists( $discount_name ) ) {

		try {

			if ( $args['unit'] == '%' ) {
				\Stripe\Coupon::create( array(
						"percent_off" => sanitize_text_field( $args['amount'] ),
						"duration"    => "forever",
						"id"          => sanitize_text_field( $discount_name ),
						"currency"    => strtolower( rcp_get_currency() )
					)
				);
			} else {
				\Stripe\Coupon::create( array(
						"amount_off" => sanitize_text_field( $args['amount'] ) * rcp_stripe_get_currency_multiplier(),
						"duration"   => "forever",
						"id"         => sanitize_text_field( $discount_name ),
						"currency"   => strtolower( rcp_get_currency() )
					)
				);
			}

		} catch ( Exception $e ) {
			wp_die( '<pre>' . $e . '</pre>', __( 'Error', 'rcp' ) );
		}

	} else {

		// first delete the discount in Stripe
		try {
			$cpn = \Stripe\Coupon::retrieve( $discount_name );
			$cpn->delete();
		} catch ( Exception $e ) {
			wp_die( '<pre>' . $e . '</pre>', __( 'Error', 'rcp' ) );
		}

		// now add a new one. This is a fake "update"
		try {

			if ( $args['unit'] == '%' ) {
				\Stripe\Coupon::create( array(
						"percent_off" => sanitize_text_field( $args['amount'] ),
						"duration"    => "forever",
						"id"          => sanitize_text_field( $discount_name ),
						"currency"    => strtolower( rcp_get_currency() )
					)
				);
			} else {
				\Stripe\Coupon::create( array(
						"amount_off" => sanitize_text_field( $args['amount'] ) * rcp_stripe_get_currency_multiplier(),
						"duration"   => "forever",
						"id"         => sanitize_text_field( $discount_name ),
						"currency"   => strtolower( rcp_get_currency() )
					)
				);
			}

		} catch (\Stripe\Error\InvalidRequest $e) {

			// Invalid parameters were supplied to Stripe's API
			$body = $e->getJsonBody();
			$err  = $body['error'];

			$error = '<h4>' . __( 'An error occurred', 'rcp' ) . '</h4>';
			if( isset( $err['code'] ) ) {
				$error .= '<p>' . sprintf( __( 'Error code: %s', 'rcp' ), $err['code'] ) . '</p>';
			}
			$error .= "<p>Status: " . $e->getHttpStatus() ."</p>";
			$error .= "<p>Message: " . $err['message'] . "</p>";

			wp_die( $error, __( 'Error', 'rcp' ), array( 'response' => 401 ) );

		} catch (\Stripe\Error\Authentication $e) {

			// Authentication with Stripe's API failed
			// (maybe you changed API keys recently)

			$body = $e->getJsonBody();
			$err  = $body['error'];

			$error = '<h4>' . __( 'An error occurred', 'rcp' ) . '</h4>';
			if( isset( $err['code'] ) ) {
				$error .= '<p>' . sprintf( __( 'Error code: %s', 'rcp' ), $err['code'] ) . '</p>';
			}
			$error .= "<p>Status: " . $e->getHttpStatus() ."</p>";
			$error .= "<p>Message: " . $err['message'] . "</p>";

			wp_die( $error, __( 'Error', 'rcp' ), array( 'response' => 401 ) );

		} catch (\Stripe\Error\ApiConnection $e) {

			// Network communication with Stripe failed

			$body = $e->getJsonBody();
			$err  = $body['error'];

			$error = '<h4>' . __( 'An error occurred', 'rcp' ) . '</h4>';
			if( isset( $err['code'] ) ) {
				$error .= '<p>' . sprintf( __( 'Error code: %s', 'rcp' ), $err['code'] ) . '</p>';
			}
			$error .= "<p>Status: " . $e->getHttpStatus() ."</p>";
			$error .= "<p>Message: " . $err['message'] . "</p>";

			wp_die( $error, __( 'Error', 'rcp' ), array( 'response' => 401 ) );

		} catch (\Stripe\Error\Base $e) {

			// Display a very generic error to the user

			$body = $e->getJsonBody();
			$err  = $body['error'];

			$error = '<h4>' . __( 'An error occurred', 'rcp' ) . '</h4>';
			if( isset( $err['code'] ) ) {
				$error .= '<p>' . sprintf( __( 'Error code: %s', 'rcp' ), $err['code'] ) . '</p>';
			}
			$error .= "<p>Status: " . $e->getHttpStatus() ."</p>";
			$error .= "<p>Message: " . $err['message'] . "</p>";

			wp_die( $error, __( 'Error', 'rcp' ), array( 'response' => 401 ) );

		} catch (Exception $e) {

			// Something else happened, completely unrelated to Stripe

			$error = '<p>' . __( 'An unidentified error occurred.', 'rcp' ) . '</p>';
			$error .= print_r( $e, true );

			wp_die( $error, __( 'Error', 'rcp' ), array( 'response' => 401 ) );

		}
	}
}
add_action( 'rcp_edit_discount', 'rcp_stripe_update_discount', 10, 2 );

/**
 * Check if a coupone exists in Stripe
 *
 * @access      private
 * @since       2.1
 */
function rcp_stripe_does_coupon_exists( $code ) {
	global $rcp_options;

	if( ! class_exists( 'Stripe\Stripe' ) ) {
		require_once RCP_PLUGIN_DIR . 'includes/libraries/stripe/init.php';
	}

	if ( rcp_is_sandbox() ) {
		$secret_key = isset( $rcp_options['stripe_test_secret'] ) ? trim( $rcp_options['stripe_test_secret'] ) : '';
	} else {
		$secret_key = isset( $rcp_options['stripe_live_secret'] ) ? trim( $rcp_options['stripe_live_secret'] ) : '';
	}

	if( empty( $secret_key ) ) {
		return;
	}

	\Stripe\Stripe::setApiKey( $secret_key );
	try {
		\Stripe\Coupon::retrieve( $code );
		$exists = true;
	} catch ( Exception $e ) {
		$exists = false;
	}

	return $exists;
}

/**
 * Return the multiplier for the currency. Most currencies are multiplied by 100. Zere decimal
 * currencies should not be multiplied so use 1.
 *
 * @param string $currency
 *
 * @since 2.5
 * @return int
 */
function rcp_stripe_get_currency_multiplier( $currency = '' ) {
	$multiplier = ( rcp_is_zero_decimal_currency( $currency ) ) ? 1 : 100;

	return apply_filters( 'rcp_stripe_get_currency_multiplier', $multiplier, $currency );
}

/**
 * Query Stripe API to get customer's card details
 *
 * @param $card       array
 * @param $member_id int
 * @param $member    object
 *
 * @since 2.5
 * @return array
 */
function rcp_stripe_get_card_details( $cards, $member_id, $member ) {

	global $rcp_options;

	if( ! rcp_is_stripe_subscriber( $member_id ) ) {
		return $cards;
	}

	if( ! class_exists( 'Stripe\Stripe' ) ) {
		require_once RCP_PLUGIN_DIR . 'includes/libraries/stripe/init.php';
	}

	if ( rcp_is_sandbox() ) {
		$secret_key = isset( $rcp_options['stripe_test_secret'] ) ? trim( $rcp_options['stripe_test_secret'] ) : '';
	} else {
		$secret_key = isset( $rcp_options['stripe_live_secret'] ) ? trim( $rcp_options['stripe_live_secret'] ) : '';
	}

	if( empty( $secret_key ) ) {
		return $cards;
	}

	\Stripe\Stripe::setApiKey( $secret_key );

	try {

		$customer = \Stripe\Customer::retrieve( $member->get_payment_profile_id() );
		$default  = $customer->sources->retrieve( $customer->default_source );

		$cards['stripe']['name']      = $default->name;
		$cards['stripe']['type']      = $default->brand;
		$cards['stripe']['zip']       = $default->address_zip;
		$cards['stripe']['exp_month'] = $default->exp_month;
		$cards['stripe']['exp_year']  = $default->exp_year;
		$cards['stripe']['last4']     = $default->last4;

	} catch ( Exception $e ) {

	}

	return $cards;

}
add_filter( 'rcp_get_card_details', 'rcp_stripe_get_card_details', 10, 3 );