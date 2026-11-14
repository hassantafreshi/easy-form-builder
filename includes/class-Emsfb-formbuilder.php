<?php
 namespace Emsfb;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
    class Formbuilder {
       public $valj_efb;
       private $pro_efb = false;
	   public $pub_bg_button_color_efb='btn-primary';
	   public $package_type_efb = 0;
    private $mobile_pos = ['', 'col-12', 'col-12', 'col-12'];
    /* F18: the element being rendered and the phrases, for the administrator's note on a locked field. */
    private $pro_notice_type_efb = '';
    private $pro_notice_texts_efb = array();
    /** F02: whether a field of this form asks for a one-time code or a second e-mail copy. */
    public $verify_fields_efb = false;
    /** The last plan notice built, so a field drawn as nothing but that notice can be wrapped like its real markup. */
    private $last_pro_notice_efb = null;
    /**
     * F08: the smart-tag context of this page as the HTML may carry it (the same for every visitor of it):
     * form id / name, site, host post, and the form's fields (for {field:…} pieces). Never a user value.
     */
    public $st_ctx = array();
    /* F05: option id => places left of the choices with a capacity (Stock::left(); a cached page is refreshed by prepare). */
    public $stock_left = array();

	/** F08: a label, description, option text or heading with its smart tags (unchanged text without a brace). */
	private function st_markup_efb($text, $where, $raw_static = false) {
		$text = is_scalar($text) ? (string) $text : '';
		if (strpos($text, '{') === false || !class_exists('\\Emsfb\\Smart_Tags')) return $raw_static ? $text : esc_html($text);
		return \Emsfb\Smart_Tags::markup($text, $where, $this->st_ctx, $raw_static);
	}

	/** F08: the aria-label of a field whose label holds tags: its text with the fallbacks (the page updates it); esc_attr($name) otherwise. */
	private function st_aria_efb($name) {
		$name = is_scalar($name) ? (string) $name : '';
		if (strpos($name, '{') === false || !class_exists('\\Emsfb\\Smart_Tags') || !\Emsfb\Smart_Tags::has_tags($name)) return esc_attr($name);
		return esc_attr(trim(\Emsfb\Smart_Tags::resolve($name, $this->st_ctx, 'text', 'label')));
	}

	/** F08: [attribute value, extra attribute] of a default value or placeholder that may hold smart tags. */
	private function st_attr_efb($text, $where, $data_attr) {
		$text = is_scalar($text) ? (string) $text : '';
		if (strpos($text, '{') === false || !class_exists('\\Emsfb\\Smart_Tags')) return array($text, '');
		$a = \Emsfb\Smart_Tags::attr_value($text, $where, $this->st_ctx);
		return array($a['value'], $a['template'] !== '' ? sprintf(' %s="%s"', $data_attr, esc_attr($a['template'])) : '');
	}
        public function __construct( $valj_efb, $pro_efb ) {

            $this->valj_efb =  $valj_efb;
            $this->pro_efb = $pro_efb;
			$this->package_type_efb = (int) get_option('emsfb_pro' ,2);
        }

	private function generateDescription_efb($rndm, $vj, $pos) {

		$mobile_hide_description = $this->pro_efb && isset($vj->mobile_hfdescription) && (int) $vj->mobile_hfdescription === 1;
		$mx = $pos[1] == 'col-md-4' || (isset($vj->message_align) && $vj->message_align != "justify-content-start") ? '' : 'mx-4';
		$msg_align = isset($vj->message_align) ? $vj->message_align : '';
		$msg_txt_color = isset($vj->message_text_color) ? $vj->message_text_color : '';
		$msg = isset($vj->message) ? $vj->message : '';
		// F08: tags in a description become isolated pieces; the text itself stays as saved.
		if (is_string($msg) && strpos($msg, '{') !== false) $msg = $this->st_markup_efb($msg, 'label', true);

		/* The description sits in a block-level wrapper. wpautop breaks content
		 * after every closing block tag and keeps a paragraph around any piece
		 * that does not start with a block tag, so a description opening with
		 * <small> - an inline tag - was one of the stray paragraphs reported
		 * between the fields on themes that run the filter over form markup.
		 * The <small> itself is left as it is: three stylesheet rules select it
		 * by tag name, and the wrapper adds no box of its own. */
		return '<div class="efb efb-field-description">'
			. '<small id="' . $rndm . '-des" class="efb form-text d-flex fs-7 col-sm-12 efb ' . ($mobile_hide_description ? 'd-none d-md-flex' : '') . ' ' . $mx . ' ' . $msg_align . ' ' . $msg_txt_color . ' ' . (isset($vj->message_text_size) ? $vj->message_text_size : '') . ' ">' . $msg . '</small>'
			. '</div>';
	}

	private function generateLabel_efb($rndm, $vj, $pos, $mobile_pos = null) {

		$global_mobile_hide_label = isset($this->valj_efb[0]->global_mobile_hflabel) && (int) $this->valj_efb[0]->global_mobile_hflabel === 1;
		$mobile_hide_label = $this->pro_efb && ($global_mobile_hide_label || (isset($vj->mobile_hflabel) && (int) $vj->mobile_hflabel === 1));
		$label_align = isset($vj->label_align) ? $vj->label_align : '';
		$label_text_size = isset($vj->label_text_size) && $vj->label_text_size != "default" ? $vj->label_text_size : '';
		$required  ='<span class="efb mx-1 text-danger" id="' . $rndm . '_req" role="none">';
		$required .= isset($vj->required) && ($vj->required == 1 || $vj->required == true) ?  '*</span>' : '</span>';
		$label_color = isset($vj->label_text_color) ? $vj->label_text_color : '';

		$label_classes = [
			'efb',
			'mx-0',
			'px-0',
			'pt-2',
			'pb-1',
			$pos[2],
			($mobile_pos !== null ? $mobile_pos[2] : 'col-12'),
			'col-form-label',
			(isset($vj->hflabel) && $vj->hflabel == 1 ? 'd-none' : ''),
			($mobile_hide_label ? 'd-none d-md-block' : ''),
			$label_color,
			$label_align,
			$label_text_size
		];

		$label_class_str = implode(' ', array_filter($label_classes));

		return '<label for="' . $rndm . '_" class="' . $label_class_str . '" id="' . $rndm . '_labG"><span id="' . $rndm . '_lab" class="efb ' . $label_text_size . '">' . $this->st_markup_efb(isset($vj->name) ? $vj->name : '', 'label') . '</span>' . $required . '</label>';
	}

	/**
	 * Deliberately left inline, and it has to stay that way.
	 *
	 * wpautop keeps a paragraph around a piece of content that does not open
	 * with a block tag, and this tooltip opens one such piece - 24 of them
	 * across the site's forms. Wrapping it in a block-level parent does fix
	 * those, but the closing tag of that wrapper becomes a fresh cut, and the
	 * element right behind the tooltip is the field's own <input> (see the
	 * order in generateTextInput_efb: label, id div, tooltip, input, then the
	 * description). Measured: 24 pieces fixed, 536 inputs newly exposed.
	 *
	 * A wrapper is only ever worth it when a block tag already follows the
	 * element being wrapped, which is the case for the upload separator and
	 * the payment buttons but not here.
	 */
	private function generateTooltip_efb($rndm) {
		return '<small id="' . $rndm . '_-message" class="efb py-1 fs-7 tx ttiptext px-2" style="display:none"> ! </small>';
	}

	private function generateDivFId_efb($rndm, $pos, $mobile_pos = null) {
		return '<div class="efb ' . $pos[3] . ' ' . ($mobile_pos !== null ? $mobile_pos[3] : 'col-12') . ' px-0 mx-0 ttEfb show" id="' . $rndm . '-f">';
	}

	private function generateElementSpecificFields_efb($elementId, $rndm, $vj, $pos, $desc, $label, $ttip, $div_f_id, $aire_describedby, $disabled,$form_id,$texts) {
		$fields = ['ui' => '', 'dataTag' => ''];
		switch ($elementId) {
			case 'email':
			case 'text':
			case 'password':
			case 'tel':
			case 'url':
			case "date":
			case 'color':
			case 'number':
			case 'firstName':
			case 'lastName':
			case 'datetime-local':
			case 'postalcode':
			case 'address_line':
				$textElements = ['firstName', 'lastName', 'postalcode', 'address_line','datetime-local'];
				// The HTML placeholder attribute is supported for password inputs.
				// Keep it disabled only for native controls where browsers do not
				// consistently render it.
				$placeholderElements = ['color', 'range', 'date'];

				$isTextType = in_array($elementId, $textElements);
				$isPlaceholderType = !in_array($elementId, $placeholderElements);

				$type = $isTextType ? 'text' : $elementId;
				$autocomplete = $this->generateAutocomplete_efb($elementId);
				$placeholder = '';
				if ($isPlaceholderType && isset($vj->placeholder)) {
					// F08: a placeholder with visitor tags is filled in the browser (data-st-ph), never in the HTML.
					list($ph_efb, $ph_attr_efb) = $this->st_attr_efb($vj->placeholder, 'placeholder', 'data-st-ph');
					$placeholder = sprintf('placeholder="%s"', esc_attr($ph_efb)) . $ph_attr_efb;
				}
				$telPattern = ($elementId === 'tel') ? 'pattern="^\+?(?:[0-9]|\s|\.|\(|\)|-){7,25}$"' : '';
				$lenAttributes = $this->generateLengthAttributes_efb($elementId, $vj);
				$classes = $elementId !== 'range' ? sprintf('form-control %s', $vj->el_border_color) : 'form-range';
				// F01: masked fields replace the fixed tel pattern and the min/max length attributes. The
				// extra attributes travel in the tel-pattern slot, so an unmasked field renders byte for byte
				// as before.
				$mask = $this->generateMaskAttributes_efb($elementId, $vj);
				if ($mask['masked']) {
					$telPattern = $mask['attrs'];
					$lenAttributes = ['maxlen' => '', 'minlen' => ''];
					$classes .= ' efb-ltr-in-rtl';
					if ($mask['autocomplete'] !== '') {
						$autocomplete = $mask['autocomplete'];
					}
				} elseif ($mask['attrs'] !== '') {
					$telPattern = trim($telPattern . ' ' . $mask['attrs']);
				}

				$fields['ui'] = $this->generateTextInput_efb(
				$type,$classes,$vj,$rndm,$desc,$label,$ttip,$div_f_id,$placeholder,$lenAttributes,$aire_describedby,$disabled,$autocomplete,$form_id,$telPattern
				);
				$fields['dataTag'] = $elementId;
				break;
			case 'switch':

				wp_enqueue_script('efb-bootstrap-bundle-min-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/bootstrap.bundle.min-efb.js', array( 'jquery' ), true,EMSFB_PLUGIN_VERSION);
				$vj->on = $vj->on ?? $texts['on'];
				$vj->off = $vj->off ?? $texts['off'];

				$ui = sprintf('
					%s
					%s
					<div class="efb %s ' . $this->mobile_pos[3] . ' px-0 mx-0 ttEfb show" id="%s-f" %s>
						<label class="efb fs-6" id="%s_off">%s</label>

						<button type="button" data-state="off" class="efb btn %s btn-toggle efb1 %s" data-css="%s" data-toggle="button" aria-pressed="false" data-vid="%s" onclick="fun_switch_efb(this)" data-id="%s-el" data-formid="%s" id="%s_" %s>
							<div class="efb handle"></div>
						</button>
						<label class="efb fs-6" id="%s_on">%s</label>
						<div class="efb mb-3">%s</div>
					',
					$label,
					$ttip,
					$pos[3],
					$rndm,
					$aire_describedby,

					$rndm,
					is_rtl() ?  $vj->on : $vj->off,

					$vj->el_height,
					str_replace(',', ' ', $vj->classes),
					$rndm,
					$rndm,
					$rndm,
					$form_id,
					$rndm,
					$disabled,
					$rndm,
					is_rtl() ?  $vj->off : $vj->on,
					$desc
				);

				$fields['ui']  = $this->pro_efb ? $ui : $this->public_pro_message_efb($texts['tfnapca'], isset($texts['thisFeatureAvailableFreePlusPro']) ? $texts['thisFeatureAvailableFreePlusPro'] : '');
				$fields['dataTag'] = $elementId;

				break;

			default:
				return false;
		}

		return $fields;
	}

	/**
	 * F01: attributes of a masked text-like input (and the H inputmode choice for any text-like field).
	 * Returns ['masked' => bool, 'attrs' => string, 'autocomplete' => string]. Everything is escaped.
	 * A field without mask keys gets masked=false and attrs='' (unchanged markup).
	 */
	private function generateMaskAttributes_efb($elementId, $vj) {
		$out = ['masked' => false, 'attrs' => '', 'autocomplete' => ''];
		$row = is_object($vj) ? get_object_vars($vj) : (is_array($vj) ? $vj : []);
		$inputmodes = ['text', 'numeric', 'decimal', 'tel', 'email', 'url', 'none'];
		$im = isset($row['inputmode']) && is_string($row['inputmode']) && in_array($row['inputmode'], $inputmodes, true) ? $row['inputmode'] : '';
		$fd = null;
		if (in_array($elementId, ['text', 'tel', 'postalcode'], true) && !empty($row['mask_preset']) && class_exists('\\Emsfb\\Masks')) {
			$fd = \Emsfb\Masks::field_def($row);
		}
		if ($fd === null) {
			if ($im !== '') {
				$out['attrs'] = sprintf('inputmode="%s"', esc_attr($im));
			}
			return $out;
		}
		$def = $fd['def'];
		$attrs = [];
		$pattern = '';
		if ($fd['kind'] === 'numeric') {
			// F01-I1: the number settings stay in the form structure; the runtime reads them from there.
			$attrs[] = 'data-mask-preset="numeric"';
			if ($im === '') {
				$im = $def['negative'] ? '' : ($def['digits'] > 0 ? 'decimal' : 'numeric');
			}
		} elseif ($fd['kind'] === 'custom') {
			$attrs[] = sprintf('data-mask="%s"', esc_attr($def['mask']));
			if (!empty($def['case'])) {
				$attrs[] = sprintf('data-mask-case="%s"', esc_attr($def['case']));
			}
			$pattern = $def['mask'];
			if ($im === '') {
				$kinds = [];
				foreach (\Emsfb\Masks::compile_mask($pattern) as $t) {
					if ($t['k'] !== 'lit') {
						$kinds[$t['k']] = true;
					}
				}
				$im = array_keys($kinds) === ['digit'] ? 'numeric' : '';
			}
		} else {
			$attrs[] = sprintf('data-mask-preset="%s"', esc_attr($def['id']));
			$pattern = !empty($def['mask']) && empty($def['e164']) ? $def['mask'] : '';
			if ($im === '' && !empty($def['inputmode'])) {
				$im = $def['inputmode'];
			}
			// The preset's autocomplete is only a default: an explicit field value (F16) wins.
			if (!empty($def['autocomplete']) && !(isset($row['autocomplete']) && is_string($row['autocomplete']) && $row['autocomplete'] !== '')) {
				$out['autocomplete'] = $def['autocomplete'];
			}
		}
		$attrs[] = sprintf('data-mask-store="%s"', isset($row['mask_store']) && $row['mask_store'] === 'display' ? 'display' : 'canonical');
		if ($fd['kind'] === 'preset' && isset($row['mask_strict']) && in_array($row['mask_strict'], [1, '1', true], true) && \Emsfb\Masks::has_strict($def)) {
			$attrs[] = 'data-mask-strict="1"';
		}
		if ($im !== '') {
			$attrs[] = sprintf('inputmode="%s"', esc_attr($im));
		}
		$attrs[] = 'dir="ltr"';
		// maxlength = tokens of the mask. Not for phone presets: a pasted +98 912 ... is longer than the
		// national mask and the browser would cut it before the mask turns it into 0912 ...
		if ($pattern !== '') {
			$attrs[] = sprintf('maxlength="%d"', count(\Emsfb\Masks::compile_mask($pattern)));
		}
		$out['masked'] = true;
		$out['attrs'] = implode(' ', $attrs);
		return $out;
	}

	private function generateAutocomplete_efb($elementId) {
		static $autocompleteOptions = [
			'email' => 'email',
			'tel' => 'tel',
			'url' => 'url',
			'password' => 'current-password',
			'firstName' => 'given-name',
			'lastName' => 'family-name',
			'postalcode' => 'postal-code',
			'address_line' => 'street-address'
		];

		return $autocompleteOptions[$elementId] ?? 'off';
	}

	private function generateLengthAttributes_efb($elementId, $vj) {
		$maxlen = '';
		$minlen = '';
		$today = date("Y-m-d");

		if ($elementId != 'date') {
			$maxlen = isset($vj->mlen) && $vj->mlen > 0 ? sprintf('maxlength="%d"', $vj->mlen) : '';
			$minlen = isset($vj->milen) ? sprintf('minlength="%d"', $vj->milen) : '';
		} else {
			$maxlen = isset($vj->mlen) && $vj->mlen == 1 ? sprintf('max="%s"', $today) : (isset($vj->mlen) ? sprintf('max="%s"',$vj->mlen) : '');
			$minlen = isset($vj->milen) && $vj->milen == 1 ? sprintf('min="%s"', $today) : (isset($vj->milen) ? sprintf('min="%s"', $vj->milen) : '');
		}

		return ['maxlen' => $maxlen, 'minlen' => $minlen];
	}

	/**
	 * F03: what the field rules add to a text-like input or textarea (an old field without the keys:
	 * nothing, so its markup is byte for byte as before). Returns
	 * ['attrs' => extra input attributes, 'after' => markup before the description, 'describedby' => ids].
	 */
	private function generateRuleExtras_efb($vj, $rndm) {
		$out = array('attrs' => '', 'after' => '', 'describedby' => array());
		if (!class_exists('\\Emsfb\\Field_Rules')) return $out;
		$f = is_object($vj) ? get_object_vars($vj) : (is_array($vj) ? $vj : array());
		$texts = $this->pro_notice_texts_efb;
		$t = function ($k, $en) use ($texts) { return isset($texts[$k]) && is_string($texts[$k]) && $texts[$k] !== '' ? $texts[$k] : $en; };
		// F: read-only (shown, focusable, sent; the server keeps the stored value). Not on a disabled field.
		if (\Emsfb\Field_Rules::is_readonly($f)) $out['attrs'] .= ' readonly data-efb-readonly="1"';
		$c = \Emsfb\Field_Rules::counter($f);
		if ($c !== null) {
			$id = $rndm . '_-limit';
			$hint = '';
			if ($c['max'] > 0) {
				$hint = sprintf('<div id="%s" class="efb efb-vh">%s</div>', esc_attr($id), esc_html(str_replace('%s', number_format_i18n_efb_fr($c['max']), $c['kind'] === 'words' ? $t('tvLimitWordsHint', 'Up to %s words') : $t('tvLimitHint', 'Up to %s characters'))));
				$out['describedby'][] = $id;
			}
			$out['after'] .= sprintf('<div class="efb efb-counter-wrap"><div id="%s_-counter" class="efb efb-counter fs-7" aria-hidden="true" data-counter="%s" data-style="%s" data-max="%d"></div>%s</div>', esc_attr($rndm), esc_attr($c['kind']), esc_attr($c['style']), (int) $c['max'], $hint);
		}
		return $out;
	}

	/**
	 * F03-G: prefix / suffix and where the description goes. Returns the pieces generateTextInput_efb()
	 * and the textarea put around the input: ['before' => after the -f div opens, 'open' => before the
	 * input (in place of the tooltip), 'close' => after the input, 'desc' => the description piece,
	 * 'describedby' => ids]. Every piece starts with a block tag (wpautop); the tooltip <small> stays
	 * the first child of the input group (generateTooltip_efb). Nothing changes for a field without keys.
	 */
	private function generateHelpAffix_efb($vj, $rndm, $desc, $ttip, $with_affix = true) {
		$out = array('before' => '', 'open' => $ttip, 'close' => '', 'desc' => $desc, 'describedby' => array());
		if (!class_exists('\\Emsfb\\Field_Rules')) return $out;
		$f = is_object($vj) ? get_object_vars($vj) : (is_array($vj) ? $vj : array());
		$texts = $this->pro_notice_texts_efb;
		if ($with_affix && \Emsfb\Field_Rules::type_ok($f, 'affix')) {
			$pfx = isset($f['prefix']) ? \Emsfb\Field_Rules::sanitize_key('prefix', $f['prefix']) : '';
			$sfx = isset($f['suffix']) ? \Emsfb\Field_Rules::sanitize_key('suffix', $f['suffix']) : '';
			if ($pfx !== '' || $sfx !== '') {
				// The group follows the input's own direction (an LTR masked input keeps +98 on the left in RTL).
				$masked = !empty($f['mask_preset']);
				$out['open'] = '<div class="efb input-group efb-affix flex-nowrap"' . ($masked ? ' dir="ltr"' : '') . '>' . $ttip
					. ($pfx !== '' ? '<span class="efb input-group-text efb-affix-text" id="' . esc_attr($rndm) . '_-pfx">' . esc_html($pfx) . '</span>' : '');
				$out['close'] = ($sfx !== '' ? '<span class="efb input-group-text efb-affix-text" id="' . esc_attr($rndm) . '_-sfx">' . esc_html($sfx) . '</span>' : '') . '</div>';
				if ($pfx !== '') $out['describedby'][] = $rndm . '_-pfx';
				if ($sfx !== '') $out['describedby'][] = $rndm . '_-sfx';
			}
		}
		$t = function ($k, $en) use ($texts) { return isset($texts[$k]) && is_string($texts[$k]) && $texts[$k] !== '' ? $texts[$k] : $en; };
		$pw_after = '';
		if (\Emsfb\Field_Rules::type_ok($f, 'password')) {
			$id = esc_attr($rndm);
			// H: show-password button in a group with the input (aria-pressed; the label says what a press does).
			if (\Emsfb\Field_Rules::on($f, 'pw_toggle')) {
				$show = $t('tvPwShow', 'Show password');
				$out['open'] = '<div class="efb input-group efb-affix efb-pw-group flex-nowrap">' . $out['open'];
				$out['close'] = '<button type="button" class="efb btn efb-pw-toggle input-group-text" aria-pressed="false" aria-controls="' . $id . '_" aria-label="' . esc_attr($show) . '" data-show="' . esc_attr($show) . '" data-hide="' . esc_attr($t('tvPwHide', 'Hide password')) . '"><svg aria-hidden="true" focusable="false" width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M8 11.5a3.5 3.5 0 1 1 0-7 3.5 3.5 0 0 1 0 7m0-1.5a2 2 0 1 0 0-4 2 2 0 0 0 0 4"/></svg></button></div>' . $out['close'];
			}
			// H: the strength meter (a bar and a word; colour is never the only sign).
			if (\Emsfb\Field_Rules::on($f, 'pw_meter')) {
				$pw_after .= '<div class="efb efb-pw-meter" id="' . $id . '_-meter" data-min="' . (int) \Emsfb\Field_Rules::int($f, 'pw_strength_min') . '"><div class="efb efb-pw-bar" aria-hidden="true"><span class="efb efb-pw-fill"></span></div><div class="efb efb-pw-word fs-7" id="' . $id . '_-strength" aria-live="polite"></div></div>';
				$out['describedby'][] = $rndm . '_-strength';
			}
			// H: the second box (never named, never part of the entry: it travels as password_confirms).
			if (\Emsfb\Field_Rules::on($f, 'pw_confirm')) {
				$lab = isset($f['pw_confirm_label']) && trim((string) $f['pw_confirm_label']) !== '' ? (string) $f['pw_confirm_label'] : $t('tvPwConfirmLbl', 'Confirm password');
				$req = isset($f['required']) && in_array($f['required'], array(1, '1', true), true);
				// The second label follows the first one's alignment, colour and size (it sat on the other side in RTL).
				$lab_cls = implode(' ', array_filter(array(
					isset($f['label_align']) ? (string) $f['label_align'] : '',
					isset($f['label_text_color']) ? (string) $f['label_text_color'] : '',
					isset($f['label_text_size']) && $f['label_text_size'] !== 'default' ? (string) $f['label_text_size'] : 'fs-6',
				)));
				$pw_after .= '<div class="efb efb-pw-confirm-wrap mt-2"><label for="' . $id . '_confirm" class="efb form-label d-block mb-1 ' . esc_attr($lab_cls) . '">' . esc_html($lab) . '</label>'
					. '<input type="password" id="' . $id . '_confirm" class="efb input-efb form-control px-2 mb-0 w-100 efb-pw-confirm ' . esc_attr(isset($f['el_border_color']) ? $f['el_border_color'] : '') . ' ' . esc_attr(isset($f['el_height']) ? $f['el_height'] : '') . '" data-for="' . $id . '" autocomplete="new-password" aria-describedby="' . $id . '_confirm-message"' . ($req ? ' required aria-required="true"' : '') . '>'
					. '<div class="efb efb-pw-confirm-msg fs-7 text-danger" id="' . $id . '_confirm-message" role="alert"></div></div>';
			}
		}
		$place = isset($f['message_place']) && in_array($f['message_place'], array('above', 'focus', 'tooltip'), true) && \Emsfb\Field_Rules::type_ok($f, 'help') && isset($f['message']) && trim((string) $f['message']) !== '' ? $f['message_place'] : 'below';
		if ($place === 'above') {
			// The description first; the tooltip and the input then get a block parent of their own, or
			// wpautop would put the piece after the description's closing tag in a paragraph.
			$out['before'] = $desc;
			$out['desc'] = '';
			if ($out['close'] === '') {
				$out['open'] = '<div class="efb efb-input-wrap">' . $out['open'];
				$out['close'] = '</div>';
			}
		} elseif ($place === 'focus' || $place === 'tooltip') {
			$cls = $place === 'focus' ? 'efb-help-focus' : 'efb-help-pop';
			$out['desc'] = str_replace('<div class="efb efb-field-description">', '<div class="efb efb-field-description ' . $cls . '" id="' . esc_attr($rndm) . '-desw">', $desc);
			if ($place === 'tooltip') {
				// After the input (a block piece there adds no paragraph); CSS puts it next to the label.
				$name = isset($f['name']) ? (string) $f['name'] : '';
				$label = str_replace('%s', $name, isset($texts['tvHelpBtn']) && is_string($texts['tvHelpBtn']) && $texts['tvHelpBtn'] !== '' ? $texts['tvHelpBtn'] : 'Help: %s');
				$out['desc'] = '<div class="efb efb-help-wrap"><button type="button" class="efb efb-help-btn" aria-expanded="false" aria-controls="' . esc_attr($rndm) . '-desw" aria-label="' . esc_attr($label) . '"><span aria-hidden="true">?</span></button></div>' . $out['desc'];
			}
		}
		$out['desc'] .= $pw_after; // H: meter and the second box after the description
		return $out;
	}

	/**
	 * F02: the pieces of a field that asks for a one-time code or a second copy (spec §3.1):
	 * 'open' / 'close' wrap the input with the "Send code" button in one row (below it under 480 px),
	 * 'after' is the second e-mail box and the code block (status line aria-live, errors role=alert,
	 * one code input with autocomplete=one-time-code). The code input is not an answer: no
	 * emsFormBuilder_v, efbField or data-vid, so it never reaches the submitted rows. No token or nonce
	 * is ever printed; verify-efb.js asks the server with the tab's own runtime token.
	 * Empty pieces for a field without the keys (byte-for-byte the old markup).
	 */
	private function generateVerify_efb($vj, $rndm, $form_id) {
		$out = array('open' => '', 'close' => '', 'after' => '', 'describedby' => array());
		if (!class_exists('\\Emsfb\\Verification')) return $out;
		$f = is_object($vj) ? get_object_vars($vj) : (is_array($vj) ? $vj : array());
		$spec = \Emsfb\Verification::field_spec($f);
		if (!$spec) return $out;
		$t = $this->pro_notice_texts_efb;
		$tx = function ($k) use ($t) { return isset($t[$k]) && is_string($t[$k]) && $t[$k] !== '' ? $t[$k] : \Emsfb\Verification::t($k); };
		$id = esc_attr($rndm);
		$req = isset($f['required']) && in_array($f['required'], array(1, '1', true), true);
		$lab_cls = implode(' ', array_filter(array(
			isset($f['label_align']) ? (string) $f['label_align'] : '',
			isset($f['label_text_color']) ? (string) $f['label_text_color'] : '',
			isset($f['label_text_size']) && $f['label_text_size'] !== 'default' ? (string) $f['label_text_size'] : 'fs-6',
		)));
		$after = '';
		if ($spec['retype']) {
			$lab = isset($f['email_retype_label']) && trim((string) $f['email_retype_label']) !== '' ? (string) $f['email_retype_label'] : $tx('vrfEmailRetype');
			$after .= '<div class="efb efb-vrf-confirm-wrap mt-2"><label for="' . $id . '_confirm" class="efb form-label d-block mb-1 ' . esc_attr($lab_cls) . '">' . esc_html($lab) . '</label>'
				. '<input type="email" id="' . $id . '_confirm" class="efb input-efb form-control px-2 mb-0 w-100 efb-email-confirm ' . esc_attr(isset($f['el_border_color']) ? $f['el_border_color'] : '') . ' ' . esc_attr(isset($f['el_height']) ? $f['el_height'] : '') . '" data-for="' . $id . '" autocomplete="email" aria-describedby="' . $id . '_confirm-message"' . ($req ? ' required aria-required="true"' : '') . '>'
				. '<div class="efb efb-vrf-confirm-msg fs-7 text-danger" id="' . $id . '_confirm-message" role="alert"></div></div>';
		}
		if ($spec['otp']) {
			$s = \Emsfb\Verification::settings();
			$len = (int) $s[$spec['channel']]['code_length'];
			$btn = isset($f['otp_btn_label']) && trim((string) $f['otp_btn_label']) !== '' ? (string) $f['otp_btn_label'] : $tx('vrfSend');
			$countries = $spec['channel'] === 'sms' ? \Emsfb\Verification::field_countries($f, $s) : array();
			$out['open'] = '<div class="efb efb-vrf-row" id="' . $id . '_vrf-row">';
			$out['close'] = '<button type="button" class="efb btn efb-vrf-send" id="' . $id . '_vrf-send" data-vid="' . $id . '" aria-describedby="' . $id . '_vrf-status" disabled>' . esc_html($btn) . '</button></div>';
			$out['describedby'][] = $rndm . '_vrf-status';
			$after .= '<div class="efb efb-vrf" id="' . $id . '_vrf" data-vid="' . $id . '" data-formid="' . esc_attr($form_id) . '" data-channel="' . esc_attr($spec['channel']) . '" data-len="' . $len . '" data-when="' . esc_attr($spec['when']) . '" data-retype="' . ($spec['retype'] ? '1' : '0') . '" data-countries="' . esc_attr(implode(',', $countries)) . '">'
				. '<div class="efb efb-vrf-status fs-7" id="' . $id . '_vrf-status" role="status" aria-live="polite"></div>'
				. '<div class="efb efb-vrf-error fs-7 text-danger" id="' . $id . '_vrf-error" role="alert"></div>'
				. '<div class="efb efb-vrf-box" id="' . $id . '_vrf-box" hidden>'
				. '<label class="efb form-label fs-7 mb-1 d-block ' . esc_attr(isset($f['label_align']) ? (string) $f['label_align'] : '') . '" for="' . $id . '_vrf-code">' . esc_html($tx('vrfCodeLabel')) . '</label>'
				. '<div class="efb efb-vrf-coderow"><input type="text" class="efb form-control efb-vrf-code" id="' . $id . '_vrf-code" inputmode="numeric" autocomplete="one-time-code" maxlength="' . $len . '" pattern="[0-9]*" dir="ltr" spellcheck="false" placeholder="' . esc_attr($tx('verify')) . '" aria-describedby="' . $id . '_vrf-status ' . $id . '_vrf-error">'
				. '<button type="button" class="efb btn efb-vrf-check" id="' . $id . '_vrf-check">' . esc_html($tx('vrfVerify')) . '</button></div>'
				. '<div class="efb efb-vrf-actions fs-7"><button type="button" class="efb btn btn-link p-0 efb-vrf-resend" id="' . $id . '_vrf-resend" disabled>' . esc_html($tx('vrfResend')) . '</button>'
				. ' <span class="efb efb-vrf-timer" id="' . $id . '_vrf-timer" aria-hidden="true"></span>'
				. ' <button type="button" class="efb btn btn-link p-0 efb-vrf-edit" id="' . $id . '_vrf-edit">' . esc_html($tx('vrfEdit')) . '</button>'
				. ' <button type="button" class="efb btn btn-link p-0 efb-vrf-help" id="' . $id . '_vrf-help" aria-expanded="false" aria-controls="' . $id . '_vrf-tips" hidden>' . esc_html($tx('vrfNoCode')) . '</button></div>'
				. '<div class="efb efb-vrf-tips fs-7 text-muted" id="' . $id . '_vrf-tips" hidden>' . esc_html($tx('vrfNoCodeTips')) . '</div>'
				. '</div></div>';
		}
		$out['after'] = $after;
		if ($after !== '' || $out['open'] !== '') $this->verify_fields_efb = true;
		return $out;
	}

	/** aria-describedby="…" with more ids (the existing attribute, or a new one). */
	private function mergeDescribedby_efb($attr, array $ids) {
		if (!$ids) return $attr;
		$cur = preg_match('/aria-describedby="([^"]*)"/', (string) $attr, $m) ? preg_split('/\s+/', trim($m[1])) : array();
		$all = implode(' ', array_values(array_unique(array_filter(array_merge($cur, array_map('esc_attr', $ids)), 'strlen'))));
		return 'aria-describedby="' . $all . '"';
	}

	private function generateTextInput_efb($type, $classes, $vj, $rndm, $desc, $label, $ttip, $div_f_id, $placeholder, $lenAttributes, $aire_describedby, $disabled, $autocomplete, $form_id, $telPattern = '') {
		// F03-H: a password that is being chosen (confirm, meter or minimum strength) is a new password.
		if ($type === 'password' && class_exists('\\Emsfb\\Field_Rules')) {
			$fv_efb = get_object_vars($vj);
			if (\Emsfb\Field_Rules::on($fv_efb, 'pw_confirm') || \Emsfb\Field_Rules::on($fv_efb, 'pw_meter') || \Emsfb\Field_Rules::int($fv_efb, 'pw_strength_min') > 0) $autocomplete = 'new-password';
		}
		// F03: counter and limit hint, read-only, prefix / suffix, description place (nothing for a field without the keys).
		$fr_efb = $this->generateRuleExtras_efb($vj, $rndm);
		if ($fr_efb['after'] !== '') $desc = $fr_efb['after'] . $desc;
		$ha_efb = $this->generateHelpAffix_efb($vj, $rndm, $desc, $ttip);
		$div_f_id .= $ha_efb['before'];
		$ttip = $ha_efb['open'];
		$desc = $ha_efb['close'] . $ha_efb['desc'];
		// F02: the e-mail field's "Send code" row, its second box and the code block (nothing for other fields).
		$vr_efb = $type === 'email' ? $this->generateVerify_efb($vj, $rndm, $form_id) : array('open' => '', 'close' => '', 'after' => '', 'describedby' => array());
		if ($vr_efb['open'] !== '' || $vr_efb['after'] !== '') {
			$ttip = $ttip . $vr_efb['open'];
			$desc = $vr_efb['close'] . $vr_efb['after'] . $desc;
		}
		$aire_describedby = $this->mergeDescribedby_efb($aire_describedby, array_merge($ha_efb['describedby'], $fr_efb['describedby'], $vr_efb['describedby']));
		if ($fr_efb['attrs'] !== '') $telPattern = trim($telPattern . ' ' . trim($fr_efb['attrs']));

		$corener = isset($vj->corner) ? $vj->corner : 'rounded-3';
		$required = ($vj->required == 1 || $vj->required == true) ? 'required' : '';
		$value = !empty($vj->value) ? 'value="' . esc_attr($vj->value) . '"' : '';
		// F08: a default with visitor tags ({get:…}, {user:…}, {date:…}) is resolved in the browser (data-st-value).
		if (!empty($vj->value) && is_string($vj->value) && strpos($vj->value, '{') !== false) {
			list($dv_efb, $dv_attr_efb) = $this->st_attr_efb($vj->value, 'default', 'data-st-value');
			$value = ($dv_efb !== '' ? 'value="' . esc_attr($dv_efb) . '"' : '') . $dv_attr_efb;
		}
		$aria_required = ($vj->required == 1) ? 'true' : 'false';
		$readonly = ($disabled == "disabled") ? 'readonly' : '';
		$el_height = isset($vj->el_height) ? $vj->el_height : '';
		$el_text_color = isset($vj->el_text_color) ? $vj->el_text_color : '';
		$additional_classes = isset($vj->classes) ? str_replace(',', ' ', $vj->classes) : '';

		return sprintf(
			'%s %s %s <input type="%s" class="efb input-efb px-2 mb-0 emsFormBuilder_v w-100 %s %s %s %s %s efbField efb1 %s" data-id="%s-el" data-vid="%s" data-formid="%s" data-css="%s" id="%s_" %s %s aria-required="%s" aria-label="%s" %s autocomplete="%s" %s %s %s %s> %s',  $label,  $div_f_id,  $ttip,  $type,  $classes,  $el_height,  $corener,  $el_text_color,  $required,  $additional_classes,  $rndm,  $rndm,  $form_id,  $rndm,  $rndm,  $placeholder,  $value,  $aria_required,  $this->st_aria_efb($vj->name),  $aire_describedby,  $autocomplete,  $lenAttributes['maxlen'],  $lenAttributes['minlen'],  $readonly,  $telPattern,  $desc
		);
	}

	private function generateSwitchInput_efb($vj, $rndm, $desc, $label, $ttip, $div_f_id, $aire_describedby, $disabled) {
		return '
		' . $label . '
		' . $ttip . '
		<div class="efb ' . $pos[3] . ' ' . $this->mobile_pos[3] . ' px-0 mx-0 ttEfb show" id ="' . $rndm . '-f" ' . $aire_describedby . '>
		<label class="efb fs-6" id="' . $rndm . '_off">' . $vj->off . '</label>
		<button type="button" data-state="off" class="efb btn ' . $vj->el_height . ' btn-toggle efb1 ' . str_replace(',', ' ', $vj->classes) . '" data-css="' . $rndm . '" data-toggle="button" aria-pressed="false" data-vid="' . $rndm .'" data-formid="' . $form_id . '" onclick="fun_switch_efb(this)" data-id="' . $rndm . '-el" id="' . $rndm . '_" ' . $disabled . '>
			<div class="efb handle"></div>
		</button>
		<label class="efb fs-6" id="' . $rndm . '_on">' . $vj->on . '</label>
		<div class="efb mb-3">' . $desc . '</div>';
	}

	private function get_position_col_el($val, $state) {
		$el_parent = "null";
		$el_label =  "null";
		$el_input = "null";
		if(isset($val->id_)){
			$el_parent = $val->id_;
			$el_label = $val->id_ . "_labG";
			$el_input = $val->id_ . "-f";
		}
		$parent_col = '';
		$label_col = 'col-md-12';
		$input_col = 'col-md-12';
		$parent_row = '';
		$size = isset($val->size) ? (int) $val->size : 100;
		switch ($size) {
			case 100:
				$parent_col = 'col-md-12';
				$label_col = 'col-md-3';
				$input_col = 'col-md-9';
				break;
			case 92:
				$parent_col = 'col-md-11';
				$label_col = 'col-md-2';
				$input_col = 'col-md-10';
				break;
			case 80:
			case 83:
				$parent_col = 'col-md-10';
				$label_col = 'col-md-2';
				$input_col = 'col-md-10';
				break;
			case 75:
				$parent_col = 'col-md-9';
				$label_col = 'col-md-2';
				$input_col = 'col-md-10';
				break;
			case 67:
				$parent_col = 'col-md-8';
				$label_col = 'col-md-3';
				$input_col = 'col-md-9';
				break;
			case 58:
				$parent_col = 'col-md-7';
				$label_col = 'col-md-3';
				$input_col = 'col-md-9';
				break;
			case 50:
				$parent_col = 'col-md-6';
				$label_col = 'col-md-3';
				$input_col = 'col-md-9';
				break;
			case 42:
				$parent_col = 'col-md-5';
				$label_col = 'col-md-3';
				$input_col = 'col-md-9';
				break;
			case 33:
				$parent_col = 'col-md-4';
				$label_col = 'col-md-4';
				$input_col = 'col-md-8';
				break;
			case 25:
				$parent_col = 'col-md-3';
				$label_col = 'col-md-4';
				$input_col = 'col-md-8';
				break;
			case 17:
				$parent_col = 'col-md-2';
				$label_col = 'col-md-4';
				$input_col = 'col-md-8';
				break;
			case 8:
				$parent_col = 'col-md-1';
				$label_col = 'col-md-5';
				$input_col = 'col-md-5';
				break;
		}
		if (isset($val->label_position) && $val->label_position == "up") {
			$label_col = 'col-md-12';
			$input_col = 'col-md-12';
			if ($state === true) {

			}
		} else {
			$parent_row = 'row';
			if ($state === true) {

			}
		}
		if ($state === true) {
			$el_parent = $this->colMdChangerEfb($el_parent, $parent_col);
			if ($el_input != "null") $el_input = $this->colMdChangerEfb($el_input, $input_col);
			if ($el_label != "null") $el_label = $this->colMdChangerEfb($el_label, $label_col);
		}
		return array($parent_row, $parent_col, $label_col, $input_col);
	}

        public function generate_mobile_css_efb() {
                if (!$this->pro_efb) return '';
                $css = '';
                foreach ($this->valj_efb as $i => $vj) {
                        if ($i === 0) continue;
                        if (!isset($vj->id_)) continue;
                        $id = $vj->id_;

                        if ((isset($this->valj_efb[0]->global_mobile_hflabel) && (int) $this->valj_efb[0]->global_mobile_hflabel === 1) || (isset($vj->mobile_hflabel) && (int) $vj->mobile_hflabel === 1)) {
                                $css .= '#' . $id . '_labG { display: none !important; }' . "\n";
                        }
                        if (isset($vj->mobile_hfdescription) && (int) $vj->mobile_hfdescription === 1) {
                                $css .= '#' . $id . '-des { display: none !important; }' . "\n";
                        }

                        if (isset($vj->mobile_label_align) && $vj->mobile_label_align !== '') {
                                $css .= '#' . $id . '_labG { ' . $this->get_align_css($vj->mobile_label_align) . ' }' . "\n";
                        }

                        if (isset($vj->mobile_message_align) && $vj->mobile_message_align !== '') {
                                $css .= '#' . $id . '-des { ' . $this->get_align_css_desc($vj->mobile_message_align) . ' }' . "\n";
                        }

                        if (isset($vj->mobile_label_text_size) && $vj->mobile_label_text_size !== '' && $vj->mobile_label_text_size !== 'fs-6') {
                                $fontSize = $this->get_font_size_css($vj->mobile_label_text_size);
                                if ($fontSize) {
                                        $css .= '#' . $id . '_lab { font-size: ' . $fontSize . ' !important; }' . "\n";
                                }
                        }

                        // Note: mobile_size and mobile_label_position are handled with
                        // col-* (xs) classes by get_position_col_mobile_el(), not here.

                        if (isset($vj->mobile_op_style) && in_array((string) $vj->mobile_op_style, array('2', '3'), true)) {
                                $op_width = (string) $vj->mobile_op_style === '2' ? '50%' : '33.3333%';
                                $css .= '#' . $id . '_options { display: flex !important; flex-wrap: wrap !important; }' . "\n";
                                $css .= '#' . $id . '_options > .form-check { flex: 0 0 ' . $op_width . ' !important; width: ' . $op_width . ' !important; max-width: ' . $op_width . ' !important; }' . "\n";
                        }
                }

                if (empty($css)) return '';
                return '<style>@media (max-width: 767.98px) {' . "\n" . $css . '}</style>';
        }

        private function get_align_css($alignClass) {
                switch ($alignClass) {
                        case 'txt-left': return 'text-align: left !important;';
                        case 'txt-center': return 'text-align: center !important;';
                        case 'txt-right': return 'text-align: right !important;';
                        default: return '';
                }
        }

        private function get_align_css_desc($alignClass) {
                switch ($alignClass) {
                        case 'justify-content-start': return 'justify-content: flex-start !important;';
                        case 'justify-content-center': return 'justify-content: center !important;';
                        case 'justify-content-end': return 'justify-content: flex-end !important;';
                        default: return '';
                }
        }

        private function get_font_size_css($sizeClass) {
                switch ($sizeClass) {
                        case 'fs-7': return '0.875rem';
                        case 'fs-6': return '1rem';
                        case 'fs-5': return '1.25rem';
                        case 'fs-4': return '1.5rem';
                        case 'fs-3': return '1.75rem';
                        default: return null;
                }
        }

	private function get_position_col_mobile_el($val) {
		// Mobile tier uses plain col-* (xs) classes: they apply from 0px up and are
		// overridden by the desktop col-md-* tier at >=768px. col-sm-* must not be
		// used here, it does not exist below 576px (real phones).
		$parent_col = 'col-12';
		$label_col = 'col-12';
		$input_col = 'col-12';
		$parent_row = '';
		$mobile_size = $this->pro_efb && isset($val->mobile_size) ? (int) $val->mobile_size : 100;
		switch ($mobile_size) {
			case 100: $parent_col = 'col-12'; break;
			case 92:  $parent_col = 'col-11'; break;
			case 83:
			case 80:  $parent_col = 'col-10'; break;
			case 75:  $parent_col = 'col-9';  break;
			case 67:  $parent_col = 'col-8';  break;
			case 58:  $parent_col = 'col-7';  break;
			case 50:  $parent_col = 'col-6';  break;
			case 42:  $parent_col = 'col-5';  break;
			case 33:  $parent_col = 'col-4';  break;
			case 25:  $parent_col = 'col-3';  break;
			case 17:  $parent_col = 'col-2';  break;
			case 8:   $parent_col = 'col-1';  break;
		}
		// Mobile label position defaults to 'up' (stacked) regardless of the desktop
		// label_position; that is the plugin's historical behaviour below 768px.
		if ($this->pro_efb && isset($val->mobile_label_position) && $val->mobile_label_position == 'beside') {
			$parent_row = 'row';
			$label_col = 'col-4';
			$input_col = 'col-8';
		}
		return array($parent_row, $parent_col, $label_col, $input_col);
	}

	private function colMdChangerEfb($classes, $value) {

		$newClasses = preg_replace('/\bcol-md+-\d+/', " $value ", $classes);

		if ($newClasses === null) {
			return $classes . ' ' . $value;
		}
		return $newClasses;
	}

	/**
	 * Notice shown in place of a field that needs the Free Plus / Pro version.
	 *
	 * A privileged viewer (someone who can edit content — the form builder /
	 * editor / admin) gets the actionable $admin_text ("activate Free Plus or
	 * Pro"), because they can actually do something about it. A normal visitor
	 * gets the neutral $text notice. When $admin_text is empty every viewer gets
	 * $text (backward compatible with the existing callers).
	 */
	private function public_pro_message_efb($text, $admin_text = '', $texts = null){
		$texts = is_array($texts) ? $texts : $this->pro_notice_texts_efb;
		$type = preg_replace('/[^A-Za-z0-9_]/', '', (string) $this->pro_notice_type_efb);
		/* F18 §7: whoever can change the plan is told what visitors see and gets a link that opens the
		   upgrade dialog for this field in the form builder (never activates anything by itself). */
		if ($type !== '' && !empty($texts['upgAdminFieldNote']) && function_exists('efb_can_change_plan_efb') && efb_can_change_plan_efb()) {
			$url = admin_url('admin.php?page=Emsfb_create&efb_upgrade=field:' . $type);
			$link = !empty($texts['upgAdminFieldLink']) ? $texts['upgAdminFieldLink'] : '';
			$text = esc_html($texts['upgAdminFieldNote'])
				. ($link !== '' ? ' <a class="efb text-white text-decoration-underline" href="' . esc_url($url) . '">' . esc_html($link) . '</a>' : '');
		} elseif ($admin_text !== '' && function_exists('current_user_can') && current_user_can('edit_posts')) {
			$text = $admin_text;
		}
		$r = sprintf(
			'<div class="efb text-white fs-6 bg-danger px-1 rounded px-2 py-1">%s</div>',
			$text
		);
		$this->last_pro_notice_efb = $r;

		return $r;
	}

	public function generate_country_list_efb($rndm, $vj, $pos, $formId, $texts ,$desc,$label,$ttip,$aire_describedby) {
		$optn = '<!--countries-->';

		$options = '';
        $optns_obj = array_filter($this->valj_efb, function($obj) use ($rndm) {
            return isset($obj->parent) && $obj->parent === $rndm;
        });
		$is_selected =false;

        foreach ($optns_obj as $i) {

            $selected = ($vj->value == $i->id_ || (property_exists($i, 'id_old') && $vj->value == $i->id_old)) ? 'selected' : '';
			if($selected == 'selected') $is_selected = true;

			$options .= sprintf(
					'<option value="%s" id="%s" data-iso="%s" data-id="%s" data-op="%s" class="efb %s emsFormBuilder_v efb" data-formid="%s"  %s>%s</option>',
					$i->value,
					$i->id_,
					$i->id_op,
					$i->id_,
					$i->id_,
					$vj->el_text_color,
					$formId,
					$selected,
					$i->value
				);

        }

		$required = ($vj->required == 1 || $vj->required == true) ? 'required' : '';

        $readonly = '';
        $ariaRequired = $vj->required == 1 ? 'true' : 'false';
        $ariaDescribedBy = !empty($vj->message) ? 'aria-describedby="' . $vj->id_ . '-des"' : '';
        $disabled = property_exists($vj, 'disabled') && $vj->disabled == true ? 'disabled' : '';
        $corner = isset($vj->corner) ? $vj->corner : 'rounded-3';
        $el_height = isset($vj->el_height) ? $vj->el_height : '';
        $el_border_color = isset($vj->el_border_color) ? $vj->el_border_color : '';
		$type = $vj->type;
       $ui = sprintf(
			'%s
			<div data-tag="%s" class="efb %s ' . $this->mobile_pos[3] . ' px-0 mx-0 ttEfb show efb1 %s" data-css="%s" id="%s-f" data-id="%s-el" data-formid="%s">
				%s
				<select class="efb form-select efb emsFormBuilder_v w-100 %s %s %s %s w-100" data-vid="%s" id="%s_options" aria-required="%s" aria-label="%s" %s data-type="%s" data-formid="%s" %s %s>
					<option disabled %s id="efbNotingSelected">%s</option>
					%s
				</select>
				%s
			</div>',
			$label,
			$type,
			$pos[3],
			str_replace(',', ' ', $vj->classes),
			$rndm,
			$rndm, $rndm, $formId,
			$ttip,
			$required,
			$el_height,
			$corner,
			$el_border_color,
			$rndm,
			$rndm,
			$ariaRequired,
			$this->st_aria_efb($vj->name),
			$ariaDescribedBy,
			$type,
			$formId,
			$readonly,
			$disabled ? 'disabled' : '',
			$is_selected ? '': 'selected',
			$texts['nothingSelected'],
			$options,
			$desc
		);

		return $ui;

	}

	public function generate_state_province_efb($rndm, $vj, $pos, $formId, $texts, $desc, $label, $ttip, $aire_describedby) {
		$options = '';
        $optns_obj = array_filter($this->valj_efb, function($obj) use ($rndm) {
            return isset($obj->parent) && $obj->parent === $rndm;
        });
		$iso_country = $vj->country;
		$is_selected = false;
        foreach ($optns_obj as $i) {
            $selected = ($vj->value == $i->id_ || (property_exists($i, 'id_old') && $vj->value == $i->id_old)) ? 'selected' : '';
			if($selected == 'selected') $is_selected = true;
			$options .= sprintf(
					'<option value="%s" id="%s" data-iso="%s" data-isoc="%s" data-id="%s" data-op="%s" class="efb %s emsFormBuilder_v efb"  data-formid="%s" %s>%s</option>',
					$i->value,
					$i->id_,
					$i->s2,
					$iso_country,
					$i->id_,
					$i->id_,
					$vj->el_text_color,
					$formId,
					$selected,
					$i->value
				);

        }

		$required = ($vj->required == 1 || $vj->required == true) ? 'required' : '';
		$readonly = false ? 'readonly' : '';
		$ariaRequired = $vj->required == 1 ? 'true' : 'false';
		$ariaDescribedBy = !empty($vj->message) ? 'aria-describedby="' . $vj->id_ . '-des"' : '';
		$disabled = property_exists($vj, 'disabled') && $vj->disabled == true ? 'disabled' : '';
		$corner = isset($vj->corner) ? $vj->corner : 'rounded-3';
		$el_height = isset($vj->el_height) ? $vj->el_height : '';
		$el_border_color = isset($vj->el_border_color) ? $vj->el_border_color : '';

		$ui = sprintf(
			'%s
			<div class="efb %s ' . $this->mobile_pos[3] . ' px-0 mx-0 ttEfb show efb1 %s" data-css="%s" id="%s-f" data-id="%s-el" data-formid="%s">
				%s
				<select data-type="stateProvince" class="efb form-select emsFormBuilder_v w-100 %s %s %s %s" data-vid="%s" id="%s_options" data-formid="%s" aria-required="%s" aria-label="%s" %s %s %s>
					<option disabled %s id="efbNotingSelected">%s</option>
					%s
				</select>
				%s
			</div>',
			$label,
			$pos[3],
			str_replace(',', ' ', $vj->classes),
			$rndm,
			$rndm, $rndm, $formId,
			$ttip,
			$required,
			$el_height,
			$corner,
			$el_border_color,
			$rndm,
			$rndm,
			$formId,
			$ariaRequired,
			$this->st_aria_efb($vj->name),
			$ariaDescribedBy,
			$readonly,
			$disabled,
			$is_selected ? '' : 'selected',
			$texts['nothingSelected'],
			$options,
			$desc
		);

		return $ui;
	}

	public function generate_city_list_efb($rndm, $vj, $pos, $formId, $texts, $desc, $label, $ttip, $aire_describedby) {
		$options = '';
        $optns_obj = array_filter($this->valj_efb, function($obj) use ($rndm) {
            return isset($obj->parent) && $obj->parent === $rndm;
        });
		$is_selected = false;
        foreach ($optns_obj as $i) {
            $selected = ($vj->value == $i->id_ || (property_exists($i, 'id_op') && $vj->value == $i->id_op)) ? 'selected' : '';
			if($selected == 'selected') $is_selected = true;
			$options .= sprintf(
					'<option value="%s" id="%s" data-iso="%s" data-isoc="%s" data-statepov="%s" data-id="%s" data-op="%s" class="efb %s emsFormBuilder_v efb" data-formid="%s" %s>%s</option>',
					$i->value,
					$i->id_,
					$i->id_,
					$vj->country,
					$vj->statePov,
					$i->id_,
					$i->id_,
					$vj->el_text_color,
					$formId,
					$selected,
					$i->value
				);

        }

		$required = ($vj->required == 1 || $vj->required == true) ? 'required' : '';
		$readonly = false ? 'readonly' : '';
		$ariaRequired = $vj->required == 1 ? 'true' : 'false';
		$ariaDescribedBy = !empty($vj->message) ? 'aria-describedby="' . $vj->id_ . '-des"' : '';
		$disabled = property_exists($vj, 'disabled') && $vj->disabled == true ? 'disabled' : '';
		$corner = isset($vj->corner) ? $vj->corner : 'rounded-3';
		$el_height = isset($vj->el_height) ? $vj->el_height : '';
		$el_border_color = isset($vj->el_border_color) ? $vj->el_border_color : '';

		$ui = sprintf(
			'%s
			<div class="efb %s ' . $this->mobile_pos[3] . ' px-0 mx-0 ttEfb show efb1 %s" data-css="%s" id="%s-f" data-id="%s-el" data-formid="%s">
				%s
				<select data-type="citylist" class="efb form-select emsFormBuilder_v w-100 %s %s %s %s" data-vid="%s" id="%s_options" data-formid="%s" aria-required="%s" aria-label="%s" %s %s %s>
					<option disabled %s id="efbNotingSelected">%s</option>
					%s
				</select>
				%s
			</div>',
			$label,
			$pos[3],
			str_replace(',', ' ', $vj->classes),
			$rndm,
			$rndm, $rndm, $formId,
			$ttip,
			$required,
			$el_height,
			$corner,
			$el_border_color,
			$rndm,
			$rndm,
			$formId,
			$ariaRequired,
			$this->st_aria_efb($vj->name),
			$ariaDescribedBy,
			$readonly,
			$disabled,
			$is_selected ? '' : 'selected',
			$texts['nothingSelected'],
			$options,
			$desc
		);

		return $ui;
	}

	public function generate_multiselect_efb($elementId, $rndm, $vj, $pos, $formId, $texts, $desc, $label, $ttip, $aire_describedby) {
		/* payMultiselect has to render exactly what the admin preview renders:
		 * the option handler keys the price off the `payefb` class, and the
		 * strlen($pay) > 2 tests below are what draw the price column at all.
		 * Both branches returned '', so on a live form a paid multiselect
		 * showed no prices and contributed nothing to the total. */
		$pay = $elementId == "multiselect" ? '' : 'payefb';
		$currency = property_exists($vj, 'currency') ? $vj->currency : 'USD';
		$va = '';
		$sl = '';
		$optn = '<!--opt-->';

		$optns_obj = array_filter($this->valj_efb, function($obj) use ($rndm) {
			return isset($obj->parent) && $obj->parent === $rndm;
		});

		$s = isset($vj->value) && gettype($vj->value)!='string' && count($vj->value) > 0 ? true : false;
		// F05: kept places and used-up choices of a multiselect (the list itself is shuffled in choices-efb.js).
		$ch_parts_efb = class_exists('\\Emsfb\\Choices') && class_exists('\\Emsfb\\Stock')
			? \Emsfb\Choices::render_parts(json_decode(wp_json_encode($vj), true), json_decode(wp_json_encode(array_values($optns_obj)), true), $formId, $this->stock_left)
			: array('opt' => array());

		foreach ($optns_obj as $i) {
			$ch_opt_efb = isset($ch_parts_efb['opt'][(string) $i->id_]) ? $ch_parts_efb['opt'][(string) $i->id_] : array('attr' => '', 'disabled' => false, 'hidden' => false, 'suffix' => '');
			$c = "efb bi-square efb";
			if ($s && in_array($i->id_, $vj->value)) {
				$c = "bi-check-square text-info efb";
				$va .= $i->value . ',';
				$sl .= $i->id_ . ' @efb!';
			}

			$optn .= sprintf(
				'<tr class="efb efblist %s %s%s" data-id="%s" data-name="%s" data-row="%s" data-formid="%s" data-state="0" data-visible="1"%s>
					<th scope="row" class="%s" data-formid="%s"></th>
					<td class="efb ms col-12" data-formid="%s">%s</td>
					%s
				</tr>',
				$vj->el_text_color,
				$pay,
				$ch_opt_efb['disabled'] ? ' efb-soldout' . ($ch_opt_efb['hidden'] ? ' d-none' : '') : '',
				$rndm,
				esc_attr($i->value),
				esc_attr($i->id_),
				$formId,
				$ch_opt_efb['attr'] . ($ch_opt_efb['disabled'] ? ' aria-disabled="true"' : ''),
				$c,
				$formId,
				$formId,
				esc_html($i->value) . $ch_opt_efb['suffix'],
				strlen($pay) > 2 ? sprintf(
					'<td class="efb ms fw-bold text-center"><span id="%s-price" class="efb efb-crrncy">%s</span></td>',
					$i->id_,
					number_format($i->price, 2) . ' ' . $currency
				) : ''
			);
		}

		$required = ($vj->required == 1 || $vj->required == true) ? 'required' : '';
		$readonly =  '';
		$ariaRequired = $vj->required == 1 ? 'true' : 'false';
		$ariaDescribedBy = !empty($vj->message) ? 'aria-describedby="' . $vj->id_ . '-des"' : '';
		$disabled = property_exists($vj, 'disabled') && $vj->disabled == true ? 'disabled' : '';
		$corner = isset($vj->corner) ? $vj->corner : 'rounded-3';
		$el_height = isset($vj->el_height) ? $vj->el_height : '';
		$el_border_color = isset($vj->el_border_color) ? $vj->el_border_color : '';

		$ui = sprintf(
			'%s
			<!--multiselect-->
			<div class="efb %s ' . $this->mobile_pos[3] . ' listSelect px-0 mx-0 ttEfb show efb1 %s" data-css="%s" id="%s-f" data-id="%s-el" data-formid="%s">
				%s
				<div class="efb efblist mx-0 inplist %s %s %s %s %s %s bi-chevron-down" data-id="menu-%s" data-no="%s" data-min="%s" data-parent="1" data-icon="1" data-select="%s" data-vid="%s" id="%s_options">
					%s
				</div>
				<div class="efb efblist mx-0 listContent shadow d-none border rounded-bottom bg-light" data-id="menu-%s" data-list="menu-%s" data-formid="%s">
					<table class="efb table menu-%s">
						<thead class="efb efblist">
							<tr>
								<th class="efb searchSection efblist p-2 bg-light" colspan="2">
									<input type="text" class="efb efblist search searchBox my-1 col-12 rounded" data-id="menu-%s" data-tag="search" placeholder="🔍 %s" onkeyup="FunSearchTableEfb(\'menu-%s\')">
								</th>
							</tr>
						</thead>
						<tbody class="efb fs-7">
							%s
						</tbody>
					</table>
				</div>
				%s
			',
			$label,
			$pos[3],
			str_replace(',', ' ', $vj->classes),
			$rndm,
			$rndm,
			$rndm,
			$formId,
			$ttip,
			$pay,
			$disabled,
			$required,
			$el_height,
			$corner,
			$el_border_color,
			$rndm,
			$vj->maxSelect,
			$vj->minSelect,
			$sl,
			$rndm,
			$rndm,
			empty($va) ? $texts['selectOption'] : $va,
			$rndm,
			$rndm,
			$formId,
			$rndm,
			$rndm,
			$texts['search'],
			$rndm,
			$optn,
			$desc
		);

		return $ui;
	}

	public function generate_pdate_input_efb ($rndm, $vj, $pos, $formId, $texts, $desc, $label, $ttip, $aire_describedby, $previewSate) {

		$classes = sprintf('form-control %s', $vj->el_border_color ?? '');
		$required = ($vj->required == 1 || $vj->required == true) ? 'required' : '';
		$ariaRequired = $vj->required == 1 ? 'true' : 'false';
		$readonly =  '';
		$value = !empty($vj->value) ? sprintf('value="%s"', esc_attr($vj->value)) : '';
		$el_height = isset($vj->el_height) ? $vj->el_height : '';
		$el_text_color = isset($vj->el_text_color) ? $vj->el_text_color : '';
		$corner = isset($vj->corner) ? $vj->corner : 'rounded-3';
		$extra_classes = str_replace(',', ' ', $vj->classes);

		$ui = sprintf(
			'%s
			<div class="efb %s ' . $this->mobile_pos[3] . ' px-0 mx-0 ttEfb show" id="%s-f">
				%s
				<input type="text" class="efb pdpF2 input-efb px-2 mb-0 emsFormBuilder_v w-100 %s %s %s %s %s efbField efb1 %s"
				data-css="%s" data-id="%s-el" data-vid="%s" id="%s_" %s aria-required="%s" aria-label="%s" %s %s>
				%s
			</div>',
			$label,
			$pos[3],
			$rndm,
			$ttip,
			$classes,
			$el_height,
			$corner,
			$el_text_color,
			$required,
			$extra_classes,
			$rndm,
			$rndm,
			$rndm,
			$value,
			$ariaRequired,
			esc_attr($vj->value),
			$aire_describedby,
			$readonly,
			$desc
		);

		return $ui;
	}

	public function generate_html_code_efb($rndm, $vj, $pos, $formId, $texts, $previewSate) {
		if (strlen($vj->value) < 2) {
			$ui = sprintf(
				'<div class="efb ' . $this->mobile_pos[3] . ' efb" id="%s-f" data-id="%s-el" data-tag="htmlCode">
					<div class="efb boxHtml-efb sign-efb efb" id="%s_html">
						<div class="efb noCode-efb m-5 text-center efb" id="%s_noCode">
							%s
						</div>
					</div>
				</div>',
				$rndm,
				$rndm,
				$rndm,
				$rndm,
				$texts['notFound']
			);
		} else {
			$ui = str_replace(['@!', '@efb@nq#'], ['"', ''], $vj->value);
			$allowed = wp_kses_allowed_html( 'post' );
			$allowed['style']  = [];
			$allowed['iframe'] = [
				'src'             => true,
				'width'           => true,
				'height'          => true,
				'frameborder'     => true,
				'allowfullscreen' => true,
				'title'           => true,
				'loading'         => true,
				'style'           => true,
				'class'           => true,
			];
			$allowed['svg'] = [
				'xmlns'       => true,
				'viewbox'     => true,
				'width'       => true,
				'height'      => true,
				'fill'        => true,
				'class'       => true,
				'style'       => true,
				'aria-hidden' => true,
				'role'        => true,
			];
			$allowed['path']    = [ 'd' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true ];
			$allowed['circle']  = [ 'cx' => true, 'cy' => true, 'r' => true, 'fill' => true, 'stroke' => true ];
			$allowed['rect']    = [ 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'fill' => true, 'rx' => true, 'ry' => true ];
			$allowed['line']    = [ 'x1' => true, 'y1' => true, 'x2' => true, 'y2' => true, 'stroke' => true ];
			$allowed['polygon'] = [ 'points' => true, 'fill' => true, 'stroke' => true ];
			$allowed['g']       = [ 'fill' => true, 'transform' => true, 'class' => true ];
			$ui = wp_kses( $ui, $allowed ) . "<!--endhtml first -->";
			$ui = sprintf(
				'<div %s>%s</div>',
				$previewSate == false ? 'class="efb bg-light" id="' . $rndm . '_html"' : '',
				$ui
			);
		}

		return $ui;
	}

	public function generate_heading_efb($rndm, $pos, $vj, $formId) {

		$el_text_color = isset($vj->el_text_color) ? $vj->el_text_color : '';
		$el_text_size = isset($vj->el_text_size) ? $vj->el_text_size : '';
		$extra_classes = isset($vj->classes) ? str_replace(',', ' ', $vj->classes) : '';
		$value = isset($vj->value) ? htmlspecialchars($vj->value, ENT_QUOTES, 'UTF-8') : '';
		// F08: a heading may pipe an answer ({field:…}) or show public tags.
		if (isset($vj->value) && is_string($vj->value) && strpos($vj->value, '{') !== false) $value = $this->st_markup_efb($vj->value, 'label');

		$ui = sprintf(
			'<div class="efb px-0 mx-0 %s ' . $this->mobile_pos[3] . '" id="%s-f" data-formid="%s">
				<p id="%s_" class="efb px-0 emsFormBuilder_v %s %s efbField efb1 %s" data-css="%s" data-vid="%s" data-id="%s-el">%s</p>
			</div>',
			$pos[0],
			$rndm,
			$formId,
			$rndm,
			$el_text_color,
			$el_text_size,
			$extra_classes,
			$rndm,
			$rndm,
			$rndm,
			$value
		);

		return $ui;
	}

	public function generate_link_efb($previewState, $pos, $rndm, $vj, $formId) {

		$disabled = $previewState != true ? 'disabled' : '';

		$el_text_color = isset($vj->el_text_color) ? $vj->el_text_color : '';
		$el_text_size = isset($vj->el_text_size) ? $vj->el_text_size : '';
		$classes = isset($vj->classes) ? str_replace(',', ' ', $vj->classes) : '';
		$href = isset($vj->href) ? $vj->href : '#';
		$value = isset($vj->value) ? esc_html($vj->value) : '';

		$ui = sprintf(
			'<div class="efb %s px-0 mx-0 ' . $this->mobile_pos[3] . '" id="%s-f" data-formid="%s">
				<a id="%s_" target="_blank" class="efb px-0 btn underline emsFormBuilder_v %s %s %s efbField efb1 %s" data-css="%s" data-vid="%s" data-id="%s-el" href="%s">%s</a>
			</div>',
			$pos[0],
			$rndm,
			$formId,
			$rndm,
			$disabled,
			$el_text_color,
			$el_text_size,
			$classes,
			$rndm,
			$rndm,
			$rndm,
			htmlspecialchars($href, ENT_QUOTES),
			htmlspecialchars($value, ENT_QUOTES)
		);

		return $ui;
	}

    public function generate_yes_no_efb($previewState, $pos, $rndm, $vj, $formId) {

		$corner = isset($vj->corner) ? $vj->corner : 'rounded-3';
		$disabled = (isset($vj->disabled) && $vj->disabled == 1) ? 'disabled' : '';
		$required = ($vj->required == 1 || $vj->required == true) ? 'required' : '';
		$ariaDescribedBy = !empty($vj->message) ? 'aria-describedby="' . $vj->id_ . '-des"' : '';
		$buttonColor = isset($vj->button_color) ? $vj->button_color : '';
		$elTextColor = isset($vj->el_text_color) ? $vj->el_text_color : '';
		$elHeight = isset($vj->el_height) ? $vj->el_height : '';
		$button1Text = isset($vj->button_1_text) ? $vj->button_1_text : 'Yes';
		$button2Text = isset($vj->button_2_text) ? $vj->button_2_text : 'No';
		$classes = isset($vj->classes) ? str_replace(',', ' ', $vj->classes) : '';

		$ui = sprintf(
			'<div class="efb %1$s ' . $this->mobile_pos[3] . ' %2$s efb1 %3$s" data-css="%4$s" id="%4$s-f" data-formid="%5$s" %6$s>
				<div class="efb efb-yesno-group btn-group btn-group-toggle w-100 col-md-12 col-sm-12 border border-0 %7$s" data-toggle="buttons" data-id="%4$s-id" id="%4$s_yn" role="group" data-yesno-kit="default" aria-required="%16$s">
					<label for="%4$s_1" data-lid="%4$s" data-value="%8$s" onclick="yesNoGetEFB(\'%8$s\', \'%4$s\', \'%4$s_b_1\', \'%5$s\')" class="efb btn %9$s %10$s %11$s %12$s yesno-efb left-efb %13$s %14$s" id="%4$s_b_1">
						<input type="radio" name="%4$s" data-type="switch" class="efb opButtonEfb elEdit emsFormBuilder_v efb" data-vid="%4$s" data-id="%4$s-id" id="%4$s_1" value="%8$s" data-formid="%5$s"><span id="%4$s_1_lab">%8$s</span>
					</label>
					<span class="efb border-right border border-light efb"></span>
					<label for="%4$s_2" data-lid="%4$s" data-value="%15$s" onclick="yesNoGetEFB(\'%15$s\', \'%4$s\', \'%4$s_b_2\', \'%5$s\')" class="efb btn %9$s %10$s %11$s %12$s yesno-efb right-efb %13$s %14$s" id="%4$s_b_2">
						<input type="radio" name="%4$s" data-type="switch" class="efb opButtonEfb elEdit emsFormBuilder_v efb" data-vid="%4$s" data-id="%4$s-id" id="%4$s_2" value="%15$s" data-formid="%5$s"><span id="%4$s_2_lab">%15$s</span>
					</label>
				</div>
			',
			$pos[3],
			$disabled,
			$classes,
			$rndm,
			$formId,
			$ariaDescribedBy,
			$required,
			$button1Text,
			$buttonColor,
			$elTextColor,
			$elHeight,
			$corner,
			$disabled,
			$previewState != true ? 'disabled' : '',
			$button2Text,
			$required ? 'true' : 'false'
		);

		return $ui;
	}

	public function pointer5_el_pro_efb($previewSate, $vj, $form_id) {
		$disabled = isset($vj->disabled) && $vj->disabled == 1 ? 'disabled' : '';
		$previewSate =  '';
		$id = $vj->id_;
		$message = $vj->message != '' ? 'aria-describedby="' . $id . '-des"' : '';
		$classes = str_replace(',', ' ', $vj->classes);

		return sprintf(
			'<div class="efb d-flex justify-content-right efb1 %s" data-css="%s" id="%s" data-formid="%s" %s>
				<div class="efb btn btn-secondary emsFormBuilder_v text-white mx-1 %s %s" data-point="1" data-id="%s" data-formid="%s" onclick="fun_point_rating(this)"><i class="efb bi-star-fill"></i></div>
				<div class="efb btn btn-secondary emsFormBuilder_v text-white mx-1 %s %s" data-point="2" data-id="%s" data-formid="%s" onclick="fun_point_rating(this)"><i class="efb bi-star-fill"></i></div>
				<div class="efb btn btn-secondary emsFormBuilder_v text-white mx-1 %s %s" data-point="3" data-id="%s" data-formid="%s" onclick="fun_point_rating(this)"><i class="efb bi-star-fill"></i></div>
				<div class="efb btn btn-secondary emsFormBuilder_v text-white mx-1 %s %s" data-point="4" data-id="%s" data-formid="%s" onclick="fun_point_rating(this)"><i class="efb bi-star-fill"></i></div>
				<div class="efb btn btn-secondary emsFormBuilder_v text-white mx-1 %s %s" data-point="5" data-id="%s" data-formid="%s" onclick="fun_point_rating(this)"><i class="efb bi-star-fill"></i></div>
				<input type="hidden" data-vid="%s" data-type="rating" id="%s-point-rating">
			</div>',
			$classes,
			$id,
			$id,
			$form_id,
			$message,
			$previewSate, $disabled, $id, $form_id,
			$previewSate, $disabled, $id, $form_id,
			$previewSate, $disabled, $id, $form_id,
			$previewSate, $disabled, $id, $form_id,
			$previewSate, $disabled, $id, $form_id,
			$id,
			$id
		);
	}

	public function pointer10_el_pro_efb($previewSate, $vj, $form_id) {
		$disabled = isset($vj->disabled) && $vj->disabled == 1 ? 'disabled' : '';
		$previewSate = $previewSate != true ? 'disabled' : '';
		$id = $vj->id_;
		$message = $vj->message != '' ? 'aria-describedby="' . $id . '-des"' : '';
		$classes = str_replace(',', ' ', $vj->classes);

		return sprintf(
			'<div class="efb NPS flex-row justify-content-right efb1 %s" data-css="%s" id="%s" data-formid="%s" %s>
				<div class="efb emsFormBuilder_v rating btn btn-outline-secondary mx-1 mb-1 %s %s" data-point="0" data-id="%s" data-formid="%s" onclick="fun_nps_rating(this)">0</div>
				<div class="efb emsFormBuilder_v rating btn btn-outline-secondary mx-1 mb-1 %s %s" data-point="1" data-id="%s" data-formid="%s" onclick="fun_nps_rating(this)">1</div>
				<div class="efb emsFormBuilder_v rating btn btn-outline-secondary mx-1 mb-1 %s %s" data-point="2" data-id="%s" data-formid="%s" onclick="fun_nps_rating(this)">2</div>
				<div class="efb emsFormBuilder_v rating btn btn-outline-secondary mx-1 mb-1 %s %s" data-point="3" data-id="%s" data-formid="%s" onclick="fun_nps_rating(this)">3</div>
				<div class="efb emsFormBuilder_v rating btn btn-outline-secondary mx-1 mb-1 %s %s" data-point="4" data-id="%s" data-formid="%s" onclick="fun_nps_rating(this)">4</div>
				<div class="efb emsFormBuilder_v rating btn btn-outline-secondary mx-1 mb-1 %s %s" data-point="5" data-id="%s" data-formid="%s" onclick="fun_nps_rating(this)">5</div>
				<div class="efb emsFormBuilder_v rating btn btn-outline-secondary mx-1 mb-1 %s %s" data-point="6" data-id="%s" data-formid="%s" onclick="fun_nps_rating(this)">6</div>
				<div class="efb emsFormBuilder_v rating btn btn-outline-secondary mx-1 mb-1 %s %s" data-point="7" data-id="%s" data-formid="%s" onclick="fun_nps_rating(this)">7</div>
				<div class="efb emsFormBuilder_v rating btn btn-outline-secondary mx-1 mb-1 %s %s" data-point="8" data-id="%s" data-formid="%s" onclick="fun_nps_rating(this)">8</div>
				<div class="efb emsFormBuilder_v rating btn btn-outline-secondary mx-1 mb-1 %s %s" data-point="9" data-id="%s" data-formid="%s" onclick="fun_nps_rating(this)">9</div>
				<div class="efb emsFormBuilder_v rating btn btn-outline-secondary mx-1 mb-1 %s %s" data-point="10" data-id="%s" data-formid="%s" onclick="fun_nps_rating(this)">10</div>
				<input type="hidden" data-vid="%s" data-type="rating" id="%s-nps-rating">
			</div>',
			$classes,
			$id,
			$id,
			$form_id,
			$message,
			$previewSate, $disabled, $id, $form_id,
			$previewSate, $disabled, $id, $form_id,
			$previewSate, $disabled, $id, $form_id,
			$previewSate, $disabled, $id, $form_id,
			$previewSate, $disabled, $id, $form_id,
			$previewSate, $disabled, $id, $form_id,
			$previewSate, $disabled, $id, $form_id,
			$previewSate, $disabled, $id, $form_id,
			$previewSate, $disabled, $id, $form_id,
			$previewSate, $disabled, $id, $form_id,
			$previewSate, $disabled, $id, $form_id,
			$id,
			$id
		);
	}

	public function smartcr_el_pro_efb($previewSate, $classes, $vj) {
		return '<h3>Smart</h3>';
	}

	public function table_matrix_el_pro_efb($elementId, $vj, $rndm, $position_l_efb, $previewSate, $aire_describedby, $label, $ttip, $desc, $form_id, $pos) {
		$type_field_efb = $elementId;
		$dataTag = $elementId;
		$col = isset($vj->op_style) && intval($vj->op_style) != 1 ? 'col-md-' . (12 / intval($vj->op_style)) : '';
		$pay = in_array($elementId, ["radio", "checkbox", "chlRadio", "chlCheckBox"]) ? "" : "default";
		$disabled = isset($vj->disabled) && $vj->disabled == 1 ? 'disabled' : '';
		$disabled_preview = $previewSate != true ? 'disabled' : '';
		$classes = str_replace(',', ' ', $vj->classes);

		$optns_obj = array_filter($this->valj_efb, function ($obj) use ($rndm) {
			return isset($obj->parent) && $obj->parent === $rndm;
		});

		$optn = '';
		foreach ($optns_obj as $i) {
			$optn .= sprintf(
				'
				<!-- start r_matrix -->
				<div class="efb col-sm-12 %1$s row my-1 t-matrix" data-id="%2$s" data-parent="%3$s" id="%2$s-v">
					<div class="efb mt-2 col-md-8 fs-6 %4$s %5$s %6$s" id="%2$s_lab">%7$s</div>
					<div class="efb col-md-4 d-flex justify-content-%8$s" %9$s id="%2$s">
						<div class="efb btn btn-secondary text-white mx-1 %10$s %11$s" data-point="1" data-id="%2$s" data-formid="%12$s" onclick="fun_point_rating(this)">
							<i class="efb bi-star-fill" data-icon="%2$s"></i>
						</div>
						<div class="efb btn btn-secondary text-white mx-1 %10$s %11$s" data-point="2" data-id="%2$s" data-formid="%12$s" onclick="fun_point_rating(this)">
							<i class="efb bi-star-fill" data-icon="%2$s"></i>
						</div>
						<div class="efb btn btn-secondary text-white mx-1 %10$s %11$s" data-point="3" data-id="%2$s" data-formid="%12$s" onclick="fun_point_rating(this)">
							<i class="efb bi-star-fill" data-icon="%2$s"></i>
						</div>
						<div class="efb btn btn-secondary text-white mx-1 %10$s %11$s" data-point="4" data-id="%2$s" data-formid="%12$s" onclick="fun_point_rating(this)">
							<i class="efb bi-star-fill" data-icon="%2$s"></i>
						</div>
						<div class="efb btn btn-secondary text-white mx-1 %10$s %11$s" data-point="5" data-id="%2$s" data-formid="%12$s" onclick="fun_point_rating(this)">
							<i class="efb bi-star-fill" data-icon="%2$s"></i>
						</div>
						<input type="hidden" class="efb emsFormBuilder_v" data-vid="%2$s" data-parent="%3$s" data-type="rating" id="%2$s-point-rating">
					</div>
					<hr class="efb t-matrix my-1">
				</div>
				<!-- end r_matrix -->',
				$col,
				$i->id_,
				$i->parent,
				$vj->el_text_color,
				$vj->el_height,
				$vj->label_text_size,
				$i->value,
				$position_l_efb,
				$aire_describedby,
				$disabled_preview,
				$disabled,
				$form_id
			);
		}

		$ui = sprintf(
			'
			<!-- table matrix -->
			%1$s
			<div class="efb %2$s ' . $this->mobile_pos[3] . ' px-0 mx-0 ttEfb show" data-id="%3$s-el" id="%3$s-f">
				%4$s
				<div class="efb %5$s %6$s efb1 %7$s" id="%3$s_options">
					%8$s
				</div>
				<div class="efb mb-3">%9$s</div>

			<!-- end table matrix -->',
			$label,
			$pos[3],
			$rndm,
			$ttip,
			$vj->required ? 'required' : '',
			$col ? 'row col-md-12' : '',
			$classes,
			$optn,
			$desc
		);

		return $ui;
	}

	private function create_intlTelInput_efb($rndm,$vj, $previewSate, $corner,$form_id) {
		$disabled = isset($vj->disabled) && $vj->disabled == 1 ? 'disabled' : '';
		$required = $vj->required == 1 || $vj->required == true ? 'required' : '';
		$ariaRequired = $vj->required == 1 ? 'true' : 'false';
		$ariaDescribedBy = !empty($vj->message) ? 'aria-describedby="' . $vj->id_ . '-des"' : '';
		$value = !empty($vj->value) ? 'value="' . esc_attr($vj->value) . '"' : '';
		$readonly = $previewSate != true ? 'readonly' : '';
		$classes =  str_replace(',', ' ', $vj->classes) ?? '';
		$onlyCountries = isset($vj->c_c) && count($vj->c_c) > 0 ? $vj->c_c : '';

		$efbFunction = get_efbFunction();
		$tt =[ 'cpnnc', 'icc', 'cpnts', 'cpntl'];
		$texts = $efbFunction->text_efb($tt);
		if(gettype($onlyCountries) == 'array') {
			$onlyCountries = json_encode($onlyCountries);
		} else {
			$onlyCountries = '[]';
		}

		$js =sprintf(
			'
			let el_emsfb_%1$s = document.getElementById("%1$s_");
			const forceIntlContainerRtlEfb_%1$s = function(inputEl) {
				if (!inputEl) {
					return;
				}
				const rtlScope = inputEl.closest("[dir=\"rtl\"]") || document.documentElement.getAttribute("dir") === "rtl";
				if (!rtlScope) {
					return;
				}
				const itiContainer = inputEl.closest(".iti");
				if (!itiContainer) {
					return;
				}
				const countryContainer = itiContainer.querySelector(".iti__country-container");
				if (countryContainer) {
					countryContainer.style.setProperty("left", "auto", "important");
					countryContainer.style.setProperty("right", "0", "important");
					countryContainer.style.setProperty("inset-inline-start", "auto", "important");
					countryContainer.style.setProperty("inset-inline-end", "0", "important");
				}
				inputEl.setAttribute("dir", "ltr");
				inputEl.style.setProperty("direction", "ltr", "important");
				inputEl.style.setProperty("text-align", "left", "important");
				inputEl.style.setProperty("unicode-bidi", "plaintext", "important");
			};
			/* A6: this inline script runs before the footer scripts. A cache plugin that
			 * delays or combines them (LiteSpeed, Rocket Loader) can load intlTelInput
			 * later than the first try, so wait for it instead of throwing. */
			const efbItiInit_%1$s = function(n) {
				if (typeof window.intlTelInput !== "function") {
					if (n < 900) setTimeout(function() { efbItiInit_%1$s(n + 1); }, n < 300 ? 100 : 1000);
					return;
				}
				const iti = window.intlTelInput(el_emsfb_%1$s, {
					onlyCountries: %11$s,
					nationalMode: true,
					autoPlaceholder: "polite",
					placeholderNumberType: "MOBILE",
					loadUtils: () => import("%10$s"),
				});
				forceIntlContainerRtlEfb_%1$s(el_emsfb_%1$s);
				el_emsfb_%1$s.addEventListener("blur", function() {
					const errorMap = [`%6$s`, `%7$s`,`%8$s`,`%9$s`, `%6$s`];
					const elem = el_emsfb_%1$s;
					const messageElem = document.getElementById("%1$s_-message");
					elem.classList.remove("border-danger");
					elem.classList.remove("border-success");
					messageElem.innerHTML = "";
					messageElem.style.display = "none";
					if (elem.value.trim()) {
						if (iti.isValidNumber()) {
							elem.classList.add("border-success");

							const countryData = iti.getSelectedCountryData();
							const countryCode = countryData.dialCode;
							const iso2 = countryData.iso2;
							const countryName = countryData.name;

							const value = iti.getNumber();

							fun_sendBack_emsFormBuilder({
								id_: "%2$s",
								name: "%3$s",
								id_ob: "%2$s",
								amount: "%4$s",
								type: "%5$s",
								value: value,
								session: sessionPub_emsFormBuilder,
								form_id: "%12$s"
							});
						} else {
							elem.classList.add("border-danger");
							let errorCode = iti.getValidationError();
							errorCode = errorMap[errorCode] ? errorMap[errorCode] : errorMap[0];
							messageElem.style.display = "block";
							messageElem.innerHTML = errorCode;
							let inx = get_row_sendback_by_id_efb("%2$s");
							if (inx !== -1) {
								sendBack_emsFormBuilder_pub.splice(inx, 1);
							}
						}
					}
				});
			};
			setTimeout(function() { efbItiInit_%1$s(0); }, 1000);',
			$rndm,
			$vj->id_,
			$this->st_aria_efb($vj->name),
			$vj->amount,
			$vj->type,
			$texts['cpnnc'],
			$texts['icc'],
			$texts['cpnts'],
			$texts['cpntl'],
			EMSFB_PLUGIN_URL . 'includes/admin/assets/js/utils-efb.js',
			$onlyCountries,
			$form_id

    	);

		$inputPhone = sprintf(
			'<input type="phone" class="efb input-efb intlPhone px-2 mb-0 emsFormBuilder_v form-control %1$s %2$s %3$s %4$s %5$s efbField efb1 %6$s" data-css="%7$s" data-id="%7$s-el" data-formid="%13$s" data-vid="%7$s" id="%7$s_" aria-required="%8$s" aria-label="%9$s" %10$s %11$s %12$s data-utilsjs="%14$s" %15$s>',
			$vj->el_border_color,
			$vj->el_height,
			$corner,
			$vj->el_text_color,
			$required,
			$classes,
			$rndm,
			$ariaRequired,
			$this->st_aria_efb($vj->name),
			$ariaDescribedBy,
			$readonly,
			$disabled,
			$form_id,
			EMSFB_PLUGIN_URL . 'includes/admin/assets/js/utils-efb.js',
			$value,
		);
		if($value != '') {
			$js .= sprintf(
				'
				setTimeout(() => {
					fun_sendBack_emsFormBuilder({
						id_: "%1$s",
						name: "%3$s",
						id_ob: "%2$s",
						amount: "%4$s",
						type: "%5$s",
						value: %6$s,
						session: sessionPub_emsFormBuilder
					});
				}, 4000);',
				$vj->id_,
				$vj->id_,
				$this->st_aria_efb($vj->name),
				$vj->amount,
				$vj->type,
				$value
			);
		}
		/* F02: the hidden "-code" input (class emsFormBuilder_v, read by nothing, bound to the answer
		 * listeners) and the hidden type=submit button are gone; a field with otp_enabled gets the
		 * "Send code" row and the code block instead. */
		$vr_efb = $this->generateVerify_efb($vj, $rndm, $form_id);
		if ($vr_efb['open'] !== '') {
			$inputPhone = $vr_efb['open'] . $inputPhone . $vr_efb['close'] . $vr_efb['after'];
		}

		return [$inputPhone, $js];
	}

	public function esign_el_pro_efb($previewSate,$pos, $rndm, $vj,$message, $formId,$updateUrbrowser) {

		$disabled = isset($vj->disabled) && $vj->disabled == 1 ? 'disabled' : '';
		$required = $vj->required == 1 || $vj->required == true ? 'required' : '';
		$ariaRequired = $vj->required == 1 ? 'true' : 'false';
		$ariaDescribedBy = !empty($vj->message) ? 'aria-describedby="' . $vj->id_ . '-des"' : '';
		$readonly = $previewSate != true ? 'readonly' : '';
		$classes =  str_replace(',', ' ', $vj->classes) ?? '';
		$el_height = isset($vj->el_height) ? $vj->el_height : '';
		$el_text_color = isset($vj->el_text_color) ? $vj->el_text_color : '';
		$corner = isset($vj->corner) ? $vj->corner : 'rounded-3';
		$additional_classes = isset($vj->classes) ? str_replace(',', ' ', $vj->classes) : '';
		$randomId =$vj->id_;

			$ui = sprintf(
				"<div class='efb %s {$this->mobile_pos[3]}' id='%s-f' data-formid='%s'>
					<canvas class='efb sign-efb bg-white %s %s %s %s efb1 %s' data-css='%s' data-code='%s' data-id='%s-el' id='%s_' %s>
						%s
					</canvas>
					%s
					<div class='efb mx-1' data-formid='%s'>%s</div>
					<div class='efb mb-3' data-formid='%s'>
						<button type='button' class='efb btn %s %s efb-btn-lg mt-1 fs-6 %s' id='%s_b' onclick='fun_clear_esign_efb(\"%s\")'>
							<i class='efb %s mx-2 %s' id='%s_icon'></i>
							<span id='%s_button_single_text' class='efb %s' %s>%s</span>
						</button>
					</div>
				",
				$pos[3],
				$randomId, $formId,
				$el_height, $corner, $el_text_color, $vj->el_border_color,
				str_replace(',', ' ', $classes),
				$randomId, $randomId, $randomId, $randomId,
				$ariaDescribedBy,
				$updateUrbrowser,
				$previewSate ? sprintf(
					"<input type='hidden' data-type='esign' data-vid='%s' class='efb emsFormBuilder_v %s' id='%s-sig-data' value='Data:image/gif;base64,R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==' data-formid='%s'>",
					$randomId,$required, $randomId, $formId
				) : '',
				$formId, $message,
				$formId,
				$corner, $vj->button_color, $disabled, $randomId, $randomId,
				$vj->icon, $vj->icon_color != 'default' ? $vj->icon_color : '',
				$randomId, $randomId,
				$vj->icon_color, $disabled, $vj->button_single_text
			);

			return $ui;

	}

	public function ui_dadfile_efb($vj, $previewSate, $form_id, $texts, $disabled, $corner) {

		$fileType = property_exists($vj, 'file') ? $vj->file : '';

		if ($fileType === 'customize') {
			$name_type_file = esc_html__('File', 'easy-form-builder') . '<span class="efb d-none d-md-inline fs-7">(' . esc_html($vj->file_ctype) . ')</span>';
		} elseif ($fileType === 'allformat') {
			$name_type_file = esc_html__('File', 'easy-form-builder');
		} else {
			$name_type_file = $texts[$fileType];
		}

		$filetype_efb = [
			'image' => 'image/png, image/jpeg, image/jpg, image/gif, image/heic',
			'media' => 'audio/mpeg, audio/wav, audio/ogg, video/mp4, video/webm, video/x-matroska, video/avi, video/mpeg, video/mpg, audio/mpg, video/mov, video/quicktime',
			'document' => '.xlsx, .xls, .doc, .docx, .ppt, .pptx, .pptm, .txt, .pdf, .dotx, .rtf, .odt, .ods, .odp, application/pdf, text/plain, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/msword, application/vnd.openxmlformats-officedocument.wordprocessingml.document, application/vnd.ms-excel, application/vnd.ms-powerpoint, application/vnd.openxmlformats-officedocument.presentationml.presentation, application/vnd.ms-powerpoint.presentation.macroEnabled.12, application/vnd.openxmlformats-officedocument.wordprocessingml.template, application/vnd.oasis.opendocument.spreadsheet, application/vnd.oasis.opendocument.presentation, application/vnd.oasis.opendocument.text',
			'zip' => '.zip, application/zip, application/octet-stream, application/x-zip-compressed, multipart/x-zip, rar, application/x-rar-compressed, application/x-rar, application/rar, application/x-compressed, .rar, .7z, .tar, .gz, .gzip, .tgz, .tar.gz, .tar.gzip, .tar.z, .tar.Z, .tar.bz2, .tar.bz, .tar.bzip2, .tar.bzip, .tbz2, .tbz, .bz2, .bz, .bzip2, .bzip, .tz2, .tz, .z, .war, .jar, .ear, .sar',
			'allformat' => 'image/png, image/jpeg, image/jpg, image/gif, audio/mpeg, audio/wav, audio/ogg, video/mp4, video/webm, video/x-matroska, video/avi, video/mpeg, video/mpg, audio/mpg, .xlsx, .xls, .doc, .docx, .ppt, .pptx, .pptm, .txt, .pdf, .dotx, .rtf, .odt, .ods, .odp, application/pdf, text/plain, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/msword, application/vnd.openxmlformats-officedocument.wordprocessingml.document, application/vnd.ms-excel, application/vnd.ms-powerpoint, application/vnd.openxmlformats-officedocument.presentationml.presentation, application/vnd.ms-powerpoint.presentation.macroEnabled.12, application/vnd.openxmlformats-officedocument.wordprocessingml.template, application/vnd.oasis.opendocument.spreadsheet, application/vnd.oasis.opendocument.presentation, application/vnd.oasis.opendocument.text, .zip, application/zip, application/octet-stream, application/x-zip-compressed, multipart/x-zip, rar, application/x-rar-compressed, application/x-rar, application/rar, application/x-compressed, .rar, .zip, .7z, .tar, .gz, .gzip, .tgz, .tar.gz, .tar.gzip, .tar.z, .tar.Z, .tar.bz2, .tar.bz, .tar.bzip2, .tar.bzip, .tbz2, .tbz, .bz2, .bz, .bzip2, .bzip, .tz2, .tz, .z, .war, .jar, .ear, .sar, .heic, image/heic, video/mov, .mov, video/quicktime, video/quicktime',
			'customize' => $fileType
		];

		$fileTypeAttr = isset($filetype_efb[$vj->value]) ? $filetype_efb[$vj->value] : '';
		$requiredClass = ($vj->required == 1 || $vj->required == true) ? 'required' : '';
		$readonlyAttr = $previewSate != true ? 'disabled' : '';
		/* F07: one accept list shared with the file field and the server (Uploads::accept_attribute()),
		 * multiple / capture / data-* limits, and the drop zone's text for several files. */
		$upload_attrs = '';
		$drop_text = $texts['dragAndDropA'];
		if (class_exists('\\Emsfb\\Uploads')) {
			$upload_attrs = \Emsfb\Uploads::input_attributes($vj);
			if (\Emsfb\Uploads::config($vj)['multi']) {
				$drop_text = isset($texts['dragAndDropMany']) && $texts['dragAndDropMany'] !== '' ? $texts['dragAndDropMany'] : esc_html__('Drag and drop files here', 'easy-form-builder');
				$name_type_file = '';
			}
		}

		return sprintf(
			'<div class="efb icon efb">
				<i class="efb fs-3 %1$s %2$s" id="%3$s_icon"></i>
			</div>
			<h6 id="%3$s_txt" class="efb text-center m-1 fs-6">%4$s %5$s</h6>
			<div class="efb efb-slot"><span class="efb fs-7 my-1">%6$s</span></div>
			<div class="efb btn %7$s efb-btn-lg fs-6 mb-1" id="%3$s_b" %8$s>
				<i class="efb bi-upload mx-2 fs-6"></i>%9$s
			</div>
			<div class="efb d-none"><input type="file" hidden=""%14$s data-type="dadfile" data-vid="%3$s" data-id="%3$s" class="efb emsFormBuilder_v %11$s dadfile" id="%3$s_" data-id="%3$s-el" data-formid="%13$s" %12$s %8$s></div>',
			$vj->icon,
			$vj->icon_color,
			$vj->id_,
			$drop_text,
			$name_type_file ,
			$texts['or'],
			$vj->button_color,
			$disabled,
			$texts['browseFile'],
			$fileTypeAttr,
			$requiredClass,
			$readonlyAttr,
			$form_id,
			$upload_attrs !== '' ? $upload_attrs : ' accept="' . esc_attr($fileTypeAttr) . '"'
		);
	}

	public function ui_recorder_efb($vj, $form_id, $texts, $disabled) {
		$kind = $vj->type;
		$kindMeta = [
			'audio_recorder' => ['icon' => 'bi-mic', 'accept' => 'audio/*', 'class' => 'efb-recorder-audio'],
			'video_recorder' => ['icon' => 'bi-camera-video', 'accept' => 'video/*', 'class' => 'efb-recorder-video-kind'],
			'screen_recorder' => ['icon' => 'bi-display', 'accept' => 'video/*', 'class' => 'efb-recorder-screen-kind'],
		];
		$meta = isset($kindMeta[$kind]) ? $kindMeta[$kind] : $kindMeta['audio_recorder'];
		$quality = property_exists($vj, 'record_quality') && $vj->record_quality ? $vj->record_quality : ($kind == 'audio_recorder' ? 'standard' : '720p');
		$maxDuration = property_exists($vj, 'max_duration') && $vj->max_duration ? intval($vj->max_duration) : 90;
		$maxFileSize = property_exists($vj, 'max_fsize') && is_numeric($vj->max_fsize) && floatval($vj->max_fsize) > 0 ? floatval($vj->max_fsize) : 20;
		$requiredClass = ($vj->required == 1 || $vj->required == true) ? 'required' : '';
		$requiredAttr = ($vj->required == 1 || $vj->required == true) ? 'required' : '';
		$readonlyAttr = $disabled == 'disabled' ? 'disabled' : '';
		$domain = wp_parse_url(home_url(), PHP_URL_HOST);
		// UX extras - MUST stay in sync with efbRecorderWidgetHtml() in
		// public/assets/js/recorder-efb.js (see docs/recorder-fields.md §2).
		$countdown = property_exists($vj, 'rec_countdown') ? intval($vj->rec_countdown) : 3;
		$download = property_exists($vj, 'rec_download') ? (intval($vj->rec_download) ? 1 : 0) : 1;
		$noise = property_exists($vj, 'rec_noise') ? (intval($vj->rec_noise) ? 1 : 0) : 1;
		$facing = property_exists($vj, 'rec_facing') && $vj->rec_facing === 'environment' ? 'environment' : 'user';
		$mirror = property_exists($vj, 'rec_mirror') ? (intval($vj->rec_mirror) ? 1 : 0) : 1;
		$watermark = property_exists($vj, 'rec_watermark') ? (intval($vj->rec_watermark) ? 1 : 0) : 1;
		// Generic style settings live on the shell, same class list the JS factory emits;
		// recorder-efb.css translates them onto the inner frame.
		$elHeight = property_exists($vj, 'el_height') && $vj->el_height ? $vj->el_height : 'h-d-efb';
		$corner = property_exists($vj, 'corner') && $vj->corner ? $vj->corner : 'rounded-3';
		$borderColor = property_exists($vj, 'el_border_color') && $vj->el_border_color ? $vj->el_border_color : 'border-d';
		$extraClasses = property_exists($vj, 'classes') && $vj->classes ? trim(str_replace(',', ' ', $vj->classes)) : '';

		$mediaPreview = $kind == 'audio_recorder'
			? sprintf('<canvas class="efb efb-recorder-meter d-none" id="%1$s-meter" width="300" height="64"></canvas>', $vj->id_)
			: sprintf(
				'<video class="efb efb-recorder-video d-none" id="%1$s-preview" playsinline muted></video>
				<div class="efb efb-recorder-watermark %4$s" id="%1$s-watermark"><span>%2$s</span><span class="efb efb-recorder-domain">%3$s</span></div>',
				$vj->id_,
				esc_html($texts['recWatermark']),
				esc_html($domain),
				$watermark ? '' : 'd-none'
			);

		return sprintf(
			'<div class="efb efb-recorder-shell %1$s %30$s %31$s %32$s efb1 %33$s" data-css="%2$s" id="%2$s_" data-id="%2$s" data-kind="%3$s" data-quality="%4$s" data-duration="%5$s" data-max-size="%28$s" data-countdown="%22$s" data-download="%23$s" data-noise="%24$s" data-facing="%25$s" data-mirror="%26$s" data-formid="%6$s" data-state="idle">
				<div class="efb efb-recorder-frame" id="%2$s-frame">
					%7$s
					<div class="efb efb-recorder-idle-hint" id="%2$s-idle"><i class="efb bi %8$s"></i><span>%9$s</span></div>
					<div class="efb efb-recorder-timer d-none" id="%2$s-timer">00:00</div>
					<div class="efb efb-recorder-action-row" id="%2$s-controls">
						<button type="button" class="efb efb-recorder-secondary-btn d-none" data-action="pause" data-id="%2$s" title="%10$s" %11$s><i class="efb bi-pause-fill"></i></button>
						<button type="button" class="efb efb-recorder-primary-btn" data-action="start" data-id="%2$s" data-start-icon="%8$s" data-stop-icon="bi-stop-fill" title="%12$s" %11$s><i class="efb bi %8$s"></i></button>
						<button type="button" class="efb efb-recorder-secondary-btn d-none" data-action="resume" data-id="%2$s" title="%13$s" %11$s><i class="efb bi-record-circle"></i></button>
						<button type="button" class="efb efb-recorder-secondary-btn d-none" data-action="redo" data-id="%2$s" title="%14$s" %11$s><i class="efb bi-arrow-counterclockwise"></i></button>
						<button type="button" class="efb efb-recorder-secondary-btn d-none" data-action="play" data-id="%2$s" title="%15$s"><i class="efb bi-play-fill"></i></button>
						<button type="button" class="efb efb-recorder-secondary-btn d-none" data-action="download" data-id="%2$s" title="%27$s"><i class="efb bi-download"></i></button>
						<button type="button" class="efb efb-recorder-secondary-btn d-none" data-action="upload" data-id="%2$s" id="%2$s-upload" title="%29$s" aria-label="%29$s" %11$s><i class="efb bi-cloud-arrow-up-fill" aria-hidden="true"></i></button>
					</div>
					<div class="efb efb-recorder-progress-track"><div class="efb efb-recorder-progress-bar" id="%2$s-progress"></div></div>
				</div>
				<div class="efb efb-recorder-status-row">
					<span class="efb efb-recorder-status" id="%2$s-status"><span class="efb efb-recorder-status-dot"></span>%16$s</span>
					<span class="efb efb-recorder-badge"><i class="efb bi %8$s" aria-hidden="true"></i><span>%21$s</span></span>
				</div>
				<div class="efb d-none"><input type="file" hidden accept="%17$s" data-type="%3$s" data-vid="%2$s" data-id="%2$s" class="efb emsFormBuilder_v %18$s %19$s" id="%2$s_file" data-formid="%6$s" onchange="valid_file_emsFormBuilder(\'%2$s\',\'msg\',\'\',%6$s)" %20$s %11$s></div>
			</div>',
			$meta['class'],
			$vj->id_,
			$kind,
			$quality,
			$maxDuration,
			$form_id,
			$mediaPreview,
			$meta['icon'],
			esc_html($texts['recTapToStart']),
			esc_html($texts['recPause']),
			$readonlyAttr,
			esc_html($texts['recStart']),
			esc_html($texts['recResume']),
			esc_html($texts['recRedo']),
			esc_html($texts['recPlay']),
			esc_html($texts['recReady']),
			$meta['accept'],
			$kind,
			$requiredClass,
			$requiredAttr,
			esc_html(isset($texts[$kind]) ? $texts[$kind] : $kind),
			$countdown,
			$download,
			$noise,
			$facing,
			$mirror,
			esc_html(isset($texts['recDownload']) ? $texts['recDownload'] : 'Download recording'),
			$maxFileSize,
			esc_html(isset($texts['recUpload']) ? $texts['recUpload'] : 'Upload'),
			esc_attr($elHeight),
			esc_attr($corner),
			esc_attr($borderColor),
			esc_attr($extraClasses)
		);
	}

	public function dadfile_el_pro_efb($previewSate, $rndm, $vj, $form_id, $texts) {
		$corner = property_exists($vj, 'corner') ? $vj->corner : 'rounded-3';
		$disabled = property_exists($vj, 'disabled') && $vj->disabled == true ? 'disabled' : '';

		$ui = $this->ui_dadfile_efb($vj, $previewSate, $form_id, $texts , $disabled, $corner);
		/* F07: a field of several files gets its file list after the drop zone (outside the label, so
		 * its buttons are not part of the label), and the drop zone is a keyboard button. */
		$multi_efb = class_exists('\\Emsfb\\Uploads') && \Emsfb\Uploads::config($vj)['multi'];
		return sprintf(
			'<div class="efb mb-3" id="uploadFilePreEfb" data-formid="%s">
				<label for="%s_" class="efb form-label">
					<div class="efb dadFile-efb py-0 %s %s %s efb1 %s %s"  id="%s_box" aria-describedby="%s" %s>
						%s
					</div>
				</label>' . ($multi_efb ? \Emsfb\Uploads::list_markup($vj->id_) : '') . '
			</div>',
			$form_id,
			$rndm,
			$disabled,
			$vj->el_height,
			$corner,
			$vj->el_border_color,
			str_replace(',', ' ', $vj->classes),
			$rndm,
			!empty($vj->message) ? $vj->id_ . '-des' : '',
			$disabled,
			$ui
		);
	}

	private function text_nr_efb($text, $type) {
		$val = $type == 1 ? '<br>' : "\n";
		return str_replace('@n#', $val, $text);
	}

	private function fun_get_links_from_string_Efb($str , $handler){

		 $pattern = '/\[([^\]]+)\]\(([^)]+)\)/';

		 if ($handler === false) {

			 $matches = [];
			 preg_match_all($pattern, $str, $matches, PREG_SET_ORDER);

			 $result = [];
			 $state = !empty($matches);

			 foreach ($matches as $match) {
				 $result[] = [
					 'text' => $match[1],
					 'url' => $match[2]
				 ];
			 }

			 return [$state, $result];
		 } else {

			 return preg_replace_callback($pattern, function($matches) {
				 return '<a href="' . htmlspecialchars($matches[2], ENT_QUOTES, 'UTF-8') . '" target="_blank">' . htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8') . '</a>';
			 }, $str);
		 }
	}

	public function add_ui_stripe_efb($rndm , $cl, $sub,$form_id,$texts) {
		$currency = $this->valj_efb[0]->currency;
		$amount =self::formatPrice_efb(0, $currency);
		return  '
		<!-- stripe -->
		<div class="efb  ' . $this->mobile_pos[3] . ' stripe emsFormBuilder_v"  id="'.$rndm.'-f" data-formid="'.$form_id.'">
		<div class="efb  stripe-bg  p-3 card w-100">
		<div class="efb  headpay border-b row col-md-12 mb-3">
		  <div class="efb  h3 col-sm-5">
			<div class="efb  col-12 text-dark"> '.$texts['payAmount'].'</div>
			<div class="efb  text-labelEfb mx-2 my-1 fs-7"> <i class="efb mx-1 bi-shield-check" id="powerby_icon_'.$form_id.'"></i><span class="efb" id="powerby_label_'.$form_id.'">Powered by Stripe</span></div>
		  </div>
		  <div class="efb  h3 col-sm-7 d-flex justify-content-end payPriceEfb" id="payPriceEfb"  data-formid="'.$form_id.'">
			<span  class="efb  totalpayEfb d-flex justify-content-evenly mx-1 stripe"  data-formid="'.$form_id.'" id="totalpayEfb_'.$form_id.'">'.$amount.'</span>

			<span class="efb  text-labelEfb '.$cl.' text-capitalize" id="chargeEfb"  data-formid="'.$form_id.'">'.$sub.'</span>
		  </div>
		</div>
		<div id="stripeCardSectionEfb" class="efb ">
		  <div class="efb  col-md-12 my-2">
		  <label for="cardnoEfb" class="efb fs-6 text-dark priceEfb">'.$texts['cardNumber'].': </label>
		  <div id="cardnoEfb" class="efb form-control h-d-efb text-labelEfb"></div>
		  </div>
		  <div class="efb  col-sm-12 row my-2">
			<div class="efb  col-sm-6 my-2">
			<label for="cardexpEfb" class="efb  fs-6 text-dark priceEfb">'.$texts['cardExpiry'].': </label>
			<div id="cardexpEfb" class="efb form-control h-d-efb text-labelEfb"></div>
			</div>
			<div class="efb  col-sm-6 my-2">
			<label for="cardcvcEfb" class="efb  fs-6 text-dark priceEfb">'.$texts['cardCVC'].': </label>
			<div id="cardcvcEfb" class="efb form-control h-d-efb text-labelEfb"></div>
			</div>
		  </div>
		</div>
		<div class="efb efb-slot"><a class="efb  btn my-2 efb p-2 rounded-3 h-l-efb  efb-btn-lg float-end text-decoration-none disabled '.$this->pub_bg_button_color_efb.' text-white" id="btnStripeEfb" data-formid="'.$form_id.'">'.$texts['payNow'].'</a></div>
		<div class="efb  bg-light border-d rounded-3 p-2 bg-muted" id="statusStripEfb" style="display: none"></div>
		</div>
		</div>
		<!-- end stripe -->
		';
	}

	public function add_ui_paypal_efb($rndm, $form_id, $texts, $currency = 'USD', $charge_class = '', $sub = '') {

		$currency = $this->valj_efb[0]->currency;
		$amount =self::formatPrice_efb(0, $currency);

		return '
		<div class="efb card w-100 ' . $this->mobile_pos[3] . ' m-0 p-0" id="'.$rndm.'-f" data-formid="'.$form_id.'">
			<div class="efb p-3 d-block" id="beforePay" data-formid="'.$form_id.'">
				<div class="efb headpay border-b row col-md-12 mb-3">
					<div class="efb h3 col-sm-5">
						<div class="efb col-12 text-dark">'.$texts['payAmount'].':</div>
						<div class="efb text-labelEfb mx-2 my-1 fs-7">
							<i class="efb mx-1 bi-shield-check" id="powerby_icon_'.$form_id.'"></i>
							<span class="efb" id="powerby_label_'.$form_id.'">Powered by PayPal</span>
						</div>
					</div>
					<div class="efb h3 col-sm-7 d-flex justify-content-end" id="payPriceEfb" data-formid="'.$form_id.'">
						<span  class="efb  totalpayEfb d-flex justify-content-evenly mx-1 paypal"  data-formid="'.$form_id.'" id="totalpayEfb_'.$form_id.'">'.$amount.'</span>'.
						(!empty($sub) ? '<span class="efb text-labelEfb '.htmlspecialchars($charge_class, ENT_QUOTES).' text-capitalize mx-1" id="chargeEfb" data-formid="'.$form_id.'">'.htmlspecialchars($sub, ENT_QUOTES).'</span>' : '')
					.'</div>
				</div>
				<div class="my-2 efb p-2" id="paypal-button-container" data-formid="'.$form_id.'">
					<div class="efb efb-slot"><a class="efb btn rounded-3 h-l-efb btn-primary text-white text-decoration-none disabled w-100 paypalEfb"
					onclick="startPaymentPayPal_efb('.intval($form_id).')"
					id="paypalEfb"
					data-formid="'.$form_id.'">'.$texts['payNow'].'</a></div>
				</div>
			</div>
			<div class="efb p-3 card w-100 d-none" id="afterPayefb" data-formid="'.$form_id.'">
			</div>
		';
	}

	public function add_ui_zp_efb($rndm , $form_id,$texts) {
		return  '
		<div class="efb card w-100 ' . $this->mobile_pos[3] . ' m-0 p-0"  id="'.$rndm.'-f"  data-formid="'.$form_id.'">
			<div class="efb  p-3 d-block" id="beforePay">
				<div class="efb  headpay border-b row col-md-12 mb-3">
					<div class="efb  h3 col-sm-5">
						<div class="efb  col-12 text-dark"> '.$texts['payAmount'].':</div>
						<div class="efb  text-labelEfb mx-2 my-1 fs-7"> <i class="efb mx-1 bi-shield-check"></i>پرداخت توسط <span Class="efb fs-6" id="efbPayBy">زرین پال</span></div>
					</div>
					<div class="efb  h3 col-sm-7 d-flex justify-content-end" id="payPriceEfb"  data-formid="'.$form_id.'">
						<span  class="efb totalpayEfb d-flex justify-content-evenly mx-1 zp" data-formid="'.$form_id.'">'.number_format(0, 2, '.', ',').'</span>
						<!-- <span class="efb currencyPayEfb fs-5" id="currencyPayEfb">تومان</span> -->
						<!-- <span class="efb  text-labelEfb one" id="chargeEfb">'.$texts['onetime'].'</span>-->
					</div>
				</div>
				<div class="efb efb-slot"><a class="efb btn my-2 efb p-2 rounded-3 h-l-efb btn-primary text-white text-decoration-none disabled w-100" onclick="pay_persia_efb('.$form_id.')" id="persiaPayEfb"  data-formid="'.$form_id.'">'.$texts['payment'].'</a></div>
			</div>
			<div class="efb p-3 card w-100 d-none" id="afterPayefb">
			</div>
		';

	}

	 public function totalprice_el_pro_efb($rndm, $vj ,$currency,$form_id) {
		$el_height = isset($vj->el_height) ? $vj->el_height : '';
		$el_text_color = isset($vj->el_text_color) ? $vj->el_text_color : '';
		$classes =  str_replace(',', ' ', $vj->classes) ?? '';
		$amount = 0;
		$lan_name_emsFormBuilder = 'en-US';
		$currency = $currency ? $currency : 'USD';
		$currency_details = self::get_currency_details_efb($currency);
		$amount =self::formatPrice_efb($amount, $currency);

		return sprintf(
			'<label class="efb totalpayEfb %s %s %s mt-1"   data-id="%s-el" id="%s_" data-formid="%s">
				%s
			</label>',
			$el_height,
			$el_text_color,
			$classes,
			$rndm,
			$rndm,
			$form_id,
			$amount
		);
	 }

	/**
	 * Render an amount with its currency symbol, in the reader's direction.
	 *
	 * Static because it reads nothing from the instance: the notification-email
	 * builder in _Public needs the same formatting and has no Formbuilder to
	 * construct (it holds submitted values, not a form structure). Keeping one
	 * implementation here is what stops a second, drifting copy from appearing.
	 *
	 * $amount must be a raw number. Passing an already grouped string such as
	 * "1,234" makes number_format_i18n() cast it to 1 and silently print the
	 * wrong price.
	 *
	 * @param int|float|string $amount   Raw, ungrouped amount.
	 * @param string           $currency ISO currency code.
	 * @return string
	 */
	public static function formatPrice_efb($amount, $currency) {

		/* Both arguments can reach here from stored submission content, which is
		 * whatever the visitor's browser posted. number_format_i18n() raises a
		 * TypeError on a non-numeric string and strtoupper() on a non-string, so
		 * a single odd value in one field would otherwise take down the whole
		 * notification email. Coerced once, here, because this is the only place
		 * that owns the rule. */
		$amount   = is_numeric($amount) ? $amount + 0 : 0;
		$currency = is_string($currency) ? $currency : '';

		$currency_details = self::get_currency_details_efb($currency);
    	$formatted_amount = number_format_i18n($amount, $currency_details['d']);
		if (is_rtl()) {
			return $formatted_amount . ' ' . $currency_details['s'];
		} else {
			return $currency_details['s'] . '' . $formatted_amount;
		}

    }

	/**
	 * Symbol and decimal count for an ISO currency code. Pure lookup, so it is
	 * static for the same reason formatPrice_efb() above is.
	 *
	 * @param string $currency ISO currency code.
	 * @return array{s:string,d:int}
	 */
	public static function get_currency_details_efb($currency) {
		$currency = strtoupper($currency);
		$symbols = array(
			'USD' => array('s' => '$', 'd' => 2),
			'AED' => array('s' => 'د.إ', 'd' => 2),
			'AFN' => array('s' => '؋', 'd' => 2),
			'ALL' => array('s' => 'L', 'd' => 2),
			'AMD' => array('s' => '֏', 'd' => 2),
			'ANG' => array('s' => 'ƒ', 'd' => 2),
			'AOA' => array('s' => 'Kz', 'd' => 2),
			'ARS' => array('s' => '$', 'd' => 2),
			'AUD' => array('s' => 'A$', 'd' => 2),
			'AWG' => array('s' => 'ƒ', 'd' => 2),
			'AZN' => array('s' => '₼', 'd' => 2),
			'BAM' => array('s' => 'KM', 'd' => 2),
			'BBD' => array('s' => '$', 'd' => 2),
			'BDT' => array('s' => '৳', 'd' => 2),
			'BGN' => array('s' => 'лв', 'd' => 2),
			'BIF' => array('s' => 'FBu', 'd' => 0),
			'BMD' => array('s' => '$', 'd' => 2),
			'BND' => array('s' => '$', 'd' => 2),
			'BOB' => array('s' => 'Bs.', 'd' => 2),
			'BRL' => array('s' => 'R$', 'd' => 2),
			'BSD' => array('s' => '$', 'd' => 2),
			'BWP' => array('s' => 'P', 'd' => 2),
			'BYN' => array('s' => 'Br', 'd' => 2),
			'BZD' => array('s' => '$', 'd' => 2),
			'CAD' => array('s' => 'C$', 'd' => 2),
			'CDF' => array('s' => 'FC', 'd' => 2),
			'CHF' => array('s' => 'CHF', 'd' => 2),
			'CLP' => array('s' => '$', 'd' => 0),
			'CNY' => array('s' => '¥', 'd' => 2),
			'COP' => array('s' => '$', 'd' => 2),
			'CRC' => array('s' => '₡', 'd' => 2),
			'CVE' => array('s' => 'Esc', 'd' => 2),
			'CZK' => array('s' => 'Kč', 'd' => 2),
			'DJF' => array('s' => 'Fdj', 'd' => 0),
			'DKK' => array('s' => 'kr', 'd' => 2),
			'DOP' => array('s' => 'RD$', 'd' => 2),
			'DZD' => array('s' => 'د.ج', 'd' => 2),
			'EGP' => array('s' => '£', 'd' => 2),
			'ETB' => array('s' => 'Br', 'd' => 2),
			'EUR' => array('s' => '€', 'd' => 2),
			'FJD' => array('s' => '$', 'd' => 2),
			'FKP' => array('s' => '£', 'd' => 2),
			'GBP' => array('s' => '£', 'd' => 2),
			'GEL' => array('s' => '₾', 'd' => 2),
			'GIP' => array('s' => '£', 'd' => 2),
			'GMD' => array('s' => 'D', 'd' => 2),
			'GNF' => array('s' => 'FG', 'd' => 0),
			'GTQ' => array('s' => 'Q', 'd' => 2),
			'GYD' => array('s' => '$', 'd' => 2),
			'HKD' => array('s' => '$', 'd' => 2),
			'HNL' => array('s' => 'L', 'd' => 2),
			'HTG' => array('s' => 'G', 'd' => 2),
			'HUF' => array('s' => 'Ft', 'd' => 2),
			'IDR' => array('s' => 'Rp', 'd' => 2),
			'ILS' => array('s' => '₪', 'd' => 2),
			'INR' => array('s' => '₹', 'd' => 2),
			'IRR' => array('s' => '﷼', 'd' => 0),
			'ISK' => array('s' => 'kr', 'd' => 0),
			'JMD' => array('s' => '$', 'd' => 2),
			'JPY' => array('s' => '¥', 'd' => 0),
			'KES' => array('s' => 'KSh', 'd' => 2),
			'KGS' => array('s' => 'лв', 'd' => 2),
			'KHR' => array('s' => '៛', 'd' => 2),
			'KMF' => array('s' => 'CF', 'd' => 0),
			'KRW' => array('s' => '₩', 'd' => 0),
			'KYD' => array('s' => '$', 'd' => 2),
			'KZT' => array('s' => '₸', 'd' => 2),
			'LAK' => array('s' => '₭', 'd' => 2),
			'LBP' => array('s' => 'ل.ل', 'd' => 0),
			'LKR' => array('s' => 'Rs', 'd' => 2),
			'LRD' => array('s' => '$', 'd' => 2),
			'LSL' => array('s' => 'L', 'd' => 2),
			'MAD' => array('s' => 'د.م.', 'd' => 2),
			'MDL' => array('s' => 'L', 'd' => 2),
			'MGA' => array('s' => 'Ar', 'd' => 2),
			'MKD' => array('s' => 'ден', 'd' => 2),
			'MMK' => array('s' => 'K', 'd' => 2),
			'MNT' => array('s' => '₮', 'd' => 2),
			'MOP' => array('s' => 'P', 'd' => 2),
			'MUR' => array('s' => '₨', 'd' => 2),
			'MVR' => array('s' => 'ރ.', 'd' => 2),
			'MWK' => array('s' => 'MK', 'd' => 2),
			'MXN' => array('s' => '$', 'd' => 2),
			'MYR' => array('s' => 'RM', 'd' => 2),
			'MZN' => array('s' => 'MT', 'd' => 2),
			'NAD' => array('s' => '$', 'd' => 2),
			'NGN' => array('s' => '₦', 'd' => 2),
			'NIO' => array('s' => 'C$', 'd' => 2),
			'NOK' => array('s' => 'kr', 'd' => 2),
			'NPR' => array('s' => '₨', 'd' => 2),
			'NZD' => array('s' => '$', 'd' => 2),
			'PAB' => array('s' => 'B/.', 'd' => 2),
			'PEN' => array('s' => 'S/', 'd' => 2),
			'PGK' => array('s' => 'K', 'd' => 2),
			'PHP' => array('s' => '₱', 'd' => 2),
			'PKR' => array('s' => '₨', 'd' => 2),
			'PLN' => array('s' => 'zł', 'd' => 2),
			'PYG' => array('s' => '₲', 'd' => 0),
			'QAR' => array('s' => 'ر.ق', 'd' => 2),
			'RON' => array('s' => 'lei', 'd' => 2),
			'RSD' => array('s' => 'дин', 'd' => 2),
			'RUB' => array('s' => '₽', 'd' => 2),
			'RWF' => array('s' => 'FRw', 'd' => 2),
			'SAR' => array('s' => 'ر.س', 'd' => 2),
			'SBD' => array('s' => '$', 'd' => 2),
			'SCR' => array('s' => '₨', 'd' => 2),
			'SEK' => array('s' => 'kr', 'd' => 2),
			'SGD' => array('s' => '$', 'd' => 2),
			'SHP' => array('s' => '£', 'd' => 2),
			'SLE' => array('s' => 'Le', 'd' => 2),
			'SOS' => array('s' => 'Sh', 'd' => 2),
			'SRD' => array('s' => '$', 'd' => 2),
			'STD' => array('s' => 'Db', 'd' => 2),
			'SZL' => array('s' => 'L', 'd' => 2),
			'THB' => array('s' => '฿', 'd' => 2),
			'TJS' => array('s' => 'ЅМ', 'd' => 2),
			'TND' => array('s' => 'د.ت', 'd' => 3),
			'TOP' => array('s' => 'T$', 'd' => 2),
			'TRY' => array('s' => '₺', 'd' => 2),
			'TTD' => array('s' => '$', 'd' => 2),
			'TWD' => array('s' => 'NT$', 'd' => 2),
			'TZS' => array('s' => 'Sh', 'd' => 2),
			'UAH' => array('s' => '₴', 'd' => 2),
			'UGX' => array('s' => 'USh', 'd' => 0),
			'UYU' => array('s' => '$U', 'd' => 2),
			'UZS' => array('s' => 'лв', 'd' => 2),
			'VND' => array('s' => '₫', 'd' => 0),
			'VUV' => array('s' => 'VT', 'd' => 0),
			'WST' => array('s' => 'T', 'd' => 2),
			'XAF' => array('s' => 'FCFA', 'd' => 0),
			'XCD' => array('s' => '$', 'd' => 2),
			'XOF' => array('s' => 'CFA', 'd' => 0),
			'XPF' => array('s' => '₣', 'd' => 0),
			'YER' => array('s' => '﷼', 'd' => 2),
			'ZAR' => array('s' => 'R', 'd' => 2),
			'ZMW' => array('s' => 'ZK', 'd' => 2),
			'BHD' => array('s' => '.د.ب', 'd' => 3),
			'JOD' => array('s' => 'د.ا', 'd' => 3),
			'KWD' => array('s' => 'د.ك', 'd' => 3),
			'OMR' => array('s' => 'ر.ع.', 'd' => 3),
			'TND' => array('s' => 'د.ت', 'd' => 3)
		);

		return isset($symbols[$currency]) ? $symbols[$currency] : array('s' => $currency, 'd' => 2);
	}

	public function ColorNameToHexEfbOfElEfb($v, $n) {

		$color_map = [
			"primary" => '#0d6efd',
			"success" => '#198754',
			"secondary" => '#6c757d',
			"danger" => '#ff455f',
			"warning" => '#e9c31a',
			"info" => '#31d2f2',
			"light" => '#fbfbfb',
			"darkb" => '#202a8d',
			"labelEfb" => '#898aa9',
			"d" => '#83859f',
			"pinkEfb" => '#ff4b93',
			"white" => '#ffffff',
			"dark" => '#212529',
			"muted" => '#777777'
		];

		$id_map = [
			"label" => "style_label_color",
			"description" => "style_message_text_color",
			"el" => "style_el_text_color",
			"btn" => "style_btn_text_color",
			"icon" => "style_icon_color",
			"border" => "style_border_color"
		];

		$id = isset($id_map[$n]) ? $id_map[$n] : null;

		if (isset($color_map[$v])) {
			$r = $color_map[$v];
		} else {
			$len = strlen('colorDEfb-');
			if (strpos($v, 'colorDEfb') !== false) {
				$r = "#" . substr($v, $len);
			} else {
				$r = '';
			}
		}

		return $r;
	}

	public function switch_el_pro_efb($previewSate, $pos, $rndm, $vj, $desc, $formId, $label, $ttip, $aire_describedby, $texts) {
		$vj->on = property_exists($vj, 'on') ? $vj->on : $texts['on'];
		$vj->off = property_exists($vj, 'off') ? $vj->off : $texts['off'];

		$disabled = property_exists($vj, 'disabled') && $vj->disabled == true ? 'disabled' : '';
		$required = $vj->required == 1 || $vj->required == true ? 'required' : '';
		$readonly = $previewSate != true ? 'readonly' : '';
		$classes = str_replace(',', ' ', $vj->classes);
		$el_height = isset($vj->el_height) ? $vj->el_height : '';

		$ui = sprintf(
			'
			%s
			%s
			<div class="efb %s ' . $this->mobile_pos[3] . ' px-0 mx-0 ttEfb show" id="%s-f" %s data-formid="%s">
				<label class="efb fs-6" id="%s_off">%s</label>
				<button type="button" data-state="off" class="efb btn %s btn-toggle efb1 %s" data-css="%s" data-toggle="button" aria-pressed="false" data-vid="%s" onclick="fun_switch_efb(this)" data-id="%s-el" id="%s_" %s %s>
					<div class="efb handle"></div>
				</button>
				<label class="efb fs-6" id="%s_on">%s</label>
				<div class="efb mb-3">%s</div>
			',
			$label,
			$ttip,
			$pos[3],
			$rndm, $aire_describedby, $formId,
			$rndm, $vj->off,
			$el_height, $classes,
			$rndm, $rndm, $rndm, $rndm,
			$readonly, $disabled,
			$rndm, $vj->on,
			$desc
		);

		return $ui;
	}

	public function rating_el_pro_efb($previewSate, $pos, $rndm, $vj, $desc, $formId, $label, $ttip, $aire_describedby, $texts) {

		$disabled = isset($vj->disabled) && $vj->disabled == 1 ? 'disabled' : '';
		$requiredClass = ($vj->required == 1 || $vj->required == true) ? 'required' : '';
		$el_height = isset($vj->el_height) ? $vj->el_height : '';
		$classes = str_replace(',', ' ', $vj->classes);
		$ariaDescribedBy = !empty($vj->message) ? 'aria-describedby="' . $vj->id_ . '-des"' : '';
		$required = $vj->required == 1 ? 'required' : '';

		$ui = sprintf(
			'%s
			%s
			<div class="efb %s ' . $this->mobile_pos[3] . '" id="%s-f" data-formid="%s">
				<div class="efb star-efb d-flex justify-content-center %s efb1 %s" data-css="%s" %s>
					%s
					%s
					%s
					%s
					%s
				</div>
				<input type="hidden" data-vid="%s" data-type="rating" class="efb emsFormBuilder_v %s" id="%s-stared" data-formid="%s">
				%s
			',
			$ttip,
			$label,
			$pos[3],
			$rndm, $formId,
			$disabled, $classes, $rndm,
			$ariaDescribedBy,
			$this->generate_rating_input($rndm, 5, $previewSate, $disabled, $el_height, $texts['stars'], $formId),
			$this->generate_rating_input($rndm, 4, $previewSate, $disabled, $el_height, $texts['stars'], $formId),
			$this->generate_rating_input($rndm, 3, $previewSate, $disabled, $el_height, $texts['stars'], $formId),
			$this->generate_rating_input($rndm, 2, $previewSate, $disabled, $el_height, $texts['stars'], $formId),
			$this->generate_rating_input($rndm, 1, $previewSate, $disabled, $el_height, $texts['stars'], $formId),
			$rndm, $requiredClass, $rndm, $formId,
			$desc
		);

		return $ui;
	}

	private function generate_rating_input($rndm, $starValue, $previewSate, $disabled, $el_height, $starText,$form_id) {
		return sprintf(
			'<input type="radio" id="%s-star%s" data-vid="%s" data-formid="%s" data-type="rating" class="efb" data-star="star" name="%s-star-efb" value="%s" data-name="star" data-id="%s-el" %s %s>
			<label id="%s_star%s" for="%s-star%s" %s title="%s stars" class="efb %s star %s"> </label>',
			$rndm, $starValue,$form_id,
			$rndm, $rndm, $starValue,
			$rndm,
			$previewSate != true ? 'disabled' : '', $disabled,
			$rndm, $starValue, $rndm, $starValue,
			($previewSate == true && $disabled == '') ? sprintf('onclick="fun_get_rating_efb(\'%s\',%s , \'%s\')"', $rndm, $starValue,$form_id) : '',
			$starValue, $el_height, $disabled,
			$starValue, $starText
		);
	}

    public function generate_select_efb($elementId, $rndm, $vj, $pos, $formId, $texts, $previewSate ,$desc,$label,$ttip,$aire_describedby ) {

        /* 'payefb', not 'pay': every price handler tests for `payefb`, so a
         * paySelect rendered by the server never registered the chosen plan
         * and the total stayed at zero however much the plan cost. The admin
         * preview has always emitted `payefb` here - only the server side of
         * the same markup was out of step. */
        $pay = $elementId != "paySelect" ? '' : 'payefb';
        $options = '';
        $optns_obj = array_filter($this->valj_efb, function($obj) use ($rndm) {
            return isset($obj->parent) && $obj->parent === $rndm;
        });
		$is_selected = false;
		// F05: kept places, used-up choices ("N left" in the option text), shuffled order (choices-efb.js).
		$ch_parts_efb = class_exists('\\Emsfb\\Choices') && class_exists('\\Emsfb\\Stock')
			? \Emsfb\Choices::render_parts(\Emsfb\Stock::mark_rows(array(json_decode(wp_json_encode($this->valj_efb[0]), true), json_decode(wp_json_encode($vj), true)))[1], json_decode(wp_json_encode(array_values($optns_obj)), true), $formId, $this->stock_left)
			: array('wrap' => '', 'opt' => array());

        foreach ($optns_obj as $i) {
            $selected = ($vj->value == $i->id_ || (property_exists($i, 'id_old') && $vj->value == $i->id_old)) ? 'selected' : '';
            $ch_opt_efb = isset($ch_parts_efb['opt'][(string) $i->id_]) ? $ch_parts_efb['opt'][(string) $i->id_] : array('attr' => '', 'disabled' => false, 'hidden' => false, 'suffix' => '');
			if ($selected == 'selected') {
				$is_selected = true;
			}
            // F08: an option text with tags shows its fallback / public text here; the browser fills the rest (data-st-text).
            $opt_text_efb = $i->value;
            if (is_string($i->value) && strpos($i->value, '{') !== false && class_exists('\\Emsfb\\Smart_Tags') && \Emsfb\Smart_Tags::has_tags($i->value)) {
                $opt_text_efb = esc_html(\Emsfb\Smart_Tags::resolve($i->value, $this->st_ctx, 'text', 'label'));
                $opt_pipes_efb = false;
                foreach (\Emsfb\Smart_Tags::tags($i->value) as $opt_t_efb) if ($opt_t_efb['ns'] === 'field') $opt_pipes_efb = true;
                if (!$opt_pipes_efb || !isset($this->st_ctx['piping']) || $this->st_ctx['piping']) $selected .= ' data-st-text="' . esc_attr($i->value) . '"';
            }
            $ch_note_efb = $ch_opt_efb['suffix'] !== '' ? ' (' . strip_tags($ch_opt_efb['suffix']) . ')' : '';
            $options .= sprintf(
                '<option class="efb %s emsFormBuilder_v efb" data-id="%s" data-op="%s" value="%s" %s%s%s%s>%s</option>',
                $vj->el_text_color,
                esc_attr($i->id_),
                esc_attr($i->id_),
                esc_attr($i->value),
                $selected,
                $ch_opt_efb['attr'],
                $ch_opt_efb['disabled'] && $selected === '' ? ' disabled' . ($ch_opt_efb['hidden'] ? ' hidden' : '') : '',
                // F05: the label without the "N left" note, for choices-efb.js (only when a note is added).
                $ch_note_efb !== '' ? ' data-label="' . esc_attr(strip_tags((string) $opt_text_efb)) . '"' : '',
                (isset($opt_text_efb) && $opt_text_efb !== $i->value ? $opt_text_efb : esc_html($i->value)) . esc_html($ch_note_efb)
            );
        }

        $required = ($vj->required == 1 || $vj->required == true) ? 'required' : '';
        $readonly = $previewSate != true ? 'readonly' : '';
        $ariaRequired = $vj->required == 1 ? 'true' : 'false';
        $ariaDescribedBy = !empty($vj->message) ? 'aria-describedby="' . $vj->id_ . '-des"' : '';
        $disabled = property_exists($vj, 'disabled') && $vj->disabled == true ? 'disabled' : '';
        $corner = isset($vj->corner) ? $vj->corner : 'rounded-3';
        $el_height = isset($vj->el_height) ? $vj->el_height : '';
        $el_border_color = isset($vj->el_border_color) ? $vj->el_border_color : '';

        $ui = sprintf(
            '%s
            <div class="efb %s ' . $this->mobile_pos[3] . ' px-0 mx-0 ttEfb show efb1 %s" data-css="%s" id="%s-f" data-id="%s-el" data-formid="%s">
                %s
                <select data-formid="%s" class="efb form-select efb emsFormBuilder_v w-100 %s %s %s %s %s w-100" data-vid="%s" id="%s_options" aria-required="%s" aria-label="%s" %s %s %s' . (class_exists('\\Emsfb\\Choices') && \Emsfb\Choices::on(json_decode(wp_json_encode($vj), true), 'op_random') ? ' data-random="1"' : '') . '>
                    <option disabled %s id="efbNotingSelected">%s</option>
                    %s
                </select>
                %s
            ',
            $label,
            $pos[3],
            str_replace(',', ' ', $vj->classes),
            $rndm,
            $rndm, $rndm, $formId,
            $ttip,
			$formId,
            $pay,
            $required,
            $el_height,
            $corner,
            $el_border_color,
            $rndm,
            $rndm,
            $ariaRequired,
            $this->st_aria_efb($vj->name),
            $ariaDescribedBy,
            $readonly,
            $disabled,
			$is_selected ? '': 'selected',
            $texts['nothingSelected'],
            $options,
            $desc
        );

        return $ui;
    }

	private function sanitize_value_efb($value, $key) {
		switch ($key) {
			case 'email':
				return sanitize_email($value);
			case 'url':
				return sanitize_url($value);
			default:
				return sanitize_text_field($value) ;
		}
	}

	private function filter_and_sanitize_attributes_efb($item, $allowed_attributes_efb) {
		return array_filter($item, function($key) use ($allowed_attributes_efb) {
			return isset($allowed_attributes_efb[$key]);
		}, ARRAY_FILTER_USE_KEY);
	}

	private function filter_attributes_by_type_efb($data,$type) {
		static $allowed_attributes_efb = ['id_' => true, 'name' => true, 'id_ob' => true, 'amount' => true, 'type' => true, 'value' => true, 'session' => true ,'form_id'=>true];
		static $attribute_map_efb = [
			'email' => true, 'date' => true, 'url' => true, 'mobile' => true, 'radio' => true,
			'payRadio' => ['price' => true], 'chlRadio' => ['src' => true, 'sub_value' => true],
			'chlCheckBox'=>['qty'=>true],
			'imgRadio' => ['src' => true, 'sub_value' => true], 'switch' => true,
			'option' => ['price' => true,'qty'=>true], 'r_matrix' => ['label' => true],'postalcode'=>true,
			'multiselect' => true, 'select' => true, 'paySelect' => true,
			'stateProvince' => true, 'statePro' => true, 'conturyList' => true,
			'country' => true, 'city' => true, 'cityList' => true, 'sample' => true,
			'persiapay' => ['amount' => true],'ardate'=>true,'pdate'=>true ,'textarea'=>true,
			/* F06: the server recomputes every key but cal (the calendar used) and tz (a visitor-zone field). */
			'advdate' => ['cal' => true, 'local' => true, 'disp' => true, 'tz' => true, 'mode' => true, 'v' => true, 'time' => true, 'utc' => true, 'approx' => true, 'iso' => true],
			'payment' => ['amount' => true], 'file' => ['url' => true, 'fsz' => true, 'fnm' => true], 'address_line'=>true,
			'dadfile' => ['url' => true, 'fsz' => true, 'fnm' => true], 'esign' => true, 'maps' => true,
			'color' => true, 'range' => true, 'number' => true, 'prcfld' => true,
			'checkbox' => true, 'table_matrix' => true, 'trmCheckbox' => true,
			'ttlprc' => true, 'smartcr' => true, 'pointr5' => true,'tel'=>true,
			'pointr10' => true, 'zarinPal' => true, 'stripe' => ['amount' => true],
			'yesNo' => true, 'payMultiselect' => true, 'rating' => true, 'text'=>true, 'password'=>true,
			'hiddenField' => true // F08
		];

			if (isset($attribute_map_efb[$type])) {
				$allowed_attributes_efb_type = is_array($attribute_map_efb[$type]) ?  array_replace($allowed_attributes_efb, $attribute_map_efb[$type]) :$allowed_attributes_efb;

				$sanitized_item = $this->filter_and_sanitize_attributes_efb($data, $allowed_attributes_efb_type);
				foreach ($sanitized_item as $key => $value) {
					if ($key !== 'value') {
						$sanitized_item[$key] = $this->sanitize_value_efb($value, $key);
					}
				}
				return $sanitized_item;
			}

		return false;
	}

	public function fun_imgRadio_efb($id, $link, $row, $state , $text) {

		$process_url = function($url) {
			$url = preg_replace('/(http:@efb@)+/', 'http://', $url);
			$url = preg_replace('/(https:@efb@)+/', 'https://', $url);
			$url = str_replace('@efb@', '/', $url);
			return $url;
		};

		$value = isset($row->value ) ? $row->value :  '';
		$sub_value = isset($row->sub_value) ? $row->sub_value :  '';

		$link = $process_url($link);

		return sprintf(
			'<label class="efb" id="%s_lab" for="%s">
				<div class="efb card col-md-3 mx-0 my-1 w-100" style="">
					<img src="%s" alt="%s" style="width: 100%%" id="%s_img">
					<div class="efb card-body">
						<h5 class="efb card-title text-dark" id="%s_value">%s</h5>
						<p class="efb card-text" id="%s_value_sub">%s</p>
					</div>
				</div>
			</label>',
			$id,
			$id,
			$link,
			$value,
			$id,
			$id,
			$value,
			$id,
			$sub_value
		);
	}

	public function fun_captcha_load_efb($siteKey, $formId,$stepNo) {
		$captchaHTML = "";

			if (strlen($siteKey) > 1) {
				$captchaHTML = sprintf(
					'<div class="efb row mx-0" data-step="%1$s" data-formid="%3$s">
						<div id="gRecaptcha" class="efb g-recaptcha my-2 mx-0 px-0" data-sitekey="%2$s" style="transform:scale(0.88);-webkit-transform:scale(0.88);transform-origin:0 0;-webkit-transform-origin:0 0;" data-formid="%3$s">

						</div>
						<small class="efb text-danger" id="recaptcha-message-%3$s"></small>
					</div>',
					$stepNo,
					$siteKey,
					$formId
				);
			}

			$ui = sprintf(
				'%1$s
				<div id="step-1-efb-msg" data-formid="%2$s"></div>',
				$captchaHTML,
				$formId
			);

			return $ui;
	}

	public function loading_message_efb($pro ,$texts,$state=0) {

		$svg = '
			<svg viewBox="0 0 120 30" height="15px" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="xMidYMid meet">
				<circle cx="15" cy="15" r="15" fill="#abb8c3">
					<animate attributeName="r" from="15" to="9"
							begin="0s" dur="1s"
							values="15;9;15" calcMode="linear"
							repeatCount="indefinite" />
				</circle>
				<circle cx="60" cy="15" r="9" fill="#abb8c3">
					<animate attributeName="r" from="9" to="15"
							begin="0.3s" dur="1s"
							values="9;15;9" calcMode="linear"
							repeatCount="indefinite" />
				</circle>
				<circle cx="105" cy="15" r="15" fill="#abb8c3">
					<animate attributeName="r" from="15" to="9"
							begin="0.6s" dur="1s"
							values="15;9;15" calcMode="linear"
							repeatCount="indefinite" />
				</circle>
			</svg>';

		$copyRight = $this->free_plus_efb_powered_by($texts);

		$loadingMessage = sprintf(
			'<h2 class="efb fs-3 text-center">%s %s</h2><p class="efb fs-5">%s</p> %s',
			$texts[0], // Accessing translation or variable for "Please wait" text
			$svg,
			$state == 1 ? '<p class="efb fs-5">' . $texts[1] . '</p>' : '',
			$copyRight
		);

		return $loadingMessage;
	}

	function  free_plus_efb_powered_by($texts) {

		$text = esc_html__('Built with %sEasy Form Builder%s by %sWordPress form plugin by whitestudio.team%s', 'easy-form-builder');
		$text = sprintf($text, '<a href="https://wordpress.org/plugins/easy-form-builder/" target="_blank">', '</a>', '<a href="https://whitestudio.team" target="_blank">', '</a>');
		$copyRight = '<!-- texts -->';
		$efb = esc_html__('Easy Form Builder', 'easy-form-builder');
		$wp_text = esc_html__('Free WordPress form plugin', 'easy-form-builder');
		$fr = '<!-- efb copyRight -->';
		$s = '';

		if($this->package_type_efb==3){

			$f = substr(get_locale(), 0, 2);
			add_action('wp_head',  [$this, 'efb_output_schema_free_plus'], 20);
			$style = 'opacity:0.5 !important;text-decoration:none !important;text-decoration-line:none !important;border:none !important;outline:none !important;box-shadow:none !important;';

			$copyRight = '<div class="efb  d-md-block" id="copyrightEfb" style="font-size: 10px;' . $style . '">
							<h2 class="efb fs-8" style="' . $style . '">' . $s . '</h2>
							<aside class="efb-ai-context">
							  <h2 class="efb fs-8" style="' . $style . '">' .
								/* translators: %1$s: opening link tag to plugin page, %2$s: closing link tag, %3$s: opening link tag to developer website, %4$s: closing link tag */
								sprintf(
									esc_html__('Powered by %1$sEasy Form Builder%2$s, the %3$sbest form plugin for WordPress%4$s.', 'easy-form-builder'),
									'<a href="https://wordpress.org/plugins/easy-form-builder/" title="' . esc_attr__('Easy Form Builder WordPress Plugin', 'easy-form-builder') . '" rel="sponsored">',
									'</a>',
									'<a href="https://whitestudio.team" rel="sponsored">',
									'</a>'
								) .
							  '</h2>
							';

			if (strpos(get_locale(), 'fa') === 0 || strpos(get_locale(), 'ar') === 0) {
				$copyRight .= '<a href="https://easyformbuilder.ir" target="_blank" style="font-size: 8px;' . $style . '" rel="sponsored">فرم ساز آسان</a> ساخته شده بوسیله <a href="https://fa.wordpress.org/plugins/easy-form-builder/" target="_blank">افزونه فرم ساز رایگان وردپرس</a> ' . $fr;
			} else if (strpos(get_locale(), 'en') !== 0) {
				$copyRight .= '<a href="https://'.$f.'.wordpress.org/plugins/easy-form-builder/" target="_blank" style="font-size: 8px;' . $style . '" rel="sponsored">'.$efb.' '. $wp_text.'</a>' . $fr;
			}
			return $copyRight .'</aside></div>';
		}else if($this->package_type_efb==2){
			$lang = substr(get_locale(), 0, 2);
			if($lang == 'fa') add_action('wp_footer',  [$this, 'efb_output_schema_free_plus'], 20);
		}
		return '<!--efb-->';
	}

	function add_buttons_zone_efb($state, $id, $valj_efb, $efb_var, $formId) {

		/* A form saved by an older builder, or imported, can lack keys the
		 * current builder always writes: default them instead of reading null. */
		if (isset($valj_efb[0]) && is_object($valj_efb[0])) {
			$button_defaults = array(
				'button_Previous_icon' => '', 'button_Next_icon' => '', 'icon' => '',
				'button_Previous_text' => '', 'button_Next_text' => '', 'button_single_text' => '',
				'button_color' => 'btn-primary', 'icon_color' => '', 'el_height' => '',
			);
			foreach ($button_defaults as $button_key => $button_default) {
				if (!isset($valj_efb[0]->$button_key)) {
					$valj_efb[0]->$button_key = $button_default;
				}
			}
		}

		$dis = '';
		$t = array_search('stripe', array_column($valj_efb, 'type'));
		$t = $t === false ? array_search('persiaPay', array_column($valj_efb, 'type')) : $t;
		$t = $t === false ? array_search('paypal', array_column($valj_efb, 'type')) : $t;
		// PAY00: a field of a gateway add-on (any type in the payment registry) is a payment method too.
		if ($t === false && class_exists('\\Emsfb\\Payments\\Registry')) {
			foreach ($valj_efb as $k => $r) {
				if ($k > 0 && isset($r->type) && \Emsfb\Payments\Registry::is_field_type($r->type)) { $t = $k; break; }
			}
		}
		$t = $t !== false ? $valj_efb[$t]->step : 0;

		if ($valj_efb[0]->type == "payment" && $t == 0) {
			return  "<script>alert('".esc_html__('Easy Form Builder' , 'easy-form-builder'). ": " .esc_html__('This form requires a payment method. Please add one or change the form type.' , 'easy-form-builder')."');</script>";
		}

		$corner = property_exists($valj_efb[0], 'corner') ? $valj_efb[0]->corner : 'rounded-3';
		$btns_align = property_exists($valj_efb[0], 'btns_align') ? $valj_efb[0]->btns_align . ' mx-3' : 'justify-content-center';
		$icon_spacing_class = is_rtl() ? 'ms-2' : 'me-2';
		$icon_spacing_next_class = is_rtl() ? 'me-2' : 'ms-2';

		$prev_icon = strlen($valj_efb[0]->button_Previous_icon) > 3 && $valj_efb[0]->button_Previous_icon != 'bi-undefined' &&  $valj_efb[0]->button_Previous_icon!='bXXX' ? sprintf('<i class="efb %s %s %s %s" id="button_group_icon"></i>', $valj_efb[0]->button_Previous_icon, $icon_spacing_class, $valj_efb[0]->icon_color, $valj_efb[0]->el_height) : '';
		$next_icon = strlen($valj_efb[0]->button_Next_icon) > 3 && $valj_efb[0]->button_Next_icon != 'bi-undefined' && $valj_efb[0]->button_Next_icon!='bXXX' ? sprintf('<i class="efb %s %s %s %s" id="button_group_icon"></i>', $valj_efb[0]->button_Next_icon, $icon_spacing_next_class, $valj_efb[0]->icon_color, $valj_efb[0]->el_height) : '';
		$class_disabled = '';

		$s = sprintf(
			'<div class="efb d-flex %s %s text-center efb mx-3" id="f_btn_send_efb" data-tag="buttonNav" data-formid="%s">
				<a id="btn_send_efb" role="button" tabindex="0" class="efb text-decoration-none mx-0 btn p-2 %s %s %s %s %s efb-btn-lg btn_send_efb" data-formid="%s" data-currentstep="1" onclick="btn_navigate_handle_efb(\'%s\' ,\'%s\' ,\'%s\',this)">%s<span id="button_group_button_single_text" class="efb %s" >%s</span></a>
			</div>',
			$btns_align,
			$state == 0 ? 'd-block' : 'd-none',
			$formId,
			$dis,
			$valj_efb[0]->button_color,
			$corner,
			$valj_efb[0]->el_height,
			$class_disabled,
			$formId,
			$formId,
			$valj_efb[0]->type,
			'btn_send_efb',
			(strlen($valj_efb[0]->icon) > 3 && $valj_efb[0]->icon != 'bi-undefined' &&  $valj_efb[0]->icon!='bXXX' ? sprintf('<i class="efb %s %s %s %s" id="button_group_icon"></i>', $valj_efb[0]->icon, $icon_spacing_class, $valj_efb[0]->icon_color, $valj_efb[0]->el_height) : ''),
			$valj_efb[0]->el_text_color,
			$valj_efb[0]->button_single_text
		);

		$d = sprintf(
			'<div class="efb d-flex %s %s %s text-center efb" id="f_button_form_np" data-formid="%s" data-step="1">
				<a id="prev_efb"  data-formid="%s" data-currentstep="1" role="button" tabindex="0" class="efb text-decoration-none btn p-2  %s %s %s efb-btn-lg m-1 d-none prev_efb" onclick="btn_navigate_handle_efb(\'%s\' ,\'%s\' ,\'%s\',this)">%s<span id="button_group_Previous_button_text" class="efb %s">%s</span></a>
				<a id="next_efb"  data-formid="%s" data-currentstep="1" role="button" tabindex="0" class="efb text-decoration-none btn %s p-2 %s %s %s efb-btn-lg m-1 next_efb" onclick="btn_navigate_handle_efb(\'%s\' ,\'%s\' ,\'%s\',this)"><span id="button_group_Next_button_text" class="efb %s">%s</span>%s</a>
			</div>',
			$btns_align,
			$state == 1 ? 'd-block' : 'd-none',
			is_rtl() ? 'flex-row-reverse' : 'flex-row',
			$formId,
			$formId,
			$valj_efb[0]->button_color,
			$corner,
			$valj_efb[0]->el_height,
			$formId,
			$valj_efb[0]->type,
			'prev_efb',
			$prev_icon,
			$valj_efb[0]->el_text_color,
			$valj_efb[0]->button_Previous_text,
			$formId,
			$dis,
			$valj_efb[0]->button_color,
			$corner,
			$valj_efb[0]->el_height,
			$formId,
			$valj_efb[0]->type,
			'next_efb',
			$valj_efb[0]->el_text_color,
			$valj_efb[0]->button_Next_text,
			$next_icon
		);

		/* F11-E: "Save and continue later" under the buttons (draft_on, Pro; the routes check again). */
		$draft = '';
		if (!empty($valj_efb[0]->draft_on) && in_array($valj_efb[0]->draft_on, array(true, 1, '1'), true) && class_exists('\\Emsfb\\Drafts') && method_exists('\\Emsfb\\Drafts', 'button_html')
			&& (!class_exists('\\Emsfb\\Form_Gate') || \Emsfb\Form_Gate::plan_ok('drafts'))) {
			$draft = \Emsfb\Drafts::button_html(is_array($efb_var) && isset($efb_var['draftSave']) ? $efb_var['draftSave'] : 'Save and continue later', $formId, false);
		}
		return sprintf('<div class="efb footer-test p-1">%s%s</div>', $state == 0 ? $s : $d, $draft);
	}

	/**
	 * F06: markup of an advdate field (twin of EFBCalendar.markup in efb-calendar-ui.js): the typed input
	 * `${id}_` (labels and messages use it), the picker button, the hint and display lines and the hidden
	 * canonical input (.emsFormBuilder_v, data-type="advdate"). Everything else is drawn by the picker.
	 */
	private function advdate_markup_efb($vj, $id, $form_id, $classes, $disabled) {
		if (!class_exists('\Emsfb\Calendar')) return '';
		$cfg = \Emsfb\Calendar::public_config($vj, $form_id);
		$mode = isset($cfg['field']['date_mode']) && in_array($cfg['field']['date_mode'], array('range', 'multiple'), true) && $cfg['plan'] === 'pro' ? $cfg['field']['date_mode'] : 'single';
		$time = !empty($cfg['field']['time_enabled']) && in_array($cfg['field']['time_enabled'], array(1, '1', true), true) && $cfg['plan'] !== 'free' && $mode === 'single';
		$typed = isset($cfg['field']['input_mode']) && $cfg['field']['input_mode'] === 'typed';
		$req = isset($vj->required) && ($vj->required == 1 || $vj->required === true) ? ' aria-required="true"' : '';
		$dis = $disabled === 'disabled' ? ' disabled' : '';
		$corner = isset($vj->corner) ? $vj->corner : 'rounded-3';
		$cls = 'efb input-efb px-2 mb-0 w-100 ' . $classes . ' ' . (isset($vj->el_height) ? $vj->el_height : '') . ' ' . $corner . ' ' . (isset($vj->el_text_color) ? $vj->el_text_color : '') . ' efbField efb1';
		$t = function ($k, $d) { $x = function_exists('get_efbFunction') ? get_efbFunction()->text_efb(array($k)) : array(); return isset($x[$k]) && $x[$k] !== '' ? $x[$k] : $d; };
		$id_e = esc_attr($id);
		$icon = '<svg class="efb-cal-ico" width="18" height="18" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true" focusable="false"><path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5M1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4z"/><path d="M5 7h2v2H5zm4 0h2v2H9zm-4 3h2v2H5zm4 0h2v2H9z"/></svg>';
		$out = '<div class="efb efb-cal" id="' . $id_e . '_cal" data-fid="' . $id_e . '" data-formid="' . esc_attr($form_id) . '" data-efb-cal="' . esc_attr(wp_json_encode($cfg, JSON_UNESCAPED_UNICODE)) . '">';
		$out .= '<div class="efb efb-cal-control"' . ($mode === 'range' ? ' role="group" aria-labelledby="' . $id_e . '_labG"' : '') . '>';
		$out .= '<input type="text" id="' . $id_e . '_" class="' . esc_attr(trim($cls)) . ' efb-cal-input" data-id="' . $id_e . '-el" data-css="' . $id_e . '" dir="ltr" inputmode="numeric" autocomplete="off" aria-describedby="' . $id_e . '_-hint' . (!empty($vj->message) ? ' ' . $id_e . '-des' : '') . '"' . $req . $dis . ($mode === 'range' ? ' aria-label="' . esc_attr($t('cal_from', 'From')) . '"' : '') . '>';
		if ($mode === 'range') {
			$out .= '<span class="efb efb-cal-sep" aria-hidden="true">&ndash;</span><input type="text" id="' . $id_e . '_to" class="' . esc_attr(trim($cls)) . ' efb-cal-input-to" dir="ltr" inputmode="numeric" autocomplete="off" aria-label="' . esc_attr($t('cal_to', 'To')) . '" aria-describedby="' . $id_e . '_-hint"' . $req . $dis . '>';
		}
		if ($time) $out .= '<input type="text" id="' . $id_e . '_time" class="' . esc_attr(trim($cls)) . ' efb-cal-time" dir="ltr" inputmode="numeric" autocomplete="off" placeholder="HH:mm" aria-label="' . esc_attr($t('cal_time', 'Time')) . '"' . $dis . '>';
		if (!$typed) $out .= '<button type="button" class="efb efb-cal-btn" aria-haspopup="dialog" aria-expanded="false" aria-label="' . esc_attr($t($mode === 'range' ? 'cal_choose_range' : 'cal_choose_date', $mode === 'range' ? 'Choose dates' : 'Choose date')) . '"' . $dis . '>' . $icon . '</button>';
		$out .= '</div><small class="efb efb-cal-hint" id="' . $id_e . '_-hint"></small><div class="efb efb-cal-disp" id="' . $id_e . '_-disp"></div>';
		if ($mode === 'multiple') $out .= '<div class="efb efb-cal-chips" role="group" aria-label="' . esc_attr($t('cal_selected_dates', 'Selected dates')) . '"></div>';
		$out .= '<input type="hidden" class="emsFormBuilder_v efb-cal-value" id="' . $id_e . '_v" data-vid="' . $id_e . '" data-formid="' . esc_attr($form_id) . '" data-type="advdate" name="' . esc_attr(isset($vj->name) ? $vj->name : $id) . '" value=""' . $dis . '></div>';
		return $out;
	}

	public function addNewElement_efb($i, $rndm,$form_id,$texts,$pro) {
		$this->pro_efb =$pro;

		$nfield = ['html','stripe','paypal','persiapay','persiaPay','zarinPal','heading','link'];
		$element_Id = $this->valj_efb[$i]->id_;
		$elementId = $this->valj_efb[$i]->type;
		$currency = isset($this->valj_efb[0]->currency) ? $this->valj_efb[0]->currency : 'USD';
		$pos = array("", "", "", "");
		$indexVJ = $i;
		$position_l_efb = is_rtl() ? "end" : "start";
		$vj = $this->valj_efb[$indexVJ];
		$this->pro_notice_type_efb = $elementId;
		$this->pro_notice_texts_efb = is_array($texts) ? $texts : array();

		if(in_array($elementId, ["option","r_matrix"])) return;

		if (!in_array($elementId, ["html", "register", "login", "subscribe", "survey"])) {
			$pos = $this->get_position_col_el($vj, false);
		}
		  $mobile_pos = $this->get_position_col_mobile_el($vj);
            $this->mobile_pos = $mobile_pos;
		// The wrapper needs .row when either view puts the label beside the input;
		// with label-up both tiers are col-*-12 so .row keeps them stacked anyway.
		if ($pos[0] !== 'row' && $mobile_pos[0] === 'row') {
			$pos[0] = 'row';
		}
		$optn = '<!-- options -->';
		$pay = 'payefb';
		$iVJ = $indexVJ;
		$dataTag = 'text';
		$rndm = $this->valj_efb[$i]->id_;
		$desc=''; $label=''; $ttip=''; $div_f_id=''; $aire_describedby=''; $disabled=''; $ui=''; $elementSpecificFields=''; $js_s=''; $classes=''; $vtype=''; $elementSpecificFields = '';

		$style ='';
		if(!in_array($elementId,$nfield)){

		$desc = $this->generateDescription_efb($element_Id, $vj, $pos);
		$label = $this->generateLabel_efb($element_Id, $vj, $pos, $mobile_pos);
		$ttip = $this->generateTooltip_efb($element_Id);
		$div_f_id = $this->generateDivFId_efb($element_Id, $pos, $mobile_pos);
		$aire_describedby = !empty($vj->message) ? 'aria-describedby="' . $vj->id_ . '-des"' : "";
		$disabled = isset($vj->disabled) && $vj->disabled == 1 ? 'disabled' : '';
		$ui ='<!--efb ui-->';
		$dataTag = '<!--efb dataTag-->';
		$efbFunction = null;
		$classes = isset($vj->el_border_color) ?  sprintf('form-control %s', $vj->el_border_color) : 'form-control' ;
		$vtype = in_array($elementId ,['imgRadio','chlCheckBox','chlRadio','payMultiselect','paySelect','payRadio','payCheckbox','trmCheckbox']) ? strtolower(substr($elementId,3)) : $elementId;
		$elementSpecificFields = $this->generateElementSpecificFields_efb($vj->type, $element_Id, $vj, $pos, $desc, $label, $ttip, $div_f_id, $aire_describedby, $disabled,$form_id,$texts);
		$js_s='';
		if(isset($vj->classes)) $classes .=' '. str_replace(',', ' ', $vj->classes) ?? '';
		}

		if (gettype($elementSpecificFields) == 'array') {
			$ui = $elementSpecificFields['ui'];
			$dataTag = $elementSpecificFields['dataTag'];
		} else {
			$corner= isset($vj->corner) ? $vj->corner : 'rounded-3';
			switch ($vj->type) {

				case 'ranking':
					// F05-G: an ordered list of the field's choices (drag, buttons, keyboard; choices-efb.js). Free Plus.
					if (!class_exists('\\Emsfb\\Choices') || !\Emsfb\Choices::plan_ok('ranking')) {
						$ui = $this->public_pro_message_efb($texts['tfnapca'], isset($texts['thisFeatureAvailableFreePlusPro']) ? $texts['thisFeatureAvailableFreePlusPro'] : '');
						$dataTag = 'ranking';
						break;
					}
					$rk_f_efb = json_decode(wp_json_encode($vj), true);
					$rk_opts_efb = array();
					foreach ($this->valj_efb as $rk_o_efb) {
						if (isset($rk_o_efb->parent, $rk_o_efb->type) && $rk_o_efb->type === 'option' && (string) $rk_o_efb->parent === (string) $element_Id) $rk_opts_efb[] = $rk_o_efb;
					}
					$rk_id_efb = esc_attr($element_Id);
					$rk_items_efb = '';
					$rk_n_efb = 0;
					foreach ($rk_opts_efb as $rk_o_efb) {
						$rk_n_efb++;
						$rk_label_efb = isset($rk_o_efb->value) && is_scalar($rk_o_efb->value) ? (string) $rk_o_efb->value : '';
						$rk_items_efb .= '<li class="efb efb-rank-item" tabindex="0" data-op="' . esc_attr($rk_o_efb->id_) . '" data-label="' . esc_attr($rk_label_efb) . '" id="' . esc_attr($rk_o_efb->id_) . '-v">'
							. '<span class="efb efb-rank-grip bi-grip-vertical" aria-hidden="true"></span>'
							. '<span class="efb efb-rank-n" aria-hidden="true">' . esc_html(\Emsfb\Choices::digits((string) $rk_n_efb)) . '</span>'
							. '<span class="efb efb-rank-label ' . esc_attr(isset($vj->el_text_color) ? $vj->el_text_color : '') . '" dir="auto">' . esc_html($rk_label_efb) . '</span>'
							. '<span class="efb efb-rank-btns">'
							. '<button type="button" class="efb efb-rank-up" aria-label="' . esc_attr(str_replace('%s', $rk_label_efb, \Emsfb\Choices::t('rnkUp', $texts))) . '"' . ($disabled ? ' disabled' : '') . '><i class="efb bi-arrow-up" aria-hidden="true"></i></button>'
							. '<button type="button" class="efb efb-rank-down" aria-label="' . esc_attr(str_replace('%s', $rk_label_efb, \Emsfb\Choices::t('rnkDown', $texts))) . '"' . ($disabled ? ' disabled' : '') . '><i class="efb bi-arrow-down" aria-hidden="true"></i></button>'
							. '</span></li>';
					}
					$rk_touch_efb = !isset($rk_f_efb['rank_touch']) || $rk_f_efb['rank_touch'] !== '0';
					$ui = $label
						. '<div class="efb ' . $pos[3] . ' ' . $this->mobile_pos[3] . ' px-0 mx-0 ttEfb show" data-id="' . $rk_id_efb . '-el" id="' . $rk_id_efb . '-f">'
						. $ttip
						. '<div class="efb efb-rank ' . (($vj->required == 1 || $vj->required == true) ? 'required' : '') . '" id="' . $rk_id_efb . '_options" data-efb-rank="1" data-formid="' . esc_attr($form_id) . '" data-vid="' . $rk_id_efb . '"'
						. ' data-top="' . (int) \Emsfb\Choices::int($rk_f_efb, 'rank_top') . '" data-touch="' . ($rk_touch_efb ? '1' : '0') . '" data-random="' . (\Emsfb\Choices::on($rk_f_efb, 'op_random') ? '1' : '0') . '"'
						. ($disabled ? ' data-disabled="1"' : '') . ' role="group" aria-labelledby="' . $rk_id_efb . '_labG" aria-describedby="' . $rk_id_efb . '_rnkhelp">'
						. '<p class="efb efb-rank-help fs-7 text-muted" id="' . $rk_id_efb . '_rnkhelp" dir="auto">' . esc_html(\Emsfb\Choices::t('rnkHelp', $texts)) . '</p>'
						. '<ol class="efb efb-rank-list" role="list">' . $rk_items_efb . '</ol>'
						. ($rk_touch_efb ? '<button type="button" class="efb efb-rank-confirm fs-7" aria-pressed="false"' . ($disabled ? ' disabled' : '') . '>' . esc_html(\Emsfb\Choices::t('rnkConfirm', $texts)) . '</button>' : '')
						. '<div class="efb efb-rank-live" aria-live="polite" role="status"></div>'
						. '</div>'
						. '<div class="efb mb-3">' . $desc . '</div>';
					$dataTag = 'ranking';
					break;

				case 'hiddenField':
					// F08: a hidden field. Nothing shows; the value is a smart-tag template the browser resolves for
					// Conditional Logic (smart-tags-efb.js) and the server rebuilds on submit. A fixed text is the
					// value itself; a template with tags never reaches the HTML resolved (a cached page is shared).
					$hdn_tpl_efb = isset($vj->value) && is_scalar($vj->value) ? (string) $vj->value : '';
					$hdn_static_efb = class_exists('\\Emsfb\\Smart_Tags') ? \Emsfb\Smart_Tags::classify($hdn_tpl_efb) === 'static' : strpos($hdn_tpl_efb, '{') === false;
					$ui = sprintf(
						'<div class="efb d-none" id="%1$s-f"><input type="hidden" class="efb emsFormBuilder_v" data-type="hiddenField" data-vid="%1$s" data-formid="%2$s" id="%1$s_" name="%1$s" value="%3$s"%4$s>',
						esc_attr($element_Id),
						esc_attr($form_id),
						$hdn_static_efb ? esc_attr($hdn_tpl_efb) : '',
						$hdn_static_efb ? '' : ' data-st-hidden="1"'
					);
					$dataTag = 'hiddenField';
					break;

				case 'advdate':
					// F06: the advanced date field. Core only, no add-on and no jQuery: the picker
					// (public/assets/js/calendar) reads data-efb-cal; the server checks the value again.
					$ui = $label . $div_f_id . $ttip . $this->advdate_markup_efb($vj, $element_Id, $form_id, $classes, $disabled) . $desc;
					$dataTag = 'advdate';
					break;

				case 'pdate':
				case 'ardate':
					$isPdate = $elementId === 'pdate';
					$inputClass = $isPdate ? 'efb pdpF2 pdp-el' : 'efb hijri-picker';

					// A disabled date field is really disabled: the Jalali picker
					// opened on it and the server then refused the whole form.
					$readonlyAttr = $disabled === "disabled" ? ($elementId === 'ardate' ? 'readonly disabled' : 'disabled') : '';
					$valueAttr = !empty($vj->value) ? sprintf('value="%s"', esc_attr($vj->value)) : '';
					$requiredAttr = ($vj->required == 1 || $vj->required == true) ? 'required' : '';
					$ariaRequiredAttr = ($vj->required == 1) ? 'true' : 'false';

					$ui = sprintf(
						'%1$s %2$s %3$s <input type="text" class="%4$s input-efb px-2 mb-0 emsFormBuilder_v w-100 %5$s %6$s %7$s %8$s %9$s efbField efb1 %10$s" data-css="%11$s" data-id="%11$s-el" data-vid="%11$s" data-formid="%12$s" id="%11$s_" autocomplete="off" %13$s aria-required="%14$s" aria-label="%15$s" %16$s %17$s> %18$s',
						$label,
						$div_f_id,
						$ttip,
						$inputClass,
						$classes,
						$vj->el_height,
						$corner,
						$vj->el_text_color,
						$requiredAttr,
						'',
						$element_Id,
						$form_id,
						$valueAttr,
						$ariaRequiredAttr,
						$this->st_aria_efb($vj->name),
						$aire_describedby,
						$readonlyAttr,
						$desc
					);

					$dataTag = $elementId;
					$ui = $pro ? $ui : $this->public_pro_message_efb($texts['tfnapca'], isset($texts['thisFeatureAvailableFreePlusPro']) ? $texts['thisFeatureAvailableFreePlusPro'] : '');

					if($isPdate){
						if(!file_exists(EMSFB_PLUGIN_DIRECTORY."/vendor/persiadatepicker/persiandate.php")) {
							if($efbFunction === null)$efbFunction = get_efbFunction();
							/* Queued, not downloaded: this renderer also runs for visitors. */
							$queued = $efbFunction->queue_addon_recovery_efb(array('form_id' => $form_id, 'addon' => 'AdnPDP', 'source' => 'public_field_render'));
							return $efbFunction->addon_wait_message_public_efb(empty($queued['queued']) ? 0 : 25);
						}else{
							require_once(EMSFB_PLUGIN_DIRECTORY."/vendor/persiadatepicker/persiandate.php");
							$persianDatePicker = new persianDatePickerEFB() ;
						}
					}else{
						if(!file_exists(EMSFB_PLUGIN_DIRECTORY."/vendor/arabicdatepicker/arabicdate.php")) {
							if($efbFunction === null)$efbFunction = get_efbFunction();
							$queued = $efbFunction->queue_addon_recovery_efb(array('form_id' => $form_id, 'addon' => 'AdnADP', 'source' => 'public_field_render'));
							return $efbFunction->addon_wait_message_public_efb(empty($queued['queued']) ? 0 : 25);
						}else{
							require_once(EMSFB_PLUGIN_DIRECTORY."/vendor/arabicdatepicker/arabicdate.php");
							$arabicDatePicker = new arabicDatePickerEfb() ;
						}
					}

				break;

				case 'range':
					$classes = 'form-range';
					$classes .= str_replace(',', ' ', $vj->classes) ?? '';
					$maxlen = isset($vj->mlen) ? $vj->mlen : 100;
					$minlen = isset($vj->milen) ? $vj->milen : 0;
					$temp = $vj->value > 0 ? $vj->value : round(($maxlen + $minlen) / 2);
					$readonlyAttr = $disabled === "disabled" ? 'readonly' : '';
					$requiredAttr = ($vj->required == 1 || $vj->required == true) ? 'required' : '';
					$ariaRequiredAttr = ($vj->required == 1) ? 'true' : 'false';
					$valueAttr = $temp ? sprintf('value="%s"', $temp) : '';

					$ui = sprintf(
						'%1$s <div class="efb %2$s ' . $this->mobile_pos[3] . ' px-0 mx-0 ttEfb show" id="%3$s-f"> %4$s <div class="efb slider m-0 p-2 %5$s %6$s efb1 %7$s" data-css="%8$s" id="%3$s-range"> <input type="%9$s" class="efb input-efb px-2 mb-0 emsFormBuilder_v w-100 %10$s efbField" data-id="%3$s-el" data-vid="%3$s" data-formid="%8$s" id="%3$s_" oninput="fun_show_val_range_efb(\'%3$s\')" %11$s min="%12$s" max="%13$s" aria-required="%14$s" aria-label="%15$s" %16$s %17$s> <p id="%3$s_rv" class="efb mx-1 py-0 my-1 fs-6 text-darkb">%18$s</p> </div> %19$s',
						$label,
						$pos[3],
						$element_Id,
						$ttip,
						$vj->el_height,
						$vj->el_text_color,
						$classes,
						$form_id,
						'range',
						'range',
						$requiredAttr,
						$minlen,
						$maxlen,
						$ariaRequiredAttr,
						$this->st_aria_efb($vj->name),
						$aire_describedby,
						$readonlyAttr,
						$temp ?: 50,
						$desc
					);

					$dataTag = $elementId;
				break;
				case 'file':
					/* F07: accept (the server's own list), multiple, capture and the limits as data-*;
					 * the file list follows a field that takes several files. */
					$upload_attrs_efb = class_exists('\\Emsfb\\Uploads') ? \Emsfb\Uploads::input_attributes($vj) : '';
					$upload_list_efb = class_exists('\\Emsfb\\Uploads') && \Emsfb\Uploads::config($vj)['multi'] ? \Emsfb\Uploads::list_markup($vj->id_) : '';
					$ui = sprintf('
						%1$s
						%2$s
						%3$s
						<input type="%4$s" class="efb input-efb px-2 py-1 emsFormBuilder_v w-100 %5$s %6$s %7$s efbField efb1 %8$s %16$s" data-css="%9$s" data-vid="%9$s" data-id="%9$s-el" data-formid="%15$s" id="%9$s_" aria-required="%10$s" aria-label="%11$s" %12$s %13$s%17$s>
						%18$s%14$s',
						$label,
						$div_f_id,
						$ttip,
						$elementId,
						($vj->required == 1 ? 'required' : ''),
						$vj->el_height,
						$classes,
						str_replace(',', ' ', $vj->classes),
						$element_Id,
						($vj->required == 1 ? 'true' : 'false'),
						$this->st_aria_efb($vj->name),
						$aire_describedby,
						($disabled == "disabled" ? 'readonly' : ''),
						$desc,
						$form_id,
						$corner,
						$upload_attrs_efb,
						$upload_list_efb
					);
					$dataTag = $elementId;
					break;

				case "textarea":
					$minlen = isset($vj->milen) && $vj->milen > 0 ? 'minlength="' . $vj->milen . '"' : '';
					// F03: a textarea has a maximum too (characters; a word limit is never cut while typing).
					if (isset($vj->mlen) && (int) $vj->mlen > 0) {
						$minlen = trim($minlen . ' maxlength="' . (int) $vj->mlen . '"');
					}
					$fr_efb = $this->generateRuleExtras_efb($vj, $element_Id);
					if ($fr_efb['attrs'] !== '') $minlen = trim($minlen . ' ' . trim($fr_efb['attrs']));
					$ha_efb = $this->generateHelpAffix_efb($vj, $element_Id, $fr_efb['after'] . $desc, $ttip, false);
					$ta_desc_efb = $ha_efb['close'] . $ha_efb['desc'];
					$ta_ttip_efb = $ha_efb['before'] . $ha_efb['open'];
					$ta_described_efb = $this->mergeDescribedby_efb($aire_describedby, $fr_efb['describedby']);
					// F01-H: mobile keyboard choice; nothing is added unless the field sets one.
					$im_efb = $this->generateMaskAttributes_efb('textarea', $vj);
					if ($im_efb['attrs'] !== '') {
						$minlen = trim($minlen . ' ' . $im_efb['attrs']);
					}
					// F08: a default / placeholder with visitor tags is filled in the browser (data-st-value / data-st-ph);
					// the extra attribute rides inside the quoted value so an old textarea renders byte for byte as before.
					$ta_tpl_efb = $this->st_attr_efb(isset($vj->placeholder) ? $vj->placeholder : '', 'placeholder', 'data-st-ph');
					$ta_ph_efb = esc_attr($ta_tpl_efb[0]) . ($ta_tpl_efb[1] !== '' ? '"' . rtrim($ta_tpl_efb[1], '"') : '');
					$ta_def_efb = $this->st_attr_efb(isset($vj->value) ? $vj->value : '', 'default', 'data-st-value');
					$ta_val_efb = esc_attr($ta_def_efb[0]) . ($ta_def_efb[1] !== '' ? '"' . rtrim($ta_def_efb[1], '"') : '');

					$ui = sprintf('
						%1$s
						<div class="efb %2$s ' . $this->mobile_pos[3] . ' px-0 mx-0 ttEfb show" id="%3$s-f">
							%4$s
							<textarea id="%3$s_" placeholder="%5$s" class="efb px-2 input-efb emsFormBuilder_v form-control w-100 %6$s %7$s %8$s %9$s %10$s efbField efb1 %11$s" data-css="%3$s" data-vid="%3$s" data-id="%3$s-el"  data-formid="%20$s" value="%12$s" aria-required="%13$s" aria-label="%14$s" %15$s rows="5" %16$s %17$s>%18$s</textarea>
							%19$s
							',
							$label,
							$pos[3],
							$element_Id,
							$ta_ttip_efb,
							$ta_ph_efb, // F03 §18.4: was printed raw into the attribute; F08: tags in the browser (data-st-ph)
							($vj->required == 1 ? 'required' : ''),
							$vj->el_height,
							$corner,
							$vj->el_text_color,
							$classes,
							'',
							$ta_val_efb,
							($vj->required == 1 ? 'true' : 'false'),
							$this->st_aria_efb($vj->name),
							$ta_described_efb,
							$disabled,
							$minlen,
							esc_html($this->text_nr_efb($ta_def_efb[0], 0)),
							$ta_desc_efb,
							$form_id

					);
					$dataTag = "textarea";
					break;

				case "mobile":

					if($pro){
						$temp =  $this->create_intlTelInput_efb($element_Id, $vj, true, $corner,$form_id);
						 $js_s .= $temp[1];
						 $optn= $temp[0];
					}else{
						$optn=$this->public_pro_message_efb($texts['tfnapca'], isset($texts['thisFeatureAvailableFreePlusPro']) ? $texts['thisFeatureAvailableFreePlusPro'] : '');
					}
					wp_register_script('intlTelInput-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/intlTelInput.min-efb.js', array(), EMSFB_PLUGIN_VERSION, true);
					wp_enqueue_script('intlTelInput-js');
					wp_register_style('intlTelInput-css', EMSFB_PLUGIN_URL . 'includes/admin/assets/css/intlTelInput.min-efb.css',true, EMSFB_PLUGIN_VERSION . '.' . (int) @filemtime(EMSFB_PLUGIN_DIRECTORY . 'includes/admin/assets/css/intlTelInput.min-efb.css'));
					wp_enqueue_style('intlTelInput-css');
					$ui = sprintf('
						%s
						%s
						%s
						%s
						%s
						',
						$label,
						$div_f_id,
						$ttip,
						$optn,
						$desc
					);
					$style = $this->field_mobile_style_efb();
					$dataTag = "textarea";
				break;
				case 'dadfile':

					$el =$pro ? $this->dadfile_el_pro_efb(true, $element_Id, $vj,$form_id,$texts) : $this->public_pro_message_efb($texts['tfnapca'], isset($texts['thisFeatureAvailableFreePlusPro']) ? $texts['thisFeatureAvailableFreePlusPro'] : '');
					$ui = sprintf('
						%1$s
						<div class="efb %2$s ' . $this->mobile_pos[3] . ' px-0 mx-0 ttEfb show" id="%3$s-f">
							%4$s
							%5$s
							%6$s',
						$label,
						$pos[3],
						$element_Id,
						$desc,
						$ttip,
						$el
					);
					$dataTag = $elementId;
				break;
				case 'audio_recorder':
				case 'video_recorder':
				case 'screen_recorder':
					$el = $this->ui_recorder_efb($vj, $form_id, $texts, $disabled);
					$ui = sprintf('
						%1$s
						<div class="efb %2$s ' . $this->mobile_pos[3] . ' px-0 mx-0 ttEfb show" id="%3$s-f">
							%4$s
							%5$s
						</div>
						%6$s',
						$label,
						$pos[3],
						$element_Id,
						$ttip,
						$el,
						$desc
					);
					$ui = $pro == true ? $ui : $this->public_pro_message_efb($texts['tfnapca'], isset($texts['thisFeatureAvailableFreePlusPro']) ? $texts['thisFeatureAvailableFreePlusPro'] : '');
					$dataTag = $elementId;
				break;
				case 'checkbox':
				case 'radio':
				case 'payCheckbox':
				case 'payRadio':
				case 'chlCheckBox':
				case 'chlRadio':
				case 'imgRadio':
				case 'trmCheckbox':
						$dataTag = $elementId;
						$col = isset($vj->op_style) && intval($vj->op_style) != 1 ? sprintf('col-md-%d', 12 / intval($vj->op_style)) : '';
						$pay = in_array($elementId, ["radio", "checkbox", "chlRadio", "chlCheckBox", "imgRadio", "trmCheckbox"]) ? '' : $pay;
						$temp = $elementId == "imgRadio" ? 'col-md-4 mx-0 px-2' : '';

						$tp = strtolower($dataTag);
						$parent = $vj;
						$optns_obj =[];
						  array_filter($this->valj_efb, function($obj) use ($element_Id,&$optns_obj) {
							if (isset($obj->parent) && $obj->parent== $element_Id) {
								$optns_obj[] = $obj;
							}
						});
						$currency = isset($this->valj_efb[0]->currency) ? $this->valj_efb[0]->currency : 'USD';
						$optn = '';
						// F05: Other, Select all, kept places, used-up choices and the group role of the options box.
						$ch_parts_efb = class_exists('\\Emsfb\\Choices') && class_exists('\\Emsfb\\Stock')
							? \Emsfb\Choices::render_parts(\Emsfb\Stock::mark_rows(array(json_decode(wp_json_encode($this->valj_efb[0]), true), json_decode(wp_json_encode($vj), true)))[1], json_decode(wp_json_encode(array_values($optns_obj)), true), $form_id, $this->stock_left)
							: array('wrap' => '', 'before' => '', 'after' => '', 'opt' => array());
						foreach ($optns_obj as $i) {
							$checked = "";
							$ch_opt_efb = isset($ch_parts_efb['opt'][(string) $i->id_]) ? $ch_parts_efb['opt'][(string) $i->id_] : array('attr' => '', 'class' => '', 'disabled' => false, 'hidden' => false, 'suffix' => '');
							if ((strpos($tp, "radio") !== false || (strpos($tp, "select") !== false && strpos($tp, "multi") === false)) && ($parent->value == $i->id_ || (isset($i->id_old) && $parent->value == $i->id_old))) {
								$checked = "checked";
							} elseif ((strpos($tp, "multi") !== false || strpos($tp, "checkbox") !== false) && is_array($parent->value) && array_search($i->id_, $parent->value) !== false) {
								$checked = "checked";
							}

							$imageRadio = $elementId == "imgRadio" ? $this->fun_imgRadio_efb($i->id_, $i->src, $i,true, $texts) : '';
							$prc = isset($i->price) ? (float) $i->price : 0; // intval() showed 19.99 as 19.00
							if($pay!='') $prc = self::formatPrice_efb($prc, $currency );
							$optn .= sprintf(
								'<div class="efb form-check %s %s %s efb1 %s mt-1%s" data-css="%s" data-parent="%s" data-id="%s" data-formid="%s" id="%s-v"%s>
									<input class="efb form-check-input emsFormBuilder_v %s %s" data-tag="%s" data-type="%s" data-vid="%s" type="%s" name="%s" value="%s" id="%s" data-id="%s-id" data-formid="%s" data-op="%s" %s %s %s>
									%s
									%s
									%s
								</div>',
								$col,
								$elementId,
								$temp,
								str_replace(',', ' ', $vj->classes),
								$ch_opt_efb['class'] . ($ch_opt_efb['hidden'] ? ' d-none' : ''),
								$element_Id,
								esc_attr($i->parent),
								esc_attr($i->id_),
								$form_id,
								esc_attr($i->id_),
								$ch_opt_efb['attr'],
								$pay,
								$vj->el_text_size,
								$dataTag,
								$vtype,
								$element_Id,
								$vtype,
								esc_attr($i->parent),
								esc_attr($i->value),
								esc_attr($i->id_),
								esc_attr($i->id_),
								$form_id,
								esc_attr($i->id_),
								$ch_opt_efb['disabled'] && $checked === '' ? 'data-soldout="1" aria-disabled="true"' : '',
								$ch_opt_efb['disabled'] && $checked === '' ? 'disabled' : $disabled,
								$checked,
								($elementId != 'imgRadio' ? sprintf('<label class="efb %s %s %s %s hStyleOpEfb" id="%s_lab" for="%s">%s</label>', isset($vj->pholder_chl_value) ? 'col-8' : '', $vj->el_text_color, $vj->el_height, $vj->label_text_size, esc_attr($i->id_), esc_attr($i->id_), is_string($i->value) && strpos($i->value, '{') !== false ? $this->st_markup_efb($i->value, 'label') : $this->fun_get_links_from_string_Efb($i->value, true)) : $imageRadio) . $ch_opt_efb['suffix'],
								strpos($elementId, 'chl') !== false ? sprintf('<input type="text" class="efb %s %s checklist col-2 hStyleOpEfb emsFormBuilder_v border-d" data-id="%s" data-type="%s"data-formid="%s" data-vid="%s" id="%s_chl"  placeholder="%s" disabled>', $vj->el_text_color, $vj->el_height, $i->id_, $dataTag, $form_id, $i->id_, $i->id_, $vj->pholder_chl_value) : '',
								strlen($pay) > 2 ? sprintf('<span class="efb col fw-bold text-labelEfb h-d-efb hStyleOpEfb d-flex justify-content-end"><span id="%s-price" class="efb efb-crrncy">%s</span></span>', $i->id_, $prc):''
							);
						}

						$temp = $elementId == "imgRadio" ? "row justify-content-center" : "";
						$ui = sprintf(
							'<!-- checkbox -->
							%s
							<div class="efb %s ' . $this->mobile_pos[3] . ' px-0 mx-0 py-0 my-0 ttEfb show" data-id="%s-el" id="%s-f">
								%s
								<div class="efb %s %s %s efb1 %s" data-css="%s" %s id="%s_options"' . ($ch_parts_efb['wrap'] !== '' ? ' data-formid="' . (int) $form_id . '"' : '') . $ch_parts_efb['wrap'] . '>
									%s
								</div>
								<div class="efb mb-3">%s</div>
							<!-- end checkbox -->',
							$label,
							$pos[3],
							$element_Id,
							$element_Id,
							$ttip,
							($vj->required == 1 || $vj->required == true) ? 'required' : '',
							$col != '' ? 'row col-md-12' : '',
							$temp,
							str_replace(',', ' ', $vj->classes),
							$element_Id,
							$aire_describedby,
							$element_Id,
							$ch_parts_efb['before'] . $optn . $ch_parts_efb['after'],
							$desc
						);
				break;
				case 'esign':

					$ui = '
					' . $label . '
					' . $ttip . '
					' . ($pro == true ? $this->esign_el_pro_efb(true, $pos, $rndm, $vj, $desc,$form_id,$texts['updateUrbrowser']) : '<div class="efb ' . $pos[3] . ' ' . $this->mobile_pos[3] . '" id="' . $element_Id . '-f">' . $this->public_pro_message_efb($texts['tfnapca'], isset($texts['thisFeatureAvailableFreePlusPro']) ? $texts['thisFeatureAvailableFreePlusPro'] : ''));
					// Like esign_el_pro_efb(), the notice opens the field's "-f" wrapper,
					// which the shared closing tag after the switch closes. Without it that
					// tag closed the form itself and the following fields fell outside it.
					// The signature pad script exists only where the pad is drawn.

					if ($pro == true) $ui.= sprintf(
						"<script>
							document.addEventListener('DOMContentLoaded', function() {
								setTimeout(() => {
									fun_event_esign_efb('%s','%s',%s,%s);
								}, 1000);
							});
						</script>",
						$element_Id,
						$form_id,
						($disabled == "disabled" ? 'true' : 'false'),
						json_encode($vj, JSON_HEX_TAG | JSON_HEX_AMP)
					);
					$dataTag = $elementId;
				break;
				case 'maps':

					$lat = isset($vj->lat) ? $vj->lat : '0';
					$lng = isset($vj->lng) ? $vj->lng : '0';
					$zoom = isset($vj->zoom) ? $vj->zoom : '8';
					$formId = $form_id;
					$required = ($vj->required == 1 || $vj->required == true) ? 'required' : '';
					$el_height = isset($vj->el_height) ? $vj->el_height : '300px';
					$ariaDescribedBy = !empty($vj->message) ? 'aria-describedby="' . $element_Id . '-des"' : '';
					$message = $vj->message;

					$ui .= sprintf(
						"<div class='efb col-md-12' id='%1\$s-f' data-formid='%2\$s'>
							<label for='%1\$s_' class='efb form-label text-labelEfb'>
								<span>%3\$s</span>
								<span class='text-danger' role='none'>%4\$s</span>
							</label>
							<div class='efb maps-efb maps-os emsFormBuilder_v  %5\$s %12\$s' id='%1\$s-map' data-vid='%1\$s' data-formid='%2\$s' data-lat='%6\$s' data-lng='%7\$s' data-zoom='%8\$s' data-id='%1\$s-el' %9\$s></div>
							<input type='hidden' name='%1\$s-lat' id='%1\$s_lat' value='%6\$s' class='efb emsFormBuilder_v'  data-formid='%2\$s' data-type='maps' data-vid='%1\$s' %10\$s>
							<input type='hidden' name='%1\$s-lng' id='%1\$s_lng' value='%7\$s' class='efb emsFormBuilder_v'  data-formid='%2\$s' data-type='maps' data-vid='%1\$s' %10\$s>
							<small id='%1\$s-des' class='form-text text-muted'>%11\$s</small>
						</div>",
						$element_Id,
						$formId,
						$label,
						($required ? '*' : ''),
						$el_height,
						$lat,
						$lng,
						$zoom,
						$ariaDescribedBy,
						$required,
						$message,
						isset($vj->mark) && (int)$vj->mark>0 ? 'd-none' : ''
					);

					if(isset($vj->mark) && (int)$vj->mark>0 ){

						$ui .=sprintf(
							"<script>
								document.addEventListener('DOMContentLoaded', function() {
									setTimeout(() => {
										%s
									}, 1000);
								});
							</script>",
							$this->map_search_section_efb($element_Id,$vj,$form_id),

						);
					}else{
						$ui .= sprintf(
							"<script>
								function efbCreateMap_%s() {
									var map = L.map('%s-map').setView([%s, %s], %s);
									L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
										attribution: '&copy; <a href=\"https://www.openstreetmap.org/copyright\">OpenStreetMap</a> contributors'
									}).addTo(map);

									var marker = L.marker([%s, %s], { draggable: true }).addTo(map);
									marker.on('dragend', function(event) {
										var position = marker.getLatLng();
										document.getElementById('%s_lat').value = position.lat;
										document.getElementById('%s_lng').value = position.lng;
									});

									map.whenReady(function() { map.invalidateSize(); });
									setTimeout(function() { map.invalidateSize(); }, 100);
									setTimeout(function() { map.invalidateSize(); }, 500);
									setTimeout(function() { map.invalidateSize(); }, 1500);
								}

								document.addEventListener('DOMContentLoaded', function() {
									efbCreateMap_%s();
									<!--code new maps -->
								});
							</script>",
							$element_Id,
							$element_Id, $lat, $lng, $zoom,
							$lat, $lng,
							$element_Id, $element_Id,
							$element_Id,
						);
					}

					if($efbFunction === null)$efbFunction = get_efbFunction();
					$efbFunction->openstreet_map_required_efb(0);
					if ($pro!==true &&  $pro!==1) {
						$ui = $this->public_pro_message_efb($texts['tfnapca'], isset($texts['thisFeatureAvailableFreePlusPro']) ? $texts['thisFeatureAvailableFreePlusPro'] : '');
					}

					$style = $this->field_maps_style_efb();
					$dataTag = "maps";
					break;
				break;

				case 'rating':

					// Free on every plan since 4.3.1 (emsfb_free_element_types_efb()).
					$ui = $this->rating_el_pro_efb(true, $pos, $rndm, $vj, $desc, $form_id, $label, $ttip, $aire_describedby, $texts);
					$dataTag = $elementId;
				break;
                case 'select':
                case 'paySelect':

                    $ui = $this->generate_select_efb($elementId, $rndm, $vj, $pos, $form_id, $texts, true ,$desc,$label,$ttip,$aire_describedby);
                    $dataTag = $elementId;
                break;
				case 'conturyList':
				case 'country':

					// Free on every plan since 4.3.1 (emsfb_free_element_types_efb()).
					$ui = $this->generate_country_list_efb($rndm, $vj, $pos, $form_id, $texts ,$desc,$label,$ttip,$aire_describedby);

					$dataTag = $elementId;
				break;
				case 'stateProvince':
				case 'statePro':
					$ui = $pro == true ? $this->generate_state_province_efb($rndm, $vj, $pos, $form_id, $texts ,$desc,$label,$ttip,$aire_describedby) : $this->public_pro_message_efb($texts['tfnapca'], isset($texts['thisFeatureAvailableFreePlusPro']) ? $texts['thisFeatureAvailableFreePlusPro'] : '');
					$dataTag = $elementId;
				break;
				case 'city':
				case 'cityList':
					$ui = $pro == true ? $this->generate_city_list_efb($rndm, $vj, $pos, $form_id, $texts ,$desc,$label,$ttip,$aire_describedby) : $this->public_pro_message_efb($texts['tfnapca'], isset($texts['thisFeatureAvailableFreePlusPro']) ? $texts['thisFeatureAvailableFreePlusPro'] : '');
					$dataTag = $elementId;
				break;
				case 'multiselect':
				case 'payMultiselect':

					$ui = $this->generate_multiselect_efb($elementId, $rndm, $vj, $pos, $form_id, $texts,$desc,$label,$ttip,$aire_describedby) ;
					$dataTag = $elementId;
				break;

				case 'html':

					$ui = $pro == true ? $this->generate_html_code_efb($rndm, $vj, $pos, $form_id, $texts, true) : $this->public_pro_message_efb($texts['tfnapca'], isset($texts['thisFeatureAvailableFreePlusPro']) ? $texts['thisFeatureAvailableFreePlusPro'] : '');
					$dataTag = $elementId;
				break;
				case 'heading':

					// Free on every plan since 4.3.1 (emsfb_free_element_types_efb()).
					$ui = $this->generate_heading_efb($rndm, $pos, $vj, $form_id);
					$dataTag = $elementId;

				break;
				case 'link':

					$ui = $pro == true ? $this->generate_link_efb(true, $pos, $rndm, $vj, $form_id) : $this->public_pro_message_efb($texts['tfnapca'], isset($texts['thisFeatureAvailableFreePlusPro']) ? $texts['thisFeatureAvailableFreePlusPro'] : '');
					$dataTag = $elementId;
				break;
				case 'yesNo':
					if($pro!==true && $pro!==1){
						$ui =$this->public_pro_message_efb($texts['tfnapca'], isset($texts['thisFeatureAvailableFreePlusPro']) ? $texts['thisFeatureAvailableFreePlusPro'] : '');
						break;
					}

					$r = $this->generate_yes_no_efb(true, $pos, $rndm, $vj, $form_id);

					$ui = '' . $label . $ttip .  $r  .$desc ;
					$dataTag = $elementId;
				break;
				case 'pointr5':
					if($pro!==true && $pro!==1){
						$ui =$this->public_pro_message_efb($texts['tfnapca'], isset($texts['thisFeatureAvailableFreePlusPro']) ? $texts['thisFeatureAvailableFreePlusPro'] : '');
						break;
					}

					$r  = $this->pointer5_el_pro_efb(true, $vj, $form_id);

					$ui = "" . $label ."<div class='efb $pos[3] {$this->mobile_pos[3]} px-0 mx-0 ttEfb show'  id='$rndm-f'> ". $ttip .  $r  .$desc ;
					$dataTag = $elementId;
				break;
				case 'pointr10':

					if($pro!==true && $pro!==1){
						$ui =$this->public_pro_message_efb($texts['tfnapca'], isset($texts['thisFeatureAvailableFreePlusPro']) ? $texts['thisFeatureAvailableFreePlusPro'] : '');
						break;
					}
					$r  = $this->pointer10_el_pro_efb(true, $vj, $form_id);
					$ui = "" . $label ."<div class='efb $pos[3] {$this->mobile_pos[3]} px-0 mx-0 ttEfb show'  id='$rndm-f'> ". $ttip .  $r  .$desc ;
					$dataTag = $elementId;
				break;
				case 'smartcr':
					if($pro!==true && $pro!==1){
						$ui =$this->public_pro_message_efb($texts['tfnapca'], isset($texts['thisFeatureAvailableFreePlusPro']) ? $texts['thisFeatureAvailableFreePlusPro'] : '');
						break;
					}
					$r  = $this->smartcr_el_pro_efb(true, $vj, $form_id);
					$ui = "" . $label ."<div class='efb $pos[3] {$this->mobile_pos[3]} px-0 mx-0 ttEfb show'  id='$rndm-f'> ". $ttip .  $r  .$desc ;
					$dataTag = $elementId;
				break;
				case 'table_matrix':
					if($pro!==true && $pro!==1){
						$ui =$this->public_pro_message_efb($texts['tfnapca'], isset($texts['thisFeatureAvailableFreePlusPro']) ? $texts['thisFeatureAvailableFreePlusPro'] : '');
						break;
					}

					$ui  = $this->table_matrix_el_pro_efb($elementId, $vj, $rndm, $position_l_efb, true, $aire_describedby, $label, $ttip, $desc,$form_id,$pos);
					$dataTag = $elementId;

				break;
				case 'prcfld':
					if($pro!==true && $pro!==1){
						$ui =$this->public_pro_message_efb($texts['tfnapca'], isset($texts['thisFeatureAvailableFreePlusPro']) ? $texts['thisFeatureAvailableFreePlusPro'] : '');
						break;
					}
					$maxlen = (property_exists($vj, 'mlen') && $vj->mlen > 0) ? 'maxlength="' . $vj->mlen . '"' : '';

					$minlen = (property_exists($vj, 'milen') && $vj->milen > 0) ? 'minlength="' . $vj->milen . '"' : '';

					$dataTag = (!property_exists($this->valj_efb[0], 'currency')) ? 'usd' : $this->valj_efb[0]->currency;

					$classes = self::get_currency_details_efb($dataTag);

					$dataTagHtml = '<span class="efb input-group-text crrncy-clss">' . $classes['s'] . '</span>';

					$classes = 'form-control ' . $vj->el_border_color;

					$ui = sprintf(
						'%s
						<div class="efb %s ' . $this->mobile_pos[3] . ' px-0 mx-0 ttEfb show" id="%s-f" data-formId="%s">
							%s
							<div class="efb input-group m-0 p-0">
								%s
								<input type="number" min="0" class="efb input-efb px-2 mb-0 payefb emsFormBuilder_v %s %s %s %s %s efbField efb1 %s" data-id="%s-el" data-vid="%s" data-css="%s" id="%s_" placeholder="%s" data-formid="%s" %s %s %s %s %s %s>
								%s
							</div>
							%s',
						$label,
						$pos[3],
						$rndm,
						$form_id,
						$ttip,
						is_rtl() ? '' : $dataTagHtml,
						$classes,
						$vj->el_height,
						$corner,
						$vj->el_text_color,
						($vj->required == 1 || $vj->required == true) ? 'required' : '',
						str_replace(',', ' ', $vj->classes),
						$rndm,
						$rndm,
						$rndm,
						$rndm,
						htmlspecialchars($vj->placeholder),
						$form_id,
						($vj->value && strlen($vj->value) > 0) ? 'value="' . htmlspecialchars($vj->value) . '"' : '',
						$aire_describedby,

						$maxlen,
						$minlen,
						'',
						$disabled == 'disabled' ? 'readonly' : '',
						is_rtl() ? $dataTagHtml : '',
						$desc
					);

					$dataTag = $elementId;
				break;
				case 'ttlprc':
					if($pro!==true && $pro!==1){
						$ui =$this->public_pro_message_efb($texts['tfnapca'], isset($texts['thisFeatureAvailableFreePlusPro']) ? $texts['thisFeatureAvailableFreePlusPro'] : '');
						break;
					}

					$currency = isset($this->valj_efb[0]->currency) ? $this->valj_efb[0]->currency : 'USD';
					$r  = $this->totalprice_el_pro_efb($rndm, $vj ,$currency,$form_id);
					$class = isset($vj->classes) ? $vj->classes : '';

					$ui = sprintf(
						'%s<div class="efb %s ' . $this->mobile_pos[3] . ' pt-2 pb-1 px-0 mx-0 ttEfb show %s" id="%s-f">%s%s</div>',
						$label,
						$pos[3],
						$class,
						$rndm,
						$r,
						$desc
					);
				break;
				case 'stripe':
					if($pro!==true && $pro!==1){
						$ui =$this->public_pro_message_efb($texts['tfnapca'], isset($texts['thisFeatureAvailableFreePlusPro']) ? $texts['thisFeatureAvailableFreePlusPro'] : '');
						break;
					}
					$sub = $texts['onetime'];
					$cl = 'one';
					if (isset($this->valj_efb[0]->paymentmethod) && $this->valj_efb[0]->paymentmethod != 'charge') {
						$n = $this->valj_efb[0]->paymentmethod.'ly';
						$sub = isset($texts[$n]) ? $texts[$n] : $texts['onetime'];
						$cl = $this->valj_efb[0]->paymentmethod;
					}

					$ui = $this->add_ui_stripe_efb($rndm , $cl, $sub,$form_id,$texts);

					$dataTag = $elementId;

				break;
				case 'paypal':
					if($pro!==true && $pro!==1){
						$ui =$this->public_pro_message_efb($texts['tfnapca'], isset($texts['thisFeatureAvailableFreePlusPro']) ? $texts['thisFeatureAvailableFreePlusPro'] : '');
						break;
					}
					$sub = $texts['onetime'];
					$cl = 'one';
					if (isset($this->valj_efb[0]->paymentmethod) && $this->valj_efb[0]->paymentmethod != 'charge') {
						$n = $this->valj_efb[0]->paymentmethod.'ly';
						$sub = isset($texts[$n]) ? $texts[$n] : $texts['onetime'];
						$cl = $this->valj_efb[0]->paymentmethod;
					}

					$ui = $this->add_ui_paypal_efb($rndm , $form_id,$texts ,'USD' , $cl , $sub);
					$dataTag = $elementId;
				break;
				case "persiaPay":
				case "zarinPal":

					if($pro!==true && $pro!==1){
						$ui =$this->public_pro_message_efb($texts['tfnapca'], isset($texts['thisFeatureAvailableFreePlusPro']) ? $texts['thisFeatureAvailableFreePlusPro'] : '');
						break;
					}

					if (has_action('efb_enqueue_persia')){
						do_action('efb_enqueue_persia');
					}

					$ui = $this->add_ui_zp_efb($rndm , $form_id,$texts);
					$dataTag = $elementId;

				break;
				default:
					// PAY00: a framework gateway field (registered by a gateway add-on). The markup is
					// Payments\Field::html(), the twin of efbPayCoreEfb.html() (builder and preview).
					if (class_exists('\\Emsfb\\Payments\\Registry') && \Emsfb\Payments\Registry::is_field_type($elementId)) {
						$ui = '<div class="efb ' . $pos[3] . ' ' . $this->mobile_pos[3] . ' px-0 mx-0" id="' . esc_attr($element_Id) . '-f">'
							. \Emsfb\Payments\Frontend::field_markup($vj, $this->valj_efb[0], $form_id, $this->valj_efb);
						$dataTag = $elementId;
					} elseif (class_exists('\\Emsfb\\Payments\\Readiness') && \Emsfb\Payments\Readiness::is_payment_type($elementId)) {
						// PAY00 readiness: the payment field of a gateway add-on that is not active. Visitors read
						// that online payment is not available (Send stays off, the server refuses); the owner sees why.
						$ui = '<div class="efb ' . $pos[3] . ' ' . $this->mobile_pos[3] . ' px-0 mx-0" id="' . esc_attr($element_Id) . '-f">'
							. \Emsfb\Payments\Readiness::public_notice(\Emsfb\Payments\Readiness::of_form($this->valj_efb, (int) $form_id), (string) $vj->id_, $form_id);
						$dataTag = $elementId;
					}
				break;

			}
		}

		if ($vj->type != "form" && $dataTag != "step" && $vj->type != 'option') {
			$hidden = (isset($vj->hidden) && $vj->hidden == 1) || $elementId === 'hiddenField' ? 'd-none' : '';
			$tagId = in_array($elementId, ["firstName", "lastName", "address", "address_line", "postalcode"]) ? 'text' : $elementId;
			$tagT = in_array($elementId, ["esign", "yesNo", "rating"]) ? '' : 'def';
			$stepNo = (int)$vj->step - 1;
			// Field types whose markup opens no inner "-f" wrapper: they get one closing tag below.
			$single_close_efb = ['option', 'html', 'stripe', 'heading', 'link','conturyList','country','stateProvince','statePro','city','cityList','maps','ttlprc','audio_recorder','video_recorder','screen_recorder'];
			// Every other type gets two, the first for its "-f" wrapper. On a plan
			// without the field, its markup is only the plan notice, so that first
			// tag closed the form container and the following fields fell outside it.
			if (!in_array($elementId, $single_close_efb, true) && $this->last_pro_notice_efb !== null && $ui === $this->last_pro_notice_efb) {
				$ui = '<div class="efb ' . $pos[3] . ' ' . $this->mobile_pos[3] . '" id="' . $element_Id . '-f">' . $ui;
			}
			$newElement = sprintf(
				'<!--startTag %1$s--><div class="efb my-1 mx-0 %1$s %2$s %3$s %4$s ttEfb %5$s %6$s %12$s efbField %7$s" data-step="%8$s" data-amount="%9$s" data-id="%10$s-id" id="%10$s" data-tag="%11$s">',
				$elementId,
				$tagT,
				$hidden,
				$disabled,
				$pos[0],
				$pos[1],
				$dataTag == "step" ? 'step' : '',
				$stepNo,
				$vj->amount,
				$element_Id,
				$elementId,
				$mobile_pos[1]
			);

			if ($elementId != 'option') {
				$newElement .= $ui;
			}

			if (!in_array($elementId, $single_close_efb)) {
				$newElement .= '<!--test2--></div></div>';
			} else {
				$newElement .= '<!--test--></div>';
			}

			$newElement .= sprintf('<!--endTag %s-->', $elementId);

			return [$newElement ,$style ,$js_s];
		}
	}

	public function show_user_profile_emsFormBuilder($text_logout, $formId) {

		$user_login = wp_get_current_user();
		$display_name = esc_html($user_login->display_name);
		$user_image = get_avatar_url($user_login->ID);
		$logout_text = esc_html($text_logout);

		$user_id =  $user_login->user_login ?? $user_login->user_email;

		return sprintf(
			'<div class="efb mt-5" data-formId="%s" id="body_efb_%s">
				<div class="efb card-block text-center text-dark">
					<div class="efb mb-3 d-flex justify-content-center">
						<img src="%s" class="efb userProfileImageEFB" alt="%s">
					</div>
					<h6 class="efb fs-5 mb-1 d-flex justify-content-center text-dark">%s</h6>
					<p class="efb fs-6">%s</p>
					<button type="button" class="efb btn fs-5 btn-lg btn-danger efb mt-1" onclick="fun_logout_efb(%s)"  data-formId="%s">%s</button>
				</div>
			</div>',
			esc_attr($formId),
			esc_attr($formId),
			esc_url($user_image),
			esc_attr($display_name),
			esc_html($display_name),
			esc_html($user_id),
			esc_js($formId),
			esc_attr($formId),
			esc_html($logout_text)

		);
	}

	public function addStyleColorBodyEfb($t, $c, $type, $id, $vj) {

		$ttype = ($id == -1) ? $type : $vj->type;

		$v = ".$t { color: $c !important; }";
		$tag = "";

		switch ($ttype) {
			case 'textarea':
				$tag = "textarea";
				break;
			case 'text':
			case 'password':
			case 'email':
			case 'number':
			case 'image':
			case 'date':
			case 'tel':
			case 'url':
			case 'range':
			case 'color':
			case 'checkbox':
			case 'radiobutton':
			case 'prcfld':
				$tag = "input";
				break;
			case 'btn':
				$tag = "btn";
				break;
			default:
				$tag = "";
				break;
		}

		if ($c[0] != "#") $c = "#$c";

		return $this->efb_add_custom_color($t, $c, $v, $type);
	}

	public function efb_add_custom_color($t, $c, $v, $type) {
		$n = '';
		if ($c[0] != "#") $c = "#$c";

		if ($type == "text") {
			$n = "{$type}-$t";
			$v = ".$n { color: $c !important; }";
		} elseif ($type == "icon") {
			$n = "text-$t";
			$v = ".$n { color: $c !important; }";
		} elseif ($type == "border") {
			$n = "{$type}-$t";
			$v = ".$n { border-color: $c !important; }";
		} elseif ($type == "bg") {
			$n = "{$type}-$t";
			$v = ".$n { background-color: $c !important; }";
		} elseif ($type == "btn") {
			$n = "{$type}-$t";
			$v = ".$n { background-color: $c !important; }";
		}

		return $v;
	}

	/**
	 * Generate CSS for checked color of radio/checkbox elements
	 * @param string $parentId The parent element ID
	 * @param string $color The hex color value
	 * @return string CSS rules
	 */
	public function generateCheckedColorCss($parentId, $color) {
		if (empty($color)) return '';

		$color = $color[0] !== '#' ? '#' . $color : $color;
		$parentId = esc_attr($parentId);

		// High specificity selectors for both radio and checkbox
		// Using attribute selectors for IDs to handle IDs starting with numbers
		$css = sprintf(
			'[data-css="%1$s"] .efb.form-check-input:checked, ' .
			'[data-css="%1$s"] .efb.form-check-input:checked[type=checkbox], ' .
			'[data-css="%1$s"] .efb.form-check-input:checked[type=radio], ' .
			'[data-parent="%1$s"] .efb.form-check-input:checked, ' .
			'[data-parent="%1$s"] .efb.form-check-input:checked[type=checkbox], ' .
			'[data-parent="%1$s"] .efb.form-check-input:checked[type=radio], ' .
			'[id="%1$s_options"] .efb.form-check-input:checked, ' .
			'[id="%1$s_options"] .efb.form-check-input:checked[type=checkbox], ' .
			'[id="%1$s_options"] .efb.form-check-input:checked[type=radio], ' .
			'[data-vid="%1$s"] .efb.form-check-input:checked, ' .
			'[data-vid="%1$s"] .efb.form-check-input:checked[type=checkbox], ' .
			'[data-vid="%1$s"] .efb.form-check-input:checked[type=radio], ' .
			'.efb.form-check-input[data-vid="%1$s"]:checked, ' .
			'.efb.form-check-input[data-vid="%1$s"]:checked[type=checkbox], ' .
			'.efb.form-check-input[data-vid="%1$s"]:checked[type=radio] ' .
			'{ background-color: %2$s !important; border-color: %2$s !important; }',
			$parentId,
			esc_attr($color)
		);

		return $css;
	}

	/**
	 * Generate CSS for range slider thumb color
	 * @param string $parentId The parent element ID
	 * @param string $color The hex color value
	 * @return string CSS rules
	 */
	public function generateRangeThumbColorCss($parentId, $color) {
		if (empty($color)) return '';

		$color = $color[0] !== '#' ? '#' . $color : $color;
		$parentId = esc_attr($parentId);

		$css = sprintf(
			'input[type="range"][data-vid="%1$s"]::-webkit-slider-thumb, ' .
			'[data-vid="%1$s"] input[type="range"]::-webkit-slider-thumb, ' .
			'[id="%1$s-range"] input[type="range"]::-webkit-slider-thumb, ' .
			'[data-css="%1$s"] input[type="range"]::-webkit-slider-thumb, ' .
			'[data-css="%1$s"] .efb.form-range::-webkit-slider-thumb { background-color: %2$s !important; } ' .
			'input[type="range"][data-vid="%1$s"]::-moz-range-thumb, ' .
			'[data-vid="%1$s"] input[type="range"]::-moz-range-thumb, ' .
			'[id="%1$s-range"] input[type="range"]::-moz-range-thumb, ' .
			'[data-css="%1$s"] input[type="range"]::-moz-range-thumb, ' .
			'[data-css="%1$s"] .efb.form-range::-moz-range-thumb { background-color: %2$s !important; } ' .
			'input[type="range"][data-vid="%1$s"]::-ms-thumb, ' .
			'[data-vid="%1$s"] input[type="range"]::-ms-thumb, ' .
			'[id="%1$s-range"] input[type="range"]::-ms-thumb, ' .
			'[data-css="%1$s"] input[type="range"]::-ms-thumb, ' .
			'[data-css="%1$s"] .efb.form-range::-ms-thumb { background-color: %2$s !important; }',
			$parentId,
			esc_attr($color)
		);

		return $css;
	}

	/**
	 * Generate CSS for range value text color
	 * @param string $parentId The parent element ID
	 * @param string $color The hex color value
	 * @return string CSS rules
	 */
	public function generateRangeValueColorCss($parentId, $color) {
		if (empty($color)) return '';

		$color = $color[0] !== '#' ? '#' . $color : $color;
		$parentId = esc_attr($parentId);

		$css = sprintf(
			'[id="%1$s_rv"], #%1$s_rv { color: %2$s !important; }',
			$parentId,
			esc_attr($color)
		);

		return $css;
	}

	/**
	 * Generate CSS for switch on color
	 * @param string $parentId The parent element ID
	 * @param string $color The hex color value
	 * @return string CSS rules
	 */
	public function generateSwitchOnColorCss($parentId, $color) {
		if (empty($color)) return '';

		$color = $color[0] !== '#' ? '#' . $color : $color;
		$parentId = esc_attr($parentId);

		$css = sprintf(
			'[id="%1$s"] .efb.btn-toggle.active, #%1$s .efb.btn-toggle.active { background-color: %2$s !important; border-color: %2$s !important; background-image: none !important; }',
			$parentId,
			esc_attr($color)
		);

		return $css;
	}

	/**
	 * Generate CSS for switch handle color
	 * @param string $parentId The parent element ID
	 * @param string $color The hex color value
	 * @return string CSS rules
	 */
	public function generateSwitchHandleColorCss($parentId, $color) {
		if (empty($color)) return '';

		$color = $color[0] !== '#' ? '#' . $color : $color;
		$parentId = esc_attr($parentId);

		$css = sprintf(
			'[id="%1$s"] .efb.btn-toggle > .handle, #%1$s .efb.btn-toggle > .handle { background-color: %2$s !important; }',
			$parentId,
			esc_attr($color)
		);

		return $css;
	}

	/**
	 * Generate CSS for switch off color
	 * @param string $parentId The parent element ID
	 * @param string $color The hex color value
	 * @return string CSS rules
	 */
	public function generateSwitchOffColorCss($parentId, $color) {
		if (empty($color)) return '';

		$color = $color[0] !== '#' ? '#' . $color : $color;
		$parentId = esc_attr($parentId);

		$css = sprintf(
			'[id="%1$s"] .efb.btn-toggle:not(.active), #%1$s .efb.btn-toggle:not(.active) { background-color: %2$s !important; border-color: %2$s !important; }',
			$parentId,
			esc_attr($color)
		);

		return $css;
	}

	public function fun_addStyle_customize_efb($val, $key, $vj) {


		if (strpos($val, 'colorDEfb') !== false) {
			$type = "";
			$color = "";

			switch ($key) {
				case 'button_color':
					$type = "btn";
					$color = isset($vj->style_btn_color) ? $vj->style_btn_color : '';
					break;
				case 'icon_color':
					$type = "icon";
					$color = isset($vj->style_icon_color) ? $vj->style_icon_color : '';
					break;
				case 'el_text_color':
					$type = "text";
					$color = isset($vj->style_el_text_color) ? $vj->style_el_text_color : '';
					break;
				case 'label_text_color':
					$type = "text";
					$color = isset($vj->style_label_color) ? $vj->style_label_color : '';
					break;
				case 'message_text_color':
					$type = "text";
					$color = isset($vj->style_message_text_color) ? $vj->style_message_text_color : '';
					break;
				case 'el_border_color':
					$type = "border";
					$color = isset($vj->style_border_color) ? $vj->style_border_color : '';
					break;
				case 'clrdoneTitleEfb':
					$type = "text";
					$color = isset($vj->clrdoneTitleEfb) ? substr($vj->clrdoneTitleEfb, -7) : '';
					break;
				case 'clrdoniconEfb':
					$type = "text";
					$color = isset($vj->clrdoniconEfb) ? substr($vj->clrdoniconEfb, -7) : '';
					break;
				case 'clrdoneMessageEfb':
					$type = "text";
					$color = isset($vj->clrdoneMessageEfb) ? substr($vj->clrdoneMessageEfb, -7) : '';
					break;
				case 'prg_bar_color':
					$type = "btn";
					$color = isset($vj->prg_bar_color) ? substr($vj->prg_bar_color, -7) : '';
					break;
			}

			if ($color != "") {
				return $this->addStyleColorBodyEfb("colorDEfb-" . substr($color, 1), substr($color, -6), $type, -1, $vj);
			}
		}else{
			// Handle checked_color for radio/checkbox elements
			if ($key === 'checked_color' && !empty($val) && isset($vj->id_)) {
				return $this->generateCheckedColorCss($vj->id_, $val);
			}else if ($key === 'range_thumb_color' && !empty($val) && isset($vj->id_)) {
				return $this->generateRangeThumbColorCss($vj->id_, $val);
			}else if ($key === 'range_value_color' && !empty($val) && isset($vj->id_)) {
				return $this->generateRangeValueColorCss($vj->id_, $val);
			}else if ($key === 'switch_on_color' && !empty($val) && isset($vj->id_)) {
				return $this->generateSwitchOnColorCss($vj->id_, $val);
			}else if ($key === 'switch_handle_color' && !empty($val) && isset($vj->id_)) {
				return $this->generateSwitchHandleColorCss($vj->id_, $val);
			}else if ($key === 'switch_off_color' && !empty($val) && isset($vj->id_)) {
				return $this->generateSwitchOffColorCss($vj->id_, $val);
			}
		}
	}

	public function map_search_section_efb($element_Id, $vj, $form_id) {

		 return sprintf('efbCreateMap("%1$s", %2$s, %3$s);',
			$element_Id,
			json_encode($vj, JSON_HEX_TAG | JSON_HEX_AMP),
			'false'
		);

	}

public function check_error_console_efb(){

	$t = [
		'title'       => esc_html__('Error Monitor', 'easy-form-builder'),
		'plugin'      => esc_html__('Plugin', 'easy-form-builder'),
		'theme'       => esc_html__('Theme', 'easy-form-builder'),
		'wpCore'      => esc_html__('WordPress Core', 'easy-form-builder'),
		'external'    => esc_html__('External', 'easy-form-builder'),
		'unknown'     => esc_html__('Unknown', 'easy-form-builder'),
		'notice'      => esc_html__('Notice', 'easy-form-builder'),
		'line'        => esc_html__('Line', 'easy-form-builder'),
		'file'        => esc_html__('File', 'easy-form-builder'),
		'dismiss'     => esc_html__('Dismiss', 'easy-form-builder'),
		'dismissAll'  => esc_html__('Dismiss All', 'easy-form-builder'),
		/* translators: %s: item label */
		'dismissItem' => esc_html__('Dismiss %s', 'easy-form-builder'),
		'noErrors'    => esc_html__('No errors detected', 'easy-form-builder'),
		'warning'     => esc_html__('These errors may interfere with forms built using Easy Form Builder.', 'easy-form-builder'),
		'adminOnly'   => esc_html__('This panel is only visible to site administrators.', 'easy-form-builder'),
		'easyformbuilder' => esc_html__('Easy Form Builder', 'easy-form-builder'),
		'warningBadge' => esc_html__('Warning', 'easy-form-builder'),
		'jqueryMissing' => esc_html__('jQuery Dependency Issue', 'easy-form-builder'),
		'jqueryMessage' => esc_html__('jQuery is not properly loaded or available. AJAX and interactive form features may not work correctly.', 'easy-form-builder'),
		/* A failed submit, explained for the site owner: what happened, why, the
		 * one thing to do, the visitor's own message and the details for support. */
		'badge'        => esc_html__('Why did this fail?', 'easy-form-builder'),
		'onlyYou'      => esc_html__('Only you can see this', 'easy-form-builder'),
		'adminOnlyNow' => esc_html__('You see this panel because you are signed in as an administrator. Visitors only see the message in the form.', 'easy-form-builder'),
		'formSubmit'   => esc_html__('Form submission', 'easy-form-builder'),
		'whatToDo'     => esc_html__('What to do', 'easy-form-builder'),
		'visitorSaw'   => esc_html__('What your visitor saw', 'easy-form-builder'),
		'techDetails'  => esc_html__('Technical details for support', 'easy-form-builder'),
		'copyDetails'  => esc_html__('Copy details', 'easy-form-builder'),
		'copiedDone'   => esc_html__('Copied', 'easy-form-builder'),
		'messagesUrl'  => esc_url(admin_url('admin.php?page=Emsfb')),
		'f_expired_title'   => esc_html__('The form had expired in the visitor\'s browser', 'easy-form-builder'),
		'f_expired_why'     => esc_html__('The page was open for a long time, so its security code expired. This is normal and is not caused by a cache plugin.', 'easy-form-builder'),
		'f_do_refresh'      => esc_html__('No action needed. The visitor was asked to refresh the page and send the form again.', 'easy-form-builder'),
		'f_do_send_again'   => esc_html__('No action needed. The visitor was asked to send the form again.', 'easy-form-builder'),
		/* translators: %s: name of a page cache plugin, e.g. LiteSpeed Cache */
		'f_cache_title'     => esc_html__('This page was probably shown from an old cached copy', 'easy-form-builder'),
		/* translators: %s: name of a page cache plugin */
		'f_cache_why'       => esc_html__('%s seems to have served a stored copy of this page whose security code had expired, so the form was refused.', 'easy-form-builder'),
		/* translators: %s: name of a page cache plugin */
		'f_cache_do'        => esc_html__('In %s, clear the cache (Purge All) and reload this page. If it happens again, exclude this page from caching.', 'easy-form-builder'),
		'f_login_title'     => esc_html__('The visitor signed in or out in another tab', 'easy-form-builder'),
		'f_login_why'       => esc_html__('The form was opened under a different login, so it was refused for safety. This is normal and is not caused by a cache plugin.', 'easy-form-builder'),
		'f_refused_title'   => esc_html__('Easy Form Builder refused this submission', 'easy-form-builder'),
		'f_refused_why'     => esc_html__('The reason is in the message your visitor saw, below.', 'easy-form-builder'),
		'f_refused_do'      => esc_html__('If real visitors get this message, check the setting it names, for example Human Shield or the form\'s own rules.', 'easy-form-builder'),
		'f_storage_title'   => esc_html__('The entry could not be saved', 'easy-form-builder'),
		'f_storage_why'     => esc_html__('Your server could not save the entry. Nothing was saved, and the visitor was told so.', 'easy-form-builder'),
		'f_storage_do'      => esc_html__('Ask your hosting company to check the database (disk space or a damaged table), then send a test form.', 'easy-form-builder'),
		'f_closed_title'    => esc_html__('Your website does not accept form requests from visitors', 'easy-form-builder'),
		/* translators: %d: HTTP status code */
		'f_closed_why'      => esc_html__('Another plugin or a setting blocks the WordPress REST API for visitors (HTTP %d).', 'easy-form-builder'),
		'f_closed_do'       => esc_html__('Allow the address /wp-json/Emsfb/ in that plugin (for example Disable REST API), or ask your hosting company.', 'easy-form-builder'),
		'f_rate_title'      => esc_html__('Too many requests from one visitor', 'easy-form-builder'),
		'f_rate_why'        => esc_html__('Human Shield or your server limited requests from this visitor\'s internet address (HTTP 429).', 'easy-form-builder'),
		'f_rate_do'         => esc_html__('If real visitors are affected, raise the limit in the Human Shield settings.', 'easy-form-builder'),
		'f_large_title'     => esc_html__('The form was larger than your server accepts', 'easy-form-builder'),
		'f_large_why'       => esc_html__('Your web server refused the request because of its size (HTTP 413). This limit is set by your hosting company.', 'easy-form-builder'),
		'f_large_do'        => esc_html__('Ask your hosting company to raise the request size limit, or ask visitors to use smaller files.', 'easy-form-builder'),
		'f_blocked_title'   => esc_html__('Your website blocked the form request', 'easy-form-builder'),
		/* translators: %d: HTTP status code */
		'f_blocked_why'     => esc_html__('A security plugin, a firewall or your hosting company refused the request before it reached Easy Form Builder (HTTP %d). It is not caused by a cache plugin.', 'easy-form-builder'),
		/* translators: 1: security plugin name, 2: time of the request */
		'f_blocked_do_plugin' => esc_html__('Open %1$s and look for a blocked request at %2$s.', 'easy-form-builder'),
		/* translators: %s: time of the request */
		'f_blocked_do_host' => esc_html__('Ask your hosting company to check the firewall log for a blocked request to /wp-json/Emsfb/ at %s.', 'easy-form-builder'),
		/* translators: %d: HTTP status code */
		'f_rejected_title'  => esc_html__('The form request was refused (HTTP %d)', 'easy-form-builder'),
		'f_rejected_why'    => esc_html__('Something between the visitor and Easy Form Builder refused the request.', 'easy-form-builder'),
		'f_rejected_do'     => esc_html__('Copy the technical details below and send them to Easy Form Builder support.', 'easy-form-builder'),
		'f_noanswer_title'  => esc_html__('Your server did not answer in time', 'easy-form-builder'),
		'f_noanswer_why'    => esc_html__('The request took too long or the connection dropped. The entry may or may not have been saved.', 'easy-form-builder'),
		/* translators: %s: time of the request */
		'f_noanswer_do'     => esc_html__('Open Messages in Easy Form Builder and check for an entry at %s.', 'easy-form-builder'),
		/* translators: %s: time of the request */
		'f_challenge_do'    => esc_html__('Cloudflare showed the visitor a security check instead of sending the form. Open Cloudflare (Security, Events) and look for a challenged request at %s.', 'easy-form-builder'),
		/* translators: %d: HTTP status code */
		'f_server_title'    => esc_html__('Your server had an error (HTTP %d)', 'easy-form-builder'),
		'f_server_why'      => esc_html__('PHP stopped with an error while handling the form. The entry may or may not have been saved.', 'easy-form-builder'),
		/* translators: %s: time of the request */
		'f_server_do'       => esc_html__('Open Messages and check for an entry at %s, then ask your hosting company for the PHP error log of that time.', 'easy-form-builder'),
		'f_badjson_title'   => esc_html__('Your server sent an unreadable answer', 'easy-form-builder'),
		'f_badjson_why'     => esc_html__('Extra text, often a PHP notice or another plugin\'s output, was printed before the answer, so it could not be read. The entry may or may not have been saved.', 'easy-form-builder'),
		/* translators: %s: time of the request */
		'f_badjson_do'      => esc_html__('Open Messages and check for an entry at %s. On a live site, turn off WP_DEBUG_DISPLAY or ask your hosting company to check the PHP error log.', 'easy-form-builder'),
	];

	$value = '
	(function() {
		"use strict";

		const EFB_ERROR_PANEL = {
			errors: [],
			errorKeys: new Set(),
			visibleErrorKeys: new Set(),
			isOpen: false,
			panel: null,
			badge: null,
			inlineTargetFormId: null,
			storageKey: "efb_error_panel_dismissed_v1",
			dismissedKeys: new Set(),

			t: ' . wp_json_encode($t) . ',

			loadDismissedKeys() {
				try {
					const raw = window.localStorage.getItem(this.storageKey);
					if (!raw) return;
					const parsed = JSON.parse(raw);
					const keys = Array.isArray(parsed) ? parsed : (Array.isArray(parsed.keys) ? parsed.keys : []);
					this.dismissedKeys = new Set(keys.filter(Boolean));
				} catch(e) {
					this.dismissedKeys = new Set();
				}
			},

			saveDismissedKeys() {
				try {
					window.localStorage.setItem(this.storageKey, JSON.stringify({ keys: Array.from(this.dismissedKeys) }));
				} catch(e) {}
			},

			parseSource(source) {
				if (!source) return { type: "unknown", name: this.t.unknown, file: "", fullPath: "" };

				let file = "";
				let fullPath = "";
				try {
					const url = new URL(source);
					const pathname = url.pathname;
					const wpContentIdx = pathname.indexOf("/wp-content/");
					if (wpContentIdx !== -1) {
						fullPath = pathname.substring(wpContentIdx + 1); // Remove leading slash
					} else {
						fullPath = pathname.split("/").slice(-4).join("/");
					}
					file = pathname.split("/").slice(-2).join("/");
				} catch(e) {
					const wpContentIdx = source.indexOf("/wp-content/");
					if (wpContentIdx !== -1) {
						fullPath = source.substring(wpContentIdx + 1);
					} else {
						fullPath = source.split("/").slice(-4).join("/");
					}
					file = source.split("/").slice(-2).join("/");
				}

				const pluginMatch = source.match(/wp-content\/plugins\/([^\/]+)/);
				if (pluginMatch) {
					const name = pluginMatch[1].replace(/-/g, " ").replace(/\b\w/g, c => c.toUpperCase());
					return {
						type: "plugin",
						slug: pluginMatch[1],
						name: name,
						file: file,
						fullPath: fullPath,
						isEFB: pluginMatch[1].includes("easy-form")
					};
				}

				const themeMatch = source.match(/wp-content\/themes\/([^\/]+)/);
				if (themeMatch) {
					const name = themeMatch[1].replace(/-/g, " ").replace(/\b\w/g, c => c.toUpperCase());
					return { type: "theme", slug: themeMatch[1], name: name, file: file, fullPath: fullPath, isEFB: false };
				}

				if (source.match(/wp-(includes|admin)/)) {
					return { type: "wpCore", name: this.t.wpCore, file: file, fullPath: fullPath, isEFB: false };
				}

				if (source.startsWith("http")) {
					try {
						const url = new URL(source);
						if (!source.includes(window.location.hostname)) {
							return { type: "external", name: url.hostname, file: file, fullPath: source, isEFB: false };
						}
					} catch(e) {}
				}

				return { type: "unknown", name: this.t.unknown, file: file, fullPath: fullPath || source, isEFB: false };
			},

			createUI() {
				this.badge = document.createElement("div");
				this.badge.id = "efb-error-badge";
				this.badge.innerHTML = `
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
						<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
						<line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
					</svg>
					<span class="efb-error-label">${this.t.badge || this.t.warningBadge}</span>
					<svg class="efb-badge-chevron" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
						<path fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708"/>
					</svg>
					<!-- <span class="efb-badge-tooltip">${this.t.adminOnly}</span>-->
				`;
				const isRtl = document.documentElement.dir === "rtl" || document.body.dir === "rtl" || getComputedStyle(document.documentElement).direction === "rtl";
				this.isRtl = isRtl;
				this.badge.style.cssText = `
					all: initial !important;
					position: relative !important;
					z-index: auto !important;
					background: #dc3545 !important;
					color: #fff !important; padding: 12px 18px !important; border-radius: 50px !important;
					cursor: pointer !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; gap: 10px !important;
					font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
					font-size: 13px !important; font-weight: 600 !important;
					box-shadow: 0 4px 15px rgba(220,53,69,0.4) !important;
					opacity: 0 !important; pointer-events: none !important; visibility: hidden !important;
					direction: ${isRtl ? "rtl" : "ltr"} !important; box-sizing: border-box !important;
					line-height: normal !important; text-transform: none !important;
					text-decoration: none !important; letter-spacing: normal !important;
					margin: 0 !important; float: none !important; border: none !important;
					min-height: 0 !important; max-height: none !important;
					min-width: 0 !important; height: auto !important; width: auto !important;
					overflow: visible !important;
				`;
				this.badge.setAttribute("role", "status");
				this.badge.setAttribute("aria-live", "polite");
				this.badge.onclick = () => this.togglePanel();
				document.body.appendChild(this.badge);

				this.panel = document.createElement("div");
				this.panel.id = "efb-error-panel";
				this.panel.innerHTML = `
					<div class="efb-panel-header">
						<div class="efb-panel-title">
							<span>${this.t.easyformbuilder} - ${this.t.title}</span>
						</div>
						<div class="efb-panel-actions">
							<button class="efb-btn-clear" onclick="EFB_ERROR_PANEL.dismissAll()">${this.t.dismissAll}</button>
							<button class="efb-btn-close" onclick="EFB_ERROR_PANEL.togglePanel()">×</button>
						</div>
					</div>

					<div class="efb-panel-warning">
						<span>${this.t.warning}</span>
					</div>
					<div class="efb-admin-notice" dir="auto">
						<span class="efb-admin-badge">${this.t.onlyYou}</span>
						<span>${this.t.adminOnlyNow}</span>
					</div>
					<div class="efb-panel-body" id="efb-error-list">
						<div class="efb-no-errors">✓ ${this.t.noErrors}</div>
					</div>
					<div class="efb-panel-footer">
						<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
							<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
						</svg>
						<!-- <span>${this.t.adminOnly}</span> -->
					</div>
				`;
				this.panel.style.cssText = `
					position: fixed !important; top: 50% !important; left: 50% !important; transform: translate(-50%, -50%) scale(0.9) !important;
					z-index: 1000000 !important; width: 92% !important; max-width: 520px !important; max-height: 80vh !important;
					background: #fff !important; border-radius: 16px !important; box-shadow: 0 25px 80px rgba(0,0,0,0.4) !important;
					font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
					display: none !important; opacity: 0 !important; transition: all 0.3s ease !important; overflow: hidden !important;
				`;
				document.body.appendChild(this.panel);

				this.overlay = document.createElement("div");
				this.overlay.id = "efb-error-overlay";
				this.overlay.style.cssText = `
					position: fixed; top: 0; left: 0; right: 0; bottom: 0;
					background: rgba(0,0,0,0.6); z-index: 999998; backdrop-filter: blur(2px);
					display: none; opacity: 0; transition: opacity 0.3s ease;
				`;
				this.overlay.onclick = () => this.togglePanel();
				document.body.appendChild(this.overlay);

				const style = document.createElement("style");
				style.textContent = `
					@keyframes efb-badge-pulse {
						0% { transform: scale(1); opacity: 0.5; }
						100% { transform: scale(1.6); opacity: 0; }
					}
					@keyframes efb-count-pop {
						0% { transform: scale(1); }
						50% { transform: scale(1.3); }
						100% { transform: scale(1); }
					}
					#efb-error-badge:hover {
						background: #c82333 !important;
						box-shadow: 0 6px 25px rgba(220,53,69,0.5) !important;
					}
					#efb-error-badge svg,
					#efb-error-badge span:not(.efb-badge-tooltip) {
						all: initial !important;
						font-family: inherit !important; color: inherit !important;
						line-height: normal !important; display: inline-block !important;
						box-sizing: border-box !important;
					}
					#efb-error-badge svg {
						width: 18px !important; height: 18px !important;
						fill: none !important; stroke: currentColor !important; stroke-width: 2.5 !important;
						vertical-align: middle !important; flex-shrink: 0 !important;
						overflow: visible !important;
					}
					#efb-error-badge .efb-error-label {
						font-size: 13px !important; font-weight: 600 !important;
						color: #fff !important;
					}
					#efb-error-badge .efb-badge-chevron {
						width: 16px !important; height: 16px !important;
						color: #fff !important; opacity: 0.9 !important;
						transition: transform 0.2s ease, opacity 0.2s ease !important;
						flex-shrink: 0 !important;
					}
					#efb-error-badge:hover .efb-badge-chevron { opacity: 1 !important; transform: translateY(1px) !important; }
					#efb-error-badge.is-open .efb-badge-chevron { transform: rotate(180deg) !important; }
					#efb-error-badge::before,
					#efb-error-badge::after {
						content: "" !important; position: absolute !important; inset: -2px !important;
						border-radius: 50px !important; z-index: -1 !important;
						border: 1.5px solid rgba(220,53,69,0.4) !important;
						animation: efb-badge-pulse 2.5s ease-out infinite !important;
						pointer-events: none !important; box-sizing: border-box !important;
						background: transparent !important;
					}
					#efb-error-badge::after {
						animation-delay: 1.25s !important;
					}
					#efb-error-badge .efb-badge-tooltip {
						all: initial !important;
						display: none !important;
						position: absolute !important; top: 50% !important; ${isRtl ? "right" : "left"}: calc(100% + 12px) !important;
						transform: translateY(-50%) translateX(${isRtl ? "8px" : "-8px"}) !important;
						white-space: nowrap !important;
						background: linear-gradient(135deg, #890000, #000014) !important;
						color: #e2e8f0 !important; padding: 8px 14px !important; border-radius: 8px !important;
						font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
						font-size: 12px !important; font-weight: 500 !important; letter-spacing: 0.2px !important;
						box-shadow: 0 4px 15px rgba(0,0,0,0.3) !important;
						opacity: 0 !important; pointer-events: none !important;
						transition: opacity 0.25s ease, transform 0.25s ease !important;
						box-sizing: border-box !important; line-height: normal !important;
						display: none !important; z-index: 1000000 !important;
					}
					#efb-error-badge .efb-badge-tooltip::before {
						content: "" !important; position: absolute !important; top: 50% !important; ${isRtl ? "left" : "right"}: 100% !important;
						transform: translateY(-50%) !important;
						border: 6px solid transparent !important;
						border-${isRtl ? "left" : "right"}-color: #1e1e2e !important;
						${isRtl ? "border-right: none" : "border-left: none"} !important;
						display: block !important;
					}
					#efb-error-badge:hover .efb-badge-tooltip {
						opacity: 1 !important; pointer-events: auto !important;
						transform: translateY(-50%) translateX(0) !important;
					}
					#efb-error-panel,
					#efb-error-panel *,
					#efb-error-panel *::before,
					#efb-error-panel *::after {
						all: revert !important;
						box-sizing: border-box !important;
						margin: 0 !important;
						padding: 0 !important;
						border: none !important;
						float: none !important;
						min-height: 0 !important;
						max-height: none !important;
						min-width: 0 !important;
						height: auto !important;
						width: auto !important;
						line-height: normal !important;
						letter-spacing: normal !important;
						text-transform: none !important;
						text-decoration: none !important;
						text-indent: 0 !important;
						text-align: start !important;
						vertical-align: baseline !important;
						white-space: normal !important;
						word-spacing: normal !important;
						background: transparent !important;
						color: inherit !important;
						font: inherit !important;
						list-style: none !important;
						outline: none !important;
						overflow: visible !important;
						position: static !important;
						transform: none !important;
						transition: none !important;
						animation: none !important;
						visibility: visible !important;
						opacity: 1 !important;
						z-index: auto !important;
						display: block !important;
						flex: none !important;
					}
					#efb-error-panel {
						position: fixed !important; top: 50% !important; left: 50% !important;
						z-index: 1000000 !important; width: 92% !important; max-width: 520px !important; max-height: 80vh !important;
						background: #fff !important; border-radius: 16px !important; box-shadow: 0 25px 80px rgba(0,0,0,0.4) !important;
						font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
						overflow: hidden !important; font-size: 14px !important; color: #333 !important;
						line-height: 1.5 !important;
						direction: ${isRtl ? "rtl" : "ltr"} !important;
					}
					#efb-error-panel svg {
						display: inline-block !important; vertical-align: middle !important;
						height: auto !important; width: auto !important; overflow: visible !important;
						fill: none !important; stroke: currentColor !important; stroke-width: 2 !important;
					}
					#efb-error-panel img {
						max-width: 100% !important; height: auto !important; max-height: 150px !important;
						display: inline-block !important; object-fit: contain !important; border: none !important;
						margin: 0 !important; padding: 0 !important;
					}
					#efb-error-panel .efb-panel-header {
						background: linear-gradient(135deg, #dc3545, #410404) !important;
						color: #fff !important; padding: 16px 20px !important;
						display: flex !important; justify-content: space-between !important; align-items: center !important;
					}
					#efb-error-panel .efb-panel-title { display: flex !important; align-items: center !important; gap: 10px !important; font-weight: 600 !important; font-size: 15px !important; color: #fff !important; }
					#efb-error-panel .efb-panel-title svg { color: #fff !important; }
					#efb-error-panel .efb-panel-actions { display: flex !important; gap: 8px !important; align-items: center !important; }
					#efb-error-panel .efb-btn-clear {
						background: rgba(255,255,255,0.2) !important; border: none !important; color: #fff !important;
						padding: 6px 12px !important; border-radius: 6px !important; cursor: pointer !important; font-size: 12px !important;
						transition: background 0.2s !important; display: inline-block !important;
					}
					#efb-error-panel .efb-btn-clear:hover { background: rgba(255,255,255,0.3) !important; }
					#efb-error-panel .efb-btn-close {
						background: none !important; border: none !important; color: #fff !important; font-size: 26px !important;
						cursor: pointer !important; line-height: 1 !important; padding: 0 4px !important; opacity: 0.8 !important;
						transition: opacity 0.2s !important; display: inline-block !important;
					}
					#efb-error-panel .efb-btn-close:hover { opacity: 1 !important; }
					#efb-error-panel .efb-panel-warning {
						background: #fff8e6 !important; border-bottom: 1px solid #ffe0a0 !important;
						padding: 12px 16px !important; display: flex !important; align-items: center !important; gap: 10px !important;
						color: #8a6d3b !important; font-size: 13px !important; line-height: 1.4 !important;
					}
					#efb-error-panel .efb-panel-warning svg { flex-shrink: 0 !important; color: #f0ad4e !important; }
					#efb-error-panel .efb-admin-notice {
						background: linear-gradient(135deg, #e8f4fd 0%, #d1e9ff 100%) !important;
						border-bottom: 1px solid #b8daff !important;
						padding: 10px 16px !important; display: flex !important; align-items: center !important; gap: 10px !important;
						color: #004085 !important; font-size: 12px !important; line-height: 1.4 !important;
						position: relative !important; overflow: hidden !important;
					}
					#efb-error-panel .efb-admin-notice::before {
						content: "" !important; position: absolute !important; top: 0 !important; left: 0 !important; right: 0 !important; height: 2px !important;
						background: linear-gradient(90deg, #007bff, #00d4ff, #007bff) !important;
						background-size: 200% 100% !important;
						animation: efb-admin-shine 2s linear infinite !important;
						display: block !important; padding: 0 !important; margin: 0 !important;
					}
					@keyframes efb-admin-shine {
						0% { background-position: 200% 0; }
						100% { background-position: -200% 0; }
					}
					#efb-error-panel .efb-admin-notice svg { flex-shrink: 0 !important; color: #007bff !important; }
					#efb-error-panel .efb-admin-notice span:not(.efb-admin-badge) { flex: 1 !important; display: inline !important; }
					#efb-error-panel .efb-admin-badge {
						background: linear-gradient(135deg, #007bff, #0056b3) !important;
						color: #fff !important; font-size: 9px !important; font-weight: 700 !important;
						padding: 4px 10px !important; border-radius: 20px !important;
						text-transform: uppercase !important; letter-spacing: 1px !important;
						box-shadow: 0 2px 8px rgba(0,123,255,0.3) !important;
						animation: efb-badge-glow 2s ease-in-out infinite !important;
						display: inline-block !important;
					}
					@keyframes efb-badge-glow {
						0%, 100% { box-shadow: 0 2px 8px rgba(0,123,255,0.3); }
						50% { box-shadow: 0 2px 15px rgba(0,123,255,0.6); }
					}
					#efb-error-panel .efb-panel-body { max-height: 45vh !important; overflow-y: auto !important; padding: 16px !important; }
					#efb-error-panel .efb-panel-footer {
						background: #f8f9fa !important; border-top: 1px solid #e9ecef !important;
						padding: 10px 16px !important; display: flex !important; align-items: center !important; gap: 8px !important;
						color: #6c757d !important; font-size: 11px !important;
					}
					#efb-error-panel .efb-panel-footer svg { opacity: 0.6 !important; color: #6c757d !important; }
					#efb-error-panel .efb-no-errors {
						text-align: center !important; color: #28a745 !important; padding: 40px 20px !important;
						font-size: 15px !important; font-weight: 500 !important;
					}
					#efb-error-panel .efb-error-item {
						background: #f8f9fa !important; border-radius: 10px !important; padding: 14px !important;
						margin-bottom: 12px !important; border-${isRtl ? "right" : "left"}: 4px solid #dc3545 !important;
						transition: transform 0.2s, box-shadow 0.2s !important;
					}
					#efb-error-panel .efb-error-item:hover { transform: translateX(${isRtl ? "-2px" : "2px"}) !important; box-shadow: 0 2px 8px rgba(0,0,0,0.08) !important; }
					#efb-error-panel .efb-error-item:last-child { margin-bottom: 0 !important; }
					#efb-error-panel .efb-error-item.is-efb { border-${isRtl ? "right" : "left"}-color: #ff4b93 !important; background: #fff5f8 !important; }
					#efb-error-panel .efb-error-item.is-theme { border-${isRtl ? "right" : "left"}-color: #28a745 !important; }
					#efb-error-panel .efb-error-item.is-core { border-${isRtl ? "right" : "left"}-color: #007bff !important; }
					#efb-error-panel .efb-error-item.is-external { border-${isRtl ? "right" : "left"}-color: #6c757d !important; }
					#efb-error-panel .efb-error-source {
						display: flex !important; align-items: center !important; gap: 8px !important;
						font-weight: 600 !important; font-size: 14px !important; margin-bottom: 8px !important;
					}
					#efb-error-panel .efb-error-source-name { flex: 1 !important; min-width: 0 !important; display: inline-block !important; }
					#efb-error-panel .efb-btn-dismiss {
						background: #fff !important; border: 1px solid #dee2e6 !important; color: #495057 !important;
						padding: 4px 9px !important; border-radius: 6px !important; cursor: pointer !important;
						font-size: 11px !important; font-weight: 600 !important; line-height: 1.2 !important;
						margin-${isRtl ? "right" : "left"}: auto !important; display: inline-block !important;
					}
					#efb-error-panel .efb-btn-dismiss:hover {
						background: #f1f3f5 !important; color: #212529 !important; border-color: #ced4da !important;
					}
					#efb-error-panel .efb-error-source .type-badge {
						font-size: 9px !important; padding: 3px 8px !important; border-radius: 4px !important;
						text-transform: uppercase !important; font-weight: 700 !important; letter-spacing: 0.5px !important;
						display: inline-block !important;
					}
					#efb-error-panel .efb-error-source .type-plugin { background: #fff3cd !important; color: #856404 !important; }
					#efb-error-panel .efb-error-source .type-theme { background: #d4edda !important; color: #155724 !important; }
					#efb-error-panel .efb-error-source .type-wpCore { background: #cce5ff !important; color: #004085 !important; }
					#efb-error-panel .efb-error-source .type-external { background: #e2e3e5 !important; color: #383d41 !important; }
					#efb-error-panel .efb-error-source .type-unknown { background: #f5c6cb !important; color: #721c24 !important; }
					#efb-error-panel .efb-error-source .type-notice { background: #fff3cd !important; color: #664d03 !important; border: 1px solid #ffecb5 !important; }
					#efb-error-panel .efb-error-msg { color: #333 !important; font-size: 13px !important; line-height: 1.5 !important; word-break: break-word !important; margin-bottom: 8px !important; }
					#efb-error-panel .efb-error-msg a { color: #0d6efd !important; text-decoration: underline !important; font-weight: 600 !important; }
					#efb-error-panel .efb-error-msg strong { font-weight: 700 !important; color: #1f2937 !important; }
					#efb-error-panel .efb-error-msg img { max-width: 100% !important; height: auto !important; max-height: 14px !important; display: inline-block !important; object-fit: contain !important; }
					#efb-error-panel .efb-error-file {
						background: #1e1e2e !important; padding: 8px 12px !important; border-radius: 6px !important;
						font-family: "SF Mono", Monaco, Consolas, monospace !important;
						font-size: 11px !important; color: #a6e3a1 !important; word-break: break-all !important;
						display: flex !important; align-items: flex-start !important; gap: 8px !important;
						line-height: 1.5 !important; direction: ltr !important; text-align: left !important;
					}
					#efb-error-panel .efb-error-file svg { flex-shrink: 0 !important; opacity: 0.7 !important; margin-top: 2px !important; color: #89b4fa !important; }
					#efb-error-panel .efb-error-file .efb-full-path { color: #cdd6f4 !important; display: inline !important; }
					#efb-error-panel .efb-stack-trace {
						margin-top: 10px !important; background: #1e1e2e !important; border-radius: 8px !important;
						overflow: hidden !important; border: 1px solid #313244 !important; direction: ltr !important; text-align: left !important;
					}
					#efb-error-panel .efb-stack-title {
						background: #313244 !important; color: #cdd6f4 !important; padding: 8px 12px !important;
						font-size: 11px !important; font-weight: 600 !important; display: flex !important; align-items: center !important; gap: 6px !important;
					}
					#efb-error-panel .efb-stack-title svg { color: #89b4fa !important; }
					#efb-error-panel .efb-stack-items { padding: 8px 0 !important; }
					#efb-error-panel .efb-stack-item {
						display: flex !important; align-items: flex-start !important; gap: 10px !important; padding: 6px 12px !important;
						font-family: "SF Mono", Monaco, Consolas, monospace !important; font-size: 11px !important;
						transition: background 0.15s !important;
					}
					#efb-error-panel .efb-stack-item:hover { background: #313244 !important; }
					#efb-error-panel .efb-stack-item.is-efb { background: rgba(255,75,147,0.1) !important; }
					#efb-error-panel .efb-stack-item.is-efb:hover { background: rgba(255,75,147,0.2) !important; }
					#efb-error-panel .efb-stack-num {
						color: #6c7086 !important; min-width: 18px !important; text-align: ${isRtl ? "left" : "right"} !important;
						font-size: 10px !important; padding-top: 2px !important; display: inline-block !important;
					}
					#efb-error-panel .efb-stack-content { flex: 1 !important; min-width: 0 !important; }
					#efb-error-panel .efb-stack-func { color: #f9e2af !important; display: block !important; margin-bottom: 2px !important; }
					#efb-error-panel .efb-stack-loc { display: flex !important; flex-wrap: wrap !important; gap: 6px !important; align-items: center !important; height: auto !important; min-height: 0 !important; max-height: none !important; }
					#efb-error-panel .efb-stack-source {
						font-size: 9px !important; padding: 2px 6px !important; border-radius: 3px !important;
						text-transform: uppercase !important; font-weight: 600 !important; display: inline-block !important;
					}
					#efb-error-panel .efb-stack-source.plugin { background: #fff3cd !important; color: #856404 !important; }
					#efb-error-panel .efb-stack-source.theme { background: #d4edda !important; color: #155724 !important; }
					#efb-error-panel .efb-stack-source.wpCore { background: #cce5ff !important; color: #004085 !important; }
					#efb-error-panel .efb-stack-source.external { background: #e2e3e5 !important; color: #383d41 !important; }
					#efb-error-panel .efb-stack-source.unknown { background: #f5c6cb !important; color: #721c24 !important; }
					#efb-error-panel .efb-stack-path { color: #a6adc8 !important; word-break: break-all !important; display: inline !important; }
					#efb-error-panel .efb-error-meta { color: #6c757d !important; font-size: 11px !important; margin-top: 8px !important; }
					#efb-error-panel .efb-failure-card { text-align: start !important; }
					#efb-error-panel .efb-failure-title { font-size: 15px !important; font-weight: 700 !important; color: #212529 !important; margin: 8px 0 4px !important; line-height: 1.5 !important; }
					#efb-error-panel .efb-failure-why, #efb-error-panel .efb-failure-do, #efb-error-panel .efb-failure-visitor { font-size: 13px !important; color: #343a40 !important; line-height: 1.7 !important; margin: 4px 0 !important; }
					#efb-error-panel .efb-failure-do { background: #e7f5ff !important; border-radius: 8px !important; padding: 8px 10px !important; }
					#efb-error-panel .efb-failure-do a { color: #0b5ed7 !important; text-decoration: none !important; font-weight: 700 !important; }
					#efb-error-panel .efb-failure-visitor { background: #f8f9fa !important; border-radius: 8px !important; padding: 8px 10px !important; }
					#efb-error-panel .efb-failure-tech { margin-top: 8px !important; font-size: 12px !important; color: #6c757d !important; }
					#efb-error-panel .efb-failure-tech summary { cursor: pointer !important; }
					#efb-error-panel .efb-failure-tech code { display: block !important; white-space: pre-wrap !important; word-break: break-all !important; background: #1e1e2e !important; color: #cdd6f4 !important; padding: 8px !important; border-radius: 6px !important; margin: 6px 0 !important; font-size: 11px !important; text-align: left !important; }
					#efb-error-panel .efb-btn-copy { font-size: 11px !important; padding: 3px 10px !important; border-radius: 6px !important; border: 1px solid #ced4da !important; background: #fff !important; cursor: pointer !important; }
					#efb-error-panel .efb-admin-notice span:not(.efb-admin-badge) { font-size: 12px !important; color: #495057 !important; margin-inline-start: 6px !important; }
				`;
				document.head.appendChild(style);
			},

			togglePanel() {
				this.isOpen = !this.isOpen;
				if (this.isOpen) {
					if (this.badge) this.badge.classList.add("is-open");
					this.panel.style.setProperty("display", "block", "important");
					this.overlay.style.display = "block";
					setTimeout(() => {
						this.panel.style.setProperty("opacity", "1", "important");
						this.panel.style.setProperty("transform", "translate(-50%, -50%) scale(1)", "important");
						this.overlay.style.opacity = "1";
					}, 10);
				} else {
					if (this.badge) this.badge.classList.remove("is-open");
					this.panel.style.setProperty("opacity", "0", "important");
					this.panel.style.setProperty("transform", "translate(-50%, -50%) scale(0.9)", "important");
					this.overlay.style.opacity = "0";
					setTimeout(() => {
						this.panel.style.setProperty("display", "none", "important");
						this.overlay.style.display = "none";
					}, 300);
				}
			},

			getInlineTarget(formId) {
				const id = formId !== null && formId !== undefined ? String(formId) : "";
				if (id !== "") {
					return document.getElementById("efb-submit-error-badge-slot-" + id)
						|| document.querySelector("#body_efb_" + id + " #efb-final-step")
						|| document.querySelector("#body_efb_" + id + " .view-efb");
				}
				return document.querySelector(".efb-submit-error-badge-slot")
					|| document.querySelector(".view-efb #efb-final-step")
					|| document.querySelector(".view-efb");
			},

			showInlineBadge(formId = null) {
				if (!this.badge) return;
				if (formId !== null && formId !== undefined) {
					this.inlineTargetFormId = formId;
				}
				const target = this.getInlineTarget(this.inlineTargetFormId);
				if (target && this.badge.parentNode !== target) {
					target.appendChild(this.badge);
				} else if (!this.badge.isConnected) {
					document.body.appendChild(this.badge);
				}
				this.showBadge();
			},

			showBadge() {
				if (!this.badge) return;
				this.badge.style.setProperty("visibility", "visible", "important");
				this.badge.style.setProperty("opacity", "1", "important");
				this.badge.style.setProperty("pointer-events", "auto", "important");
			},

			hideBadge() {
				if (!this.badge) return;
				this.badge.style.setProperty("opacity", "0", "important");
				this.badge.style.setProperty("pointer-events", "none", "important");
				this.badge.style.setProperty("visibility", "hidden", "important");
			},

			clearSubmissionBadge(formId = null) {
				this.hideBadge();
				if (formId === null || formId === undefined || String(formId) === String(this.inlineTargetFormId)) {
					this.inlineTargetFormId = null;
					this.visibleErrorKeys.clear();
				}
				this.closePanelIfOpen();
			},

			updateCount() {},


			addError(errorData) {
				errorData = errorData || {};
				const { message, source, lineno, stack = [], typeOverride = null, nameOverride = null, formatOptions = {}, showBadge = false, context = "", formId = null, failure = null } = errorData;
				const shouldShowBadge = showBadge === true || context === "submissionAjax";
				/* A failed submit gets its own key per occurrence: dismissing one must
				   not hide the next failure, and it is never stored (see dismissError). */
				const errorKey = failure ? "efb-submit:" + (failure.at || Date.now()) + ":" + (failure.cause || "") : (message || "") + "|" + (source || "") + "|" + (lineno || "");
				if (this.dismissedKeys.has(errorKey)) return;
				if (this.errorKeys.has(errorKey)) {
					if (shouldShowBadge) {
						this.visibleErrorKeys.add(errorKey);
						this.showInlineBadge(formId);
					}
					return;
				}
				this.errorKeys.add(errorKey);

				const parsed = this.parseSource(source);
				if (typeOverride) { parsed.type = typeOverride; }
				if (nameOverride) { parsed.name = nameOverride; }
				const time = new Date().toLocaleTimeString([], {hour: "2-digit", minute: "2-digit"});

				const realSource = stack.length > 0 ? stack[0].parsed : parsed;
				const realPath = stack.length > 0 ? stack[0].parsed.fullPath + ":" + stack[0].line : (parsed.fullPath + (lineno ? ":" + lineno : ""));

				this.errors.push({ key: errorKey, message, source, lineno, parsed, stack, time, showBadge: shouldShowBadge });

				if (shouldShowBadge) {
					this.visibleErrorKeys.add(errorKey);
					this.showInlineBadge(formId);
				}
				this.updateCount();

				if (context === "submissionAjax") {
					if (failure) this.renderFailureCard(errorKey, failure, time);
					return;
				}

				const list = document.getElementById("efb-error-list");
				if (!list) return;
				const noErrors = list.querySelector(".efb-no-errors");
				if (noErrors) noErrors.remove();

				const typeClass = realSource.isEFB ? "is-efb" :
					realSource.type === "theme" ? "is-theme" :
					realSource.type === "wpCore" ? "is-core" :
					realSource.type === "external" ? "is-external" : "";

				let stackHtml = "";
				if (stack.length > 0) {
					stackHtml = `<div class="efb-stack-trace">
						<div class="efb-stack-title">
							<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
								<polyline points="4 17 10 11 4 5"/><line x1="12" y1="19" x2="20" y2="19"/>
							</svg>
							Stack Trace
						</div>
						<div class="efb-stack-items">
							${stack.map((s, i) => `
								<div class="efb-stack-item ${s.parsed.isEFB ? "is-efb" : ""}">
									<span class="efb-stack-num">${i + 1}</span>
									<div class="efb-stack-content">
										<span class="efb-stack-func">${this.escapeHtml(s.func)}</span>
										<span class="efb-stack-loc">
											<span class="efb-stack-source ${s.parsed.type}">${s.parsed.name}</span>
											<span class="efb-stack-path">${s.parsed.fullPath}:${s.line}</span>
										</span>
									</div>
								</div>
							`).join("")}
						</div>
					</div>`;
				}

				const item = document.createElement("div");
				item.className = "efb-error-item " + typeClass;
				const messageHtml = context === "submissionAjax" ? "" : `<div class="efb-error-msg">${this.formatMessage(message, formatOptions)}</div>`;
				item.innerHTML = `
					<div class="efb-error-source">
						<span class="type-badge type-${realSource.type}">${this.t[realSource.type] || realSource.type}</span>
						<span class="efb-error-source-name">${realSource.name}</span>
						<button type="button" class="efb-btn-dismiss" aria-label="${this.getDismissLabel(realSource.name)}">${this.t.dismiss}</button>
					</div>
					${messageHtml}
					<div class="efb-error-file">
						<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
							<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
						</svg>
						<span class="efb-full-path">${realPath}</span>
					</div>
					${stackHtml}
					<div class="efb-error-meta">${time}</div>
				`;
				item.dataset.errorKey = errorKey;
				const dismissBtn = item.querySelector(".efb-btn-dismiss");
				if (dismissBtn) {
					dismissBtn.addEventListener("click", () => this.dismissError(errorKey));
				}
				list.insertBefore(item, list.firstChild);
			},

			/* The card for one failed submit: what happened, why, the one thing to
			   do, the visitor message and the details for support. Every value that
			   came from the request is escaped. */
			failureTexts(f) {
				const t = this.t;
				const status = Number(f.status) || 0;
				const at = f.timeLabel || "";
				const fill = (str, a, b) => String(str || "").split("%1$s").join(a).split("%2$s").join(b).split("%s").join(a).split("%d").join(String(status));
				const cachePlugin = this.escapeHtml(f.cachePlugin || "");
				const securityPlugin = this.escapeHtml(f.securityPlugin || "");
				switch (f.cause) {
					case "stale_token":
					case "stale_context":
						if (f.cachePlugin) return { title: t.f_cache_title, why: fill(t.f_cache_why, cachePlugin), todo: fill(t.f_cache_do, cachePlugin) };
						return { title: t.f_expired_title, why: t.f_expired_why, todo: f.cause === "stale_token" ? t.f_do_refresh : t.f_do_send_again };
					case "identity_changed": return { title: t.f_login_title, why: t.f_login_why, todo: t.f_do_send_again };
					case "server_refusal": return { title: t.f_refused_title, why: t.f_refused_why, todo: t.f_refused_do };
					case "server_failed": return { title: t.f_storage_title, why: t.f_storage_why, todo: t.f_storage_do };
					case "closed": return { title: t.f_closed_title, why: fill(t.f_closed_why), todo: t.f_closed_do };
					case "rate_limited": return { title: t.f_rate_title, why: t.f_rate_why, todo: t.f_rate_do };
					case "too_large": return { title: t.f_large_title, why: t.f_large_why, todo: t.f_large_do };
					case "blocked": return { title: t.f_blocked_title, why: fill(t.f_blocked_why), todo: f.challenge ? fill(t.f_challenge_do, at) : (f.securityPlugin ? fill(t.f_blocked_do_plugin, securityPlugin, at) : fill(t.f_blocked_do_host, at)) };
					case "unknown_outcome": return { title: t.f_noanswer_title, why: t.f_noanswer_why, todo: fill(t.f_noanswer_do, at), link: t.messagesUrl };
					case "server_error": return { title: fill(t.f_server_title), why: t.f_server_why, todo: fill(t.f_server_do, at), link: t.messagesUrl };
					case "bad_answer": return { title: t.f_badjson_title, why: t.f_badjson_why, todo: fill(t.f_badjson_do, at), link: t.messagesUrl };
					default: return { title: fill(t.f_rejected_title), why: t.f_rejected_why, todo: t.f_rejected_do };
				}
			},

			renderFailureCard(errorKey, f, time) {
				const list = document.getElementById("efb-error-list");
				if (!list) return;
				const noErrors = list.querySelector(".efb-no-errors");
				if (noErrors) noErrors.remove();
				const esc = (v) => this.escapeHtml(v === undefined || v === null ? "" : String(v));
				const x = this.failureTexts(f);
				const tech = [
					f.status ? "HTTP " + f.status : "no HTTP answer",
					f.code || "",
					f.step ? "step: " + f.step : "",
					f.formId ? "form " + f.formId : "",
					f.page || "",
					f.at ? new Date(f.at).toISOString() : "",
					f.legacy ? "runtime: page nonce" : "runtime: live",
					f.serverMessage ? "server: " + f.serverMessage : ""
				].filter(Boolean).join(" | ");
				const item = document.createElement("div");
				item.className = "efb-error-item is-efb efb-failure-card";
				item.setAttribute("dir", "auto");
				item.dataset.errorKey = errorKey;
				item.innerHTML = `
					<div class="efb-error-source">
						<span class="type-badge type-plugin">${esc(this.t.formSubmit)}</span>
						<span class="efb-error-source-name">${esc(this.t.easyformbuilder)}</span>
						<button type="button" class="efb-btn-dismiss" aria-label="${this.getDismissLabel(this.t.formSubmit)}">${this.t.dismiss}</button>
					</div>
					<div class="efb-failure-title">${x.title || ""}</div>
					<div class="efb-failure-why">${x.why || ""}</div>
					<div class="efb-failure-do"><strong>${this.t.whatToDo}:</strong> ${x.todo || ""}${x.link ? ` <a href="${esc(x.link)}" target="_blank" rel="noopener noreferrer">&rarr;</a>` : ""}</div>
					${f.visitorText ? `<div class="efb-failure-visitor"><strong>${this.t.visitorSaw}:</strong> <span dir="auto">${esc(f.visitorText)}</span></div>` : ""}
					<details class="efb-failure-tech"><summary>${this.t.techDetails}</summary><code dir="ltr">${esc(tech)}</code><button type="button" class="efb-btn-copy">${this.t.copyDetails}</button></details>
					<div class="efb-error-meta">${esc(time)}</div>
				`;
				const dismissBtn = item.querySelector(".efb-btn-dismiss");
				if (dismissBtn) dismissBtn.addEventListener("click", () => this.dismissError(errorKey));
				const copyBtn = item.querySelector(".efb-btn-copy");
				if (copyBtn) copyBtn.addEventListener("click", () => {
					const code = item.querySelector(".efb-failure-tech code");
					const done = () => { copyBtn.textContent = this.t.copiedDone; };
					/* Without a secure context (plain http) the Clipboard API is missing: select
					   the text and use the old copy command; if that fails too, the text stays
					   selected for a manual copy and "Copied" is not claimed. */
					const fallback = () => {
						try {
							const range = document.createRange(); range.selectNodeContents(code);
							const sel = window.getSelection(); sel.removeAllRanges(); sel.addRange(range);
							if (document.execCommand("copy")) done();
						} catch (e) {}
					};
					if (window.isSecureContext && navigator.clipboard && navigator.clipboard.writeText) {
						navigator.clipboard.writeText(tech).then(done, fallback);
					} else {
						fallback();
					}
				});
				list.insertBefore(item, list.firstChild);
			},

			getDismissLabel(label) {
				const template = this.t.dismissItem || "Dismiss %s";
				return this.escapeHtml(template.replace("%s", label || ""));
			},

			showEmptyState() {
				const list = document.getElementById("efb-error-list");
				if (list) {
					list.innerHTML = "<div class=\"efb-no-errors\">&#10003; " + this.t.noErrors + "</div>";
				}
			},

			closePanelIfOpen() {
				if (this.isOpen) {
					this.togglePanel();
				}
			},

			dismissAll() {
				this.errors.forEach(error => {
					if (error.key) {
						this.dismissedKeys.add(error.key);
					}
				});
				this.saveDismissedKeys();
				this.errors = [];
				this.errorKeys.clear();
				this.visibleErrorKeys.clear();
				this.updateCount();
				const list = document.getElementById("efb-error-list");
				list.innerHTML = "<div class=\"efb-no-errors\">✓ " + this.t.noErrors + "</div>";
				this.hideBadge();
				this.closePanelIfOpen();
			},

			dismissError(errorKey) {
				if (!errorKey) return;
				if (String(errorKey).indexOf("efb-submit:") !== 0) {
					this.dismissedKeys.add(errorKey);
					this.saveDismissedKeys();
				}
				this.errors = this.errors.filter(error => error.key !== errorKey);
				this.errorKeys.delete(errorKey);
				this.visibleErrorKeys.delete(errorKey);
				const list = document.getElementById("efb-error-list");
				if (list) {
					Array.from(list.querySelectorAll(".efb-error-item")).forEach(item => {
						if (item.dataset.errorKey === errorKey) {
							item.remove();
						}
					});
				}
				this.updateCount();
				if (this.errors.length === 0) {
					this.showEmptyState();
				}
				if (this.visibleErrorKeys.size === 0) {
					this.hideBadge();
					this.closePanelIfOpen();
				}
			},

			clearErrors() {
				this.dismissAll();
			},

			escapeHtml(text) {
				const div = document.createElement("div");
				div.textContent = text;
				return div.innerHTML;
			},

			escapeRegExp(text) {
				const backslash = String.fromCharCode(92);
				const specials = backslash + "^$.*+?()[]{}|";
				return String(text).split("").map(ch => specials.indexOf(ch) !== -1 ? backslash + ch : ch).join("");
			},

			formatMessage(message, options = {}) {
				let html = this.escapeHtml(message).replace(/\n/g, "<br>");
				const boldTexts = Array.isArray(options.boldTexts) ? options.boldTexts : [];
				boldTexts.filter(Boolean).forEach(text => {
					const safeText = this.escapeHtml(text);
					html = html.replace(new RegExp(this.escapeRegExp(safeText), "g"), "<strong>" + safeText + "</strong>");
				});

				const links = Array.isArray(options.links) ? options.links : [];
				links.filter(Boolean).forEach(link => {
					const rawUrl = typeof link === "string" ? link : link.url;
					const rawLabel = typeof link === "string" ? link : (link.label || link.url);
					if (!rawUrl) return;
					const safeUrl = this.escapeHtml(rawUrl);
					const safeLabel = this.escapeHtml(rawLabel || rawUrl);
					html = html.replace(new RegExp(this.escapeRegExp(safeUrl), "g"), "<a href=\"" + safeUrl + "\" target=\"_blank\" rel=\"noopener noreferrer\">" + safeLabel + "</a>");
				});

				return html;
			},

			parseStack(stackString) {
				if (!stackString) return [];
				const lines = stackString.split("\n");
				const stack = [];

				for (const line of lines) {
					const match = line.match(/at\s+(.+?)\s*\(?(https?:\/\/[^)\s]+):(\d+):(\d+)\)?/);
					if (match) {
						const [, funcName, url, lineNo, colNo] = match;
						const parsed = this.parseSource(url);
						stack.push({
							func: funcName.trim(),
							url: url,
							line: lineNo,
							col: colNo,
							parsed: parsed
						});
					}
				}
				return stack;
			},

			test(message) {
				const msg = message || "🧪 This is a TEST error message from EFB_ERROR_PANEL.test()";
				const err = new Error(msg);
				const stack = this.parseStack(err.stack);
				this.addError({
					message: msg,
					source: window.location.href,
					lineno: null,
					stack: stack
				});
				console.info("%c[EFB Debug Panel]%c Test error added: " + msg, "color:#ff4b93;font-weight:bold", "color:inherit");
			},

			log(message, options = {}) {
				if (!message) { console.warn("[EFB Debug Panel] log() requires a message"); return; }

				let source = options.source || window.location.href;
				let lineno = options.line || options.lineno || null;
				let stack = [];

				if (Array.isArray(options.stack) && options.stack.length > 0) {
					stack = options.stack.map(s => {
						const filePath = s.file || s.url || "";
						const parsed = this.parseSource(filePath.startsWith("http") ? filePath : window.location.origin + "/" + filePath);
						parsed.fullPath = filePath;
						return {
							func: s.func || s.function || "anonymous",
							url: filePath,
							line: String(s.line || "?"),
							col: String(s.col || s.column || "?"),
							parsed: parsed
						};
					});
				} else if (options.captureStack !== false) {
					const err = new Error("__efb_log__");
					stack = this.parseStack(err.stack);
					if (stack.length > 0 && stack[0].func.includes("log")) {
						stack.shift();
					}
				}

				const typeOverride = options.type || null;
				const nameOverride = options.name || null;
				const formatOptions = options.format || {};
				const showBadge = options.showBadge === true;
				const context = options.context || "";
				const formId = options.formId || options.form_id || null;
				const failure = options.failure && typeof options.failure === "object" ? options.failure : null;
				this.addError({ message, source, lineno, stack, typeOverride, nameOverride, formatOptions, showBadge, context, formId, failure });
				console.info("%c[EFB Debug Panel]%c Logged: " + message, "color:#ff4b93;font-weight:bold", "color:inherit");
			},

			init() {
				const self = this;
				this.loadDismissedKeys();

				if (document.readyState === "loading") {
					document.addEventListener("DOMContentLoaded", () => self.createUI());
				} else {
					self.createUI();
				}

				// Check if jQuery is loaded and working properly
				setTimeout(() => {
					if (typeof jQuery === "undefined" || !jQuery || typeof jQuery.ajax !== "function") {
						self.addError({
							message: self.t.jqueryMessage,
							source: "window",
							lineno: null,
							typeOverride: "jqueryMissing",
							nameOverride: self.t.jqueryMissing
						});
					}
					/* WordPress deliberately runs jQuery in noConflict mode, so a missing
					 * global `$` is normal and must not be reported as an application error. */
				}, 500);

				window.addEventListener("error", function(event) {
					let stack = [];
					if (event.error && event.error.stack) {
						stack = self.parseStack(event.error.stack);
					}
					self.addError({
						message: event.message,
						source: event.filename,
						lineno: event.lineno,
						stack: stack
					});
				}, true);

				window.addEventListener("unhandledrejection", function(event) {
					let source = "";
					let stack = [];
					let message = event.reason instanceof Error ? event.reason.message : String(event.reason);

					if (event.reason && event.reason.stack) {
						const match = event.reason.stack.match(/https?:\/\/[^\s]+/);
						source = match ? match[0] : "";
						stack = self.parseStack(event.reason.stack);
					}

					self.addError({ message, source, lineno: null, stack });
				});
			}
		};

		EFB_ERROR_PANEL.init();
		window.EFB_ERROR_PANEL = EFB_ERROR_PANEL;
	})();';

	return $value;
}

	public function efb_register_head_hooks() {

		add_action( 'wp_head', [ $this, 'efb_print_author_link' ], 1 );

		add_filter( 'the_generator', [ $this, 'efb_filter_generator' ], 10, 2 );
	}

	public function efb_print_author_link() {
		$enabled = apply_filters( 'efb_author_link_enabled', true );
		if ( ! $enabled ) { return; }

		printf( '<link rel="author" href="%s">' . "\n", esc_url( 'https://whitestudio.team/' ) );
	}

	public function efb_filter_generator( $generator, $type ) {
		$enabled = apply_filters( 'efb_generator_meta_enabled', true );
		if ( ! $enabled ) { return $generator; }

		return '<meta name="generator" content="'. esc_html__( 'Easy Form Builder', 'easy-form-builder' ). ' - ' . esc_html__( 'WhiteStudio.team', 'easy-form-builder' ).'" />' . "\n";
	}

	private function efb_print_schema_ld( $schema ) {
		$enabled = apply_filters( 'efb_schema_ld_enabled', true );
		if ( ! $enabled ) { return; }
		if ( empty( $schema ) ) { return; }

		wp_print_inline_script_tag(
			wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
			[ 'type' => 'application/ld+json' ]
		);
	}

	public function efb_output_schema_free_plus() {
		$page_url = home_url( add_query_arg( [], sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ) ) ) );
		$locale = get_locale();
		$sub = substr(get_locale(), 0, 2);
		$sub = $sub == 'en' ? '' : $sub;
		$lang   = str_replace('_', '-', $locale);
		$ws_url = $locale === 'fa_IR' ? 'https://easyformbuilder.ir' : 'https://whitestudio.team';
		$wp_url =  'https://' . $sub . '.wordpress.org/plugins/easy-form-builder/' ;
		$software_id = $ws_url . '#easy-form-builder';
		$org_id      = $ws_url . '#organization';

		$schema = [
			'@context' => 'https://schema.org',
			'@graph'   => [

				[
					'@type'      => 'WebPage',
					'@id'        => $page_url . '#webpage',
					'url'        => $page_url,
					'inLanguage' => $lang,
					'mentions'   => [
						'@id' => $software_id,
					],
				],

				[
					'@type' => 'SoftwareApplication',
					'@id'   => $software_id,

					'name' => esc_html__( 'Easy Form Builder', 'easy-form-builder' ),

					'alternateName' => [
						esc_html__( 'EFB', 'easy-form-builder' ),
						esc_html__( 'Easy Form Builder WordPress Plugin', 'easy-form-builder' ),
					],

					'description' => esc_html__( 'Easy Form Builder is a free and powerful WordPress form builder plugin for creating contact forms, payment forms, surveys, registration forms, multi-step forms, file upload forms, electronic signature forms, and audio, video, and screen recording forms, with conditional logic, email notifications, integrations, entry management, and advanced spam protection.', 'easy-form-builder' ),

					'url'        => $ws_url,
					'installUrl' => $wp_url,

					'sameAs' => [
						$wp_url,
					],

					'applicationCategory'    => 'BusinessApplication',
					'applicationSubCategory' => esc_html__( 'WordPress Form Builder Plugin', 'easy-form-builder' ),

					'operatingSystem'      => 'WordPress',
					'runtimePlatform'      => 'WordPress',
					'softwareRequirements' => esc_html__( 'WordPress 5.0 or later; PHP 7.0 or later', 'easy-form-builder' ),

					'softwareVersion' => defined( 'EMSFB_PLUGIN_VERSION' ) ? EMSFB_PLUGIN_VERSION : '',

					'isAccessibleForFree' => true,

					'softwareHelp' => [
						'@type' => 'CreativeWork',
						'url'   => $ws_url . 'documents/',
						'name'  => esc_html__( 'Easy Form Builder Documentation', 'easy-form-builder' ),
					],

					'featureList' => [
						esc_html__( 'Drag-and-Drop WordPress Form Builder', 'easy-form-builder' ),
						esc_html__( 'Unlimited Forms and Form Entries', 'easy-form-builder' ),
						esc_html__( 'Responsive and RTL-Friendly Forms', 'easy-form-builder' ),

						esc_html__( 'Contact and Inquiry Forms', 'easy-form-builder' ),
						esc_html__( 'Support and Feedback Forms with Message Tracking', 'easy-form-builder' ),
						esc_html__( 'Survey and Poll Forms with Visual Results', 'easy-form-builder' ),
						esc_html__( 'Stripe and PayPal Payment Forms', 'easy-form-builder' ),
						esc_html__( 'WordPress Login and Registration Forms', 'easy-form-builder' ),
						esc_html__( 'File Upload Forms', 'easy-form-builder' ),
						esc_html__( 'Audio Recording Forms', 'easy-form-builder' ),
						esc_html__( 'Video Recording Forms', 'easy-form-builder' ),
						esc_html__( 'Screen Recording Forms with Optional Microphone Audio', 'easy-form-builder' ),
						esc_html__( 'Private and Restricted-Access Forms', 'easy-form-builder' ),
						esc_html__( 'Multi-Step Forms', 'easy-form-builder' ),
						esc_html__( 'Smart Forms with Conditional Logic', 'easy-form-builder' ),
						esc_html__( 'Electronic Signature Forms', 'easy-form-builder' ),
						esc_html__( 'Location Picker Forms with OpenStreetMap and Leaflet', 'easy-form-builder' ),
						esc_html__( 'Job Application Forms', 'easy-form-builder' ),
						esc_html__( 'Event Registration Forms', 'easy-form-builder' ),
						esc_html__( 'Order Forms with Recurring and Subscription Billing', 'easy-form-builder' ),

						esc_html__( 'Drag-and-Drop File Upload Fields', 'easy-form-builder' ),
						esc_html__( 'Audio, Video and Screen Recording Fields', 'easy-form-builder' ),
						esc_html__( 'Electronic Signature and Location Picker Fields', 'easy-form-builder' ),
						esc_html__( 'Country, State, Province and City Fields', 'easy-form-builder' ),
						esc_html__( 'Multiple-Select Dropdowns and Range Sliders', 'easy-form-builder' ),
						esc_html__( 'Rating Stars, 5-Point Scale, NPS and Yes-No Questions', 'easy-form-builder' ),
						esc_html__( 'Switch and Toggle Fields', 'easy-form-builder' ),
						esc_html__( 'Gregorian, Hijri and Jalali Date Pickers', 'easy-form-builder' ),
						esc_html__( 'Telephone, Mobile and Color Picker Fields', 'easy-form-builder' ),
						esc_html__( 'Matrix and Table Fields for Structured Data', 'easy-form-builder' ),

						esc_html__( 'Automatic Email Notifications', 'easy-form-builder' ),
						esc_html__( 'Custom Email Templates with Visual Editor', 'easy-form-builder' ),
						esc_html__( 'Conditional Email Notifications and Recipient Routing', 'easy-form-builder' ),
						esc_html__( 'Email Delivery Status Checking', 'easy-form-builder' ),
						esc_html__( 'Email Error Logging', 'easy-form-builder' ),
						esc_html__( 'Email Deliverability Reports with Spam Score, SPF, DKIM and DMARC Checks', 'easy-form-builder' ),
						esc_html__( 'SMS and Telegram Submission Notifications', 'easy-form-builder' ),

						esc_html__( 'WordPress Form Entry and Message Management Dashboard', 'easy-form-builder' ),
						esc_html__( 'Read and Reply to Form Submissions with File Attachments', 'easy-form-builder' ),
						esc_html__( 'Open and Closed Message Status', 'easy-form-builder' ),
						esc_html__( 'Search Submissions by Form or Confirmation Code', 'easy-form-builder' ),
						esc_html__( 'Unique Submission Confirmation and Tracking Codes', 'easy-form-builder' ),
						esc_html__( 'PDF Downloads and CSV Exports', 'easy-form-builder' ),
						esc_html__( 'Survey and Poll Result Charts', 'easy-form-builder' ),

						esc_html__( 'Google Sheets Form Submission Sync', 'easy-form-builder' ),
						esc_html__( 'Stripe and PayPal Payment Workflows', 'easy-form-builder' ),
						esc_html__( 'External API Auto-Populate and Autofill', 'easy-form-builder' ),
						esc_html__( 'Offline Form Data Collection', 'easy-form-builder' ),
						esc_html__( 'Shield Security silentCAPTCHA Integration', 'easy-form-builder' ),
						esc_html__( 'Gutenberg, Elementor, Divi and WPBakery Integration', 'easy-form-builder' ),

						esc_html__( 'Form Submissions Stored in the WordPress Database', 'easy-form-builder' ),
						esc_html__( 'No Third-Party Form Submission Servers Required', 'easy-form-builder' ),
						esc_html__( 'Client-Side and Server-Side Form Validation', 'easy-form-builder' ),
						esc_html__( 'Google reCAPTCHA v2 Spam Protection', 'easy-form-builder' ),
						esc_html__( 'CAPTCHA-Free Spam Protection with Honeypot and Signed Single-Use Tokens', 'easy-form-builder' ),
						esc_html__( 'Rate Limiting and Abuse Protection', 'easy-form-builder' ),
						esc_html__( 'Protection Against Email, SMS, Telegram and Google Sheets Quota Abuse', 'easy-form-builder' ),
						esc_html__( 'IP Allowlist and Blocklist Security Controls', 'easy-form-builder' ),
						esc_html__( 'Monitor-Only Security Mode', 'easy-form-builder' ),
					],

					'keywords' => [
						esc_html__( 'WordPress form builder', 'easy-form-builder' ),
						esc_html__( 'free WordPress form plugin', 'easy-form-builder' ),
						esc_html__( 'WordPress form plugin', 'easy-form-builder' ),
						esc_html__( 'drag and drop form builder', 'easy-form-builder' ),

						esc_html__( 'contact form plugin', 'easy-form-builder' ),
						esc_html__( 'WordPress contact form', 'easy-form-builder' ),
						esc_html__( 'survey form plugin', 'easy-form-builder' ),
						esc_html__( 'WordPress survey plugin', 'easy-form-builder' ),
						esc_html__( 'WordPress poll plugin', 'easy-form-builder' ),

						esc_html__( 'payment form plugin', 'easy-form-builder' ),
						esc_html__( 'WordPress payment forms', 'easy-form-builder' ),
						esc_html__( 'Stripe payment forms', 'easy-form-builder' ),
						esc_html__( 'PayPal payment forms', 'easy-form-builder' ),
						esc_html__( 'subscription payment forms', 'easy-form-builder' ),
						esc_html__( 'recurring payment forms', 'easy-form-builder' ),

						esc_html__( 'WordPress registration form', 'easy-form-builder' ),
						esc_html__( 'WordPress login form', 'easy-form-builder' ),
						esc_html__( 'multi-step form plugin', 'easy-form-builder' ),
						esc_html__( 'conditional logic forms', 'easy-form-builder' ),
						esc_html__( 'smart forms WordPress', 'easy-form-builder' ),

						esc_html__( 'file upload form plugin', 'easy-form-builder' ),
						esc_html__( 'electronic signature forms', 'easy-form-builder' ),
						esc_html__( 'e-signature form plugin', 'easy-form-builder' ),
						esc_html__( 'location picker form', 'easy-form-builder' ),

						esc_html__( 'WordPress audio recorder form', 'easy-form-builder' ),
						esc_html__( 'WordPress video recorder form', 'easy-form-builder' ),
						esc_html__( 'WordPress screen recorder form', 'easy-form-builder' ),

						esc_html__( 'job application forms', 'easy-form-builder' ),
						esc_html__( 'WordPress job application form', 'easy-form-builder' ),
						esc_html__( 'event registration forms', 'easy-form-builder' ),
						esc_html__( 'WordPress event registration form', 'easy-form-builder' ),

						esc_html__( 'email notification forms', 'easy-form-builder' ),
						esc_html__( 'WordPress email notifications', 'easy-form-builder' ),
						esc_html__( 'conditional email notifications', 'easy-form-builder' ),
						esc_html__( 'form email deliverability', 'easy-form-builder' ),
						esc_html__( 'SPF DKIM DMARC WordPress forms', 'easy-form-builder' ),

						esc_html__( 'WordPress form entries', 'easy-form-builder' ),
						esc_html__( 'form submission management', 'easy-form-builder' ),
						esc_html__( 'WordPress form message management', 'easy-form-builder' ),
						esc_html__( 'form submissions PDF export', 'easy-form-builder' ),
						esc_html__( 'form submissions CSV export', 'easy-form-builder' ),

						esc_html__( 'Google Sheets form integration', 'easy-form-builder' ),
						esc_html__( 'WordPress forms API integration', 'easy-form-builder' ),
						esc_html__( 'form autofill API', 'easy-form-builder' ),
						esc_html__( 'offline WordPress forms', 'easy-form-builder' ),

						esc_html__( 'secure WordPress forms', 'easy-form-builder' ),
						esc_html__( 'WordPress form spam protection', 'easy-form-builder' ),
						esc_html__( 'CAPTCHA-free form spam protection', 'easy-form-builder' ),
						esc_html__( 'honeypot spam protection WordPress', 'easy-form-builder' ),
						esc_html__( 'WordPress form rate limiting', 'easy-form-builder' ),
						esc_html__( 'self-hosted WordPress forms', 'easy-form-builder' ),
						esc_html__( 'WordPress forms stored locally', 'easy-form-builder' ),

						esc_html__( 'Gutenberg form builder', 'easy-form-builder' ),
						esc_html__( 'Elementor form plugin', 'easy-form-builder' ),
						esc_html__( 'Divi form plugin', 'easy-form-builder' ),
						esc_html__( 'WPBakery form plugin', 'easy-form-builder' ),
					],

					'publisher' => [
						'@id' => $org_id,
					],

					'creator' => [
						'@id' => $org_id,
					],

					'inLanguage' => $lang,
				],
			],
		];

		$this->efb_print_schema_ld( $schema );
		$this->efb_register_head_hooks();
	}

		/* public function efb_output_schema_free () {
			$page_url = home_url( add_query_arg( [], sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ) ) ) );
			$locale = get_locale();
			$lang   = str_replace('_', '-', $locale);
			$ws_url = $locale === 'fa_IR' ? 'https://easyformbuilder.ir' : 'https://whitestudio.team';
			$wp_url = $locale === 'fa_IR' ? 'https://fa.wordpress.org/plugins/easy-form-builder/' : 'https://wordpress.org/plugins/easy-form-builder/';

			$schema = [
				'@context' => 'https://schema.org',
				'@type'    => 'WebPage',
				'@id'      => $page_url . '#webpage',
				'url'      => $page_url,
				'mentions' => [
					'@type' => 'SoftwareApplication',
					'@id'   => $wp_url . '#software',
					'name'  => esc_html__( 'Easy Form Builder', 'easy-form-builder' ),
					'alternateName' => esc_html__( 'Free WordPress Form Builder Plugin', 'easy-form-builder' ),
					'description' => esc_html__( 'Easy Form Builder is a WordPress form builder plugin for creating contact forms, payment forms, and survey forms.', 'easy-form-builder' ),
					'applicationCategory' => ['BusinessApplication', 'WebApplication'],
					'operatingSystem'     => 'WordPress',
					'softwareVersion'     => defined('EMSFB_PLUGIN_VERSION') ? EMSFB_PLUGIN_VERSION : '',
					'url'   => $wp_url,
					'isAccessibleForFree' => true,
					'keywords' => [
						esc_html__( 'WordPress forms', 'easy-form-builder' ),
						esc_html__( 'contact form plugin', 'easy-form-builder' ),
						esc_html__( 'payment form plugin', 'easy-form-builder' ),
						esc_html__( 'survey form plugin', 'easy-form-builder' ),
						esc_html__( 'email notification form', 'easy-form-builder' ),
					],
					'publisher' => [
						'@type' => 'Organization',
						'@id'   => $ws_url . '/#organization',
						'name'  => sprintf( esc_html__( 'Easy Form Builder - %s', 'easy-form-builder' ), esc_html__( 'Free WordPress Form Builder Plugin', 'easy-form-builder' ) ),
						'url'   => $ws_url
					],
					'inLanguage' => $lang
				]
			];

			$this->efb_print_schema_ld( $schema );
			$this->efb_register_head_hooks();
		} */

		public function field_maps_style_efb(){
			return '
					.map-container {
					width: 100%;
					height: 350px;
					margin-top: 10px;
					}
					.map {
					width: 100%;
					height: 100%;
					}
					.leaflet-control-container .leaflet-control-layers {
					background: white;
					padding: 10px;
					}
					.leaflet-control-container .custom-control {
						position: relative;
						bottom: 0;
						left: 0;
						width: 100%;
						background: rgba(248, 249, 250, 0.95);
						padding: 8px;
						box-shadow: 0 0 15px rgba(0, 0, 0, 0.2);
						z-index: 1000;
						border-radius: 8px;
					}

					.leaflet-control-container .custom-control .d-flex {
						gap: 6px;
						flex-wrap: wrap;
					}

					.leaflet-control-container .custom-control input,
					.leaflet-control-container .custom-control button,
					.leaflet-control-container .custom-control a {
						margin: 2px;
						border-radius: 6px;
						border: none;
						box-shadow: 0 2px 4px rgba(0,0,0,0.1);
						transition: all 0.2s ease;
					}

					.leaflet-control-container .custom-control a:hover {
						transform: translateY(-1px);
						box-shadow: 0 4px 8px rgba(0,0,0,0.15);
					}

					.leaflet-control-container .custom-control input[type="text"] {
						flex: 1;
						min-width: 150px;
						padding: 6px 12px;
						border: 1px solid #dee2e6;
						background: white;
					}

					@media (max-width: 576px) {
						.leaflet-control-container .custom-control {
							padding: 6px;
							width: calc(100% - 10px);
							margin: 5px;
						}

						.leaflet-control-container .custom-control .d-flex {
							gap: 4px;
						}

						.leaflet-control-container .custom-control input[type="text"] {
							min-width: 120px;
							font-size: 14px;
							padding: 8px 10px;
						}

						.leaflet-control-container .custom-control .btn {
							padding: 8px 10px;
							font-size: 14px;
						}

						.leaflet-control-container .custom-control .btn i {
							font-size: 16px;
						}

						.leaflet-control-container .custom-control .d-none.d-md-inline {
							display: none !important;
						}

						.leaflet-control-container .custom-control .d-inline.d-md-none {
							display: inline !important;
						}
					}

					@media (min-width: 577px) and (max-width: 992px) {
						.leaflet-control-container .custom-control {
							width: calc(100% - 10px);
							margin: 5px;
						}

						.leaflet-control-container .custom-control input[type="text"] {
							min-width: 180px;
						}
					}
					@media (min-width: 993px) {
						.leaflet-control-container .custom-control {
							width: auto;
							max-width: 90%;
						}

						.leaflet-control-container .custom-control input[type="text"] {
							min-width: 200px;
						}
					}
					.leaflet-control-container .error-message {
					color: red;
					margin: 5px;
					}
					#efb-create-map-btn {
					margin: 10px;
					}

					.custom-control.leaflet-control {
					display: flex;
					align-items: center;
					gap: 10px;
					}

					.custom-control.leaflet-control .efb {
					margin: 0 5px;
					}

					.custom-control.leaflet-control input[type="text"] {
					flex: 1;
					min-width: 200px;
					}

					.custom-control.leaflet-control a,
					.custom-control.leaflet-control input[type="text"] {
					margin: 0;
					}

					.custom-control.leaflet-control .error-message {
					margin-top: 10px;
					}
					.efb-searchbox .btn {
						transition: all 0.2s ease-in-out;
						border: none;
						font-weight: 500;
					}

					.efb-searchbox .btn:hover {
						transform: translateY(-1px);
						box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
					}

					.efb-searchbox .btn:active {
						transform: translateY(0);
					}

					.efb-searchbox .form-control {
						border: 1px solid #dee2e6;
						border-radius: 6px;
						transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
					}

					.efb-searchbox .form-control:focus {
						border-color: #86b7fe;
						outline: 0;
						box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
					}

					.efb .map-control-container {
					display: flex !important;
					flex-wrap: nowrap !important;
					align-items: center !important;
					justify-content: flex-start !important;
					gap: 4px !important;
					position: relative !important;
					}

					.efb .map-control-container .flex-shrink-0 {
					min-width: 32px !important;
					height: 32px !important;
					padding: 0 !important;
					flex-shrink: 0 !important;
					}

					.efb .map-control-container .form-control {
					min-width: 100px !important;
					height: 32px !important;
					font-size: 13px !important;
					padding: 4px 8px !important;
					flex-grow: 1 !important;
					}

					@media (max-width: 576px) {
					.efb .map-control-container {
						gap: 2px !important;
					}

					.efb .map-control-container .flex-shrink-0 {
						min-width: 30px !important;
						height: 30px !important;
					}

					.efb .map-control-container .form-control {
						font-size: 12px !important;
						padding: 3px 6px !important;
						height: 30px !important;
					}
					}
					';
		}

		public function field_mobile_style_efb(){
			return '
					.iti--inline-dropdown .iti__dropdown-content{
					z-index: 10000!important;
					}

					.mobile .btn-select-form.efb {	font-size: 10px;
					height: 100px;
					}
					.mobile .description-logo.efb {
					height: 65px;
					width: 65px;
					float: left;
					margin: 0px;
					}
					.mobile .title-holder.efb {	margin: 0px 0 12px 0;
					font-size: 32px !important;
					}
					.mobile .title-icon.efb {	font-size: 32px !important;
					}
					.mobile #efb-dd {	margin-top: 20%;
					margin-bottom: 20%;
					}
					.mobile .mobile-title.efb {	font-size: 20px !important;
					}
					.mobile .mobile-text.efb {	font-size: 14px !important;
					}

					.mobile .efblist.inplist.h-d-efb {	font-size: 15px !important;
					}

					.mobile div#sideBoxEfb {	height: 100vh;
					width: 70%}
					.mobile #content-efb {	margin-top: 10px;
					}';
		}

		public function efb_selected_loading_svg($state ,$color='#abb8c3'){
			switch($state){
				case 'loading':
					return '<svg class="efb-loading-spinner" width="24" height="24" viewBox="0 0 50 50">
								<circle class="efb-path" cx="25" cy="25" r="20" fill="none" stroke-width="5"></circle>
							</svg>';
				case 'check':
					return '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
								<polyline points="20 6 9 17 4 12"/>
							</svg>';
				case 'error':
					return '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
								<line x1="18" y1="6" x2="6" y2="18"/>
								<line x1="6" y1="6" x2="18" y2="18"/>
							</svg>';
				case 'cloud':
					return '<svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" class="bi bi-cloud-arrow-down" viewBox="0 0 16 16" style="width: 60%;">
							<path fill-rule="evenodd" d="M7.646 10.854a.5.5 0 0 0 .708 0l2-2a.5.5 0 0 0-.708-.708L8.5 9.293V5.5a.5.5 0 0 0-1 0v3.793L6.354 8.146a.5.5 0 1 0-.708.708l2 2z"/>
							<path d="M4.406 3.342A5.53 5.53 0 0 1 8 2c2.69 0 4.923 2 5.166 4.579C14.758 6.804 16 8.137 16 9.773 16 11.569 14.502 13 12.687 13H3.781C1.708 13 0 11.366 0 9.318c0-1.763 1.266-3.223 2.942-3.593.143-.863.698-1.723 1.464-2.383zm.653.757c-.757.653-1.153 1.44-1.153 2.056v.448l-.445.049C2.064 6.805 1 7.952 1 9.318 1 10.785 2.23 12 3.781 12h8.906C13.98 12 15 10.988 15 9.773c0-1.216-1.02-2.228-2.313-2.228h-.5v-.5C12.188 4.825 10.328 3 8 3a4.53 4.53 0 0 0-2.941 1.1z">
								<animate attributeName="opacity" values="1;0;1" dur="2s" repeatCount="indefinite" />
							</path>
							</svg>';
				case 'spinner':
					return '<svg class="efb-autofill-spinner" width="18" height="18" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
							<circle cx="12" cy="12" r="10" stroke="'.$color.'" stroke-width="3" fill="none" stroke-linecap="round">
							<animate attributeName="stroke-dasharray" values="0 63;32 63;63 63" dur="1s" repeatCount="indefinite"/>
							<animate attributeName="stroke-dashoffset" values="0;-20;-63" dur="1s" repeatCount="indefinite"/>
							</circle>
						</svg>';
				case 'dots':
					return '<svg viewBox="0 0 120 30" height="15px" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="xMidYMid meet">
							<circle cx="15" cy="15" r="15" fill="'.$color.'">
								<animate attributeName="r" from="15" to="9" begin="0s" dur="1s" values="15;9;15" calcMode="linear" repeatCount="indefinite"/>
							</circle>
							<circle cx="60" cy="15" r="9" fill="'.$color.'">
								<animate attributeName="r" from="9" to="15" begin="0.3s" dur="1s" values="9;15;9" calcMode="linear" repeatCount="indefinite"/>
							</circle>
							<circle cx="105" cy="15" r="15" fill="'.$color.'">
								<animate attributeName="r" from="15" to="9" begin="0.6s" dur="1s" values="15;9;15" calcMode="linear" repeatCount="indefinite"/>
							</circle>
						</svg>';
				case 'pulse':
					return '<svg width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
							<circle cx="12" cy="12" r="8" fill="none" stroke="'.$color.'" stroke-width="2">
								<animate attributeName="r" values="8;11;8" dur="1.5s" repeatCount="indefinite"/>
								<animate attributeName="opacity" values="1;0.5;1" dur="1.5s" repeatCount="indefinite"/>
							</circle>
							<circle cx="12" cy="12" r="4" fill="'.$color.'">
								<animate attributeName="r" values="4;6;4" dur="1.5s" repeatCount="indefinite"/>
							</circle>
						</svg>';
				case 'ripple':
					return '<svg width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
							<circle cx="12" cy="12" r="0" fill="none" stroke="'.$color.'" stroke-width="2">
								<animate attributeName="r" values="0;10" dur="1.5s" repeatCount="indefinite"/>
								<animate attributeName="opacity" values="1;0" dur="1.5s" repeatCount="indefinite"/>
							</circle>
							<circle cx="12" cy="12" r="0" fill="none" stroke="'.$color.'" stroke-width="2">
								<animate attributeName="r" values="0;10" dur="1.5s" begin="0.5s" repeatCount="indefinite"/>
								<animate attributeName="opacity" values="1;0" dur="1.5s" begin="0.5s" repeatCount="indefinite"/>
							</circle>
							<circle cx="12" cy="12" r="3" fill="'.$color.'"/>
						</svg>';
				case 'bounce':
					return '<svg width="60" height="20" viewBox="0 0 60 20" xmlns="http://www.w3.org/2000/svg">
							<circle cx="10" cy="10" r="5" fill="'.$color.'">
								<animate attributeName="cy" values="10;4;10" dur="0.6s" repeatCount="indefinite"/>
							</circle>
							<circle cx="30" cy="10" r="5" fill="'.$color.'">
								<animate attributeName="cy" values="10;4;10" dur="0.6s" begin="0.15s" repeatCount="indefinite"/>
							</circle>
							<circle cx="50" cy="10" r="5" fill="'.$color.'">
								<animate attributeName="cy" values="10;4;10" dur="0.6s" begin="0.3s" repeatCount="indefinite"/>
							</circle>
						</svg>';
				case 'orbit':
					return '<svg width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
							<circle cx="12" cy="12" r="3" fill="'.$color.'"/>
							<circle cx="12" cy="4" r="2" fill="'.$color.'">
								<animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="1s" repeatCount="indefinite"/>
							</circle>
							<circle cx="12" cy="4" r="1.5" fill="'.$color.'">
								<animateTransform attributeName="transform" type="rotate" from="180 12 12" to="540 12 12" dur="1.5s" repeatCount="indefinite"/>
							</circle>
						</svg>';
				case 'wave':
					return '<svg width="40" height="20" viewBox="0 0 40 20" xmlns="http://www.w3.org/2000/svg">
							<circle cx="5" cy="10" r="3" fill="'.$color.'">
								<animate attributeName="opacity" values="0.3;1;0.3" dur="1s" repeatCount="indefinite"/>
							</circle>
							<circle cx="15" cy="10" r="3" fill="'.$color.'">
								<animate attributeName="opacity" values="0.3;1;0.3" dur="1s" begin="0.2s" repeatCount="indefinite"/>
							</circle>
							<circle cx="25" cy="10" r="3" fill="'.$color.'">
								<animate attributeName="opacity" values="0.3;1;0.3" dur="1s" begin="0.4s" repeatCount="indefinite"/>
							</circle>
							<circle cx="35" cy="10" r="3" fill="'.$color.'">
								<animate attributeName="opacity" values="0.3;1;0.3" dur="1s" begin="0.6s" repeatCount="indefinite"/>
							</circle>
						</svg>';
				case 'hourglass':
					return '<svg width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
							<path d="M6 2h12v6l-4 4 4 4v6H6v-6l4-4-4-4V2z" fill="none" stroke="'.$color.'" stroke-width="2" stroke-linejoin="round">
								<animateTransform attributeName="transform" type="rotate" from="0 12 12" to="180 12 12" dur="1.5s" repeatCount="indefinite"/>
							</path>
						</svg>';

				default:
				case 'bars':
					return '<svg width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
							<rect x="2" y="6" width="4" height="12" fill="'.$color.'">
								<animate attributeName="height" values="12;20;12" dur="0.8s" repeatCount="indefinite"/>
								<animate attributeName="y" values="6;2;6" dur="0.8s" repeatCount="indefinite"/>
							</rect>
							<rect x="10" y="6" width="4" height="12" fill="'.$color.'">
								<animate attributeName="height" values="12;20;12" dur="0.8s" begin="0.2s" repeatCount="indefinite"/>
								<animate attributeName="y" values="6;2;6" dur="0.8s" begin="0.2s" repeatCount="indefinite"/>
							</rect>
							<rect x="18" y="6" width="4" height="12" fill="'.$color.'">
								<animate attributeName="height" values="12;20;12" dur="0.8s" begin="0.4s" repeatCount="indefinite"/>
								<animate attributeName="y" values="6;2;6" dur="0.8s" begin="0.4s" repeatCount="indefinite"/>
							</rect>
						</svg>';

			}

		}

}

if (!function_exists('Emsfb\\number_format_i18n_efb_fr')) {
	/** A count in the site's digits (number_format_i18n when WordPress is loaded). */
	function number_format_i18n_efb_fr($n) {
		return function_exists('number_format_i18n') ? number_format_i18n((int) $n) : (string) (int) $n;
	}
}
