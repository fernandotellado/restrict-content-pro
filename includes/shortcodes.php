<?php

/*******************************************
* Restrict Content ShortCodes
*******************************************/

add_filter( 'rcp_restrict_shortcode_return', 'wpautop' );
add_filter( 'rcp_restrict_shortcode_return', 'do_shortcode' );
add_filter( 'widget_text', 'do_shortcode' );


/**
 * Restricting content to registered users and or user roles
 *
 * @since
 * @access public
 *
 * @param $atts
 * @param $content
 * @return mixed|void
 */
function rcp_restrict_shortcode( $atts, $content = null ) {

	$atts = shortcode_atts( array(
		'userlevel'    => 'none',
		'message'      => '',
		'paid'         => false,
		'level'        => 0,
		'subscription' => ''
	), $atts, 'restrict' );

	global $rcp_options, $user_ID;

	if ( strlen( trim( $atts['message'] ) ) > 0 ) {
		$teaser = $atts['message'];
	} elseif ( $atts['paid'] ) {
		$teaser = $rcp_options['paid_message'];
	} else {
		$teaser = $rcp_options['free_message'];
	}

	$subscription = array_map( 'trim', explode( ',', $atts['subscription'] ) );

	$has_access = false;

	if( $atts['paid'] ) {

		if ( rcp_is_active( $user_ID ) && rcp_user_has_access( $user_ID, $atts['level'] ) ) {
			$has_access = true;
		}

		$classes = 'rcp_restricted rcp_paid_only';

	} else {

		if ( rcp_user_has_access( $user_ID, $atts['level'] ) ) {
			$has_access = true;
		}

		$classes = 'rcp_restricted';
	}

	if ( ! empty( $subscription ) && ! empty( $subscription[0] ) ) {
		if ( ! in_array( rcp_get_subscription_id( $user_ID ), $subscription ) || ( in_array( rcp_get_subscription_id( $user_ID ), $subscription ) && rcp_is_expired( $user_ID ) ) ) {
			$has_access = false;
		}
	}

	if ( $atts['userlevel'] === 'admin' && ! current_user_can( 'switch_themes' ) ) {
		$has_access = false;
	}

	if ( $atts['userlevel'] === 'editor' && ! current_user_can( 'moderate_comments' ) ) {
		$has_access = false;
	}

	if ( $atts['userlevel'] === 'author' && ! current_user_can( 'upload_files' ) ) {
		$has_access = false;
	}

	if ( $atts['userlevel'] === 'contributor' && ! current_user_can( 'edit_posts' ) ) {
		$has_access = false;
	}

	if ( $atts['userlevel'] === 'subscriber' && ! current_user_can( 'read' ) ) {
		$has_access = false;
	}

	if ( $atts['userlevel'] === 'none' && ! is_user_logged_in() ) {
		$has_access = false;
	}

	if ( current_user_can( 'manage_options' ) ) {
		$has_access = true;
	}

	$has_access = (bool) apply_filters( 'rcp_restrict_shortcode_has_access', $has_access, $user_ID, $atts );

	if ( $has_access ) {
		return apply_filters( 'rcp_restrict_shortcode_return', $content );
	} else {
		return '<div class="' . $classes . '">' . rcp_format_teaser( $teaser ) . '</div>';
	}
}
add_shortcode( 'restrict', 'rcp_restrict_shortcode' );


/**
 * Shows content only to active, paid users
 *
 * @since
 * @access public
 *
 * @param $atts
 * @param $content
 * @return mixed|void
 */
function rcp_is_paid_user_shortcode( $atts, $content = null ) {

	global $user_ID;

	if( rcp_is_active( $user_ID ) ) {
		return do_shortcode( $content );
	}
}
add_shortcode( 'is_paid', 'rcp_is_paid_user_shortcode' );


/**
 * Shows content only to logged-in free users, and can hide from paid
 *
 * @since
 * @access public
 *
 * @param $atts
 * @param $content
 * @return mixed|void
 */
function rcp_is_free_user_shortcode( $atts, $content = null ) {

	$atts = shortcode_atts( array(
		'hide_from_paid' => true
	), $atts, 'is_free' );

	global $user_ID;

	if( $atts['hide_from_paid'] ) {
		if( !rcp_is_active( $user_ID ) && is_user_logged_in() ) {
			return do_shortcode( $content );
		}
	} elseif( is_user_logged_in() ) {
		return do_shortcode( $content );
	}
}
add_shortcode( 'is_free', 'rcp_is_free_user_shortcode' );


/**
 * Shows content only to not logged-in users
 *
 * @since
 * @access public
 *
 * @param $atts
 * @param $content
 * @return mixed|void
 */
function rcp_not_logged_in( $atts, $content = null ) {
	if( !is_user_logged_in() ) {
		return do_shortcode( $content );
	}
}
add_shortcode( 'not_logged_in', 'rcp_not_logged_in' );


/**
 * Allows content to be shown to only users that don't have an active subscription
 *
 * @since
 * @access public
 *
 * @param $atts
 * @param $content
 * @return mixed|void
 */
function rcp_is_not_paid( $atts, $content = null ) {
	global $user_ID;
	if( rcp_is_active( $user_ID ) )
		return;
	else
		return do_shortcode( $content );

}
add_shortcode( 'is_not_paid', 'rcp_is_not_paid' );


/**
 * Displays the currently logged-in user display-name
 *
 * @since
 * @access public
 *
 * @param $atts
 * @param $content
 * @return mixed|void
 */
function rcp_user_name( $atts, $content = null ) {
	global $user_ID;
	if(is_user_logged_in()) {
		return get_userdata( $user_ID )->display_name;
	}

}
add_shortcode( 'user_name', 'rcp_user_name' );


/**
 * Displays user registration form
 *
 * @since
 * @access public
 *
 * @param $atts
 * @param $content
 * @return mixed|void
 */
function rcp_registration_form( $atts, $content = null ) {

	$atts = shortcode_atts( array(
		'id' => null,
		'registered_message' => __( 'You are already registered and have an active subscription.', 'rcp' )
	), $atts, 'register_form' );

	global $user_ID;

	// only show the registration form to non-logged-in members
	if( ! rcp_is_active( $user_ID ) || rcp_is_trialing( $user_ID ) || rcp_subscription_upgrade_possible( $user_ID ) ) {

		global $rcp_options, $rcp_load_css, $rcp_load_scripts;

		// set this to true so the CSS and JS scripts are loaded
		$rcp_load_css = true;
		$rcp_load_scripts = true;

		$output = rcp_registration_form_fields( $atts['id'] );

	} else {
		$output = $atts['registered_message'];
	}
	return $output;
}
add_shortcode( 'register_form', 'rcp_registration_form' );


/**
 * Displays stripe checkout form
 *
 * @since 2.5
 * @access public
 *
 * @param $atts
 * @return mixed|void
 */
function rcp_register_form_stripe_checkout( $atts ) {
	global $rcp_options;

	if ( empty( $atts['id'] ) ) {
		return '';
	}

	// button is an alias for data-label
	if ( isset( $atts['button'] ) ) {
		$atts['data-label'] = $atts['button'];
	}

	$key = ( rcp_is_sandbox() ) ? $rcp_options['stripe_test_publishable'] : $rcp_options['stripe_live_publishable'];

	$member       = new RCP_Member( wp_get_current_user()->ID );
	$subscription = rcp_get_subscription_details( $atts['id'] );
	$amount       = $subscription->price + $subscription->fee;

	if( $member->ID > 0 ) {
		$amount -= $member->get_prorate_credit_amount();
	}

	if( $amount < 0 ) {
		$amount = 0;
	}

	$data = wp_parse_args( $atts, array(
		'id'                     => 0,
		'data-key'               => $key,
		'data-name'              => get_option( 'blogname' ),
		'data-description'       => $subscription->description,
		'data-label'             => sprintf( __( 'Join %s', 'rcp' ), $subscription->name ),
		'data-panel-label'       => __( 'Register - {{amount}}', 'rcp' ),
		'data-amount'            => $amount * rcp_stripe_get_currency_multiplier(),
		'data-locale'            => 'auto',
		'data-allow-remember-me' => true,
		'data-currency'          => rcp_get_currency(),
		'data-alipay'            => isset( $rcp_options['stripe_alipay'] ) && '1' === $rcp_options['stripe_alipay'] && 'USD' === rcp_get_currency() ? 'true' : 'false'
	) );

	if ( empty( $data['data-email'] ) && ! empty( $member->user_email ) ) {
		$data['data-email'] = $member->user_email;
	}

	if ( empty( $data['data-image'] ) && $image = get_site_icon_url() ) {
		$data['data-image'] = $image;
	}

	$data = apply_filters( 'rcp_stripe_checkout_data', $data );

	if ( 'USD' !== rcp_get_currency() ) {
		unset( $data['data-alipay'] );
	}

	ob_start();

	if( $member->ID > 0 && $member->get_subscription_id() == $subscription->id && $member->is_active() ) : ?>

		<div class="rcp-stripe-checkout-notice"><?php _e( 'You are already subscribed.', 'rcp' ); ?></div>

	<?php else : ?>
		<form action="" method="post">
			<?php do_action( 'register_form_stripe_fields', $data ); ?>
			<script src="https://checkout.stripe.com/checkout.js" class="stripe-button" <?php foreach( $data as $label => $value ) { printf( ' %s="%s" ', esc_attr( $label ), esc_attr( $value ) ); } ?> ></script>
			<input type="hidden" name="rcp_level" value="<?php echo $subscription->id ?>" />
			<input type="hidden" name="rcp_register_nonce" value="<?php echo wp_create_nonce('rcp-register-nonce' ); ?>"/>
			<input type="hidden" name="rcp_gateway" value="stripe_checkout"/>
			<input type="hidden" name="rcp_stripe_checkout" value="1"/>
		</form>
	<?php endif;

	return apply_filters( 'register_form_stripe', ob_get_clean(), $atts );
}
add_shortcode( 'register_form_stripe', 'rcp_register_form_stripe_checkout' );


/**
 * Displays user login form
 *
 * @since
 * @access public
 *
 * @param $atts
 * @param $content
 *
 * @return string
 */
function rcp_login_form( $atts, $content = null ) {

	global $post;

	$current_page = rcp_get_current_url();

	$atts = shortcode_atts( array(
		'redirect' 	=> $current_page,
		'class' 	=> 'rcp_form'
	), $atts, 'login_form' );

	$output = '';

	global $rcp_load_css;

	// set this to true so the CSS is loaded
	$rcp_load_css = true;

	return rcp_login_form_fields( array( 'redirect' => $atts['redirect'], 'class' => $atts['class'] ) );

}
add_shortcode( 'login_form', 'rcp_login_form' );


/**
 * Displays a password reset form
 *
 * @since
 * @access public
 *
 * @return string
 */
function rcp_reset_password_form() {
	if( is_user_logged_in() ) {

		global $rcp_options, $rcp_load_css, $rcp_load_scripts;
		// set this to true so the CSS is loaded
		$rcp_load_css = true;
		if( isset( $rcp_options['front_end_validate'] ) ) {
			$rcp_load_scripts = true;
		}

		// get the password reset form fields
		$output = rcp_change_password_form();

		return $output;
	}
}
add_shortcode( 'password_form', 'rcp_reset_password_form' );


/**
 * Displays a list of premium posts
 *
 * @since
 * @access public
 *
 * @return string
 */
function rcp_list_paid_posts() {
	$paid_posts = rcp_get_paid_posts();
	$list = '';
	if( $paid_posts ) {
		$list .= '<ul class="rcp_paid_posts">';
		foreach( $paid_posts as $post_id ) {
			$list .= '<li><a href="' . esc_url( get_permalink( $post_id ) ) . '">' . get_the_title( $post_id ) . '</a></li>';
		}
		$list .= '</ul>';
	}
	return $list;
}
add_shortcode( 'paid_posts', 'rcp_list_paid_posts' );


/**
 * Displays the current user's subscription details
 *
 * @since
 * @access public
 *
 * @param $atts
 * @param $content
 */
function rcp_user_subscription_details( $atts, $content = null ) {

	$atts = shortcode_atts( array(
		'option' => ''
	), $atts, 'subscription_details' );

	global $user_ID, $rcp_options;

	ob_start();

	if( is_user_logged_in() ) {

		rcp_get_template_part( 'subscription' );

	} else {

		echo rcp_login_form_fields();

	}

	return ob_get_clean();
}
add_shortcode( 'subscription_details', 'rcp_user_subscription_details' );


/**
 * Profile Editor Shortcode
 *
 * Outputs the EDD Profile Editor to allow users to amend their details from the front-end
 *
 * @since 1.5
 * @access public
 *
 * @param $atts
 * @param $content
 */
function rcp_profile_editor_shortcode( $atts, $content = null ) {

	global $rcp_load_css;

	$rcp_load_css = true;

	ob_start();

	rcp_get_template_part( 'profile', 'editor' );

	return ob_get_clean();
}
add_shortcode( 'rcp_profile_editor', 'rcp_profile_editor_shortcode' );


/**
 * Update card form short code
 *
 * Displays a form to update the billing credit / debit card.
 *
 * @since 2.1
 * @access public
 *
 * @param $atts
 * @param $content
 */
function rcp_update_billing_card_shortcode( $atts, $content = null ) {
	global $rcp_load_css, $rcp_load_scripts;

	$rcp_load_css = true;
	$rcp_load_scripts = true;

	ob_start();

	if( rcp_member_can_update_billing_card() ) {

		do_action( 'rcp_before_update_billing_card_form' );

		if( isset( $_GET['card'] ) ) {

			switch( $_GET['card'] ) {

				case 'updated' :

					echo '<p class="rcp_success"><span>' . __( 'Billing card updated successfully', 'rcp' ) . '</span></p>';

					break;

				case 'not-updated' :

					if( isset( $_GET['msg'] ) ) {
						$message = urldecode( $_GET['msg'] );
					} else {
						$message = __( 'Billing card could not be updated, please try again.', 'rcp' );
					}

					echo '<p class="rcp_error"><span>' . $message . '</span></p>';

					break;

			}

		}

		rcp_get_template_part( 'card-update', 'form' );
		do_action( 'rcp_after_update_billing_card_form' );

	}

	return ob_get_clean();
}
add_shortcode( 'card_details', 'rcp_update_billing_card_shortcode' ); // Old version
add_shortcode( 'rcp_update_card', 'rcp_update_billing_card_shortcode' );

/**
 * Show User's Subscription ID Shortcode
 *
 * @since 2.5
 * @access public
 *
 * @return string
 */
function rcp_user_subscription_id_shortcode() {
	if ( ! is_user_logged_in() ) {
		return '';
	}

	return rcp_get_subscription_id();
}
add_shortcode( 'subscription_id', 'rcp_user_subscription_id_shortcode' );


/**
 * Show User's Subscription ID Shortcode
 *
 * @since 2.5
 * @access public
 *
 * @return string
 */
function rcp_user_subscription_name_shortcode() {
	if ( ! is_user_logged_in() ) {
		return '';
	}

	if ( ! $id = rcp_get_subscription_id() ) {
		return '';
	}

	return rcp_get_subscription_name( $id );
}
add_shortcode( 'subscription_name', 'rcp_user_subscription_name_shortcode' );


/**
 * Show User's Expiration Shortcode
 *
 * @since 2.5
 * @access public
 *
 * @return string
 */
function rcp_user_expiration_shortcode() {
	if ( ! is_user_logged_in() ) {
		return '';
	}

	return rcp_get_expiration_date();
}
add_shortcode( 'user_expiration', 'rcp_user_expiration_shortcode' );
