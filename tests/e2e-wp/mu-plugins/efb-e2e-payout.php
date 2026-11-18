<?php
/**
 * Plugin Name: EFB e2e: PAY-OUT stand-ins (local test site only)
 * Description: tests/test-payout-*-http.php and the e2e scenario "payout" copy this file into wp-content/mu-plugins
 *              (tests/e2e-wp/ctl-payout.php mu). While the option efb_e2e_payout_fake is set, the Stripe SDK of the
 *              Stripe add-on (legacy field: card/add, confirm, the final submit, the background check) is answered by
 *              a local HTTP client: PaymentIntents are kept in the option efb_e2e_payout_stripe (created with the
 *              requested amount and currency; their status is the option's "mode", "succeeded" by default), and every
 *              call is logged. Nothing leaves the machine and no real key is used. Self-contained (no file of the
 *              plugin is required), so it works whichever core or add-on version the PAY-OUT tests put in place.
 */

/* Which core folder this request runs (the mixed-version tests wait for it after they swap a version). */
add_action( 'rest_api_init', function () {
	register_rest_route( 'efb-e2e/v1', 'payout-probe', array( 'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => function () {
		return array( 'dir' => defined( 'EMSFB_PLUGIN_DIRECTORY' ) ? realpath( EMSFB_PLUGIN_DIRECTORY ) : '' );
	} ) );
} );

if ( ! get_option( 'efb_e2e_payout_fake' ) ) return;

/** The fake's state: intents, mode, log. */
function efb_e2e_payout_state( $set = null ) {
	if ( is_array( $set ) ) { update_option( 'efb_e2e_payout_stripe', $set, false ); return $set; }
	$s = get_option( 'efb_e2e_payout_stripe', array() );
	$s = is_array( $s ) ? $s : array();
	return array_merge( array( 'intents' => array(), 'mode' => 'succeeded', 'log' => array() ), $s );
}

function efb_e2e_payout_stripe_client() {
	if ( ! interface_exists( '\Stripe\HttpClient\ClientInterface' ) ) {
		$init = WP_PLUGIN_DIR . '/easy-form-builder/vendor/stripe/stripe-php/init.php';
		if ( ! is_readable( $init ) || class_exists( '\Stripe\StripeClient', false ) ) return;
		require_once $init;
	}
	if ( class_exists( 'Efb_E2E_Payout_Stripe_Http', false ) ) return;
	/* Declared here, once the SDK's interface exists (a class inside a function is declared when it runs). */
	final class Efb_E2E_Payout_Stripe_Http implements \Stripe\HttpClient\ClientInterface {
		public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = "v1") {
			$s = efb_e2e_payout_state();
			$path = (string) parse_url($absUrl, PHP_URL_PATH);
			$key = "";
			foreach ((array) $headers as $h) if (stripos($h, "Authorization:") === 0) $key = trim(substr($h, 14));
			$s["log"][] = array("method" => strtoupper($method), "path" => $path, "key" => substr(preg_replace("/^Bearer\s+/", "", $key), 0, 8), "params" => is_array($params) ? $params : array());
			$out = array("error" => array("type" => "invalid_request_error", "message" => "unsupported"));
			$code = 400;
			if (strtoupper($method) === "POST" && $path === "/v1/payment_intents") {
				$id = "pi_efbe2e" . substr(md5(uniqid("", true)), 0, 14);
				$s["intents"][$id] = array("id" => $id, "amount" => (int) ($params["amount"] ?? 0), "currency" => strtolower((string) ($params["currency"] ?? "usd")), "created" => time(), "description" => (string) ($params["description"] ?? ""));
				$out = array("id" => $id, "object" => "payment_intent", "amount" => $s["intents"][$id]["amount"], "currency" => $s["intents"][$id]["currency"], "status" => "requires_payment_method", "client_secret" => $id . "_secret_efbe2e", "created" => time());
				$code = 200;
			} elseif (preg_match("#^/v1/payment_intents/(pi_[A-Za-z0-9]+)$#", $path, $m)) {
				if (!isset($s["intents"][$m[1]])) {
					$out = array("error" => array("type" => "invalid_request_error", "code" => "resource_missing", "message" => "No such payment_intent"));
					$code = 404;
				} else {
					$i = $s["intents"][$m[1]];
					$mode = isset($i["mode"]) ? $i["mode"] : $s["mode"];
					$status = in_array($mode, array("processing", "requires_payment_method", "canceled"), true) ? $mode : "succeeded";
					$out = array("id" => $i["id"], "object" => "payment_intent", "amount" => $i["amount"], "amount_received" => $status === "succeeded" ? $i["amount"] : 0, "currency" => $i["currency"], "status" => $status, "created" => $i["created"]);
					$code = 200;
				}
			}
			$s["log"] = array_slice($s["log"], -60);
			efb_e2e_payout_state($s);
			return array(wp_json_encode($out), $code, array());
		}
	}
	\Stripe\ApiRequestor::setHttpClient( new Efb_E2E_Payout_Stripe_Http() );
}

add_action( 'plugins_loaded', 'efb_e2e_payout_stripe_client', 1 );
add_action( 'rest_api_init', 'efb_e2e_payout_stripe_client', 1 );
