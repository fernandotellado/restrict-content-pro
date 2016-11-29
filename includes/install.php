<?php

// function to create the DB / Options / Defaults
function rcp_options_install( $network_wide ) {
   	global $wpdb, $rcp_db_name, $rcp_db_version, $rcp_discounts_db_name, $rcp_discounts_db_version,
   	$rcp_payments_db_name, $rcp_payments_db_version;

   	$rcp_options = get_option( 'rcp_settings', array() );

   	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

	if ( $network_wide ) {
		add_action( 'shutdown', 'rcp_create_tables' );
	} else {
		rcp_create_tables();
	}

	update_option( "rcp_db_version", $rcp_db_version );

	update_option( "rcp_discounts_db_version", $rcp_discounts_db_version );

	update_option( "rcp_payments_db_version", $rcp_payments_db_version );

	// Create RCP caps
	$caps = new RCP_Capabilities;
	$caps->add_caps();

	// Checks if the purchase page option exists
	if ( ! isset( $rcp_options['registration_page'] ) ) {

		// Register Page
		$register = wp_insert_post(
			array(
				'post_title'     => __( 'Register', 'rcp' ),
				'post_content'   => '[register_form]',
				'post_status'    => 'publish',
				'post_author'    => 1,
				'post_type'      => 'page',
				'comment_status' => 'closed'
			)
		);

		// Welcome (Success) Page
		$success = wp_insert_post(
			array(
				'post_title'     => __( 'Welcome', 'rcp' ),
				'post_content'   => __( 'Welcome! This is your success page where members are redirected after completing their registration.', 'rcp' ),
				'post_status'    => 'publish',
				'post_author'    => 1,
				'post_parent'    => $register,
				'post_type'      => 'page',
				'comment_status' => 'closed'
			)
		);

		// Store our page IDs
		$rcp_options['registration_page'] = $register;
		$rcp_options['redirect']  = $success;

	}

	// Checks if the account page option exists
	if ( empty( $rcp_options['account_page'] ) ) {

		$account = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_type = 'page' AND post_content LIKE '%[subscription_details%' LIMIT 1;" );

		if( empty( $account ) ) {

			// Account Page
			$account = wp_insert_post(
				array(
					'post_title'     => __( 'Your Membership', 'rcp' ),
					'post_content'   => '[subscription_details]',
					'post_status'    => 'publish',
					'post_author'    => 1,
					'post_parent'    => $rcp_options['registration_page'],
					'post_type'      => 'page',
					'comment_status' => 'closed'
				)
			);

		}

		// Store our page IDs
		$rcp_options['account_page'] = $account;

	}

	// Checks if the profile editor page option exists
	if ( empty( $rcp_options['edit_profile'] ) ) {

		$profile = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_type = 'page' AND post_content LIKE '%[rcp_profile_editor%' LIMIT 1;" );

		if( empty( $profile ) ) {

			// Profile editor Page
			$profile = wp_insert_post(
				array(
					'post_title'     => __( 'Edit Your Profile', 'rcp' ),
					'post_content'   => '[rcp_profile_editor]',
					'post_status'    => 'publish',
					'post_author'    => 1,
					'post_parent'    => $rcp_options['registration_page'],
					'post_type'      => 'page',
					'comment_status' => 'closed'
				)
			);

		}

		// Store our page IDs
		$rcp_options['edit_profile'] = $profile;

	}

	// Checks if the update billing card page option exists
	if ( empty( $rcp_options['update_card'] ) ) {

		$update_card = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_type = 'page' AND post_content LIKE '%[rcp_update_card%' LIMIT 1;" );

		if( empty( $update_card ) ) {

			// update_card editor Page
			$update_card = wp_insert_post(
				array(
					'post_title'     => __( 'Update Billing Card', 'rcp' ),
					'post_content'   => '[rcp_update_card]',
					'post_status'    => 'publish',
					'post_author'    => 1,
					'post_parent'    => $rcp_options['registration_page'],
					'post_type'      => 'page',
					'comment_status' => 'closed'
				)
			);

		}

		// Store our page IDs
		$rcp_options['update_card'] = $update_card;

	}

	update_option( 'rcp_settings', $rcp_options );

	// and option that allows us to make sure RCP is installed
	update_option( 'rcp_is_installed', '1' );
	update_option( 'rcp_version', RCP_PLUGIN_VERSION );

}
// run the install scripts upon plugin activation
register_activation_hook( RCP_PLUGIN_FILE, 'rcp_options_install' );

function rcp_check_if_installed() {

	if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
		return;
	}

	// this is mainly for network activated installs
	if( ! get_option( 'rcp_is_installed' ) ) {
		rcp_options_install();
	}
}
add_action( 'admin_init', 'rcp_check_if_installed' );

function rcp_create_tables() {

	// create the RCP subscription level database table
	$rcp_db_name = rcp_get_levels_db_name();
	$sql = "CREATE TABLE {$rcp_db_name} (
		id bigint(9) NOT NULL AUTO_INCREMENT,
		name varchar(200) NOT NULL,
		description longtext NOT NULL,
		duration smallint NOT NULL,
		duration_unit tinytext NOT NULL,
		price tinytext NOT NULL,
		fee tinytext NOT NULL,
		list_order mediumint NOT NULL,
		level mediumint NOT NULL,
		status varchar(12) NOT NULL,
		role tinytext NOT NULL,
		PRIMARY KEY id (id),
		KEY name (name),
		KEY status (status)
		) CHARACTER SET utf8 COLLATE utf8_general_ci;";

	@dbDelta( $sql );

	// Create subscription meta table
	$sub_meta_table_name = rcp_get_level_meta_db_name();
	$sql = "CREATE TABLE {$sub_meta_table_name} (
		meta_id bigint(20) NOT NULL AUTO_INCREMENT,
		level_id bigint(20) NOT NULL DEFAULT '0',
		meta_key varchar(255) DEFAULT NULL,
		meta_value longtext,
		PRIMARY KEY meta_id (meta_id),
		KEY level_id (level_id),
		KEY meta_key (meta_key)
		) CHARACTER SET utf8 COLLATE utf8_general_ci;";

	@dbDelta( $sql );

	// create the RCP discounts database table
	$rcp_discounts_db_name = rcp_get_discounts_db_name();
	$sql = "CREATE TABLE {$rcp_discounts_db_name} (
		id bigint(9) NOT NULL AUTO_INCREMENT,
		name tinytext NOT NULL,
		description longtext NOT NULL,
		amount tinytext NOT NULL,
		unit tinytext NOT NULL,
		code tinytext NOT NULL,
		use_count mediumint NOT NULL,
		max_uses mediumint NOT NULL,
		status tinytext NOT NULL,
		expiration mediumtext NOT NULL,
		subscription_id mediumint NOT NULL,
		PRIMARY KEY id (id)
		) CHARACTER SET utf8 COLLATE utf8_general_ci;";

	@dbDelta( $sql );

	// create the RCP payments database table
	$rcp_payments_db_name = rcp_get_payments_db_name();
	$sql = "CREATE TABLE {$rcp_payments_db_name} (
		id bigint(9) NOT NULL AUTO_INCREMENT,
		subscription varchar(200) NOT NULL,
		date datetime NOT NULL,
		amount mediumtext NOT NULL,
		user_id mediumint NOT NULL,
		payment_type tinytext NOT NULL,
		subscription_key varchar(32) NOT NULL,
		transaction_id varchar(64) NOT NULL,
		status varchar(12) NOT NULL,
		PRIMARY KEY id (id),
		KEY subscription(subscription),
		KEY user_id (user_id),
		KEY subscription_key (subscription_key),
		KEY transaction_id (transaction_id),
		KEY status (status)
		) CHARACTER SET utf8 COLLATE utf8_general_ci;";

	@dbDelta( $sql );

	// Create payment meta table
	$sub_meta_table_name = rcp_get_payment_meta_db_name();
	$sql = "CREATE TABLE {$sub_meta_table_name} (
		meta_id bigint(20) NOT NULL AUTO_INCREMENT,
		payment_id bigint(20) NOT NULL DEFAULT '0',
		meta_key varchar(255) DEFAULT NULL,
		meta_value longtext,
		PRIMARY KEY meta_id (meta_id),
		KEY payment_id (payment_id),
		KEY meta_key (meta_key)
		) CHARACTER SET utf8 COLLATE utf8_general_ci;";

	@dbDelta( $sql );
}