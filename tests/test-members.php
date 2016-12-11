<?php

class RCP_Member_Tests extends WP_UnitTestCase {

	protected $member;
	protected $level_id;
	protected $level_id_2;
	protected $level_id_3;
	protected $post_id;

	public function setUp() {
		parent::setUp();

		$user = wp_insert_user( array(
			'user_login' => 'test',
			'user_pass'       => 'pass',
			'first_name' => 'Tester',
			'user_email' => 'test@test.com'
		) );

		$this->member = new RCP_Member( $user );

		$levels = new RCP_Levels;

		$this->level_id = $levels->insert( array(
			'name'          => 'Gold',
			'duration'      => 1,
			'duration_unit' => 'month',
			'level'         => 1,
			'status'        => 'active',
			'price'         => 10
		) );

		$this->level_id_2 = $levels->insert( array(
			'name'          => 'Silver',
			'duration'      => 1,
			'duration_unit' => 'month',
			'status'        => 'active',
			'level'         => 3
		) );

		$this->level_id_3 = $levels->insert( array(
			'name'          => 'Bronze',
			'duration'      => 1,
			'duration_unit' => 'day',
			'status'        => 'active',
			'level'         => 3
		) );

		$this->post_id = wp_insert_post( array(
			'post_title'  => 'Test',
			'post_status' => 'publish',
		) );
	}

	function test_get_default_status() {
		$status = rcp_get_status( 1 );
		$this->assertEquals( 'free', $status );
	}

	function test_set_status() {

		rcp_set_status( 1, 'active' );

		$status = rcp_get_status( 1 );

		$this->assertEquals( 'active', $status );

		rcp_set_status( 1, 'pending' );

		$status = rcp_get_status( 1 );

		$this->assertEquals( 'pending', $status );

		rcp_set_status( 1, 'cancelled' );

		$status = rcp_get_status( 1 );

		$this->assertEquals( 'cancelled', $status );

		$this->member->set_status( 'expired' );

		$this->assertEquals( 'expired', $this->member->get_status() );

	}

	function test_get_status() {

		$this->assertEquals( 'free', $this->member->get_status() );
		$this->assertEquals( 'free', rcp_get_status( 1 ) );

	}

	function test_get_expiration_date() {

		$this->assertInternalType( 'string', $this->member->get_expiration_date() );

		// Should be today
		$this->assertEquals( date_i18n( get_option( 'date_format' ) ), $this->member->get_expiration_date() );

		$this->member->set_expiration_date( 'none' );

		$this->assertEquals( 'none', $this->member->get_expiration_date() );

		$this->member->set_expiration_date( '2025-01-01 00:00:00' );

		$this->assertEquals( date_i18n( get_option( 'date_format' ), strtotime( '2025-01-01 00:00:00' ) ), $this->member->get_expiration_date() );
		$this->assertEquals( '2025-01-01 00:00:00', $this->member->get_expiration_date( false) );
	}

	function test_get_expiration_time() {

		$this->assertFalse( $this->member->get_expiration_time() );

		$this->member->set_expiration_date( date( 'Y-n-d' ) );

		$this->assertInternalType( 'int', $this->member->get_expiration_time() );

	}

	function test_set_expiration_date() {

		$this->member->set_expiration_date( '2025-01-01 00:00:00' );

		$this->assertEquals( date_i18n( get_option( 'date_format' ), strtotime( '2025-01-01 00:00:00' ) ), $this->member->get_expiration_date() );
		$this->assertEquals( '2025-01-01 00:00:00', $this->member->get_expiration_date( false) );

	}

	function test_calculate_expiration() {

		// Test brand new member
		update_user_meta( $this->member->ID, 'rcp_subscription_level', $this->level_id );
		delete_user_meta( $this->member->ID, 'rcp_expiration' );

		$expiration = $this->member->calculate_expiration();

		$this->member->set_expiration_date( $expiration );
		$this->assertEquals( $expiration, $this->member->get_expiration_date( false ) );
		$this->assertEquals( date( 'Y-n-d', strtotime( '+1 month') ), date( 'Y-n-d', $this->member->get_expiration_time() ) );

		// Now manually set expiration to last day of the month to force a date "walk".
		// See https://github.com/pippinsplugins/restrict-content-pro/issues/239

		update_user_meta( $this->member->ID, 'rcp_expiration', date( 'Y-n-d 23:59:59', strtotime( 'October 31, 2018' ) ) );

		$this->member->set_status( 'active' );

		$expiration = $this->member->calculate_expiration();
		$this->member->set_expiration_date( $expiration );
		$this->assertEquals( '2018-12-01 23:59:59', date( 'Y-n-d H:i:s', $this->member->get_expiration_time() ) );

		// Now test a one-day subscription
		delete_user_meta( $this->member->ID, 'rcp_expiration' );
		update_user_meta( $this->member->ID, 'rcp_subscription_level', $this->level_id_3 );

		$expiration = $this->member->calculate_expiration();
		$this->member->set_expiration_date( $expiration );
		$this->assertEquals( date( 'Y-n-d 23:59:59', strtotime( '+1 day' ) ), date( 'Y-n-d H:i:s', $this->member->get_expiration_time() ) );

	}

	function test_is_expired() {

		$this->assertFalse( $this->member->is_expired() );

		$this->member->set_expiration_date( '2014-01-01 00:00:00' );
		$this->assertTrue( $this->member->is_expired() );

		$this->member->set_expiration_date( '2025-01-01 00:00:00' );
		$this->assertFalse( $this->member->is_expired() );

	}

	function test_is_recurring() {

		$this->assertFalse( $this->member->is_recurring() );

		$this->member->set_recurring( true );

		$this->assertTrue( $this->member->is_recurring() );

		$this->member->set_recurring( false );

		$this->assertFalse( $this->member->is_recurring() );

		$this->member->set_recurring( 1 );

		$this->assertTrue( $this->member->is_recurring() );

		$this->member->set_recurring( 0 );

		$this->assertFalse( $this->member->is_recurring() );

		$this->member->set_recurring( 1 );
		$this->member->cancel();

		$this->assertFalse( $this->member->is_recurring() );

	}

	function test_renew() {

		$this->member->set_expiration_date( '2014-01-01 00:00:00' );
		$this->assertTrue( $this->member->is_expired() );

		// Should be false when no subscription ID is set
		$this->assertFalse( $this->member->renew() );

		update_user_meta( $this->member->ID, 'rcp_subscription_level', $this->level_id );

		delete_user_meta( $this->member->ID, 'rcp_expiration' );

		$this->member->renew();

		$this->assertFalse( $this->member->is_expired() );
		$this->assertEquals( date_i18n( get_option( 'date_format' ), strtotime( '+1 month' ) ), $this->member->get_expiration_date() );

	}

	function test_cancel() {

		$this->assertFalse( $this->member->is_active() );

		$this->member->set_status( 'active' );
		$this->member->set_recurring( true );

		$this->member->cancel();

		// Should still be active since the expiration date is in the future
		$this->assertTrue( $this->member->is_active() );
		$this->assertFalse( $this->member->is_recurring() );
		$this->assertEquals( 'cancelled', $this->member->get_status() );
	}

	function test_is_trialing() {

		$this->assertFalse( $this->member->is_trialing() );

		$this->member->set_status( 'active' );

		update_user_meta( $this->member->ID, 'rcp_is_trialing', 'yes' );

		$this->assertTrue( $this->member->is_trialing() );

		$this->member->set_status( 'free' );
		$this->assertFalse( $this->member->is_trialing() );


	}

	function test_has_trialed() {

		$this->assertFalse( $this->member->has_trialed() );

		update_user_meta( $this->member->ID, 'rcp_has_trialed', 'yes' );

		$this->assertTrue( $this->member->has_trialed() );

	}

	function test_can_access() {

		$this->member->set_status( 'active' );
		update_user_meta( $this->member->ID, 'rcp_subscription_level', $this->level_id );


		update_post_meta( $this->post_id, 'rcp_subscription_level', array( $this->level_id, $this->level_id_2 ) );

		$this->assertTrue( $this->member->can_access( $this->post_id ) );

		update_post_meta( $this->post_id, 'rcp_access_level', 4 );

		$this->assertFalse( $this->member->can_access( $this->post_id ) );

		update_post_meta( $this->post_id, 'rcp_access_level', 1 );

		$this->assertTrue( $this->member->can_access( $this->post_id ) );

		$this->member->set_status( 'cancelled' );

		$this->assertTrue( $this->member->can_access( $this->post_id ) );

		$this->member->set_status( 'expired' );

		$this->assertFalse( $this->member->can_access( $this->post_id ) );

		$this->member->renew();

		$this->assertTrue( $this->member->can_access( $this->post_id ) );

		$this->member->set_status( 'free' );

		$this->assertFalse( $this->member->can_access( $this->post_id ) );

		$this->member->set_status( 'active' );

		update_post_meta( $this->post_id, 'rcp_subscription_level', array( $this->level_id ) );

		$this->assertTrue( $this->member->can_access( $this->post_id ) );

		update_post_meta( $this->post_id, 'rcp_subscription_level', array( $this->level_id_2 ) );

		$this->assertFalse( $this->member->can_access( $this->post_id ) );

		update_user_meta( $this->member->ID, 'rcp_subscription_level', $this->level_id_2 );

		$this->assertTrue( $this->member->can_access( $this->post_id ) );
	}

	function test_cannot_access_active_content_as_pending_member() {

		$this->member->set_status( 'pending' );
		update_post_meta( $this->post_id, '_is_paid', true );
		update_post_meta( $this->post_id, 'rcp_subscription_level', 'any-paid' );

		$this->assertFalse( $this->member->can_access( $this->post_id ) );

	}

	function test_cannot_access_active_content_as_expired_member() {

		$this->member->set_status( 'expired' );
		update_post_meta( $this->post_id, '_is_paid', true );
		update_post_meta( $this->post_id, 'rcp_subscription_level', 'any-paid' );

		$this->assertFalse( $this->member->can_access( $this->post_id ) );

	}

	function test_can_access_active_content_as_cancelled_member() {

		$this->member->set_status( 'cancelled' );
		$this->member->set_expiration( date( 'Y-n-d H:i:s', strtotime( '+1 week' ) ) );
		update_post_meta( $this->post_id, '_is_paid', true );
		update_post_meta( $this->post_id, 'rcp_subscription_level', 'any-paid' );

		$this->assertTrue( $this->member->can_access( $this->post_id ) );

	}

	function test_cannot_access_active_content_as_free_member() {

		$this->member->set_status( 'free' );
		update_post_meta( $this->post_id, '_is_paid', true );
		update_post_meta( $this->post_id, 'rcp_subscription_level', 'any-paid' );

		$this->assertFalse( $this->member->can_access( $this->post_id ) );

	}

	function test_can_access_active_content_as_active_member() {

		$this->member->set_status( 'active' );
		$this->member->set_expiration( date( 'Y-n-d H:i:s', strtotime( '+1 week' ) ) );
		update_post_meta( $this->post_id, '_is_paid', true );
		update_post_meta( $this->post_id, 'rcp_subscription_level', 'any-paid' );

		$this->assertTrue( $this->member->can_access( $this->post_id ) );

	}

	function test_can_access_active_content_as_active_member_with_subscription_level() {

		$this->member->set_status( 'active' );
		$this->member->set_expiration( date( 'Y-n-d H:i:s', strtotime( '+1 week' ) ) );
		update_post_meta( $this->post_id, '_is_paid', true );
		update_user_meta( $this->member->ID, 'rcp_subscription_level', $this->level_id );
		update_post_meta( $this->post_id, 'rcp_subscription_level', array( $this->level_id, $this->level_id_2 ) );

		$this->assertTrue( $this->member->can_access( $this->post_id ) );

	}

	function test_can_access_active_content_as_cancelled_member_with_subscription_level() {

		$this->member->set_status( 'cancelled' );
		$this->member->set_expiration( date( 'Y-n-d H:i:s', strtotime( '+1 week' ) ) );
		update_post_meta( $this->post_id, '_is_paid', true );
		update_user_meta( $this->member->ID, 'rcp_subscription_level', $this->level_id );
		update_post_meta( $this->post_id, 'rcp_subscription_level', array( $this->level_id, $this->level_id_2 ) );

		$this->assertTrue( $this->member->can_access( $this->post_id ) );

	}

	function test_cannot_access_active_content_as_expired_member_with_subscription_level() {

		$this->member->set_expiration( date( 'Y-n-d H:i:s', strtotime( '-1 week' ) ) );
		$this->member->set_status( 'expired' );
		update_post_meta( $this->post_id, '_is_paid', true );
		update_user_meta( $this->member->ID, 'rcp_subscription_level', $this->level_id );
		update_post_meta( $this->post_id, 'rcp_subscription_level', array( $this->level_id, $this->level_id_2 ) );

		$this->assertFalse( $this->member->can_access( $this->post_id ) );

	}

	function test_cannot_access_active_content_as_pending_member_with_subscription_level() {

		$this->member->set_status( 'pending' );
		update_post_meta( $this->post_id, '_is_paid', true );
		update_user_meta( $this->member->ID, 'rcp_subscription_level', $this->level_id );
		update_post_meta( $this->post_id, 'rcp_subscription_level', array( $this->level_id, $this->level_id_2 ) );

		$this->assertFalse( $this->member->can_access( $this->post_id ) );

	}

	function test_cannot_access_active_content_as_free_member_with_subscription_level() {

		$this->member->set_status( 'free' );
		update_post_meta( $this->post_id, '_is_paid', true );
		update_user_meta( $this->member->ID, 'rcp_subscription_level', $this->level_id );
		update_post_meta( $this->post_id, 'rcp_subscription_level', array( $this->level_id, $this->level_id_2 ) );

		$this->assertFalse( $this->member->can_access( $this->post_id ) );

	}

	function test_cannot_access_active_content_as_active_member_without_subscription_level() {

		$this->member->set_status( 'active' );
		$this->member->set_expiration( date( 'Y-n-d H:i:s', strtotime( '+1 week' ) ) );
		update_post_meta( $this->post_id, '_is_paid', true );
		update_user_meta( $this->member->ID, 'rcp_subscription_level', $this->level_id_3 );
		update_post_meta( $this->post_id, 'rcp_subscription_level', array( $this->level_id, $this->level_id_2 ) );

		$this->assertFalse( $this->member->can_access( $this->post_id ) );

	}

	function test_cannot_access_active_content_as_cancelled_member_without_subscription_level() {

		$this->member->set_status( 'cancelled' );
		$this->member->set_expiration( date( 'Y-n-d H:i:s', strtotime( '+1 week' ) ) );
		update_post_meta( $this->post_id, '_is_paid', true );
		update_user_meta( $this->member->ID, 'rcp_subscription_level', $this->level_id_3 );
		update_post_meta( $this->post_id, 'rcp_subscription_level', array( $this->level_id, $this->level_id_2 ) );

		$this->assertFalse( $this->member->can_access( $this->post_id ) );

	}

	function test_cannot_access_active_content_as_expired_member_without_subscription_level() {

		$this->member->set_expiration( date( 'Y-n-d H:i:s', strtotime( '-1 week' ) ) );
		$this->member->set_status( 'expired' );
		update_post_meta( $this->post_id, '_is_paid', true );
		update_user_meta( $this->member->ID, 'rcp_subscription_level', $this->level_id_3 );
		update_post_meta( $this->post_id, 'rcp_subscription_level', array( $this->level_id, $this->level_id_2 ) );

		$this->assertFalse( $this->member->can_access( $this->post_id ) );

	}

	function test_cannot_access_active_content_as_pending_member_without_subscription_level() {

		$this->member->set_status( 'pending' );
		update_post_meta( $this->post_id, '_is_paid', true );
		update_user_meta( $this->member->ID, 'rcp_subscription_level', $this->level_id_3 );
		update_post_meta( $this->post_id, 'rcp_subscription_level', array( $this->level_id, $this->level_id_2 ) );

		$this->assertFalse( $this->member->can_access( $this->post_id ) );

	}

	function test_cannot_access_active_content_as_free_member_without_subscription_level() {

		$this->member->set_status( 'free' );
		update_post_meta( $this->post_id, '_is_paid', true );
		update_user_meta( $this->member->ID, 'rcp_subscription_level', $this->level_id_3 );
		update_post_meta( $this->post_id, 'rcp_subscription_level', array( $this->level_id, $this->level_id_2 ) );

		$this->assertFalse( $this->member->can_access( $this->post_id ) );

	}
}