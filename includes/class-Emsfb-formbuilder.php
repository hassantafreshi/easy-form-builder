<?php
 namespace Emsfb;
    class Formbuilder {
       public $valj_efb;
       private $pro_efb = false;
	   public $pub_bg_button_color_efb='btn-primary';
        public function __construct( $valj_efb, $pro_efb ) {
            $this->valj_efb =  $valj_efb;
            $this->pro_efb = $pro_efb;        
        }



        
	/* field builder */
	private function generateDescription_efb($rndm, $vj, $pos) {
		//  error_log('generateDescription_efb');
		//  error_log($vj->message_align);
		//  error_log($pos[1]);
		$mx = $pos[1] == 'col-md-4' || (isset($vj->message_align) && $vj->message_align != "justify-content-start") ? '' : 'mx-4';
		$msg_align = isset($vj->message_align) ? $vj->message_align : '';
		$msg_txt_color = isset($vj->message_text_color) ? $vj->message_text_color : '';
		$msg = isset($vj->message) ? $vj->message : '';
		return '<small id="' . $rndm . '-des" class="efb form-text d-flex fs-7 col-sm-12 efb ' . $mx . ' ' . $msg_align . ' ' . $msg_txt_color . ' ' . (isset($vj->message_text_size) ? $vj->message_text_size : '') . ' ">' . $msg . '</small>';
	}

	/* field builder */
	private function generateLabel_efb($rndm, $vj, $pos) {


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
			'col-sm-12',
			'col-form-label',
			(isset($vj->hflabel) && $vj->hflabel == 1 ? 'd-none' : ''),
			$label_color,
			$label_align,
			$label_text_size
		];
		
		$label_class_str = implode(' ', array_filter($label_classes));

		return '<label for="' . $rndm . '_" class="' . $label_class_str . '" id="' . $rndm . '_labG"><span id="' . $rndm . '_lab" class="efb ' . $label_text_size . '">' . $vj->name . '</span>' . $required . '</label>';
	}

	/* field builder */
	private function generateTooltip_efb($rndm) {
		return '<small id="' . $rndm . '_-message" class="efb py-1 fs-7 tx ttiptext px-2"> ! </small>';
	}

	/* field builder */
	private function generateDivFId_efb($rndm, $pos) {
		return '<div class="efb ' . $pos[3] . ' col-sm-12 px-0 mx-0 ttEfb show" id="' . $rndm . '-f">';
	}

	/* field builder */
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
				$lenAttributes = $this->generateLengthAttributes_efb($elementId, $vj);
				$classes = $elementId !== 'range' ? sprintf('form-control %s', $vj->el_border_color) : 'form-range';

				$fields['ui'] = $this->generateTextInput_efb(
				$type,$classes,$vj,$rndm,$desc,$label,$ttip,$div_f_id,$placeholder,$lenAttributes,$aire_describedby,$disabled,$autocomplete,$form_id
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
					<div class="efb %s col-sm-12 px-0 mx-0 ttEfb show" id="%s-f" %s>
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

					$rndm, // id for the first label
					$vj->off,

					$vj->el_height,
					str_replace(',', ' ', $vj->classes),
					$rndm, //data-css
					$rndm, // data-vid
					$rndm, // data-id and id for the button
					$form_id,
					$rndm,
					$disabled,
					$rndm, // id for the second label
					$vj->on,
					$desc
				);

				$fields['ui']  = $this->pro_efb ? $ui : $this->public_pro_message_efb($texts['tfnapca']);
				$fields['dataTag'] = $elementId;

				break;
			// Add other cases as needed
			default:
				return false;
		}
		// error_log('fields: '.json_encode($fields));
		return $fields;
	}

	/* field builder */
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

	/* field builder */
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

	/* field builder */
	private function generateTextInput_efb($type, $classes, $vj, $rndm, $desc, $label, $ttip, $div_f_id, $placeholder, $lenAttributes, $aire_describedby, $disabled, $autocomplete, $form_id) {
		// error_log('generateTextInput_efb');
		// error_log(json_encode($lenAttributes));
		$corener = isset($vj->corner) ? $vj->corner : 'efb-square';
		$required = ($vj->required == 1 || $vj->required == true) ? 'required' : '';
		$value = !empty($vj->value) ? 'value="' . $vj->value . '"' : '';
		$aria_required = ($vj->required == 1) ? 'true' : 'false';
		$readonly = ($disabled == "disabled") ? 'readonly' : '';
		$el_height = isset($vj->el_height) ? $vj->el_height : '';
		$el_text_color = isset($vj->el_text_color) ? $vj->el_text_color : '';
		$additional_classes = isset($vj->classes) ? str_replace(',', ' ', $vj->classes) : '';
	
		return sprintf(
			'%s %s %s <input type="%s" class="efb input-efb px-2 mb-0 emsFormBuilder_v w-100 %s %s %s %s %s efbField efb1 %s" data-id="%s-el" data-vid="%s" data-formid="%s" data-css="%s" id="%s_" %s %s aria-required="%s" aria-label="%s" %s autocomplete="%s" %s %s %s> %s',  $label,  $div_f_id,  $ttip,  $type,  $classes,  $el_height,  $corener,  $el_text_color,  $required,  $additional_classes,  $rndm,  $rndm,  $form_id,  $rndm,  $rndm,  $placeholder,  $value,  $aria_required,  $vj->name,  $aire_describedby,  $autocomplete,  $lenAttributes['maxlen'],  $lenAttributes['minlen'],  $readonly,  $desc
		);
	}

	/* field builder */
	private function generateSwitchInput_efb($vj, $rndm, $desc, $label, $ttip, $div_f_id, $aire_describedby, $disabled) {
		return '
		' . $label . '
		' . $ttip . '
		<div class="efb ' . $pos[3] . ' col-sm-12 px-0 mx-0 ttEfb show" id ="' . $rndm . '-f" ' . $aire_describedby . '>
		<label class="efb fs-6" id="' . $rndm . '_off">' . $vj->off . '</label>
		<button type="button" data-state="off" class="efb btn ' . $vj->el_height . ' btn-toggle efb1 ' . str_replace(',', ' ', $vj->classes) . '" data-css="' . $rndm . '" data-toggle="button" aria-pressed="false" data-vid="' . $rndm .'" data-formid="' . $form_id . '" onclick="fun_switch_efb(this)" data-id="' . $rndm . '-el" id="' . $rndm . '_" ' . $disabled . '>
			<div class="efb handle"></div>
		</button>
		<label class="efb fs-6" id="' . $rndm . '_on">' . $vj->on . '</label>
		<div class="efb mb-3">' . $desc . '</div>';
	}

	/* field builder */
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
				// Add any additional logic for label_position == "up" here
			}
		} else {
			$parent_row = 'row';
			if ($state === true) {
				// Add any additional logic for other label_position here
			}
		}
		if ($state === true) {
			$el_parent = $this->colMdChangerEfb($el_parent, $parent_col);
			if ($el_input != "null") $el_input = $this->colMdChangerEfb($el_input, $input_col);
			if ($el_label != "null") $el_label = $this->colMdChangerEfb($el_label, $label_col);
		}
		return array($parent_row, $parent_col, $label_col, $input_col);
	}

	/* field builder */
	private function colMdChangerEfb($classes, $value) {
		// Use a regular expression to replace the col-md-* class with the new value
		$newClasses = preg_replace('/\bcol-md+-\d+/', " $value ", $classes);
		// If the replacement did not occur (preg_replace returns null), concatenate the new value
		if ($newClasses === null) {
			return $classes . ' ' . $value;
		}
		return $newClasses;
	}

	/* field builder */
	private function public_pro_message_efb($text){
		$r = sprintf(
			'<div class="efb text-white fs-6 bg-danger px-1 rounded px-2">%s</div>',
			$text
		);
		// error_log('public_pro_message_efb: '.$r);
		return $r;
	}

	// $rndm, $vj, $pos, $formId, $texts ,$desc,$label,$ttip,$aire_describedby
	public function generate_country_list_efb($rndm, $vj, $pos, $formId, $texts ,$desc,$label,$ttip,$aire_describedby) {
		$optn = '<!--countries-->';
		
		$options = '';
        $optns_obj = array_filter($this->valj_efb, function($obj) use ($rndm) {
            return isset($obj->parent) && $obj->parent === $rndm;
        });
        // error_log('optns_obj:'.json_encode($optns_obj));
        foreach ($optns_obj as $i) {
			// error_log('optns_obj:'.json_encode($i));
            $selected = ($vj->value == $i->id_ || (property_exists($i, 'id_old') && $vj->value == $i->id_old)) ? 'selected' : '';
           /*  $options .= sprintf(
                '<option class="efb %s emsFormBuilder_v efb" data-id="%s" data-op="%s" value="%s" %s>%s</option>',
                $vj->el_text_color,
                $i->id_,
                $i->id_,
                $i->value,
                $selected,
                $i->value
            ); */
			$options .= sprintf(
					'<option value="%s" id="%s" data-iso="%s" data-id="%s" data-op="%s" class="efb %s emsFormBuilder_v efb" %s>%s</option>',
					$i->value,
					$i->id_,
					$i->id_op,
					$i->id_,
					$i->id_,
					$vj->el_text_color,
					$selected,
					$i->value
				);
				// error_log('options:'.$options);
        }

				/* 
				$optn .= sprintf(
					'<option value="%s" id="%s" data-iso="%s" data-id="%s" data-op="%s" class="efb %s emsFormBuilder_v efb" %s>%s</option>',
					$value,
					$i->id_,
					$i->id_op,
					$i->id_,
					$i->id_,
					$this->valj_efb[$indx_parent]->el_text_color,
					$selected,
					$value
				); */
			//}
		
	
		$required = ($vj->required == 1 || $vj->required == true) ? 'required' : '';
        //$readonly = $previewSate != true ? 'readonly' : '';
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
			<div data-tag="%s" class="efb %s col-sm-12 px-0 mx-0 ttEfb show efb1 %s" data-css="%s" id="%s-f" data-id="%s-el" data-formid="%s">
				%s
				<select class="efb form-select efb emsFormBuilder_v w-100 %s %s %s %s w-100" data-vid="%s" id="%s_options" aria-required="%s" aria-label="%s" %s data-type="%s" data-formid="%s" %s %s>
					<option selected disabled>%s</option>
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
			$type, // اضافه کردن ویژگی data-type
			$formId,
			$readonly,
			$disabled ? 'disabled' : '',
			$texts['nothingSelected'],
			$options,
			$desc
		);

		return $ui;

	}


	public function generate_state_province_efb($rndm, $vj, $pos, $formId, $texts, $desc, $label, $ttip, $aire_describedby) {
		$optn = '<!--options-->';
		
		$required = ($vj->required == 1 || $vj->required == true) ? 'required' : '';
		$readonly = false ? 'readonly' : ''; // Assuming $previewSate is false as not provided
		$ariaRequired = $vj->required == 1 ? 'true' : 'false';
		$ariaDescribedBy = !empty($vj->message) ? 'aria-describedby="' . $vj->id_ . '-des"' : '';
		$disabled = property_exists($vj, 'disabled') && $vj->disabled == true ? 'disabled' : '';
		$corner = isset($vj->corner) ? $vj->corner : 'efb-square';
		$el_height = isset($vj->el_height) ? $vj->el_height : '';
		$el_border_color = isset($vj->el_border_color) ? $vj->el_border_color : '';
	
		$ui = sprintf(
			'%s
			<div class="efb %s col-sm-12 px-0 mx-0 ttEfb show efb1 %s" data-css="%s" id="%s-f" data-id="%s-el" data-formid="%s">
				%s
				<select data-type="stateProvince" class="efb form-select emsFormBuilder_v w-100 %s %s %s %s" data-vid="%s" id="%s_options" data-formid="%s" aria-required="%s" aria-label="%s" %s %s %s>
					<option selected disabled>%s</option>
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
			$formId, // Added data-formid to the select element
			$ariaRequired,
			$vj->name,
			$ariaDescribedBy,
			$readonly,
			$disabled,
			$texts['nothingSelected'],
			$optn,
			$desc
		);
	
		return $ui;
	}
	
	public function generate_city_list_efb($rndm, $vj, $pos, $formId, $texts, $desc, $label, $ttip, $aire_describedby) {
		$optn = '<!--options-->';
		
		$required = ($vj->required == 1 || $vj->required == true) ? 'required' : '';
		$readonly = false ? 'readonly' : ''; // Assuming $previewSate is false as not provided
		$ariaRequired = $vj->required == 1 ? 'true' : 'false';
		$ariaDescribedBy = !empty($vj->message) ? 'aria-describedby="' . $vj->id_ . '-des"' : '';
		$disabled = property_exists($vj, 'disabled') && $vj->disabled == true ? 'disabled' : '';
		$corner = isset($vj->corner) ? $vj->corner : 'efb-square';
		$el_height = isset($vj->el_height) ? $vj->el_height : '';
		$el_border_color = isset($vj->el_border_color) ? $vj->el_border_color : '';
	
		$ui = sprintf(
			'%s
			<div class="efb %s col-sm-12 px-0 mx-0 ttEfb show efb1 %s" data-css="%s" id="%s-f" data-id="%s-el" data-formid="%s">
				%s
				<select data-type="citylist" class="efb form-select emsFormBuilder_v w-100 %s %s %s %s" data-vid="%s" id="%s_options" data-formid="%s" aria-required="%s" aria-label="%s" %s %s %s>
					<option selected disabled>%s</option>
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
			$formId, // Added data-formid to the select element
			$ariaRequired,
			$vj->name,
			$ariaDescribedBy,
			$readonly,
			$disabled,
			$texts['nothingSelected'],
			$optn,
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
	
		//$indx_parent = array_search($rndm, array_column($this->valj_efb, 'id_'));
		$s = isset($vj->value) && count($vj->value) > 0 ? true : false;
	
		// error_log('type $this->$vj->value:'.gettype($vj->value));
		// error_log('$this->$vj->value:'.json_encode($vj->value));
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
				$formId, // Added data-formid to the tr tag
				$c,
				$formId, // Added data-formid to the th tag
				$formId, // Added data-formid to the td tag
				$i->value,
				strlen($pay) > 2 ? sprintf(
					'<td class="efb ms fw-bold text-center"><span id="%s-price" class="efb efb-crrncy">%s</span></td>',
					$i->id_,
					number_format($i->price, 2) . ' ' . $currency
				) : ''
			);
		}
	
		$required = ($vj->required == 1 || $vj->required == true) ? 'required' : '';
		$readonly =  ''; // Assuming $previewSate is false as not provided
		$ariaRequired = $vj->required == 1 ? 'true' : 'false';
		$ariaDescribedBy = !empty($vj->message) ? 'aria-describedby="' . $vj->id_ . '-des"' : '';
		$disabled = property_exists($vj, 'disabled') && $vj->disabled == true ? 'disabled' : '';
		$corner = isset($vj->corner) ? $vj->corner : 'efb-square';
		$el_height = isset($vj->el_height) ? $vj->el_height : '';
		$el_border_color = isset($vj->el_border_color) ? $vj->el_border_color : '';
	
		// error_log('va: '.$va);
		// error_log('sl: '.$sl);
		$ui = sprintf(
			'%s
			<!--multiselect-->
			<div class="efb %s col-sm-12 listSelect px-0 mx-0 ttEfb show efb1 %s" data-css="%s" id="%s-f" data-id="%s-el" data-formid="%s">
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
		// Setting up classes and conditions
		$classes = sprintf('form-control %s', $vj->el_border_color ?? '');
		$required = ($vj->required == 1 || $vj->required == true) ? 'required' : '';
		$ariaRequired = $vj->required == 1 ? 'true' : 'false';
		$readonly =  '';
		$value = !empty($vj->value) ? sprintf('value="%s"', $vj->value) : '';
		$el_height = isset($vj->el_height) ? $vj->el_height : '';
		$el_text_color = isset($vj->el_text_color) ? $vj->el_text_color : '';
		$corner = isset($vj->corner) ? $vj->corner : 'efb-square';
		$extra_classes = str_replace(',', ' ', $vj->classes);
	
		// Generating UI
		$ui = sprintf(
			'%s
			<div class="efb %s col-sm-12 px-0 mx-0 ttEfb show" id="%s-f">
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
			$vj->value,
			$aire_describedby,
			$readonly,
			$desc
		);
	
		return $ui;
	}
	


	public function generate_html_code_efb($rndm, $vj, $pos, $formId, $texts, $previewSate) {
		if (strlen($vj->value) < 2) {
			$ui = sprintf(
				'<div class="efb col-sm-12 efb" id="%s-f" data-id="%s-el" data-tag="htmlCode">
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
			$ui = str_replace(['@!', '@efb@nq#'], ['"', ''], $vj->value) . "<!--endhtml first -->";
			$ui = sprintf(
				'<div %s>%s</div>',
				$previewSate == false ? 'class="efb bg-light" id="' . $rndm . '_html"' : '',
				$ui
			);
		}
	
		return $ui;
	}
	

	public function generate_heading_efb($rndm, $pos, $vj, $formId) {
		// Extract properties with defaults
		$el_text_color = isset($vj->el_text_color) ? $vj->el_text_color : '';
		$el_text_size = isset($vj->el_text_size) ? $vj->el_text_size : '';
		$extra_classes = isset($vj->classes) ? str_replace(',', ' ', $vj->classes) : '';
		$value = isset($vj->value) ? htmlspecialchars($vj->value, ENT_QUOTES, 'UTF-8') : '';
	
		// Build the HTML
		$ui = sprintf(
			'<div class="efb px-0 mx-0 %s col-sm-12" id="%s-f" data-formid="%s">
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
		// Determine if the link should be disabled in preview mode
		$disabled = $previewState != true ? 'disabled' : '';
		
		// Extract classes and other properties
		$el_text_color = isset($vj->el_text_color) ? $vj->el_text_color : '';
		$el_text_size = isset($vj->el_text_size) ? $vj->el_text_size : '';
		$classes = isset($vj->classes) ? str_replace(',', ' ', $vj->classes) : '';
		$href = isset($vj->href) ? $vj->href : '#';
		$value = isset($vj->value) ? $vj->value : '';
	
		// Construct the UI HTML structure
		$ui = sprintf(
			'<div class="efb %s px-0 mx-0 col-sm-12" id="%s-f" data-formid="%s">
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
		// تعیین ویژگی‌ها و شرایط
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
			'<div class="efb %1$s col-sm-12 %2$s efb1 %3$s" data-css="%4$s" id="%4$s-f" data-formid="%5$s" %6$s>
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
			$pos[3],             // %1$s
			$disabled,           // %2$s
			$classes,            // %3$s
			$rndm,               // %4$s
			$formId,             // %5$s
			$ariaDescribedBy,    // %6$s
			$required,           // %7$s
			$button1Text,        // %8$s
			$buttonColor,        // %9$s
			$elTextColor,        // %10$s
			$elHeight,           // %11$s
			$corner,             // %12$s
			$disabled,           // %13$s
			$previewState != true ? 'disabled' : '',   // %14$s
			$button2Text         // %15$s
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
			$classes,         // %1$s
			$id,              //  %2$s
			$id,              // %3$s
			$form_id,         // %4$s			
			$message,         // %5$s	
			$previewSate, $disabled, $id, $form_id, // first star
			$previewSate, $disabled, $id, $form_id, // second star
			$previewSate, $disabled, $id, $form_id, // third star 
			$previewSate, $disabled, $id, $form_id, // fourth star
			$previewSate, $disabled, $id, $form_id, // fifth star
			$id,              // data-vid
			$id               // id for hidden input
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
			$classes,    // %1$s: classes for main div
			$id,         // %2$s: data-css and id for main div
			$id,         // %3$s: id for main div
			$form_id,    // %4$s: form ID
			$message,    // %5$s: aria-describedby if there is a message
			$previewSate, $disabled, $id, $form_id, // %6: rating button for point 0
			$previewSate, $disabled, $id, $form_id, // %7: rating button for point 1
			$previewSate, $disabled, $id, $form_id, // %8: rating button for point 2
			$previewSate, $disabled, $id, $form_id, // %9: rating button for point 3
			$previewSate, $disabled, $id, $form_id, // %10: rating button for point 4
			$previewSate, $disabled, $id, $form_id, // %11: rating button for point 5
			$previewSate, $disabled, $id, $form_id, // %12: rating button for point 6
			$previewSate, $disabled, $id, $form_id, // %13: rating button for point 7
			$previewSate, $disabled, $id, $form_id, // %14: rating button for point 8
			$previewSate, $disabled, $id, $form_id, // %15: rating button for point 9
			$previewSate, $disabled, $id, $form_id, // %16: rating button for point 10
			$id,         // %17: data-vid for hidden input
			$id          // %18: id for hidden input
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
	
		// Filter options based on parent ID
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
				$col,                // %1$s
				$i->id_,             // %2$s
				$i->parent,          // %3$s
				$vj->el_text_color,  // %4$s
				$vj->el_height,      // %5$s
				$vj->label_text_size,// %6$s
				$i->value,           // %7$s
				$position_l_efb,     // %8$s
				$aire_describedby,   // %9$s
				$disabled_preview,   // %10$s
				$disabled,           // %11$s
				$form_id             // %12$s
			);
		}
	
		$ui = sprintf(
			'
			<!-- table matrix -->
			%1$s
			<div class="efb %2$s col-sm-12 px-0 mx-0 ttEfb show" data-id="%3$s-el" id="%3$s-f">
				%4$s
				<div class="efb %5$s %6$s efb1 %7$s" id="%3$s_options">
					%8$s
				</div>
				<div class="efb mb-3">%9$s</div>
			
			<!-- end table matrix -->',
			$label,                    // %1$s
			$pos[3],                   // %2$s
			$rndm,                     // %3$s
			$ttip,                     // %4$s
			$vj->required ? 'required' : '',  // %5$s
			$col ? 'row col-md-12' : '',      // %6$s
			$classes,                  // %7$s
			$optn,                     // %8$s
			$desc                      // %9$s
		);
	
		return $ui;
	}
	
	
	
	

	
	
	
	

	/* field builder */
	private function create_intlTelInput_efb($rndm,$vj, $previewSate, $corner,$form_id) {
		$disabled = isset($vj->disabled) && $vj->disabled == 1 ? 'disabled' : '';
		$required = $vj->required == 1 || $vj->required == true ? 'required' : '';
		$ariaRequired = $vj->required == 1 ? 'true' : 'false';
		$ariaDescribedBy = !empty($vj->message) ? 'aria-describedby="' . $vj->id_ . '-des"' : '';
		$value = !empty($vj->value) ? 'value="' . $vj->value . '"' : '';
		$readonly = $previewSate != true ? 'readonly' : '';
		$classes =  str_replace(',', ' ', $vj->classes) ?? '';
		$onlyCountries = isset($vj->c_c) && count($vj->c_c) > 0 ? $vj->c_c : '';
		require_once EMSFB_PLUGIN_DIRECTORY . 'includes/functions.php'; 
		$efbFunction = new EFBFunction();
		$tt =[ 'cpnnc', 'icc', 'cpnts', 'cpntl'];
		$texts = $efbFunction->text_efb($tt);

		
		// Call the function to load intlTelInput
		$js =sprintf(
			'
			setTimeout(function() {
				const iti = window.intlTelInput(document.getElementById("%1$s_"), {
					onlyCountries: onlyCountries,
					autoHideDialCode: true,
					placeholderNumberType: "MOBILE",
					utilsScript: %10$s,
				});
				document.getElementById("%1$s_").addEventListener("blur", function() {
					const errorMap = ["%6$s, %7$s,%8$s,%9$s, "%6$s];
					const elem = document.getElementById("%1$s_");
					const messageElem = document.getElementById("%1$s_-message");
					elem.classList.remove("border-danger");
					elem.classList.remove("border-success");
					messageElem.innerHTML = "";
					messageElem.classList.remove("d-block");
					messageElem.classList.add("d-none");
					if (elem.value.trim()) {
						if (iti.isValidNumber()) {
							elem.classList.add("border-success");
							const mobile_no = elem.value.replace(/^0+/, "");
							const value = `+${iti.s.dialCode}${mobile_no}`;
							fun_sendBack_emsFormBuilder({ 
								id_: "%2$s", 
								name: "%3$s", 
								id_ob: "%2$s", 
								amount: "%4$s", 
								type: "%5$s", 
								value: value, 
								session: sessionPub_emsFormBuilder 
							});
						} else {
							elem.classList.add("border-danger");
							let errorCode = iti.getValidationError();
							errorCode = errorMap[errorCode] ? errorMap[errorCode] : errorMap[0];
							messageElem.classList.remove("d-none");
							messageElem.classList.add("d-block");
							messageElem.innerHTML = errorCode;
							let inx = get_row_sendback_by_id_efb("%2$s");
							if (inx !== -1) {
								sendBack_emsFormBuilder_pub.splice(inx, 1);
							}
						}
					}
				});
			}, 80);',
			$rndm,
			$vj->id_,
			$vj->name,
			$vj->amount,
			$vj->type,
			$texts['cpnnc'],
			$texts['icc'],
			$texts['cpnts'],
			$texts['cpntl'],
			EMSFB_PLUGIN_URL . 'includes/admin/assets/js/utils-efb.js'

    	);
	
		// Create the HTML string
		$inputPhone = sprintf(
			'<input type="phone" class="efb input-efb intlPhone px-2 mb-0 emsFormBuilder_v form-control %1$s %2$s %3$s %4$s %5$s efbField efb1 %6$s" data-css="%7$s" data-id="%7$s-el" data-formid="%13$s" data-vid="%7$s" id="%7$s_" aria-required="%8$s" aria-label="%9$s" %10$s %11$s %12$s data-utilsjs="%14$s">
			<input type="phone" class="efb input-efb intlPhone px-2 mb-0 emsFormBuilder_v form-control %1$s %2$s %3$s %4$s %5$s efbField d-none efb1 %6$s" data-css="%7$s" data-id="%7$s-el" data-formid="%13$s" data-vid="%7$s" id="%7$s-code" placeholder="verify" %11$s %12$s %10$s data-utilsjs="%14$s">',
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
			EMSFB_PLUGIN_URL . 'includes/admin/assets/js/utils-efb.js'
		);
	
		$buttonSubmit = sprintf(
			'<button id="%1$s-btn" type="submit" class="efb d-none">Submit</button>',
			$rndm
		);
	
		return [$inputPhone  . $buttonSubmit ,$js];
	}

	/* field builder */
	public function esign_el_pro_efb($previewSate,$pos, $rndm, $vj,$message, $formId,$updateUrbrowser) {
		// true, $pos, $rndm, $vj, $desc
		// error_log('esign_el_pro_efb');
		// error_log('esign_el_pro_efb: '.json_encode($vj));
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
		
			// ایجاد HTML برای المان esign
			$ui = sprintf(
				"<div class='efb %s col-sm-12' id='%s-f' data-formid='%s'>
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
				$pos[3], // کلاس‌های موقعیت
				$randomId, $formId, // شناسه و formId
				$el_height, $corner, $el_text_color, $vj->el_border_color,
				str_replace(',', ' ', $classes), // کلاس‌های اضافی
				$randomId, $randomId, $randomId, $randomId, // شناسه و داده‌ها
				$ariaDescribedBy,
				$updateUrbrowser, // پیغام به‌روزرسانی مرورگر
				$previewSate ? sprintf(
					"<input type='hidden' data-type='esign' data-vid='%s' class='efb emsFormBuilder_v %s' id='%s-sig-data' value='Data:image/gif;base64,R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==' data-formid='%s'>",
					$randomId,$required, $randomId, $formId
				) : '',
				$formId, $message, // فرم‌آی‌دی و توضیحات
				$formId, // فرم‌آی‌دی برای دکمه
				$corner, $vj->button_color, $disabled, $randomId, $randomId, // دکمه و شناسه‌ها
				$vj->icon, $vj->icon_color != 'default' ? $vj->icon_color : '',
				$randomId, $randomId, // شناسه‌های آیکون و متن
				$vj->icon_color, $disabled, $vj->button_single_text // رنگ و متن دکمه
			);
		
			return $ui;
	
		


	}

	/* field builder */
	public function dadfile_el_pro_efb($previewSate, $rndm, $vj, $form_id, $texts) {
		$corner = property_exists($vj, 'corner') ? $vj->corner : 'efb-square';
		$disabled = property_exists($vj, 'disabled') && $vj->disabled == true ? 'disabled' : '';

		function ui_dadfile_efb($vj, $previewSate, $form_id, $texts, $disabled, $corner) { 
			
			
			$fileType = property_exists($vj, 'file') ? $vj->file : '';
	
			if ($fileType == 'customize') {
				$name_type_file = $vj->file_ctype;
			}else{
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
				<input type="file" hidden="" accept="%10$s" data-type="dadfile" data-vid="%3$s" data-id="%3$s" class="efb emsFormBuilder_v %11$s" id="%3$s_" data-id="%3$s-el" data-formid="%13$s" %12$s %8$s>',
				$vj->icon,
				$vj->icon_color,
				$vj->id_,
				$texts['dragAndDropA'],  // dragAndDropA
				$name_type_file ,
				$texts['or'],  // or
				$vj->button_color,
				$disabled,
				$texts['browseFile'],  // browseFile
				$fileTypeAttr,
				$requiredClass,
				$readonlyAttr,
				$form_id
			);
		}
		$ui = ui_dadfile_efb($vj, $previewSate, $form_id, $texts , $disabled, $corner);
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

	/* field builder */
	private function text_nr_efb($text, $type) {
		$val = $type == 1 ? '<br>' : "\n";
		return str_replace('@n#', $val, $text);
	}

	/* field builder */
	private function fun_get_links_from_string_Efb($str , $handler){
		 // Define the regular expression pattern for matching links
		 $pattern = '/\[([^\]]+)\]\(([^)]+)\)/';

		 if ($handler === false) {
			 // Use preg_match_all to find all matches
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
			 // Use preg_replace_callback to replace matches with anchor tags
			 return preg_replace_callback($pattern, function($matches) {
				 return '<a href="' . htmlspecialchars($matches[2], ENT_QUOTES, 'UTF-8') . '" target="_blank">' . htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8') . '</a>';
			 }, $str);
		 }
	}

	/* field builder */
	public function add_ui_stripe_efb($rndm , $cl, $sub,$form_id,$texts) {
		$currency = $this->valj_efb[0]->currency;
		$amount =$this->formatPrice_efb(0, $currency);
		return  '
		<!-- stripe -->
		<div class="efb  col-sm-12 stripe"  id="'.$rndm.'-f">
		<div class="efb  stripe-bg  p-3 card w-100">
		<div class="efb  headpay border-b row col-md-12 mb-3">
		  <div class="efb  h3 col-sm-5">
			<div class="efb  col-12 text-dark"> '.$texts['payAmount'].'</div>
			<div class="efb  text-labelEfb mx-2 my-1 fs-7"> <i class="efb mx-1 bi-shield-check"></i><span>Powered by Stripe</span></div>
		  </div> 
		  <div class="efb  h3 col-sm-7 d-flex justify-content-end payPriceEfb" id="payPriceEfb"  data-formid="'.$form_id.'"> 
			<span  class="efb  totalpayEfb d-flex justify-content-evenly mx-1"  data-formid="'.$form_id.'">'.$amount.'</span>
			
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


	public function add_ui_zp_efb($rndm , $form_id,$texts) {
		return  '
		<div class="efb card w-100 col-sm-12 m-0 p-0"  id="'.$rndm.'-f"  data-formid="'.$form_id.'">
			<div class="efb  p-3 d-block" id="beforePay">
				<div class="efb  headpay border-b row col-md-12 mb-3">
					<div class="efb  h3 col-sm-5">
						<div class="efb  col-12 text-dark"> '.$texts['payAmount'].':</div>
						<div class="efb  text-labelEfb mx-2 my-1 fs-7"> <i class="efb mx-1 bi-shield-check"></i>پرداخت توسط <span Class="efb fs-6" id="efbPayBy">زرین پال</span></div>
					</div>
					<div class="efb  h3 col-sm-7 d-flex justify-content-end" id="payPriceEfb"  data-formid="'.$form_id.'">
						<span  class="efb totalpayEfb d-flex justify-content-evenly mx-1" data-formid="'.$form_id.'">'.number_format(0, 2, '.', ',').'</span>
						<!-- <span class="efb currencyPayEfb fs-5" id="currencyPayEfb">تومان</span> -->
						<!-- <span class="efb  text-labelEfb one" id="chargeEfb">'.$texts['onetime'].'</span>-->
					</div>
				</div>
				<a class="efb btn my-2 efb p-2 efb-square h-l-efb btn-primary text-white text-decoration-none disabled w-100" onclick="pay_persia_efb()" id="persiaPayEfb"  data-formid="'.$form_id.'">'.$texts['payment'].'</a>
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

	/* field builder */
	public function formatPrice_efb($amount, $currency) {
   
		$currency_details = $this->get_currency_details_efb($currency);
    	$formatted_amount = number_format_i18n($amount, $currency_details['d']);
   	 	return $currency_details['s'] . ' ' . $formatted_amount;

    }

	/* field builder */
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


        /* field builder */
	public function ColorNameToHexEfbOfElEfb($v, $n) {	
		// ColorNameToHexEfbOfElEfb(color.slice(4),'btn') // slice text=5 bg=2 border=6 btn=3 icon=4
		// ColorNameToHexEfbOfElEfb(color.slice(7),'border') // slice text=5 bg=2 border=6 btn=3     
		// error_log('ColorNameToHexEfbOfElEfb v:'.$v .' n:'.$n);
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
		// error_log($r);
		return $r;
	}

    /* field builder */
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
			<div class="efb %s col-sm-12 px-0 mx-0 ttEfb show" id="%s-f" %s data-formid="%s">
				<label class="efb fs-6" id="%s_off">%s</label>
				<button type="button" data-state="off" class="efb btn %s btn-toggle efb1 %s" data-css="%s" data-toggle="button" aria-pressed="false" data-vid="%s" onclick="fun_switch_efb(this)" data-id="%s-el" id="%s_" %s %s>
					<div class="efb handle"></div>
				</button>
				<label class="efb fs-6" id="%s_on">%s</label>
				<div class="efb mb-3">%s</div>
			',
			$label,
			$ttip,
			$pos[3],  // postion
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

	/* field builder */
	public function rating_el_pro_efb($previewSate, $pos, $rndm, $vj, $desc, $formId, $label, $ttip, $aire_describedby, $texts) {

		$disabled = isset($vj->disabled) && $vj->disabled == 1 ? 'disabled' : '';
		$requiredClass = ($vj->required == 1 || $vj->required == true) ? 'required' : '';
		$el_height = isset($vj->el_height) ? $vj->el_height : '';
		$classes = str_replace(',', ' ', $vj->classes);
		$ariaDescribedBy = !empty($vj->message) ? 'aria-describedby="' . $vj->id_ . '-des"' : '';
		$required = $vj->required == 1 ? 'required' : '';
		
		// ایجاد HTML برای rating element
		$ui = sprintf(
			'%s
			%s
			<div class="efb %s col-sm-12" id="%s-f" data-formid="%s">
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
			$pos[3], // موقعیت
			$rndm, $formId, // شناسه و فرم آی‌دی
			$disabled, $classes, $rndm, // کلاس‌ها و داده‌ها
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


	/* field builder */
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

    /* field builder */

    public function generate_select_efb($elementId, $rndm, $vj, $pos, $formId, $texts, $previewSate ,$desc,$label,$ttip,$aire_describedby ) {
        // error_log('>>generate_select_efb');
        // error_log('elementId:'.$elementId);
        $pay = $elementId != "paySelect" ? '' : 'pay';
        $options = '';
        $optns_obj = array_filter($this->valj_efb, function($obj) use ($rndm) {
            return isset($obj->parent) && $obj->parent === $rndm;
        });
        // error_log('optns_obj:'.json_encode($optns_obj));
        foreach ($optns_obj as $i) {
            $selected = ($vj->value == $i->id_ || (property_exists($i, 'id_old') && $vj->value == $i->id_old)) ? 'selected' : '';
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
            <div class="efb %s col-sm-12 px-0 mx-0 ttEfb show efb1 %s" data-css="%s" id="%s-f" data-id="%s-el" data-formid="%s">
                %s
                <select class="efb form-select efb emsFormBuilder_v w-100 %s %s %s %s %s w-100" data-vid="%s" id="%s_options" aria-required="%s" aria-label="%s" %s %s %s>
                    <option selected disabled>%s</option>
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
            $texts['nothingSelected'],
            $options,
            $desc
        );
    
        return $ui;
    }







    /* new */
	/* sanitize */
	/* sanitize recived object value */
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
		// error_log('filter_attributes_by_type_efb');
		// error_log($type);
		// error_log(json_encode($attribute_map_efb[$type]));

			if (isset($attribute_map_efb[$type])) {
				$allowed_attributes_efb_type = is_array($attribute_map_efb[$type]) ?  array_replace($allowed_attributes_efb, $attribute_map_efb[$type]) :$allowed_attributes_efb;
				// error_log(json_encode($allowed_attributes_efb_type));
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
		// Define the URL processing function
		$process_url = function($url) {
			$url = preg_replace('/(http:@efb@)+/', 'http://', $url);
			$url = preg_replace('/(https:@efb@)+/', 'https://', $url);
			$url = str_replace('@efb@', '/', $url);
			return $url;
		};
	
		// Determine value and sub_value based on the state
		$value = isset($row->value ) ? $row->value :  '';
		$sub_value = isset($row->sub_value) ? $row->sub_value :  '';
		// error_log(json_encode($row));
		// error_log('value-------------------------->'.$row->value);
		// error_log('sub_value-------------------------->'.$row->sub_value);
	
		// Process the link and set the default if necessary
		
		$link = $process_url($link);
	
		// Return the constructed HTML
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
       
	public function fun_captcha_load_efb($siteKey, $formId) {
		$captchaHTML = "";

			if (strlen($siteKey) > 1) {
				$captchaHTML = sprintf(
					'<div class="efb row mx-0">
						<div id="gRecaptcha" class="efb g-recaptcha my-2 mx-0 px-0" data-sitekey="%1$s" data-callback="verifyCaptcha" style="transform:scale(0.88);-webkit-transform:scale(0.88);transform-origin:0 0;-webkit-transform-origin:0 0;"></div>
						<small class="efb text-danger" id="recaptcha-message"></small>
					</div>',
					$siteKey
				);
			}

			// Final HTML structure including formId
			$ui = sprintf(
				'%1$s
				<div id="step-1-efb-msg" data-formid="%2$s"></div>',
				$captchaHTML,
				$formId
			);

			return $ui;
	}

	public function loading_message_efb($pro ,$texts,$state=0) {
		$pro = false;
		// SVG animation for loading indicator
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

		// Powered by Easy Form Builder by white studio team
		$text = esc_html__('Powered by %sEasy Form Builder%s by %swhite studio team%s', 'easy-form-builder');
		$text = sprintf($text, '<a href="https://wordpress.org/plugins/easy-form-builder/" target="_blank">', '</a>', '<a href="https://whitestudio.team" target="_blank">', '</a>');
		$copyRight = '<!-- efb copyRight -->';
		$efb = esc_html__('Easy Form Builder', 'easy-form-builder');
		$wp_text = esc_html__('WordPress', 'easy-form-builder');
		$fr = '<!-- efb copyRight -->';
		$wr = $state == 1 ? '<p class="efb fs-5">' . $texts[1] . '</p>' : '';

		if (strpos(get_locale(), 'fa') !== false) {
			$s = '<a href="https://easyformbuilder.ir" target="_blank">فرم ساز وردپرس</a> <a href="https://fa.wordpress.org/plugins/easy-form-builder/" target="_blank">افزونه فرم ساز وردپرس</a>' . $fr;
		} else if (strpos(get_locale(), 'en') == false) {
			$f = substr(get_locale(), 0, 2);
			$s = '<a href="https://'.$f.'.wordpress.org/plugins/easy-form-builder/" target="_blank">'.$efb.' '. $wp_text.'</a>' . $fr;
		}

		// state can be used for user setting to show or hide the copy right
		// error_log('state:' . $state);
		// error_log('pro:' . $pro);

		if ($state == 1 && $pro != 1) {
			$copyRight = '<div class="efb d-none" id="copyrightEfb">  <h2 class="efb fs-8">' . $text . '</h2>
							<h3 class="efb fs-8 d-none">' . $s . '</h3>
						</div>';
		}
	

	
		// Generate the loading message with SVG animation
		$loadingMessage = sprintf(
			'<h3 class="efb fs-3 text-center">%s %s</h3><p class="efb fs-5">%s</p> %s',
			$texts[0], // Accessing translation or variable for "Please wait" text
			$svg,// SVG animation
			$wr,
			$copyRight
		);
	
		return $loadingMessage;
	}


	function add_buttons_zone_efb($state, $id, $valj_efb, $efb_var, $formId) {
		// Determine button status and initialize display variables
		$dis = '';
		$t = array_search('stripe', array_column($valj_efb, 'type'));
		$t = $t === false ? array_search('persiaPay', array_column($valj_efb, 'type')) : $t;
		$t = $t === false ? array_search('paypal', array_column($valj_efb, 'type')) : $t;
		$t = $t !== false ? $valj_efb[$t]->step : 0;
	
		// Check if payment method is required
		//This form requires a payment method. Please add one or change the form type.
		$dis = ($valj_efb[0]->type == "payment" && $valj_efb[0]->steps == 1 && $t == 1) ? 'disabled' : '';
		if ($valj_efb[0]->type == "payment" && $t == 0) {
			return  "<script>alert('".esc_html__('This form requires a payment method. Please add one or change the form type.' , 'easy-form-builder')."');</script>";
		}
	
		// Set alignment and corner styles

		$corner = property_exists($valj_efb[0], 'corner') ? $valj_efb[0]->corner : 'efb-square';
		$btns_align = property_exists($valj_efb[0], 'btns_align') ? $valj_efb[0]->btns_align . ' mx-3' : 'justify-content-center';

		$prev_icon = strlen($valj_efb[0]->button_Previous_icon) > 3 && $valj_efb[0]->button_Previous_icon != 'bi-undefined' ? sprintf('<i class="efb %s mx-2 %s %s" id="button_group_icon"></i>', $valj_efb[0]->button_Previous_icon, $valj_efb[0]->icon_color, $valj_efb[0]->el_height) : '';
		$next_icon = strlen($valj_efb[0]->button_Next_text) > 3 && $valj_efb[0]->button_Next_text != 'bi-undefined' ? sprintf('<i class="efb %s mx-2 %s %s" id="button_group_icon"></i>', $valj_efb[0]->button_Next_icon, $valj_efb[0]->icon_color, $valj_efb[0]->el_height) : '';
	
		// Single button display
		$s = sprintf(
			'<div class="efb d-flex %s %s text-center efb mx-3" id="f_btn_send_efb" data-tag="buttonNav" data-formid="%s">
				<a id="btn_send_efb" role="button" class="efb text-decoration-none mx-0 btn p-2 %s %s %s %s efb-btn-lg btn_send_efb" data-formid="%s" data-currentstep="1" onclick="btn_navigate_handle_efb(\'%s\' ,\'%s\' ,\'%s\',this)">%s<span id="button_group_button_single_text" class="efb %s" >%s</span></a>
			</div>',
			$btns_align,
			$state == 0 ? 'd-block' : 'd-none',
			$formId,
			$dis,
			$valj_efb[0]->button_color,
			$corner,
			$valj_efb[0]->el_height,
			$formId,
			$formId,
			$valj_efb[0]->type,
			'btn_send_efb',
			(strlen($valj_efb[0]->icon) > 3 && $valj_efb[0]->icon != 'bi-undefined' ? sprintf('<i class="efb %s mx-2 %s %s" id="button_group_icon"></i>', $valj_efb[0]->icon, $valj_efb[0]->icon_color, $valj_efb[0]->el_height) : ''),
			$valj_efb[0]->el_text_color,
			$valj_efb[0]->button_single_text
		);
	
		// Navigation buttons
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
	

	
	
		// Return button structure based on state
		return sprintf('<div class="efb footer-test p-1">%s</div>', $state == 0 ? $s : $d);
	}

    	/* field builder */
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
		// error_log($elementId);
		if(in_array($elementId, ["option","r_matrix"])) return;

		if (!in_array($elementId, ["html", "register", "login", "subscribe", "survey"])) {
			$pos = $this->get_position_col_el($vj, false);
		}
		$optn = '<!-- options -->';
		$pay = 'payefb';
		$iVJ = $indexVJ;
		$dataTag = 'text';
		$rndm = $this->valj_efb[$i]->id_;
		$desc=''; $label=''; $ttip=''; $div_f_id=''; $aire_describedby=''; $disabled=''; $ui=''; $elementSpecificFields=''; $js_s=''; $classes=''; $vtype=''; $elementSpecificFields = '';
			/*
		// + after check functionlity cheange below codes to
		 $desc =isset($vj->message) && strlen($vj->message)>0 ?$this->generateDescription_efb($element_Id, $vj, $pos) :'<!-- descripton not exist -->';
		 $label =isset($vj->name) && strlen($vj->name)   ? $this->generateLabel_efb($element_Id, $vj, $pos) :'<!-- label not exist -->';

		*/
		$style ='';
		if(!in_array($elementId,$nfield)){

		
		$desc = $this->generateDescription_efb($element_Id, $vj, $pos);
		$label = $this->generateLabel_efb($element_Id, $vj, $pos);
		$ttip = $this->generateTooltip_efb($element_Id);
		$div_f_id = $this->generateDivFId_efb($element_Id, $pos);
		$aire_describedby = !empty($vj->message) ? 'aria-describedby="' . $vj->id_ . '-des"' : "";
		$disabled = isset($vj->disabled) && $vj->disabled == 1 ? 'disabled' : '';
		$ui ='<!--efb ui-->';
		$dataTag = '<!--efb dataTag-->';
		$classes = isset($vj->el_border_color) ?  sprintf('form-control %s', $vj->el_border_color) : 'form-control' ;
		$vtype = in_array($elementId ,['imgRadio','chlCheckBox','chlRadio','payMultiselect','paySelect','payRadio','payCheckbox','trmCheckbox']) ? strtolower(substr($elementId,3)) : $elementId;
		$elementSpecificFields = $this->generateElementSpecificFields_efb($vj->type, $element_Id, $vj, $pos, $desc, $label, $ttip, $div_f_id, $aire_describedby, $disabled,$form_id,$texts);
		$js_s='<!--JS-->';		
		if(isset($vj->classes)) $classes .=' '. str_replace(',', ' ', $vj->classes) ?? '';
		}
        

		// error_log('pro:'.$pro);
		if (gettype($elementSpecificFields) == 'array') {
			$ui = $elementSpecificFields['ui'];
			$dataTag = $elementSpecificFields['dataTag'];
		} else {
			$corner= isset($vj->corner) ? $vj->corner : 'efb-square';
			switch ($vj->type) {
				// Other cases go here (e.g., 'pdate', 'ardate', 'range', 'maps', 'file', 'textarea', 'mobile', 'dadfile', etc.)
				// These cases should be processed similarly to how generateElementSpecificFields handles different element types
				case 'pdate':
				case 'ardate':
					$isPdate = $elementId === 'pdate';
					$inputClass = $isPdate ? 'efb pdpF2 pdp-el' : 'efb hijri-picker';

					$readonlyAttr = $elementId === 'ardate' && $disabled === "disabled" ? 'readonly' : '';
					$valueAttr = !empty($vj->value) ? sprintf('value="%s"', $vj->value) : '';
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
					// error_log('element ID:'.$element_Id);
					$dataTag = $elementId;
					$ui = $pro ? $ui : $this->public_pro_message_efb($texts['tfnapca']);

					if($isPdate){
						if(!is_dir(EMSFB_PLUGIN_DIRECTORY."/vendor/persiadatepicker")) {
							$this->efbFunction->download_all_addons_efb();
							return "<div id='body_efb' class='efb card-public row pb-3 efb px-2'  style='color: #9F6000; background-color: #FEEFB3;  padding: 5px 10px;'> <div class='efb text-center my-5'><h2 style='text-align: center;'></h2><h3 class='efb warning text-center text-darkb fs-4'>".esc_html__('We have made some updates. Please wait a few minutes before trying again.', 'easy-form-builder')."</h3><p class='efb fs-5  text-center my-1 text-pinkEfb' style='text-align: center;'><p></div></div>";
						}else{
							require_once(EMSFB_PLUGIN_DIRECTORY."/vendor/persiadatepicker/persiandate.php");
							$persianDatePicker = new persianDatePickerEFB() ; 	
						}
					}else{
						if(!is_dir(EMSFB_PLUGIN_DIRECTORY."/vendor/arabicdatepicker")) {
							$this->efbFunction->download_all_addons_efb();
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
						'%1$s <div class="efb %2$s col-sm-12 px-0 mx-0 ttEfb show" id="%3$s-f"> %4$s <div class="efb slider m-0 p-2 %5$s %6$s efb1 %7$s" data-css="%8$s" id="%3$s-range"> <input type="%9$s" class="efb input-efb px-2 mb-0 emsFormBuilder_v w-100 %10$s efbField" data-id="%3$s-el" data-vid="%3$s" data-formid="%8$s" id="%3$s_" oninput="fun_show_val_range_efb(\'%3$s\')" %11$s min="%12$s" max="%13$s" aria-required="%14$s" aria-label="%15$s" %16$s %17$s> <p id="%3$s_rv" class="efb mx-1 py-0 my-1 fs-6 text-darkb">%18$s</p> </div> %19$s',
						$label,
						$pos[3],
						$element_Id,
						$ttip,
						$vj->el_height,
						$vj->el_text_color,
						$classes,
						$form_id,
						'range',
						'range', // نوع ورودی
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
						<div class="efb %2$s col-sm-12 px-0 mx-0 ttEfb show" id="%3$s-f">
							%4$s
							<textarea id="%3$s_" placeholder="%5$s" class="efb px-2 input-efb emsFormBuilder_v form-control w-100 %6$s %7$s %8$s %9$s %10$s efbField efb1 %11$s" data-css="%3$s" data-vid="%3$s" data-id="%3$s-el"  data-formid="%20$s" value="%12$s" aria-required="%13$s" aria-label="%14$s" %15$s rows="5" %16$s %17$s>%18$s</textarea>
							%19$s',
							$label,  // %1$s
							$pos[3],  // %2$s
							$element_Id,  // %3$s
							$ttip,  // %4$s
							$vj->placeholder,  // %5$s
							($vj->required == 1 ? 'required' : ''),  // %6$s
							$vj->el_height,  // %7$s
							$corner,  // %8$s
							$vj->el_text_color,  // %9$s
							$classes,  // %10$s
							'',  // %11$s
							$vj->value,  // %12$s
							($vj->required == 1 ? 'true' : 'false'),  // %13$s
							$vj->name,  // %14$s
							$aire_describedby,  // %15$s
							$disabled,  // %16$s
							$minlen,  // %17$s
							$this->text_nr_efb($vj->value, 0),  // %18$s
							$desc,  // %19$s
							$form_id  // %20$s

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
					wp_register_script('intlTelInput-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/intlTelInput.min-efb.js', null, null, true);	
					wp_enqueue_script('intlTelInput-js');
					wp_register_style('intlTelInput-css', EMSFB_PLUGIN_URL . 'includes/admin/assets/css/intlTelInput.min-efb.css',true,EMSFB_PLUGIN_VERSION);
					wp_enqueue_style('intlTelInput-css');
					$ui = sprintf('
						%s
						%s
						%s
						%s
						%s',
						$label,
						$div_f_id,
						$ttip,
						$optn,
						$desc
					);
					
					$dataTag = "textarea";
				break;			
				case 'dadfile':
					
					// error_log('case dadfile');
					
					$el =$pro ? $this->dadfile_el_pro_efb(true, $element_Id, $vj,$form_id,$texts) : $this->public_pro_message_efb($texts['tfnapca']);
					$ui = sprintf('
						%1$s
						<div class="efb %2$s col-sm-12 px-0 mx-0 ttEfb show" id="%3$s-f">
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
							// error_log(json_encode($i));
							// $id, $link, $row, $state = true, $text
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
							<div class="efb %s col-sm-12 px-0 mx-0 py-0 my-0 ttEfb show" data-id="%s-el" id="%s-f">
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
					// $previewSate, $rndm, $vj, $form_id,$texts
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

					// تولید HTML المان نقشه با استفاده از sprintf و اضافه کردن formId
					//1$s
					$ui .= sprintf(
						"<div class='efb col-md-12' id='%1\$s-f' data-formid='%2\$s'>
							<label for='%1\$s_' class='efb form-label text-labelEfb'>
								<span>%3\$s</span>
								<span class='text-danger' role='none'>%4\$s</span>
							</label>
							<div class='efb maps-efb maps-os emsFormBuilder_v' id='%1\$s-map' data-vid='%1\$s' style='height: %5\$s;' data-formid='%2\$s' data-lat='%6\$s' data-lng='%7\$s' data-zoom='%8\$s' data-id='%1\$s-el' %9\$s></div>
							<input type='hidden' name='%1\$s-lat' id='%1\$s_lat' value='%6\$s' class='efb emsFormBuilder_v'  data-formid='%2\$s' data-type='maps' data-vid='%1\$s' %10\$s>
							<input type='hidden' name='%1\$s-lng' id='%1\$s_lng' value='%7\$s' class='efb emsFormBuilder_v'  data-formid='%2\$s' data-type='maps' data-vid='%1\$s' %10\$s>
							<small id='%1\$s-des' class='form-text text-muted'>%11\$s</small>
						</div>",
						$element_Id,  // %1$s
						$formId,      // %2$s
						$label,       // %3$s
						($required ? '*' : ''), // %4$s
						$el_height,   // %5$s
						$lat,         // %6$s
						$lng,         // %7$s
						$zoom,        // %8$s
						$ariaDescribedBy, // %9$s
						$required,    // %10$s
						$message      // %11$s
					);
		
	
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
							}
		
							document.addEventListener('DOMContentLoaded', function() {
								efbCreateMap_%s();
							});
						</script>",
						$element_Id, // شناسه نقشه برای اطمینان از یکتایی
						$element_Id, $lat, $lng, $zoom, // تنظیمات اولیه نقشه
						$lat, $lng, // موقعیت اولیه مارکر
						$element_Id, $element_Id, // به‌روزرسانی عرض و طول در inputهای مخفی
						$element_Id // شناسه تابع جاوااسکریپت برای بارگذاری نقشه
					);
					if ($pro!==true &&  $pro!==1) {
						$ui = $this->public_pro_message_efb($texts['tfnapca']);
					} 
					break;
					$dataTag = "maps";
				break;
			/* 	case 'switch':
					// switch_el_pro_efb($previewSate, $pos, $rndm, $vj, $desc, $formId, $label, $ttip, $aire_describedby, $texts)
					$ui ="<!-- switch -->";
					$ui = $pro == true ? $this->switch_el_pro_efb(true, $pos, $rndm, $vj, $desc, $form_id, $label, $ttip, $aire_describedby, $texts)  : $this->public_pro_message_efb($texts['tfnapca']);
					$ui .="<!-- switch -->";
					$dataTag = $elementId;
				break; */
				case 'rating':

					$ui = $pro == true ? $this->rating_el_pro_efb(true, $pos, $rndm, $vj, $desc, $form_id, $label, $ttip, $aire_describedby, $texts) : $this->public_pro_message_efb($texts['tfnapca']);
					$dataTag = $elementId;
				break;
                case 'select':
                case 'paySelect':
                    // generate_select_efb($elementId, $rndm, $vj, $pos, $formId, $texts, $previewSate=true ,$desc,$label,$ttip,$aire_describedby )
                    // error_log('select');
  
                    $ui = $this->generate_select_efb($elementId, $rndm, $vj, $pos, $form_id, $texts, true ,$desc,$label,$ttip,$aire_describedby);
                    $dataTag = $elementId;
                break;
				case 'conturyList':
				case 'country':
					// error_log('country');
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
					// error_log('multiselect');
					// error_log($desc);
					//$elementId, $rndm, $vj, $pos, $formId, $texts, $desc, $label, $ttip, $previewSate
					$ui = $pro == true ? $this->generate_multiselect_efb($elementId, $rndm, $vj, $pos, $form_id, $texts,$desc,$label,$ttip,$aire_describedby) : $this->public_pro_message_efb($texts['tfnapca']);
					$dataTag = $elementId;
				break;

				case 'html':
					//generate_html_code_efb($rndm, $vj, $pos, $formId, $texts, $previewSate)
					$ui = $pro == true ? $this->generate_html_code_efb($rndm, $vj, $pos, $form_id, $texts, true) : $this->public_pro_message_efb($texts['tfnapca']);
					$dataTag = $elementId;
				break;
				case 'heading':
					//generate_heading_efb($rndm, $pos, $vj, $formId)
					$ui = $pro == true ? $this->generate_heading_efb($rndm, $pos, $vj, $form_id) : $this->public_pro_message_efb($texts['tfnapca']);
					$dataTag = $elementId;

				break;
				case 'link':
					//generate_link_efb($previewState, $pos, $rndm, $vj, $formId)
					$ui = $pro == true ? $this->generate_link_efb(true, $pos, $rndm, $vj, $form_id) : $this->public_pro_message_efb($texts['tfnapca']);
					$dataTag = $elementId;
				break;
				case 'yesNo':
					if($pro!==true && $pro!==1){
						$ui =$this->public_pro_message_efb($texts['tfnapca']);
						break;
					}
					//generate_yes_no_efb($previewState, $pos, $rndm, $vj, $formId)
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

					$ui = "" . $label ."<div class='efb $pos[3] col-sm-12 px-0 mx-0 ttEfb show'  id='$rndm-f'> ". $ttip .  $r  .$desc ;
					$dataTag = $elementId;
				break;
				case 'pointr10':

					if($pro!==true && $pro!==1){
						$ui =$this->public_pro_message_efb($texts['tfnapca']);
						break;
					}
					$r  = $this->pointer10_el_pro_efb(true, $vj, $form_id);
					$ui = "" . $label ."<div class='efb $pos[3] col-sm-12 px-0 mx-0 ttEfb show'  id='$rndm-f'> ". $ttip .  $r  .$desc ;
					$dataTag = $elementId;
				break;
				case 'smartcr':
					if($pro!==true && $pro!==1){
						$ui =$this->public_pro_message_efb($texts['tfnapca']);
						break;
					}
					$r  = $this->smartcr_el_pro_efb(true, $vj, $form_id);
					$ui = "" . $label ."<div class='efb $pos[3] col-sm-12 px-0 mx-0 ttEfb show'  id='$rndm-f'> ". $ttip .  $r  .$desc ;
					$dataTag = $elementId;
				break;
				case 'table_matrix':
					if($pro!==true && $pro!==1){
						$ui =$this->public_pro_message_efb($texts['tfnapca']);
						break;
					}
					//table_matrix_el_pro_efb($elementId, $vj, $rndm, $position_l_efb, $previewSate, $aire_describedby, $label, $ttip, $desc)
					$ui  = $this->table_matrix_el_pro_efb($elementId, $vj, $rndm, $position_l_efb, true, $aire_describedby, $label, $ttip, $desc,$form_id,$pos);
					$dataTag = $elementId;

				break;
				case 'prcfld':
					if($pro!==true && $pro!==1){
						$ui =$this->public_pro_message_efb($texts['tfnapca']);
						break;
					}
					$maxlen = (property_exists($vj, 'mlen') && $vj->mlen > 0) ? 'maxlength="' . $vj->mlen . '"' : '';
    
					// Set minlength attribute
					$minlen = (property_exists($vj, 'milen') && $vj->milen > 0) ? 'minlength="' . $vj->milen . '"' : '';
					
					// Determine currency symbol
					$dataTag = (!property_exists($this->valj_efb[0], 'currency')) ? 'usd' : $this->valj_efb[0]->currency;
					//convert to up
					
					$classes = $this->get_currency_details_efb($dataTag);
					
					$dataTagHtml = '<span class="efb input-group-text crrncy-clss">' . $classes['s'] . '</span>';
					
					// Set additional classes
					$classes = 'form-control ' . $vj->el_border_color;
					
					// Generate UI
					$ui = sprintf(
						'%s
						<div class="efb %s col-sm-12 px-0 mx-0 ttEfb show" id="%s-f" data-formId="%s">
							%s
							<div class="efb input-group m-0 p-0">
								%s
								<input type="number" class="efb input-efb px-2 mb-0 payefb emsFormBuilder_v %s %s %s %s %s efbField efb1 %s" data-id="%s-el" data-vid="%s" data-css="%s" id="%s_" placeholder="%s" %s %s %s %s %s %s>
								%s
							</div>
							%s',
						$label, // Label for the input
						$pos[3], // Position
						$rndm, // Random ID
						$form_id, // Form ID
						$ttip, // Tooltip
						is_rtl() ? '' : $dataTagHtml, // Currency symbol on the left for LTR
						$classes, // Input classes
						$vj->el_height, // Element height
						$corner, // Border radius
						$vj->el_text_color, // Text color
						($vj->required == 1 || $vj->required == true) ? 'required' : '', // Required attribute
						str_replace(',', ' ', $vj->classes), // Additional classes
						$rndm, // Data ID
						$rndm, // Data vid
						$rndm, // CSS ID
						$rndm, // Element ID
						htmlspecialchars($vj->placeholder), // Placeholder text
						($vj->value && strlen($vj->value) > 0) ? 'value="' . htmlspecialchars($vj->value) . '"' : '', // Value attribute
						$aire_describedby, // Aria described by
						$maxlen, // Maxlength attribute
						$minlen, // Minlength attribute
						'', // Readonly attribute
						$disabled == 'disabled' ? 'readonly' : '', // Disabled attribute
						is_rtl() ? $dataTagHtml : '', // Currency symbol on the right for RTL
						$desc // Description
					);

					$dataTag = $elementId;
				break;
				case 'ttlprc':
					if($pro!==true && $pro!==1){
						$ui =$this->public_pro_message_efb($texts['tfnapca']);
						break;
					}
					//totalprice_el_pro_efb($rndm, $vj ,$currency)
					$currency = isset($this->valj_efb[0]->currency) ? $this->valj_efb[0]->currency : 'USD';
					$r  = $this->totalprice_el_pro_efb($rndm, $vj ,$currency,$form_id);
					$class = isset($vj->classes) ? $vj->classes : '';
				
					$ui = sprintf(
						'%s<div class="efb %s col-sm-12 pt-2 pb-1 px-0 mx-0 ttEfb show %s" id="%s-f">%s%s</div>',
						$label,  // %s
						$pos[3],  // %s
						$class,  // %s
						$rndm,  // %s
						$r,  // %s
						$desc  // %s
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
					//$rndm , $cl, $sub,$form_id,$texts
					$ui = $this->add_ui_stripe_efb($rndm , $cl, $sub,$form_id,$texts);
				
					$dataTag = $elementId;
				break;
				case "persiaPay":
				case "zarinPal":

					if($pro!==true && $pro!==1){
						$ui =$this->public_pro_message_efb($texts['tfnapca']);
						break;
					}

					//wp_register_script('parsipay_js', plugins_url('../public/assets/js/persia_pay-efb.js',__FILE__), array('jquery'), EMSFB_PLUGIN_VERSION, true);
					//easy-form-builder\vendor\persiapay\persia_pay-efb.js
					wp_register_script('parsipay_js', EMSFB_PLUGIN_URL . 'public/assets/js/persia_pay-efb.js', array('jquery'), EMSFB_PLUGIN_VERSION, true);
					wp_enqueue_script('parsipay_js');

					$ui = $this->add_ui_zp_efb($rndm , $form_id,$texts);
					$dataTag = $elementId;

				break;
			
			
			}
		}


		// error_log('ui=>'.$ui);
		// error_log('dataTag'. $dataTag);
		if ($vj->type != "form" && $dataTag != "step" && $vj->type != 'option') {
			$hidden = isset($vj->hidden) && $vj->hidden == 1 ? 'd-none' : '';
			$tagId = in_array($elementId, ["firstName", "lastName", "address", "address_line", "postalcode"]) ? 'text' : $elementId;
			$tagT = in_array($elementId, ["esign", "yesNo", "rating"]) ? '' : 'def';
			$stepNo = (int)$vj->step - 1;
			$newElement = sprintf(
				'<!--startTag %1$s--><div class="efb my-1 mx-0 %1$s %2$s %3$s %4$s ttEfb %5$s %6$s col-sm-12 efbField %7$s" data-step="%8$s" data-amount="%9$s" data-id="%10$s-id" id="%10$s" data-tag="%11$s">',
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
				$elementId
			);
			
			if ($elementId != 'option') {
				$newElement .= $ui;
			}
			
			if (!in_array($elementId, ['option', 'html', 'stripe', 'heading', 'link','conturyList','country','stateProvince','statePro','city','cityList','maps'])) {
				$newElement .= '<!--test2--></div></div>';
			} else {
				$newElement .= '<!--test--></div>';
			}
			
			$newElement .= sprintf('<!--endTag %s-->', $elementId);
			 error_log('newElement: reult'.$newElement);
			// error_log('style: reult'.$style);
			return [$newElement ,$style];
		}
	}

	public function show_user_profile_emsFormBuilder($text_logout, $formId) {
		// Sanitize and escape output for security
		$user_login = wp_get_current_user();
		$display_name = esc_html($user_login->display_name);
		$user_image = get_avatar_url($user_login->ID);
		$logout_text = esc_html($text_logout);
	
		$user_id =  $user_login->user_login ?? $user_login->user_email;
	

	
		// Return the user profile HTML
		return sprintf(
			'<div class="efb mt-5" data-formId="%s">
				<div class="efb card-block text-center text-dark">
					<div class="efb mb-3 d-flex justify-content-center">
						<img src="%s" class="efb userProfileImageEFB" alt="%s">
					</div>
					<h6 class="efb fs-5 mb-1 d-flex justify-content-center text-dark">%s</h6>
					<p class="efb fs-6">%s</p>
					<button type="button" class="efb btn fs-5 btn-lg btn-danger efb mt-1" onclick="emsFormBuilder_logout()"  data-formId="%s">%s</button>
				</div>
			</div>',
			$formId,
			$user_image,
			$display_name,
			$display_name,
			$user_id,
			$formId,
			$logout_text
		);
	}


	public function addStyleColorBodyEfb($t, $c, $type, $id, $vj) {
		// Determine type based on id and default assignment
		$ttype = ($id == -1) ? $type : $vj->type;
		
		// Set the color style for CSS
		$v = ".$t { color: $c !important; }";
		$tag = "";
	
		// Switch-case to determine the tag type
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
	
		// Ensure color starts with '#' if not already
		if ($c[0] != "#") $c = "#$c";
	
		// Call helper function to add custom style
		return $this->efb_add_custom_color($t, $c, $v, $type);
	}
	
	public function efb_add_custom_color($t, $c, $v, $type) {
		$n = '';
		if ($c[0] != "#") $c = "#$c";
	
		// Determine style class based on type
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
	
		// Inject style into the page		
		return $v;
	}
	
	public function fun_addStyle_customize_efb($val, $key, $vj) {
		// Check if the value includes 'colorDEfb' and set type and color
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
	
			// Call addStyleColorBodyEfb if color is set
			if ($color != "") {
				return $this->addStyleColorBodyEfb("colorDEfb-" . substr($color, 1), substr($color, -6), $type, -1, $vj);
			}
		}
	}
	public function check_error_console_efb(){


	$file_text = esc_html__('File', 'easy-form-builder');
	$line_text = esc_html__('Line', 'easy-form-builder');
	$column_text = esc_html__('Column', 'easy-form-builder');
	$error_text = esc_html__('Error Message', 'easy-form-builder');
	$origin_text = esc_html__('This error originates from the %s', 'easy-form-builder');
	$plugin_text = esc_html__('plugin', 'easy-form-builder');
	$theme_text = esc_html__('theme', 'easy-form-builder');
	$interfere_text = esc_html__('It may interfere with the functionality of the Easy Form Builder plugin.', 'easy-form-builder');
	$contact_text = esc_html__('For further assistance, please contact support.', 'easy-form-builder');
	$efb = esc_html__('Easy Form Builder', 'easy-form-builder') .':\n';
	
	$value = '
	window.onerror = function (message, source, lineno, colno, error) {
		const wpContentRegex = /wp-content\/(plugins|themes)\/([^/]+)\/(.*)/;
		const wpIncludesRegex = /wp-includes\/(.*)/;
		let errorMessage = `'.$efb.''.$error_text.': ${message}\n`;
	
		if (wpContentRegex.test(source)) {
			const matches = source.match(wpContentRegex);
			const type = matches[1];
			const slug = matches[2];
			const filePath = matches[3];
	
			if (type === "plugins") {
				errorMessage += `'.$origin_text.': '.$plugin_text.' "${slug}".\n`;
			} else if (type === "themes") {
				errorMessage += `'.$origin_text.': '.$theme_text.' "${slug}".\n`;
			}
			errorMessage += `'.$file_text.': ${source}\n'.$line_text.': ${lineno}, '.$column_text.': ${colno}\n`;
			errorMessage += `'.$interfere_text.'\n'.$contact_text.'`;
		} else if (wpIncludesRegex.test(source)) {
			const filePath = source.match(wpIncludesRegex)[1];
			errorMessage += `'.$origin_text.': wp-includes/${filePath} '.$file_text.': ${source}\n'.$line_text.': ${lineno}, '.$column_text.': ${colno}\n'.$contact_text.'`;
		} else {
			errorMessage += `'.$file_text.': ${source}\n'.$line_text.': ${lineno}, '.$column_text.': ${colno}\n'.$contact_text.'`;
		}
	
		alert(errorMessage);
	};';
	
	return $value;
	
	
	}
	
}

