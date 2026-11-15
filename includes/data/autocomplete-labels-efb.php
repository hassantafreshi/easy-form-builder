<?php
/**
 * F16: the short descriptions of the autocomplete tokens in the builder's "Browser autofill" list, by group.
 * The token names themselves are never translated (a browser reads them); every label is a literal __() so that
 * tests/core-i18n/extract.php finds it. Read by Emsfb\A11y::localize_vars() (efb_var.a11y.labels).
 */
if (!defined('ABSPATH')) {
	exit;
}

return array(
	'a11yAcGrpName' => array(
		/* translators: F16 builder, "Browser autofill" list: what the browser fills in */
		'name' => __('Full name', 'easy-form-builder'),
		/* translators: F16 builder, "Browser autofill" list: what the browser fills in */
		'honorific-prefix' => __('Title (Mr, Ms, Dr)', 'easy-form-builder'),
		/* translators: F16 builder, "Browser autofill" list: what the browser fills in */
		'given-name' => __('First name', 'easy-form-builder'),
		/* translators: F16 builder, "Browser autofill" list: what the browser fills in */
		'additional-name' => __('Middle name', 'easy-form-builder'),
		/* translators: F16 builder, "Browser autofill" list: what the browser fills in */
		'family-name' => __('Last name', 'easy-form-builder'),
		/* translators: F16 builder, "Browser autofill" list: what the browser fills in */
		'nickname' => __('Nickname', 'easy-form-builder'),
		/* translators: F16 builder, "Browser autofill" list: what the browser fills in */
		'organization' => __('Company or organization', 'easy-form-builder'),
		/* translators: F16 builder, "Browser autofill" list: what the browser fills in */
		'organization-title' => __('Job title', 'easy-form-builder'),
	),
	'a11yAcGrpContact' => array(
		/* translators: F16 builder, "Browser autofill" list: what the browser fills in */
		'email' => __('Email address', 'easy-form-builder'),
		/* translators: F16 builder, "Browser autofill" list: what the browser fills in */
		'tel' => __('Phone number (with country code)', 'easy-form-builder'),
		/* translators: F16 builder, "Browser autofill" list: what the browser fills in */
		'tel-national' => __('Phone number (national)', 'easy-form-builder'),
		/* translators: F16 builder, "Browser autofill" list: what the browser fills in */
		'tel-country-code' => __('Country calling code', 'easy-form-builder'),
		/* translators: F16 builder, "Browser autofill" list: what the browser fills in */
		'url' => __('Website address', 'easy-form-builder'),
		/* translators: F16 builder, "Browser autofill" list: what the browser fills in */
		'impp' => __('Messenger address', 'easy-form-builder'),
	),
	'a11yAcGrpAddress' => array(
		/* translators: F16 builder, "Browser autofill" list: what the browser fills in */
		'street-address' => __('Street address (all lines)', 'easy-form-builder'),
		/* translators: F16 builder, "Browser autofill" list: what the browser fills in */
		'address-line1' => __('Address line 1', 'easy-form-builder'),
		/* translators: F16 builder, "Browser autofill" list: what the browser fills in */
		'address-line2' => __('Address line 2', 'easy-form-builder'),
		/* translators: F16 builder, "Browser autofill" list: what the browser fills in */
		'address-level2' => __('City', 'easy-form-builder'),
		/* translators: F16 builder, "Browser autofill" list: what the browser fills in */
		'address-level1' => __('State or province', 'easy-form-builder'),
		/* translators: F16 builder, "Browser autofill" list: what the browser fills in */
		'country' => __('Country code', 'easy-form-builder'),
		/* translators: F16 builder, "Browser autofill" list: what the browser fills in */
		'country-name' => __('Country name', 'easy-form-builder'),
		/* translators: F16 builder, "Browser autofill" list: what the browser fills in */
		'postal-code' => __('Postal code', 'easy-form-builder'),
	),
	'a11yAcGrpPay' => array(
		/* translators: F16 builder, "Browser autofill" list: what the browser fills in */
		'cc-name' => __('Name on card', 'easy-form-builder'),
		/* translators: F16 builder, "Browser autofill" list: what the browser fills in */
		'cc-number' => __('Card number', 'easy-form-builder'),
		/* translators: F16 builder, "Browser autofill" list: what the browser fills in */
		'cc-exp' => __('Card expiry date', 'easy-form-builder'),
		/* translators: F16 builder, "Browser autofill" list: what the browser fills in */
		'cc-csc' => __('Card security code', 'easy-form-builder'),
		/* translators: F16 builder, "Browser autofill" list: what the browser fills in */
		'transaction-amount' => __('Amount', 'easy-form-builder'),
		/* translators: F16 builder, "Browser autofill" list: what the browser fills in */
		'transaction-currency' => __('Currency', 'easy-form-builder'),
	),
	'a11yAcGrpOther' => array(
		/* translators: F16 builder, "Browser autofill" list: what the browser fills in */
		'bday' => __('Date of birth', 'easy-form-builder'),
		/* translators: F16 builder, "Browser autofill" list: what the browser fills in */
		'sex' => __('Gender', 'easy-form-builder'),
		/* translators: F16 builder, "Browser autofill" list: what the browser fills in */
		'language' => __('Preferred language', 'easy-form-builder'),
		/* translators: F16 builder, "Browser autofill" list: what the browser fills in */
		'username' => __('Username', 'easy-form-builder'),
		/* translators: F16 builder, "Browser autofill" list: what the browser fills in */
		'new-password' => __('New password', 'easy-form-builder'),
		/* translators: F16 builder, "Browser autofill" list: what the browser fills in */
		'current-password' => __('Current password', 'easy-form-builder'),
		/* translators: F16 builder, "Browser autofill" list: what the browser fills in */
		'one-time-code' => __('One-time code', 'easy-form-builder'),
		/* translators: F16 builder, "Browser autofill" list: what the browser fills in */
		'photo' => __('Photo address', 'easy-form-builder'),
	),
);
