<?php
/**
 * Translatable labels of the input-mask presets (F01) and of their country groups.
 *
 * Every label is a literal __() call so that tests/core-i18n/extract.php (wp i18n make-pot) finds it.
 * The English text equals label.en in includes/data/masks-catalog-efb.json; the fa and ar columns of
 * the catalog seed the Persian and Arabic packs. A preset added to the catalog without a line here
 * still shows its catalog label (Masks_Labels::label() falls back to it).
 */

namespace Emsfb;

if (!defined('ABSPATH')) {
	exit;
}

class Masks_Labels {

	/** Preset id => translated label. */
	public static function presets() {
		return array(
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'ir.mobile' => __('Iran mobile number', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'ir.landline' => __('Iran landline (with area code)', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'ir.national_id' => __('Iranian national code', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'ir.legal_id' => __('Iranian legal-entity national ID', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'ir.economic_code' => __('Iranian economic code', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'ir.postal_code' => __('Iranian postal code', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'ir.bank_card' => __('Iranian bank card (Shetab)', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'ir.sheba' => __('Sheba (Iranian IBAN)', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'ir.account_number' => __('Iranian bank account number', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'ir.vehicle_plate' => __('Iranian car plate', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'ir.passport' => __('Iranian passport number', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'ir.date_jalali' => __('Jalali (Solar Hijri) date', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'ir.time' => __('Time (24-hour)', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'ir.amount_toman' => __('Amount in Toman', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'ir.amount_rial' => __('Amount in Rial', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'sa.mobile' => __('Saudi mobile number', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'sa.landline' => __('Saudi landline', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'sa.national_id' => __('Saudi National ID / Iqama', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'sa.cr_number' => __('Saudi Commercial Registration (CR) number', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'sa.vat' => __('Saudi VAT number', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'sa.iban' => __('Saudi IBAN', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'sa.postal_code' => __('Saudi postal code', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'sa.short_address' => __('Saudi National Address short code', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'sa.date_hijri' => __('Hijri date (Umm al-Qura)', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'ae.mobile' => __('UAE mobile number', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'ae.emirates_id' => __('Emirates ID number', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'ae.iban' => __('UAE IBAN', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'ae.trn' => __('UAE Tax Registration Number (TRN)', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'eg.mobile' => __('Egyptian mobile number', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'eg.national_id' => __('Egyptian national ID', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'eg.iban' => __('Egyptian IBAN', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'eg.postal_code' => __('Egyptian postal code', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'kw.mobile' => __('Kuwaiti mobile number', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'kw.civil_id' => __('Kuwaiti Civil ID', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'kw.iban' => __('Kuwaiti IBAN', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'kw.postal_code' => __('Kuwaiti postal code', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'qa.mobile' => __('Qatari mobile number', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'qa.qid' => __('Qatar ID (QID)', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'qa.iban' => __('Qatari IBAN', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'bh.mobile' => __('Bahraini mobile number', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'bh.cpr' => __('Bahraini CPR number', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'bh.iban' => __('Bahraini IBAN', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'om.mobile' => __('Omani mobile number', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'om.iban' => __('Omani IBAN', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'om.postal_code' => __('Omani postal code', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'jo.mobile' => __('Jordanian mobile number', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'jo.national_number' => __('Jordanian national number', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'jo.iban' => __('Jordanian IBAN', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'jo.postal_code' => __('Jordanian postal code', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'iq.mobile' => __('Iraqi mobile number', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'iq.iban' => __('Iraqi IBAN', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'lb.mobile' => __('Lebanese mobile number', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'lb.iban' => __('Lebanese IBAN', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'ma.mobile' => __('Moroccan mobile number', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'ma.cin' => __('Moroccan CIN (national ID card)', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'ma.iban' => __('Moroccan IBAN (RIB)', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'ma.postal_code' => __('Moroccan postal code', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'dz.mobile' => __('Algerian mobile number', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'dz.iban' => __('Algerian IBAN', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'dz.postal_code' => __('Algerian postal code', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'tn.mobile' => __('Tunisian mobile number', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'tn.cin' => __('Tunisian CIN', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'tn.iban' => __('Tunisian IBAN', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'tn.postal_code' => __('Tunisian postal code', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'intl.phone_e164' => __('International phone (E.164)', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'us.phone' => __('US / Canada phone (NANP)', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'us.zip' => __('US ZIP code', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'us.ssn' => __('US Social Security number', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'us.ein' => __('US Employer Identification Number (EIN)', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'uk.mobile' => __('UK mobile number', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'uk.landline' => __('UK landline', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'uk.postcode' => __('UK postcode', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'uk.nino' => __('UK National Insurance number', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'ca.postal_code' => __('Canadian postal code', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'au.mobile' => __('Australian mobile number', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'au.postcode' => __('Australian postcode', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'au.abn' => __('Australian Business Number (ABN)', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'in.mobile' => __('Indian mobile number', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'in.pin' => __('Indian PIN code', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'in.pan' => __('Indian PAN', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'in.aadhaar' => __('Aadhaar number', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'de.plz' => __('German postal code (PLZ)', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'intl.iban' => __('IBAN (any country)', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'intl.card' => __('Credit / debit card number', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'intl.cvc' => __('Card security code (CVC)', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'intl.card_expiry' => __('Card expiry (MM/YY)', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'intl.date_mdy' => __('Date (MM/DD/YYYY)', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'intl.date_dmy' => __('Date (DD/MM/YYYY)', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'intl.date_iso' => __('Date (YYYY-MM-DD)', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'intl.time_24' => __('Time (24-hour)', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'intl.time_12' => __('Time (12-hour)', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'intl.currency' => __('Currency amount', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'intl.percentage' => __('Percentage', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'intl.ipv4' => __('IPv4 address', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'intl.mac' => __('MAC address', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'intl.hex_color' => __('Hex colour', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'intl.slug' => __('URL slug', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'intl.license_key' => __('License key (XXXX-XXXX-XXXX-XXXX)', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'intl.passport' => __('Passport number (generic)', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'br.cpf' => __('Brazilian CPF', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'br.cnpj' => __('Brazilian CNPJ (numeric or alphanumeric)', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'br.cep' => __('Brazilian CEP', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'br.mobile' => __('Brazilian mobile', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'br.phone' => __('Brazilian landline', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'ca.sin' => __('Canadian SIN', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'us.routing_number' => __('US ABA routing number', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'us.phone_ext' => __('US phone with extension', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'intl.time_24s' => __('Time (HH:MM:SS)', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'intl.datetime_dmy' => __('Date and time (DD/MM/YYYY HH:MM[:SS])', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'intl.month_year' => __('Month and year (MM/YYYY)', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'intl.vin' => __('Vehicle identification number (VIN)', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'intl.swift_bic' => __('SWIFT / BIC code', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'uk.sort_code' => __('UK bank sort code', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'es.dni' => __('Spanish DNI', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'es.nie' => __('Spanish NIE', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'it.codice_fiscale' => __('Italian codice fiscale', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'fr.siren' => __('French SIREN', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'fr.siret' => __('French SIRET', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'nl.bsn' => __('Dutch BSN', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'nl.postcode' => __('Dutch postcode', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'mx.curp' => __('Mexican CURP', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'tr.mobile' => __('Turkish mobile', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'tr.tc_kimlik' => __('Turkish T.C. Kimlik No', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'pk.mobile' => __('Pakistani mobile', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'pk.cnic' => __('Pakistani CNIC', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'af.mobile' => __('Afghan mobile', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'ps.mobile' => __('Palestinian mobile', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'ly.mobile' => __('Libyan mobile', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'sd.mobile' => __('Sudanese mobile', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'ye.mobile' => __('Yemeni mobile', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'sy.mobile' => __('Syrian mobile', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'mr.mobile' => __('Mauritanian mobile', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'eg.tax_id' => __('Egyptian tax registration number', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'ma.ice' => __('Moroccan ICE', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'dz.nif' => __('Algerian NIF', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'tn.mf' => __('Tunisian matricule fiscal', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'in.gstin' => __('Indian GSTIN', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'in.ifsc' => __('Indian IFSC', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'intl.imei' => __('IMEI', 'easy-form-builder'),
			/* translators: input format preset shown in the form builder's "Format / mask" list */
			'intl.gtin' => __('GTIN / EAN / UPC barcode', 'easy-form-builder'),
		);
	}

	/** Group key (preset id prefix) => translated country or group name. */
	public static function groups() {
		return array(
			/* translators: country group in the form builder's "Format / mask" list */
			'ir' => __('Iran', 'easy-form-builder'),
			/* translators: country group in the form builder's "Format / mask" list */
			'sa' => __('Saudi Arabia', 'easy-form-builder'),
			/* translators: country group in the form builder's "Format / mask" list */
			'ae' => __('United Arab Emirates', 'easy-form-builder'),
			/* translators: country group in the form builder's "Format / mask" list */
			'eg' => __('Egypt', 'easy-form-builder'),
			/* translators: country group in the form builder's "Format / mask" list */
			'kw' => __('Kuwait', 'easy-form-builder'),
			/* translators: country group in the form builder's "Format / mask" list */
			'qa' => __('Qatar', 'easy-form-builder'),
			/* translators: country group in the form builder's "Format / mask" list */
			'bh' => __('Bahrain', 'easy-form-builder'),
			/* translators: country group in the form builder's "Format / mask" list */
			'om' => __('Oman', 'easy-form-builder'),
			/* translators: country group in the form builder's "Format / mask" list */
			'jo' => __('Jordan', 'easy-form-builder'),
			/* translators: country group in the form builder's "Format / mask" list */
			'iq' => __('Iraq', 'easy-form-builder'),
			/* translators: country group in the form builder's "Format / mask" list */
			'lb' => __('Lebanon', 'easy-form-builder'),
			/* translators: country group in the form builder's "Format / mask" list */
			'ma' => __('Morocco', 'easy-form-builder'),
			/* translators: country group in the form builder's "Format / mask" list */
			'dz' => __('Algeria', 'easy-form-builder'),
			/* translators: country group in the form builder's "Format / mask" list */
			'tn' => __('Tunisia', 'easy-form-builder'),
			/* translators: country group in the form builder's "Format / mask" list */
			'us' => __('United States', 'easy-form-builder'),
			/* translators: country group in the form builder's "Format / mask" list */
			'uk' => __('United Kingdom', 'easy-form-builder'),
			/* translators: country group in the form builder's "Format / mask" list */
			'ca' => __('Canada', 'easy-form-builder'),
			/* translators: country group in the form builder's "Format / mask" list */
			'au' => __('Australia', 'easy-form-builder'),
			/* translators: country group in the form builder's "Format / mask" list */
			'in' => __('India', 'easy-form-builder'),
			/* translators: country group in the form builder's "Format / mask" list */
			'de' => __('Germany', 'easy-form-builder'),
			/* translators: country group in the form builder's "Format / mask" list */
			'intl' => __('International', 'easy-form-builder'),
			/* translators: country group in the form builder's "Format / mask" list */
			'br' => __('Brazil', 'easy-form-builder'),
			/* translators: country group in the form builder's "Format / mask" list */
			'es' => __('Spain', 'easy-form-builder'),
			/* translators: country group in the form builder's "Format / mask" list */
			'it' => __('Italy', 'easy-form-builder'),
			/* translators: country group in the form builder's "Format / mask" list */
			'fr' => __('France', 'easy-form-builder'),
			/* translators: country group in the form builder's "Format / mask" list */
			'nl' => __('Netherlands', 'easy-form-builder'),
			/* translators: country group in the form builder's "Format / mask" list */
			'mx' => __('Mexico', 'easy-form-builder'),
			/* translators: country group in the form builder's "Format / mask" list */
			'tr' => __('Türkiye', 'easy-form-builder'),
			/* translators: country group in the form builder's "Format / mask" list */
			'pk' => __('Pakistan', 'easy-form-builder'),
			/* translators: country group in the form builder's "Format / mask" list */
			'af' => __('Afghanistan', 'easy-form-builder'),
			/* translators: country group in the form builder's "Format / mask" list */
			'ps' => __('Palestine', 'easy-form-builder'),
			/* translators: country group in the form builder's "Format / mask" list */
			'ly' => __('Libya', 'easy-form-builder'),
			/* translators: country group in the form builder's "Format / mask" list */
			'sd' => __('Sudan', 'easy-form-builder'),
			/* translators: country group in the form builder's "Format / mask" list */
			'ye' => __('Yemen', 'easy-form-builder'),
			/* translators: country group in the form builder's "Format / mask" list */
			'sy' => __('Syria', 'easy-form-builder'),
			/* translators: country group in the form builder's "Format / mask" list */
			'mr' => __('Mauritania', 'easy-form-builder'),
		);
	}

	/** Translated label of one preset, or $fallback when it has none. */
	public static function label($id, $fallback = '') {
		$all = self::presets();
		return isset($all[$id]) ? $all[$id] : $fallback;
	}
}
