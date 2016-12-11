<?php

class RCP_EDD {

	private $user;
	private $member;

	public function __construct() {
		$this->user = wp_get_current_user();
		$this->member = new RCP_Member( $this->user->ID );

		add_filter( 'edd_can_purchase_download', array( $this, 'can_purchase' ), 10, 2 );
		add_filter( 'edd_purchase_download_form', array( $this, 'download_form' ), 10, 2 );
		add_filter( 'edd_file_download_has_access', array( $this, 'file_download_has_access' ), 10, 3 );
		add_filter( 'edd_downloads_query', array( $this, 'edd_downloads_query' ), 10, 2 );
		add_filter( 'edd_downloads_excerpt', array( $this, 'edd_downloads_excerpt' ) );
	}

	/**
	 * Restricts the ability to purchase a product if the user doesn't have access to it.
	 *
	 * @access public
	 * @since 2.7
	 */
	public function can_purchase( $can_purchase, $download ) {

		if ( ! $can_purchase || ! $this->member->can_access( $download->ID ) ) {
			$can_purchase = false;
		}

		return $can_purchase;
	}

	/**
	 * Overrides the purchase form if the user doesn't have access to the product.
	 *
	 * @access public
	 * @since 2.7
	 */
	public function download_form( $purchase_form, $args ) {

		if ( ! $this->member->can_access( $args['download_id'] ) ) {
			return '';
		}

		return $purchase_form;
	}

	/**
	 * Prevents downloading files if the member doesn't have access.
	 *
	 * @access public
	 * @since 2.7
	 */
	public function file_download_has_access( $has_access, $payment_id, $args ) {

		if ( ! $this->member->can_access( $args['download'] ) ) {
			$has_access = false;
		}

		return $has_access;
	}

	/**
	 * Removes restricted downloads from the [downloads] shortcode query.
	 *
	 * @access public
	 * @since 2.7
	 */
	public function edd_downloads_query( $query, $atts ) {

		global $rcp_options;

		if ( isset( $rcp_options['hide_premium'] ) && ! rcp_is_active( get_current_user_id() ) ) {
			$premium_ids = rcp_get_paid_posts();
			if ( ! empty( $premium_ids ) ) {
				$query['post__not_in'] = $premium_ids;
			}
		}

		return $query;
	}

	/**
	 * Filters the excerpt in the [downloads] shortcode if the member doesn't have access.
	 *
	 * @access public
	 * @since 2.7
	 */
	public function edd_downloads_excerpt( $excerpt ) {

		global $rcp_options;

		$post_id = get_the_ID();

		if ( $this->member->can_access( $post_id ) || get_post_meta( $post_id, 'rcp_show_excerpt', true ) ) {
			return $excerpt;
		}

		if ( rcp_is_paid_content( $post_id ) ) {
			$excerpt = ! empty( $rcp_options['paid_message'] ) ? $rcp_options['paid_message'] : false;
		} else {
			$excerpt = ! empty( $rcp_options['free_message'] ) ? $rcp_options['free_message'] : false;
		}

		if( empty( $excerpt ) ) {
			$excerpt = __( 'This content is restricted to subscribers', 'rcp' );
		}

		return $excerpt;
	}
}


function rcp_edd_init() {

	if ( ! class_exists( 'Easy_Digital_Downloads' ) ) {
		return;
	}
	new RCP_EDD;
}
add_action( 'init', 'rcp_edd_init' );