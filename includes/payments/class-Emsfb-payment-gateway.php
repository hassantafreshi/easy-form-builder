<?php
/**
 * PAY00 §5: the contract a gateway add-on implements, and the value objects it exchanges with core.
 *
 * An add-on (vendor/<dir>/routes-efb.php, loaded when its flag is on) does only this:
 *
 *     add_action( 'efb_register_payment_gateways', function ( $registry ) {
 *         $registry->register( new My_Gateway() );   // My_Gateway extends \Emsfb\Payments\Gateway
 *     } );
 *
 * Core owns the tables, the REST routes (create through the form submit, webhook, return, status,
 * poll), the amount (Pricing), the state machine, finalize (once), the reconcile job, the
 * encrypted settings and the payment field. A gateway never sees a browser amount: it charges
 * $p->amount_minor in $p->currency, and it reaches the network only through $this->request()
 * (Http::request with api_hosts() as the allow-list), so tests answer it with pre_http_request.
 */
namespace Emsfb\Payments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class Gateway {

	/** Framework API version this class was written for (see Registry::register()). */
	const API = 1;

	/** [a-z][a-z0-9]{1,19}: the field type, the settings option suffix and the webhook path part. */
	abstract public function slug();

	/** The add-on key (e.g. 'AdnMOL'); '' for a gateway shipped with core (the test gateway). */
	abstract public function addon_key();

	/** Translated display name (the add-on's own text domain). */
	abstract public function label();

	/** What the gateway can do; core shows options and checks limits from it. */
	public function supports() {
		return array(
			'flows'          => array( 'redirect' ), // redirect | embedded | qr
			'refund'         => false,
			'partial_refund' => false,
			'recurring'      => false,               // false | 'native' | 'mit' (phase 1: one-time only)
			'authorize'      => false,
			'currencies'     => array(),             // empty = any
			'countries'      => array(),
			'min_minor'      => array(),             // array( 'EUR' => 30 )
			'max_minor'      => array(),
			'webhook_auth'   => 'refetch',           // signature | token | refetch
		);
	}

	/**
	 * Settings form: list of fields.
	 * key ([a-z0-9_]{1,40}), type (secret | text | select | checkbox), label, help, per_mode (bool: one
	 * value for test and one for live), required (bool), pattern (regex without delimiters), options (select).
	 * Secrets are stored encrypted (Secrets) in the option emsfb_pgw_<slug>, never in the form JSON.
	 */
	abstract public function settings_schema();

	/** A light request that proves the keys of $mode work ("Test connection"). @return Result */
	abstract public function test_connection( Settings $s, $mode );

	/** Creates the payment at the provider. Must use $p->amount_minor and $p->currency. @return Create_Result */
	abstract public function create( Payment $p, Settings $s, Checkout_Context $ctx );

	/** The provider's truth about a payment. @return Remote_State */
	abstract public function fetch( Payment $p, Settings $s );

	/**
	 * Verifies and reads a webhook. null = not ours / nothing to do (answered 200, no outbound call);
	 * an event with auth_failed = true is answered 401. needs_refetch = true makes core call fetch().
	 *
	 * @return Webhook_Event|null
	 */
	abstract public function parse_webhook( \WP_REST_Request $r, Settings $s );

	/** Browser return parameters → the gateway reference to check. Never a status. */
	public function parse_return( array $query, Payment $p ) {
		return $p->gateway_ref;
	}

	/** @return Refund_Result */
	public function refund( Payment $p, $amount_minor, $reason, Settings $s ) {
		return Refund_Result::unsupported();
	}

	/** @return Result */
	public function cancel( Payment $p, Settings $s ) {
		return Result::unsupported();
	}

	/** Minor units → the provider's amount format (default: decimal string "12.50"). */
	public function format_amount( $minor, $currency ) {
		return Money::to_decimal_string( $minor, $currency, $this->slug() );
	}

	/** Seconds after creation when an unpaid payment counts as expired. */
	public function pending_ttl( Payment $p ) {
		return DAY_IN_SECONDS;
	}

	/** Public scripts/styles of the visitor-side adapter, enqueued only on pages with this field. */
	public function public_assets() {
		return array( 'js' => array(), 'css' => array(), 'external' => array() );
	}

	/** Payment methods offered by the account: array( array( id, label ), … ). */
	public function methods( Settings $s ) {
		return array();
	}

	/** Logos served from the add-on's own folder: array( array( url, alt ), … ). */
	public function logos() {
		return array();
	}

	/** Hosts $this->request() may reach (SSRF guard). */
	public function api_hosts() {
		return array();
	}

	/** Link to the payment in the provider's dashboard ('' when there is none). */
	public function dashboard_url( Payment $p ) {
		return '';
	}

	/** Whether the keys of $mode are present and readable. */
	public function is_configured( Settings $s, $mode ) {
		foreach ( (array) $this->settings_schema() as $f ) {
			if ( empty( $f['required'] ) ) continue;
			$v = ( isset( $f['type'] ) && $f['type'] === 'secret' ) ? $s->secret( $f['key'], $mode ) : $s->get( $f['key'], $mode );
			if ( $v === null || $v === '' ) return false;
		}
		return true;
	}

	/** The only network access a gateway should use. */
	protected function request( $method, $url, array $args = array() ) {
		return Http::request( $method, $url, $args, $this->api_hosts() );
	}
}

/** A payment row as the gateway sees it (read only by convention). */
class Payment {
	public $id = 0;
	public $uid = '';
	public $form_id = 0;
	public $field_id = '';
	public $msg_id = 0;
	public $track = '';
	public $gateway = '';
	public $mode = 'test';
	public $status = 'created';
	public $amount_minor = 0;
	public $currency = '';
	public $refunded_minor = 0;
	public $gateway_ref = '';
	public $method = '';
	public $payer_email = '';
	public $payer_name = '';
	public $locale = '';
	public $idem_key = '';
	public $attempt_key = '';
	public $created_at = '';
	public $expires_at = null;
	public $quote = array();

	public function __construct( array $row = array() ) {
		foreach ( $row as $k => $v ) {
			if ( ! property_exists( $this, $k ) ) continue;
			if ( $k === 'quote' ) {
				$d = is_string( $v ) ? json_decode( $v, true ) : $v;
				$this->quote = is_array( $d ) ? $d : array();
				continue;
			}
			$this->$k = $v;
		}
		foreach ( array( 'id', 'form_id', 'msg_id', 'amount_minor', 'refunded_minor' ) as $k ) $this->$k = (int) $this->$k;
	}

	public function amount_decimal() {
		return Money::to_decimal_string( $this->amount_minor, $this->currency );
	}

	public function is_test() {
		return $this->mode !== 'live';
	}
}

/** What core hands to Gateway::create(). */
class Checkout_Context {
	public $return_url = '';
	public $cancel_url = '';
	public $webhook_url = '';
	public $locale = '';
	public $method = '';
	public $description = '';
	public $payer_email = '';
	public $metadata = array();

	public function __construct( array $a = array() ) {
		foreach ( $a as $k => $v ) {
			if ( property_exists( $this, $k ) ) $this->$k = $v;
		}
	}
}

class Result {
	public $ok = false;
	public $error = '';
	public $message = '';
	public $unsupported = false;

	public static function ok( $message = '' ) {
		$r = new static();
		$r->ok = true;
		$r->message = (string) $message;
		return $r;
	}

	public static function fail( $error, $message = '' ) {
		$r = new static();
		$r->error = (string) $error;
		$r->message = (string) $message;
		return $r;
	}

	public static function unsupported() {
		$r = new static();
		$r->error = 'unsupported';
		$r->unsupported = true;
		return $r;
	}
}

class Refund_Result extends Result {
	public $status = 'pending'; // pending | refunded | failed
	public $gateway_ref = '';
}

class Create_Result extends Result {
	public $gateway_ref = '';
	/** created | pending | authorized | paid (paid still needs a fetch() to be trusted) */
	public $status = 'pending';
	/** array( 'type' => redirect|embedded|qr|none, 'url' => …, 'client' => array (public data only), 'expires_at' => … ) */
	public $next = array( 'type' => 'none' );
	public $method = '';
	public $expires_at = null;

	public static function redirect( $gateway_ref, $url, $extra = array() ) {
		$r = new static();
		$r->ok = true;
		$r->gateway_ref = (string) $gateway_ref;
		$r->next = array_merge( array( 'type' => 'redirect', 'url' => (string) $url ), $extra );
		return $r;
	}
}

class Remote_State {
	public $ok = false;
	public $error = '';
	/** One of Store::STATUSES. */
	public $status = '';
	public $amount_minor = null;
	public $currency = '';
	public $paid_at = null;
	public $method = '';
	public $refunded_minor = 0;
	public $raw = array();

	public static function of( $status, $amount_minor, $currency, array $extra = array() ) {
		$s = new static();
		$s->ok = true;
		$s->status = (string) $status;
		$s->amount_minor = $amount_minor === null ? null : (int) $amount_minor;
		$s->currency = strtoupper( (string) $currency );
		foreach ( $extra as $k => $v ) {
			if ( property_exists( $s, $k ) ) $s->$k = $v;
		}
		return $s;
	}

	public static function error( $code ) {
		$s = new static();
		$s->error = (string) $code;
		return $s;
	}
}

class Webhook_Event {
	public $verified = false;
	public $auth_failed = false;
	public $gateway_ref = '';
	public $event_id = '';
	public $type = '';
	public $needs_refetch = true;
	/** Remote_State when the verified event carries the state itself (needs_refetch = false). */
	public $state = null;
	public $payload = array();

	public function __construct( array $a = array() ) {
		foreach ( $a as $k => $v ) {
			if ( property_exists( $this, $k ) ) $this->$k = $v;
		}
	}

	public static function rejected( $payload = array() ) {
		return new static( array( 'auth_failed' => true, 'payload' => $payload ) );
	}
}
