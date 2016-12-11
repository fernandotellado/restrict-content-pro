<?php

class RCP_Integrations {

	public function __construct() {

		add_action( 'plugins_loaded', array( $this, 'load' ) );

	}

	public function get_integrations() {

		return apply_filters( 'rcp_integrations', array(
			'woocommerce'            => 'WooCommerce',
			'google-authenticator'   => 'Google Authenticator',
			'wp-approve-user'        => 'WP Approve User',
			'easy-digital-downloads' => 'Easy Digital Downloads'
		) );

	}

	public function load() {

		do_action( 'rcp_integrations_load' );

		foreach( $this->get_integrations() as $filename => $integration ) {

			if( file_exists( RCP_PLUGIN_DIR . 'includes/integrations/class-rcp-' . $filename . '.php' ) ) {
				require_once RCP_PLUGIN_DIR . 'includes/integrations/class-rcp-' . $filename . '.php';
			}

		}

		do_action( 'rcp_integrations_loaded' );

	}

}
new RCP_Integrations;