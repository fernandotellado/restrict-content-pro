<?php
/**
 * Manual Payment Gateway
 *
 * @package     Restrict Content Pro
 * @copyright   Copyright (c) 2012, Pippin Williamson
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.1
*/

class RCP_Payment_Gateway_Manual extends RCP_Payment_Gateway {

	/**
	 * Get things going
	 *
	 * @since 2.1
	 */
	public function init() {

		global $rcp_options;

		$this->supports[]  = 'one-time';
		$this->supports[]  = 'fees';

	}

	/**
	 * Process registration
	 *
	 * @since 2.1
	 */
	public function process_signup() {

		$member = new RCP_Member( $this->user_id );

		$old_level = get_user_meta( $member->ID, '_rcp_old_subscription_id', true );
		if ( ! empty( $old_level ) && $old_level == $this->subscription_id ) {
			$expiration = $member->calculate_expiration();
		} else {
			delete_user_meta( $member->ID, 'rcp_pending_expiration_date' );
			$expiration = $member->calculate_expiration( true );
		}

		$member->renew( false, 'pending', $expiration );

		// setup the payment info in an array for storage
		$payment_data = array(
			'subscription'     => $this->subscription_name,
			'payment_type'     => 'manual',
			'subscription_key' => $this->subscription_key,
			'amount'           => $this->amount + $this->signup_fee,
			'user_id'          => $this->user_id,
			'transaction_id'   => $this->generate_transaction_id()
		);

		$rcp_payments = new RCP_Payments();
		$rcp_payments->insert( $payment_data );

		wp_redirect( $this->return_url ); exit;

	}

}