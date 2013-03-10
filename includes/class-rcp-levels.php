<?php

/**
 * RCP Subscription Levels class
 *
 * This class handles querying, inserting, updating, and removing subscription levels
 * Also includes other discount helper functions
 *
 * @since 1.5
*/

class RCP_Levels {

	/**
	 * Holds the name of our levels database table
	 *
	 * @access  private
	 * @since   1.5
	*/

	private $db_name;


	/**
	 * Holds the version number of our levels database table
	 *
	 * @access  private
	 * @since   1.5
	*/

	private $db_version;


	/**
	 * Get things started
	 *
	 * @since   1.5
	*/

	function __construct() {

		$this->db_name    = rcp_get_levels_db_name();
		$this->db_version = '1.2';

	}


	/**
	 * Retrieve a specific subscription level from the database
	 *
	 * @access  public
	 * @since   1.5
	*/

	public function get_level( $level_id = 0 ) {
		global $wpdb;

		$level = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->db_name} WHERE id='%d';", $level_id ) );

		return $level;

	}


	/**
	 * Retrieve all subscription levels from the database
	 *
	 * @access  public
	 * @since   1.5
	*/

	public function get_levels( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'status'  => 'all',
			'limit'   => null,
			'orderby' => 'list_order'
		);

		$args = wp_parse_args( $args, $defaults );

		if( $args['status'] == 'active' ) {
			$where = "WHERE `status` !='inactive'";
		} elseif( $args['status'] == 'inactive' ) {
			$where = "WHERE `status` ='{$status}'";
		} else {
			$where = "";
		}

		if( ! empty( $args['limit'] ) )
			$limit = " LIMIT={$args['limit']}";
		else
			$limit = '';

		$levels = $wpdb->get_results( "SELECT * FROM {$this->db_name} {$where} ORDER BY {$args['orderby']}{$limit};" );

		if( $levels )
			return $levels;
		return false;
	}


	/**
	 * Retrieve a field for a subscription level
	 *
	 * @access  public
	 * @since   1.5
	*/

	public function get_level_field( $level_id = 0, $field = '' ) {

		global $wpdb;

		$field = $wpdb->get_col( $wpdb->prepare( "SELECT {$field} FROM {$this->db_name} WHERE id='%d';", $level_id ) );

		if( $field )
			return $field[0];
		return false;
	}


	/**
	 * Insert a subscription level into the database
	 *
	 * @access  public
	 * @since   1.5
	*/

	public function insert( $args = array() ) {

		global $wpdb;

		$defaults = array(
			'name'          => '',
			'description'   => '',
			'duration'      => 'unlimited',
			'duration_unit' => 'm',
			'price'         => '0',
			'list_order'    => '0',
			'level' 	    => '0',
			'status'        => 'inactive'
		);

		$args = wp_parse_args( $args, $defaults );

		do_action( 'rcp_pre_add_subscription', $args );

		$add = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$this->db_name} SET
					`name`          = '%s',
					`description`   = '%s',
					`duration`      = '%d',
					`duration_unit` = '%s',
					`price`         = '%s',
					`list_order`    = '0',
					`level`         = '%d',
					`status`        = '%s'
				;",
				sanitize_text_field( $args['name'] ),
				sanitize_text_field( $args['description'] ),
				sanitize_text_field( $args['duration'] ),
				sanitize_text_field( $args['duration_unit'] ),
				sanitize_text_field( $args['price'] ),
				absint( $args['level'] ),
				sanitize_text_field( $args['status'] )
			 )
		);

		if( $add ) {
			do_action( 'rcp_add_subscription', $wpdb->insert_id, $args );
			delete_transient( 'rcp_subscription_levels' );
			return $wpdb->insert_id;
		}

		return false;

	}


	/**
	 * Update an existing subscription level
	 *
	 * @access  public
	 * @since   1.5
	*/

	public function update( $level_id = 0, $args = array() ) {

		global $wpdb;

		$level = $this->get_level( $level_id );
		$level = get_object_vars( $level );

		$args     = array_merge( $level, $args );

		do_action( 'rcp_pre_edit_subscription_level', absint( $args['id'] ), $args );

		$update = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->db_name} SET
					`name`          = '%s',
					`description`   = '%s',
					`duration`      = '%d',
					`duration_unit` = '%s',
					`price`         = '%s',
					`list_order`    = '0',
					`level`         = '%d',
					`status`        = '%s'
					WHERE `id`    = '%d'
				;",
				sanitize_text_field( $args['name'] ),
				wp_kses( $args['description'], rcp_allowed_html_tags() ),
				sanitize_text_field( $args['duration'] ),
				sanitize_text_field( $args['duration_unit'] ),
				sanitize_text_field( $args['price'] ),
				absint( $args['level'] ),
				sanitize_text_field( $args['status'] ),
				absint( $args['id'] )
			)
		);

		do_action( 'rcp_edit_subscription_level', absint( $args['id'] ), $args );

		delete_transient( 'rcp_subscription_levels' );

		if( $update )
			return true;
		return false;

	}


	/**
	 * Delete a subscription level
	 *
	 * @access  public
	 * @since   1.5
	*/

	public function remove( $level_id = 0 ) {

		global $wpdb;
		$remove = $wpdb->query( $wpdb->prepare( "DELETE FROM " . $this->db_name . " WHERE `id`='%d';", absint( $level_id ) ) );
		delete_transient( 'rcp_subscription_levels' );
	}

}