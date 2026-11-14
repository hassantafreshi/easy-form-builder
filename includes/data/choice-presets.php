<?php
/**
 * F05-A: ready-made choice lists of the builder's "Add in bulk" dialog (spec §4.1).
 *
 * Each list is written in its own language and is not translated; only its name is a phrase
 * (name_key, in efbFunction::text_efb()). Every item is [label, stored value]. Stored values
 * are unique inside a list and never hold " < or > (tests/test-choice-presets.php).
 *
 * Sources (checked 2026-11-14):
 *  - Iran: ISO 3166-2:IR (31 provinces, codes IR-00 … IR-30, the numeric codes of the 2020 update);
 *    names as used by the Statistical Centre of Iran.
 *  - Afghanistan: ISO 3166-2:AF (34 provinces).
 *  - Arab League members: the 22 member states (league's own list); codes ISO 3166-1 alpha-2.
 *    Palestine is listed as a member under its ISO code PS; Syria is listed (membership restored 2023).
 *  - GCC: the 6 member states. Saudi Arabia: ISO 3166-2:SA (13 regions). UAE: ISO 3166-2:AE (7).
 *    Egypt: ISO 3166-2:EG (27 governorates).
 *  - United States: USPS codes (50 states and DC). Canada: ISO 3166-2:CA / Canada Post (13).
 *    United Kingdom: ISO 3166-2:GB nations (4). Australia: ISO 3166-2:AU (8).
 *  - Months, weekdays and scales: values are numbers (1 = first item).
 * A change of a country's divisions (a new province) is one line here.
 */
if (!defined('ABSPATH')) {
	exit;
}

return array(
	/* ------------------------------------------------------------------ Persian */
	'ir_provinces' => array('lang' => 'fa', 'name_key' => 'plIrProv', 'name' => 'Provinces of Iran', 'items' => array(
		array('آذربایجان شرقی', 'IR-03'), array('آذربایجان غربی', 'IR-04'), array('اردبیل', 'IR-24'), array('اصفهان', 'IR-10'),
		array('البرز', 'IR-30'), array('ایلام', 'IR-16'), array('بوشهر', 'IR-18'), array('تهران', 'IR-23'),
		array('چهارمحال و بختیاری', 'IR-14'), array('خراسان جنوبی', 'IR-29'), array('خراسان رضوی', 'IR-09'), array('خراسان شمالی', 'IR-28'),
		array('خوزستان', 'IR-06'), array('زنجان', 'IR-19'), array('سمنان', 'IR-20'), array('سیستان و بلوچستان', 'IR-11'),
		array('فارس', 'IR-07'), array('قزوین', 'IR-26'), array('قم', 'IR-25'), array('کردستان', 'IR-12'),
		array('کرمان', 'IR-08'), array('کرمانشاه', 'IR-05'), array('کهگیلویه و بویراحمد', 'IR-17'), array('گلستان', 'IR-27'),
		array('گیلان', 'IR-01'), array('لرستان', 'IR-15'), array('مازندران', 'IR-02'), array('مرکزی', 'IR-00'),
		array('هرمزگان', 'IR-22'), array('همدان', 'IR-13'), array('یزد', 'IR-21'),
	)),
	'fa_months' => array('lang' => 'fa', 'name_key' => 'plJalaliMonths', 'name' => 'Solar Hijri months', 'items' => array(
		array('فروردین', '1'), array('اردیبهشت', '2'), array('خرداد', '3'), array('تیر', '4'), array('مرداد', '5'), array('شهریور', '6'),
		array('مهر', '7'), array('آبان', '8'), array('آذر', '9'), array('دی', '10'), array('بهمن', '11'), array('اسفند', '12'),
	)),
	'fa_weekdays' => array('lang' => 'fa', 'name_key' => 'plFaWeekdays', 'name' => 'Days of the week (from Saturday)', 'items' => array(
		array('شنبه', '1'), array('یکشنبه', '2'), array('دوشنبه', '3'), array('سه‌شنبه', '4'), array('چهارشنبه', '5'), array('پنجشنبه', '6'), array('جمعه', '7'),
	)),
	'fa_education' => array('lang' => 'fa', 'name_key' => 'plFaEducation', 'name' => 'Education level', 'items' => array(
		array('زیر دیپلم', '1'), array('دیپلم', '2'), array('کاردانی', '3'), array('کارشناسی', '4'), array('کارشناسی ارشد', '5'), array('دکتری حرفه‌ای', '6'), array('دکتری تخصصی', '7'),
	)),
	'fa_marital' => array('lang' => 'fa', 'name_key' => 'plFaMarital', 'name' => 'Marital status', 'items' => array(
		array('مجرد', 'single'), array('متأهل', 'married'), array('مطلقه', 'divorced'), array('همسر فوت‌شده', 'widowed'),
	)),
	'fa_military' => array('lang' => 'fa', 'name_key' => 'plFaMilitary', 'name' => 'Military service status', 'items' => array(
		array('پایان خدمت', 'done'), array('معافیت دائم', 'exempt'), array('معافیت تحصیلی', 'study'), array('در حال خدمت', 'serving'), array('مشمول', 'due'),
	)),
	'fa_satisfaction' => array('lang' => 'fa', 'name_key' => 'plFaSatisf', 'name' => 'Satisfaction scale', 'items' => array(
		array('خیلی ناراضی', '1'), array('ناراضی', '2'), array('بی‌نظر', '3'), array('راضی', '4'), array('خیلی راضی', '5'),
	)),
	'fa_likert' => array('lang' => 'fa', 'name_key' => 'plFaLikert', 'name' => 'Agreement scale', 'items' => array(
		array('کاملاً مخالفم', '1'), array('مخالفم', '2'), array('نظری ندارم', '3'), array('موافقم', '4'), array('کاملاً موافقم', '5'),
	)),
	'fa_age' => array('lang' => 'fa', 'name_key' => 'plFaAge', 'name' => 'Age range', 'items' => array(
		array('زیر ۱۸ سال', 'u18'), array('۱۸ تا ۲۴ سال', '18-24'), array('۲۵ تا ۳۴ سال', '25-34'), array('۳۵ تا ۴۴ سال', '35-44'), array('۴۵ تا ۵۴ سال', '45-54'), array('۵۵ سال و بیشتر', '55+'),
	)),
	'af_provinces' => array('lang' => 'fa', 'name_key' => 'plAfProv', 'name' => 'Provinces of Afghanistan', 'items' => array(
		array('ارزگان', 'AF-URU'), array('بادغیس', 'AF-BDG'), array('بامیان', 'AF-BAM'), array('بدخشان', 'AF-BDS'), array('بغلان', 'AF-BGL'),
		array('بلخ', 'AF-BAL'), array('پروان', 'AF-PAR'), array('پکتیا', 'AF-PIA'), array('پکتیکا', 'AF-PKA'), array('پنجشیر', 'AF-PAN'),
		array('تخار', 'AF-TAK'), array('جوزجان', 'AF-JOW'), array('خوست', 'AF-KHO'), array('دایکندی', 'AF-DAY'), array('زابل', 'AF-ZAB'),
		array('سرپل', 'AF-SAR'), array('سمنگان', 'AF-SAM'), array('غزنی', 'AF-GHA'), array('غور', 'AF-GHO'), array('فاریاب', 'AF-FYB'),
		array('فراه', 'AF-FRA'), array('قندهار', 'AF-KAN'), array('کابل', 'AF-KAB'), array('کاپیسا', 'AF-KAP'), array('کندز', 'AF-KDZ'),
		array('کنر', 'AF-KNR'), array('لغمان', 'AF-LAG'), array('لوگر', 'AF-LOG'), array('میدان وردک', 'AF-WAR'), array('ننگرهار', 'AF-NAN'),
		array('نورستان', 'AF-NUR'), array('نیمروز', 'AF-NIM'), array('هرات', 'AF-HER'), array('هلمند', 'AF-HEL'),
	)),

	/* ------------------------------------------------------------------ Arabic */
	'arab_states' => array('lang' => 'ar', 'name_key' => 'plArabStates', 'name' => 'Arab League countries', 'items' => array(
		array('الأردن', 'JO'), array('الإمارات العربية المتحدة', 'AE'), array('البحرين', 'BH'), array('تونس', 'TN'), array('الجزائر', 'DZ'),
		array('جيبوتي', 'DJ'), array('السعودية', 'SA'), array('السودان', 'SD'), array('سوريا', 'SY'), array('الصومال', 'SO'),
		array('العراق', 'IQ'), array('عُمان', 'OM'), array('فلسطين', 'PS'), array('قطر', 'QA'), array('جزر القمر', 'KM'),
		array('الكويت', 'KW'), array('لبنان', 'LB'), array('ليبيا', 'LY'), array('مصر', 'EG'), array('المغرب', 'MA'),
		array('موريتانيا', 'MR'), array('اليمن', 'YE'),
	)),
	'sa_regions' => array('lang' => 'ar', 'name_key' => 'plSaRegions', 'name' => 'Regions of Saudi Arabia', 'items' => array(
		array('منطقة الرياض', 'SA-01'), array('منطقة مكة المكرمة', 'SA-02'), array('منطقة المدينة المنورة', 'SA-03'), array('المنطقة الشرقية', 'SA-04'),
		array('منطقة القصيم', 'SA-05'), array('منطقة حائل', 'SA-06'), array('منطقة تبوك', 'SA-07'), array('منطقة الحدود الشمالية', 'SA-08'),
		array('منطقة جازان', 'SA-09'), array('منطقة نجران', 'SA-10'), array('منطقة الباحة', 'SA-11'), array('منطقة الجوف', 'SA-12'), array('منطقة عسير', 'SA-14'),
	)),
	'ae_emirates' => array('lang' => 'ar', 'name_key' => 'plAeEmirates', 'name' => 'Emirates of the UAE', 'items' => array(
		array('أبوظبي', 'AE-AZ'), array('دبي', 'AE-DU'), array('الشارقة', 'AE-SH'), array('عجمان', 'AE-AJ'), array('أم القيوين', 'AE-UQ'), array('رأس الخيمة', 'AE-RK'), array('الفجيرة', 'AE-FU'),
	)),
	'eg_governorates' => array('lang' => 'ar', 'name_key' => 'plEgGov', 'name' => 'Governorates of Egypt', 'items' => array(
		array('القاهرة', 'EG-C'), array('الجيزة', 'EG-GZ'), array('الإسكندرية', 'EG-ALX'), array('الدقهلية', 'EG-DK'), array('البحر الأحمر', 'EG-BA'),
		array('البحيرة', 'EG-BH'), array('الفيوم', 'EG-FYM'), array('الغربية', 'EG-GH'), array('الإسماعيلية', 'EG-IS'), array('المنوفية', 'EG-MNF'),
		array('المنيا', 'EG-MN'), array('القليوبية', 'EG-KB'), array('الوادي الجديد', 'EG-WAD'), array('السويس', 'EG-SUZ'), array('أسوان', 'EG-ASN'),
		array('أسيوط', 'EG-AST'), array('بني سويف', 'EG-BNS'), array('بورسعيد', 'EG-PTS'), array('دمياط', 'EG-DT'), array('الشرقية', 'EG-SHR'),
		array('جنوب سيناء', 'EG-JS'), array('كفر الشيخ', 'EG-KFS'), array('مطروح', 'EG-MT'), array('الأقصر', 'EG-LX'), array('قنا', 'EG-KN'),
		array('شمال سيناء', 'EG-SIN'), array('سوهاج', 'EG-SHG'),
	)),
	'gcc' => array('lang' => 'ar', 'name_key' => 'plGcc', 'name' => 'GCC countries', 'items' => array(
		array('الإمارات العربية المتحدة', 'AE'), array('البحرين', 'BH'), array('السعودية', 'SA'), array('عُمان', 'OM'), array('قطر', 'QA'), array('الكويت', 'KW'),
	)),
	'hijri_months' => array('lang' => 'ar', 'name_key' => 'plHijriMonths', 'name' => 'Hijri months', 'items' => array(
		array('محرم', '1'), array('صفر', '2'), array('ربيع الأول', '3'), array('ربيع الآخر', '4'), array('جمادى الأولى', '5'), array('جمادى الآخرة', '6'),
		array('رجب', '7'), array('شعبان', '8'), array('رمضان', '9'), array('شوال', '10'), array('ذو القعدة', '11'), array('ذو الحجة', '12'),
	)),
	'ar_months' => array('lang' => 'ar', 'name_key' => 'plArMonths', 'name' => 'Gregorian months (both Arabic names)', 'items' => array(
		array('يناير / كانون الثاني', '1'), array('فبراير / شباط', '2'), array('مارس / آذار', '3'), array('أبريل / نيسان', '4'), array('مايو / أيار', '5'), array('يونيو / حزيران', '6'),
		array('يوليو / تموز', '7'), array('أغسطس / آب', '8'), array('سبتمبر / أيلول', '9'), array('أكتوبر / تشرين الأول', '10'), array('نوفمبر / تشرين الثاني', '11'), array('ديسمبر / كانون الأول', '12'),
	)),
	'ar_weekdays' => array('lang' => 'ar', 'name_key' => 'plArWeekdays', 'name' => 'Days of the week (from Sunday)', 'items' => array(
		array('الأحد', '1'), array('الاثنين', '2'), array('الثلاثاء', '3'), array('الأربعاء', '4'), array('الخميس', '5'), array('الجمعة', '6'), array('السبت', '7'),
	)),
	'ar_education' => array('lang' => 'ar', 'name_key' => 'plArEducation', 'name' => 'Education level', 'items' => array(
		array('أقل من الثانوية', '1'), array('الثانوية العامة', '2'), array('دبلوم', '3'), array('بكالوريوس', '4'), array('ماجستير', '5'), array('دكتوراه', '6'),
	)),
	'ar_satisfaction' => array('lang' => 'ar', 'name_key' => 'plArSatisf', 'name' => 'Satisfaction scale', 'items' => array(
		array('غير راضٍ إطلاقًا', '1'), array('غير راضٍ', '2'), array('محايد', '3'), array('راضٍ', '4'), array('راضٍ جدًا', '5'),
	)),

	/* ------------------------------------------------------------------ English */
	'us_states' => array('lang' => 'en', 'name_key' => 'plUsStates', 'name' => 'U.S. states', 'items' => array(
		array('Alabama', 'AL'), array('Alaska', 'AK'), array('Arizona', 'AZ'), array('Arkansas', 'AR'), array('California', 'CA'), array('Colorado', 'CO'),
		array('Connecticut', 'CT'), array('Delaware', 'DE'), array('District of Columbia', 'DC'), array('Florida', 'FL'), array('Georgia', 'GA'), array('Hawaii', 'HI'),
		array('Idaho', 'ID'), array('Illinois', 'IL'), array('Indiana', 'IN'), array('Iowa', 'IA'), array('Kansas', 'KS'), array('Kentucky', 'KY'),
		array('Louisiana', 'LA'), array('Maine', 'ME'), array('Maryland', 'MD'), array('Massachusetts', 'MA'), array('Michigan', 'MI'), array('Minnesota', 'MN'),
		array('Mississippi', 'MS'), array('Missouri', 'MO'), array('Montana', 'MT'), array('Nebraska', 'NE'), array('Nevada', 'NV'), array('New Hampshire', 'NH'),
		array('New Jersey', 'NJ'), array('New Mexico', 'NM'), array('New York', 'NY'), array('North Carolina', 'NC'), array('North Dakota', 'ND'), array('Ohio', 'OH'),
		array('Oklahoma', 'OK'), array('Oregon', 'OR'), array('Pennsylvania', 'PA'), array('Rhode Island', 'RI'), array('South Carolina', 'SC'), array('South Dakota', 'SD'),
		array('Tennessee', 'TN'), array('Texas', 'TX'), array('Utah', 'UT'), array('Vermont', 'VT'), array('Virginia', 'VA'), array('Washington', 'WA'),
		array('West Virginia', 'WV'), array('Wisconsin', 'WI'), array('Wyoming', 'WY'),
	)),
	'ca_provinces' => array('lang' => 'en', 'name_key' => 'plCaProv', 'name' => 'Canadian provinces and territories', 'items' => array(
		array('Alberta', 'AB'), array('British Columbia', 'BC'), array('Manitoba', 'MB'), array('New Brunswick', 'NB'), array('Newfoundland and Labrador', 'NL'),
		array('Northwest Territories', 'NT'), array('Nova Scotia', 'NS'), array('Nunavut', 'NU'), array('Ontario', 'ON'), array('Prince Edward Island', 'PE'),
		array('Quebec', 'QC'), array('Saskatchewan', 'SK'), array('Yukon', 'YT'),
	)),
	'uk_nations' => array('lang' => 'en', 'name_key' => 'plUkNations', 'name' => 'UK nations', 'items' => array(
		array('England', 'GB-ENG'), array('Northern Ireland', 'GB-NIR'), array('Scotland', 'GB-SCT'), array('Wales', 'GB-WLS'),
	)),
	'au_states' => array('lang' => 'en', 'name_key' => 'plAuStates', 'name' => 'Australian states and territories', 'items' => array(
		array('Australian Capital Territory', 'AU-ACT'), array('New South Wales', 'AU-NSW'), array('Northern Territory', 'AU-NT'), array('Queensland', 'AU-QLD'),
		array('South Australia', 'AU-SA'), array('Tasmania', 'AU-TAS'), array('Victoria', 'AU-VIC'), array('Western Australia', 'AU-WA'),
	)),
	'en_months' => array('lang' => 'en', 'name_key' => 'plMonths', 'name' => 'Months', 'items' => array(
		array('January', '1'), array('February', '2'), array('March', '3'), array('April', '4'), array('May', '5'), array('June', '6'),
		array('July', '7'), array('August', '8'), array('September', '9'), array('October', '10'), array('November', '11'), array('December', '12'),
	)),
	'en_weekdays' => array('lang' => 'en', 'name_key' => 'plWeekdays', 'name' => 'Days of the week', 'items' => array(
		array('Monday', '1'), array('Tuesday', '2'), array('Wednesday', '3'), array('Thursday', '4'), array('Friday', '5'), array('Saturday', '6'), array('Sunday', '7'),
	)),
	'en_age' => array('lang' => 'en', 'name_key' => 'plAge', 'name' => 'Age range', 'items' => array(
		array('Under 18', 'u18'), array('18-24', '18-24'), array('25-34', '25-34'), array('35-44', '35-44'), array('45-54', '45-54'), array('55 or older', '55+'),
	)),
	'en_satisfaction' => array('lang' => 'en', 'name_key' => 'plSatisf', 'name' => 'Satisfaction scale', 'items' => array(
		array('Very dissatisfied', '1'), array('Dissatisfied', '2'), array('Neutral', '3'), array('Satisfied', '4'), array('Very satisfied', '5'),
	)),
	'en_agreement' => array('lang' => 'en', 'name_key' => 'plLikert', 'name' => 'Agreement scale', 'items' => array(
		array('Strongly disagree', '1'), array('Disagree', '2'), array('Neither agree nor disagree', '3'), array('Agree', '4'), array('Strongly agree', '5'),
	)),
	'en_importance' => array('lang' => 'en', 'name_key' => 'plImportance', 'name' => 'Importance scale', 'items' => array(
		array('Not at all important', '1'), array('Slightly important', '2'), array('Moderately important', '3'), array('Very important', '4'), array('Extremely important', '5'),
	)),
	'en_likelihood' => array('lang' => 'en', 'name_key' => 'plLikelihood', 'name' => 'Likelihood scale', 'items' => array(
		array('Very unlikely', '1'), array('Unlikely', '2'), array('Neutral', '3'), array('Likely', '4'), array('Very likely', '5'),
	)),
	'en_yes_no' => array('lang' => 'en', 'name_key' => 'plYesNo', 'name' => 'Yes / no / not sure', 'items' => array(
		array('Yes', 'yes'), array('No', 'no'), array('Not sure', 'not_sure'),
	)),
	'en_education' => array('lang' => 'en', 'name_key' => 'plEducation', 'name' => 'Education level', 'items' => array(
		array('Less than high school', '1'), array('High school', '2'), array('Some college', '3'), array("Bachelor's degree", '4'), array("Master's degree", '5'), array('Doctorate', '6'),
	)),
);
