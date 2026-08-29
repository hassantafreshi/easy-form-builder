<?php
namespace Emsfb;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Conditional Logic addon (AdnSMF) — server-side rule evaluator and
 * submission normalizer. Forms without active logic_rules are returned
 * unchanged, so their legacy validation path remains authoritative.
 */
class Emsfb_Logic_Validator {

	/* Fixed-point budget for the value loop. Rules run in priority order within
	 * a single pass, so an acyclic cascade converges in one or two; a budget
	 * this size only ever runs out on a chain wired against its own priority
	 * order, or on a genuine loop — which stabilized:false then reports. */
	const MAX_PASSES = 25;

	private static $structural_types = array(
		'form', 'step', 'option', 'submit', 'r_matrix', 'buttonnav',
	);

	private static $select_types = array(
		'select', 'payselect', 'conturylist', 'stateprovince',
		'statepro', 'country', 'city', 'citylist',
	);

	private static $radio_types = array(
		'radio', 'payradio', 'imgradio', 'chlradio',
	);

	private static $checkbox_types = array(
		'checkbox', 'paycheckbox', 'chlcheckbox',
	);

	private static $multiselect_types = array(
		'multiselect', 'paymultiselect',
	);

	private static $payment_types = array(
		'payment', 'stripe', 'paypal', 'persiapay',
	);

	/* Where a paid figure may be recorded, most specific first. `amount` is last
	 * because in an EFB row it is the field's ordering index, not money; on a
	 * gateway row `value` holds the charged figure and must outrank it. */
	private static $money_keys = array(
		'paymentAmount', 'paid_amount', 'total', 'price', 'amount',
	);
	private static $money_keys_payment = array(
		'paymentAmount', 'paid_amount', 'total', 'price', 'value', 'amount',
	);

	/**
	 * Environment for non-field condition sources. Defaults are built from
	 * WordPress when available; tests inject their own via set_environment().
	 */
	private $environment = null;

	public function set_environment( $env ) {
		$this->environment = is_array( $env ) ? $env : null;
	}

	public function get_environment( $form_fields_array = array() ) {
		$env = array(
			'query' => array(),
			'user' => array( 'logged_in' => false, 'roles' => array() ),
			'current_step' => null,
		);

		if ( is_array( $this->environment ) ) {
			if ( isset( $this->environment['query'] ) && is_array( $this->environment['query'] ) ) $env['query'] = $this->environment['query'];
			if ( isset( $this->environment['user'] ) && is_array( $this->environment['user'] ) ) {
				$env['user']['logged_in'] = ! empty( $this->environment['user']['logged_in'] );
				$env['user']['roles'] = isset( $this->environment['user']['roles'] ) && is_array( $this->environment['user']['roles'] )
					? array_map( 'strval', $this->environment['user']['roles'] )
					: array();
			}
			if ( isset( $this->environment['current_step'] ) && is_numeric( $this->environment['current_step'] ) ) {
				$env['current_step'] = (int) $this->environment['current_step'];
			}
			return $env;
		}

		// Live WordPress request: query params come from the page the form was
		// filled on (AJAX referer), user state from the real session.
		if ( function_exists( 'wp_get_referer' ) ) {
			$referer = wp_get_referer();
			if ( is_string( $referer ) && $referer !== '' ) {
				$query_string = (string) parse_url( $referer, PHP_URL_QUERY );
				if ( $query_string !== '' ) parse_str( $query_string, $env['query'] );
			}
		}
		if ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) {
			$env['user']['logged_in'] = true;
			if ( function_exists( 'wp_get_current_user' ) ) {
				$user = wp_get_current_user();
				$env['user']['roles'] = isset( $user->roles ) && is_array( $user->roles ) ? array_values( $user->roles ) : array();
			}
		}

		// A submission always arrives from the last visible step.
		$max_step = 0;
		foreach ( (array) $form_fields_array as $item ) {
			if ( is_array( $item ) && strtolower( (string) ( $item['type'] ?? '' ) ) === 'step' && isset( $item['step'] ) && is_numeric( $item['step'] ) ) {
				$max_step = max( $max_step, (int) $item['step'] );
			}
		}
		$env['current_step'] = $max_step > 0 ? $max_step : 1;

		return $env;
	}

	public function empty_result() {
		return array(
			'is_conditional' => false,
			'stabilized' => true,
			'hidden_fields' => array(),
			'shown_fields' => array(),
			'required_fields' => array(),
			'optional_fields' => array(),
			'disabled_fields' => array(),
			'enabled_fields' => array(),
			'hidden_steps' => array(),
			'shown_steps' => array(),
			'ignored_fields' => array(),
			'matched_rules' => array(),
			'set_values' => array(),
			'cleared_fields' => array(),
			'values_map' => array(),
			'messages' => array(),
			'jumps' => array(),
			'trace' => array(),
			'ui_changes' => array(),
			'focus_fields' => array(),
			'scroll_fields' => array(),
			'submit_blocked' => false,
			'block_messages' => array(),
			'end_form' => null,
		);
	}

	/**
	 * A form is conditional only when it contains at least one usable enabled
	 * rule. Merely having an empty logic_rules property does not change submit.
	 */
	public function has_active_rules( $form_fields_array ) {
		$rules = isset( $form_fields_array[0]['logic_rules'] )
			? $form_fields_array[0]['logic_rules']
			: array();
		if ( ! is_array( $rules ) ) return false;

		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) || empty( $rule['enabled'] ) ) continue;
			$items = isset( $rule['conditions']['items'] ) ? $rule['conditions']['items'] : array();
			$actions = isset( $rule['actions'] ) ? $rule['actions'] : array();
			if ( is_array( $items ) && ! empty( $items ) && is_array( $actions ) && ! empty( $actions ) ) {
				return true;
			}
		}
		$legacy = isset( $form_fields_array[0]['conditions'] ) ? $form_fields_array[0]['conditions'] : array();
		if ( is_array( $legacy ) ) {
			foreach ( $legacy as $condition ) {
				if ( is_array( $condition ) && ! empty( $condition['state'] ) && ! empty( $condition['id_'] ) && ! empty( $condition['condition'] ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Build condition values keyed by form field id.
	 */
	public function build_values_map( $form_fields_array, $submitted_values ) {
		$index = $this->index_structure( $form_fields_array );
		$values = array();

		foreach ( $submitted_values as $row ) {
			if ( ! is_array( $row ) || empty( $row['id_'] ) ) continue;
			$field_id = (string) $row['id_'];
			$type = strtolower( (string) ( ! empty( $row['type'] )
				? $row['type']
				: ( isset( $index['fields'][ $field_id ]['type'] ) ? $index['fields'][ $field_id ]['type'] : '' ) ) );

			if ( in_array( $type, self::$checkbox_types, true ) ) {
				if ( ! isset( $values[ $field_id ] ) || ! is_array( $values[ $field_id ] ) ) {
					$values[ $field_id ] = array();
				}
				$option = isset( $row['id_ob'] ) ? $row['id_ob'] : ( isset( $row['value'] ) ? $row['value'] : '' );
				if ( $option !== '' && ! in_array( $option, $values[ $field_id ], true ) ) {
					$values[ $field_id ][] = $option;
				}
				continue;
			}

			if ( $type === 'yesno' ) {
				$value = isset( $row['id_ob'] ) ? (string) $row['id_ob'] : (string) ( $row['value'] ?? '' );
				if ( $value === $field_id . '_1' || strtolower( $value ) === 'yes' || $value === '1' ) {
					$values[ $field_id ] = 'yes';
				} elseif ( $value === $field_id . '_2' || strtolower( $value ) === 'no' || $value === '0' ) {
					$values[ $field_id ] = 'no';
				} else {
					$values[ $field_id ] = '';
				}
				continue;
			}

			if ( in_array( $type, self::$radio_types, true ) ) {
				$values[ $field_id ] = isset( $row['id_ob'] ) ? $row['id_ob'] : ( $row['value'] ?? '' );
				continue;
			}

			if ( in_array( $type, self::$multiselect_types, true ) ) {
				$raw = isset( $row['value'] ) ? $row['value'] : '';
				$values[ $field_id ] = is_array( $raw )
					? array_values( array_filter( $raw, array( $this, 'is_not_empty_value' ) ) )
					: array_values( array_filter( array_map( 'trim', explode( '@efb!', (string) $raw ) ), array( $this, 'is_not_empty_value' ) ) );
				continue;
			}

			$values[ $field_id ] = isset( $row['value'] ) ? $row['value'] : '';
		}

		return $values;
	}

	public function is_not_empty_value( $value ) {
		return $value !== '' && $value !== null;
	}

	/**
	 * Evaluate one condition group against an already-built values map.
	 *
	 * Field rules reach the evaluator through evaluate(); the notification,
	 * confirmation and webhook rules do not — they carry the same condition
	 * shape but are processed in _Public, which grew its own second copy of the
	 * comparison logic. That copy never resolved an option id to its stored
	 * value, so a rule built in the UI on a select or multiselect option (the
	 * builder writes the option's id_) compared an id against the visible text
	 * and never matched: the notification was not sent, the conditional
	 * confirmation never appeared, the webhook was never called. Exposing the
	 * real evaluator here gives all four rule collections one semantic instead
	 * of two that drift apart.
	 *
	 * @param array      $form_fields_array Full form structure (for the option index).
	 * @param array      $group             Condition group as stored on the rule.
	 * @param array      $values            Values map from build_values_map().
	 * @param array|null $submitted_values  Rows, needed only by the payment operators.
	 */
	public function evaluate_condition_group( $form_fields_array, $group, $values, $submitted_values = array() ) {
		return $this->evaluate_condition_group_internal( $group, $values, $form_fields_array, (array) $submitted_values );
	}

	/**
	 * Evaluate rules until set_value/clear_value no longer change condition
	 * inputs. Presentation state is rebuilt from its baseline on every pass.
	 */
	public function evaluate( $form_fields_array, $submitted_values ) {
		$empty = $this->empty_result();
		if ( ! $this->has_active_rules( $form_fields_array ) ) return $empty;

		$rules = $this->sorted_rules( $form_fields_array[0]['logic_rules'] ?? array() );
		if ( empty( $rules ) ) $rules = $this->legacy_rules( $form_fields_array );
		$original_values = $this->build_values_map( $form_fields_array, $submitted_values );
		$values = $original_values;
		$result = $empty;
		$result['is_conditional'] = true;
		$seen = array();

		/* Fixed point, or an explicit failure to reach one. Two ways to miss it:
		 * an exact repeat (A→B→A), caught by $seen, and monotonic divergence
		 * ({total} = {total} + {qty} climbs forever, never repeating a
		 * signature). Only the budget catches the second, so exhausting it must
		 * report stabilized:false — reporting true published the Nth iterate as
		 * if it were a computed answer, which is how a "+1" rule silently
		 * produced +10. */
		$stabilized = false;
		for ( $pass = 0; $pass < self::MAX_PASSES; $pass++ ) {
			$signature = serialize( $values );
			if ( isset( $seen[ $signature ] ) ) break;
			$seen[ $signature ] = true;

			$result = $this->evaluate_pass( $form_fields_array, $submitted_values, $rules, $values );
			$next_values = $result['values_map'];
			if ( serialize( $next_values ) === $signature ) {
				$stabilized = true;
				break;
			}
			$values = $next_values;
		}
		$result['stabilized'] = $stabilized;

		/* An unconverged run has no trustworthy computed value, so it publishes
		 * none: the visitor's own answers stand and the builder's Test Mode
		 * shows the "did not stabilize" warning. Visibility/required state still
		 * applies — set membership converges regardless of the value loop. */
		if ( ! $stabilized ) {
			$result['values_map'] = $original_values;
		}

		// Value actions must survive the final stabilization pass even when the
		// condition which originally changed the value no longer matches.
		$result['set_values'] = array();
		$result['cleared_fields'] = array();
		foreach ( array_unique( array_merge( array_keys( $original_values ), array_keys( $result['values_map'] ) ) ) as $field_id ) {
			$before = array_key_exists( $field_id, $original_values ) ? $original_values[ $field_id ] : '';
			$after = array_key_exists( $field_id, $result['values_map'] ) ? $result['values_map'][ $field_id ] : '';
			if ( serialize( $before ) === serialize( $after ) ) continue;
			if ( $after === '' || $after === null || $after === array() ) {
				$result['cleared_fields'][] = $field_id;
			} else {
				$result['set_values'][ $field_id ] = $after;
			}
		}

		/* Developer hook: last chance to adjust the evaluated result. */
		if ( function_exists( 'apply_filters' ) ) {
			$result = apply_filters( 'efb_logic_modify_result', $result, $form_fields_array, $submitted_values );
		}

		return $result;
	}

	/**
	 * Remove fields which are not part of the effective conditional form and
	 * apply server-authoritative set_value/clear_value actions.
	 */
	public function prepare_submission( $form_fields_array, $submitted_values ) {
		$result = $this->evaluate( $form_fields_array, $submitted_values );
		if ( empty( $result['is_conditional'] ) ) {
			return array(
				'is_conditional' => false,
				'submitted_values' => $submitted_values,
				'logic_result' => $result,
			);
		}

		$clean = $submitted_values;
		for ( $pass = 0; $pass < 10; $pass++ ) {
			$result = $this->evaluate( $form_fields_array, $clean );
			$remove = array_fill_keys(
				array_merge( $result['ignored_fields'], $result['cleared_fields'], array_keys( $result['set_values'] ) ),
				true
			);
			$next = array();
			foreach ( $clean as $row ) {
				$field_id = is_array( $row ) && isset( $row['id_'] ) ? $row['id_'] : '';
				if ( $field_id !== '' && isset( $remove[ $field_id ] ) ) continue;
				$next[] = $row;
			}

			foreach ( $result['set_values'] as $field_id => $value ) {
				if ( in_array( $field_id, $result['ignored_fields'], true ) ) continue;
				$next = array_merge(
					$next,
					$this->submission_rows_for_value( $form_fields_array, $field_id, $value )
				);
			}

			if ( serialize( $next ) === serialize( $clean ) ) {
				$clean = $next;
				break;
			}
			$clean = $next;
		}

		$result = $this->evaluate( $form_fields_array, $clean );

		return array(
			'is_conditional' => true,
			'submitted_values' => array_values( $clean ),
			'logic_result' => $result,
		);
	}

	public function validate_required_fields( $form_fields_array, $submitted_values, $logic_result ) {
		if ( empty( $logic_result['is_conditional'] ) ) {
			return array( 'valid' => true, 'missing_field' => null, 'missing_name' => null );
		}

		$ignored = array_fill_keys( $logic_result['ignored_fields'] ?? array(), true );
		$required = array_fill_keys( $logic_result['required_fields'] ?? array(), true );
		$optional = array_fill_keys( $logic_result['optional_fields'] ?? array(), true );
		$submitted_ids = $this->build_submitted_ids( $submitted_values );

		foreach ( $form_fields_array as $position => $field ) {
			if ( $position < 1 || ! is_array( $field ) || empty( $field['id_'] ) ) continue;
			$id = $field['id_'];
			$type = strtolower( (string) ( $field['type'] ?? '' ) );
			if ( in_array( $type, self::$structural_types, true ) || isset( $ignored[ $id ] ) ) continue;

			if ( isset( $required[ $id ] ) ) {
				$is_required = true;
			} elseif ( isset( $optional[ $id ] ) ) {
				$is_required = false;
			} else {
				$is_required = $this->to_bool( $field['required'] ?? false );
			}

			if ( $is_required && ! isset( $submitted_ids[ $id ] ) ) {
				return array(
					'valid' => false,
					'missing_field' => $id,
					'missing_name' => isset( $field['name'] ) ? $field['name'] : $id,
				);
			}
		}

		return array( 'valid' => true, 'missing_field' => null, 'missing_name' => null );
	}

	private function evaluate_pass( $form, $submitted, $rules, $values ) {
		$index = $this->index_structure( $form );
		$env = $this->get_environment( $form );
		$hidden = array();
		$shown = array();
		$required = array();
		$optional = array();
		$disabled = array();
		$enabled = array();
		$hidden_steps = array();
		$shown_steps = array();
		$matched_rules = array();
		$set_values = array();
		$cleared = array();
		$messages = array();
		$jumps = array();
		$trace = array();
		$ui_changes = array();
		$focus_fields = array();
		$scroll_fields = array();
		$submit_blocked = false;
		$block_messages = array();
		$end_form = null;

		foreach ( $index['fields'] as $id => $field ) {
			if ( $this->to_bool( $field['hidden'] ?? false ) ) $hidden[ $id ] = true;
			if ( $this->to_bool( $field['disabled'] ?? false ) ) $disabled[ $id ] = true;
		}
		foreach ( $index['steps'] as $id => $step ) {
			if ( $this->to_bool( $step['hidden'] ?? false ) ) $hidden_steps[ $id ] = true;
		}

		// A show action defines a target that starts hidden until its rule matches.
		foreach ( $rules as $rule ) {
			foreach ( $rule['actions'] as $action_index => $action ) {
				$type = $action['type'] ?? '';
				$target = $action['target'] ?? '';
				if ( $type === 'show_field' && $target !== '' ) $hidden[ $target ] = true;
				if ( $type === 'show_step' && $target !== '' ) $hidden_steps[ $target ] = true;
			}
		}

		$stopped_field_targets = array();
		$stopped_step_targets = array();
		$is_step_action_type = function ( $type ) {
			return $type === 'show_step' || $type === 'hide_step' || $type === 'jump_to_step';
		};

		foreach ( $rules as $rule ) {
			$rule_actions = $rule['actions'] ?? array();

			// stop_processing only freezes the SPECIFIC targets a stopping rule
			// acted on, not every later rule in the form. A rule is skipped here
			// only if ALL of its action targets were already frozen by an
			// earlier matched stop_processing rule.
			$blocked_by_stop = ! empty( $rule_actions );
			if ( $blocked_by_stop ) {
				foreach ( $rule_actions as $action ) {
					$target = $action['target'] ?? '';
					if ( $target === '' ) { $blocked_by_stop = false; break; }
					$type = $action['type'] ?? '';
					$is_locked = $is_step_action_type( $type )
						? array_key_exists( $target, $stopped_step_targets )
						: array_key_exists( $target, $stopped_field_targets );
					if ( ! $is_locked ) { $blocked_by_stop = false; break; }
				}
			}
			if ( $blocked_by_stop ) {
				$trace[] = array( 'id' => (string) $rule['id'], 'status' => 'blocked' );
				continue;
			}

			/* Developer hook: filter (or veto with a falsy value) a rule
			 * right before its conditions are evaluated. */
			if ( function_exists( 'apply_filters' ) ) {
				$rule = apply_filters( 'efb_logic_before_evaluate_rule', $rule, $values, $form );
				if ( empty( $rule ) || ! is_array( $rule ) ) {
					$trace[] = array( 'id' => 'filtered', 'status' => 'skipped' );
					continue;
				}
				$rule_actions = $rule['actions'] ?? array();
			}

			$matched = $this->evaluate_condition_group_internal( $rule['conditions'], $values, $form, $submitted, $env );
			if ( function_exists( 'apply_filters' ) ) {
				$matched = (bool) apply_filters( 'efb_logic_after_evaluate_rule', $matched, $rule, $values, $form );
			}
			if ( ! $matched ) {
				$trace[] = array( 'id' => (string) $rule['id'], 'status' => 'not_matched' );
				continue;
			}
			$matched_rules[] = $rule['id'];
			$trace[] = array( 'id' => (string) $rule['id'], 'status' => 'matched' );

			if ( function_exists( 'do_action' ) ) do_action( 'efb_logic_before_actions', $rule, $values, $form );

			foreach ( $rule_actions as $action_index => $action ) {
				$type = $action['type'] ?? '';
				$target = $action['target'] ?? '';
				$action_key = $rule['id'] . ':' . $action_index;

				/* block_submit / end_form are form-level actions with no target */
				if ( $type === 'block_submit' ) {
					$submit_blocked = true;
					if ( ! empty( $action['value'] ) ) {
						$block_messages[] = array( 'key' => $action_key, 'value' => (string) $action['value'] );
					}
					continue;
				}
				if ( $type === 'end_form' ) {
					$submit_blocked = true;
					if ( $end_form === null ) {
						$end_form = array( 'key' => $action_key, 'message' => (string) ( $action['value'] ?? '' ) );
					}
					continue;
				}
				if ( $target === '' ) continue;

				switch ( $type ) {
					case 'show_field':
						unset( $hidden[ $target ] );
						$shown[ $target ] = true;
						break;
					case 'hide_field':
						$hidden[ $target ] = true;
						unset( $shown[ $target ] );
						break;
					case 'set_required':
						$required[ $target ] = true;
						unset( $optional[ $target ] );
						break;
					case 'set_optional':
						$optional[ $target ] = true;
						unset( $required[ $target ] );
						break;
					case 'enable_field':
						unset( $disabled[ $target ] );
						$enabled[ $target ] = true;
						break;
					case 'disable_field':
						$disabled[ $target ] = true;
						unset( $enabled[ $target ] );
						break;
					case 'show_step':
						unset( $hidden_steps[ $target ] );
						$shown_steps[ $target ] = true;
						break;
					case 'hide_step':
						$hidden_steps[ $target ] = true;
						unset( $shown_steps[ $target ] );
						break;
					case 'set_value':
						$value = $this->resolve_action_value( $action, $form, $values );
						if ( $value !== null ) {
							$values[ $target ] = $this->normalize_value_for_field( $form, $target, $value );
							$set_values[ $target ] = $values[ $target ];
							unset( $cleared[ $target ] );
						}
						break;
					case 'calculate':
						$value = $this->resolve_calculation_value( $action, $form, $values );
						if ( $value !== null ) {
							$values[ $target ] = $this->normalize_value_for_field( $form, $target, $value );
							$set_values[ $target ] = $values[ $target ];
							unset( $cleared[ $target ] );
						}
						break;
					case 'copy_value':
						$source_id = (string) ( $action['value'] ?? '' );
						if ( $source_id !== '' && array_key_exists( $source_id, $index['fields'] ) ) {
							$copied = array_key_exists( $source_id, $values ) ? $values[ $source_id ] : '';
							$values[ $target ] = $this->normalize_value_for_field( $form, $target, $copied );
							$set_values[ $target ] = $values[ $target ];
							unset( $cleared[ $target ] );
						}
						break;
					case 'clear_value':
						$values[ $target ] = '';
						$cleared[ $target ] = true;
						unset( $set_values[ $target ] );
						break;
					case 'set_placeholder':
					case 'set_help':
					case 'set_label':
						$ui_changes[] = array(
							'key' => $action_key,
							'target' => $target,
							'prop' => substr( $type, 4 ),
							'value' => (string) ( $action['value'] ?? '' ),
						);
						break;
					case 'focus_field':
						$focus_fields[] = array( 'key' => $action_key, 'target' => $target );
						break;
					case 'scroll_to_field':
						$scroll_fields[] = array( 'key' => $action_key, 'target' => $target );
						break;
					case 'show_message':
						$messages[] = array(
							'key' => $action_key,
							'target' => $target,
							'value' => (string) ( $action['value'] ?? '' ),
						);
						break;
					case 'jump_to_step':
						$jumps[] = array(
							'key' => $action_key,
							'target' => $target,
						);
						break;
				}
			}

			if ( function_exists( 'do_action' ) ) do_action( 'efb_logic_after_actions', $rule, $values, $form );

			if ( ! empty( $rule['stop_processing'] ) ) {
				foreach ( $rule_actions as $action ) {
					$target = $action['target'] ?? '';
					if ( $target === '' ) continue;
					$type = $action['type'] ?? '';
					if ( $is_step_action_type( $type ) ) $stopped_step_targets[ $target ] = true;
					else $stopped_field_targets[ $target ] = true;
				}
			}
		}

		/* A hidden step ignores the fields that live on it. Step ACTION TARGETS
		 * are step ids (`id_`), while a field's `step` is its POSITION — two
		 * namespaces that are both small integers and routinely disagree (a form
		 * whose steps were reordered has id_ "3" sitting at position 2).
		 * Resolving the target to its position is therefore the only correct
		 * comparison: matching the raw id against a position makes hiding step
		 * id_ "2" also ignore whatever happens to sit at position 2, silently
		 * stripping that step's answers from the submission. Only an
		 * unresolvable target — never produced by the builder, which validates
		 * targets against real step ids — is read as a position itself. */
		$ignored = $hidden + $disabled;
		$hidden_step_positions = array();
		foreach ( $hidden_steps as $step_id => $_unused ) {
			$hidden_step_positions[ isset( $index['steps'][ $step_id ]['step'] )
				? (string) $index['steps'][ $step_id ]['step']
				: (string) $step_id ] = true;
		}
		foreach ( $index['fields'] as $id => $field ) {
			$field_step = isset( $field['step'] ) ? (string) $field['step'] : '';
			if ( $field_step !== '' && isset( $hidden_step_positions[ $field_step ] ) ) {
				$ignored[ $id ] = true;
			}
		}

		return array(
			'is_conditional' => true,
			'stabilized' => true,
			'hidden_fields' => array_keys( $hidden ),
			'shown_fields' => array_keys( $shown ),
			'required_fields' => array_keys( $required ),
			'optional_fields' => array_keys( $optional ),
			'disabled_fields' => array_keys( $disabled ),
			'enabled_fields' => array_keys( $enabled ),
			'hidden_steps' => array_keys( $hidden_steps ),
			'shown_steps' => array_keys( $shown_steps ),
			'ignored_fields' => array_keys( $ignored ),
			'matched_rules' => $matched_rules,
			'set_values' => $set_values,
			'cleared_fields' => array_keys( $cleared ),
			'values_map' => $values,
			'messages' => $messages,
			'jumps' => $jumps,
			'trace' => $trace,
			'ui_changes' => $ui_changes,
			'focus_fields' => $focus_fields,
			'scroll_fields' => $scroll_fields,
			'submit_blocked' => $submit_blocked,
			'block_messages' => $block_messages,
			'end_form' => $end_form,
		);
	}

	private function sorted_rules( $rules ) {
		$clean = array();
		foreach ( $rules as $position => $rule ) {
			if ( ! is_array( $rule ) || empty( $rule['enabled'] ) ) continue;
			$items = $rule['conditions']['items'] ?? array();
			$actions = $rule['actions'] ?? array();
			if ( ! is_array( $items ) || empty( $items ) || ! is_array( $actions ) || empty( $actions ) ) continue;
			$rule['_position'] = $position;
			$rule['priority'] = (int) ( $rule['priority'] ?? 10 );
			$rule['id'] = isset( $rule['id'] ) ? (string) $rule['id'] : 'rule_' . $position;
			$clean[] = $rule;
		}
		usort( $clean, function ( $a, $b ) {
			if ( $a['priority'] === $b['priority'] ) return $a['_position'] - $b['_position'];
			return $a['priority'] - $b['priority'];
		} );
		return $clean;
	}

	private function legacy_rules( $form ) {
		$legacy = isset( $form[0]['conditions'] ) && is_array( $form[0]['conditions'] )
			? $form[0]['conditions']
			: array();
		$index = $this->index_structure( $form );
		$rules = array();

		foreach ( $legacy as $position => $condition ) {
			if ( ! is_array( $condition ) || ! $this->to_bool( $condition['state'] ?? false ) || empty( $condition['id_'] ) ) continue;
			$items = array();
			foreach ( ( $condition['condition'] ?? array() ) as $item ) {
				if ( ! is_array( $item ) || empty( $item['one'] ) || ! isset( $item['two'] ) || $item['two'] === '' ) continue;
				$items[] = array(
					'type' => 'condition',
					'source' => 'field',
					'field_id' => $item['one'],
					'compare' => $item['term'] ?? 'is',
					'value' => $item['two'],
				);
			}
			if ( empty( $items ) ) continue;

			$target = (string) $condition['id_'];
			$is_step = isset( $index['steps'][ $target ] );
			$show = ! isset( $condition['show'] ) || $this->to_bool( $condition['show'] );
			$rules[] = array(
				'id' => 'legacy_' . $position,
				'enabled' => true,
				'priority' => 10 + $position,
				'_position' => $position,
				'conditions' => array( 'type' => 'group', 'operator' => 'AND', 'items' => $items ),
				'actions' => array(
					array(
						'type' => $is_step ? ( $show ? 'show_step' : 'hide_step' ) : ( $show ? 'show_field' : 'hide_field' ),
						'target' => $target,
					),
				),
			);
		}
		return $rules;
	}

	private function evaluate_condition_group_internal( $group, $values, $form, $submitted, $env = null ) {
		$items = isset( $group['items'] ) && is_array( $group['items'] ) ? $group['items'] : array();
		if ( empty( $items ) ) return false;
		$result = false;

		foreach ( $items as $index => $item ) {
			$is_group = is_array( $item ) && ( ( $item['type'] ?? '' ) === 'group' || isset( $item['items'] ) );
			$matched = $is_group
				? $this->evaluate_condition_group_internal( $item, $values, $form, $submitted, $env )
				: $this->evaluate_condition( $item, $values, $form, $submitted, $env );
			if ( $index === 0 ) {
				$result = $matched;
				continue;
			}
			$connector = strtoupper( (string) ( $item['connector'] ?? ( $group['operator'] ?? 'AND' ) ) ) === 'OR' ? 'OR' : 'AND';
			$result = $connector === 'OR' ? ( $result || $matched ) : ( $result && $matched );
		}
		/* negate turns AND into NAND, OR into NOR, and a single item into NOT. */
		return ! empty( $group['negate'] ) ? ! $result : $result;
	}

	private function evaluate_condition( $condition, $values, $form, $submitted, $env = null ) {
		if ( ! is_array( $condition ) || empty( $condition['field_id'] ) ) return false;
		$field_id = (string) $condition['field_id'];
		$compare = (string) ( $condition['compare'] ?? 'is' );
		$expected = $condition['value'] ?? '';
		$source = (string) ( $condition['source'] ?? 'field' );
		if ( $env === null ) $env = $this->get_environment( $form );

		/* Non-field sources read from the request environment, not submitted rows. */
		if ( $source === 'query_param' ) {
			$param = (string) ( $condition['param'] ?? $condition['field_id'] );
			$query_value = isset( $env['query'][ $param ] ) ? $env['query'][ $param ] : '';
			return $this->compare_scalar( is_array( $query_value ) ? implode( ',', $query_value ) : $query_value, $expected, $compare );
		}
		if ( $source === 'user' ) {
			if ( $field_id === 'logged_in' ) {
				return $this->compare_scalar( ! empty( $env['user']['logged_in'] ) ? 'yes' : 'no', $expected, $compare );
			}
			if ( $field_id === 'role' ) {
				$roles = isset( $env['user']['roles'] ) && is_array( $env['user']['roles'] ) ? $env['user']['roles'] : array();
				$expected_role = strtolower( trim( (string) ( is_array( $expected ) ? implode( ',', $expected ) : $expected ) ) );
				$has_role = in_array( $expected_role, array_map( 'strtolower', array_map( 'strval', $roles ) ), true );
				if ( $compare === 'is' ) return $has_role;
				if ( $compare === 'is_not' ) return ! $has_role;
				if ( $compare === 'is_empty' ) return count( $roles ) === 0;
				if ( $compare === 'is_not_empty' ) return count( $roles ) > 0;
				return $this->compare_scalar( implode( ' ', $roles ), $expected, $compare );
			}
			return false;
		}
		if ( $source === 'current_step' ) {
			$step = isset( $env['current_step'] ) && $env['current_step'] !== null ? (string) $env['current_step'] : '';
			return $this->compare_scalar( $step, $expected, $compare );
		}

		if ( in_array( $compare, array( 'is_paid', 'is_not_paid', 'amount_eq', 'amount_gt', 'amount_lt' ), true ) ) {
			$payment = $this->payment_state( $field_id, $form, $submitted, $values );
			if ( $compare === 'is_paid' ) return $payment['paid'];
			if ( $compare === 'is_not_paid' ) return ! $payment['paid'];
			if ( ! is_numeric( $payment['amount'] ) || ! is_numeric( $expected ) ) return false;
			if ( $compare === 'amount_eq' ) return abs( (float) $payment['amount'] - (float) $expected ) < 0.00001;
			if ( $compare === 'amount_gt' ) return (float) $payment['amount'] > (float) $expected;
			return (float) $payment['amount'] < (float) $expected;
		}

		$raw_expected = $expected;
		$value = array_key_exists( $field_id, $values ) ? $values[ $field_id ] : '';
		$expected = $this->normalize_value_for_field( $form, $field_id, $expected );

		if ( is_array( $value ) ) {
			/* A multi-value field (checkbox, multiselect) holds one entry per
			 * ticked option; the condition names exactly ONE of them. Two things
			 * made the naive membership test always wrong:
			 *   - normalize_value_for_field() shapes its result like the FIELD,
			 *     so a checkbox operand comes back wrapped, and a strict
			 *     in_array( array('c1'), array('c1'), true ) is false.
			 *   - the two sides can spell the same option differently — the
			 *     builder stores the option's id_, a row records id_ob but falls
			 *     back to the visible text when that is missing, and multiselect
			 *     rows keep text.
			 * The effect was that every "checkbox is X" silently never fired and
			 * every "checkbox is_not X" fired even when X was ticked. Match on a
			 * SCALAR, and accept either spelling of the option. */
			$wanted = array();
			$want = function ( $candidate ) use ( &$wanted ) {
				if ( $candidate === null || $candidate === '' || is_array( $candidate ) ) return;
				$text = strtolower( trim( (string) $candidate ) );
				if ( $text !== '' && ! in_array( $text, $wanted, true ) ) $wanted[] = $text;
			};
			foreach ( (array) $expected as $candidate ) $want( $candidate );
			$want( $raw_expected );

			$index = $this->index_structure( $form );
			$options_for_field = isset( $index['options'][ $field_id ] ) ? $index['options'][ $field_id ] : array();
			foreach ( $options_for_field as $option_id => $option_text ) {
				/* both directions: the operand may be the option id (what the
				 * builder writes) or its visible text (what an imported or
				 * hand-written rule may carry), and a row may record either. */
				if ( (string) $option_id === (string) $raw_expected ) $want( $option_text );
				if ( (string) $option_text === (string) $raw_expected ) $want( $option_id );
			}

			$present = false;
			foreach ( $value as $entry ) {
				if ( in_array( strtolower( trim( (string) $entry ) ), $wanted, true ) ) { $present = true; break; }
			}
			if ( $compare === 'is' ) return $present;
			if ( $compare === 'is_not' ) return ! $present;
			if ( $compare === 'is_empty' ) return count( $value ) === 0;
			if ( $compare === 'is_not_empty' ) return count( $value ) > 0;
			$joined = implode( ' ', array_map( 'strval', $value ) );
			return $this->compare_scalar( $joined, $expected, $compare );
		}

		return $this->compare_scalar( $value, $expected, $compare );
	}

	private function compare_scalar( $value, $expected, $compare ) {
		$value = trim( (string) $value );
		$expected_scalar = is_array( $expected ) ? implode( ',', $expected ) : trim( (string) $expected );
		$value_lower = strtolower( $value );
		$expected_lower = strtolower( $expected_scalar );

		switch ( $compare ) {
			case 'is': return $value_lower === $expected_lower;
			case 'is_not': return $value_lower !== $expected_lower;
			case 'contains': return strpos( $value_lower, $expected_lower ) !== false;
			case 'not_contains': return strpos( $value_lower, $expected_lower ) === false;
			case 'starts_with': return strpos( $value_lower, $expected_lower ) === 0;
			case 'ends_with':
				$length = strlen( $expected_lower );
				return $length === 0 || substr( $value_lower, -$length ) === $expected_lower;
			case 'gt': return is_numeric( $value ) && is_numeric( $expected_scalar ) && (float) $value > (float) $expected_scalar;
			case 'gte': return is_numeric( $value ) && is_numeric( $expected_scalar ) && (float) $value >= (float) $expected_scalar;
			case 'lt': return is_numeric( $value ) && is_numeric( $expected_scalar ) && (float) $value < (float) $expected_scalar;
			case 'lte': return is_numeric( $value ) && is_numeric( $expected_scalar ) && (float) $value <= (float) $expected_scalar;
			case 'between':
			case 'not_between':
				$range = is_array( $expected ) ? $expected : preg_split( '/\s*,\s*/', $expected_scalar );
				/* Numeric operators never match a non-numeric value: an empty budget is
				 * neither inside nor outside the range, so not_between must not fire. */
				if ( count( $range ) < 2 || ! is_numeric( $value ) || ! is_numeric( $range[0] ) || ! is_numeric( $range[1] ) ) return false;
				$inside = (float) $value >= (float) $range[0] && (float) $value <= (float) $range[1];
				return $compare === 'between' ? $inside : ! $inside;
			case 'is_empty': return $value === '';
			case 'is_not_empty': return $value !== '';
			case 'date_before':
			case 'date_after':
				$value_ts = $this->date_timestamp( $value );
				$expected_ts = $this->date_timestamp( $expected_scalar );
				if ( $value_ts === null || $expected_ts === null ) return false;
				return $compare === 'date_before' ? $value_ts < $expected_ts : $value_ts > $expected_ts;
			case 'date_between':
				$date_range = is_array( $expected ) ? $expected : preg_split( '/\s*,\s*/', $expected_scalar );
				if ( count( $date_range ) < 2 ) return false;
				$ts = $this->date_timestamp( $value );
				$from_ts = $this->date_timestamp( $date_range[0] );
				$to_ts = $this->date_timestamp( $date_range[1] );
				if ( $ts === null || $from_ts === null || $to_ts === null ) return false;
				return $ts >= $from_ts && $ts <= $to_ts;
			default: return false;
		}
	}

	/**
	 * Second-precision timestamp for a date string, or null when unparseable.
	 * Mirrors the JS runtime: invalid/empty dates never match any date operator.
	 */
	private function date_timestamp( $value ) {
		$text = trim( (string) $value );
		if ( $text === '' ) return null;
		$parsed = strtotime( strlen( $text ) === 10 ? $text . ' 00:00:00' : $text );
		return $parsed === false ? null : $parsed;
	}

	private function resolve_action_value( $action, $form, $values ) {
		$value = $action['value'] ?? '';
		if ( ( $action['value_type'] ?? 'static' ) !== 'autofill_key' ) return $value;

		$conditions = isset( $form[0]['autofill_conditions'] ) && is_array( $form[0]['autofill_conditions'] )
			? $form[0]['autofill_conditions']
			: array();
		foreach ( $conditions as $condition ) {
			if ( ! is_array( $condition ) || ( $condition['source'] ?? '' ) !== $value ) continue;
			$source_id = $condition['id_'] ?? '';
			return $source_id !== '' && array_key_exists( $source_id, $values ) ? $values[ $source_id ] : null;
		}
		return null;
	}

	private function number_from_calculation_value( $value ) {
		if ( is_array( $value ) ) {
			$total = 0.0;
			foreach ( $value as $item ) {
				if ( $item === '' || $item === null ) continue;
				if ( ! is_numeric( $item ) ) return array( 'valid' => false, 'value' => 0.0 );
				$total += (float) $item;
			}
			return array( 'valid' => true, 'value' => $total );
		}
		if ( $value === '' || $value === null ) return array( 'valid' => true, 'value' => 0.0 );
		if ( ! is_numeric( $value ) ) return array( 'valid' => false, 'value' => 0.0 );
		return array( 'valid' => true, 'value' => (float) $value );
	}

	private function tokenize_calculation_formula( $formula ) {
		$text = substr( (string) $formula, 0, 500 );
		$tokens = array();
		$length = strlen( $text );
		$i = 0;

		while ( $i < $length ) {
			$ch = $text[ $i ];
			if ( preg_match( '/\s/', $ch ) ) {
				$i++;
				continue;
			}
			if ( $ch === '{' || $ch === '[' ) {
				$close = $ch === '{' ? '}' : ']';
				$end = strpos( $text, $close, $i + 1 );
				if ( $end === false ) return null;
				$ref = trim( substr( $text, $i + 1, $end - $i - 1 ) );
				if ( $ref === '' ) return null;
				$tokens[] = array( 'type' => 'ref', 'value' => $ref );
				$i = $end + 1;
				continue;
			}
			if ( preg_match( '/[0-9.]/', $ch ) ) {
				$start = $i;
				$dots = 0;
				while ( $i < $length && preg_match( '/[0-9.]/', $text[ $i ] ) ) {
					if ( $text[ $i ] === '.' ) $dots++;
					$i++;
				}
				$literal = substr( $text, $start, $i - $start );
				if ( $literal === '.' || $dots > 1 || ! is_numeric( $literal ) ) return null;
				$tokens[] = array( 'type' => 'number', 'value' => (float) $literal );
				continue;
			}
			if ( preg_match( '/[A-Za-z_]/', $ch ) ) {
				$start = $i;
				while ( $i < $length && preg_match( '/[A-Za-z0-9_-]/', $text[ $i ] ) ) $i++;
				$tokens[] = array( 'type' => 'ref', 'value' => substr( $text, $start, $i - $start ) );
				continue;
			}
			if ( strpos( '+-*/()', $ch ) !== false ) {
				$tokens[] = array(
					'type' => ( $ch === '(' || $ch === ')' ) ? 'paren' : 'op',
					'value' => $ch,
				);
				$i++;
				continue;
			}
			return null;
		}

		return $tokens;
	}

	private function parse_calculation_expression( $tokens, &$pos, $form, $values ) {
		$left = $this->parse_calculation_term( $tokens, $pos, $form, $values );
		while ( ! empty( $left['valid'] ) && $pos < count( $tokens ) && $tokens[ $pos ]['type'] === 'op' && in_array( $tokens[ $pos ]['value'], array( '+', '-' ), true ) ) {
			$op = $tokens[ $pos ]['value'];
			$pos++;
			$right = $this->parse_calculation_term( $tokens, $pos, $form, $values );
			if ( empty( $right['valid'] ) ) return $right;
			$left['value'] = $op === '+' ? $left['value'] + $right['value'] : $left['value'] - $right['value'];
		}
		return $left;
	}

	private function parse_calculation_term( $tokens, &$pos, $form, $values ) {
		$left = $this->parse_calculation_factor( $tokens, $pos, $form, $values );
		while ( ! empty( $left['valid'] ) && $pos < count( $tokens ) && $tokens[ $pos ]['type'] === 'op' && in_array( $tokens[ $pos ]['value'], array( '*', '/' ), true ) ) {
			$op = $tokens[ $pos ]['value'];
			$pos++;
			$right = $this->parse_calculation_factor( $tokens, $pos, $form, $values );
			if ( empty( $right['valid'] ) ) return $right;
			if ( $op === '/' && abs( $right['value'] ) < 0.000000000001 ) return array( 'valid' => false, 'value' => 0.0 );
			$left['value'] = $op === '*' ? $left['value'] * $right['value'] : $left['value'] / $right['value'];
		}
		return $left;
	}

	private function parse_calculation_factor( $tokens, &$pos, $form, $values ) {
		if ( $pos >= count( $tokens ) ) return array( 'valid' => false, 'value' => 0.0 );
		$token = $tokens[ $pos ];
		if ( $token['type'] === 'op' && in_array( $token['value'], array( '+', '-' ), true ) ) {
			$pos++;
			$unary = $this->parse_calculation_factor( $tokens, $pos, $form, $values );
			if ( empty( $unary['valid'] ) ) return $unary;
			return array( 'valid' => true, 'value' => $token['value'] === '-' ? -$unary['value'] : $unary['value'] );
		}
		if ( $token['type'] === 'number' ) {
			$pos++;
			return array( 'valid' => true, 'value' => $token['value'] );
		}
		if ( $token['type'] === 'ref' ) {
			$pos++;
			$index = $this->index_structure( $form );
			$field_id = (string) $token['value'];
			if ( ! array_key_exists( $field_id, $index['fields'] ) ) return array( 'valid' => false, 'value' => 0.0 );
			return $this->number_from_calculation_value( array_key_exists( $field_id, $values ) ? $values[ $field_id ] : '' );
		}
		if ( $token['type'] === 'paren' && $token['value'] === '(' ) {
			$pos++;
			$nested = $this->parse_calculation_expression( $tokens, $pos, $form, $values );
			if ( empty( $nested['valid'] ) || $pos >= count( $tokens ) || $tokens[ $pos ]['type'] !== 'paren' || $tokens[ $pos ]['value'] !== ')' ) {
				return array( 'valid' => false, 'value' => 0.0 );
			}
			$pos++;
			return $nested;
		}
		return array( 'valid' => false, 'value' => 0.0 );
	}

	private function evaluate_calculation_formula( $formula, $form, $values ) {
		$tokens = $this->tokenize_calculation_formula( $formula );
		if ( empty( $tokens ) ) return null;
		$pos = 0;
		$result = $this->parse_calculation_expression( $tokens, $pos, $form, $values );
		if ( empty( $result['valid'] ) || $pos !== count( $tokens ) || ! is_finite( $result['value'] ) ) return null;
		return (float) $result['value'];
	}

	private function format_calculation_result( $value, $decimals ) {
		$has_decimals = $decimals !== null && $decimals !== '';
		if ( $has_decimals ) {
			$places = max( 0, min( 6, intval( $decimals ) ) );
			return number_format( (float) $value, $places, '.', '' );
		}
		$text = rtrim( rtrim( sprintf( '%.10F', (float) $value ), '0' ), '.' );
		return $text === '' || $text === '-0' ? '0' : $text;
	}

	private function resolve_calculation_value( $action, $form, $values ) {
		$formula = trim( (string) ( $action['value'] ?? '' ) );
		if ( $formula === '' ) return null;
		$value = $this->evaluate_calculation_formula( $formula, $form, $values );
		if ( $value === null ) return null;
		return $this->format_calculation_result( $value, $action['decimals'] ?? null );
	}

	private function normalize_value_for_field( $form, $field_id, $value ) {
		$index = $this->index_structure( $form );
		$type = strtolower( (string) ( $index['fields'][ $field_id ]['type'] ?? '' ) );
		if ( $type === 'yesno' ) {
			$value = strtolower( (string) $value );
			if ( $value === $field_id . '_1' || $value === '1' ) return 'yes';
			if ( $value === $field_id . '_2' || $value === '0' ) return 'no';
			return in_array( $value, array( 'yes', 'no' ), true ) ? $value : '';
		}
		if ( in_array( $type, self::$select_types, true ) || in_array( $type, self::$multiselect_types, true ) ) {
			$values = is_array( $value ) ? $value : array( $value );
			$resolved = array();
			foreach ( $values as $item ) {
				$resolved[] = isset( $index['options'][ $field_id ][ $item ] )
					? $index['options'][ $field_id ][ $item ]
					: $item;
			}
			return in_array( $type, self::$multiselect_types, true ) ? $resolved : reset( $resolved );
		}
		if ( in_array( $type, self::$checkbox_types, true ) ) return (array) $value;
		return $value;
	}

	private function payment_state( $field_id, $form, $submitted, $values ) {
		$paid_states = array( 'paid', 'succeeded', 'success', 'completed', 'complete', 'approved', 'captured', 'authorized' );
		$paid = false;
		$amount = null;

		foreach ( $submitted as $row ) {
			if ( ! is_array( $row ) ) continue;
			$row_type = strtolower( (string) ( $row['type'] ?? '' ) );
			$is_target = ( $row['id_'] ?? '' ) === $field_id;
			if ( ! $is_target && ! in_array( $row_type, self::$payment_types, true ) ) continue;

			foreach ( array( 'payment_status', 'status', 'state' ) as $key ) {
				if ( isset( $row[ $key ] ) && in_array( strtolower( (string) $row[ $key ] ), $paid_states, true ) ) $paid = true;
			}
			if ( ! empty( $row['paymentIntent'] ) || ! empty( $row['transaction_id'] ) || ! empty( $row['refId'] ) || ! empty( $row['authority'] ) ) {
				$paid = true;
			}
			/* Money keys in priority order, FIRST match wins. `amount` comes last
			 * on purpose: in an EFB row `amount` is the field's ORDERING INDEX,
			 * and the row a gateway pushes after a successful charge sets it to 0
			 * while the real figure sits in paymentAmount (and value). Reading
			 * `amount` as money made every amount_gt false and every amount_lt
			 * true — a "small payment" rule fired on a $10,000 charge. */
			$row_amount = null;
			$keys = in_array( $row_type, self::$payment_types, true ) ? self::$money_keys_payment : self::$money_keys;
			foreach ( $keys as $key ) {
				if ( isset( $row[ $key ] ) && is_numeric( $row[ $key ] ) ) { $row_amount = (float) $row[ $key ]; break; }
			}
			if ( $row_amount !== null ) $amount = $row_amount;
		}

		if ( $amount === null && isset( $values[ $field_id ] ) && is_numeric( $values[ $field_id ] ) ) {
			$amount = (float) $values[ $field_id ];
		}
		return array( 'paid' => $paid, 'amount' => $amount );
	}

	private function submission_rows_for_value( $form, $field_id, $value ) {
		$index = $this->index_structure( $form );
		if ( ! isset( $index['fields'][ $field_id ] ) ) return array();
		$type = (string) ( $index['fields'][ $field_id ]['type'] ?? 'text' );
		$type_lower = strtolower( $type );

		if ( $type_lower === 'yesno' ) {
			$yes = strtolower( (string) $value ) === 'yes';
			return array( array(
				'id_' => $field_id,
				'type' => $type,
				'id_ob' => $field_id . ( $yes ? '_1' : '_2' ),
				'value' => $yes ? 'yes' : 'no',
			) );
		}

		if ( in_array( $type_lower, self::$radio_types, true ) ) {
			return array( array( 'id_' => $field_id, 'type' => $type, 'id_ob' => (string) $value, 'value' => (string) $value ) );
		}

		if ( in_array( $type_lower, self::$checkbox_types, true ) ) {
			$rows = array();
			foreach ( (array) $value as $option ) {
				$rows[] = array( 'id_' => $field_id, 'type' => $type, 'id_ob' => (string) $option, 'value' => (string) $option );
			}
			return $rows;
		}

		if ( in_array( $type_lower, self::$multiselect_types, true ) ) {
			$value = is_array( $value ) ? implode( '@efb!', $value ) : (string) $value;
		}
		return array( array( 'id_' => $field_id, 'type' => $type, 'value' => is_array( $value ) ? implode( ',', $value ) : (string) $value ) );
	}

	private function build_submitted_ids( $submitted_values ) {
		$ids = array();
		foreach ( $submitted_values as $row ) {
			if ( ! is_array( $row ) || empty( $row['id_'] ) ) continue;
			$type = strtolower( (string) ( $row['type'] ?? '' ) );
			$value = $row['value'] ?? '';
			if ( in_array( $type, self::$checkbox_types, true ) && ! empty( $row['id_ob'] ) ) {
				$ids[ $row['id_'] ] = true;
			} elseif ( ( in_array( $type, self::$radio_types, true ) || $type === 'yesno' ) && ! empty( $row['id_ob'] ) ) {
				$ids[ $row['id_'] ] = true;
			} elseif ( in_array( $type, array( 'file', 'dadfile', 'esign', 'audio_recorder', 'video_recorder', 'screen_recorder' ), true ) && ( $value !== '' || ! empty( $row['url'] ) ) ) {
				$ids[ $row['id_'] ] = true;
			} elseif ( is_array( $value ) ? ! empty( $value ) : ( $value !== '' && $value !== null ) ) {
				$ids[ $row['id_'] ] = true;
			}
		}
		return $ids;
	}

	private function index_structure( $form ) {
		$fields = array();
		$steps = array();
		$options = array();
		foreach ( $form as $item ) {
			if ( ! is_array( $item ) || empty( $item['id_'] ) ) continue;
			$id = (string) $item['id_'];
			$type = strtolower( (string) ( $item['type'] ?? '' ) );
			if ( $type === 'step' ) {
				$steps[ $id ] = $item;
			} elseif ( $type === 'option' ) {
				$parent = isset( $item['parent'] ) ? (string) $item['parent'] : '';
				if ( $parent !== '' ) $options[ $parent ][ $id ] = isset( $item['value'] ) ? $item['value'] : $id;
			} elseif ( ! in_array( $type, self::$structural_types, true ) ) {
				$fields[ $id ] = $item;
			}
		}
		return array( 'fields' => $fields, 'steps' => $steps, 'options' => $options );
	}

	private function to_bool( $value ) {
		return $value === true || $value === 1 || $value === '1' || $value === 'true';
	}
}

add_filter( 'efb_logic_evaluate', function ( $default, $form_fields_array, $submitted_values ) {
	$validator = new Emsfb_Logic_Validator();
	return $validator->evaluate( $form_fields_array, $submitted_values );
}, 10, 3 );

add_filter( 'efb_logic_prepare_submission', function ( $default, $form_fields_array, $submitted_values ) {
	$validator = new Emsfb_Logic_Validator();
	return $validator->prepare_submission( $form_fields_array, $submitted_values );
}, 10, 3 );

add_filter( 'efb_logic_validate_required', function ( $default, $form_fields_array, $submitted_values, $logic_result ) {
	$validator = new Emsfb_Logic_Validator();
	return $validator->validate_required_fields( $form_fields_array, $submitted_values, $logic_result );
}, 10, 4 );
