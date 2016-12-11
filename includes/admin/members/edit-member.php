<?php
if( isset( $_GET['edit_member'] ) ) {
	$member_id = absint( $_GET['edit_member'] );
} elseif( isset( $_GET['view_member'] ) ) {
	$member_id = absint( $_GET['view_member'] );
}
$member = new RCP_Member( $member_id );
?>
<h2>
	<?php _e( 'Edit Member:', 'rcp' ); echo ' ' . $member->display_name; ?>
</h2>
<?php if( $switch_to_url = rcp_get_switch_to_url( $member->ID ) ) { ?>
	<a href="<?php echo esc_url( $switch_to_url ); ?>" class="rcp_switch"><?php _e('Switch to User', 'rcp'); ?></a>
<?php } ?>
<form id="rcp-edit-member" action="" method="post">
	<table class="form-table">
		<tbody>
			<?php do_action( 'rcp_edit_member_before', $member->ID ); ?>
			<tr valign="top">
				<th scope="row" valign="top">
					<label for="rcp-status"><?php _e( 'Status', 'rcp' ); ?></label>
				</th>
				<td>
					<select name="status" id="rcp-status">
						<?php
							$statuses = array( 'active', 'expired', 'cancelled', 'pending', 'free' );
							$current_status = rcp_get_status( $member->ID );
							foreach( $statuses as $status ) :
								echo '<option value="' . esc_attr( $status ) .  '"' . selected( $status, rcp_get_status( $member->ID ), false ) . '>' . ucwords( $status ) . '</option>';
							endforeach;
						?>
					</select>
					<span alt="f223" class="rcp-help-tip dashicons dashicons-editor-help" title="<?php _e( 'An Active status is required to access paid content. Members with a status of Cancelled may continue to access paid content until the expiration date on their account is reached.', 'rcp' ); ?>"></span>
					<p class="description"><?php _e( 'The status of this user\'s subscription', 'rcp' ); ?></p>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row" valign="top">
					<label for="rcp-level"><?php _e( 'Subscription Level', 'rcp' ); ?></label>
				</th>
				<td>
					<select name="level" id="rcp-level">
						<?php
							foreach( rcp_get_subscription_levels( 'all' ) as $key => $level) :
								echo '<option value="' . esc_attr( absint( $level->id ) ) . '"' . selected( $level->name, $member->get_subscription_name(), false ) . '>' . esc_html( $level->name ) . '</option>';
							endforeach;
						?>
					</select>
					<p class="description"><?php _e( 'Choose the subscription level for this user', 'rcp' ); ?></p>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row" valign="top">
					<label for="rcp-key"><?php _e( 'Subscription Key', 'rcp' ); ?></label>
				</th>
				<td>
					<input id="rcp-key" type="text" style="width: 200px;" value="<?php echo esc_attr( $member->get_subscription_key() ); ?>" disabled="disabled"/>
					<span alt="f223" class="rcp-help-tip dashicons dashicons-editor-help" title="<?php _e( 'This key is used for reference purposes and may be shown on payment and subscription records in your merchant accounts.', 'rcp' ); ?>"></span>
					<p class="description"><?php _e( 'The member\'s subscription key. This cannot be changed.', 'rcp' ); ?></p>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row" valign="top">
					<label for="rcp-expiration"><?php _e( 'Expiration date', 'rcp' ); ?></label>
				</th>
				<td>
					<?php
					$expiration_date = $member->get_expiration_date( false );
					if( 'none' != $expiration_date ) {
						$expiration_date = date( 'Y-m-d', strtotime( $expiration_date, current_time( 'timestamp' ) ) );
					}
					?>
					<input name="expiration" id="rcp-expiration" type="text" style="width: 120px;" class="rcp-datepicker" value="<?php echo esc_attr( $expiration_date ); ?>"/>
					<label for="rcp-unlimited">
						<input name="unlimited" id="rcp-unlimited" type="checkbox"<?php checked( get_user_meta( $member->ID, 'rcp_expiration', true ), 'none' ); ?>/>
						<span class="description"><?php _e( 'Never expires?', 'rcp' ); ?></span>
					</label>
					<span alt="f223" class="rcp-help-tip dashicons dashicons-editor-help" title="<?php _e( 'This is the date the member will lose access to content if their membership is not renewed.', 'rcp' ); ?>"></span>
					<p class="description"><?php _e( 'Enter the expiration date for this user in the format of yyyy-mm-dd', 'rcp' ); ?></p>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row" valign="top">
					<label for="rcp-payment-profile-id"><?php _e( 'Payment Profile ID', 'rcp' ); ?></label>
				</th>
				<td>
					<input name="payment-profile-id" id="rcp-payment-profile-id" type="text" style="width: 200px;" value="<?php echo esc_attr( $member->get_payment_profile_id() ); ?>"/>
					<p class="description"><?php _e( 'This is the customer\'s payment profile ID in the payment processor', 'rcp' ); ?></p>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row" valign="top">
					<?php _e( 'Recurring', 'rcp' ); ?>
				</th>
				<td>
					<label for="rcp-recurring">
						<input name="recurring" id="rcp-recurring" type="checkbox" value="1" <?php checked( 1, rcp_is_recurring( $member->ID ) ); ?>/>
						<?php _e( 'Is this user\'s subscription recurring?', 'rcp' ); ?>
					</label>
					<span alt="f223" class="rcp-help-tip dashicons dashicons-editor-help" title="<?php _e( 'If checked, this member has a recurring subscription. Only customers with recurring memberships will be given the option to cancel their membership on their subscription details page.', 'rcp' ); ?>"></span>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row" valign="top">
					<?php _e( 'Trialing', 'rcp' ); ?>
				</th>
				<td>
					<label for="rcp-trialing">
						<input name="trialing" id="rcp-trialing" type="checkbox" value="1" <?php checked( 1, rcp_is_trialing( $member->ID ) ); ?>/>
						<?php _e( 'Does this user have a trial membership?', 'rcp' ); ?>
					</label>
					<span alt="f223" class="rcp-help-tip dashicons dashicons-editor-help" title="<?php _e( 'Members are limited to a single trial membership. Once a trial has been used, the member may not sign up for another trial membership.', 'rcp' ); ?>"></span>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row" valign="top">
					<?php _e( 'Sign Up Method', 'rcp' ); ?>
				</th>
				<td>
					<?php $method = get_user_meta( $member->ID, 'rcp_signup_method', true ) ? get_user_meta( $member->ID, 'rcp_signup_method', true ) : 'live';?>
					<select name="signup_method" id="rcp-signup-method">
						<option value="live" <?php selected( $method, 'live' ); ?>><?php _e( 'User Signup', 'rcp' ); ?>
						<option value="manual" <?php selected( $method, 'manual' ); ?>><?php _e( 'Added by admin manually', 'rcp' ); ?>
					</select>
					<p class="description"><?php _e( 'Was this a real signup or a membership given to the user', 'rcp' ); ?></p>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row" valign="top">
					<label for="rcp-notes"><?php _e( 'User Notes', 'rcp' ); ?></label>
				</th>
				<td>
					<textarea name="notes" id="rcp-notes" class="large-text" rows="10" cols="50"><?php echo esc_textarea( get_user_meta( $member->ID, 'rcp_notes', true ) ); ?></textarea>
					<p class="description"><?php _e( 'Use this area to record notes about this user if needed', 'rcp' ); ?></p>
				</td>
			</tr>
			<tr class="form-field">
				<th scope="row" valign="top">
					<?php _e( 'Discount codes used', 'rcp' ); ?>
				</th>
				<td>
					<?php
					$discounts = get_user_meta( $member->ID, 'rcp_user_discounts', true );
					if( $discounts ) {
						foreach( $discounts as $discount ) {
							if( is_string( $discount ) ) {
								echo $discount . '<br/>';
							}
						}
					} else {
						_e( 'None', 'rcp' );
					}
					?>
				</td>
			</tr>
			<tr class="form-field">
				<td colspan="2" scope="row" valign="top" style="padding: 20px 10px 20px 0;">
					<h4><?php _e( 'Payments', 'rcp' ); ?></h4>
					<?php echo rcp_print_user_payments_formatted( $member->ID ); ?>
				</td>
			</tr>
			<?php do_action( 'rcp_edit_member_after', $member->ID ); ?>
		</tbody>
	</table>
	<p class="submit">
		<input type="hidden" name="rcp-action" value="edit-member"/>
		<input type="hidden" name="user" value="<?php echo absint( urldecode( $_GET['edit_member'] ) ); ?>"/>
		<input type="submit" value="<?php _e( 'Update User Subscription', 'rcp' ); ?>" class="button-primary"/>
	</p>
	<?php wp_nonce_field( 'rcp_edit_member_nonce', 'rcp_edit_member_nonce' ); ?>
</form>
