<?php

/*
* Redirect non-subscribed users away from a premium post
* If the redirect page is premium, users are sent to the home page
*/
function rcp_redirect_from_premium_post() {
	global $rcp_options, $user_ID, $post;
	if( isset($rcp_options['hide_premium'] ) && $rcp_options['hide_premium'] ) {
		if( !rcp_is_active( $user_ID ) && is_singular() && ( rcp_is_paid_content( $post->ID ) || rcp_post_has_restricted_taxonomy_terms( $post->ID ) ) ) {
			if( isset( $rcp_options['redirect_from_premium'] ) ) {
				$redirect = get_permalink( $rcp_options['redirect_from_premium'] );
			} else {
				$redirect = home_url();
			}
			wp_redirect( $redirect ); exit;
		}
	}
}
add_action( 'template_redirect', 'rcp_redirect_from_premium_post', 999 );

/*
* Hijack the default WP login URL and redirect users to custom login page
*/
function rcp_hijack_login_url( $login_url ) {
	global $rcp_options;
	if( isset( $rcp_options['hijack_login_url'] ) && isset( $rcp_options['login_redirect'] ) ) {
		$login_url = get_permalink( $rcp_options['login_redirect'] );
	}
	return $login_url;
}
add_filter( 'login_url', 'rcp_hijack_login_url' );


/*
* Redirects users to the custom login page when access wp-login.php
*/
function rcp_redirect_from_wp_login() {
	global $rcp_options;

	if( isset( $rcp_options['hijack_login_url'] ) && isset( $rcp_options['login_redirect'] ) ) {
		wp_redirect( get_permalink( $rcp_options['login_redirect'] ) ); exit;
	}
}
add_action( 'login_form_login', 'rcp_redirect_from_wp_login' );