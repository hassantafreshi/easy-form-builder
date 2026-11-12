<?php
/**
 * Not a test. Runs the PHP side of the payment-field and pricing twins for the node parity tests:
 * reads {"cases":[{"op":..., ...}]} on stdin, prints the results as JSON.
 * ops: sanitize {k,v} · field {row, form0, opts, locale, defaults, ctx} · quote {form, rows, ctx}
 *      · gwsanitize {k, v, schema} · gwschema {schema} · gwoptions {row, schema} · warnings {form, infos}
 *      · money {value, currency} · tominor {minor, currency}
 */
define('ABSPATH', __DIR__ . '/');
require __DIR__ . '/../includes/payments/class-Emsfb-payment-money.php';
require __DIR__ . '/../includes/payments/payment-field-functions-efb.php';
require __DIR__ . '/../includes/payments/class-Emsfb-payment-pricing.php';
use Emsfb\Payments\Field;
use Emsfb\Payments\Pricing;
use Emsfb\Payments\Money;
$in = json_decode(stream_get_contents(STDIN), true);
$out = array();
foreach ($in['cases'] as $c) {
	switch ($c['op']) {
		case 'sanitize':
			$out[] = Field::sanitize_key($c['k'], $c['v']);
			break;
		case 'field':
			$r = Field::resolve($c['row'], $c['form0'], isset($c['opts']) ? $c['opts'] : array());
			$t = Field::texts($c['row'], $c['locale'], $c['defaults']);
			$out[] = array('resolved' => $r, 'texts' => $t, 'css' => Field::css($r, $c['ctx']['id']), 'html' => Field::html($r, $t, $c['ctx']));
			break;
		case 'quote':
			$q = Pricing::quote($c['form'], $c['rows'], array_merge(array('server_prices' => false), $c['ctx']));
			unset($q['hash']);
			$out[] = $q;
			break;
		case 'gwsanitize':
			$out[] = Field::sanitize_gateway_key($c['k'], $c['v'], isset($c['schema']) ? $c['schema'] : null);
			break;
		case 'gwschema':
			$out[] = Field::clean_schema($c['schema']);
			break;
		case 'gwoptions':
			$out[] = Field::gateway_options($c['row'], $c['schema']);
			break;
		case 'warnings':
			$out[] = Field::warnings($c['form'], $c['infos']);
			break;
		case 'money':
			$out[] = Money::from_decimal($c['value'], $c['currency']);
			break;
		case 'tominor':
			$out[] = Money::to_decimal_string($c['minor'], $c['currency']);
			break;
	}
}
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
