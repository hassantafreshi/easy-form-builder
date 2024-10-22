<?php
 namespace Emsfb;
    class Formbuilder {
       public $valj_efb;
       private $pro_efb = false;
        public function __construct( $valj_efb, $pro_efb ) {
            $this->valj_efb =  $valj_efb;
            $this->pro_efb = $pro_efb;
            error_log( print_r( $this->valj_efb, true ) );
            error_log( print_r( $this->pro_efb, true ) );
        }



        
	/* field builder */
	private function generateDescription_efb($rndm, $vj, $pos) {
		// error_log('generateDescription_efb');
		// error_log($vj->message_align);
		// error_log($pos[1]);
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
				$textElements = ['firstName', 'lastName', 'postalcode', 'address_line'];
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
				// تنظیم مقادیر 'on' و 'off' در صورت عدم وجود
				$vj->on = $vj->on ?? $texts['on'];
				$vj->off = $vj->off ?? $texts['off'];

				// استفاده از sprintf برای ساختن رشته HTML به صورت بهینه
				$ui = sprintf('
					%s
					%s
					<div class="efb %s col-sm-12 px-0 mx-0 ttEfb show" id="%s-f" %s>
						<label class="efb fs-6" id="%s_off">%s</label>
						
						<button type="button" data-state="off" class="efb btn %s btn-toggle efb1 %s" data-css="%s" data-toggle="button" aria-pressed="false" data-vid="%s" onclick="fun_switch_efb(this)" data-id="%s-el" data-formId="%s" id="%s_" %s>
							<div class="efb handle"></div>
						</button>
						<label class="efb fs-6" id="%s_on">%s</label>
						<div class="efb mb-3">%s</div>
					</div>',
					$label,
					$ttip,
					$pos[3],
					$rndm,
					$aire_describedby,

					$rndm, // id for the first label
					$vj->off,

					$vj->el_height,
					str_replace(',', ' ', $vj->classes),
					$rndm,
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
		//error_log('fields: '.json_encode($fields));
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
		error_log('generateTextInput_efb');
		error_log(json_encode($lenAttributes));
		$corener = isset($vj->corner) ? $vj->corner : 'efb-square';
		$required = ($vj->required == 1 || $vj->required == true) ? 'required' : '';
		$value = !empty($vj->value) ? 'value="' . $vj->value . '"' : '';
		$aria_required = ($vj->required == 1) ? 'true' : 'false';
		$readonly = ($disabled == "disabled") ? 'readonly' : '';
		$el_height = isset($vj->el_height) ? $vj->el_height : '';
		$el_text_color = isset($vj->el_text_color) ? $vj->el_text_color : '';
		$additional_classes = isset($vj->classes) ? str_replace(',', ' ', $vj->classes) : '';
	
		return sprintf(
			'%s %s %s <input type="%s" class="efb input-efb px-2 mb-0 emsFormBuilder_v w-100 %s %s %s %s %s efbField efb1 %s" data-id="%s-el" data-vid="%s" data-formId="%s" data-css="%s" id="%s_" %s %s aria-required="%s" aria-label="%s" %s autocomplete="%s" %s %s %s> %s',  $label,  $div_f_id,  $ttip,  $type,  $classes,  $el_height,  $corener,  $el_text_color,  $required,  $additional_classes,  $rndm,  $rndm,  $form_id,  $rndm,  $rndm,  $placeholder,  $value,  $aria_required,  $vj->name,  $aire_describedby,  $autocomplete,  $lenAttributes['maxlen'],  $lenAttributes['minlen'],  $readonly,  $desc
		);
	}

	/* field builder */
	private function generateSwitchInput_efb($vj, $rndm, $desc, $label, $ttip, $div_f_id, $aire_describedby, $disabled) {
		return '
		' . $label . '
		' . $ttip . '
		<div class="efb ' . $pos[3] . ' col-sm-12 px-0 mx-0 ttEfb show" id ="' . $rndm . '-f" ' . $aire_describedby . '>
		<label class="efb fs-6" id="' . $rndm . '_off">' . $vj->off . '</label>
		<button type="button" data-state="off" class="efb btn ' . $vj->el_height . ' btn-toggle efb1 ' . str_replace(',', ' ', $vj->classes) . '" data-css="' . $rndm . '" data-toggle="button" aria-pressed="false" data-vid="' . $rndm .'" data-formId="' . $form_id . '" onclick="fun_switch_efb(this)" data-id="' . $rndm . '-el" id="' . $rndm . '_" ' . $disabled . '>
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
		//error_log('public_pro_message_efb: '.$r);
		return $r;
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
	
		// Call the function to load intlTelInput
		$js =sprintf(
			'
			setTimeout(function() {
				const iti = window.intlTelInput(document.getElementById("%1$s_"), {
					onlyCountries: onlyCountries,
					autoHideDialCode: true,
					placeholderNumberType: "MOBILE",
					utilsScript: efb_var.images.utilsJs,
				});
				document.getElementById("%1$s_").addEventListener("blur", function() {
					const errorMap = [efb_var.text.cpnnc, efb_var.text.icc,efb_var.text.cpnts,efb_var.text.cpntl, efb_var.text.cpnnc];
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
			$vj->type
    	);
	
		// Create the HTML string
		$inputPhone = sprintf(
			'<input type="phone" class="efb input-efb intlPhone px-2 mb-0 emsFormBuilder_v form-control %1$s %2$s %3$s %4$s %5$s efbField efb1 %6$s" data-css="%7$s" data-id="%7$s-el" data-formId="%13$s" data-vid="%7$s" id="%7$s_" aria-required="%8$s" aria-label="%9$s" %10$s %11$s %12$s>
			<input type="phone" class="efb input-efb intlPhone px-2 mb-0 emsFormBuilder_v form-control %1$s %2$s %3$s %4$s %5$s efbField d-none efb1 %6$s" data-css="%7$s" data-id="%7$s-el" data-formId="%13$s data-vid="%7$s" id="%7$s-code" placeholder="verify" %11$s %12$s %10$s>',
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
			$form_id
		);
	
		$buttonSubmit = sprintf(
			'<button id="%1$s-btn" type="submit" class="efb d-none">Submit</button>',
			$rndm
		);
	
		return [$inputPhone  . $buttonSubmit ,$js];
	}

	/* field builder */
	public function esign_el_pro_efb($previewSate,$pos, $rndm, $vj,$message, $formId,$updateUrbrowser) {
		//true, $pos, $rndm, $vj, $desc
		error_log('esign_el_pro_efb');
		error_log('esign_el_pro_efb: '.json_encode($vj));
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
				"<div class='efb %s col-sm-12' id='%s-f' data-form-id='%s'>
					<canvas class='efb sign-efb bg-white %s %s %s %s efb1 %s' data-css='%s' data-code='%s' data-id='%s-el' id='%s_' %s>
						%s
					</canvas>
					%s
					<div class='efb mx-1' data-form-id='%s'>%s</div>
					<div class='efb mb-3' data-form-id='%s'>
						<button type='button' class='efb btn %s %s efb-btn-lg mt-1 fs-6 %s' id='%s_b' onclick='fun_clear_esign_efb(\"%s\")'>
							<i class='efb %s mx-2 %s' id='%s_icon'></i>
							<span id='%s_button_single_text' class='efb %s' %s>%s</span>
						</button>
					</div>
				</div>",
				$pos[3], // کلاس‌های موقعیت
				$randomId, $formId, // شناسه و formId
				$el_height, $corner, $el_text_color, $vj->el_border_color,
				str_replace(',', ' ', $classes), // کلاس‌های اضافی
				$randomId, $randomId, $randomId, $randomId, // شناسه و داده‌ها
				$ariaDescribedBy,
				$updateUrbrowser, // پیغام به‌روزرسانی مرورگر
				$previewSate ? sprintf(
					"<input type='hidden' data-type='esign' data-vid='%s' class='efb emsFormBuilder_v %s' id='%s-sig-data' value='Data:image/gif;base64,R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==' data-form-id='%s'>",
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
			
			
			$file = property_exists($vj, 'file') ? $vj->file : '';
			$fileType = $file;
			if ($file == 'customize') {
				$fileType = $vj->file_ctype;
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
				<span class="efb fs-7">%6$s</span>
				<button type="button" class="efb btn %7$s efb-btn-lg fs-6" id="%3$s_b" %8$s>
					<i class="efb bi-upload mx-2 fs-6"></i>%9$s
				</button>
				<input type="file" hidden="" accept="%10$s" data-type="dadfile" data-vid="%3$s" data-id="%3$s" class="efb emsFormBuilder_v %11$s" id="%3$s_" data-id="%3$s-el" data-formId="%13$s" %12$s %8$s>',
				$vj->icon,
				$vj->icon_color,
				$vj->id_,
				$texts[0],  //mainText
				$fileType,
				$texts[1],  //or
				$vj->button_color,
				$disabled,
				$texts[2],  //browseFile
				$fileTypeAttr,
				$requiredClass,
				$readonlyAttr,
				$form_id
			);
		}
		$ui = ui_dadfile_efb($vj, $previewSate, $form_id, $texts , $disabled, $corner);
		return sprintf(
			'<div class="efb mb-3" id="uploadFilePreEfb" data-formId="%s">
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
	private function formatPrice_efb($amount, $currency) {
   
		$currency_details = $this->get_currency_details_efb($currency);
    	$formatted_amount = number_format_i18n($amount, $currency_details['d']);
   	 	return $currency_details['s'] . ' ' . $formatted_amount;

    }

	/* field builder */
	function get_currency_details_efb($currency) {
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
		//ColorNameToHexEfbOfElEfb(color.slice(4),'btn') //slice text=5 bg=2 border=6 btn=3 icon=4
		//ColorNameToHexEfbOfElEfb(color.slice(7),'border') //slice text=5 bg=2 border=6 btn=3     
		error_log('ColorNameToHexEfbOfElEfb v:'.$v .' n:'.$n);
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
		error_log($r);
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
			'%s
			%s
			<div class="efb %s col-sm-12 px-0 mx-0 ttEfb show" id="%s-f" %s data-form-id="%s">
				<label class="efb fs-6" id="%s_off">%s</label>
				<button type="button" data-state="off" class="efb btn %s btn-toggle efb1 %s" data-css="%s" data-toggle="button" aria-pressed="false" data-vid="%s" onClick="fun_switch_efb(this)" data-id="%s-el" id="%s_" %s %s>
					<div class="efb handle"></div>
				</button>
				<label class="efb fs-6" id="%s_on">%s</label>
				<div class="efb mb-3">%s</div>
			</div>',
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
	
		// ایجاد HTML برای rating element
		$ui = sprintf(
			'<div class="efb %s col-sm-12" id="%s-f" data-form-id="%s">
				<div class="efb star-efb d-flex justify-content-center %s efb1 %s" data-css="%s" %s>
					%s
					%s
					%s
					%s
					%s
				</div>
				<input type="hidden" data-vid="%s" data-type="rating" class="efb emsFormBuilder_v %s" id="%s-stared" data-form-id="%s">
			</div>',
			$pos[3], // موقعیت
			$rndm, $formId, // شناسه و فرم آی‌دی
			$disabled, $classes, $rndm, // کلاس‌ها و داده‌ها
			$ariaDescribedBy,
			$this->generate_rating_input($rndm, 5, $previewSate, $disabled, $el_height, $texts['stars']),
			$this->generate_rating_input($rndm, 4, $previewSate, $disabled, $el_height, $texts['stars']),
			$this->generate_rating_input($rndm, 3, $previewSate, $disabled, $el_height, $texts['stars']),
			$this->generate_rating_input($rndm, 2, $previewSate, $disabled, $el_height, $texts['stars']),
			$this->generate_rating_input($rndm, 1, $previewSate, $disabled, $el_height, $texts['stars']),
			$rndm, $requiredClass, $rndm, $formId
		);
	
		return $ui;
	}


	/* field builder */
	private function generate_rating_input($rndm, $starValue, $previewSate, $disabled, $el_height, $starText) {
		return sprintf(
			'<input type="radio" id="%s-star%s" data-vid="%s" data-type="rating" class="efb" data-star="star" name="%s-star-efb" value="%s" data-name="star" data-id="%s-el" %s %s>
			<label id="%s_star%s" for="%s-star%s" %s title="%s stars" class="efb %s star %s"> %s %s </label>',
			$rndm, $starValue, // شناسه و مقدار ستاره
			$rndm, $rndm, $starValue, // داده‌ها و مقدار ستاره
			$rndm, // داده‌های ID
			$previewSate != true ? 'disabled' : '', $disabled, // حالت‌های پیش‌نمایش و غیرفعال
			$rndm, $starValue, $rndm, $starValue, // شناسه و مقدار ستاره
			($previewSate == true && $disabled == '') ? sprintf('onClick="fun_get_rating_efb(\'%s\',%s)"', $rndm, $starValue) : '',
			$starValue, $el_height, $disabled, // عنوان و کلاس‌های المان
			$starValue, $starText // مقدار ستاره و متن ستاره
		);
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
		//error_log('filter_attributes_by_type_efb');
		//error_log($type);
		//error_log(json_encode($attribute_map_efb[$type]));

			if (isset($attribute_map_efb[$type])) {
				$allowed_attributes_efb_type = is_array($attribute_map_efb[$type]) ?  array_replace($allowed_attributes_efb, $attribute_map_efb[$type]) :$allowed_attributes_efb;
				//error_log(json_encode($allowed_attributes_efb_type));
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
       

    	/* field builder */
	public function addNewElement_efb($i, $rndm,$form_id,$texts) {
		error_log('addNewElement_efb');
		//error_log(json_encode($this->valj_efb[$i]));
		error_log(json_encode($this->valj_efb[$i]->type));
		$element_Id = $this->valj_efb[$i]->id_;
		$elementId = $this->valj_efb[$i]->type;
		$pos = array("", "", "", "");
		$indexVJ = $i;
		$vj = $this->valj_efb[$indexVJ];
		//error_log($elementId);
		if(in_array($elementId, ["option"])) return;

		if (!in_array($elementId, ["html", "register", "login", "subscribe", "survey"])) {
			$pos = $this->get_position_col_el($vj, false);
		}
		$optn = '<!-- options -->';
		$pay = 'payefb';
		$iVJ = $indexVJ;
		$dataTag = 'text';
		/*
		//+ after check functionlity cheange below codes to
		 $desc =isset($vj->message) && strlen($vj->message)>0 ?$this->generateDescription_efb($element_Id, $vj, $pos) :'<!-- descripton not exist -->';
		 $label =isset($vj->name) && strlen($vj->name)   ? $this->generateLabel_efb($element_Id, $vj, $pos) :'<!-- label not exist -->';

		*/
		$style ='';
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
		$pro =0;
		$pro = $this->pro_efb;
		$classes .=' '. str_replace(',', ' ', $vj->classes) ?? '';
		$currency = isset($this->valj_efb[0]->currency) ? $this->valj_efb[0]->currency : 'USD';


		//error_log('pro:'.$pro);
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
						'%1$s %2$s %3$s <input type="text" class="%4$s input-efb px-2 mb-0 emsFormBuilder_v w-100 %5$s %6$s %7$s %8$s %9$s efbField efb1 %10$s" data-css="%11$s" data-id="%11$s-el" data-vid="%11$s" data-formId="%12$s" id="%11$s_" %13$s aria-required="%14$s" aria-label="%15$s" %16$s %17$s> %18$s',
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
					//error_log('element ID:'.$element_Id);
					$dataTag = $elementId;
					$ui = $pro ? $ui : $this->public_pro_message_efb($texts['tfnapca']);
					
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
						'%1$s <div class="efb %2$s col-sm-12 px-0 mx-0 ttEfb show" id="%3$s-f"> %4$s <div class="efb slider m-0 p-2 %5$s %6$s efb1 %7$s" data-css="%8$s" id="%3$s-range"> <input type="%9$s" class="efb input-efb px-2 mb-0 emsFormBuilder_v w-100 %10$s efbField" data-id="%3$s-el" data-vid="%3$s" data-formId="%8$s" id="%3$s_" oninput="fun_show_val_range_efb(\'%3$s\')" %11$s min="%12$s" max="%13$s" aria-required="%14$s" aria-label="%15$s" %16$s %17$s> <p id="%3$s_rv" class="efb mx-1 py-0 my-1 fs-6 text-darkb">%18$s</p> </div> %19$s',
						$label,
						$pos[3],
						$element_Id,
						$ttip,
						$vj->el_height,
						$vj->el_text_color,
						$classes,
						$form_id,
						$form_id,
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
						<input type="%4$s" class="efb input-efb px-2 py-1 emsFormBuilder_v w-100 %5$s %6$s %7$s efbField efb1 %8$s %16$s" data-css="%9$s" data-vid="%9$s" data-id="%9$s-el" data-formId="%15$s" id="%9$s_" aria-required="%10$s" aria-label="%11$s" %12$s %13$s>
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
							<textarea id="%3$s_" placeholder="%5$s" class="efb px-2 input-efb emsFormBuilder_v form-control w-100 %6$s %7$s %8$s %9$s %10$s efbField efb1 %11$s" data-css="%3$s" data-vid="%3$s" data-id="%3$s-el"  data-formId="%19$s" value="%12$s" aria-required="%13$s" aria-label="%14$s" %15$s rows="5" %16$s %17$s>%18$s</textarea>
							%19$s',
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
						$vj->value,
						($vj->required == 1 ? 'true' : 'false'),
						$vj->name,
						$aire_describedby,
						$disabled,
						$minlen,
						$this->text_nr_efb($vj->value, 0),
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
					$txts = [$texts['dragAndDropA'],$texts['or'],$texts['browseFile']];
					//error_log('case dadfile');
					//error_log(json_encode($this->text_));
					$el =$pro ? $this->dadfile_el_pro_efb(true, $element_Id, $vj,$form_id,$txts) : $this->public_pro_message_efb($texts['tfnapca']);
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
							$prc = isset($i->price) ? intval($i->price) : 0;
							if($pay!='') $prc = $this->formatPrice_efb($prc, $currency );
							$optn .= sprintf(
								'<div class="efb form-check %s %s %s efb1 %s mt-1" data-css="%s" data-parent="%s" data-id="%s" data-formId="%s" id="%s-v">
									<input class="efb form-check-input emsFormBuilder_v %s %s" data-tag="%s" data-type="%s" data-vid="%s" type="%s" name="%s" value="%s" id="%s" data-id="%s-id" data-formId="%s" data-op="%s" %s %s %s>
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
								$elementId != 'imgRadio' ? sprintf('<label class="efb %s %s %s %s hStyleOpEfb" id="%s_lab" for="%s">%s</label>', isset($vj->pholder_chl_value) ? 'col-8' : '', $vj->el_text_color, $vj->el_height, $vj->label_text_size, $i->id_, $i->id_, $this->fun_get_links_from_string_Efb($i->value, true)) : $this->fun_imgRadio_efb($i->id_, $i->src ?? '', $i),
								strpos($elementId, 'chl') !== false ? sprintf('<input type="text" class="efb %s %s checklist col-2 hStyleOpEfb emsFormBuilder_v border-d" data-id="%s" data-type="%s" data-vid="" id="%s_chl" placeholder="%s" disabled>', $vj->el_text_color, $vj->el_height, $i->id_, $dataTag, $i->id_, $vj->pholder_chl_value) : '',
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
							</div>
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
					' . ($this->pro_efb == true ? $this->esign_el_pro_efb(true, $pos, $rndm, $vj, $desc,$form_id,$texts['updateUrbrowser']) : $this->public_pro_message());
					//$previewSate, $rndm, $vj, $form_id,$texts
					$dataTag = $elementId;
				break;	
				case 'maps':

					$lat = isset($valj_efb[$randomId]['lat']) ? $valj_efb[$randomId]['lat'] : '0';
					$lng = isset($valj_efb[$randomId]['lng']) ? $valj_efb[$randomId]['lng'] : '0';
					$zoom = isset($valj_efb[$randomId]['zoom']) ? $valj_efb[$randomId]['zoom'] : '8';
					$formId = isset($valj_efb[0]['formId']) ? $valj_efb[0]['formId'] : '';
					
					// تولید HTML المان نقشه با استفاده از sprintf و اضافه کردن formId
					$ui .= sprintf(
						"<div class='efb col-md-12' id='%s-f' data-formId='%s'>
							<label for='%s_' class='efb form-label text-labelEfb'>
								<span>%s</span>
								<span class='text-danger' role='none'>%s</span>
							</label>
							<div class='efb maps-efb maps-os' id='%s-map' style='height: %s;' data-formId='%s' data-lat='%s' data-lng='%s' data-zoom='%s' data-id='%s-el' %s></div>
							<input type='hidden' name='%s-lat' id='%s_lat' value='%s' class='efb emsFormBuilder_v' data-type='maps' data-vid='%s' %s>
							<input type='hidden' name='%s-lng' id='%s_lng' value='%s' class='efb emsFormBuilder_v' data-type='maps' data-vid='%s' %s>
							<small id='%s-des' class='form-text text-muted'>%s</small>
						</div>",
						$randomId, $formId, // شناسه بخش و formId به عنوان data-set
						$randomId, $label, // لیبل نقشه
						($required ? '*' : ''), // نشانه نیاز به پر شدن
						$randomId, $el_height, $lat, $lng, $zoom, $randomId, // تنظیمات نقشه
						$ariaDescribedBy, // توصیف برای دسترسی‌پذیری
						$randomId, $randomId, $lat, $randomId, $required, // عرض جغرافیایی
						$randomId, $randomId, $lng, $randomId, $required, // طول جغرافیایی
						$randomId, $message // پیغام
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
						$randomId, // شناسه نقشه برای اطمینان از یکتایی
						$randomId, $lat, $lng, $zoom, // تنظیمات اولیه نقشه
						$lat, $lng, // موقعیت اولیه مارکر
						$randomId, $randomId, // به‌روزرسانی عرض و طول در inputهای مخفی
						$randomId // شناسه تابع جاوااسکریپت برای بارگذاری نقشه
					);
					if ($pro!==true || $pro!==1) {
						$ui = $this->public_pro_message_efb($texts['locationPicker']);
					} 
					break;
					$dataTag = "maps";
				break;
				case 'switch':
					//switch_el_pro_efb($previewSate, $pos, $rndm, $vj, $desc, $formId, $label, $ttip, $aire_describedby, $texts)
					$ui = $this->pro_efb == true ? $this->switch_el_pro_efb(true, $pos, $rndm, $vj, $desc, $form_id, $label, $ttip, $aire_describedby, $texts)  : $this->public_pro_message();
					$dataTag = $elementId;
				break;
				case 'rating':

					$ui = $this->pro_efb == true ? $this->rating_el_pro_efb(true, $pos, $rndm, $vj, $desc, $form_id, $label, $ttip, $aire_describedby, $texts) : $this->public_pro_message();
					$dataTag = $elementId;
				break;
			
			
			}
		}


		//error_log('ui=>'.$ui);
		//error_log('dataTag'. $dataTag);
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
			
			if (!in_array($elementId, ['option', 'html', 'stripe', 'heading', 'link'])) {
				$newElement .= '</div></div>';
			} else {
				$newElement .= '</div>';
			}
			
			$newElement .= sprintf('<!--endTag %s-->', $elementId);
			error_log('newElement: reult'.$newElement);
			error_log('style: reult'.$style);
			return [$newElement ,$style];
		}
	}
    }