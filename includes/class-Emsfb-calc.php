<?php
/**
 * F04: numbers and calculations (spec docs/feature-gaps/F04-number-calculation.fa.md).
 *
 * The pure part (normalize, tokenize, parse, evaluate, run, round_half_away, to_fixed, number parsing,
 * step / decimals / range checks, display format) is the twin of window.EFBCalc
 * (public/assets/js/calc-efb.js): both give the same answer, byte for byte, for every vector of
 * docs/feature-gaps/reference/calc-test-vectors.json (tests/test-calc-engine.php|js,
 * tests/test-calc-parity.js). A formula is read by a hand-written tokenizer into a tree of plain
 * arrays and the evaluator walks that tree: no string is executed (no eval, no create_function).
 *
 * Limits: 2000 characters, 512 tokens, nesting depth 32, 100 references; |result| <= 1e15 and the
 * scaled result below 2^53. An error is a code, never an exception (spec §13).
 *
 * The WordPress part (the server's own result of every calc field on submit, the options' calc
 * values, display in e-mails and the viewer, the save checks, forms/{id}/schema, the capability
 * registry) comes after the pure part.
 */

namespace Emsfb;

if (!defined('ABSPATH')) {
	exit;
}

final class Calc {

	const MAX_LEN = 2000;
	const MAX_TOKENS = 512;
	const MAX_DEPTH = 32;
	const MAX_REFS = 100;
	const MAX_ARGS = 200;
	const MAX_ABS = 1e15;
	const MAX_SAFE = 9007199254740992.0;
	const EPS = 1e-9;
	const WS = " \t\n\r\x0B\x0C\u{00A0}\u{2000}\u{2001}\u{2002}\u{2003}\u{2004}\u{2005}\u{2006}\u{2007}\u{2008}\u{2009}\u{200A}\u{2028}\u{2029}\u{202F}\u{205F}\u{3000}";

	private static $POW10 = array(1.0, 10.0, 100.0, 1000.0, 10000.0, 100000.0, 1000000.0);
	private static $FUNCS = array(
		'round' => array(1, 2), 'ceil' => array(1, 2), 'floor' => array(1, 2), 'min' => array(1, 200), 'max' => array(1, 200), 'abs' => array(1, 1), 'if' => array(3, 3),
		'sum' => array(1, 200), 'avg' => array(1, 200), 'count' => array(1, 200), 'gt' => array(2, 2), 'gte' => array(2, 2), 'lt' => array(2, 2), 'lte' => array(2, 2),
		'eq' => array(2, 2), 'neq' => array(2, 2), 'and' => array(1, 200), 'or' => array(1, 200), 'not' => array(1, 1), 'mod' => array(2, 2), 'pow' => array(2, 2),
	);
	const PARTS = array('count', 'price', 'calc', 'value', 'lo', 'hi');
	const LIST_FUNCS = array('sum', 'avg', 'min', 'max', 'count');
	/** Field types a formula may not read (spec §6.3). */
	const FORBIDDEN_TYPES = array('password', 'file', 'dadfile', 'esign', 'maps', 'html', 'audio_recorder', 'video_recorder', 'screen_recorder');

	/** Plan of each part (spec §15; the owner may change it with the filter efb_calc_plan). */
	const PLAN = array('number' => 'free', 'dual' => 'free', 'calc' => 'free_plus', 'option_calc' => 'free_plus', 'calc_price' => 'pro');

	/** WordPress hooks (the submit, display and builder parts below). */
	public static function register() {
		if (!function_exists('add_filter')) return;
		add_filter('efb_admin_localize_vars', array(__CLASS__, 'localize_vars'), 27, 2);
		add_filter('efb_choices_calc_column', array(__CLASS__, 'choices_calc_column'), 10, 1);
		add_filter('efb_capabilities', array(__CLASS__, 'capabilities'), 26, 2);
		add_filter('efb_form_schema_field', array(__CLASS__, 'schema_field'), 13, 3);
		add_filter('efb_payment_readiness', array(__CLASS__, 'payment_readiness'), 10, 3);
	}

	/** Stable codes (SitePilot contract): submit refusals and formula problems. */
	const CODES = array('nan' => 'efb_num_nan', 'dec' => 'efb_num_dec', 'step' => 'efb_num_step', 'min' => 'efb_num_min', 'max' => 'efb_num_max', 'order' => 'efb_num_order',
		'required' => 'efb_calc_required', 'error' => 'efb_calc_error');
	const FORMULA_CODES = array('calcSyntax', 'calcEmpty', 'calcLong', 'calcTokens', 'calcDepth', 'calcRefs', 'calcFn', 'calcArgs', 'calcRef', 'calcForbidden', 'calcSelf', 'calcCycle');
	const EVAL_CODES = array('calcDiv0', 'calcNaN', 'calcRange', 'calcListCtx', 'calcRef', 'calcArgs');

	/** efb_capabilities: the F04 entries (plans of spec §15) and capabilities.calc. */
	public static function capabilities($out, $args = array()) {
		if (!is_array($out) || !isset($out['features']) || !is_array($out['features'])) return $out;
		$package = isset($out['plan']['package_type']) ? (int) $out['plan']['package_type'] : null;
		$num_keys = array('num_step', 'num_decimals', 'num_format', 'num_dec_sep', 'num_grp_sep', 'num_digits', 'num_prefix', 'num_suffix', 'num_currency', 'num_max_zero', 'milen', 'mlen');
		$entries = array(
			'setting:number_format' => array('number', 'setting', $num_keys, 'numFormat'),
			'setting:range_dual'    => array('dual', 'setting', array('range_mode', 'num_step'), 'rangeDual'),
			'setting:calc_option'   => array('option_calc', 'setting', array('option.calc'), 'calcOptValue'),
			'setting:calc_price'    => array('calc_price', 'setting', array('calc_as_price'), 'calcAsPrice'),
		);
		foreach ($entries as $key => $e) {
			$need = isset(self::PLAN[$e[0]]) ? self::PLAN[$e[0]] : 'pro';
			if (function_exists('apply_filters')) $need = apply_filters('efb_calc_plan', $need, $e[0]);
			$ok = $need === 'free' ? true : (class_exists('\\Emsfb\\Builder_Api') ? Builder_Api::plan_at_least($need, $package) : false);
			$out['features'][$key] = array(
				'kind' => $e[1], 'scope' => 'field', 'package' => $need, 'addon' => null, 'available_now' => $ok, 'reason' => $ok ? null : 'plan',
				'prompt_state' => $need === 'free_plus' ? 3 : ($need === 'pro' ? 1 : 0),
				'message_key' => $need === 'free_plus' ? 'thisFeatureAvailableFreePlusPro' : ($need === 'pro' ? 'proUnlockMsg' : null),
				'form_keys' => $e[2], 'label_key' => $e[3],
			);
		}
		$need = self::PLAN['calc'];
		if (function_exists('apply_filters')) $need = apply_filters('efb_calc_plan', $need, 'calc');
		$ok = class_exists('\\Emsfb\\Builder_Api') ? Builder_Api::plan_at_least($need, $package) : false;
		$out['features']['field:calc'] = array_merge(isset($out['features']['field:calc']) && is_array($out['features']['field:calc']) ? $out['features']['field:calc'] : array(), array(
			'kind' => 'field', 'package' => $need, 'addon' => null, 'available_now' => $ok, 'reason' => $ok ? null : 'plan',
			'prompt_state' => $need === 'free_plus' ? 3 : 1, 'message_key' => $need === 'free_plus' ? 'thisFeatureAvailableFreePlusPro' : 'proUnlockMsg',
			'label_key' => 'calcField', 'pii_default' => false, 'form_keys' => array('formula', 'calc_show', 'calc_empty', 'num_decimals', 'num_format', 'num_prefix', 'num_suffix', 'num_currency', 'num_digits', 'calc_as_price'),
		));
		$out['calc'] = array(
			'types'        => array('number' => array('number', 'range', 'calc'), 'choices' => self::CHOICE_TYPES, 'forbidden' => self::FORBIDDEN_TYPES),
			'field_keys'   => self::FIELD_KEYS,
			'option_keys'  => array('calc'),
			'enums'        => self::ENUMS,
			'functions'    => array_keys(self::$FUNCS),
			'parts'        => self::PARTS,
			'grammar'      => 'expr := term ((+|-) term)*; term := unary ((*|/) unary)*; unary := (-|+) unary | primary; primary := NUMBER | {id} | {field:id} | {id.part} | fn(args) | (expr); no < > " % characters',
			'limits'       => array('formula' => self::MAX_FORMULA, 'tokens' => self::MAX_TOKENS, 'depth' => self::MAX_DEPTH, 'refs' => self::MAX_REFS, 'abs' => self::MAX_ABS, 'decimals' => 6, 'prefix' => 20),
			'codes'        => array('submit' => array_values(self::CODES), 'formula' => self::FORMULA_CODES, 'evaluate' => self::EVAL_CODES),
			'answer'       => array('calc' => 'computed by the server on submit; a calc row in the request is ignored', 'number' => 'local digits and separators accepted, stored as a canonical ASCII number', 'range_dual' => 'value "lo..hi", lo and hi'),
			'never'        => array('step' => 'the step of a number is num_step; the key step is the form step'),
			'readonly'     => array('formula_err'),
			'validate'     => 'efbBuilderApi.validateFormula(formula, rows) / efb_api_form_validate give the same codes as the server (Calc::form_errors)',
			'plan'         => array('number' => 'free', 'range_dual' => 'free', 'calc' => self::PLAN['calc'], 'option_calc' => self::PLAN['option_calc'], 'calc_price' => self::PLAN['calc_price']),
		);
		return $out;
	}

	/** forms/{id}/schema: a calc field is the server's (assistant none) with the ids its formula reads (never the formula); number settings. */
	public static function schema_field($e, $r, $form_id) {
		if (!is_array($e) || !is_array($r) || !isset($r['type'])) return $e;
		$type = (string) $r['type'];
		if ($type === 'calc') {
			$e['assistant'] = 'none';
			$e['fill'] = array('api' => 'none', 'note' => 'the server computes this value on submit from the other answers');
			$e['calc'] = array('refs' => self::ref_ids(isset($r['formula']) && is_scalar($r['formula']) ? (string) $r['formula'] : ''), 'decimals' => self::config($r)['decimals'], 'shown' => !isset($r['calc_show']) || $r['calc_show'] !== 'hidden', 'error' => isset($r['formula_err']) && $r['formula_err'] === '1');
			return $e;
		}
		if (in_array($type, array('number', 'range'), true)) {
			$c = self::config($r);
			$n = array('step' => $c['step'] !== '' ? $c['step'] : null, 'decimals' => $c['decimals'], 'min' => $c['min'] !== '' ? $c['min'] : null, 'max' => $c['max'] !== '' ? $c['max'] : null, 'input' => 'local digits and separators accepted; stored as ASCII');
			if ($type === 'range' && isset($r['range_mode']) && $r['range_mode'] === 'dual') $n['dual'] = array('value' => 'lo..hi', 'keys' => array('lo', 'hi'));
			$e['number'] = $n;
		}
		return $e;
	}

	/** Whether this site's plan has a part (free / free_plus / pro). */
	public static function plan_ok($part, $package = null) {
		$need = isset(self::PLAN[$part]) ? self::PLAN[$part] : 'pro';
		if (function_exists('apply_filters')) $need = apply_filters('efb_calc_plan', $need, $part);
		if ($need === 'free') return true;
		return class_exists('\\Emsfb\\Builder_Api') ? (bool) \Emsfb\Builder_Api::plan_at_least($need, $package) : false;
	}

	/** F05's "Add in bulk" keeps a third column (the calculation value) when calc values are on this plan. */
	public static function choices_calc_column($on) {
		return $on || self::plan_ok('option_calc');
	}

	/** efb_admin_localize_vars: efb_var.calc = {currencies, functions, plan, limits}. */
	public static function localize_vars($vars, $context = '') {
		if (!is_array($vars)) return $vars;
		$cur = array();
		if (class_exists('\\Emsfb\\Formbuilder') && method_exists('\\Emsfb\\Formbuilder', 'currency_table_efb')) {
			foreach (\Emsfb\Formbuilder::currency_table_efb() as $code => $d) $cur[$code] = array('s' => (string) $d['s'], 'd' => (int) $d['d']);
		}
		$vars['calc'] = array(
			'currencies' => $cur,
			'functions'  => array_keys(self::$FUNCS),
			'plan'       => array('calc' => self::plan_ok('calc'), 'option_calc' => self::plan_ok('option_calc'), 'calc_price' => self::plan_ok('calc_price')),
			'limits'     => array('formula' => self::MAX_FORMULA, 'tokens' => self::MAX_TOKENS, 'depth' => self::MAX_DEPTH, 'refs' => self::MAX_REFS),
		);
		return $vars;
	}

	/* ------------------------------------------------------------------ digits */

	private static function map_digits() {
		static $m = null;
		if ($m !== null) return $m;
		$m = array();
		for ($i = 0; $i < 10; $i++) {
			$m[self::chr(0x06F0 + $i)] = (string) $i;
			$m[self::chr(0x0660 + $i)] = (string) $i;
			$m[self::chr(0xFF10 + $i)] = (string) $i;
		}
		return $m;
	}

	private static function chr($cp) {
		return html_entity_decode('&#' . (int) $cp . ';', ENT_QUOTES, 'UTF-8');
	}

	/** Persian, Arabic-Indic and full-width digits to ASCII. */
	public static function normalize_digits($s) {
		return strtr(self::str($s), self::map_digits());
	}

	private static function str($v) {
		if ($v === null) return '';
		if (is_bool($v)) return $v ? 'true' : 'false';
		if (is_float($v)) return self::js_num($v);
		return is_scalar($v) ? (string) $v : '';
	}

	/** A float as JavaScript's String() writes the ones the tests send (integers and short decimals). */
	private static function js_num($v) {
		if (is_nan($v)) return 'NaN';
		if ($v == floor($v) && abs($v) < 1e21) return sprintf('%.0f', $v);
		$s = rtrim(rtrim(sprintf('%.15F', $v), '0'), '.');
		return (float) $s === $v ? $s : json_encode($v);
	}

	/** A formula as the tokenizer reads it: local digits, ٫ = '.', ، = ','; bidi marks removed. Twin: normalizeFormula(). */
	public static function normalize_formula($s) {
		$s = self::normalize_digits($s);
		$s = str_replace(array("\u{066B}", "\u{060C}", "\u{FF0C}", "\u{00D7}", "\u{00F7}"), array('.', ',', ',', '*', '/'), $s);
		$s = preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}]/u', '', $s);
		$s = preg_replace('/[\x{2212}\x{2010}-\x{2015}\x{FE63}\x{FF0D}]/u', '-', (string) $s);
		return (string) $s;
	}

	/* ------------------------------------------------------------------ tokenizer and parser */

	private static function err($code, $pos = -1, $near = '') {
		return array('ok' => false, 'code' => $code, 'pos' => (int) $pos, 'near' => (string) $near);
	}

	private static function chars($s) {
		if ($s === '') return array();
		$c = preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY);
		return is_array($c) ? $c : str_split($s);
	}

	private static function is_digit($c) { return strlen($c) === 1 && $c >= '0' && $c <= '9'; }
	private static function is_alpha($c) { return strlen($c) === 1 && (($c >= 'a' && $c <= 'z') || ($c >= 'A' && $c <= 'Z')); }

	public static function tokenize($src) {
		$s = self::str($src);
		if (!preg_match('//u', $s)) return self::err('calcSyntax', 0, '');
		$C = self::chars(self::normalize_formula($s));
		$n = count($C);
		if ($n > self::MAX_LEN) return self::err('calcLong', self::MAX_LEN, '');
		$out = array();
		$i = 0;
		$slice = function ($a, $b) use ($C, $n) { return implode('', array_slice($C, $a, max(0, min($b, $n) - $a))); };
		while ($i < $n) {
			$c = $C[$i];
			if (mb_strpos(self::WS, $c, 0, 'UTF-8') !== false) { $i++; continue; }
			if (count($out) >= self::MAX_TOKENS) return self::err('calcTokens', $i, $c);
			if (self::is_digit($c)) {
				$j = $i;
				while ($j < $n && self::is_digit($C[$j])) $j++;
				if ($j < $n && $C[$j] === '.' && $j + 1 < $n && self::is_digit($C[$j + 1])) { $j++; while ($j < $n && self::is_digit($C[$j])) $j++; }
				$after = $j < $n ? $C[$j] : '';
				if ($j - $i > 30 || $after === '.' || self::is_alpha($after) || $after === '_') return self::err('calcSyntax', $i, $slice($i, $i + 12));
				$out[] = array('t' => 'num', 'v' => $slice($i, $j), 'p' => $i);
				$i = $j;
				continue;
			}
			if ($c === '{') {
				$j = $i + 1;
				while ($j < $n && $C[$j] !== '}' && $j - $i <= 60) $j++;
				$inner = $j < $n && $C[$j] === '}' ? $slice($i + 1, $j) : null;
				$m = array();
				if ($inner === null || !preg_match('/^(?:field:)?([A-Za-z0-9_-]{1,40})(?:\.([A-Za-z0-9_-]{1,40}))?$/D', $inner, $m)) {
					return self::err('calcSyntax', $i, $slice($i, $inner === null ? $i + 12 : $j + 1));
				}
				$out[] = array('t' => 'ref', 'id' => $m[1], 'part' => isset($m[2]) ? $m[2] : '', 'raw' => '{' . $inner . '}', 'p' => $i);
				$i = $j + 1;
				continue;
			}
			if (self::is_alpha($c)) {
				$j = $i;
				while ($j < $n && (self::is_alpha($C[$j]) || $C[$j] === '_')) $j++;
				$v = $slice($i, $j);
				if ($j - $i > 20) return self::err('calcSyntax', $i, $slice($i, $i + 12));
				$out[] = array('t' => 'id', 'v' => strtolower($v), 'p' => $i);
				$i = $j;
				continue;
			}
			if (strpos('+-*/(),', $c) !== false && strlen($c) === 1) { $out[] = array('t' => $c, 'p' => $i); $i++; continue; }
			return self::err('calcSyntax', $i, $c);
		}
		return array('ok' => true, 'tokens' => $out, 'len' => $n);
	}

	/** {ok, ast, refs:[{id, part}]} or {ok:false, code, pos, near}. Twin: EFBCalc.parse(). */
	public static function parse($src) {
		$tk = self::tokenize($src);
		if (!$tk['ok']) return $tk;
		$p = new Calc_Parser($tk['tokens'], $tk['len']);
		return $p->run();
	}

	/** The distinct field ids a formula reads. */
	public static function ref_ids($src) {
		$p = is_array($src) ? $src : self::parse($src);
		if (empty($p['ok'])) return array();
		$out = array();
		foreach ($p['refs'] as $r) if (!in_array($r['id'], $out, true)) $out[] = $r['id'];
		return $out;
	}

	/* ------------------------------------------------------------------ evaluator */

	public static function num($v) {
		if (!is_float($v) && !is_int($v)) throw new Calc_Fail('calcRange');
		$v = (float) $v;
		if (is_nan($v) || is_infinite($v)) throw new Calc_Fail('calcRange');
		return $v;
	}

	/** Half away from zero with an epsilon (spec §6.2): never PHP round(). */
	public static function round_half_away($x, $d = 0) {
		$s = self::$POW10[(int) $d];
		$r = floor(abs($x) * $s + 0.5 + self::EPS) / $s;
		return $x < 0 ? -$r : $r;
	}

	/** The canonical text with d decimals (0-6), or with up to 6 and no trailing zeros (d null). Null when not exact. */
	public static function to_fixed($x, $d = null) {
		$free = $d === null || $d === '';
		$k = $free ? 6 : (int) $d;
		$s = self::$POW10[$k];
		$scaled = floor(abs((float) $x) * $s + 0.5 + self::EPS);
		if (!($scaled < self::MAX_SAFE)) return null;
		$digits = sprintf('%.0f', $scaled);
		while (strlen($digits) <= $k) $digits = '0' . $digits;
		$ip = substr($digits, 0, strlen($digits) - $k);
		$dp = $k > 0 ? substr($digits, strlen($digits) - $k) : '';
		if ($free) $dp = rtrim($dp, '0');
		$out = $ip . ($dp !== '' ? '.' . $dp : '');
		if ($x < 0 && preg_match('/[1-9]/', $out)) $out = '-' . $out;
		return $out;
	}

	public static function scale_of($d) {
		if (!is_float($d) || $d != floor($d) || $d < 0 || $d > 6) throw new Calc_Fail('calcArgs');
		return self::$POW10[(int) $d];
	}

	/**
	 * Walks a parsed tree. $resolver(array $ref): float|int, numeric string, list, null (empty) or
	 * ['error' => code]. $opts['empty'] = 'blank': an empty answer empties the result.
	 * {ok, value} / {ok, value: null, empty: true} / {ok: false, code}.
	 */
	public static function evaluate($ast, $resolver, array $opts = array()) {
		$ev = new Calc_Eval($resolver, isset($opts['empty']) && $opts['empty'] === 'blank');
		try {
			$v = $ev->scalar($ast);
			return array('ok' => true, 'value' => $v);
		} catch (Calc_Fail $e) {
			if ($e->code_ === '#empty' || $ev->is_empty) return array('ok' => true, 'value' => null, 'empty' => true);
			return array('ok' => false, 'code' => $e->code_);
		} catch (\Throwable $e) {
			return array('ok' => false, 'code' => 'calcSyntax');
		}
	}

	/** The canonical result with the field's decimals ('' = free). Twin: EFBCalc.finish(). */
	public static function finish(array $res, $decimals) {
		if (empty($res['ok'])) return $res;
		if (!empty($res['empty']) || $res['value'] === null) return array('ok' => true, 'value' => '');
		$v = $res['value'];
		if (!is_float($v) || is_nan($v) || is_infinite($v) || abs($v) > self::MAX_ABS) return array('ok' => false, 'code' => 'calcRange');
		$d = $decimals === '' || $decimals === null ? null : $decimals;
		if ($d !== null && !(is_numeric($d) && (float) $d >= 0 && (float) $d <= 6 && (float) $d == floor((float) $d))) $d = null;
		$d = $d === null ? null : (int) $d;
		$s = self::to_fixed($d === null ? $v : self::round_half_away($v, $d), $d);
		if ($s === null) return array('ok' => false, 'code' => 'calcRange');
		return array('ok' => true, 'value' => $s);
	}

	/** One formula from start to end (the shared vectors). Twin: EFBCalc.run(). */
	public static function run($formula, array $values = array(), $decimals = '', array $opts = array()) {
		$p = self::parse($formula);
		if (!$p['ok']) return array('ok' => false, 'code' => $p['code'], 'pos' => $p['pos'], 'near' => $p['near']);
		$r = self::evaluate($p['ast'], function ($ref) use ($values) {
			$key = $ref['part'] !== '' ? $ref['id'] . '.' . $ref['part'] : $ref['id'];
			if (!array_key_exists($key, $values)) return array('error' => 'calcRef');
			return Calc::value_of($values[$key]);
		}, $opts);
		$f = self::finish($r, $decimals);
		return $f['ok'] ? array('ok' => true, 'value' => $f['value']) : array('ok' => false, 'code' => $f['code']);
	}

	/** A raw answer as a formula reads it: number, list, null (empty) or ['error' => 'calcNaN']. Twin: valueOf(). */
	public static function value_of($v) {
		if (is_array($v)) {
			if (isset($v['error'])) return array('error' => (string) $v['error']);
			if ($v !== array() && array_keys($v) !== range(0, count($v) - 1)) return array('error' => 'calcNaN');
			$out = array();
			foreach ($v as $x) {
				$y = self::value_of($x);
				if (is_array($y) && isset($y['error'])) return $y;
				$out[] = $y;
			}
			return $out;
		}
		if (is_int($v) || is_float($v)) return (float) $v;
		if ($v === null) return null;
		if (!is_string($v)) return array('error' => 'calcNaN');
		$s = self::normalize_digits($v);
		$s = str_replace("\u{066B}", '.', $s);
		$s = preg_replace('/[\x{066C}\x{060C},\s]/u', '', $s);
		$s = str_replace("\u{2212}", '-', (string) $s);
		if ($s === '') return null;
		if (!preg_match('/^-?\d+(\.\d+)?$/D', $s) || strlen($s) > 40) return array('error' => 'calcNaN');
		return (float) $s;
	}

	/* ------------------------------------------------------------------ numbers typed by a visitor */

	const DEC_SEPS = array('.' => '.', ',' => ',', 'momayez' => "\u{066B}");
	const GRP_SEPS = array(',' => ',', '.' => '.', 'space' => ' ', 'apos' => "'", 'arabic' => "\u{066C}", 'none' => '');
	const COMMA_DEC = array('de', 'es', 'it', 'nl', 'pt', 'tr', 'id', 'da', 'el', 'ro', 'hr', 'sl', 'sr', 'vi', 'fr', 'ru', 'pl', 'cs', 'sv', 'nb', 'nn', 'fi', 'uk', 'sk', 'hu', 'bg', 'lt', 'lv', 'et', 'kk', 'uz', 'az', 'ka', 'be', 'mk', 'sq', 'bs', 'is');
	const SPACE_GRP = array('fr', 'ru', 'pl', 'cs', 'sv', 'nb', 'nn', 'fi', 'uk', 'sk', 'hu', 'bg', 'lt', 'lv', 'et', 'kk', 'uz', 'be');
	const RTL_LANGS = array('fa', 'ar', 'he', 'ur', 'ps', 'ckb', 'ug', 'dv', 'yi', 'sd', 'ku');
	const LATIN_ARABIC = array('ar_MA', 'ar_DZ', 'ar_TN', 'ary', 'ar_LY');
	const NUMBER_TYPES = array('number', 'range', 'prcfld', 'calc');

	private static function lang($locale) {
		$p = preg_split('/[_-]/', (string) $locale);
		return strtolower((string) $p[0]);
	}

	public static function is_rtl($locale) { return in_array(self::lang($locale), self::RTL_LANGS, true); }

	/** 'fa', 'ar' or 'latin' for num_digits auto / native and a locale. Twin: scriptOf(). */
	public static function script_of($mode, $locale) {
		if ($mode !== 'native' && $mode !== 'auto') return 'latin';
		$l = self::lang($locale);
		if (in_array($l, array('fa', 'ps', 'ur', 'ckb'), true)) return 'fa';
		if ($l === 'ar' && !in_array(str_replace('-', '_', (string) $locale), self::LATIN_ARABIC, true)) return 'ar';
		return 'latin';
	}

	/** The number keys of a field (spec §5.1) with the defaults of a locale. Twin: EFBCalc.config(). */
	public static function config($f, array $ctx = array()) {
		$f = is_object($f) ? get_object_vars($f) : (is_array($f) ? $f : array());
		$locale = isset($ctx['locale']) && is_string($ctx['locale']) && $ctx['locale'] !== '' ? $ctx['locale'] : 'en_US';
		$l = self::lang($locale);
		$has = function ($k) use ($f) { return array_key_exists($k, $f) && $f[$k] !== null && is_scalar($f[$k]) && self::str($f[$k]) !== ''; };
		$type = isset($f['type']) && is_scalar($f['type']) ? (string) $f['type'] : '';
		$number_type = in_array($type, self::NUMBER_TYPES, true);
		$digits = $number_type && $has('num_digits') && preg_match('/^(auto|latin|native)$/D', (string) $f['num_digits']) ? (string) $f['num_digits'] : 'latin';
		$script = self::script_of($digits, $locale);
		if ($has('num_dec_sep') && isset(self::DEC_SEPS[(string) $f['num_dec_sep']])) $dec = self::DEC_SEPS[(string) $f['num_dec_sep']];
		else $dec = $script !== 'latin' ? "\u{066B}" : (in_array($l, self::COMMA_DEC, true) ? ',' : '.');
		if ($has('num_grp_sep') && isset(self::GRP_SEPS[(string) $f['num_grp_sep']])) $grp = self::GRP_SEPS[(string) $f['num_grp_sep']];
		else $grp = $script !== 'latin' ? "\u{066C}" : ($locale === 'de_CH' ? "'" : (in_array($l, self::SPACE_GRP, true) ? ' ' : (in_array($l, self::COMMA_DEC, true) ? '.' : ',')));
		if ($locale === 'de_CH' && !$has('num_dec_sep')) $dec = '.';
		if ($grp === $dec) $grp = $dec === '.' ? ',' : '.';
		$decimals = $has('num_decimals') && preg_match('/^[0-6]$/D', (string) $f['num_decimals']) ? (int) $f['num_decimals'] : ($type === 'calc' && !$has('num_decimals') ? 2 : null);
		if ($type === 'calc' && $has('num_decimals') && !preg_match('/^[0-6]$/D', (string) $f['num_decimals'])) $decimals = null;
		$cur = $number_type && $has('num_currency') && (string) $f['num_currency'] === 'form' && !empty($ctx['currency']) && is_array($ctx['currency']) ? $ctx['currency'] : null;
		if ($cur) $decimals = isset($cur['d']) && is_int($cur['d']) ? $cur['d'] : 2;
		$affix = function ($k, $k2) use ($f, $has) {
			$v = $has($k) ? (string) $f[$k] : ($k2 !== '' && $has($k2) ? (string) $f[$k2] : '');
			$v = str_replace(array('"', '<', '>'), '', $v);
			return function_exists('mb_substr') ? mb_substr($v, 0, 20, 'UTF-8') : substr($v, 0, 20);
		};
		$dnum = function ($k) use ($f, $has) {
			$v = $has($k) ? trim(Calc::normalize_digits((string) $f[$k])) : '';
			return preg_match('/^-?\d{1,15}(\.\d{1,6})?$/D', $v) ? preg_replace('/^(-?)0+(?=\d)/', '$1', $v) : '';
		};
		$min = $dnum('milen');
		$max = $dnum('mlen');
		if ($max !== '' && (float) $max == 0 && !($has('num_max_zero') && preg_match('/^(1|true)$/D', (string) $f['num_max_zero']))) $max = '';
		$st = $has('num_step') ? self::normalize_digits((string) $f['num_step']) : '';
		$step = preg_match('/^\d{1,9}(\.\d{1,6})?$/D', $st) && (float) $st > 0 ? $st : '';
		return array(
			'type' => $type, 'locale' => $locale, 'script' => $script, 'dec' => $dec, 'grp' => $grp,
			'group' => $number_type && (($has('num_format') && (string) $f['num_format'] === 'group') || (bool) $cur),
			'decimals' => $number_type ? $decimals : null, 'step' => $step, 'min' => $min, 'max' => $max,
			'prefix' => $cur ? '' : $affix('num_prefix', $type === 'number' ? 'prefix' : ''),
			'suffix' => $cur ? '' : $affix('num_suffix', $type === 'number' ? 'suffix' : ''),
			'cur' => $cur ? array('s' => isset($cur['s']) ? (string) $cur['s'] : '', 'd' => $decimals) : null,
			'rtl' => self::is_rtl($locale),
		);
	}

	/** What a visitor typed as a canonical ASCII number: {ok, err: ''|'nan'|'empty', canon}. Twin: parseInput(). */
	public static function parse_input($raw, array $cfg = array()) {
		$s = self::normalize_digits(self::str($raw));
		if (!preg_match('//u', $s)) return array('ok' => false, 'err' => 'nan', 'canon' => '');
		$s = preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}\x{00AD}]/u', '', $s);
		$s = preg_replace('/[\x{2212}\x{2010}-\x{2015}\x{FE63}\x{FF0D}]/u', '-', (string) $s);
		$s = preg_replace('/[\x{00A0}\x{2007}\x{202F}\x{3000}]/u', ' ', (string) $s);
		$s = self::trim_js((string) $s);
		$C = self::chars($s);
		if (count($C) > 64) return array('ok' => false, 'err' => 'nan', 'canon' => '');
		$strip = function ($a, $at_start) use (&$s) {
			if (!is_string($a) || $a === '') return;
			if ($at_start && strpos($s, $a) === 0) $s = self::trim_js((string) substr($s, strlen($a)));
			if (!$at_start && strlen($s) >= strlen($a) && substr($s, -strlen($a)) === $a) $s = self::trim_js((string) substr($s, 0, -strlen($a)));
		};
		$strip(isset($cfg['prefix']) ? $cfg['prefix'] : '', true);
		$strip(isset($cfg['suffix']) ? $cfg['suffix'] : '', false);
		if (!empty($cfg['cur']['s'])) { $strip($cfg['cur']['s'], true); $strip($cfg['cur']['s'], false); }
		if ($s === '') return array('ok' => false, 'err' => 'empty', 'canon' => '');
		$dec = isset($cfg['dec']) && $cfg['dec'] !== '' ? $cfg['dec'] : '.';
		$grp = array_key_exists('grp', $cfg) && $cfg['grp'] !== null ? $cfg['grp'] : ',';
		$neg = false;
		if (substr($s, 0, 1) === '-') { $neg = true; $s = self::trim_js((string) substr($s, 1)); }
		$out = '';
		$seen = false;
		foreach (self::chars($s) as $c) {
			if (self::is_digit($c)) { $out .= $c; continue; }
			if ($c === "\u{066B}" || $c === $dec) { if ($seen) return array('ok' => false, 'err' => 'nan', 'canon' => ''); $seen = true; $out .= '.'; continue; }
			if ($c === "\u{066C}" || $c === "\u{060C}" || $c === $grp || $c === ' ' || $c === "'" || ($c === ',' && $dec !== ',')) { if ($seen) return array('ok' => false, 'err' => 'nan', 'canon' => ''); continue; }
			if ($c === '.' && $dec !== '.' && $grp !== '.') { if ($seen) return array('ok' => false, 'err' => 'nan', 'canon' => ''); $seen = true; $out .= '.'; continue; }
			return array('ok' => false, 'err' => 'nan', 'canon' => '');
		}
		if (!preg_match('/^(\d+\.?\d*|\.\d+)$/D', $out)) return array('ok' => false, 'err' => 'nan', 'canon' => '');
		if (substr($out, 0, 1) === '.') $out = '0' . $out;
		$out = preg_replace('/^0+(?=\d)/', '', preg_replace('/\.$/', '', $out));
		if (strpos($out, '.') !== false) $out = rtrim(rtrim($out, '0'), '.');
		if ($neg && preg_match('/[1-9]/', $out)) $out = '-' . $out;
		return array('ok' => true, 'err' => '', 'canon' => $out);
	}

	/** String.prototype.trim() of the characters this file produces. */
	private static function trim_js($s) {
		return (string) preg_replace('/^[\s\x{00A0}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}\x{FEFF}]+|[\s\x{00A0}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}\x{FEFF}]+$/u', '', $s);
	}

	/** Sign-aware comparison of two decimal strings without floats. */
	public static function cmp_dec($a, $b) {
		$a = (string) $a; $b = (string) $b;
		$na = substr($a, 0, 1) === '-';
		$nb = substr($b, 0, 1) === '-';
		if ($na !== $nb) return $na ? -1 : 1;
		$pa = explode('.', preg_replace('/-/', '', $a, 1));
		$pb = explode('.', preg_replace('/-/', '', $b, 1));
		$ia = preg_replace('/^0+(?=\d)/', '', $pa[0]);
		$ib = preg_replace('/^0+(?=\d)/', '', $pb[0]);
		if (strlen($ia) !== strlen($ib)) $r = strlen($ia) < strlen($ib) ? -1 : 1;
		elseif ($ia !== $ib) $r = strcmp($ia, $ib) < 0 ? -1 : 1;
		else {
			$da = isset($pa[1]) ? $pa[1] : '';
			$db = isset($pb[1]) ? $pb[1] : '';
			$n = max(strlen($da), strlen($db));
			$da = str_pad($da, $n, '0');
			$db = str_pad($db, $n, '0');
			$r = $da === $db ? 0 : (strcmp($da, $db) < 0 ? -1 : 1);
		}
		return $na ? -$r : $r;
	}

	private static function dec_count($s) {
		$i = strpos($s, '.');
		return $i === false ? 0 : strlen($s) - $i - 1;
	}

	private static function scaled_digits($x, $k) {
		$p = explode('.', preg_replace('/-/', '', $x, 1));
		$d = isset($p[1]) ? $p[1] : '';
		$d = str_pad($d, $k, '0');
		return preg_replace('/^0+(?=\d)/', '', $p[0] . $d);
	}

	private static function mod_digits($x, $m) {
		$r = 0;
		$len = strlen($x);
		for ($i = 0; $i < $len; $i++) $r = ($r * 10 + (ord($x[$i]) - 48)) % $m;
		return $r;
	}

	/** Whether value - base is a whole multiple of step, with scaled integers. Twin: onStep(). */
	public static function on_step($value, $step, $base = '0') {
		if ($step === '' || $step === null) return true;
		$base = $base === '' || $base === null ? '0' : (string) $base;
		$value = (string) $value; $step = (string) $step;
		$k = max(self::dec_count($value), self::dec_count($step), self::dec_count($base));
		$m = (int) self::scaled_digits($step, $k);
		if (!($m > 0) || $m >= 562949953421312) return true;
		$res = function ($x) use ($k, $m) {
			$r = Calc::mod_digits_pub(Calc::scaled_digits_pub($x, $k), $m);
			return substr($x, 0, 1) === '-' && $r !== 0 ? $m - $r : $r;
		};
		return $res($value) === $res($base);
	}

	/** @internal */
	public static function mod_digits_pub($x, $m) { return self::mod_digits($x, $m); }
	/** @internal */
	public static function scaled_digits_pub($x, $k) { return self::scaled_digits($x, $k); }

	/** '' or 'dec' | 'step' | 'min' | 'max' (spec §10.1 order). Twin: check(). */
	public static function check($canon, array $cfg) {
		$canon = (string) $canon;
		if (isset($cfg['decimals']) && $cfg['decimals'] !== null && $cfg['decimals'] !== '' && self::dec_count($canon) > (int) $cfg['decimals']) return 'dec';
		if (!empty($cfg['step']) && !self::on_step($canon, $cfg['step'], isset($cfg['min']) && $cfg['min'] !== '' ? $cfg['min'] : '0')) return 'step';
		if (isset($cfg['min']) && $cfg['min'] !== '' && self::cmp_dec($canon, $cfg['min']) < 0) return 'min';
		if (isset($cfg['max']) && $cfg['max'] !== '' && self::cmp_dec($canon, $cfg['max']) > 0) return 'max';
		return '';
	}

	/* ------------------------------------------------------------------ display */

	public static function to_script($s, $script) {
		if ($script !== 'fa' && $script !== 'ar') return (string) $s;
		$base = $script === 'fa' ? 0x06F0 : 0x0660;
		$m = array();
		for ($i = 0; $i < 10; $i++) $m[(string) $i] = self::chr($base + $i);
		return strtr((string) $s, $m);
	}

	/** A canonical number as the visitor reads it; anything else unchanged. Twin: EFBCalc.format(). */
	public static function format($canon, array $cfg) {
		$raw = self::str($canon);
		$s = self::trim_js($raw);
		if (!preg_match('/^-?\d+(\.\d+)?$/D', $s)) return $raw;
		$neg = substr($s, 0, 1) === '-';
		$p = explode('.', ltrim($s, '-'));
		$ip = $p[0];
		$dp = isset($p[1]) ? $p[1] : '';
		if (!empty($cfg['cur'])) $dp = str_pad($dp, (int) $cfg['cur']['d'], '0');
		if (!empty($cfg['group']) && isset($cfg['grp']) && $cfg['grp'] !== '') {
			$out = '';
			for ($i = strlen($ip); $i > 0; $i -= 3) $out = substr($ip, max(0, $i - 3), $i - max(0, $i - 3)) . ($out !== '' ? $cfg['grp'] . $out : '');
			$ip = $out;
		}
		$body = ($neg ? '-' : '') . $ip . ($dp !== '' ? (isset($cfg['dec']) && $cfg['dec'] !== '' ? $cfg['dec'] : '.') . $dp : '');
		$body = self::to_script($body, isset($cfg['script']) ? $cfg['script'] : 'latin');
		if (!empty($cfg['cur']) && $cfg['cur']['s'] !== '') return !empty($cfg['rtl']) ? $body . ' ' . $cfg['cur']['s'] : $cfg['cur']['s'] . $body;
		$pre = isset($cfg['prefix']) ? (string) $cfg['prefix'] : '';
		$suf = isset($cfg['suffix']) ? (string) $cfg['suffix'] : '';
		return ($pre !== '' ? $pre . (preg_match('/[A-Za-z\x{0600}-\x{06FF}]$/u', $pre) ? ' ' : '') : '') . $body . ($suf !== '' ? (preg_match('/^[A-Za-z\x{0600}-\x{06FF}]/u', $suf) ? ' ' : '') . $suf : '');
	}

	/** A low..high pair of a two-handle slider, or null. Twin: splitRange(). */
	public static function split_range($v) {
		if (!preg_match('/^\s*(-?\d+(?:\.\d+)?)\s*\.\.\s*(-?\d+(?:\.\d+)?)\s*$/D', self::normalize_digits(self::str($v)), $m)) return null;
		return array('lo' => $m[1], 'hi' => $m[2]);
	}

	/* ================================================================== WordPress part */

	/** The F04 keys of a number field (spec §5.1); a field with none of them renders and stores as before. */
	const NUM_KEYS = array('num_step', 'num_decimals', 'num_format', 'num_dec_sep', 'num_grp_sep', 'num_digits', 'num_prefix', 'num_suffix', 'num_currency', 'num_max_zero');

	/** Default English texts of the F04 messages (the phrase map in functions.php is the source; these are fallbacks). */
	const DEFAULTS = array(
		'numStepM_' => '%1$s must be a multiple of %2$s.', 'numDecM_' => '%1$s allows at most %2$s decimal places.', 'numNanM_' => 'Please enter a number in %s.',
		'calcErr' => 'This value cannot be calculated from the answers given.', 'numRange' => 'Between %1$s and %2$s',
		'mcplen' => 'Please enter a number that is greater than or equal to NN.', 'mxcplen' => 'Please enter a number that is less than or equal to NN',
		'mnvvXXX_' => 'Please enter valid value for the %s field.', 'rngOrder' => '%s: the lower value must not be above the upper value.',
	);

	private static function f($f) {
		return is_object($f) ? get_object_vars($f) : (is_array($f) ? $f : array());
	}

	private static function has_key(array $f, $k) {
		return array_key_exists($k, $f) && is_scalar($f[$k]) && (string) $f[$k] !== '';
	}

	/** Whether a slider has two handles (range_mode dual). */
	public static function is_dual($f) {
		$f = self::f($f);
		return isset($f['type'], $f['range_mode']) && $f['type'] === 'range' && $f['range_mode'] === 'dual';
	}

	/** The limits, step and starting pair of a slider: {min, max, step, lo, hi} as decimal strings. */
	public static function range_setup($f, $form0 = null) {
		$f = self::f($f);
		$cfg = self::field_config($f, $form0);
		$min = isset($f['milen']) && is_scalar($f['milen']) && preg_match('/^-?\d{1,15}(\.\d{1,6})?$/D', trim((string) $f['milen'])) ? trim((string) $f['milen']) : '0';
		$max = isset($f['mlen']) && is_scalar($f['mlen']) && preg_match('/^-?\d{1,15}(\.\d{1,6})?$/D', trim((string) $f['mlen'])) && (float) $f['mlen'] != 0 ? trim((string) $f['mlen']) : '100';
		if ((float) $max < (float) $min) $max = $min;
		$pair = self::split_range(isset($f['value']) ? $f['value'] : '');
		$lo = $pair ? $pair['lo'] : $min;
		$hi = $pair ? $pair['hi'] : $max;
		if ((float) $lo < (float) $min || (float) $lo > (float) $max) $lo = $min;
		if ((float) $hi > (float) $max || (float) $hi < (float) $lo) $hi = $max;
		return array('min' => $min, 'max' => $max, 'step' => $cfg['step'] !== '' ? $cfg['step'] : 'any', 'lo' => $lo, 'hi' => $hi, 'cfg' => $cfg);
	}

	/**
	 * The page markup of a two-handle slider (inside the field's -f wrapper; builder twin efbRange2Canvas()):
	 * two native range inputs on one track, each with its own label ("Minimum: …", "Maximum: …"), the pair
	 * "lo – hi" announced politely, the hidden answer "lo..hi". calc-efb.js keeps lo <= hi, PageUp / PageDown
	 * move ten steps; a right-to-left page reverses the track (dir on the wrapper).
	 */
	public static function range2_markup($vj, $id, $form_id, $texts = array(), $form0 = null, $disabled = false) {
		$f = self::f($vj);
		$e = function ($v) { return function_exists('esc_attr') ? esc_attr($v) : htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
		$h = function ($v) { return function_exists('esc_html') ? esc_html($v) : htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
		$r = self::range_setup($f, $form0);
		$json = function_exists('wp_json_encode') ? wp_json_encode(self::client_config($r['cfg']), JSON_UNESCAPED_UNICODE) : json_encode(self::client_config($r['cfg']), JSON_UNESCAPED_UNICODE);
		$name = isset($f['name']) && is_scalar($f['name']) ? (string) $f['name'] : '';
		$lab_lo = self::t('rngLo', $texts) . ': ' . $name;
		$lab_hi = self::t('rngHi', $texts) . ': ' . $name;
		$rtl = function_exists('is_rtl') && is_rtl();
		$req = isset($f['required']) && in_array($f['required'], array(1, '1', true), true);
		$dis = $disabled ? ' disabled' : '';
		$in = function ($which, $val, $label) use ($id, $r, $e, $dis) {
			return '<input type="range" class="efb efb-range2-' . $which . '" id="' . $e($id) . '_' . $which . '" min="' . $e($r['min']) . '" max="' . $e($r['max']) . '" step="' . $e($r['step']) . '" value="' . $e($val) . '" aria-label="' . $e($label) . '" aria-valuetext="' . $e(Calc::format($val, $r['cfg'])) . '"' . $dis . '>';
		};
		return '<div class="efb efb-range2' . ($req ? ' required' : '') . '" data-efb-range2="1" data-vid="' . $e($id) . '" data-formid="' . $e($form_id) . '" data-numcfg="' . $e($json) . '" dir="' . ($rtl ? 'rtl' : 'ltr') . '" role="group" aria-labelledby="' . $e($id) . '_labG">'
			. '<div class="efb efb-range2-track"><span class="efb efb-range2-fill" aria-hidden="true"></span>' . $in('lo', $r['lo'], $lab_lo) . $in('hi', $r['hi'], $lab_hi) . '</div>'
			. '<output class="efb efb-range2-out fs-6" id="' . $e($id) . '_rv" aria-live="polite" dir="auto">' . $h(Calc::format($r['lo'], $r['cfg']) . ' – ' . Calc::format($r['hi'], $r['cfg'])) . '</output>'
			. '<input type="hidden" class="efb emsFormBuilder_v" data-vid="' . $e($id) . '" data-type="range" data-formid="' . $e($form_id) . '" id="' . $e($id) . '_" value="' . $e($r['lo'] . '..' . $r['hi']) . '">'
			. '</div>';
	}

	/** Whether a number / range / calc field uses an F04 key (it then renders as a typed-number box). */
	public static function is_f4($f) {
		$f = self::f($f);
		$type = isset($f['type']) && is_scalar($f['type']) ? (string) $f['type'] : '';
		if (!in_array($type, array('number', 'range', 'calc'), true)) return false;
		foreach (self::NUM_KEYS as $k) if (self::has_key($f, $k)) return true;
		return false;
	}

	/** Keys of the save rule below (the option key calc, the field keys). */
	const FIELD_KEYS = array('num_step', 'num_decimals', 'num_format', 'num_dec_sep', 'num_grp_sep', 'num_digits', 'num_prefix', 'num_suffix', 'num_currency', 'num_max_zero',
		'formula', 'formula_err', 'calc_show', 'calc_empty', 'calc_as_price', 'range_mode');
	const ENUMS = array(
		'num_format' => array('none', 'group'), 'num_dec_sep' => array('.', ',', 'momayez'), 'num_grp_sep' => array(',', '.', 'space', 'apos', 'arabic', 'none'),
		'num_digits' => array('auto', 'latin', 'native'), 'num_currency' => array('form'), 'calc_show' => array('visible', 'hidden'), 'calc_empty' => array('zero', 'blank'),
		'range_mode' => array('single', 'dual'),
	);
	const MAX_FORMULA = 2000;

	/**
	 * One F04 key as stored ('' = not set). $type = the row's type: num_digits, num_prefix and num_suffix belong
	 * to F01's numeric mask on other types (they are not touched here). Twin: EFBCalc.sanitizeKey().
	 */
	public static function sanitize_key($k, $v, $type = '') {
		if ($v === null || is_array($v) || is_object($v)) return '';
		if (is_bool($v)) $v = $v ? '1' : '';
		$s = trim(self::normalize_digits((string) $v));
		if ($k === 'num_step' || $k === 'calc') $s = str_replace("\u{066B}", '.', $s);
		switch ($k) {
			case 'num_step':
				return preg_match('/^\d{1,9}(\.\d{1,6})?$/D', $s) && (float) $s > 0 ? preg_replace('/^0+(?=\d)/', '', $s) : '';
			case 'num_decimals':
				return preg_match('/^[0-6]$/D', $s) ? $s : '';
			case 'calc':
				return preg_match('/^-?\d{1,15}(\.\d{1,6})?$/D', $s) ? $s : '';
			case 'num_max_zero':
			case 'calc_as_price':
				return in_array(strtolower($s), array('1', 'true', 'on'), true) ? '1' : '';
			case 'num_prefix':
			case 'num_suffix':
				$t = preg_replace('/<[^>]*>/', '', (string) $v);
				$t = str_replace(array('"', '<', '>', '`'), '', (string) $t);
				$t = trim((string) preg_replace('/[\x00-\x20\x7F]+/', ' ', (string) $t));
				return function_exists('mb_substr') ? mb_substr($t, 0, 20, 'UTF-8') : substr($t, 0, 20);
			case 'formula':
				$f = trim((string) preg_replace('/[\x00-\x1F\x7F]+/', ' ', self::normalize_formula((string) $v)));
				if (strlen($f) > self::MAX_FORMULA || !preg_match('/^[A-Za-z0-9_{}().,:\s+\-*\/]*$/D', $f)) return '';
				return (string) preg_replace('/ {2,}/', ' ', $f);
			case 'formula_err':
				return '';
		}
		if ($k === 'num_dec_sep') { $map = array("\u{066B}" => 'momayez'); if (isset($map[$s])) return $map[$s]; }
		if ($k === 'num_grp_sep') { $map = array("\u{066C}" => 'arabic', "'" => 'apos', '' => ''); if ((string) $v === ' ') return 'space'; if (isset($map[$s])) return $map[$s]; }
		if (isset(self::ENUMS[$k])) {
			foreach (self::ENUMS[$k] as $e) if (strcasecmp($e, $s) === 0) return $e;
			return '';
		}
		return '';
	}

	/** Whether a key is one of F04's on this row type. */
	public static function is_key($k, $type = '') {
		if ($k === 'calc') return $type === 'option';
		if (in_array($k, array('num_digits', 'num_prefix', 'num_suffix'), true)) return in_array($type, self::NUMBER_TYPES, true);
		return in_array($k, self::FIELD_KEYS, true);
	}

	/** Types a formula can read as a number (or a list of choices); everything else is text (a number or an error). */
	const LAYOUT_TYPES = array('option', 'step', 'form', 'r_matrix', 'heading', 'link', 'html', 'buttonNav', 'submit');

	/**
	 * The formula problems of a form's calc fields: id => {code, pos, near, ref}. code: calcSyntax / calcLong /
	 * calcTokens / calcDepth / calcRefs / calcFn / calcArgs / calcEmpty (parse), calcRef (no such field),
	 * calcForbidden (a password, file, signature, map or html field), calcSelf, calcCycle (through other calc
	 * fields). Twin: EFBCalc.formErrors(); the builder, efbBuilderApi.validateFormula() and the AutoPilot
	 * validator give the same answer.
	 */
	public static function form_errors($rows) {
		$out = array();
		if (!is_array($rows)) return $out;
		$types = array();
		$calc = array();
		foreach ($rows as $i => $r) {
			$r = self::f($r);
			if ($i < 1 || !isset($r['id_'], $r['type']) || !is_scalar($r['id_']) || !is_scalar($r['type'])) continue;
			if ((string) $r['type'] === 'option' || (string) $r['type'] === 'step') continue;
			$types[(string) $r['id_']] = (string) $r['type'];
			if ((string) $r['type'] === 'calc') $calc[(string) $r['id_']] = isset($r['formula']) && is_scalar($r['formula']) ? (string) $r['formula'] : '';
		}
		$deps = array();
		foreach ($calc as $id => $formula) {
			$p = self::parse($formula);
			if (!$p['ok']) { $out[$id] = array('code' => $p['code'], 'pos' => $p['pos'], 'near' => $p['near'], 'ref' => ''); continue; }
			$deps[$id] = array();
			foreach ($p['refs'] as $ref) {
				$rid = $ref['id'];
				if ($rid === $id) { $out[$id] = array('code' => 'calcSelf', 'pos' => -1, 'near' => '{' . $rid . '}', 'ref' => $rid); break; }
				if (!isset($types[$rid])) { $out[$id] = array('code' => 'calcRef', 'pos' => -1, 'near' => '{' . $rid . '}', 'ref' => $rid); break; }
				if (in_array($types[$rid], self::FORBIDDEN_TYPES, true) || in_array($types[$rid], self::LAYOUT_TYPES, true)) { $out[$id] = array('code' => 'calcForbidden', 'pos' => -1, 'near' => '{' . $rid . '}', 'ref' => $rid); break; }
				if ($types[$rid] === 'calc' && !in_array($rid, $deps[$id], true)) $deps[$id][] = $rid;
			}
		}
		foreach (self::cycle_ids($deps) as $id) {
			if (!isset($out[$id])) $out[$id] = array('code' => 'calcCycle', 'pos' => -1, 'near' => '', 'ref' => '');
		}
		ksort($out, SORT_STRING);
		return $out;
	}

	/** Ids of the calc fields that are on a cycle of references. */
	public static function cycle_ids(array $deps) {
		$state = array();
		$on = array();
		$visit = function ($id, array $stack) use (&$visit, &$state, &$on, $deps) {
			if (isset($state[$id]) && $state[$id] === 2) return;
			if (isset($state[$id]) && $state[$id] === 1) {
				$k = array_search($id, $stack, true);
				foreach (array_slice($stack, $k === false ? 0 : $k) as $c) $on[$c] = true;
				return;
			}
			$state[$id] = 1;
			$stack[] = $id;
			foreach (isset($deps[$id]) ? $deps[$id] : array() as $d) if (isset($deps[$d])) $visit($d, $stack);
			$state[$id] = 2;
		};
		$ids = array_keys($deps);
		sort($ids, SORT_STRING);
		foreach ($ids as $id) $visit($id, array());
		$r = array_keys($on);
		sort($r, SORT_STRING);
		return $r;
	}

	/** The calc fields in the order they must be computed (references first; a cycle's fields left out). */
	public static function topo_order(array $rows) {
		$errs = self::form_errors($rows);
		$deps = array();
		foreach ($rows as $i => $r) {
			$r = self::f($r);
			if ($i < 1 || ($r['type'] ?? '') !== 'calc' || !isset($r['id_'])) continue;
			$deps[(string) $r['id_']] = isset($errs[(string) $r['id_']]) ? null : self::ref_ids(isset($r['formula']) ? (string) $r['formula'] : '');
		}
		$order = array();
		$done = array();
		$visit = function ($id) use (&$visit, &$order, &$done, $deps) {
			if (isset($done[$id])) return;
			$done[$id] = true;
			foreach ((array) $deps[$id] as $d) if (array_key_exists($d, $deps)) $visit($d);
			$order[] = $id;
		};
		foreach (array_keys($deps) as $id) $visit($id);
		return $order;
	}

	const CHOICE_TYPES = array('radio', 'checkbox', 'select', 'multiselect', 'payRadio', 'payCheckbox', 'paySelect', 'payMultiselect', 'imgRadio', 'chlRadio', 'chlCheckBox');

	/** The number of a choice in formulas: its calc value, else a numeric stored value, else 0. Twin: optionNumber(). */
	public static function option_number($o) {
		$o = self::f($o);
		$c = isset($o['calc']) && is_scalar($o['calc']) ? trim(self::normalize_digits((string) $o['calc'])) : '';
		if (preg_match('/^-?\d{1,15}(\.\d{1,6})?$/D', $c)) return (float) $c;
		$v = isset($o['opt_value']) && is_scalar($o['opt_value']) ? trim(self::normalize_digits((string) $o['opt_value'])) : '';
		if (preg_match('/^-?\d{1,15}(\.\d{1,6})?$/D', $v)) return (float) $v;
		return 0.0;
	}

	/**
	 * Every calc field's result from the answers: id => {ok, value, code}. Calc rows among $rows are ignored
	 * (computed here, never read). $opts: hidden (ids hidden by Conditional Logic), locale, currency. Twin:
	 * EFBCalc.evaluateForm().
	 */
	public static function evaluate_form($structure, $rows, array $opts = array()) {
		$hidden = array();
		foreach (isset($opts['hidden']) ? (array) $opts['hidden'] : array() as $h) $hidden[(string) $h] = true;
		$fields = array();
		$options = array();
		foreach ((array) $structure as $i => $r) {
			$r = self::f($r);
			if ($i < 1 || !isset($r['id_']) || !is_scalar($r['id_'])) continue;
			$type = isset($r['type']) ? (string) $r['type'] : '';
			if ($type === 'option') { $options[isset($r['parent']) ? (string) $r['parent'] : ''][] = $r; continue; }
			if ($type === 'step') continue;
			$fields[(string) $r['id_']] = $r;
		}
		$answers = array();
		foreach ((array) $rows as $r) {
			if (!is_array($r) || !isset($r['id_']) || !is_scalar($r['id_']) || (isset($r['type']) && $r['type'] === 'calc')) continue;
			$id = (string) $r['id_'];
			if (!isset($fields[$id]) || ($fields[$id]['type'] ?? '') === 'calc') continue;
			$answers[$id][] = $r;
		}
		$errs = self::form_errors($structure);
		$out = array();
		$sv = function ($v) { return $v === null || is_array($v) || is_object($v) ? '' : (string) $v; };
		$selected = function ($id) use (&$answers, &$options, $sv) {
			$ops = isset($options[$id]) ? $options[$id] : array();
			$picked = array();
			$seen = array();
			$add = function ($o) use (&$picked, &$seen) { if ($o && !isset($seen[(string) $o['id_']])) { $seen[(string) $o['id_']] = true; $picked[] = $o; } };
			foreach (isset($answers[$id]) ? $answers[$id] : array() as $r) {
				$ob = isset($r['id_ob']) && is_scalar($r['id_ob']) ? (string) $r['id_ob'] : '';
				$hit = null;
				foreach ($ops as $o) if ((string) $o['id_'] === $ob) $hit = $o;
				if ($hit) { $add($hit); continue; }
				if (isset($r['ids']) && is_string($r['ids']) && $r['ids'] !== '') {
					foreach (explode(',', $r['ids']) as $x) foreach ($ops as $o) if ((string) $o['id_'] === $x) $add($o);
					continue;
				}
				foreach (explode('@efb!', $sv(isset($r['value']) ? $r['value'] : '')) as $label) {
					if ($label === '') continue;
					foreach ($ops as $o) if ($sv(isset($o['value']) ? $o['value'] : '') === $label) { $add($o); break; }
				}
			}
			return $picked;
		};
		$raw_value = function ($id) use (&$answers) {
			foreach (isset($answers[$id]) ? $answers[$id] : array() as $r) {
				if (isset($r['value']) && is_scalar($r['value']) && (string) $r['value'] !== '') return (string) $r['value'];
			}
			return '';
		};
		$resolve = function ($ref) use (&$fields, &$hidden, &$out, $selected, $raw_value) {
			$id = $ref['id'];
			$part = $ref['part'];
			if (!isset($fields[$id])) return array('error' => 'calcRef');
			$f = $fields[$id];
			$t = isset($f['type']) ? (string) $f['type'] : '';
			if (in_array($t, Calc::FORBIDDEN_TYPES, true) || in_array($t, Calc::LAYOUT_TYPES, true)) return array('error' => 'calcForbidden');
			if (isset($hidden[$id])) return $part === 'count' || $part === 'price' ? 0.0 : null;
			if ($t === 'calc') {
				if ($part !== '' && $part !== 'value') return array('error' => 'calcRef');
				if (!isset($out[$id])) return null;
				if (!$out[$id]['ok']) return array('error' => $out[$id]['code']);
				return $out[$id]['value'] === '' ? null : (float) $out[$id]['value'];
			}
			$choice = in_array($t, Calc::CHOICE_TYPES, true);
			if ($part === 'count') return $choice ? (float) count($selected($id)) : ($raw_value($id) === '' ? 0.0 : 1.0);
			if ($part === 'price') {
				if (!$choice) return 0.0;
				$sp = 0.0;
				foreach ($selected($id) as $o) {
					$p = isset($o['price']) && is_scalar($o['price']) ? trim(Calc::normalize_digits((string) $o['price'])) : '';
					if (preg_match('/^-?\d{1,15}(\.\d{1,6})?$/D', $p)) $sp += (float) $p;
				}
				return $sp;
			}
			if ($part === 'lo' || $part === 'hi') {
				$rg = Calc::split_range($raw_value($id));
				if (!$rg) return $raw_value($id) === '' ? null : array('error' => 'calcRef');
				return (float) ($part === 'lo' ? $rg['lo'] : $rg['hi']);
			}
			if ($part !== '' && $part !== 'value' && $part !== 'calc') return array('error' => 'calcRef');
			if ($choice) {
				$picked = $selected($id);
				if (!$picked) return null;
				$sum = 0.0;
				foreach ($picked as $o) $sum += Calc::option_number($o);
				return $sum;
			}
			$raw = $raw_value($id);
			if ($t === 'range') { $rr = Calc::split_range($raw); if ($rr) return (float) $rr['hi'] - (float) $rr['lo']; }
			return Calc::value_of($raw);
		};
		foreach (self::topo_order($structure) as $id) {
			if (!isset($fields[$id]) || isset($hidden[$id])) continue;
			$f = $fields[$id];
			if (isset($errs[$id])) { $out[$id] = array('ok' => false, 'value' => '', 'code' => $errs[$id]['code']); continue; }
			$p = self::parse(isset($f['formula']) && is_scalar($f['formula']) ? (string) $f['formula'] : '');
			if (!$p['ok']) { $out[$id] = array('ok' => false, 'value' => '', 'code' => $p['code']); continue; }
			$c = self::config($f, array('locale' => isset($opts['locale']) ? $opts['locale'] : 'en_US', 'currency' => isset($opts['currency']) ? $opts['currency'] : null));
			$r = self::finish(self::evaluate($p['ast'], $resolve, array('empty' => isset($f['calc_empty']) && $f['calc_empty'] === 'blank' ? 'blank' : 'zero')), $c['decimals'] === null ? '' : $c['decimals']);
			$out[$id] = $r['ok'] ? array('ok' => true, 'value' => $r['value'], 'code' => '') : array('ok' => false, 'value' => '', 'code' => $r['code']);
		}
		return $out;
	}

	/**
	 * The submit (every endpoint: forms/message/add, payments/create, the offline restore, API clients):
	 * the calc rows of the checked answers, computed here. Any calc row in $items (the browser's) is dropped.
	 * $ignored = ids hidden by Conditional Logic. {ok, rows} or {ok:false, m, field_id, code}.
	 */
	public static function submit_rows($form, array $items, array $ignored = array(), $lanText = array()) {
		$calc = array();
		$ids = array();
		foreach ((array) $form as $i => $r) {
			$r = self::f($r);
			if ($i > 0 && isset($r['type'], $r['id_']) && $r['type'] === 'calc') { $calc[] = $r; $ids[(string) $r['id_']] = true; }
		}
		$items = array_values(array_filter($items, function ($it) use ($ids) {
			return !(is_array($it) && ((isset($it['type']) && $it['type'] === 'calc') || (isset($it['id_']) && is_scalar($it['id_']) && isset($ids[(string) $it['id_']]))));
		}));
		if (!$calc || !self::plan_ok('calc')) return array('ok' => true, 'rows' => $items);
		$form0 = isset($form[0]) ? $form[0] : null;
		$res = self::evaluate_form($form, $items, array('hidden' => $ignored, 'locale' => self::locale(), 'currency' => self::currency($form0)));
		$session = '';
		$fid = '';
		foreach ($items as $it) {
			if (is_array($it) && isset($it['session']) && is_scalar($it['session'])) { $session = (string) $it['session']; $fid = isset($it['form_id']) && is_scalar($it['form_id']) ? (string) $it['form_id'] : ''; break; }
		}
		$hid = array_flip(array_map('strval', $ignored));
		foreach ($calc as $f) {
			$id = (string) $f['id_'];
			if (isset($hid[$id])) continue;
			$r = isset($res[$id]) ? $res[$id] : array('ok' => true, 'value' => '', 'code' => '');
			$name = isset($f['name']) && is_scalar($f['name']) ? (string) $f['name'] : '';
			$req = isset($f['required']) && in_array($f['required'], array(1, '1', true), true);
			if (!$r['ok'] || $r['value'] === '') {
				if ($req) {
					$bname = '<b>' . (function_exists('esc_html') ? esc_html($name) : htmlspecialchars($name, ENT_QUOTES, 'UTF-8')) . '</b>';
					return array('ok' => false, 'm' => $r['ok'] ? self::t('mnvvXXX_', $lanText, array($bname)) : self::t('calcErr', $lanText), 'field_id' => $id, 'code' => $r['ok'] ? 'efb_calc_required' : 'efb_calc_error');
				}
				continue;
			}
			$row = array('id_' => $id, 'name' => $name, 'id_ob' => $id, 'amount' => isset($f['amount']) && is_scalar($f['amount']) ? (string) $f['amount'] : '', 'type' => 'calc', 'value' => $r['value'], 'session' => $session, 'form_id' => $fid);
			$row = self::with_display($row, $f, $form0);
			$items[] = $row;
		}
		return array('ok' => true, 'rows' => $items);
	}

	/**
	 * SitePilot contract (efb_api_form_validate, §7.2): errors that refuse a definition (a formula that does not
	 * parse or reads a field that is missing / forbidden / on a cycle: calc_formula with formula_code; an F04 key
	 * the save rules cannot keep: calc_invalid) and risk flags (payment for a calc used as the amount).
	 * The same codes as efbBuilderApi.validateFormula() and the save (form_errors()).
	 */
	public static function validate_rows(array $rows) {
		$errors = array();
		$flags = array();
		foreach ($rows as $i => $r) {
			if (!is_array($r) || $i < 1) continue;
			$type = isset($r['type']) && is_scalar($r['type']) ? (string) $r['type'] : '';
			foreach ($r as $k => $v) {
				if (!is_string($k) || !self::is_key($k, $type) || $k === 'formula_err' || $v === '' || $v === null) continue;
				if (self::sanitize_key($k, $v, $type) === '' && !in_array($v, array(false, 0, '0'), true)) $errors[] = array('code' => 'calc_invalid', 'path' => 'rows[' . $i . '].' . $k);
			}
			if ($type === 'calc' && isset($r['calc_as_price']) && in_array($r['calc_as_price'], array('1', 1, true), true)) $flags['payment'] = true;
		}
		$ids = array();
		foreach ($rows as $i => $r) if (is_array($r) && isset($r['id_']) && is_scalar($r['id_'])) $ids[(string) $r['id_']] = $i;
		foreach (self::form_errors($rows) as $id => $e) {
			$errors[] = array('code' => 'calc_formula', 'path' => 'rows[' . (isset($ids[$id]) ? $ids[$id] : '?') . '].formula', 'field' => (string) $id, 'formula_code' => $e['code'] === 'calcSelf' ? 'calcCycle' : $e['code'], 'near' => $e['near']);
		}
		return array('errors' => $errors, 'flags' => array_keys($flags));
	}

	/** One formula against a form (the twin of efbBuilderApi.validateFormula()): {ok, code, pos, near, refs}. */
	public static function validate_formula($formula, array $rows) {
		$p = self::parse((string) $formula);
		if (!$p['ok']) return array('ok' => false, 'code' => $p['code'], 'pos' => $p['pos'], 'near' => $p['near'], 'refs' => array());
		if (!isset($rows[0]) || !is_array($rows[0]) || isset($rows[0]['id_'])) array_unshift($rows, array('type' => 'form'));
		$rows[] = array('id_' => '__efb_formula_check__', 'type' => 'calc', 'formula' => (string) $formula);
		$e = self::form_errors($rows);
		$e = isset($e['__efb_formula_check__']) ? $e['__efb_formula_check__'] : null;
		return $e ? array('ok' => false, 'code' => $e['code'] === 'calcSelf' ? 'calcCycle' : $e['code'], 'pos' => $e['pos'], 'near' => $e['near'], 'refs' => self::ref_ids($p)) : array('ok' => true, 'code' => '', 'pos' => -1, 'near' => '', 'refs' => self::ref_ids($p));
	}

	/**
	 * The submitted rows with every calc row replaced by the server's result (for Conditional Logic, which reads
	 * the answers before the field loop; number comparisons on a calc field then use the server's value).
	 * Nothing changes for a form without calc fields.
	 */
	public static function logic_rows($form, array $rows) {
		$calc = array();
		foreach ((array) $form as $i => $r) {
			$r = self::f($r);
			if ($i > 0 && isset($r['type'], $r['id_']) && $r['type'] === 'calc') $calc[(string) $r['id_']] = $r;
		}
		if (!$calc) return $rows;
		$keep = array();
		foreach ($rows as $r) if (!(is_array($r) && ((isset($r['type']) && $r['type'] === 'calc') || (isset($r['id_']) && is_scalar($r['id_']) && isset($calc[(string) $r['id_']]))))) $keep[] = $r;
		if (!self::plan_ok('calc')) return $keep;
		$res = self::evaluate_form($form, $keep, array('locale' => self::locale(), 'currency' => self::currency(isset($form[0]) ? $form[0] : null)));
		foreach ($calc as $id => $f) {
			if (!isset($res[$id]) || !$res[$id]['ok'] || $res[$id]['value'] === '') continue;
			$keep[] = array('id_' => $id, 'name' => isset($f['name']) && is_scalar($f['name']) ? (string) $f['name'] : '', 'id_ob' => $id, 'amount' => isset($f['amount']) && is_scalar($f['amount']) ? (string) $f['amount'] : '', 'type' => 'calc', 'value' => $res[$id]['value']);
		}
		return $keep;
	}

	/** The calc fields of a form used as an amount to pay (calc_as_price = "1"). */
	public static function price_fields($form) {
		$out = array();
		foreach ((array) $form as $i => $r) {
			$r = self::f($r);
			if ($i > 0 && isset($r['type'], $r['id_']) && $r['type'] === 'calc' && isset($r['calc_as_price']) && in_array($r['calc_as_price'], array('1', 1, true), true)) $out[] = $r;
		}
		return $out;
	}

	/**
	 * G (Pro): the amounts of the calc fields used as a price, computed here from the answers (any calc row or
	 * price the browser sent is ignored). {ok, items: [{id, label, value}]} or {ok:false, error, field_id}:
	 * price_not_computable (the formula fails: division by zero, a text answer), invalid_selection (a
	 * negative amount). On a plan without the part, no amount comes from a calc field. Used by
	 * Payments\Pricing::quote() (payments/create) and fun_validation_pay_elements_efb() (the older routes).
	 */
	public static function price_items($form, array $rows, array $hidden = array()) {
		$fields = self::price_fields($form);
		if (!$fields || !self::plan_ok('calc_price')) return array('ok' => true, 'items' => array());
		$form0 = isset($form[0]) ? $form[0] : null;
		$res = self::evaluate_form($form, $rows, array('hidden' => $hidden, 'locale' => self::locale(), 'currency' => self::currency($form0)));
		$items = array();
		foreach ($fields as $f) {
			$id = (string) $f['id_'];
			if (in_array($id, array_map('strval', $hidden), true)) continue;
			$r = isset($res[$id]) ? $res[$id] : array('ok' => true, 'value' => '');
			if (!$r['ok']) return array('ok' => false, 'error' => 'price_not_computable', 'field_id' => $id);
			if ($r['value'] === '') continue;
			if (substr($r['value'], 0, 1) === '-' && preg_match('/[1-9]/', $r['value'])) return array('ok' => false, 'error' => 'invalid_selection', 'field_id' => $id);
			$items[] = array('id' => $id, 'label' => isset($f['name']) && is_scalar($f['name']) ? (string) $f['name'] : '', 'value' => $r['value']);
		}
		return array('ok' => true, 'items' => $items);
	}

	/** efb_payment_readiness: a payment form whose amount comes from a calc field needs the plan of that part. */
	public static function payment_readiness($out, $form, $form_id = 0) {
		if (!is_array($out) || empty($out['has_payment']) || !is_array($form) || self::plan_ok('calc_price')) return $out;
		foreach (self::price_fields($form) as $f) {
			$out['issues'][] = array('code' => 'pay_calc_plan', 'level' => 'error', 'field_id' => (string) $f['id_'], 'gateway' => '',
				'm' => function_exists('__') ? __('A calculation used as the amount to pay needs the Pro plan.', 'easy-form-builder') : 'A calculation used as the amount to pay needs the Pro plan.',
				'fix' => array('kind' => 'plan', 'url' => function_exists('admin_url') ? admin_url('admin.php?page=Emsfb_addon') : '', 'label' => function_exists('__') ? __('See the plans', 'easy-form-builder') : 'See the plans'));
			$out['ready'] = false;
		}
		return $out;
	}

	/** The save: formula_err = "1" on every calc field with a problem of form_errors(), removed otherwise. */
	public static function normalize_rows($rows) {
		if (!is_array($rows)) return $rows;
		$errs = self::form_errors($rows);
		foreach ($rows as $i => $r) {
			if ($i < 1 || !is_array($r) || ($r['type'] ?? '') !== 'calc' || !isset($r['id_'])) continue;
			if (isset($errs[(string) $r['id_']])) $rows[$i]['formula_err'] = '1';
			else unset($rows[$i]['formula_err']);
		}
		return $rows;
	}

	/** Whether a form (its stored JSON text) needs calc-efb.js on the page. */
	public static function json_needs_runtime($json) {
		if (!is_string($json) || $json === '') return false;
		return (bool) preg_match('/\\\\?"(?:num_step|num_decimals|num_format|num_dec_sep|num_grp_sep|num_currency|num_max_zero|range_mode|calc_as_price|calc)\\\\?"\s*:\s*\\\\?"[^"\\\\]|type\\\\?"\s*:\s*\\\\?"calc\\\\?"|\\\\?"num_(?:digits|prefix|suffix)\\\\?"\s*:\s*\\\\?"(?:auto|native|latin|[^"\\\\0-9])/', $json);
	}

	/** The site language (the page and the server use the same one: get_locale(), efb_var.wp_lan). */
	public static function locale() {
		$l = function_exists('get_locale') ? get_locale() : 'en_US';
		return is_string($l) && $l !== '' ? $l : 'en_US';
	}

	/** Symbol and decimals of the form currency ({s, d}) or null. */
	public static function currency($form0) {
		$form0 = self::f($form0);
		$code = isset($form0['currency']) && is_string($form0['currency']) ? trim($form0['currency']) : '';
		if ($code === '') $code = 'USD';
		if (!class_exists('\\Emsfb\\Formbuilder') && is_readable(__DIR__ . '/class-Emsfb-formbuilder.php')) require_once __DIR__ . '/class-Emsfb-formbuilder.php';
		if (class_exists('\\Emsfb\\Formbuilder') && method_exists('\\Emsfb\\Formbuilder', 'get_currency_details_efb')) {
			$d = \Emsfb\Formbuilder::get_currency_details_efb($code);
			if (is_array($d) && isset($d['s'])) return array('s' => (string) $d['s'], 'd' => isset($d['d']) ? (int) $d['d'] : 2);
		}
		return array('s' => strtoupper($code), 'd' => 2);
	}

	/** config() of a stored field with the site language and the form currency. */
	public static function field_config($f, $form0 = null) {
		return self::config($f, array('locale' => self::locale(), 'currency' => self::currency($form0)));
	}

	/** What the page needs of a config (data-numcfg). */
	public static function client_config(array $cfg) {
		return array(
			'decimals' => $cfg['decimals'], 'step' => $cfg['step'], 'min' => $cfg['min'], 'max' => $cfg['max'], 'dec' => $cfg['dec'], 'grp' => $cfg['grp'],
			'group' => $cfg['group'], 'script' => $cfg['script'], 'prefix' => $cfg['prefix'], 'suffix' => $cfg['suffix'], 'cur' => $cfg['cur'], 'rtl' => $cfg['rtl'],
		);
	}

	/** A text of the phrase map (or the English default) with %1$s / %2$s / %s / NN filled. */
	public static function t($key, $lanText = array(), array $args = array()) {
		$s = is_array($lanText) && isset($lanText[$key]) && is_string($lanText[$key]) && $lanText[$key] !== '' ? $lanText[$key] : (isset(self::DEFAULTS[$key]) ? self::DEFAULTS[$key] : $key);
		foreach ($args as $i => $a) $s = str_replace('%' . ($i + 1) . '$s', (string) $a, $s);
		if (isset($args[0])) $s = str_replace('%s', (string) $args[0], $s);
		if (isset($args[0])) $s = str_replace('NN', (string) $args[0], $s);
		return $s;
	}

	/** Unicode isolation (FSI … PDI) of a value placed inside a sentence (RTL pages keep the order). */
	public static function isolate($s) {
		return "\u{2068}" . $s . "\u{2069}";
	}

	/**
	 * The server check of one submitted number / range row (spec §10.2). Old fields without F04 keys keep
	 * today's rule (is_numeric, min / max as values) and today's stored text, except that local digits and
	 * separators are read (they were refused). Returns {ok, row, m, code}.
	 */
	public static function check_row($f, array $item, $lanText = array(), $form0 = null) {
		$f = self::f($f);
		$name = isset($f['name']) && is_scalar($f['name']) ? (string) $f['name'] : '';
		$bname = '<b>' . (function_exists('esc_html') ? esc_html($name) : htmlspecialchars($name, ENT_QUOTES, 'UTF-8')) . '</b>';
		$raw = isset($item['value']) && is_scalar($item['value']) ? (string) $item['value'] : '';
		$bad = function ($m, $code) use ($item) { return array('ok' => false, 'row' => null, 'm' => $m, 'code' => $code); };
		$cfg = self::field_config($f, $form0);
		$f4 = self::is_f4($f);
		// F: a two-handle slider: "lo..hi", both numbers inside the limits and on the step, lo <= hi; lo and hi stored beside it.
		if (self::is_dual($f)) {
			if ($cfg['min'] === '' && isset($f['milen']) && is_scalar($f['milen']) && preg_match('/^-?\d{1,15}(\.\d{1,6})?$/D', (string) $f['milen'])) $cfg['min'] = (string) $f['milen'];
			$pair = self::split_range($raw);
			if (!$pair) return $bad(self::t('mnvvXXX_', $lanText, array($bname)), 'nan');
			$lo = self::parse_input($pair['lo'], $cfg);
			$hi = self::parse_input($pair['hi'], $cfg);
			if (!$lo['ok'] || !$hi['ok']) return $bad(self::t('mnvvXXX_', $lanText, array($bname)), 'nan');
			if (self::cmp_dec($lo['canon'], $hi['canon']) > 0) return $bad(self::t('rngOrder', $lanText, array($bname)), 'order');
			foreach (array($lo['canon'], $hi['canon']) as $one) {
				$c = self::check($one, $cfg);
				if ($c === 'dec') return $bad(self::t('numDecM_', $lanText, array($bname, (string) $cfg['decimals'])), 'dec');
				if ($c === 'step') return $bad(self::t('numStepM_', $lanText, array($bname, $cfg['step'])), 'step');
				if ($c === 'min') return $bad(self::t('mcplen', $lanText, array(self::format($cfg['min'], $cfg))), 'min');
				if ($c === 'max') return $bad(self::t('mxcplen', $lanText, array(self::format($cfg['max'], $cfg))), 'max');
			}
			$item['value'] = $lo['canon'] . '..' . $hi['canon'];
			$item['lo'] = $lo['canon'];
			$item['hi'] = $hi['canon'];
			return array('ok' => true, 'row' => $item, 'm' => '', 'code' => '');
		}
		unset($item['lo'], $item['hi']);
		if (!$f4 && is_numeric($raw)) {
			// Today's rule, today's stored text.
			$n = (float) $raw;
			if ((isset($f['milen']) && is_numeric($f['milen']) && $n < (float) $f['milen']) || (isset($f['mlen']) && is_numeric($f['mlen']) && (float) $f['mlen'] != 0 && $n > (float) $f['mlen'])) {
				return $bad(self::t('mnvvXXX_', $lanText, array($bname)), 'range');
			}
			return array('ok' => true, 'row' => $item, 'm' => '', 'code' => '');
		}
		$p = self::parse_input($raw, $cfg);
		if (!$p['ok']) return $bad(self::t($f4 ? 'numNanM_' : 'mnvvXXX_', $lanText, array($bname)), 'nan');
		$canon = $p['canon'];
		if (!$f4) {
			// An old field: the number read from local digits, checked as before.
			$n = (float) $canon;
			if ((isset($f['milen']) && is_numeric($f['milen']) && $n < (float) $f['milen']) || (isset($f['mlen']) && is_numeric($f['mlen']) && (float) $f['mlen'] != 0 && $n > (float) $f['mlen'])) {
				return $bad(self::t('mnvvXXX_', $lanText, array($bname)), 'range');
			}
		} else {
			$c = self::check($canon, $cfg);
			if ($c === 'dec') return $bad(self::t('numDecM_', $lanText, array($bname, (string) $cfg['decimals'])), 'dec');
			if ($c === 'step') return $bad(self::t('numStepM_', $lanText, array($bname, self::format($cfg['step'], array_merge($cfg, array('group' => false, 'cur' => null, 'prefix' => '', 'suffix' => ''))))), 'step');
			if ($c === 'min') return $bad(self::t('mcplen', $lanText, array(self::format($cfg['min'], $cfg))), 'min');
			if ($c === 'max') return $bad(self::t('mxcplen', $lanText, array(self::format($cfg['max'], $cfg))), 'max');
		}
		$item['value'] = $canon;
		return array('ok' => true, 'row' => $item, 'm' => '', 'code' => '');
	}

	/** The display text of a stored value of a field ('' when it is shown as stored). "lo – hi" for a two-handle slider. */
	public static function display($f, $value, $form0 = null) {
		$f = self::f($f);
		if (!self::is_f4($f) && !(isset($f['type']) && $f['type'] === 'calc') && !(isset($f['range_mode']) && $f['range_mode'] === 'dual')) return '';
		$cfg = self::field_config($f, $form0);
		$v = is_scalar($value) ? (string) $value : '';
		$r = self::split_range($v);
		$out = $r ? self::format($r['lo'], $cfg) . ' – ' . self::format($r['hi'], $cfg) : self::format($v, $cfg);
		return $out === $v ? '' : $out;
	}

	/** A validated row with disp (the server's display text) when the field formats its numbers; never a browser's disp. */
	public static function with_display(array $row, $f, $form0 = null) {
		unset($row['disp']);
		$d = self::display($f, isset($row['value']) ? $row['value'] : '', $form0);
		if ($d !== '') $row['disp'] = $d;
		return $row;
	}

	/**
	 * The input attributes of a number field with F04 keys (PHP renderer; the builder twin is
	 * efbNumInputAttrs() in calc-builder-efb.js): a text box for typed digits and separators.
	 */
	public static function input_attrs($f, $form0 = null) {
		$cfg = self::field_config($f, $form0);
		$json = function_exists('wp_json_encode') ? wp_json_encode(self::client_config($cfg), JSON_UNESCAPED_UNICODE) : json_encode(self::client_config($cfg), JSON_UNESCAPED_UNICODE);
		$e = function ($v) { return function_exists('esc_attr') ? esc_attr($v) : htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
		return 'inputmode="decimal" dir="ltr" data-num="1" data-numcfg="' . $e($json) . '"';
	}

	/**
	 * The page markup of a calc field (inside its -f wrapper; the builder twin is efbCalcCanvas()): a read-only
	 * <output> announced politely once the 100 ms debounce settles, and the hidden canonical value. The result
	 * is computed by calc-efb.js in the page and again by the server on submit.
	 */
	public static function calc_markup($vj, $id, $form_id, $form0 = null) {
		$f = self::f($vj);
		$e = function ($v) { return function_exists('esc_attr') ? esc_attr($v) : htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
		$cfg = self::field_config($f, $form0);
		$json = function_exists('wp_json_encode') ? wp_json_encode(self::client_config($cfg), JSON_UNESCAPED_UNICODE) : json_encode(self::client_config($cfg), JSON_UNESCAPED_UNICODE);
		$cls = isset($f['el_text_color']) && is_scalar($f['el_text_color']) ? (string) $f['el_text_color'] : '';
		$req = isset($f['required']) && in_array($f['required'], array(1, '1', true), true);
		return '<div class="efb efb-calc" data-efb-calc="1" data-vid="' . $e($id) . '" data-formid="' . $e($form_id) . '" data-numcfg="' . $e($json) . '">'
			. '<output class="efb efb-calc-out form-control-plaintext ' . $e($cls) . ($req ? ' required' : '') . '" id="' . $e($id) . '_out" role="status" aria-live="polite" aria-atomic="true" aria-labelledby="' . $e($id) . '_labG" dir="auto"></output>'
			. '<input type="hidden" class="efb emsFormBuilder_v" data-vid="' . $e($id) . '" data-type="calc" data-formid="' . $e($form_id) . '" id="' . $e($id) . '_" value="">'
			. '</div>';
	}

	/** "Between min and max" for a screen reader ('' without both limits). */
	public static function range_hint($f, $lanText = array(), $form0 = null) {
		$cfg = self::field_config($f, $form0);
		if ($cfg['min'] === '' || $cfg['max'] === '') return '';
		return self::t('numRange', $lanText, array(self::format($cfg['min'], $cfg), self::format($cfg['max'], $cfg)));
	}
}

/** @internal An evaluation failure (a code, never shown as an exception). */
final class Calc_Fail extends \Exception {
	public $code_;
	public function __construct($code) { parent::__construct((string) $code); $this->code_ = (string) $code; }
}

/** @internal The recursive-descent parser of Calc::parse(). */
final class Calc_Parser {
	private $t;
	private $pos = 0;
	private $len;
	private $refs = array();
	private $depth = 0;
	private $failure = null;

	public function __construct(array $tokens, $len) { $this->t = $tokens; $this->len = (int) $len; }

	private function peek() { return isset($this->t[$this->pos]) ? $this->t[$this->pos] : null; }

	private static function near($t) {
		if (!$t) return '';
		if ($t['t'] === 'num' || $t['t'] === 'id') return $t['v'];
		if ($t['t'] === 'ref') return $t['raw'];
		return $t['t'];
	}

	private function fail($code, $t) {
		if ($this->failure === null) $this->failure = array('ok' => false, 'code' => $code, 'pos' => $t ? (int) $t['p'] : $this->len, 'near' => $t ? self::near($t) : '');
		return null;
	}

	private function deeper($t) {
		$this->depth++;
		if ($this->depth > Calc::MAX_DEPTH) { $this->fail('calcDepth', $t); return false; }
		return true;
	}

	public function run() {
		$ast = $this->t ? $this->expr() : null;
		if ($this->failure !== null) return $this->failure;
		if (!$this->t) return array('ok' => false, 'code' => 'calcEmpty', 'pos' => 0, 'near' => '');
		if (!$ast) return array('ok' => false, 'code' => 'calcSyntax', 'pos' => $this->len, 'near' => '');
		if ($this->pos < count($this->t)) return array('ok' => false, 'code' => 'calcSyntax', 'pos' => (int) $this->t[$this->pos]['p'], 'near' => self::near($this->t[$this->pos]));
		return array('ok' => true, 'ast' => $ast, 'refs' => $this->refs);
	}

	private function expr() {
		$a = $this->term();
		while ($a && ($p = $this->peek()) && ($p['t'] === '+' || $p['t'] === '-')) {
			$op = $this->t[$this->pos++]['t'];
			$b = $this->term();
			if (!$b) return null;
			$a = array('t' => 'bin', 'op' => $op, 'a' => $a, 'b' => $b);
		}
		return $a;
	}

	private function term() {
		$a = $this->unary();
		while ($a && ($p = $this->peek()) && ($p['t'] === '*' || $p['t'] === '/')) {
			$op = $this->t[$this->pos++]['t'];
			$b = $this->unary();
			if (!$b) return null;
			$a = array('t' => 'bin', 'op' => $op, 'a' => $a, 'b' => $b);
		}
		return $a;
	}

	private function unary() {
		$t = $this->peek();
		if ($t && ($t['t'] === '-' || $t['t'] === '+')) {
			$this->pos++;
			if (!$this->deeper($t)) return null;
			$a = $this->unary();
			$this->depth--;
			if (!$a) return null;
			return $t['t'] === '-' ? array('t' => 'neg', 'a' => $a) : $a;
		}
		return $this->primary();
	}

	private function primary() {
		static $funcs = null;
		if ($funcs === null) {
			$funcs = array('round' => array(1, 2), 'ceil' => array(1, 2), 'floor' => array(1, 2), 'min' => array(1, 200), 'max' => array(1, 200), 'abs' => array(1, 1), 'if' => array(3, 3),
				'sum' => array(1, 200), 'avg' => array(1, 200), 'count' => array(1, 200), 'gt' => array(2, 2), 'gte' => array(2, 2), 'lt' => array(2, 2), 'lte' => array(2, 2),
				'eq' => array(2, 2), 'neq' => array(2, 2), 'and' => array(1, 200), 'or' => array(1, 200), 'not' => array(1, 1), 'mod' => array(2, 2), 'pow' => array(2, 2));
		}
		$t = $this->peek();
		if (!$t) return $this->fail('calcSyntax', null);
		if ($t['t'] === 'num') { $this->pos++; return array('t' => 'num', 'v' => (float) $t['v']); }
		if ($t['t'] === 'ref') {
			$this->pos++;
			if (count($this->refs) >= Calc::MAX_REFS) return $this->fail('calcRefs', $t);
			$this->refs[] = array('id' => $t['id'], 'part' => $t['part']);
			return array('t' => 'ref', 'id' => $t['id'], 'part' => $t['part'], 'raw' => $t['raw']);
		}
		if ($t['t'] === '(') {
			$this->pos++;
			if (!$this->deeper($t)) return null;
			$e = $this->expr();
			$this->depth--;
			if (!$e) return null;
			$p = $this->peek();
			if (!$p || $p['t'] !== ')') return $this->fail('calcSyntax', $p);
			$this->pos++;
			return $e;
		}
		if ($t['t'] === 'id') {
			if (!isset($funcs[$t['v']])) return $this->fail('calcFn', $t);
			$this->pos++;
			$p = $this->peek();
			if (!$p || $p['t'] !== '(') return $this->fail('calcSyntax', $p ? $p : $t);
			$open = $this->t[$this->pos++];
			if (!$this->deeper($open)) return null;
			$args = array();
			$p = $this->peek();
			if ($p && $p['t'] === ')') { $this->pos++; } else {
				while (true) {
					$x = $this->expr();
					if (!$x) return null;
					$args[] = $x;
					$p = $this->peek();
					if ($p && $p['t'] === ',') { $this->pos++; continue; }
					if ($p && $p['t'] === ')') { $this->pos++; break; }
					return $this->fail('calcSyntax', $p);
				}
			}
			$this->depth--;
			$lim = $funcs[$t['v']];
			if (count($args) < $lim[0] || count($args) > $lim[1]) return $this->fail('calcArgs', $t);
			return array('t' => 'call', 'fn' => $t['v'], 'args' => $args);
		}
		return $this->fail('calcSyntax', $t);
	}
}

/** @internal The tree walker of Calc::evaluate(). */
final class Calc_Eval {
	private $resolver;
	private $blank;
	public $is_empty = false;

	public function __construct($resolver, $blank) { $this->resolver = $resolver; $this->blank = (bool) $blank; }

	private static function truthy($v) { return abs($v) >= Calc::EPS ? 1 : 0; }

	public function scalar($n) { return $this->val($n, false); }

	private function val($n, $list_ok) {
		switch ($n['t']) {
			case 'num': return $n['v'];
			case 'neg': return Calc::num(-$this->scalar($n['a']));
			case 'bin':
				$a = $this->scalar($n['a']);
				$b = $this->scalar($n['b']);
				if ($n['op'] === '+') return Calc::num($a + $b);
				if ($n['op'] === '-') return Calc::num($a - $b);
				if ($n['op'] === '*') return Calc::num($a * $b);
				if ($b == 0) throw new Calc_Fail('calcDiv0');
				return Calc::num($a / $b);
			case 'ref':
				$r = call_user_func($this->resolver, array('id' => $n['id'], 'part' => $n['part'], 'raw' => $n['raw']));
				if (is_array($r) && isset($r['error'])) throw new Calc_Fail((string) $r['error']);
				if (is_array($r)) {
					if (!$list_ok) throw new Calc_Fail('calcListCtx');
					$list = array();
					foreach ($r as $x) {
						if ($x === null || $x === '') { if ($this->blank) { $this->is_empty = true; throw new Calc_Fail('#empty'); } continue; }
						$list[] = Calc::num(is_string($x) ? (float) $x : $x);
					}
					return $list;
				}
				if ($r === null || $r === '') {
					if ($this->blank) { $this->is_empty = true; throw new Calc_Fail('#empty'); }
					return 0.0;
				}
				return Calc::num(is_string($r) ? (float) $r : $r);
			case 'call':
				return $this->call($n);
		}
		throw new Calc_Fail('calcSyntax');
	}

	private function flat(array $args) {
		$out = array();
		foreach ($args as $a) {
			$v = $this->val($a, true);
			if (is_array($v)) { foreach ($v as $x) $out[] = $x; } else $out[] = $v;
		}
		return $out;
	}

	private function call($n) {
		$f = $n['fn'];
		$A = $n['args'];
		switch ($f) {
			case 'if': return self::truthy($this->scalar($A[0])) ? $this->scalar($A[1]) : $this->scalar($A[2]);
			case 'and': foreach ($A as $a) if (!self::truthy($this->scalar($a))) return 0.0; return 1.0;
			case 'or': foreach ($A as $a) if (self::truthy($this->scalar($a))) return 1.0; return 0.0;
			case 'not': return self::truthy($this->scalar($A[0])) ? 0.0 : 1.0;
			case 'gt': return $this->scalar($A[0]) - $this->scalar($A[1]) >= Calc::EPS ? 1.0 : 0.0;
			case 'gte': return $this->scalar($A[0]) - $this->scalar($A[1]) > -Calc::EPS ? 1.0 : 0.0;
			case 'lt': $x = $this->scalar($A[0]); return $this->scalar($A[1]) - $x >= Calc::EPS ? 1.0 : 0.0;
			case 'lte': $x = $this->scalar($A[0]); return $this->scalar($A[1]) - $x > -Calc::EPS ? 1.0 : 0.0;
			case 'eq': return abs($this->scalar($A[0]) - $this->scalar($A[1])) < Calc::EPS ? 1.0 : 0.0;
			case 'neq': return abs($this->scalar($A[0]) - $this->scalar($A[1])) < Calc::EPS ? 0.0 : 1.0;
			case 'abs': return abs($this->scalar($A[0]));
			case 'round':
			case 'ceil':
			case 'floor':
				$x = $this->scalar($A[0]);
				$d = count($A) > 1 ? $this->scalar($A[1]) : 0.0;
				$s = Calc::scale_of($d);
				if ($f === 'round') return Calc::round_half_away($x, (int) $d);
				if ($f === 'ceil') return Calc::num(ceil($x * $s - Calc::EPS) / $s);
				return Calc::num(floor($x * $s + Calc::EPS) / $s);
			case 'mod':
				$x = $this->scalar($A[0]);
				$k = $this->scalar($A[1]);
				if ($k == 0) throw new Calc_Fail('calcDiv0');
				return Calc::num(fmod($x, $k));
			case 'pow':
				$x = $this->scalar($A[0]);
				$k = $this->scalar($A[1]);
				if ($k != floor($k) || $k < -10 || $k > 10) throw new Calc_Fail('calcArgs');
				$p = 1.0;
				for ($i = 0; $i < abs($k); $i++) $p = Calc::num($p * $x);
				if ($k < 0) { if ($p == 0) throw new Calc_Fail('calcDiv0'); $p = Calc::num(1 / $p); }
				return $p;
			case 'count':
				$c = 0;
				foreach ($A as $a) { $v = $this->val($a, true); $c += is_array($v) ? count($v) : 1; }
				return (float) $c;
			case 'sum':
			case 'avg':
			case 'min':
			case 'max':
				$L = $this->flat($A);
				if (!$L) { if ($f === 'sum') return 0.0; throw new Calc_Fail('calcArgs'); }
				$acc = $f === 'sum' || $f === 'avg' ? 0.0 : $L[0];
				foreach ($L as $x) {
					if ($f === 'sum' || $f === 'avg') $acc = Calc::num($acc + $x);
					elseif ($f === 'min') $acc = $x < $acc ? $x : $acc;
					else $acc = $x > $acc ? $x : $acc;
				}
				return $f === 'avg' ? Calc::num($acc / count($L)) : $acc;
		}
		throw new Calc_Fail('calcFn');
	}
}
