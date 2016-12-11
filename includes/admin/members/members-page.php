<?php

function rcp_members_page() {
	global $rcp_options, $rcp_db_name, $wpdb;
	$current_page = admin_url( '/admin.php?page=rcp-members' ); ?>
	<div class="wrap" id="rcp-members-page">

		<?php if( isset( $_GET['edit_member'] ) || isset( $_GET['view_member'] ) ) :
			include( 'edit-member.php' );
		else : ?>
			<h2><?php _e(' Paid Subscribers', 'rcp' ); ?></h2>
			<?php

			$subscription_id = isset( $_GET['subscription'] ) && $_GET['subscription'] != 'all' ? urldecode( $_GET['subscription'] ) : null;
			$status          = ! empty( $_GET['status'] )  ? urldecode( $_GET['status'] ) : 'active';
			$order           = ! empty( $_GET['order']  )  ? urldecode( $_GET['order']  ) : 'DESC';
			$search          = ! empty( $_GET['s'] )       ? urldecode( $_GET['s'] )      : '';

			$base_url        = admin_url( 'admin.php?page=rcp-members' );
			if( $search ) {
				$base_url = add_query_arg( 's', $search, $base_url );
			}

			// Get subscriber count
			if( ! empty( $search ) || ! empty( $subscription_id ) ) {

				// Query counts
				$active_count    = rcp_count_members( $subscription_id, 'active', null, $search );
				$pending_count   = rcp_count_members( $subscription_id, 'pending', null, $search );
				$expired_count   = rcp_count_members( $subscription_id, 'expired', null, $search );
				$cancelled_count = rcp_count_members( $subscription_id, 'cancelled', null, $search );
				$free_count      = rcp_count_members( $subscription_id, 'free', null, $search );
				$current_count   = rcp_count_members( $subscription_id, $status, null, $search );

			} else {

				// Retrieve static counts
				$active_count    = rcp_get_member_count( 'active' );
				$pending_count   = rcp_get_member_count( 'pending' );
				$expired_count   = rcp_get_member_count( 'expired' );
				$cancelled_count = rcp_get_member_count( 'cancelled' );
				$free_count      = rcp_get_member_count( 'free' );
				$current_count   = rcp_get_member_count( $status );
			}


			// pagination variables
			$page            = isset( $_GET['p'] ) ? absint( $_GET['p'] ) : 1;
			$user            = get_current_user_id();
			$screen          = get_current_screen();
			$screen_option   = $screen->get_option( 'per_page', 'option' );
			$per_page        = get_user_meta( $user, $screen_option, true );
			if ( empty ( $per_page) || $per_page < 1 ) {
				$per_page    = $screen->get_option( 'per_page', 'default' );
			}
			$total_pages     = 1;
			$offset          = $per_page * ( $page - 1 );
			$total_pages     = ceil( $current_count / $per_page );

			?>
			<ul class="subsubsub">
				<li><?php _e('Status: ', 'rcp'); ?></li>
				<li>
					<a href="<?php echo esc_url( add_query_arg('status', 'active', $base_url ) ); ?>" title="<?php _e('View all active subscribers', 'rcp'); ?>" <?php echo (isset($_GET['status']) && $_GET['status'] == 'active') || !isset($_GET['status']) ? 'class="current"' : ''; ?>>
					<?php _e('Active', 'rcp'); ?>
					</a>(<?php echo $active_count; ?>)
				</li>
				<li>
					<a href="<?php echo esc_url( add_query_arg('status', 'pending', $base_url ) ); ?>" title="<?php _e('View all pending subscribers', 'rcp'); ?>" <?php echo (isset($_GET['status']) && $_GET['status'] == 'pending') ? 'class="current"' : ''; ?>>
						<?php _e('Pending', 'rcp'); ?>
					</a>(<?php echo $pending_count; ?>)
				</li>
				<li>
					<a href="<?php echo esc_url( add_query_arg('status', 'expired', $base_url ) ); ?>" title="<?php _e('View all expired subscribers', 'rcp'); ?>" <?php echo (isset($_GET['status']) && $_GET['status'] == 'expired') ? 'class="current"' : ''; ?>>
						<?php _e('Expired', 'rcp'); ?>
					</a>(<?php echo $expired_count; ?>)
				</li>
				<li>
					<a href="<?php echo esc_url( add_query_arg('status', 'cancelled', $base_url ) ); ?>" title="<?php _e('View all cancelled subscribers', 'rcp'); ?>" <?php echo (isset($_GET['status']) && $_GET['status'] == 'cancelled') ? 'class="current"' : ''; ?>>
						<?php _e('Cancelled', 'rcp'); ?>
					</a>(<?php echo $cancelled_count; ?>)
				</li>
				<li>
					<a href="<?php echo esc_url( add_query_arg('status', 'free', $base_url ) ); ?>" title="<?php _e('View all free members', 'rcp'); ?>" <?php echo (isset($_GET['status']) && $_GET['status'] == 'free') ? 'class="current"' : ''; ?>>
						<?php _e('Free', 'rcp'); ?>
					</a>(<?php echo $free_count; ?>)
				</li>
				<?php do_action( 'rcp_members_page_statuses' ); ?>
			</ul>
			<form id="rcp-member-search" method="get" action="<?php menu_page_url( 'rcp-members' ); ?>">
				<label class="screen-reader-text" for="rcp-member-search-input"><?php _e( 'Search Members', 'rcp' ); ?></label>
				<input type="search" id="rcp-member-search-input" name="s" value="<?php echo esc_attr( $search ); ?>"/>
				<input type="hidden" name="page" value="rcp-members"/>
				<input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>"/>
				<input type="submit" name="" id="rcp-member-search-submit" class="button" value="<?php _e( 'Search members', 'rcp' ); ?>"/>
			</form>
			<form id="members-filter" action="" method="get">
				<?php
				$levels = rcp_get_subscription_levels( 'all' );
				if($levels) : ?>
					<select name="subscription" id="rcp-subscription">
						<option value="all"><?php _e('All Subscriptions', 'rcp'); ?></option>
						<?php
							foreach($levels as $level) :
								echo '<option value="' . $level->id . '" ' . selected($subscription_id, $level->id, false) . '>' . $level->name . '</option>';
							endforeach;
						?>
					</select>
				<?php endif; ?>
				<select name="order" id="rcp-order">
					<option value="DESC" <?php selected($order, 'DESC'); ?>><?php _e('Newest First', 'rcp'); ?></option>
					<option value="ASC" <?php selected($order, 'ASC'); ?>><?php _e('Oldest First', 'rcp'); ?></option>
				</select>
				<input type="hidden" name="page" value="rcp-members"/>
				<input type="hidden" name="status" value="<?php echo isset($_GET['status']) ? $_GET['status'] : 'active'; ?>"/>
				<input type="submit" class="button-secondary" value="<?php _e('Filter', 'rcp'); ?>"/>
			</form>
			<?php do_action('rcp_members_above_table'); ?>
			<form id="rcp-members-form" action="<?php echo esc_attr( admin_url( 'admin.php?page=rcp-members' ) ); ?>" method="post">
				<div id="rcp-bulk-action-options" class="tablenav top">
					<label for="rcp-bulk-member-action" class="screen-reader-text"><?php _e( 'Select bulk action', 'rcp' ); ?></label>
					<select name="rcp-bulk-action" id="rcp-bulk-member-action">
						<option value="-1"><?php _e( 'Bulk Actions', 'rcp' ); ?></option>
						<option value="mark-active"><?php _e( 'Mark as Active', 'rcp' ); ?></option>
						<option value="mark-expired"><?php _e( 'Mark as Expired', 'rcp' ); ?></option>
						<option value="mark-cancelled"><?php _e( 'Revoke Access', 'rcp' ); ?></option>
					</select>
					<input type="text" class="rcp-datepicker" name="expiration" placeholder="<?php esc_attr_e( 'New Expiration Date', 'rcp' ); ?>" id="rcp-bulk-expiration" value=""/>
					<input type="submit" id="rcp-submit-bulk-action" class="button action" value="<?php _e( 'Apply', 'rcp' ); ?>"/>
				</div>
				<?php wp_nonce_field( 'rcp_bulk_edit_nonce', 'rcp_bulk_edit_nonce' ); ?>
				<table class="wp-list-table widefat">
					<thead>
						<tr>
							<td id="cb" class="manage-column column-cb check-column">
								<label class="screen-reader-text" for="cb-select-all-1"><?php _e( 'Select All', 'rcp' ); ?></label>
								<input id="cb-select-all-1" type="checkbox">
							</td>
							<th scope="col" class="rcp-user-col manage-column column-primary"><?php _e('User', 'rcp'); ?></th>
							<th scope="col" class="rcp-sub-col manage-column"><?php _e('Subscription', 'rcp'); ?></th>
							<th scope="col" class="rcp-status-col manage-column"><?php _e('Status', 'rcp'); ?></th>
							<th scope="col" class="rcp-recurring-col manage-column"><?php _e('Recurring', 'rcp'); ?></th>
							<th scope="col" class="rcp-expiration-col manage-column"><?php _e('Expiration', 'rcp'); ?></th>
							<th scope="col" class="rcp-role-col manage-column"><?php _e('User Role', 'rcp'); ?></th>
							<?php do_action('rcp_members_page_table_header'); ?>
						</tr>
					</thead>
					<tbody id="the-list">
					<?php

					if( isset( $_GET['signup_method'] ) ) {
						$method = $_GET['signup_method'] == 'live' ? 'live' : 'manual';
						$members = get_users( array(
								'meta_key' => 'rcp_signup_method',
								'meta_value' => $method,
								'number' => 999999
							)
						);
						$per_page = 999999;
					} else {
						$members = rcp_get_members( $status, $subscription_id, $offset, $per_page, $order, null, $search );
					}
					if($members) :
						$i = 1;
						foreach( $members as $key => $member ) :

							$rcp_member = new RCP_Member( $member->ID );

							// Show pending expiration date for members with a pending status. See https://github.com/restrictcontentpro/restrict-content-pro/issues/708.
							if ( 'pending' === $status ) {
								$expiration = $rcp_member->get_expiration_date( true, true );
							} else {
								$expiration = $rcp_member->get_expiration_date( true, false );
							}

							?>
							<tr class="rcp_row <?php do_action( 'rcp_member_row_class', $member ); if( rcp_is_odd( $i ) ) { echo ' alternate'; } ?>">
								<th scope="row" class="check-column">
									<input type="checkbox" class="rcp-member-cb" name="member-ids[]" value="<?php echo absint( $member->ID ); ?>"/>
								</th>
								<td class="has-row-actions column-primary" data-colname="<?php _e( 'User', 'rcp' ); ?>">
									<strong>
										<a href="<?php echo esc_url( add_query_arg('edit_member', $member->ID, $current_page) ); ?>" title="<?php _e( 'Edit Member', 'rcp' ); ?>"><?php echo $member->user_login; ?></a>
										<?php if( $member->user_login != $member->user_email ) : ?>
											<?php echo '&nbsp;&ndash;&nbsp;' . $member->user_email; ?>
										<?php endif; ?>
									</strong>
									<?php if( current_user_can( 'rcp_manage_members' ) ) : ?>
										<div class="row-actions">
											<span class="edit">
												<a href="<?php echo esc_url( add_query_arg('edit_member', $member->ID, $current_page) ); ?>"><?php _e( 'Edit Member', 'rcp' ); ?></a>
												<span class="rcp-separator"> | </span>
												<a href="<?php echo esc_url( add_query_arg( 'user_id', $member->ID, admin_url( 'user-edit.php' ) ) ); ?>" title="<?php _e( 'View User\'s Profile', 'rcp' ); ?>"><?php _e( 'Edit User Account', 'rcp' ); ?></a>
											</span>
											<?php if( rcp_can_member_cancel( $member->ID ) ) { ?>
												<span> | <a href="<?php echo wp_nonce_url( add_query_arg('cancel_member', $member->ID, $current_page ), 'rcp-cancel-nonce' ); ?>" class="trash rcp_cancel"><?php _e('Cancel', 'rcp'); ?></a></span>
											<?php } ?>
											<?php if( $switch_to_url = rcp_get_switch_to_url( $member->ID ) ) { ?>
												<span> | <a href="<?php echo esc_url( $switch_to_url ); ?>" class="rcp_switch"><?php _e('Switch to User', 'rcp'); ?></a></span>
											<?php } ?>
											<span class="rcp-separator"> | </span>
											<span class="id rcp-member-id"><?php echo __( 'ID:', 'rcp' ) . ' ' . $member->ID; ?></span>
											<?php do_action( 'rcp_member_row_actions', $member->ID ); ?>
										</div>
									<?php endif; ?>
									<button type="button" class="toggle-row"><span class="screen-reader-text"><?php _e( 'Show more details', 'rcp' ); ?></span></button>
								</td>
								<td data-colname="<?php _e( 'Subscription', 'rcp' ); ?>"><?php echo rcp_get_subscription($member->ID); ?></td>
								<td data-colname="<?php _e( 'Status', 'rcp' ); ?>"><?php echo rcp_print_status($member->ID, false); ?></td>
								<td data-colname="<?php _e( 'Recurring', 'rcp' ); ?>"><?php echo rcp_is_recurring($member->ID) ? __('yes', 'rcp') : __('no', 'rcp'); ?></td>
								<td data-colname="<?php _e( 'Expiration', 'rcp' ); ?>"><?php echo $expiration; ?></td>
								<td data-colname="<?php _e( 'User Role', 'rcp' ); ?>"><?php echo rcp_get_user_role($member->ID); ?></td>
								<?php do_action('rcp_members_page_table_column', $member->ID); ?>
							</tr>
						<?php $i++;
						endforeach;
					else : ?>
						<tr><td colspan="6"><?php _e('No subscribers found', 'rcp'); ?></td></tr>
					<?php endif; ?>
					</tbody>
					<tfoot>
						<tr>
							<td id="cb" class="manage-column column-cb check-column">
								<label class="screen-reader-text" for="cb-select-all-1"><?php _e( 'Select All', 'rcp' ); ?></label>
								<input id="cb-select-all-1" type="checkbox">
							</td>
							<th scope="col" class="rcp-user-col manage-column column-primary"><?php _e('User', 'rcp'); ?></th>
							<th scope="col" class="rcp-sub-col manage-column"><?php _e('Subscription', 'rcp'); ?></th>
							<th scope="col" class="rcp-status-col manage-column"><?php _e('Status', 'rcp'); ?></th>
							<th scope="col" class="rcp-recurring-col manage-column"><?php _e('Recurring', 'rcp'); ?></th>
							<th scope="col" class="rcp-expiration-col manage-column"><?php _e('Expiration', 'rcp'); ?></th>
							<th scope="col" class="rcp-role-col manage-column"><?php _e('User Role', 'rcp'); ?></th>
							<?php do_action('rcp_members_page_table_footer'); ?>
						</tr>
					</tfoot>
				</table>
			</form>
			<?php if ($total_pages > 1 && !isset($_GET['signup_method']) ) : ?>
				<div class="tablenav bottom">
					<div class="tablenav-pages alignright">
						<?php
							$query_string = $_SERVER['QUERY_STRING'];
							$base = 'admin.php?' . remove_query_arg( 'p', $query_string ) . '%_%';
							echo paginate_links( array(
								'base' => $base,
								'format' => '&p=%#%',
								'prev_text' => __('&laquo; Previous', 'rcp' ),
								'next_text' => __('Next &raquo;', 'rcp'),
								'total' => $total_pages,
								'current' => $page,
								'end_size' => 1,
								'mid_size' => 5,
							));
						?>
					</div>
				</div><!--end .tablenav-->
			<?php endif; ?>
			<?php do_action('rcp_members_below_table'); ?>
			<h3>
				<?php _e('Add New Subscription (for existing user)', 'rcp'); ?>
				<span alt="f223" class="rcp-help-tip dashicons dashicons-editor-help" title="<?php _e( 'If you wish to create a brand new account, that may be done from Users &rarr; Add New. <br/><strong>Note</strong>: this will not create a payment profile for the member. That must be done manually through your merchant account.', 'rcp' ); ?>"></span>
			</h3>
			<form id="rcp-add-new-member" action="" method="post">
				<table class="form-table">
					<tbody>
						<tr class="form-field">
							<th scope="row" valign="top">
								<label for="rcp-username"><?php _e('Username', 'rcp'); ?></label>
							</th>
							<td>
								<input type="text" name="user" id="rcp-user" autocomplete="off" class="regular-text rcp-user-search" style="width: 120px;"/>
								<img class="rcp-ajax waiting" src="<?php echo admin_url('images/wpspin_light.gif'); ?>" style="display: none;"/>
								<div id="rcp_user_search_results"></div>
								<p class="description"><?php _e('Begin typing the user name to add a subscription to.', 'rcp'); ?></p>
							</td>
						</tr>
						<tr class="form-field">
							<th scope="row" valign="top">
								<label for="rcp-level"><?php _e('Subscription Level', 'rcp'); ?></label>
							</th>
							<td>
								<select name="level" id="rcp-level">
									<option value="choose"><?php _e('--choose--', 'rcp'); ?></option>
									<?php
										foreach( rcp_get_subscription_levels() as $level) :
											echo '<option value="' . $level->id . '">' . $level->name . '</option>';
										endforeach;
									?>
								</select>
								<span alt="f223" class="rcp-help-tip dashicons dashicons-editor-help" title="<?php _e( 'The subscription level determines the content the member has access to. <strong>Note</strong>: adding a subscription level to a member will not create a payment profile in your merchant account.', 'rcp' ); ?>"></span>
								<p class="description"><?php _e('Choose the subscription level for this user.', 'rcp'); ?></p>
							</td>
						</tr>
						<tr class="form-field">
							<th scope="row" valign="top">
								<label for="rcp-expiration"><?php _e('Expiration date', 'rcp'); ?></label>
							</th>
							<td>
								<input name="expiration" id="rcp-expiration" type="text" style="width: 120px;" class="rcp-datepicker"/>
								<label for="rcp-unlimited">
									<input name="unlimited" id="rcp-unlimited" type="checkbox"/>
									<span class="description"><?php _e( 'Never expires?', 'rcp' ); ?></span>
								</label>
								<p class="description"><?php _e('Enter the expiration date for this user in the format of yyyy-mm-dd', 'rcp'); ?></p>
							</td>
						</tr>
					</tbody>
				</table>
				<p class="submit">
					<input type="hidden" name="rcp-action" value="add-subscription"/>
					<input type="submit" value="<?php _e('Add User Subscription', 'rcp'); ?>" class="button-primary"/>
				</p>
				<?php wp_nonce_field( 'rcp_add_member_nonce', 'rcp_add_member_nonce' ); ?>
			</form>

		<?php endif; ?>

	</div><!--end wrap-->

	<?php
}