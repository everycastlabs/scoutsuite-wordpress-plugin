<?php
/**
 * Public enquiry form: shortcode rendering and submission handling.
 *
 * Distinct from the waiting list form (class-scoutsuite-waitlist-form.php):
 * this is a general "get in touch" form, not a request to join a specific
 * section, and it works against a Group, District or County Org ID alike —
 * unlike the waiting list, which only ever posts to a single Group.
 *
 * Same plain-HTML, no-JavaScript-required pattern as the waiting list form:
 * posts to admin-post.php, validates and calls the Scout Suite API server
 * side, stores the outcome in a short lived transient and redirects back.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ScoutSuite_Waitlist_Enquiry {

	const NONCE_ACTION = 'scoutsuite_enquiry_submit';
	const POST_ACTION  = 'scoutsuite_enquiry_submit';

	public function __construct() {
		add_shortcode( 'scoutsuite_enquiry', array( __CLASS__, 'render_form' ) );

		// Handle submissions from both logged out and logged in visitors.
		add_action( 'admin_post_nopriv_' . self::POST_ACTION, array( $this, 'handle_submission' ) );
		add_action( 'admin_post_' . self::POST_ACTION, array( $this, 'handle_submission' ) );
	}

	/**
	 * Render the form. Used by the shortcode and as the block render
	 * callback, so it must be static and self contained.
	 *
	 * @return string
	 */
	public static function render_form() {
		$options = scoutsuite_waitlist_get_options();

		wp_enqueue_style( 'scoutsuite-waitlist' );

		if ( '' === trim( $options['org_id'] ) ) {
			// Tell editors what is missing; show nothing to visitors.
			if ( current_user_can( 'manage_options' ) ) {
				return '<div class="sswl-notice sswl-notice-error">'
					. esc_html__( 'Scout Suite: set your Org ID under Settings, Scout Suite. Only administrators see this message.', 'scoutsuite-waitlist' )
					. '</div>';
			}
			return '';
		}

		$feedback = self::consume_feedback();
		$old      = isset( $feedback['old'] ) && is_array( $feedback['old'] ) ? $feedback['old'] : array();
		$errors   = isset( $feedback['errors'] ) && is_array( $feedback['errors'] ) ? $feedback['errors'] : array();

		// After a successful submission show only the success message.
		if ( isset( $feedback['status'] ) && 'success' === $feedback['status'] ) {
			return '<div id="scoutsuite-enquiry" class="sswl-notice sswl-notice-success" role="status">'
				. esc_html( $options['enquiry_success_message'] )
				. '</div>';
		}

		ob_start();
		?>
		<div id="scoutsuite-enquiry" class="sswl-wrap">
			<?php if ( isset( $feedback['status'] ) && 'error' === $feedback['status'] && ! empty( $feedback['message'] ) ) : ?>
				<div class="sswl-notice sswl-notice-error" role="alert">
					<?php echo esc_html( $feedback['message'] ); ?>
				</div>
			<?php endif; ?>

			<form class="sswl-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::POST_ACTION ); ?>" />
				<?php wp_nonce_field( self::NONCE_ACTION ); ?>
				<input type="hidden" name="sse_redirect" value="<?php echo esc_url( self::current_url() ); ?>" />

				<?php // Honeypot: hidden from people, tempting to bots. Submissions that fill it are dropped. ?>
				<p class="sswl-hp" aria-hidden="true">
					<label for="sse_website">Website</label>
					<input type="text" id="sse_website" name="sse_website" value="" tabindex="-1" autocomplete="off" />
				</p>

				<fieldset class="sswl-fieldset">
					<legend><?php esc_html_e( 'Get in touch', 'scoutsuite-waitlist' ); ?></legend>

					<p class="sswl-field">
						<label for="sse_name"><?php esc_html_e( 'Your name', 'scoutsuite-waitlist' ); ?> <span class="sswl-required" aria-hidden="true">*</span></label>
						<input type="text" id="sse_name" name="sse_name" required autocomplete="name"
							value="<?php echo esc_attr( self::old_value( $old, 'name' ) ); ?>" />
						<?php self::field_error( $errors, 'name' ); ?>
					</p>

					<div class="sswl-row sswl-row-2col">
						<p class="sswl-field">
							<label for="sse_email"><?php esc_html_e( 'Email address', 'scoutsuite-waitlist' ); ?></label>
							<input type="email" id="sse_email" name="sse_email" autocomplete="email"
								value="<?php echo esc_attr( self::old_value( $old, 'email' ) ); ?>" />
							<?php self::field_error( $errors, 'email' ); ?>
						</p>
						<p class="sswl-field">
							<label for="sse_phone"><?php esc_html_e( 'Phone number', 'scoutsuite-waitlist' ); ?></label>
							<input type="tel" id="sse_phone" name="sse_phone" autocomplete="tel"
								value="<?php echo esc_attr( self::old_value( $old, 'phone' ) ); ?>" />
						</p>
					</div>
					<p class="sswl-field-hint description">
						<?php esc_html_e( 'Please provide an email address or phone number so we can get back to you.', 'scoutsuite-waitlist' ); ?>
						<?php self::field_error( $errors, 'contact_method' ); ?>
					</p>

					<p class="sswl-field">
						<label for="sse_message"><?php esc_html_e( 'How can we help?', 'scoutsuite-waitlist' ); ?></label>
						<textarea id="sse_message" name="sse_message" rows="4"><?php echo esc_textarea( self::old_value( $old, 'message' ) ); ?></textarea>
					</p>
				</fieldset>

				<div class="sswl-consent">
					<?php if ( '' !== trim( $options['privacy_notice'] ) ) : ?>
						<p class="sswl-privacy-notice"><?php echo esc_html( $options['privacy_notice'] ); ?></p>
					<?php endif; ?>
					<p class="sswl-field sswl-field-checkbox">
						<label>
							<input type="checkbox" name="sse_consent" value="1" required <?php checked( self::old_value( $old, 'consent' ), '1' ); ?> />
							<?php echo esc_html( $options['consent_label'] ); ?> <span class="sswl-required" aria-hidden="true">*</span>
						</label>
						<?php self::field_error( $errors, 'consent' ); ?>
					</p>
				</div>

				<p class="sswl-submit">
					<button type="submit" class="sswl-button"><?php esc_html_e( 'Send enquiry', 'scoutsuite-waitlist' ); ?></button>
				</p>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Handle the admin-post.php submission.
	 */
	public function handle_submission() {
		$redirect = isset( $_POST['sse_redirect'] ) ? esc_url_raw( wp_unslash( $_POST['sse_redirect'] ) ) : '';
		if ( '' === $redirect || 0 !== strpos( $redirect, home_url() ) ) {
			$redirect = home_url( '/' );
		}

		// Nonce check. On failure send the visitor back with a retry message.
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ), self::NONCE_ACTION ) ) {
			$this->redirect_with_feedback(
				$redirect,
				array(
					'status'  => 'error',
					'message' => __( 'Your session expired. Please try submitting the form again.', 'scoutsuite-waitlist' ),
				)
			);
		}

		// Honeypot: pretend success so bots learn nothing, store nothing.
		if ( ! empty( $_POST['sse_website'] ) ) {
			$this->redirect_with_feedback( $redirect, array( 'status' => 'success' ) );
		}

		// Sanitise every input.
		$input = array(
			'name'    => isset( $_POST['sse_name'] ) ? sanitize_text_field( wp_unslash( $_POST['sse_name'] ) ) : '',
			'email'   => isset( $_POST['sse_email'] ) ? sanitize_email( wp_unslash( $_POST['sse_email'] ) ) : '',
			'phone'   => isset( $_POST['sse_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['sse_phone'] ) ) : '',
			'message' => isset( $_POST['sse_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['sse_message'] ) ) : '',
			'consent' => isset( $_POST['sse_consent'] ) ? '1' : '',
		);

		// Validate. Mirrors the Scout Suite API contract: name required, and
		// at least one of email or phone. Consent is required by this
		// plugin for GDPR.
		$errors = array();

		if ( '' === $input['name'] ) {
			$errors['name'] = __( 'Please enter your name.', 'scoutsuite-waitlist' );
		}
		if ( '' !== $input['email'] && ! is_email( $input['email'] ) ) {
			$errors['email'] = __( 'Please enter a valid email address.', 'scoutsuite-waitlist' );
		}
		if ( '' === $input['email'] && '' === $input['phone'] ) {
			$errors['contact_method'] = __( 'Please provide an email address or phone number.', 'scoutsuite-waitlist' );
		}
		if ( '1' !== $input['consent'] ) {
			$errors['consent'] = __( 'Please tick the consent box so we can process your enquiry.', 'scoutsuite-waitlist' );
		}

		if ( ! empty( $errors ) ) {
			$this->redirect_with_feedback(
				$redirect,
				array(
					'status'  => 'error',
					'message' => __( 'Please check the highlighted fields and try again.', 'scoutsuite-waitlist' ),
					'errors'  => $errors,
					'old'     => $input,
				)
			);
		}

		// Build the API payload. Optional fields are only sent when filled in.
		$payload = array(
			'name'   => $input['name'],
			'source' => 'wordpress',
		);
		if ( '' !== $input['email'] ) {
			$payload['email'] = $input['email'];
		}
		if ( '' !== $input['phone'] ) {
			$payload['phone'] = $input['phone'];
		}
		if ( '' !== $input['message'] ) {
			$payload['message'] = $input['message'];
		}

		$api    = scoutsuite_waitlist_get_api();
		$result = $api->submit_enquiry( $payload );

		if ( $result['success'] ) {
			$this->redirect_with_feedback( $redirect, array( 'status' => 'success' ) );
		}

		$this->redirect_with_feedback(
			$redirect,
			array(
				'status'  => 'error',
				'message' => $result['message'],
				'old'     => $input,
			)
		);
	}

	/**
	 * Store feedback in a transient and redirect back to the form. The URL
	 * only ever carries a random token, never personal data.
	 *
	 * @param string $redirect Destination URL.
	 * @param array  $feedback Status, message, field errors, old input.
	 */
	private function redirect_with_feedback( $redirect, $feedback ) {
		// Lowercase so the token survives sanitize_key() when read back.
		$token = strtolower( wp_generate_password( 16, false, false ) );
		set_transient( 'sse_feedback_' . $token, $feedback, 5 * MINUTE_IN_SECONDS );

		$url = add_query_arg( 'sse', rawurlencode( $token ), $redirect ) . '#scoutsuite-enquiry';
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Read and delete the feedback transient referenced in the URL, if any.
	 *
	 * @return array
	 */
	private static function consume_feedback() {
		if ( empty( $_GET['sse'] ) ) {
			return array();
		}

		$token = sanitize_key( wp_unslash( $_GET['sse'] ) );
		if ( '' === $token ) {
			return array();
		}

		$feedback = get_transient( 'sse_feedback_' . $token );
		delete_transient( 'sse_feedback_' . $token );

		return is_array( $feedback ) ? $feedback : array();
	}

	/**
	 * URL of the page currently being rendered, used as the redirect target.
	 * The sse token from any previous submission is stripped.
	 *
	 * @return string
	 */
	private static function current_url() {
		$permalink = get_permalink();
		if ( $permalink ) {
			return remove_query_arg( 'sse', $permalink );
		}

		$request = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		return remove_query_arg( 'sse', home_url( $request ) );
	}

	/**
	 * Previously submitted value for repopulating the form after an error.
	 *
	 * @param array  $old Old input array.
	 * @param string $key Field key.
	 * @return string
	 */
	private static function old_value( $old, $key ) {
		return isset( $old[ $key ] ) && is_string( $old[ $key ] ) ? $old[ $key ] : '';
	}

	/**
	 * Print a field level validation message when one exists.
	 *
	 * @param array  $errors Field error map.
	 * @param string $key    Field key.
	 */
	private static function field_error( $errors, $key ) {
		if ( ! empty( $errors[ $key ] ) ) {
			echo '<span class="sswl-field-error">' . esc_html( $errors[ $key ] ) . '</span>';
		}
	}
}
