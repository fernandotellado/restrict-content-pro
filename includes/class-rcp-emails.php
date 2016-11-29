<?php
/**
 * Emails
 *
 * This class handles all emails sent through Restrict Content Pro
 *
 * @package     Restrict Content Pro
 * @subpackage  Classes/Emails
 * @copyright   Copyright (c) 2016, Restrict Content Pro
 * @license     http://opensource.org/license/gpl-2.1.php GNU Public License
 * @since       2.7
 */


// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;


/**
 * RCP_Emails class
 *
 * @since 2.7
 */
class RCP_Emails {

	/**
	 * Holds the from address
	 *
	 * @since 2.7
	 */
	private $from_address;

	/**
	 * Holds the from name
	 *
	 * @since 2.7
	 */
	private $from_name;

	/**
	 * Holds the email content type
	 *
	 * @since 2.7
	 */
	private $content_type;

	/**
	 * Holds the email headers
	 *
	 * @since 2.7
	 */
	private $headers;

	/**
	 * Whether to send email in HTML
	 *
	 * @since 2.7
	 */
	private $html = true;

	/**
	 * The email template to use
	 *
	 * @since 2.7
	 */
	private $template;

	/**
	 * The header text for the email
	 *
	 * @since 2.7
	 */
	private $heading = '';

	/**
	 * Affiliate ID
	 *
	 * @since 2.7
	 */
	private $member_id;

	/**
	 * Payment ID
	 *
	 * @since 2.7
	 */
	private $payment_id;

	/**
	 * Container for storing all tags
	 *
	 * @since 2.7
	 */
	private $tags;

	/**
	 * Get things going
	 *
	 * @since 2.7
	 * @return void
	 */
	public function __construct() {

		if ( 'none' === $this->get_template() ) {
			$this->html = false;
		}

		add_action( 'rcp_email_send_before', array( $this, 'send_before' ) );
		add_action( 'rcp_email_send_after', array( $this, 'send_after' ) );
	}

	/**
	 * Set a property
	 *
	 * @since 2.7
	 * @return void
	 */
	public function __set( $key, $value ) {
		$this->$key = $value;
	}

	/**
	 * Get the email from name
	 *
	 * @since 2.7
	 * @return string The email from name
	 */
	public function get_from_name() {
		global $rcp_options;

		if ( ! $this->from_name ) {
			$this->from_name = ! empty( $rcp_options['from_name'] ) ? sanitize_text_field( $rcp_options['from_name'] ) : get_option( 'blogname' );
		}

		return apply_filters( 'rcp_emails_from_name', wp_specialchars_decode( $this->from_name ), $this );
	}

	/**
	 * Get the email from address
	 *
	 * @since 2.7
	 * @return string The email from address
	 */
	public function get_from_address() {
		if ( ! $this->from_address ) {

			global $rcp_options;
			$this->from_address = ! empty( $rcp_options['from_email'] ) ? sanitize_text_field( $rcp_options['from_email'] ) : get_option( 'admin_email' );
		}

		return apply_filters( 'rcp_emails_from_address', $this->from_address, $this );
	}

	/**
	 * Get the email content type
	 *
	 * @since 2.7
	 * @return string The email content type
	 */
	public function get_content_type() {
		if ( ! $this->content_type && $this->html ) {
			$this->content_type = apply_filters( 'rcp_email_default_content_type', 'text/html', $this );
		} elseif ( ! $this->html ) {
			$this->content_type = 'text/plain';
		}

		return apply_filters( 'rcp_email_content_type', $this->content_type, $this );
	}

	/**
	 * Get the email headers
	 *
	 * @since 2.7
	 * @return string The email headers
	 */
	public function get_headers() {
		if ( ! $this->headers ) {
			$this->headers  = "From: {$this->get_from_name()} <{$this->get_from_address()}>\r\n";
			$this->headers .= "Reply-To: {$this->get_from_address()}\r\n";
			$this->headers .= "Content-Type: {$this->get_content_type()}; charset=utf-8\r\n";
		}

		return apply_filters( 'rcp_email_headers', $this->headers, $this );
	}

	/**
	 * Retrieve email templates
	 *
	 * @since 2.7
	 * @return array The email templates
	 */
	public function get_templates() {
		$templates    = array(
			'default' => __( 'Default Template', 'rcp' ),
			'none'	  => __( 'No template, plain text only', 'rcp' )
		);

		return apply_filters( 'rcp_email_templates', $templates );
	}

	/**
	 * Get the enabled email template
	 *
	 * @since 2.7
	 * @return string|null
	 */
	public function get_template() {
		if ( ! $this->template ) {
			$this->template = ! empty( $rcp_options['email_template'] ) ? sanitize_text_field( $rcp_options['email_template'] ) : 'default';
		}

		return apply_filters( 'rcp_email_template', $this->template );
	}

	/**
	 * Get the header text for the email
	 *
	 * @since 2.7
	 * @return string The header text
	 */
	public function get_heading() {
		if ( ! $this->heading ) {
			$this->heading = ! empty( $rcp_options['email_header_text'] ) ? sanitize_text_field( $rcp_options['email_header_text'] ) : __( 'Hello', 'rcp' );
		}
		return apply_filters( 'rcp_email_heading', $this->heading );
	}

	/**
	 * Build the email
	 *
	 * @since 2.7
	 * @param string $message The email message
	 * @return string
	 */
	public function build_email( $message ) {

		if ( false === $this->html ) {
			return apply_filters( 'rcp_email_message', wp_strip_all_tags( $message ), $this );
		}

		$message = $this->text_to_html( $message );

		ob_start();

		rcp_get_template_part( 'emails/header', $this->get_template(), true );

		/**
		 * Hooks into the email header
		 *
		 * @since 2.7
		 */
		do_action( 'rcp_email_header', $this );

		rcp_get_template_part( 'emails/body', $this->get_template(), true );

		/**
		 * Hooks into the email body
		 *
		 * @since 2.7
		 */
		do_action( 'rcp_email_body', $this );

		rcp_get_template_part( 'emails/footer', $this->get_template(), true );

		/**
		 * Hooks into the email footer
		 *
		 * @since 2.7
		 */
		do_action( 'rcp_email_footer', $this );

		$body	 = ob_get_clean();
		$message = str_replace( '{email}', $message, $body );

		return apply_filters( 'rcp_email_message', $message, $this );
	}

	/**
	 * Send the email
	 *
	 * @since 2.7
	 * @param string $to The To address
	 * @param string $subject The subject line of the email
	 * @param string $message The body of the email
	 * @param string|array $attachments Attachments to the email
	 */
	public function send( $to, $subject, $message, $attachments = '' ) {

		if ( ! did_action( 'init' ) && ! did_action( 'admin_init' ) ) {
			_doing_it_wrong( __FUNCTION__, __( 'You cannot send emails with rcp_Emails until init/admin_init has been reached', 'rcp' ), null );
			return false;
		}

		$this->setup_email_tags();

		/**
		 * Hooks before email is sent
		 *
		 * @since 2.7
		 */
		do_action( 'rcp_email_send_before', $this );

		$message = $this->build_email( $message );

		$message = $this->parse_tags( $message );

		$attachments = apply_filters( 'rcp_email_attachments', $attachments, $this );

		$sent = wp_mail( $to, $subject, $message, $this->get_headers(), $attachments );

		/**
		 * Hooks after the email is sent
		 *
		 * @since 2.7
		 */
		do_action( 'rcp_email_send_after', $this );

		return $sent;
	}

	/**
	 * Add filters/actions before the email is sent
	 *
	 * @since 2.7
	 */
	public function send_before() {
		add_filter( 'wp_mail_from', array( $this, 'get_from_address' ) );
		add_filter( 'wp_mail_from_name', array( $this, 'get_from_name' ) );
		add_filter( 'wp_mail_content_type', array( $this, 'get_content_type' ) );
	}

	/**
	 * Remove filters/actions after the email is sent
	 *
	 * @since 2.7
	 */
	public function send_after() {
		remove_filter( 'wp_mail_from', array( $this, 'get_from_address' ) );
		remove_filter( 'wp_mail_from_name', array( $this, 'get_from_name' ) );
		remove_filter( 'wp_mail_content_type', array( $this, 'get_content_type' ) );

		// Reset heading to an empty string
		$this->heading = '';
	}

	/**
	 * Converts text formatted HTML. This is primarily for turning line breaks into <p> and <br/> tags.
	 *
	 * @since 2.7
	 */
	public function text_to_html( $message ) {
		if ( 'text/html' === $this->content_type || true === $this->html ) {
			$message = wpautop( $message );
		}

		return $message;
	}

	/**
	 * Search content for email tags and filter email tags through their hooks
	 *
	 * @since 2.7
	 * @param string $content Content to search for email tags
	 * @param int $affiliate_id The affiliate ID
	 * @return string $content Filtered content
	 */
	private function parse_tags( $content ) {

		// Make sure there's at least one tag
		if ( empty( $this->tags ) || ! is_array( $this->tags ) ) {
			return $content;
		}

		$new_content = preg_replace_callback( "/{([A-z0-9\-\_]+)}/s", array( $this, 'do_tag' ), $content );

		// This is an old filter and should no longer be used. Use rcp_email_template_tags instead to register new tags.
		$new_content = apply_filters( 'rcp_email_tags', $content, $this->member_id );

		return $new_content;
	}

	/**
	 * Setup all registered email tags
	 *
	 * @since 2.7
	 * @return void
	 */
	private function setup_email_tags() {

		$tags = $this->get_tags();

		foreach( $tags as $tag ) {
			if ( isset( $tag['function'] ) && is_callable( $tag['function'] ) ) {
				$this->tags[ $tag['tag'] ] = $tag;
			}
		}

	}

	/**
	 * Retrieve all registered email tags
	 *
	 * @since 2.7
	 * @return array
	 */
	public function get_tags() {

		// Setup default tags array
		$email_tags = array(
			array(
				'tag'         => 'name',
				'description' => __( 'The full name of the member', 'rcp' ),
				'function'    => 'rcp_email_tag_name'
			),
			array(
				'tag'         => 'username',
				'description' => __( 'The user name of the member on the site', 'rcp' ),
				'function'    => 'rcp_email_tag_user_name'
			),
			array(
				'tag'         => 'useremail',
				'description' => __( 'The email address of the member', 'rcp' ),
				'function'    => 'rcp_email_tag_user_email'
			),
			array(
				'tag'         => 'firstname',
				'description' => __( 'The first name of the member', 'rcp' ),
				'function'    => 'rcp_email_tag_first_name'
			),
			array(
				'tag'         => 'lastname',
				'description' => __( 'The last name of the member', 'rcp' ),
				'function'    => 'rcp_email_tag_last_name'
			),
			array(
				'tag'         => 'displayname',
				'description' => __( 'The display name of the member', 'rcp' ),
				'function'    => 'rcp_email_tag_display_name'
			),
			array(
				'tag'         => 'expiration',
				'description' => __( 'The expiration date of the member', 'rcp' ),
				'function'    => 'rcp_email_tag_expiration'
			),
			array(
				'tag'         => 'subscription_name',
				'description' => __( 'The name of the subscription level the member is subscribed to', 'rcp' ),
				'function'    => 'rcp_email_tag_expiration'
			),
			array(
				'tag'         => 'subscription_key',
				'description' => __( 'The unique key of the subscription level the member is subscribed to', 'rcp' ),
				'function'    => 'rcp_email_tag_subscription_key'
			),
			array(
				'tag'         => 'amount',
				'description' => __( 'The amount of the last payment made by the member', 'rcp' ),
				'function'    => 'rcp_email_tag_amount'
			),
			array(
				'tag'         => 'member_id',
				'description' => __( 'The members&#8217;s ID', 'rcp' ),
				'function'    => 'rcp_email_tag_member_id'
			),
			array(
				'tag'         => 'blogname',
				'description' => __( 'The name of this website', 'rcp' ),
				'function'    => 'rcp_email_tag_site_name'
			),
		);

		return apply_filters( 'rcp_email_template_tags', $email_tags, $this );

	}

	/**
	 * Parse a specific tag.
	 *
	 * @since 2.7
	 * @param $m Message
	 */
	private function do_tag( $m ) {

		// Get tag
		$tag = $m[1];

		// Return tag if not set
		if ( ! $this->email_tag_exists( $tag ) ) {
			return $m[0];
		}

		return call_user_func( $this->tags[$tag]['function'], $this->member_id, $this->payment, $tag );
	}

	/**
	 * Check if $tag is a registered email tag
	 *
	 * @since 2.7
	 * @param string $tag Email tag that will be searched
	 * @return bool True if exists, false otherwise
	 */
	public function email_tag_exists( $tag ) {
		return array_key_exists( $tag, $this->tags );
	}

}
