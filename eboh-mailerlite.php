<?php
/**
 * Plugin Name:       EBOH MailerLite
 * Plugin URI:        https://github.com/harmrietmeijer/eboh-mailerlite
 * Description:       Nieuwsbrief-signup voor de EBOH-site via MailerLite Connect API. Beheer API-key en groep in Instellingen → EBOH MailerLite; embed met shortcode [eboh_mailerlite_form].
 * Version:           1.0.0
 * Requires at least: 5.5
 * Requires PHP:      7.4
 * Author:            EBOH
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       eboh-mailerlite
 *
 * @package EBOH_MailerLite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EBOH_ML_VERSION', '1.0.0' );
define( 'EBOH_ML_FILE', __FILE__ );
define( 'EBOH_ML_DIR', plugin_dir_path( __FILE__ ) );
define( 'EBOH_ML_URL', plugin_dir_url( __FILE__ ) );
define( 'EBOH_ML_API_ENDPOINT', 'https://connect.mailerlite.com/api/subscribers' );

/* =====================================================================
 * 1. ACTIVATION / DEFAULTS
 * ===================================================================== */

register_activation_hook( __FILE__, 'eboh_ml_on_activate' );
function eboh_ml_on_activate() {
	$defaults = array(
		'api_key'           => '',
		'default_group_id'  => '',
		'button_text'       => 'Aanmelden',
		'placeholder_email' => 'jouw@e-mailadres.nl',
		'success_message'   => 'Bedankt! Check je mailbox om je aanmelding te bevestigen.',
		'error_message'     => 'Er ging iets mis. Probeer het later nog eens of mail ons.',
		'consent_text'      => 'Ik ga akkoord met de verwerking van mijn e-mailadres voor de EBOH-nieuwsbrief.',
	);
	$existing = get_option( 'eboh_ml_settings', array() );
	update_option( 'eboh_ml_settings', wp_parse_args( $existing, $defaults ) );
}

function eboh_ml_get_setting( $key, $fallback = '' ) {
	$opts = get_option( 'eboh_ml_settings', array() );
	return isset( $opts[ $key ] ) && $opts[ $key ] !== '' ? $opts[ $key ] : $fallback;
}

/* =====================================================================
 * 2. ADMIN PAGINA (Instellingen → EBOH MailerLite)
 * ===================================================================== */

add_action( 'admin_menu', 'eboh_ml_register_admin_page' );
function eboh_ml_register_admin_page() {
	add_options_page(
		__( 'EBOH MailerLite', 'eboh-mailerlite' ),
		__( 'EBOH MailerLite', 'eboh-mailerlite' ),
		'manage_options',
		'eboh-mailerlite',
		'eboh_ml_render_admin_page'
	);
}

add_action( 'admin_init', 'eboh_ml_register_settings' );
function eboh_ml_register_settings() {
	register_setting( 'eboh_ml_settings_group', 'eboh_ml_settings', array(
		'sanitize_callback' => 'eboh_ml_sanitize_settings',
	) );
}

function eboh_ml_sanitize_settings( $input ) {
	$out = array();
	$out['api_key']           = isset( $input['api_key'] ) ? trim( sanitize_text_field( $input['api_key'] ) ) : '';
	$out['default_group_id']  = isset( $input['default_group_id'] ) ? trim( sanitize_text_field( $input['default_group_id'] ) ) : '';
	$out['button_text']       = isset( $input['button_text'] ) ? sanitize_text_field( $input['button_text'] ) : 'Aanmelden';
	$out['placeholder_email'] = isset( $input['placeholder_email'] ) ? sanitize_text_field( $input['placeholder_email'] ) : '';
	$out['success_message']   = isset( $input['success_message'] ) ? wp_kses_post( $input['success_message'] ) : '';
	$out['error_message']     = isset( $input['error_message'] ) ? wp_kses_post( $input['error_message'] ) : '';
	$out['consent_text']      = isset( $input['consent_text'] ) ? wp_kses_post( $input['consent_text'] ) : '';
	return $out;
}

function eboh_ml_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$opts = get_option( 'eboh_ml_settings', array() );
	$test_result = '';
	if ( isset( $_POST['eboh_ml_test_nonce'] ) && wp_verify_nonce( $_POST['eboh_ml_test_nonce'], 'eboh_ml_test' ) ) {
		$test_result = eboh_ml_test_connection();
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'EBOH MailerLite', 'eboh-mailerlite' ); ?></h1>
		<p><?php esc_html_e( 'Beheer hier de MailerLite-koppeling voor de nieuwsbrief-signup. Plaats het formulier op de site met de shortcode:', 'eboh-mailerlite' ); ?>
			<code>[eboh_mailerlite_form]</code>
		</p>
		<p>
			<?php esc_html_e( 'Optionele attributen:', 'eboh-mailerlite' ); ?>
			<code>[eboh_mailerlite_form group="123456" button="Schrijf me in"]</code>
		</p>

		<form method="post" action="options.php">
			<?php settings_fields( 'eboh_ml_settings_group' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="eboh_ml_api_key"><?php esc_html_e( 'API key', 'eboh-mailerlite' ); ?></label></th>
					<td>
						<input name="eboh_ml_settings[api_key]" id="eboh_ml_api_key" type="password" autocomplete="off" class="regular-text" value="<?php echo esc_attr( isset( $opts['api_key'] ) ? $opts['api_key'] : '' ); ?>" />
						<p class="description"><?php
							printf(
								/* translators: %s = MailerLite dashboard URL */
								wp_kses_post( __( 'MailerLite Connect API-key. Aan te maken in je MailerLite-dashboard: %s', 'eboh-mailerlite' ) ),
								'<a href="https://dashboard.mailerlite.com/integrations/api" target="_blank" rel="noopener noreferrer">Integrations → API</a>'
							);
						?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="eboh_ml_group"><?php esc_html_e( 'Standaard groep-ID', 'eboh-mailerlite' ); ?></label></th>
					<td>
						<input name="eboh_ml_settings[default_group_id]" id="eboh_ml_group" type="text" class="regular-text" value="<?php echo esc_attr( isset( $opts['default_group_id'] ) ? $opts['default_group_id'] : '' ); ?>" />
						<p class="description"><?php esc_html_e( 'Numerieke groep-ID waar nieuwe inschrijvers in komen. Zichtbaar in de URL van een MailerLite-groep. Laat leeg om alleen subscribers (zonder groep) toe te voegen.', 'eboh-mailerlite' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="eboh_ml_button"><?php esc_html_e( 'Knop-tekst', 'eboh-mailerlite' ); ?></label></th>
					<td>
						<input name="eboh_ml_settings[button_text]" id="eboh_ml_button" type="text" class="regular-text" value="<?php echo esc_attr( isset( $opts['button_text'] ) ? $opts['button_text'] : 'Aanmelden' ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="eboh_ml_placeholder"><?php esc_html_e( 'Placeholder e-mailveld', 'eboh-mailerlite' ); ?></label></th>
					<td>
						<input name="eboh_ml_settings[placeholder_email]" id="eboh_ml_placeholder" type="text" class="regular-text" value="<?php echo esc_attr( isset( $opts['placeholder_email'] ) ? $opts['placeholder_email'] : '' ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="eboh_ml_success"><?php esc_html_e( 'Succes-bericht', 'eboh-mailerlite' ); ?></label></th>
					<td>
						<textarea name="eboh_ml_settings[success_message]" id="eboh_ml_success" class="large-text" rows="2"><?php echo esc_textarea( isset( $opts['success_message'] ) ? $opts['success_message'] : '' ); ?></textarea>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="eboh_ml_error"><?php esc_html_e( 'Foutmelding', 'eboh-mailerlite' ); ?></label></th>
					<td>
						<textarea name="eboh_ml_settings[error_message]" id="eboh_ml_error" class="large-text" rows="2"><?php echo esc_textarea( isset( $opts['error_message'] ) ? $opts['error_message'] : '' ); ?></textarea>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="eboh_ml_consent"><?php esc_html_e( 'AVG-toestemmingstekst', 'eboh-mailerlite' ); ?></label></th>
					<td>
						<textarea name="eboh_ml_settings[consent_text]" id="eboh_ml_consent" class="large-text" rows="2"><?php echo esc_textarea( isset( $opts['consent_text'] ) ? $opts['consent_text'] : '' ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Tekst bij de verplichte checkbox die de gebruiker moet aanvinken voordat een aanmelding wordt verzonden.', 'eboh-mailerlite' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>

		<hr>
		<h2><?php esc_html_e( 'Verbinding testen', 'eboh-mailerlite' ); ?></h2>
		<form method="post">
			<?php wp_nonce_field( 'eboh_ml_test', 'eboh_ml_test_nonce' ); ?>
			<p>
				<button type="submit" class="button"><?php esc_html_e( 'Test API-koppeling', 'eboh-mailerlite' ); ?></button>
			</p>
		</form>
		<?php if ( $test_result ) : ?>
			<div class="notice notice-<?php echo esc_attr( $test_result['ok'] ? 'success' : 'error' ); ?> inline">
				<p><?php echo esc_html( $test_result['message'] ); ?></p>
			</div>
		<?php endif; ?>
	</div>
	<?php
}

/* =====================================================================
 * 3. API CLIENT
 * ===================================================================== */

/**
 * Voeg een subscriber toe aan MailerLite. Returns array met success/error info.
 */
function eboh_ml_subscribe( $email, $group_id = '' ) {
	$api_key = eboh_ml_get_setting( 'api_key' );
	if ( empty( $api_key ) ) {
		return array(
			'ok'      => false,
			'code'    => 'no_api_key',
			'message' => __( 'API-key ontbreekt in EBOH MailerLite-instellingen.', 'eboh-mailerlite' ),
		);
	}
	if ( ! is_email( $email ) ) {
		return array(
			'ok'      => false,
			'code'    => 'invalid_email',
			'message' => __( 'Geen geldig e-mailadres.', 'eboh-mailerlite' ),
		);
	}

	$body = array( 'email' => $email );
	if ( ! empty( $group_id ) ) {
		$body['groups'] = array( (string) $group_id );
	}

	$response = wp_remote_post( EBOH_ML_API_ENDPOINT, array(
		'timeout' => 12,
		'headers' => array(
			'Content-Type'  => 'application/json',
			'Accept'        => 'application/json',
			'Authorization' => 'Bearer ' . $api_key,
		),
		'body'    => wp_json_encode( $body ),
	) );

	if ( is_wp_error( $response ) ) {
		return array(
			'ok'      => false,
			'code'    => 'http_error',
			'message' => $response->get_error_message(),
		);
	}

	$code = wp_remote_retrieve_response_code( $response );
	$data = json_decode( wp_remote_retrieve_body( $response ), true );

	// 200 = bestaande update, 201 = nieuwe subscriber, 202 = pending double opt-in
	if ( in_array( $code, array( 200, 201, 202 ), true ) ) {
		return array(
			'ok'   => true,
			'code' => 'subscribed',
			'data' => $data,
		);
	}

	$msg = isset( $data['message'] ) ? $data['message'] : sprintf(
		/* translators: %d = HTTP-code */
		__( 'MailerLite gaf HTTP-code %d terug.', 'eboh-mailerlite' ),
		$code
	);

	return array(
		'ok'      => false,
		'code'    => 'api_error',
		'message' => $msg,
		'status'  => $code,
	);
}

/**
 * Verbindingscheck voor de admin-pagina: probeert API-key te valideren via een
 * lightweight GET op /api/groups (geen schrijfactie).
 */
function eboh_ml_test_connection() {
	$api_key = eboh_ml_get_setting( 'api_key' );
	if ( empty( $api_key ) ) {
		return array( 'ok' => false, 'message' => __( 'Geen API-key ingesteld.', 'eboh-mailerlite' ) );
	}
	$response = wp_remote_get( 'https://connect.mailerlite.com/api/groups?limit=1', array(
		'timeout' => 10,
		'headers' => array(
			'Accept'        => 'application/json',
			'Authorization' => 'Bearer ' . $api_key,
		),
	) );
	if ( is_wp_error( $response ) ) {
		return array( 'ok' => false, 'message' => $response->get_error_message() );
	}
	$code = wp_remote_retrieve_response_code( $response );
	if ( $code === 200 ) {
		return array( 'ok' => true, 'message' => __( 'Verbinding met MailerLite geslaagd.', 'eboh-mailerlite' ) );
	}
	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	$msg  = isset( $body['message'] ) ? $body['message'] : sprintf( __( 'HTTP %d', 'eboh-mailerlite' ), $code );
	return array( 'ok' => false, 'message' => $msg );
}

/* =====================================================================
 * 4. SHORTCODE
 * ===================================================================== */

add_shortcode( 'eboh_mailerlite_form', 'eboh_ml_shortcode_form' );
function eboh_ml_shortcode_form( $atts ) {
	$atts = shortcode_atts( array(
		'group'  => '',
		'button' => '',
		'title'  => '',
	), $atts, 'eboh_mailerlite_form' );

	$group_id    = $atts['group'] !== '' ? $atts['group'] : eboh_ml_get_setting( 'default_group_id' );
	$button_text = $atts['button'] !== '' ? $atts['button'] : eboh_ml_get_setting( 'button_text', 'Aanmelden' );
	$placeholder = eboh_ml_get_setting( 'placeholder_email', 'jouw@e-mailadres.nl' );
	$consent     = eboh_ml_get_setting( 'consent_text' );
	$title       = $atts['title'];

	// Markeer dat we de form-assets nodig hebben op deze pagina.
	wp_enqueue_style( 'eboh-ml-form' );
	wp_enqueue_script( 'eboh-ml-form' );

	ob_start();
	?>
	<div class="eboh-ml-form" data-group="<?php echo esc_attr( $group_id ); ?>">
		<?php if ( $title ) : ?>
			<h3 class="eboh-ml-form__title"><?php echo esc_html( $title ); ?></h3>
		<?php endif; ?>
		<form class="eboh-ml-form__form" novalidate>
			<?php wp_nonce_field( 'eboh_ml_subscribe', 'eboh_ml_nonce' ); ?>
			<div class="eboh-ml-form__row">
				<label class="eboh-ml-form__label screen-reader-text" for="eboh-ml-email-<?php echo esc_attr( uniqid() ); ?>"><?php esc_html_e( 'E-mailadres', 'eboh-mailerlite' ); ?></label>
				<input class="eboh-ml-form__email" type="email" name="email" placeholder="<?php echo esc_attr( $placeholder ); ?>" required autocomplete="email" />
				<button class="eboh-ml-form__submit" type="submit"><?php echo esc_html( $button_text ); ?></button>
			</div>
			<?php if ( $consent ) : ?>
				<label class="eboh-ml-form__consent">
					<input class="eboh-ml-form__consent-input" type="checkbox" name="consent" required />
					<span><?php echo wp_kses_post( $consent ); ?></span>
				</label>
			<?php endif; ?>
			<div class="eboh-ml-form__feedback" role="status" aria-live="polite"></div>
		</form>
	</div>
	<?php
	return ob_get_clean();
}

/* =====================================================================
 * 5. ASSETS (CSS + JS)
 * ===================================================================== */

add_action( 'wp_enqueue_scripts', 'eboh_ml_register_assets' );
function eboh_ml_register_assets() {
	wp_register_style(
		'eboh-ml-form',
		EBOH_ML_URL . 'assets/form.css',
		array(),
		EBOH_ML_VERSION
	);
	wp_register_script(
		'eboh-ml-form',
		EBOH_ML_URL . 'assets/form.js',
		array(),
		EBOH_ML_VERSION,
		true
	);
	wp_localize_script( 'eboh-ml-form', 'EBOH_ML', array(
		'ajax_url' => admin_url( 'admin-ajax.php' ),
		'action'   => 'eboh_ml_subscribe',
		'success'  => eboh_ml_get_setting( 'success_message', 'Bedankt!' ),
		'error'    => eboh_ml_get_setting( 'error_message', 'Er ging iets mis.' ),
	) );
}

/* =====================================================================
 * 6. AJAX HANDLER
 * ===================================================================== */

add_action( 'wp_ajax_eboh_ml_subscribe', 'eboh_ml_ajax_subscribe' );
add_action( 'wp_ajax_nopriv_eboh_ml_subscribe', 'eboh_ml_ajax_subscribe' );

function eboh_ml_ajax_subscribe() {
	check_ajax_referer( 'eboh_ml_subscribe', 'nonce' );

	$email    = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	$group_id = isset( $_POST['group'] ) ? sanitize_text_field( wp_unslash( $_POST['group'] ) ) : '';
	$consent  = ! empty( $_POST['consent'] );

	// Honeypot-veld 'hp' moet leeg blijven (zie JS).
	if ( ! empty( $_POST['hp'] ) ) {
		wp_send_json_success( array( 'message' => eboh_ml_get_setting( 'success_message' ) ) );
		return;
	}

	$consent_required = (bool) eboh_ml_get_setting( 'consent_text' );
	if ( $consent_required && ! $consent ) {
		wp_send_json_error( array( 'message' => __( 'Vink eerst de toestemming aan.', 'eboh-mailerlite' ) ), 400 );
	}

	if ( ! is_email( $email ) ) {
		wp_send_json_error( array( 'message' => __( 'Vul een geldig e-mailadres in.', 'eboh-mailerlite' ) ), 400 );
	}

	$result = eboh_ml_subscribe( $email, $group_id );
	if ( $result['ok'] ) {
		wp_send_json_success( array( 'message' => eboh_ml_get_setting( 'success_message' ) ) );
	}

	wp_send_json_error( array(
		'message' => eboh_ml_get_setting( 'error_message' ),
		'detail'  => $result['message'],
	), 500 );
}
