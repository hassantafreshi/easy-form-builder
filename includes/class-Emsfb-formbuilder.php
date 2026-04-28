<?php
 namespace Emsfb;
    class Formbuilder {
       public $valj_efb;
       private $pro_efb = false;
	   public $pub_bg_button_color_efb='btn-primary';
	   public $package_type_efb = 0;
    private $mobile_pos = ['', 'col-sm-12', 'col-sm-12', 'col-sm-12'];
        public function __construct( $valj_efb, $pro_efb ) {
            $this->valj_efb =  $valj_efb;
            $this->pro_efb = $pro_efb;
			$this->package_type_efb = (int) get_option('emsfb_pro' ,2);
        }

	private function generateDescription_efb($rndm, $vj, $pos) {

		$mx = $pos[1] == 'col-md-4' || (isset($vj->message_align) && $vj->message_align != "justify-content-start") ? '' : 'mx-4';
		$msg_align = isset($vj->message_align) ? $vj->message_align : '';
		$msg_txt_color = isset($vj->message_text_color) ? $vj->message_text_color : '';
		$msg = isset($vj->message) ? $vj->message : '';
		return '<small id="' . $rndm . '-des" class="efb form-text d-flex fs-7 col-sm-12 efb ' . $mx . ' ' . $msg_align . ' ' . $msg_txt_color . ' ' . (isset($vj->message_text_size) ? $vj->message_text_size : '') . ' ">' . $msg . '</small>';
	}

	private function generateLabel_efb($rndm, $vj, $pos, $mobile_pos = null) {

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
			($mobile_pos !== null ? $mobile_pos[2] : 'col-sm-12'),
			'col-form-label',
			(isset($vj->hflabel) && $vj->hflabel == 1 ? 'd-none' : ''),
			$label_color,
			$label_align,
			$label_text_size
		];

		$label_class_str = implode(' ', array_filter($label_classes));

		return '<label for="' . $rndm . '_" class="' . $label_class_str . '" id="' . $rndm . '_labG"><span id="' . $rndm . '_lab" class="efb ' . $label_text_size . '">' . $vj->name . '</span>' . $required . '</label>';
	}

	private function generateTooltip_efb($rndm) {
		return '<small id="' . $rndm . '_-message" class="efb py-1 fs-7 tx ttiptext px-2" style="display:none"> ! </small>';
	}

	private function generateDivFId_efb($rndm, $pos, $mobile_pos = null) {
		return '<div class="efb ' . $pos[3] . ' ' . ($mobile_pos !== null ? $mobile_pos[3] : 'col-sm-12') . ' px-0 mx-0 ttEfb show" id="' . $rndm . '-f">';
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
				$placeholderElements = ['color', 'range', 'password', 'date'];

				$isTextType = in_array($elementId, $textElements);
				$isPlaceholderType = !in_array($elementId, $placeholderElements);

				$type = $isTextType ? 'text' : $elementId;
				$autocomplete = $this->generateAutocomplete_efb($elementId);
				$placeholder = $isPlaceholderType ? sprintf('placeholder="%s"', $vj->placeholder) : '';
				$telPattern = ($elementId === 'tel') ? 'pattern="^\+?(?:[0-9]|\s|\.|\(|\)|-){7,25}$"' : '';
				$lenAttributes = $this->generateLengthAttributes_efb($elementId, $vj);
				$classes = $elementId !== 'range' ? sprintf('form-control %s', $vj->el_border_color) : 'form-range';

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

				$fields['ui']  = $this->pro_efb ? $ui : $this->public_pro_message_efb($texts['tfnapca']);
				$fields['dataTag'] = $elementId;

				break;

			default:
				return false;
		}

		return $fields;
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

	private function generateTextInput_efb($type, $classes, $vj, $rndm, $desc, $label, $ttip, $div_f_id, $placeholder, $lenAttributes, $aire_describedby, $disabled, $autocomplete, $form_id, $telPattern = '') {

		$corener = isset($vj->corner) ? $vj->corner : 'efb-square';
		$required = ($vj->required == 1 || $vj->required == true) ? 'required' : '';
		$value = !empty($vj->value) ? 'value="' . esc_attr($vj->value) . '"' : '';
		$aria_required = ($vj->required == 1) ? 'true' : 'false';
		$readonly = ($disabled == "disabled") ? 'readonly' : '';
		$el_height = isset($vj->el_height) ? $vj->el_height : '';
		$el_text_color = isset($vj->el_text_color) ? $vj->el_text_color : '';
		$additional_classes = isset($vj->classes) ? str_replace(',', ' ', $vj->classes) : '';

		return sprintf(
			'%s %s %s <input type="%s" class="efb input-efb px-2 mb-0 emsFormBuilder_v w-100 %s %s %s %s %s efbField efb1 %s" data-id="%s-el" data-vid="%s" data-formid="%s" data-css="%s" id="%s_" %s %s aria-required="%s" aria-label="%s" %s autocomplete="%s" %s %s %s %s> %s',  $label,  $div_f_id,  $ttip,  $type,  $classes,  $el_height,  $corener,  $el_text_color,  $required,  $additional_classes,  $rndm,  $rndm,  $form_id,  $rndm,  $rndm,  $placeholder,  $value,  $aria_required,  $vj->name,  $aire_describedby,  $autocomplete,  $lenAttributes['maxlen'],  $lenAttributes['minlen'],  $readonly,  $telPattern,  $desc
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
                $css = '';
                foreach ($this->valj_efb as $i => $vj) {
                        if ($i === 0) continue;
                        if (!isset($vj->id_)) continue;
                        $id = $vj->id_;

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

                        if (isset($vj->mobile_label_position)) {
                                if ($vj->mobile_label_position === 'up') {
                                        $css .= '#' . $id . ' { flex-direction: column !important; }' . "\n";
                                        $css .= '#' . $id . '_labG { width: 100% !important; max-width: 100% !important; flex: 0 0 100% !important; }' . "\n";
                                        $css .= '#' . $id . '-f { width: 100% !important; max-width: 100% !important; flex: 0 0 100% !important; }' . "\n";
                                } else if ($vj->mobile_label_position === 'beside') {
                                        $css .= '#' . $id . ' { flex-direction: row !important; flex-wrap: wrap !important; }' . "\n";
                                        $css .= '#' . $id . '_labG { width: 33.33% !important; max-width: 33.33% !important; flex: 0 0 33.33% !important; }' . "\n";
                                        $css .= '#' . $id . '-f { width: 66.67% !important; max-width: 66.67% !important; flex: 0 0 66.67% !important; }' . "\n";
                                }
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
		$parent_col = 'col-sm-12';
		$label_col = 'col-sm-12';
		$input_col = 'col-sm-12';
		$parent_row = '';
		$mobile_size = isset($val->mobile_size) ? (int) $val->mobile_size : 100;
		switch ($mobile_size) {
			case 100: $parent_col = 'col-sm-12'; break;
			case 92:  $parent_col = 'col-sm-11'; break;
			case 83:
			case 80:  $parent_col = 'col-sm-10'; break;
			case 75:  $parent_col = 'col-sm-9';  break;
			case 67:  $parent_col = 'col-sm-8';  break;
			case 58:  $parent_col = 'col-sm-7';  break;
			case 50:  $parent_col = 'col-sm-6';  break;
			case 42:  $parent_col = 'col-sm-5';  break;
			case 33:  $parent_col = 'col-sm-4';  break;
			case 25:  $parent_col = 'col-sm-3';  break;
			case 17:  $parent_col = 'col-sm-2';  break;
			case 8:   $parent_col = 'col-sm-1';  break;
		}
		$label_col = 'col-sm-12';
		$input_col = 'col-sm-12';
		if (isset($val->label_position) && $val->label_position != "up") {
			$parent_row = 'row';
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

	private function public_pro_message_efb($text){
		$r = sprintf(
			'<div class="efb text-white fs-6 bg-danger px-1 rounded px-2">%s</div>',
			$text
		);

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
        $corner = isset($vj->corner) ? $vj->corner : 'efb-square';
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
			$vj->name,
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
		$corner = isset($vj->corner) ? $vj->corner : 'efb-square';
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
			$vj->name,
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
		$corner = isset($vj->corner) ? $vj->corner : 'efb-square';
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
			$vj->name,
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
		$pay = $elementId == "multiselect" ? '' : '';
		$currency = property_exists($vj, 'currency') ? $vj->currency : 'USD';
		$va = '';
		$sl = '';
		$optn = '<!--opt-->';

		$optns_obj = array_filter($this->valj_efb, function($obj) use ($rndm) {
			return isset($obj->parent) && $obj->parent === $rndm;
		});

		$s = isset($vj->value) && gettype($vj->value)!='string' && count($vj->value) > 0 ? true : false;

		foreach ($optns_obj as $i) {
			$c = "efb bi-square efb";
			if ($s && in_array($i->id_, $vj->value)) {
				$c = "bi-check-square text-info efb";
				$va .= $i->value . ',';
				$sl .= $i->id_ . ' @efb!';
			}

			$optn .= sprintf(
				'<tr class="efb efblist %s %s" data-id="%s" data-name="%s" data-row="%s" data-formid="%s" data-state="0" data-visible="1">
					<th scope="row" class="%s" data-formid="%s"></th>
					<td class="efb ms col-12" data-formid="%s">%s</td>
					%s
				</tr>',
				$vj->el_text_color,
				$pay,
				$rndm,
				$i->value,
				$i->id_,
				$formId,
				$c,
				$formId,
				$formId,
				$i->value,
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
		$corner = isset($vj->corner) ? $vj->corner : 'efb-square';
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
		$corner = isset($vj->corner) ? $vj->corner : 'efb-square';
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

		$corner = isset($vj->corner) ? $vj->corner : 'efb-square';
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
				<div class="efb btn-group btn-group-toggle w-100 col-md-12 col-sm-12 %7$s" data-toggle="buttons" data-id="%4$s-id" id="%4$s_yn">
					<label for="%4$s_1" data-lid="%4$s" data-value="%8$s" onclick="yesNoGetEFB(\'%8$s\', \'%4$s\', \'%4$s_b_1\')" class="efb btn %9$s %10$s %11$s %12$s yesno-efb left-efb %13$s %14$s" id="%4$s_b_1">
						<input type="radio" name="%4$s" data-type="switch" class="efb opButtonEfb elEdit emsFormBuilder_v efb" data-vid="%4$s" data-id="%4$s-id" id="%4$s_1" value="%8$s" data-formid="%5$s"><span id="%4$s_1_lab">%8$s</span>
					</label>
					<span class="efb border-right border border-light efb"></span>
					<label for="%4$s_2" data-lid="%4$s" data-value="%15$s" onclick="yesNoGetEFB(\'%15$s\', \'%4$s\', \'%4$s_b_2\')" class="efb btn %9$s %10$s %11$s %12$s yesno-efb right-efb %13$s %14$s" id="%4$s_b_2">
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
			$button2Text
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
			setTimeout(function() {
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
			}, 1000);',
			$rndm,
			$vj->id_,
			$vj->name,
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
			'<input type="phone" class="efb input-efb intlPhone px-2 mb-0 emsFormBuilder_v form-control %1$s %2$s %3$s %4$s %5$s efbField efb1 %6$s" data-css="%7$s" data-id="%7$s-el" data-formid="%13$s" data-vid="%7$s" id="%7$s_" aria-required="%8$s" aria-label="%9$s" %10$s %11$s %12$s data-utilsjs="%14$s" %15$s>
			<input type="phone" class="efb input-efb intlPhone px-2 mb-0 emsFormBuilder_v form-control %1$s %2$s %3$s %4$s %5$s efbField d-none efb1 %6$s" data-css="%7$s" data-id="%7$s-el" data-formid="%13$s" data-vid="%7$s" id="%7$s-code" placeholder="verify" %11$s %12$s %10$s data-utilsjs="%14$s"  %15$s>',
			$vj->el_border_color,
			$vj->el_height,
			$corner,
			$vj->el_text_color,
			$required,
			$classes,
			$rndm,
			$ariaRequired,
			$vj->name,
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
				$vj->name,
				$vj->amount,
				$vj->type,
				$value
			);
		}
		$buttonSubmit = sprintf(
			'<button id="%1$s-btn" type="submit" class="efb d-none">Submit</button>',
			$rndm
		);

		return [$inputPhone  . $buttonSubmit ,$js];
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
		$corner = isset($vj->corner) ? $vj->corner : 'efb-square';
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

		return sprintf(
			'<div class="efb icon efb">
				<i class="efb fs-3 %1$s %2$s" id="%3$s_icon"></i>
			</div>
			<h6 id="%3$s_txt" class="efb text-center m-1 fs-6">%4$s %5$s</h6>
			<span class="efb fs-7 my-1">%6$s</span>
			<div class="efb btn %7$s efb-btn-lg fs-6 mb-1" id="%3$s_b" %8$s>
				<i class="efb bi-upload mx-2 fs-6"></i>%9$s
			</div>
			<input type="file" hidden="" accept="%10$s" data-type="dadfile" data-vid="%3$s" data-id="%3$s" class="efb emsFormBuilder_v %11$s dadfile" id="%3$s_" data-id="%3$s-el" data-formid="%13$s" %12$s %8$s>',
			$vj->icon,
			$vj->icon_color,
			$vj->id_,
			$texts['dragAndDropA'],
			$name_type_file ,
			$texts['or'],
			$vj->button_color,
			$disabled,
			$texts['browseFile'],
			$fileTypeAttr,
			$requiredClass,
			$readonlyAttr,
			$form_id
		);
	}
	public function dadfile_el_pro_efb($previewSate, $rndm, $vj, $form_id, $texts) {
		$corner = property_exists($vj, 'corner') ? $vj->corner : 'efb-square';
		$disabled = property_exists($vj, 'disabled') && $vj->disabled == true ? 'disabled' : '';

		$ui = $this->ui_dadfile_efb($vj, $previewSate, $form_id, $texts , $disabled, $corner);
		return sprintf(
			'<div class="efb mb-3" id="uploadFilePreEfb" data-formid="%s">
				<label for="%s_" class="efb form-label">
					<div class="efb dadFile-efb py-0 %s %s %s efb1 %s %s"  id="%s_box" aria-describedby="%s" %s>
						%s
					</div>
				</label>
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
		$amount =$this->formatPrice_efb(0, $currency);
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
		<a class="efb  btn my-2 efb p-2 efb-square h-l-efb  efb-btn-lg float-end text-decoration-none disabled '.$this->pub_bg_button_color_efb.' text-white" id="btnStripeEfb" data-formid="'.$form_id.'">'.$texts['payNow'].'</a>
		<div class="efb  bg-light border-d rounded-3 p-2 bg-muted" id="statusStripEfb" style="display: none"></div>
		</div>
		</div>
		<!-- end stripe -->
		';
	}

	public function add_ui_paypal_efb($rndm, $form_id, $texts, $currency = 'USD', $charge_class = '', $sub = '') {

		$currency = $this->valj_efb[0]->currency;
		$amount =$this->formatPrice_efb(0, $currency);

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
					<a class="efb btn efb-square h-l-efb btn-primary text-white text-decoration-none disabled w-100 paypalEfb"
					onclick="startPaymentPayPal_efb('.intval($form_id).')"
					id="paypalEfb"
					data-formid="'.$form_id.'">'.$texts['payNow'].'</a>
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
				<a class="efb btn my-2 efb p-2 efb-square h-l-efb btn-primary text-white text-decoration-none disabled w-100" onclick="pay_persia_efb('.$form_id.')" id="persiaPayEfb"  data-formid="'.$form_id.'">'.$texts['payment'].'</a>
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
		$currency_details = $this->get_currency_details_efb($currency);
		$amount =$this->formatPrice_efb($amount, $currency);

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

	public function formatPrice_efb($amount, $currency) {

		$currency_details = $this->get_currency_details_efb($currency);
    	$formatted_amount = number_format_i18n($amount, $currency_details['d']);
		if (is_rtl()) {
			return $formatted_amount . ' ' . $currency_details['s'];
		} else {
			return $currency_details['s'] . '' . $formatted_amount;
		}

    }

	public function get_currency_details_efb($currency) {
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

        $pay = $elementId != "paySelect" ? '' : 'pay';
        $options = '';
        $optns_obj = array_filter($this->valj_efb, function($obj) use ($rndm) {
            return isset($obj->parent) && $obj->parent === $rndm;
        });
		$is_selected = false;

        foreach ($optns_obj as $i) {
            $selected = ($vj->value == $i->id_ || (property_exists($i, 'id_old') && $vj->value == $i->id_old)) ? 'selected' : '';
			if ($selected == 'selected') {
				$is_selected = true;
			}
            $options .= sprintf(
                '<option class="efb %s emsFormBuilder_v efb" data-id="%s" data-op="%s" value="%s" %s>%s</option>',
                $vj->el_text_color,
                $i->id_,
                $i->id_,
                $i->value,
                $selected,
                $i->value
            );
        }

        $required = ($vj->required == 1 || $vj->required == true) ? 'required' : '';
        $readonly = $previewSate != true ? 'readonly' : '';
        $ariaRequired = $vj->required == 1 ? 'true' : 'false';
        $ariaDescribedBy = !empty($vj->message) ? 'aria-describedby="' . $vj->id_ . '-des"' : '';
        $disabled = property_exists($vj, 'disabled') && $vj->disabled == true ? 'disabled' : '';
        $corner = isset($vj->corner) ? $vj->corner : 'efb-square';
        $el_height = isset($vj->el_height) ? $vj->el_height : '';
        $el_border_color = isset($vj->el_border_color) ? $vj->el_border_color : '';

        $ui = sprintf(
            '%s
            <div class="efb %s ' . $this->mobile_pos[3] . ' px-0 mx-0 ttEfb show efb1 %s" data-css="%s" id="%s-f" data-id="%s-el" data-formid="%s">
                %s
                <select data-formid="%s" class="efb form-select efb emsFormBuilder_v w-100 %s %s %s %s %s w-100" data-vid="%s" id="%s_options" aria-required="%s" aria-label="%s" %s %s %s>
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
            $vj->name,
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
			'payment' => ['amount' => true], 'file' => ['url' => true], 'address_line'=>true,
			'dadfile' => ['url' => true], 'esign' => true, 'maps' => true,
			'color' => true, 'range' => true, 'number' => true, 'prcfld' => true,
			'checkbox' => true, 'table_matrix' => true, 'trmCheckbox' => true,
			'ttlprc' => true, 'smartcr' => true, 'pointr5' => true,'tel'=>true,
			'pointr10' => true, 'zarinPal' => true, 'stripe' => ['amount' => true],
			'yesNo' => true, 'payMultiselect' => true, 'rating' => true, 'text'=>true, 'password'=>true
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
		$wp_text = esc_html__('WordPress', 'easy-form-builder');
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
									esc_html__('Powered by %1$sEasy Form Builder%2$s . %3$sWhiteStudio.team%4$s.', 'easy-form-builder'),
									'<a href="https://wordpress.org/plugins/easy-form-builder/" title="' . esc_attr__('Easy Form Builder WordPress Plugin', 'easy-form-builder') . '" rel="sponsored noopener">',
									'</a>',
									'<a href="https://whitestudio.team" rel="sponsored noopener">',
									'</a>'
								) .
							  '</h2>
							';

			if (strpos(get_locale(), 'fa') === 0 || strpos(get_locale(), 'ar') === 0) {
				$copyRight .= '<a href="https://easyformbuilder.ir" target="_blank" style="font-size: 8px;' . $style . '" rel="sponsored noopener">فرم ساز آسان</a>ساخته شده بوسیله<a href="https://fa.wordpress.org/plugins/easy-form-builder/" target="_blank">افزونه فرم ساز رایگان وردپرس</a>' . $fr;
			} else if (strpos(get_locale(), 'en') !== 0) {
				$copyRight .= '<a href="https://'.$f.'.wordpress.org/plugins/easy-form-builder/" target="_blank" style="font-size: 8px;' . $style . '" rel="sponsored noopener">'.$efb.' '. $wp_text.'</a>' . $fr;
			}
			return $copyRight .'</aside></div>';
		}else if($this->package_type_efb==2){

			add_action('wp_footer',  [$this, 'efb_output_schema_free'], 20);
		}
		return '<!--efb-->';
	}

	function add_buttons_zone_efb($state, $id, $valj_efb, $efb_var, $formId) {

		$dis = '';
		$t = array_search('stripe', array_column($valj_efb, 'type'));
		$t = $t === false ? array_search('persiaPay', array_column($valj_efb, 'type')) : $t;
		$t = $t === false ? array_search('paypal', array_column($valj_efb, 'type')) : $t;
		$t = $t !== false ? $valj_efb[$t]->step : 0;

		if ($valj_efb[0]->type == "payment" && $t == 0) {
			return  "<script>alert('".esc_html__('Easy Form Builder' , 'easy-form-builder'). ": " .esc_html__('This form requires a payment method. Please add one or change the form type.' , 'easy-form-builder')."');</script>";
		}

		$corner = property_exists($valj_efb[0], 'corner') ? $valj_efb[0]->corner : 'efb-square';
		$btns_align = property_exists($valj_efb[0], 'btns_align') ? $valj_efb[0]->btns_align . ' mx-3' : 'justify-content-center';
		$icon_spacing_class = is_rtl() ? 'ms-2' : 'me-2';

		$prev_icon = strlen($valj_efb[0]->button_Previous_icon) > 3 && $valj_efb[0]->button_Previous_icon != 'bi-undefined' &&  $valj_efb[0]->button_Previous_icon!='bXXX' ? sprintf('<i class="efb %s %s %s %s" id="button_group_icon"></i>', $valj_efb[0]->button_Previous_icon, $icon_spacing_class, $valj_efb[0]->icon_color, $valj_efb[0]->el_height) : '';
		$next_icon = strlen($valj_efb[0]->button_Next_text) > 3 && $valj_efb[0]->button_Next_text != 'bi-undefined' && $valj_efb[0]->button_Next_text!='bXXX' ? sprintf('<i class="efb %s %s %s %s" id="button_group_icon"></i>', $valj_efb[0]->button_Next_icon, $icon_spacing_class, $valj_efb[0]->icon_color, $valj_efb[0]->el_height) : '';
		$class_disabled = '';

		$s = sprintf(
			'<div class="efb d-flex %s %s text-center efb mx-3" id="f_btn_send_efb" data-tag="buttonNav" data-formid="%s">
				<a id="btn_send_efb" role="button" class="efb text-decoration-none mx-0 btn p-2 %s %s %s %s %s efb-btn-lg btn_send_efb" data-formid="%s" data-currentstep="1" onclick="btn_navigate_handle_efb(\'%s\' ,\'%s\' ,\'%s\',this)">%s<span id="button_group_button_single_text" class="efb %s" >%s</span></a>
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
				<a id="prev_efb"  data-formid="%s" data-currentstep="1" role="button" class="efb text-decoration-none btn p-2  %s %s %s efb-btn-lg m-1 d-none prev_efb" onclick="btn_navigate_handle_efb(\'%s\' ,\'%s\' ,\'%s\',this)">%s<span id="button_group_Previous_button_text" class="efb %s">%s</span></a>
				<a id="next_efb"  data-formid="%s" data-currentstep="1" role="button" class="efb text-decoration-none btn %s p-2 %s %s %s efb-btn-lg m-1 next_efb" onclick="btn_navigate_handle_efb(\'%s\' ,\'%s\' ,\'%s\',this)"><span id="button_group_Next_button_text" class="efb %s">%s</span>%s</a>
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

		return sprintf('<div class="efb footer-test p-1">%s</div>', $state == 0 ? $s : $d);
	}

	public function addNewElement_efb($i, $rndm,$form_id,$texts) {
		$pro = $this->pro_efb == 1 || $this->pro_efb == true ? true : false;
		$nfield = ['html','stripe','paypal','persiapay','persiaPay','zarinPal','heading','link'];
		$element_Id = $this->valj_efb[$i]->id_;
		$elementId = $this->valj_efb[$i]->type;
		$currency = isset($this->valj_efb[0]->currency) ? $this->valj_efb[0]->currency : 'USD';
		$pos = array("", "", "", "");
		$indexVJ = $i;
		$position_l_efb = is_rtl() ? "end" : "start";
		$vj = $this->valj_efb[$indexVJ];

		if(in_array($elementId, ["option","r_matrix"])) return;

		if (!in_array($elementId, ["html", "register", "login", "subscribe", "survey"])) {
			$pos = $this->get_position_col_el($vj, false);
		}
		  $mobile_pos = $this->get_position_col_mobile_el($vj);
            $this->mobile_pos = $mobile_pos;
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
			$corner= isset($vj->corner) ? $vj->corner : 'efb-square';
			switch ($vj->type) {

				case 'pdate':
				case 'ardate':
					$isPdate = $elementId === 'pdate';
					$inputClass = $isPdate ? 'efb pdpF2 pdp-el' : 'efb hijri-picker';

					$readonlyAttr = $elementId === 'ardate' && $disabled === "disabled" ? 'readonly' : '';
					$valueAttr = !empty($vj->value) ? sprintf('value="%s"', esc_attr($vj->value)) : '';
					$requiredAttr = ($vj->required == 1 || $vj->required == true) ? 'required' : '';
					$ariaRequiredAttr = ($vj->required == 1) ? 'true' : 'false';

					$ui = sprintf(
						'%1$s %2$s %3$s <input type="text" class="%4$s input-efb px-2 mb-0 emsFormBuilder_v w-100 %5$s %6$s %7$s %8$s %9$s efbField efb1 %10$s" data-css="%11$s" data-id="%11$s-el" data-vid="%11$s" data-formid="%12$s" id="%11$s_" %13$s aria-required="%14$s" aria-label="%15$s" %16$s %17$s> %18$s',
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
						$vj->name,
						$aire_describedby,
						$readonlyAttr,
						$desc
					);

					$dataTag = $elementId;
					$ui = $pro ? $ui : $this->public_pro_message_efb($texts['tfnapca']);

					if($isPdate){
						if(!is_dir(EMSFB_PLUGIN_DIRECTORY."/vendor/persiadatepicker")) {
							if($efbFunction === null)$efbFunction = get_efbFunction();
							$efbFunction->download_all_addons_efb();
							return "<div id='body_efb' class='efb card-public row pb-3 efb px-2'  style='color: #9F6000; background-color: #FEEFB3;  padding: 5px 10px;'> <div class='efb text-center my-5'><h2 style='text-align: center;'></h2><h3 class='efb warning text-center text-darkb fs-4'>".esc_html__('We have made some updates. Please wait a few minutes before trying again.', 'easy-form-builder')."</h3><p class='efb fs-5  text-center my-1 text-pinkEfb' style='text-align: center;'><p></div></div>";
						}else{
							require_once(EMSFB_PLUGIN_DIRECTORY."/vendor/persiadatepicker/persiandate.php");
							$persianDatePicker = new persianDatePickerEFB() ;
						}
					}else{
						if(!is_dir(EMSFB_PLUGIN_DIRECTORY."/vendor/arabicdatepicker")) {
							if($efbFunction === null)$efbFunction = get_efbFunction();
							$efbFunction->download_all_addons_efb();
							return "<div id='body_efb' class='efb card-public row pb-3 efb px-2'  style='color: #9F6000; background-color: #FEEFB3;  padding: 5px 10px;'> <div class='efb text-center my-5'><h2 style='text-align: center;'></h2><h3 class='efb warning text-center text-darkb fs-4'>".esc_html__('We have made some updates. Please wait a few minutes before trying again.', 'easy-form-builder')."</h3><p class='efb fs-5  text-center my-1 text-pinkEfb' style='text-align: center;'><p></div></div>";
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
						$vj->name,
						$aire_describedby,
						$readonlyAttr,
						$temp ?: 50,
						$desc
					);

					$dataTag = $elementId;
				break;
				case 'file':
					$ui = sprintf('
						%1$s
						%2$s
						%3$s
						<input type="%4$s" class="efb input-efb px-2 py-1 emsFormBuilder_v w-100 %5$s %6$s %7$s efbField efb1 %8$s %16$s" data-css="%9$s" data-vid="%9$s" data-id="%9$s-el" data-formid="%15$s" id="%9$s_" aria-required="%10$s" aria-label="%11$s" %12$s %13$s>
						%14$s',
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
						$vj->name,
						$aire_describedby,
						($disabled == "disabled" ? 'readonly' : ''),
						$desc,
						$form_id,
						$corner
					);
					$dataTag = $elementId;
					break;

				case "textarea":
					$minlen = isset($vj->milen) && $vj->milen > 0 ? 'minlength="' . $vj->milen . '"' : '';

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
							$ttip,
							$vj->placeholder,
							($vj->required == 1 ? 'required' : ''),
							$vj->el_height,
							$corner,
							$vj->el_text_color,
							$classes,
							'',
							esc_attr($vj->value),
							($vj->required == 1 ? 'true' : 'false'),
							esc_attr($vj->name),
							$aire_describedby,
							$disabled,
							$minlen,
							esc_html($this->text_nr_efb($vj->value, 0)),
							$desc,
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
						$optn=$this->public_pro_message_efb($texts['tfnapca']);
					}
					wp_register_script('intlTelInput-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/intlTelInput.min-efb.js', array(), EMSFB_PLUGIN_VERSION, true);
					wp_enqueue_script('intlTelInput-js');
					wp_register_style('intlTelInput-css', EMSFB_PLUGIN_URL . 'includes/admin/assets/css/intlTelInput.min-efb.css',true,EMSFB_PLUGIN_VERSION);
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

					$el =$pro ? $this->dadfile_el_pro_efb(true, $element_Id, $vj,$form_id,$texts) : $this->public_pro_message_efb($texts['tfnapca']);
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
						foreach ($optns_obj as $i) {
							$checked = "";
							if ((strpos($tp, "radio") !== false || (strpos($tp, "select") !== false && strpos($tp, "multi") === false)) && ($parent->value == $i->id_ || (isset($i->id_old) && $parent->value == $i->id_old))) {
								$checked = "checked";
							} elseif ((strpos($tp, "multi") !== false || strpos($tp, "checkbox") !== false) && is_array($parent->value) && array_search($i->id_, $parent->value) !== false) {
								$checked = "checked";
							}

							$imageRadio = $elementId == "imgRadio" ? $this->fun_imgRadio_efb($i->id_, $i->src, $i,true, $texts) : '';
							$prc = isset($i->price) ? intval($i->price) : 0;
							if($pay!='') $prc = $this->formatPrice_efb($prc, $currency );
							$optn .= sprintf(
								'<div class="efb form-check %s %s %s efb1 %s mt-1" data-css="%s" data-parent="%s" data-id="%s" data-formid="%s" id="%s-v">
									<input class="efb form-check-input emsFormBuilder_v %s %s" data-tag="%s" data-type="%s" data-vid="%s" type="%s" name="%s" value="%s" id="%s" data-id="%s-id" data-formid="%s" data-op="%s" %s %s %s>
									%s
									%s
									%s
								</div>',
								$col,
								$elementId,
								$temp,
								str_replace(',', ' ', $vj->classes),
								$element_Id,
								$i->parent,
								$i->id_,
								$form_id,
								$i->id_,
								$pay,
								$vj->el_text_size,
								$dataTag,
								$vtype,
								$element_Id,
								$vtype,
								$i->parent,
								$i->value,
								$i->id_,
								$i->id_,
								$form_id,
								$i->id_,
								'',
								$disabled,
								$checked,
								$elementId != 'imgRadio' ? sprintf('<label class="efb %s %s %s %s hStyleOpEfb" id="%s_lab" for="%s">%s</label>', isset($vj->pholder_chl_value) ? 'col-8' : '', $vj->el_text_color, $vj->el_height, $vj->label_text_size, $i->id_, $i->id_, $this->fun_get_links_from_string_Efb($i->value, true)) : $imageRadio,
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
								<div class="efb %s %s %s efb1 %s" data-css="%s" %s id="%s_options">
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
							$optn,
							$desc
						);
				break;
				case 'esign':

					$ui = '
					' . $label . '
					' . $ttip . '
					' . ($pro == true ? $this->esign_el_pro_efb(true, $pos, $rndm, $vj, $desc,$form_id,$texts['updateUrbrowser']) : $this->public_pro_message_efb($texts['tfnapca']));

					$ui.= sprintf(
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
						json_encode($vj)
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
						$ui = $this->public_pro_message_efb($texts['tfnapca']);
					}

					$style = $this->field_maps_style_efb();
					$dataTag = "maps";
					break;
				break;

				case 'rating':

					$ui = $pro == true ? $this->rating_el_pro_efb(true, $pos, $rndm, $vj, $desc, $form_id, $label, $ttip, $aire_describedby, $texts) : $this->public_pro_message_efb($texts['tfnapca']);
					$dataTag = $elementId;
				break;
                case 'select':
                case 'paySelect':

                    $ui = $this->generate_select_efb($elementId, $rndm, $vj, $pos, $form_id, $texts, true ,$desc,$label,$ttip,$aire_describedby);
                    $dataTag = $elementId;
                break;
				case 'conturyList':
				case 'country':

					$ui =$pro == true ? $this->generate_country_list_efb($rndm, $vj, $pos, $form_id, $texts ,$desc,$label,$ttip,$aire_describedby): $this->public_pro_message_efb($texts['tfnapca']);

					$dataTag = $elementId;
				break;
				case 'stateProvince':
				case 'statePro':
					$ui = $pro == true ? $this->generate_state_province_efb($rndm, $vj, $pos, $form_id, $texts ,$desc,$label,$ttip,$aire_describedby) : $this->public_pro_message_efb($texts['tfnapca']);
					$dataTag = $elementId;
				break;
				case 'city':
				case 'cityList':
					$ui = $pro == true ? $this->generate_city_list_efb($rndm, $vj, $pos, $form_id, $texts ,$desc,$label,$ttip,$aire_describedby) : $this->public_pro_message_efb($texts['tfnapca']);
					$dataTag = $elementId;
				break;
				case 'multiselect':
				case 'payMultiselect':

					$ui = $this->generate_multiselect_efb($elementId, $rndm, $vj, $pos, $form_id, $texts,$desc,$label,$ttip,$aire_describedby) ;
					$dataTag = $elementId;
				break;

				case 'html':

					$ui = $pro == true ? $this->generate_html_code_efb($rndm, $vj, $pos, $form_id, $texts, true) : $this->public_pro_message_efb($texts['tfnapca']);
					$dataTag = $elementId;
				break;
				case 'heading':

					$ui = $pro == true ? $this->generate_heading_efb($rndm, $pos, $vj, $form_id) : $this->public_pro_message_efb($texts['tfnapca']);
					$dataTag = $elementId;

				break;
				case 'link':

					$ui = $pro == true ? $this->generate_link_efb(true, $pos, $rndm, $vj, $form_id) : $this->public_pro_message_efb($texts['tfnapca']);
					$dataTag = $elementId;
				break;
				case 'yesNo':
					if($pro!==true && $pro!==1){
						$ui =$this->public_pro_message_efb($texts['tfnapca']);
						break;
					}

					$r = $this->generate_yes_no_efb(true, $pos, $rndm, $vj, $form_id);

					$ui = '' . $label . $ttip .  $r  .$desc ;
					$dataTag = $elementId;
				break;
				case 'pointr5':
					if($pro!==true && $pro!==1){
						$ui =$this->public_pro_message_efb($texts['tfnapca']);
						break;
					}

					$r  = $this->pointer5_el_pro_efb(true, $vj, $form_id);

					$ui = "" . $label ."<div class='efb $pos[3] {$this->mobile_pos[3]} px-0 mx-0 ttEfb show'  id='$rndm-f'> ". $ttip .  $r  .$desc ;
					$dataTag = $elementId;
				break;
				case 'pointr10':

					if($pro!==true && $pro!==1){
						$ui =$this->public_pro_message_efb($texts['tfnapca']);
						break;
					}
					$r  = $this->pointer10_el_pro_efb(true, $vj, $form_id);
					$ui = "" . $label ."<div class='efb $pos[3] {$this->mobile_pos[3]} px-0 mx-0 ttEfb show'  id='$rndm-f'> ". $ttip .  $r  .$desc ;
					$dataTag = $elementId;
				break;
				case 'smartcr':
					if($pro!==true && $pro!==1){
						$ui =$this->public_pro_message_efb($texts['tfnapca']);
						break;
					}
					$r  = $this->smartcr_el_pro_efb(true, $vj, $form_id);
					$ui = "" . $label ."<div class='efb $pos[3] {$this->mobile_pos[3]} px-0 mx-0 ttEfb show'  id='$rndm-f'> ". $ttip .  $r  .$desc ;
					$dataTag = $elementId;
				break;
				case 'table_matrix':
					if($pro!==true && $pro!==1){
						$ui =$this->public_pro_message_efb($texts['tfnapca']);
						break;
					}

					$ui  = $this->table_matrix_el_pro_efb($elementId, $vj, $rndm, $position_l_efb, true, $aire_describedby, $label, $ttip, $desc,$form_id,$pos);
					$dataTag = $elementId;

				break;
				case 'prcfld':
					if($pro!==true && $pro!==1){
						$ui =$this->public_pro_message_efb($texts['tfnapca']);
						break;
					}
					$maxlen = (property_exists($vj, 'mlen') && $vj->mlen > 0) ? 'maxlength="' . $vj->mlen . '"' : '';

					$minlen = (property_exists($vj, 'milen') && $vj->milen > 0) ? 'minlength="' . $vj->milen . '"' : '';

					$dataTag = (!property_exists($this->valj_efb[0], 'currency')) ? 'usd' : $this->valj_efb[0]->currency;

					$classes = $this->get_currency_details_efb($dataTag);

					$dataTagHtml = '<span class="efb input-group-text crrncy-clss">' . $classes['s'] . '</span>';

					$classes = 'form-control ' . $vj->el_border_color;

					$ui = sprintf(
						'%s
						<div class="efb %s ' . $this->mobile_pos[3] . ' px-0 mx-0 ttEfb show" id="%s-f" data-formId="%s">
							%s
							<div class="efb input-group m-0 p-0">
								%s
								<input type="number" class="efb input-efb px-2 mb-0 payefb emsFormBuilder_v %s %s %s %s %s efbField efb1 %s" data-id="%s-el" data-vid="%s" data-css="%s" id="%s_" placeholder="%s" data-formid="%s" %s %s %s %s %s %s>
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
						$ui =$this->public_pro_message_efb($texts['tfnapca']);
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
						$ui =$this->public_pro_message_efb($texts['tfnapca']);
						break;
					}
					$sub = $texts['onetime'];
					$cl = 'one';
					if ($this->valj_efb[0]->paymentmethod != 'charge') {
						$n = $this->valj_efb[0]->paymentmethod.'ly';
						$sub = $texts[$n];
						$cl = $this->valj_efb[0]->paymentmethod;
					}

					$ui = $this->add_ui_stripe_efb($rndm , $cl, $sub,$form_id,$texts);

					$dataTag = $elementId;

				break;
				case 'paypal':
					if($pro!==true && $pro!==1){
						$ui =$this->public_pro_message_efb($texts['tfnapca']);
						break;
					}
					$sub = $texts['onetime'];
					$cl = 'one';
						if ($this->valj_efb[0]->paymentmethod != 'charge') {
						$n = $this->valj_efb[0]->paymentmethod.'ly';
						$sub = $texts[$n];
						$cl = $this->valj_efb[0]->paymentmethod;
					}

					$ui = $this->add_ui_paypal_efb($rndm , $form_id,$texts ,'USD' , $cl , $sub);
					$dataTag = $elementId;
				break;
				case "persiaPay":
				case "zarinPal":

					if($pro!==true && $pro!==1){
						$ui =$this->public_pro_message_efb($texts['tfnapca']);
						break;
					}

					if (has_action('efb_enqueue_persia')){
						do_action('efb_enqueue_persia');
					}

					$ui = $this->add_ui_zp_efb($rndm , $form_id,$texts);
					$dataTag = $elementId;

				break;

			}
		}

		if ($vj->type != "form" && $dataTag != "step" && $vj->type != 'option') {
			$hidden = isset($vj->hidden) && $vj->hidden == 1 ? 'd-none' : '';
			$tagId = in_array($elementId, ["firstName", "lastName", "address", "address_line", "postalcode"]) ? 'text' : $elementId;
			$tagT = in_array($elementId, ["esign", "yesNo", "rating"]) ? '' : 'def';
			$stepNo = (int)$vj->step - 1;
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

			if (!in_array($elementId, ['option', 'html', 'stripe', 'heading', 'link','conturyList','country','stateProvince','statePro','city','cityList','maps','ttlprc'])) {
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
			json_encode($vj),
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
		'clear'       => esc_html__('Clear All', 'easy-form-builder'),
		'noErrors'    => esc_html__('No errors detected', 'easy-form-builder'),
		'warning'     => esc_html__('These errors may interfere with forms built using Easy Form Builder.', 'easy-form-builder'),
		'adminOnly'   => esc_html__('This panel is only visible to site administrators.', 'easy-form-builder'),
		'easyformbuilder' => esc_html__('Easy Form Builder', 'easy-form-builder'),
		'warningBadge' => esc_html__('Warning', 'easy-form-builder'),
		'jqueryMissing' => esc_html__('jQuery Dependency Issue', 'easy-form-builder'),
		'jqueryMessage' => esc_html__('jQuery is not properly loaded or available. AJAX and interactive form features may not work correctly.', 'easy-form-builder'),
	];

	$value = '
	(function() {
		"use strict";

		const EFB_ERROR_PANEL = {
			errors: [],
			errorKeys: new Set(),
			isOpen: false,
			panel: null,
			badge: null,

			t: ' . wp_json_encode($t) . ',

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
					<span class="efb-error-label">${this.t.warningBadge}</span>
					<span class="efb-badge-tooltip">${this.t.adminOnly}</span>
				`;
				const isRtl = document.documentElement.dir === "rtl" || document.body.dir === "rtl" || getComputedStyle(document.documentElement).direction === "rtl";
				this.isRtl = isRtl;
				this.badge.style.cssText = `
					all: initial !important;
					position: fixed !important; top: 35px !important; ${isRtl ? "right" : "left"}: 30px !important;
					z-index: 999999 !important;
					background: #dc3545 !important;
					color: #fff !important; padding: 12px 18px !important; border-radius: 50px !important;
					cursor: pointer !important; display: flex !important; align-items: center !important; gap: 10px !important;
					font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
					font-size: 13px !important; font-weight: 600 !important;
					box-shadow: 0 4px 15px rgba(220,53,69,0.4) !important;
					opacity: 0 !important; pointer-events: none !important; visibility: hidden !important;
					direction: ltr !important; box-sizing: border-box !important;
					line-height: normal !important; text-transform: none !important;
					text-decoration: none !important; letter-spacing: normal !important;
					margin: 0 !important; float: none !important; border: none !important;
					min-height: 0 !important; max-height: none !important;
					min-width: 0 !important; height: auto !important; width: auto !important;
					overflow: visible !important;
				`;
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
							<button class="efb-btn-clear" onclick="EFB_ERROR_PANEL.clearErrors()">${this.t.clear}</button>
							<button class="efb-btn-close" onclick="EFB_ERROR_PANEL.togglePanel()">×</button>
						</div>
					</div>

					<div class="efb-panel-warning">
						<span>${this.t.warning}</span>
					</div>
					<div class="efb-admin-notice">
						<span>${this.t.adminOnly}</span>
						<span class="efb-admin-badge">ADMIN</span>
					</div>
					<div class="efb-panel-body" id="efb-error-list">
						<div class="efb-no-errors">✓ ${this.t.noErrors}</div>
					</div>
					<div class="efb-panel-footer">
						<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
							<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
						</svg>
						<span>${this.t.adminOnly}</span>
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
						display: block !important; z-index: 1000000 !important;
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
				`;
				document.head.appendChild(style);
			},

			togglePanel() {
				this.isOpen = !this.isOpen;
				if (this.isOpen) {
					this.panel.style.setProperty("display", "block", "important");
					this.overlay.style.display = "block";
					setTimeout(() => {
						this.panel.style.setProperty("opacity", "1", "important");
						this.panel.style.setProperty("transform", "translate(-50%, -50%) scale(1)", "important");
						this.overlay.style.opacity = "1";
					}, 10);
				} else {
					this.panel.style.setProperty("opacity", "0", "important");
					this.panel.style.setProperty("transform", "translate(-50%, -50%) scale(0.9)", "important");
					this.overlay.style.opacity = "0";
					setTimeout(() => {
						this.panel.style.setProperty("display", "none", "important");
						this.overlay.style.display = "none";
					}, 300);
				}
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

			updateCount() {},


			addError(errorData) {
				const { message, source, lineno, stack = [], typeOverride = null, nameOverride = null } = errorData;
				const errorKey = (message || "") + "|" + (source || "") + "|" + (lineno || "");
				if (this.errorKeys.has(errorKey)) return;
				this.errorKeys.add(errorKey);

				const parsed = this.parseSource(source);
				if (typeOverride) { parsed.type = typeOverride; }
				if (nameOverride) { parsed.name = nameOverride; }
				const time = new Date().toLocaleTimeString([], {hour: "2-digit", minute: "2-digit"});

				const realSource = stack.length > 0 ? stack[0].parsed : parsed;
				const realPath = stack.length > 0 ? stack[0].parsed.fullPath + ":" + stack[0].line : (parsed.fullPath + (lineno ? ":" + lineno : ""));

				this.errors.push({ message, source, lineno, parsed, stack, time });

				this.showBadge();
				this.updateCount();

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
				item.innerHTML = `
					<div class="efb-error-source">
						<span class="type-badge type-${realSource.type}">${this.t[realSource.type] || realSource.type}</span>
						<span>${realSource.name}</span>
					</div>
					<div class="efb-error-msg">${this.escapeHtml(message)}</div>
					<div class="efb-error-file">
						<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
							<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
						</svg>
						<span class="efb-full-path">${realPath}</span>
					</div>
					${stackHtml}
					<div class="efb-error-meta">${time}</div>
				`;
				list.insertBefore(item, list.firstChild);
			},

			clearErrors() {
				this.errors = [];
				this.errorKeys.clear();
				this.updateCount();
				const list = document.getElementById("efb-error-list");
				list.innerHTML = "<div class=\"efb-no-errors\">✓ " + this.t.noErrors + "</div>";
				this.hideBadge();
			},

			escapeHtml(text) {
				const div = document.createElement("div");
				div.textContent = text;
				return div.innerHTML;
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
				this.addError({ message, source, lineno, stack, typeOverride, nameOverride });
				console.info("%c[EFB Debug Panel]%c Logged: " + message, "color:#ff4b93;font-weight:bold", "color:inherit");
			},

			init() {
				const self = this;

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
					if (typeof $ === "undefined" && typeof jQuery !== "undefined") {
						console.warn("[EFB] $ is undefined. If using jQuery in noConflict mode, use jQuery instead of $");
					}
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
	}

		public function efb_output_schema_free () {
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
		}

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
