<?php
/**
 * What an administrator can do when the email test says the mail lands in
 * spam or never arrives.
 *
 * Both fixes are about the site's hosting and domain, not about this plugin:
 * the plugin only runs the test and shows the result. So this file does not
 * change anything - it describes two ways out and hands the admin the links.
 *
 *   1. Free setup: the domain's SPF, DKIM and DMARC records, one guide (with a
 *      video and an AI prompt) per record, on whitestudio.team.
 *   2. A sending service with a free plan whose official WordPress plugin can
 *      be installed from the WordPress plugin directory in one click.
 *
 * The services list is filterable (emsfb_email_sending_services), so a host or
 * an agency can add its own service or remove one.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Guides on whitestudio.team, as paths under /document/. The German and Arabic
 * sites carry the same slugs; the admin script picks the subdomain, exactly as
 * Link_emsFormBuilder() does for every other guide.
 *
 * @return array<string,string> key => slug
 */
function emsfb_email_next_steps_docs_efb() {
	$docs = array(
		'overview' => 'why-form-emails-go-to-spam-hosting-not-plugin',
		'spf'      => 'set-up-spf-record-for-wordpress-email',
		'dkim'     => 'set-up-dkim-email-signature-for-wordpress',
		'dmarc'    => 'set-up-dmarc-policy-for-wordpress-email',
		'services' => 'free-email-sending-services-for-wordpress-forms',
		'smtp'     => 'send-email-using-smtp-plugin',
	);
	$docs = apply_filters( 'emsfb_email_next_steps_docs', $docs );
	return is_array( $docs ) ? array_map( 'sanitize_title', array_filter( $docs, 'is_string' ) ) : array();
}

/**
 * Sending services with a free plan and an official plugin on wordpress.org.
 *
 * The free-plan figures are the services' own, as published when this list was
 * written; the note tells the admin to check the current plan before relying
 * on it. A plugin that is already on the site is reported as installed rather
 * than offered again.
 *
 * @return array<int,array<string,mixed>>
 */
function emsfb_email_sending_services_efb() {
	$services = array(
		array(
			'id'   => 'easy-wp-smtp',
			'name' => 'Easy WP SMTP',
			'slug' => 'easy-wp-smtp',
			'note' => __( 'Connects Gmail, Outlook, Brevo and almost any SMTP service to WordPress. Free.', 'easy-form-builder' ),
			'site' => 'https://easywpsmtp.com/',
		),
		array(
			'id'   => 'brevo',
			'name' => 'Brevo',
			'slug' => 'mailin',
			'note' => __( 'Free plan with a daily sending limit; official plugin, sign in and connect.', 'easy-form-builder' ),
			'site' => 'https://www.brevo.com/',
		),
		array(
			'id'   => 'smtp2go',
			'name' => 'SMTP2GO',
			'slug' => 'smtp2go',
			'note' => __( 'Free plan with a monthly sending limit; official plugin, one API key.', 'easy-form-builder' ),
			'site' => 'https://www.smtp2go.com/',
		),
		array(
			'id'   => 'mailjet',
			'name' => 'Mailjet',
			'slug' => 'mailjet-for-wordpress',
			'note' => __( 'Free plan with a daily sending limit; official plugin, one API key.', 'easy-form-builder' ),
			'site' => 'https://www.mailjet.com/',
		),
		array(
			'id'   => 'postmark',
			'name' => 'Postmark',
			'slug' => 'postmark-approved-wordpress-plugin',
			'note' => __( 'Small free plan for transactional email; official plugin, one API token.', 'easy-form-builder' ),
			'site' => 'https://postmarkapp.com/',
		),
	);

	$services = apply_filters( 'emsfb_email_sending_services', $services );
	if ( ! is_array( $services ) ) {
		return array();
	}

	$out = array();
	foreach ( $services as $service ) {
		if ( ! is_array( $service ) || empty( $service['slug'] ) || empty( $service['name'] ) ) {
			continue;
		}
		$slug = sanitize_key( (string) $service['slug'] );
		if ( '' === $slug ) {
			continue;
		}
		$out[] = array(
			'id'        => sanitize_key( (string) ( $service['id'] ?? $slug ) ),
			'name'      => sanitize_text_field( (string) $service['name'] ),
			'slug'      => $slug,
			'note'      => sanitize_text_field( (string) ( $service['note'] ?? '' ) ),
			'site'      => isset( $service['site'] ) ? esc_url_raw( (string) $service['site'], array( 'https' ) ) : '',
			'installed' => is_dir( WP_PLUGIN_DIR . '/' . $slug ),
		);
	}
	return $out;
}

/**
 * Everything the result screen needs, for the current administrator.
 *
 * @return array{can_install:bool,install_base:string,plugins_url:string,docs:array<string,string>,services:array<int,array<string,mixed>>}
 */
function emsfb_email_next_steps_efb() {
	$file_mods_allowed = ! ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS );
	$can_install       = $file_mods_allowed && current_user_can( 'install_plugins' );

	return array(
		'can_install'  => $can_install,
		// The plugin directory's own information screen for a slug; its
		// "Install Now" button does the install, so no code runs here.
		// On multisite a subsite's plugin-install.php redirects to the network
		// screen and drops the query string, so link the network screen directly.
		'install_base' => $can_install ? ( is_multisite() ? network_admin_url( 'plugin-install.php?tab=plugin-information&plugin=' ) : admin_url( 'plugin-install.php?tab=plugin-information&plugin=' ) ) : '',
		'plugins_url'  => current_user_can( 'activate_plugins' ) ? admin_url( 'plugins.php' ) : '',
		'docs'         => emsfb_email_next_steps_docs_efb(),
		'services'     => emsfb_email_sending_services_efb(),
	);
}
