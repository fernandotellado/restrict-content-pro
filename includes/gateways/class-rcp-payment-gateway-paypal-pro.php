<?php
/**
 * PayPal Express Gateway class
 *
 * @package     Restrict Content Pro
 * @copyright   Copyright (c) 2012, Pippin Williamson
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.1
*/

class RCP_Payment_Gateway_PayPal_Pro extends RCP_Payment_Gateway {

	private $api_endpoint;
	protected $username;
	protected $password;
	protected $signature;

	/**
	 * Get things going
	 *
	 * @since 2.1
	 */
	public function init() {

		global $rcp_options;

		$this->supports[]  = 'one-time';
		$this->supports[]  = 'recurring';
		$this->supports[]  = 'fees';

		if( $this->test_mode ) {

			$this->api_endpoint = 'https://api-3t.sandbox.paypal.com/nvp';

		} else {

			$this->api_endpoint = 'https://api-3t.paypal.com/nvp';

		}

		if( rcp_has_paypal_api_access() ) {

			$creds = rcp_get_paypal_api_credentials();

			$this->username  = $creds['username'];
			$this->password  = $creds['password'];
			$this->signature = $creds['signature'];

		}

	}

	/**
	 * Process registration
	 *
	 * @since 2.1
	 */
	public function process_signup() {

		global $rcp_options;

		$args = array(
			'USER'               => $this->username,
			'PWD'                => $this->password,
			'SIGNATURE'          => $this->signature,
			'VERSION'            => '124',
			'METHOD'             => $this->auto_renew ? 'CreateRecurringPaymentsProfile' : 'DoDirectPayment',
			'AMT'                => $this->amount,
			'CURRENCYCODE'       => strtoupper( $this->currency ),
			'SHIPPINGAMT'        => 0,
			'TAXAMT'             => 0,
			'DESC'               => $this->subscription_name,
			'SOFTDESCRIPTOR'     => get_bloginfo( 'name' ) . ' - ' . $this->subscription_name,
			'SOFTDESCRIPTORCITY' => get_bloginfo( 'admin_email' ),
			'CUSTOM'             => $this->user_id,
			'NOTIFYURL'          => add_query_arg( 'listener', 'EIPN', home_url( 'index.php' ) ),
			'EMAIL'              => $this->email,
			'CREDITCARDTYPE'     => '',
			'ACCT'               => sanitize_text_field( $_POST['rcp_card_number'] ),
			'EXPDATE'            => sanitize_text_field( $_POST['rcp_card_exp_month'] . $_POST['rcp_card_exp_year'] ), // needs to be in the format 062019
			'CVV2'               => sanitize_text_field( $_POST['rcp_card_cvc'] ),
			'ZIP'                => sanitize_text_field( $_POST['rcp_card_zip'] ),
			'BUTTONSOURCE'       => 'EasyDigitalDownloads_SP',
			'PROFILESTARTDATE'   => date( 'Y-m-d\TH:i:s', strtotime( '+' . $this->length . ' ' . $this->length_unit, time() ) ),
			'BILLINGPERIOD'      => ucwords( $this->length_unit ),
			'BILLINGFREQUENCY'   => $this->length,
			'FAILEDINITAMTACTION'=> 'CancelOnFailure',
			'TOTALBILLINGCYCLES' => $this->auto_renew ? 0 : 1
		);

		if ( $this->auto_renew ) {

			$initamt = round( $this->amount + $this->signup_fee, 2 );

			if ( $initamt >= 0 ) {
				$args['INITAMT'] = $initamt;
			}

		}

		$request = wp_remote_post( $this->api_endpoint, array( 'timeout' => 45, 'sslverify' => false, 'httpversion' => '1.1', 'body' => $args ) );
		$body    = wp_remote_retrieve_body( $request );
		$code    = wp_remote_retrieve_response_code( $request );
		$message = wp_remote_retrieve_response_message( $request );

		if( is_wp_error( $request ) ) {

			do_action( 'rcp_paypal_pro_signup_payment_failed', $request, $this );

			$error = '<p>' . __( 'An unidentified error occurred.', 'rcp' ) . '</p>';
			$error .= '<p>' . $request->get_error_message() . '</p>';

			wp_die( $error, __( 'Error', 'rcp' ), array( 'response' => '401' ) );

		} elseif ( 200 == $code && 'OK' == $message ) {

			if( is_string( $body ) ) {
				wp_parse_str( $body, $body );
			}

			if( false !== strpos( strtolower( $body['ACK'] ), 'failure' ) ) {

				do_action( 'rcp_paypal_pro_signup_payment_failed', $request, $this );

				$error = '<p>' . __( 'PayPal subscription creation failed.', 'rcp' ) . '</p>';
				$error .= '<p>' . __( 'Error message:', 'rcp' ) . ' ' . $body['L_LONGMESSAGE0'] . '</p>';
				$error .= '<p>' . __( 'Error code:', 'rcp' ) . ' ' . $body['L_ERRORCODE0'] . '</p>';

				wp_die( $error, __( 'Error', 'rcp' ), array( 'response' => '401' ) );

			} else {

				// Successful signup
				$member = new RCP_Member( $this->user_id );

				if( $member->just_upgraded() && rcp_can_member_cancel( $member->ID ) ) {

					$cancelled = rcp_cancel_member_payment_profile( $member->ID, false );

				}

				if ( isset( $body['PROFILEID'] ) ) {
					$member->set_payment_profile_id( $body['PROFILEID'] );
				}

				if ( isset( $body['PROFILESTATUS'] ) && 'ActiveProfile' === $body['PROFILESTATUS'] ) {
					// Confirm a one-time payment
					$member->renew( $this->auto_renew );
				}

				wp_redirect( esc_url_raw( rcp_get_return_url() ) ); exit;
				exit;

			}

		} else {

			wp_die( __( 'Something has gone wrong, please try again', 'rcp' ), __( 'Error', 'rcp' ), array( 'back_link' => true, 'response' => '401' ) );

		}

	}

	/**
	 * Add credit card form
	 *
	 * @since 2.1
	 * @return string
	 */
	public function fields() {

		ob_start();
		rcp_get_template_part( 'card-form' );
		return ob_get_clean();
	}

	/**
	 * Validate additional fields during registration submission
	 *
	 * @since 2.1
	 */
	public function validate_fields() {

		if( ! rcp_has_paypal_api_access() ) {
			$this->add_error( 'no_paypal_api', __( 'You have not configured PayPal API access. Please configure it in Restrict &rarr; Settings', 'rcp' ) );
		}

		if( empty( $_POST['rcp_card_number'] ) ) {
			$this->add_error( 'missing_card_number', __( 'The card number you have entered is invalid', 'rcp' ) );
		}

		if( empty( $_POST['rcp_card_cvc'] ) ) {
			$this->add_error( 'missing_card_code', __( 'The security code you have entered is invalid', 'rcp' ) );
		}

		if( empty( $_POST['rcp_card_zip'] ) ) {
			$this->add_error( 'missing_card_zip', __( 'The zip / postal code you have entered is invalid', 'rcp' ) );
		}

		if( empty( $_POST['rcp_card_name'] ) ) {
			$this->add_error( 'missing_card_name', __( 'The card holder name you have entered is invalid', 'rcp' ) );
		}

		if( empty( $_POST['rcp_card_exp_month'] ) ) {
			$this->add_error( 'missing_card_exp_month', __( 'The card expiration month you have entered is invalid', 'rcp' ) );
		}

		if( empty( $_POST['rcp_card_exp_year'] ) ) {
			$this->add_error( 'missing_card_exp_year', __( 'The card expiration year you have entered is invalid', 'rcp' ) );
		}

	}

	/**
	 * Process webhooks
	 *
	 * @since 2.1
	 */
	public function process_webhooks() {

		// These are processed through PayPal Express gateway

	}

}