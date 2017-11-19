<?php
/**
 * Manage BuddyPress Signups.
 *
 * @since 1.5.0
 */
class BPCLI_Signup extends BPCLI_Component {

	/**
	 * Signup object fields.
	 *
	 * @var array
	 */
	protected $obj_fields = array(
		'signup_id',
		'user_login',
		'user_name',
		'meta',
		'activation_key',
		'registered',
	);

	/**
	 * Add a signup.
	 *
	 * ## OPTIONS
	 *
	 * [--user-login=<user-login>]
	 * : User login for the signup. If none is provided, a random one will be used.
	 *
	 * [--user-email=<user-email>]
	 * : User email for the signup. If none is provided, a random one will be used.
	 *
	 * [--activation-key=<activation-key>]
	 * : Activation key for the signup.
	 *
	 * [--silent=<silent>]
	 * : Silent signup creation.
	 * ---
	 * default: false
	 * ---
	 *
	 * [--porcelain]
	 * : Output only the new signup id.
	 *
	 * ## EXAMPLE
	 *
	 *     $ wp bp signup add --user-login=test_user --user-email=teste@site.com
	 *     Success: Successfully added new user signup (ID #345).
	 */
	public function add( $args, $assoc_args ) {
		$signup_args = array(
			'meta' => '',
		);

		// Add a random user login if none is provided.
		$signup_args['user_login'] = ( isset( $assoc_args['user-login'] ) )
			? $assoc_args['user-login']
			: $this->get_random_login();

		// Sanitize login (random or not).
		$signup_args['user_login'] = preg_replace( '/\s+/', '', sanitize_user( $signup_args['user_login'], true ) );

		// Add a random email if none is provided.
		$signup_args['user_email'] = ( isset( $assoc_args['user-email'] ) )
			? $assoc_args['user-email']
			: $this->get_random_login() . '@example.com';

		// Sanitize email (random or not).
		$signup_args['user_email'] = sanitize_email( $signup_args['user_email'] );

		$signup_args['activation_key'] = ( isset( $assoc_args['activation-key'] ) )
			? $assoc_args['activation-key']
			: wp_generate_password( 32, false );

		$id = BP_Signup::add( $signup_args );

		if ( ! $id ) {
			WP_CLI::error( 'Could not add user signup.' );
		}

		if ( $assoc_args['silent'] ) {
			return;
		}

		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'porcelain' ) ) {
			WP_CLI::line( $id );
		} else {
			WP_CLI::success( sprintf( 'Successfully added new user signup (ID #%d).', $id ) );
		}
	}

	/**
	 * Get a signup.
	 *
	 * ## OPTIONS
	 *
	 * <signup-id>
	 * : Identifier for the signup. Can be a signup ID, an email address, or a user_login.
	 *
	 * [--match-field=<match-field>]
	 * : Field to match the signup-id to. Use if there is ambiguity between, eg, signup ID and user_login.
	 * ---
	 * options:
	 *   - signup_id
	 *   - user_email
	 *   - user_login
	 * ---
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific signup fields.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - ids
	 *   - json
	 *   - count
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp bp signup get 123
	 *     $ wp bp signup get foo@example.com
	 *     $ wp bp signup get 123 --match-field=id
	 */
	public function get( $args, $assoc_args ) {
		$id = $args[0];

		$signup_args = array(
			'number' => 1,
		);

		$signup = $this->get_signup_by_identifier( $id, $assoc_args );

		if ( ! $signup ) {
			WP_CLI::error( 'No signup found by that identifier.' );
		}

		$formatter = $this->get_formatter( $assoc_args );
		$formatter->display_item( $signup );
	}

	/**
	 * Delete a signup.
	 *
	 * ## OPTIONS
	 *
	 * <signup-id>...
	 * : ID or IDs of signup.
	 *
	 * [--yes]
	 * : Answer yes to the confirmation message.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp bp signup delete 520
	 *     Success: Signup deleted.
	 *
	 *     $ wp bp signup delete 55654 54564 --yes
	 *     Success: Signup deleted.
	 */
	public function delete( $args, $assoc_args ) {
		$signup_id = $args[0];

		WP_CLI::confirm( 'Are you sure you want to delete this signup?', $assoc_args );

		parent::_delete( array( $signup_id ), $assoc_args, function( $signup_id ) {
			if ( BP_Signup::delete( array( $signup_id ) ) ) {
				return array( 'success', 'Signup deleted.' );
			} else {
				return array( 'error', 'Could not delete signup.' );
			}
		} );
	}

	/**
	 * Activate a signup.
	 *
	 * ## OPTIONS
	 *
	 * <signup-id>
	 * : Identifier for the signup. Can be a signup ID, an email address, or a user_login.
	 *
	 * ## EXAMPLE
	 *
	 *     $ wp bp signup activate ee48ec319fef3nn4
	 *     Success: Signup activated, new user (ID #545).
	 */
	public function activate( $args, $assoc_args ) {
		$signup = $this->get_signup_by_identifier( $args[0], $assoc_args );

		if ( ! $signup ) {
			WP_CLI::error( 'No signup found by that identifier.' );
		}

		$user_id = bp_core_activate_signup( $signup->activation_key );

		if ( $user_id ) {
			WP_CLI::success( sprintf( 'Signup activated, new user (ID #%d).', $user_id ) );
		} else {
			WP_CLI::error( 'Signup not activated.' );
		}
	}

	/**
	 * Generate random signups.
	 *
	 * ## OPTIONS
	 *
	 * [--count=<number>]
	 * : How many signups to generate.
	 * ---
	 * default: 100
	 * ---
	 *
	 * ## EXAMPLE
	 *
	 *     $ wp bp signup generate --count=50
	 */
	public function generate( $args, $assoc_args ) {
		$notify = \WP_CLI\Utils\make_progress_bar( 'Generating signups', $assoc_args['count'] );

		for ( $i = 0; $i < $assoc_args['count']; $i++ ) {
			$this->add( array(), array(
				'silent' => true,
			) );

			$notify->tick();
		}

		$notify->finish();
	}

	/**
	 * Resend activation e-mail to a newly registered user.
	 *
	 * ## OPTIONS
	 *
	 * <signup-id>
	 * : Identifier for the signup. Can be a signup ID, an email address, or a user_login.
	 *
	 * ## EXAMPLE
	 *
	 *     $ wp bp signup resend test@example.com
	 *     Success: Email sent successfully.
	 *
	 * @alias send
	 */
	public function resend( $args, $assoc_args ) {
		$signup = $this->get_signup_by_identifier( $args[0], $assoc_args );

		if ( ! $signup ) {
			WP_CLI::error( 'No signup found by that identifier.' );
		}

		// Send email.
		BP_Signup::resend( array( $signup->signup_id ) );

		WP_CLI::success( 'Email sent successfully.' );
	}

	/**
	 * Get a list of signups.
	 *
	 * ## OPTIONS
	 *
	 * [--<field>=<value>]
	 * : One or more parameters to pass. See BP_Signup::get()
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - ids
	 *   - count
	 *   - csv
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp bp signup list --format=ids
	 *     $ wp bp signup list --number=100 --format=count
	 *     $ wp bp signup list --number=5 --activation_key=ee48ec319fef3nn4
	 *
	 * @subcommand list
	 */
	public function _list( $_, $assoc_args ) {
		$formatter  = $this->get_formatter( $assoc_args );
		$signups    = BP_Signup::get( $assoc_args );

		if ( 'ids' === $formatter->format ) {
			echo implode( ' ', wp_list_pluck( $signups['signups'], 'signup_id' ) ); // WPCS: XSS ok.
		} elseif ( 'count' === $formatter->format ) {
			WP_CLI::line( $signups['total'] );
		} else {
			$formatter->display_items( $signups['signups'] );
		}
	}

	/**
	 * Look up a signup by the provided identifier.
	 *
	 * @since 1.5.0
	 */
	protected function get_signup_by_identifier( $identifier, $assoc_args ) {
		if ( isset( $assoc_args['match-field'] ) ) {
			switch ( $assoc_args['match-field'] ) {
				case 'signup_id':
					$signup_args['include'] = array( $identifier );
					break;

				case 'user_login':
					$signup_args['user_login'] = $identifier;
					break;

				case 'user_email':
				default:
					$signup_args['usersearch'] = $identifier;
					break;
			}
		} else {
			if ( is_numeric( $identifier ) ) {
				$signup_args['include'] = array( intval( $identifier ) );
			} elseif ( is_email( $identifier ) ) {
				$signup_args['usersearch'] = $identifier;
			} else {
				$signup_args['user_login'] = $identifier;
			}
		}

		$signups = BP_Signup::get( $signup_args );

		$signup = null;
		if ( ! empty( $signups['signups'] ) ) {
			$signup = reset( $signups['signups'] );
		}

		return $signup;
	}
}

WP_CLI::add_command( 'bp signup', 'BPCLI_Signup' );
