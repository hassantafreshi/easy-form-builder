<?php
/**
 * F16-E: the markup a field had before F16, made F16's way, for the byte-for-byte snapshot tests (F04, F08, F10, SPLIT):
 * the required star is hidden from screen readers (aria-hidden instead of role="none") and the empty error box no longer
 * holds " ! " (it was read through aria-describedby). Nothing else may differ.
 */
if (!function_exists('efb_f16_markup')) {
	function efb_f16_markup($html) {
		if (is_array($html)) return array_map('efb_f16_markup', $html);
		if (!is_string($html)) return $html;
		$html = preg_replace('/(_req") role="none">/', '$1 aria-hidden="true">', $html);
		$html = preg_replace("/(_req\\\\?\") role='none'>/", '$1 aria-hidden="true">', $html);
		$html = str_replace('style="display:none"> ! </small>', 'style="display:none"></small>', $html);
		// A textarea has no value attribute (its text is its value).
		$html = preg_replace('/(<textarea [^>]*?data-formid="[^"]*") value="[^"]*"/', '$1', $html);
		// A choice group is named by aria-labelledby (its label's for="" pointed at nothing); a required radiogroup says so.
		if (preg_match_all('/id="([A-Za-z0-9]+)_options"[^>]*role="(radio)?group"/', $html, $m)) {
			foreach ($m[1] as $i => $id) {
				$html = str_replace('<label for="' . $id . '_" ', '<label ', $html);
				if ($m[2][$i] === 'radio') $html = preg_replace('/(class="efb required[^"]*"[^>]*id="' . $id . '_options" role="radiogroup" aria-labelledby="' . $id . '_labG")/', '$1 aria-required="true"', $html);
			}
		}
		return $html;
	}
}
