
const iconMarginGlobal = efb_var.rtl == 1 ? 'ms-2' : 'me-2';

const currency_efb = ["USD (United State dollar)","AED (United Arab Emirates dirham, درهم إماراتي)","AFN (Afghan afghani)","ALL (Albania Lek)","AMD (Armenian dram, Հայկական Դրామ)","ANG (Netherlands Antillean guilder, Antilliaanse gulden)","AOA (Angolan kwanza)","ARS (Argentine peso,Peso argentino)","AUD (Australian dollar)","AWG (Aruban florin, Arubaanse florin)","AZN (Azerbaijani manat, Azərbaycan manatı)","BAM (Bosnia and Herzegovina convertible mark, Конвертибилна марка)","BBD (Barbadian dollar)","BDT (Bangladeshi taka, টাকা)","BGN (Bulgarian lev, Български лев)","BIF (franc burundais)","BMD (Bermudian dollar)","BND (Brunei dollar, ringgit Brunei)","BOB  (Bolivian boliviano, boliviano)","BRL  (Brazilian real, Real brasileiro)","BSD (Bahamian dollar)","BWP (Botswana pula)","BYN (Belarusian ruble, беларускі рубель)","BZD (Belize dollar)","CAD (Canadian dollar, dollar canadien)","CDF (Congolese franc, franc congolais)","CHF (Swiss franc)","CLP  (Chilean peso, Peso chileno)","CNY (Renminbi, 人民币)","COP  (Colombian peso, peso colombiano)","CRC  (Costa Rican colón, colón costarricense)","CVE  (Cape Verdean escudo, escudo cabo-verdiano)","CZK (Czech koruna, koruna česká)","DJF  (Djiboutian franc, الفرنك الجيبوتي)","DKK (Danish krone, dansk krone)","DOP (Dominican peso, peso dominicano)","DZD (Algerian dinar, دينار جزائري)","EGP (Egyptian pound, جنيه مصرى)","ETB (Ethiopian birr)","EUR (Euro)","FJD (Fijian dollar)","FKP  (Falkland Islands pound)","GBP (Pound sterling)","GEL (Georgian lari,  ქართული ლარი)","GIP (Gibraltar pound)","GMD (Gambian dalasi)","GNF  (Guinean franc, franc guinéen)","GTQ  (Guatemalan quetzal,  quetzal guatemalteco)","GYD (Guyanese dollar)","HKD (Hong Kong dollar, 港元)","HNL  (Honduran lempira, lempira hondureño)","HRK (Croatian kuna, hrvatska kuna)","HTG (Haitian gourde, gourde haïtienne)","HUF (Hungarian forint, Magyar forint)","IDR (Indonesian rupiah)","ILS (Israeli new shekel, שקל חדש)","INR (Indian rupee)","ISK (Icelandic krona, króna)","JMD (Jamaican dollar)","JPY (Japanese yen, 日本円)","KES (Kenyan shilling, Kenyan shilling)","KGS (Kyrgyzstani som, Кыргыз сому)","KHR (Cambodian riel, រៀលកម្ពុជា/រៀលខ្មែរ)","KMF (Comorian franc)","KRW (South Korean won, 대한민국 원)","KYD (Cayman Islands dollar)","KZT (Kazakhstani tenge, Қазақстан теңгесі)","LAK (Lao kip,ເງີນກີບລາວ)","LBP (Lebanese pound, Livre libanaise)","LKR (Sri Lankan rupee, ශ්‍රී ලංකා රුපියල්)","LRD (Liberian dollar)","LSL (Lesotho loti)","MAD (Moroccan dirham, ⴰⴷⵔⵀⵎ ⵏ ⵍⵎⵖⵔⵉⴱ)","MDL (Moldovan leu, leu moldovenesc)","MGA (Malagasy ariary,ariary malgache)","MKD (Macedonian denar,денар)","MMK (Myanmar kyat)","MNT (Mongolian tögrög, Монгол төгрөг)","MOP (Macanese pataca)","MRO (Mauritanian ouguiya, أوقية موريتانية)","MUR (Mauritian rupee, Roupie mauricienne)","MVR (Maldivian rufiyaa)","MWK (Malawian kwacha)","MXN (Mexican peso, Peso Mexicano)","MYR (Malaysian ringgit, Ringgit Malaysia)","MZN (Mozambican metical, Metical moçambicano)","NAD (Namibian dollar)","NGN (Nigerian naira)","NIO (Nicaraguan córdoba, córdoba nicaragüense)","NOK (Norwegian krone, norsk krone)","NPR (Nepalese rupee, रुपैयाँ)","NZD (New Zealand dollar)","PAB (Panamanian balboa, Balboa panameño)","PEN (Peruvian sol, sol peruano)","PGK (Papua New Guinean kina)","PHP (Philippine peso, Piso ng Pilipinas)","PKR (Pakistani rupee)","PLN (Polish złoty, Polski złoty)","PYG (Paraguayan guaraní, Guaraní paraguayo)","QAR (Qatari riyal, ريال قطري)","RON (Romanian leu, Leu românesc)","RSD (Serbian dinar, Cрпски динар)","RUB (Russian ruble, Российский рубль)","RWF (Rwandan franc, franc rwandais)","SAR (Saudi riyalSaudi riyal, ريال سعودي)","SBD (Solomon Islands dollar)","SCR (Seychellois rupee, roupie seychelloise)","SEK (Swedish krona, svensk krona )","SGD (Singapore dollar, Dolar Singapura)","SHP (Saint Helena pound)","SLL (Sierra Leonean leone)","SOS (Somali shilling, Shilin Soomaali)","SRD (Surinamese, Surinamese )","STD (São Tomé and Príncipe dobra, dobra são-tomense)","SZL (Swazi lilangeni)","THB (Thai baht, บาทไทย)","TJS (Tajikistani somoni, Сомонӣ)","TOP (Tonga Pa'anga)","TRY (Turkish New Lira)","TTD (Trinidad/Tobago Dollar)","TWD (Taiwan Dollar)","TZS (Tanzania Shilling)","UAH (Ukraine Hryvnia)","UGX (Uganda Shilling)","UYU (Uruguay Peso)","UZS (Uzbekistani soʻm, Oʻzbek soʻmi)","VND (Vietnam Dong)","VUV (Vanuatu Vatu)","WST (Samoa Tala)","XAF (CFA Franc BEAC)","XCD (East Caribbean Dollar)","XOF (CFA Franc BCEAO)","XPF (CFP Franc)","YER (Yemen Rial)","ZAR (South Africa Rand)","ZMW (Zambian kwacha)"];
const currency_paypal_efb = ["USD (United State dollar)","AUD (Australian dollar)","BRL  (Brazilian real, Real brasileiro)","CAD (Canadian dollar, dollar canadien)","CHF (Swiss franc)","CNY (Renminbi, 人民币)","CZK (Czech koruna, koruna česká)","DKK (Danish krone, dansk krone)","EUR (Euro)","GBP (Pound sterling)","HKD (Hong Kong dollar, 港元)","HUF (Hungarian forint, Magyar forint)","ILS (Israeli new shekel, שקל חדש)","JPY (Japanese yen, 日本円)","MXN (Mexican peso, Peso Mexicano)","MYR (Malaysian ringgit, Ringgit Malaysia)","NOK (Norwegian krone, norsk krone)","NZD (New Zealand dollar)","PHP (Philippine peso, Piso ng Pilipinas)","PLN (Polish złoty, Polski złoty)","SEK (Swedish krona, svensk krona )","SGD (Singapore dollar, Dolar Singapura)","THB (Thai baht, บาทไทย)","TWD (Taiwan Dollar)"];
const lan_con_efb = {af:"ZA",ak:"AK",sq:"AL",hy:"AM",rup_MK:"en",as:"as",az_TR:"AZ",ba:"RU",eu:"ES",bel:"BY",bn_BD:"BD",bs_BA:"BA",my_MM:"MM",ca:"ES",bal:"ES",co:"FR",hr:"HR",dv:"MV",nl_NL:"NL",eo:"EO",fo:"FO",fr_BE:"FR",fy:"NL",fuc:"CM",gl_ES:"ES",ka_GE:"GE",gn:"BO",gu_IN:"IN",haw_US:"US",haz:"AF",is_IS:"IS",ido:"FI",jv_ID:"ID",kn:"IN",km:"KH",kin:"RW",ky_KY:"KG",ckb:"IQ",lo:"LA",li:"BE",lin:"CG",lb_LU:"LU",mk_MK:"MK",mg_MG:"MG",ml_IN:"IN",mr:"IN",xmf:"GA",mn:"MN",me_ME:"ME",ne_NP:"NP",nn_NO:"NO",ory:"IN",os:"IR",ps:"PK",fa_AF:"AF",pa_IN:"IN",rhg:"BD",ro_RO:"RO",ru_UA:"UA",rue:"SK",sah:"RU",sa_IN:"IN",srd:"IT",gd:"GB",sr_RS:"CS",sd_PK:"IN",si_LK:"LK ",sl_SI:"SI ",so_SO:"SO",azb:"IR",es_AR:"AR",es_CL:"CL",es_CO:"CO",es_MX:"MX",es_PE:"PE",es_PR:"PR ",es_ES:"ES",es_VE:"VE",su_ID:"SD",sw:"KE",sv_SE:"SE",gsw:"CH",tl:"PH",tg:"TJ ",tzm:"MA",ta_IN:"IN",ta_LK:"LK",tt_RU:"RU",te:"IN",th:"TH",bo:"CH",tir:"ET",tr_TR:"TR",tuk:"TM",ug_CN:"CN",uk:"UA",ur:"PK",uz_UZ:"UZ",vi:"VN",wa:"BE",cy:"GB ",yor:"NG",en_AU:"AU",en_CA:"CA",en_GB:"GB",en_NZ:"NZ",en_US:"US",en_ZA:"ZA",cs_CZ:"CZ",da_DK:"DK",de_AT:"AT",de_CH_informal:"CH",de_DE:"DE",ar:"SA",fa_IR:"IR",ja:"JA",zh_CN:"CN",zh_HK:"HK",zh_SG:"SG",zh_TW:"TW",pl_PL:"PL",pt_AO:"AO",pt_BR:"BR",pt_PT:"PT",ro_RO:"RO",ru_RU:"RU",sk_SK:"SK",ms_MY:"MY",nb_NO:"NO",nl_BE:"BE",ko_KR:"KR",he_IL:"IL",hi_IN:"IN",hu_HU:"HU",id_ID:"ID",it_IT:"IT",fi:"fi",fr_CA:"CA",fr_FR:"FR"};
const fields_efb = [
  { name: efb_var.text.text, icon: 'bi-file-earmark-text', id: 'text', pro: false,  tag:'basic all'},
  { name: efb_var.text.name, icon: 'bi-person-circle', id: 'name', pro: false,   tag:'basic all'},
  { name: efb_var.text.password, icon: 'bi-lock', id: 'password', pro: false, tag:'basic all' },
  { name: efb_var.text.email, icon: 'bi-envelope', id: 'email', pro: false,  tag:'basic all' },
  { name: efb_var.text.number, icon: 'bi-pause', id: 'number', pro: false,  tag:'basic all' },

  { name: efb_var.text.textarea, icon: 'bi-card-text', id: 'textarea', pro: false, tag:'basic all' },
  { name: efb_var.text.step, icon: 'bi-file', id: 'steps', pro: false, tag:'advance all' },
  { name: efb_var.text.checkbox, icon: 'bi-check-square', id: 'checkbox', pro: false, tag:'basic all'},
  { name: efb_var.text.radiobutton, icon: 'bi-record-circle', id: 'radio', pro: false, tag:'basic all' },
  { name: efb_var.text.select, icon: 'bi-check2', id: 'select', pro: false , tag:'basic all'},
  { name: efb_var.text.multiselect, icon: 'bi-check-all', id: 'multiselect', pro: false, tag:'advance all' },
  { name: efb_var.text.tel, icon: 'bi-telephone', id: 'tel', pro: false, tag:'basic all' },
  { name: efb_var.text.mobile, icon: 'bi-phone', id: 'mobile', pro: true, tag:'advance all' },

  { name: efb_var.text.range, icon: 'bi-arrow-left-right', id: 'range', pro: false, tag:'basic all' },
  { name: efb_var.text.ddate, icon: 'bi-calendar-date', id: 'date', pro: false, tag:'basic all' },
  { name: efb_var.text.file, icon: 'bi-file-earmark-plus', id: 'file', pro: false, tag:'basic all' },
  { name: efb_var.text.dadfile, icon: 'bi-plus-square-dotted', id: 'dadfile', pro: true, tag:'advance all' },
  { name: efb_var.text.address, icon: 'bi-geo-alt', id: 'address', pro: true, tag:'advance all' },

  { name: efb_var.text.payCheckbox, icon: 'bi-basket2', id: 'payCheckbox', pro: true, tag:'payment all' },
  { name: efb_var.text.payRadio, icon: 'bi-basket3', id: 'payRadio', pro: true, tag:'payment all' },
  { name: efb_var.text.prcfld, icon: 'bi-bag-plus', id: 'prcfld', pro: true, tag:'payment all' },
  { name: efb_var.text.ttlprc, icon: 'bi-cash', id: 'ttlprc', pro: true, tag:'payment all' },
  { name: efb_var.text.locationPicker, icon: 'bi-pin-map', id: 'maps', pro: true, tag:'advance all' },
  { name: efb_var.text.stripe, icon: 'bi-stripe', id: 'stripe', pro: true, tag:'payment all' },
  { name: efb_var.text.paypal, icon: 'bi-paypal', id: 'paypal', pro: true, tag:'payment all' },
  { name: efb_var.text.url, icon: 'bi-link-45deg', id: 'url', pro: false, tag:'basic all' },
  { name: efb_var.text.conturyList, icon: 'bi-flag', id: 'conturyList', pro: true, tag:'advance all' },
  { name: efb_var.text.stateProvince, icon: 'bi-triangle-fill', id: 'stateProvince', pro: true, tag:'advance all' },
  { name: efb_var.text.cityList, icon: 'bi-circle', id: 'cityList', pro: true, tag:'advance all' },
  { name: efb_var.text.esign, icon: 'bi-pen', id: 'esign', pro: true, tag:'advance all' },
  { name: efb_var.text.switch, icon: 'bi-toggle2-on', id: 'switch', pro: true, tag:'advance all' },
  { name: efb_var.text.chlCheckBox, icon: 'bi-card-checklist', id: 'chlCheckBox', pro: true, tag:'advance all' },
  { name: efb_var.text.heading, icon: 'bi-fonts', id: 'heading', pro: true, tag:'advance all' },

  { name: efb_var.text.color, icon: 'bi-palette', id: 'color', pro: true, tag:'basic all' },
  { name: efb_var.text.rating, icon: 'bi-star', id: 'rating', pro: true, tag:'advance all' },
  { name: efb_var.text.yesNo, icon: 'bi-hand-index', id: 'yesNo', pro: true, tag:'advance all' },
  { name: efb_var.text.link, icon: 'bi-link-45deg', id: 'link', pro: true, tag:'advance all' },
  { name: efb_var.text.htmlCode, icon: 'bi-code-square', id: 'html', pro: true, tag:'advance all' },
  { name: efb_var.text.pr5, icon: 'bi-heart', id: 'pointr5', pro: true, tag: 'advance all' },
  { name: efb_var.text.nps_, icon: 'bi-square', id: 'pointr10', pro: true, tag: 'advance all' },
  { name: efb_var.text.imgRadio, icon: 'bi-images', id: 'imgRadio', pro: true, tag:'advance all' },
  { name: efb_var.text.pdate, icon: 'bi-calendar-date', id: 'pdate', pro: true, tag:'advance all' },
  { name: efb_var.text.ardate, icon: 'bi-calendar-date', id: 'ardate', pro: true, tag:'advance all' },

  { name: efb_var.text.terms, icon: 'bi-shield-check', id: 'trmCheckbox', pro: true, tag:'advance all' },

  { name: efb_var.text.nps_tm, icon: ' bi-table', id: 'table_matrix', pro: true, tag: 'advance all' },

]

const paymentMethodEls =(idset)=>{

  return`<label for="paymentMethodEl" class="efb mt-3 efb"><i class="efb bi-wallet2 fs-7 ${iconMarginGlobal}"></i>${efb_var.text.methodPayment}</label>
  <select  data-id="${idset}" class="efb elEdit form-select efb border-d rounded-4"  id="paymentMethodEl"  data-tag="${valj_efb[0].type}">
  <option value="charge" ${valj_efb[0].paymentmethod=='charge' ? 'selected' :''}>${efb_var.text.onetime}</option>
  <option value="day" ${valj_efb[0].paymentmethod=='day' ? 'selected' :''}>${efb_var.text.dayly}</option>
  <option value="week" ${valj_efb[0].paymentmethod=='week' ? 'selected' :''}>${efb_var.text.weekly}</option>
  <option value="month" ${valj_efb[0].paymentmethod=='month' ? 'selected' :''}>${efb_var.text.monthly}</option>
  <option value="year" ${valj_efb[0].paymentmethod=='year' ? 'selected' :''}>${efb_var.text.yearly}</option>

  </select>`;
}
const formTypeEls =()=>{

  return`<span class="efb"><label for="formTypeEl" class="efb mt-3 mx-2 efb">${efb_var.text.frmtype}</label>
  <select data-id="formSet" class="efb elEdit form-select efb border-d rounded-4"  id="formTypeEl"  data-tag="${valj_efb[0].type}">
  <option value="form" ${valj_efb[0].type=='form' ? 'selected' :''}>${efb_var.text.form}</option>
  <option value="payment" ${valj_efb[0].type=='payment' ? 'selected' :''}>${efb_var.text.payment}</option>
  <option value="survey" ${valj_efb[0].type=='survey' ? 'selected' :''}>${efb_var.text.survey}</option>
  <option value="login" ${valj_efb[0].type=='login' ? 'selected' :''}>${efb_var.text.login}</option>
  <option value="login" ${valj_efb[0].type=='login' ? 'selected' :''} disabled>${efb_var.text.login}</option>
  <option value="register" ${valj_efb[0].type=='register' ? 'selected' :''} disabled>${efb_var.text.register}</option>
  </select></span>`;
}

const surveyChartTypeEls = () => {
  if (!valj_efb[0].hasOwnProperty('survey_chart_type')) {
    Object.assign(valj_efb[0], { survey_chart_type: 'none' });
  }

  const chartTypes = [
    { value: 'none', name: efb_var.text.surveyNoChart || 'Do not show results', icon: 'bi-eye-slash' },
    { value: 'bar', name: efb_var.text.surveyBarChart || 'Show results with bar chart', icon: 'bi-bar-chart-fill' },
    { value: 'pie', name: efb_var.text.surveyPieChart || 'Show results with pie chart', icon: 'bi-pie-chart-fill' }
  ];

  let options = '';
  for (let type of chartTypes) {
    options += `<option value="${type.value}" ${valj_efb[0].survey_chart_type === type.value ? 'selected' : ''}><i class="bi ${type.icon}"></i> ${type.name}</option>`;
  }

  return `
  <div class="efb mt-3 mb-2 survey-chart-options ${valj_efb[0].type !== 'survey' ? 'd-none' : ''}" id="surveyChartOptionsWrapper">
    <label for="surveyChartTypeEl" class="efb mb-2">
      <i class="efb bi-bar-chart-line fs-7 ${iconMarginGlobal}"></i>
      ${efb_var.text.surveyResultsDisplay || 'Survey Results Display'}
    </label>
    <select data-id="formSet" class="efb elEdit form-select efb border-d rounded-4" id="surveyChartTypeEl">
      ${options}
    </select>
    <small class="efb text-muted mt-1 d-block fs-7">
      <i class="bi bi-info-circle"></i> ${efb_var.text.surveyChartHelp || 'After submission, visitors can see aggregate survey results'}
    </small>
  </div>`;
}

const loadingTypeEls = () => {
  if (!valj_efb[0].hasOwnProperty('loading_type')) {
    Object.assign(valj_efb[0], { loading_type: 'dots' });
  }
  if (!valj_efb[0].hasOwnProperty('loading_color')) {
    Object.assign(valj_efb[0], { loading_color: '#abb8c3' });
  }

  const loadingTypes = [
    { value: 'bars', name: efb_var.text.bars || 'Bars', icon: '▮▮▮' },
    { value: 'dots', name: efb_var.text.dots || 'Dots', icon: '●●●' },
    { value: 'spinner', name: efb_var.text.spinner || 'Spinner', icon: '◐' },
    { value: 'pulse', name: efb_var.text.pulse || 'Pulse', icon: '◉' },
    { value: 'ripple', name: efb_var.text.ripple || 'Ripple', icon: '◎' },
    { value: 'bounce', name: efb_var.text.bounce || 'Bounce', icon: '⚫⚫⚫' },
    { value: 'orbit', name: efb_var.text.orbit || 'Orbit', icon: '◌' },
    { value: 'wave', name: efb_var.text.wave || 'Wave', icon: '〰' },
    { value: 'hourglass', name: efb_var.text.hourglass || 'Hourglass', icon: '⧗' }
  ];

  let options = '';
  for (let type of loadingTypes) {
    options += `<option value="${type.value}" ${valj_efb[0].loading_type === type.value ? 'selected' : ''}>${type.icon} ${type.name}</option>`;
  }

  return `
  <div class="efb mt-3 mb-2">
    <label for="loadingTypeEl" class="efb mb-2"><i class="efb bi-arrow-repeat fs-7 ${iconMarginGlobal}"></i>${efb_var.text.loadingType || 'Loading Animation'}</label>
    <select data-id="formSet" class="efb elEdit form-select efb border-d rounded-4" id="loadingTypeEl">
      ${options}
    </select>
  </div>
  <div class="efb mt-2 mb-2">
    <label for="loadingColorEl" class="efb mb-2"><i class="efb bi-palette fs-7 ${iconMarginGlobal}"></i>${efb_var.text.loadingColor || 'Loading Color'}</label>
    <div class="efb d-flex align-items-center gap-2">
      <input type="color" data-id="formSet" class="efb elEdit form-control form-control-color border-d rounded-4" id="loadingColorEl" value="${valj_efb[0].loading_color}" style="width: 60px; height: 38px;">
      <input type="text" class="efb form-control border-d rounded-4 h-d-efb" id="loadingColorTextEl" value="${valj_efb[0].loading_color}" style="width: 100px;" readonly>
    </div>
  </div>
  <div class="efb mt-3 mb-3 p-3 border rounded-4 bg-light" id="loadingPreviewContainer">
    <label class="efb mb-2 text-muted"><i class="efb bi-eye fs-7 ${iconMarginGlobal}"></i>${efb_var.text.preview || 'Preview'} (${valj_efb[0].loadingType || 'Loading Animation'})</label>
    <div class="efb d-flex justify-content-center align-items-center p-3" id="loadingPreviewEl" style="min-height: 60px; background: rgba(255,255,255,0.8); border-radius: 8px;">
      ${getLoadingSvgPreview(valj_efb[0].loading_type, valj_efb[0].loading_color)}
    </div>
  </div>`;
}

const getLoadingSvgPreview = (type, color = '#abb8c3') => {
  const svgMap = {
    'dots': `<svg viewBox="0 0 120 30" height="30px" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="xMidYMid meet">
      <circle cx="15" cy="15" r="15" fill="${color}">
        <animate attributeName="r" from="15" to="9" begin="0s" dur="1s" values="15;9;15" calcMode="linear" repeatCount="indefinite"/>
      </circle>
      <circle cx="60" cy="15" r="9" fill="${color}">
        <animate attributeName="r" from="9" to="15" begin="0.3s" dur="1s" values="9;15;9" calcMode="linear" repeatCount="indefinite"/>
      </circle>
      <circle cx="105" cy="15" r="15" fill="${color}">
        <animate attributeName="r" from="15" to="9" begin="0.6s" dur="1s" values="15;9;15" calcMode="linear" repeatCount="indefinite"/>
      </circle>
    </svg>`,
    'spinner': `<svg class="efb-autofill-spinner" width="36" height="36" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
      <circle cx="12" cy="12" r="10" stroke="${color}" stroke-width="3" fill="none" stroke-linecap="round">
        <animate attributeName="stroke-dasharray" values="0 63;32 63;63 63" dur="1s" repeatCount="indefinite"/>
        <animate attributeName="stroke-dashoffset" values="0;-20;-63" dur="1s" repeatCount="indefinite"/>
      </circle>
    </svg>`,
    'pulse': `<svg width="48" height="48" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
      <circle cx="12" cy="12" r="8" fill="none" stroke="${color}" stroke-width="2">
        <animate attributeName="r" values="8;11;8" dur="1.5s" repeatCount="indefinite"/>
        <animate attributeName="opacity" values="1;0.5;1" dur="1.5s" repeatCount="indefinite"/>
      </circle>
      <circle cx="12" cy="12" r="4" fill="${color}">
        <animate attributeName="r" values="4;6;4" dur="1.5s" repeatCount="indefinite"/>
      </circle>
    </svg>`,
    'bars': `<svg width="48" height="48" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
      <rect x="2" y="6" width="4" height="12" fill="${color}">
        <animate attributeName="height" values="12;20;12" dur="0.8s" repeatCount="indefinite"/>
        <animate attributeName="y" values="6;2;6" dur="0.8s" repeatCount="indefinite"/>
      </rect>
      <rect x="10" y="6" width="4" height="12" fill="${color}">
        <animate attributeName="height" values="12;20;12" dur="0.8s" begin="0.2s" repeatCount="indefinite"/>
        <animate attributeName="y" values="6;2;6" dur="0.8s" begin="0.2s" repeatCount="indefinite"/>
      </rect>
      <rect x="18" y="6" width="4" height="12" fill="${color}">
        <animate attributeName="height" values="12;20;12" dur="0.8s" begin="0.4s" repeatCount="indefinite"/>
        <animate attributeName="y" values="6;2;6" dur="0.8s" begin="0.4s" repeatCount="indefinite"/>
      </rect>
    </svg>`,
    'ripple': `<svg width="48" height="48" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
      <circle cx="12" cy="12" r="0" fill="none" stroke="${color}" stroke-width="2">
        <animate attributeName="r" values="0;10" dur="1.5s" repeatCount="indefinite"/>
        <animate attributeName="opacity" values="1;0" dur="1.5s" repeatCount="indefinite"/>
      </circle>
      <circle cx="12" cy="12" r="0" fill="none" stroke="${color}" stroke-width="2">
        <animate attributeName="r" values="0;10" dur="1.5s" begin="0.5s" repeatCount="indefinite"/>
        <animate attributeName="opacity" values="1;0" dur="1.5s" begin="0.5s" repeatCount="indefinite"/>
      </circle>
      <circle cx="12" cy="12" r="3" fill="${color}"/>
    </svg>`,
    'bounce': `<svg width="120" height="40" viewBox="0 0 60 20" xmlns="http://www.w3.org/2000/svg">
      <circle cx="10" cy="10" r="5" fill="${color}">
        <animate attributeName="cy" values="10;4;10" dur="0.6s" repeatCount="indefinite"/>
      </circle>
      <circle cx="30" cy="10" r="5" fill="${color}">
        <animate attributeName="cy" values="10;4;10" dur="0.6s" begin="0.15s" repeatCount="indefinite"/>
      </circle>
      <circle cx="50" cy="10" r="5" fill="${color}">
        <animate attributeName="cy" values="10;4;10" dur="0.6s" begin="0.3s" repeatCount="indefinite"/>
      </circle>
    </svg>`,
    'orbit': `<svg width="48" height="48" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
      <circle cx="12" cy="12" r="3" fill="${color}"/>
      <circle cx="12" cy="4" r="2" fill="${color}">
        <animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="1s" repeatCount="indefinite"/>
      </circle>
      <circle cx="12" cy="4" r="1.5" fill="${color}" opacity="0.6">
        <animateTransform attributeName="transform" type="rotate" from="180 12 12" to="540 12 12" dur="1.5s" repeatCount="indefinite"/>
      </circle>
    </svg>`,
    'wave': `<svg width="80" height="40" viewBox="0 0 40 20" xmlns="http://www.w3.org/2000/svg">
      <circle cx="5" cy="10" r="3" fill="${color}">
        <animate attributeName="opacity" values="0.3;1;0.3" dur="1s" repeatCount="indefinite"/>
      </circle>
      <circle cx="15" cy="10" r="3" fill="${color}">
        <animate attributeName="opacity" values="0.3;1;0.3" dur="1s" begin="0.2s" repeatCount="indefinite"/>
      </circle>
      <circle cx="25" cy="10" r="3" fill="${color}">
        <animate attributeName="opacity" values="0.3;1;0.3" dur="1s" begin="0.4s" repeatCount="indefinite"/>
      </circle>
      <circle cx="35" cy="10" r="3" fill="${color}">
        <animate attributeName="opacity" values="0.3;1;0.3" dur="1s" begin="0.6s" repeatCount="indefinite"/>
      </circle>
    </svg>`,
    'hourglass': `<svg width="48" height="48" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
      <path d="M6 2h12v6l-4 4 4 4v6H6v-6l4-4-4-4V2z" fill="none" stroke="${color}" stroke-width="2" stroke-linejoin="round">
        <animateTransform attributeName="transform" type="rotate" from="0 12 12" to="180 12 12" dur="1.5s" repeatCount="indefinite"/>
      </path>
    </svg>`
  };
  return svgMap[type] || svgMap['dots'];
}

const updateLoadingPreview = () => {
  const previewEl = document.getElementById('loadingPreviewEl');
  if (previewEl) {
    previewEl.innerHTML = getLoadingSvgPreview(valj_efb[0].loading_type, valj_efb[0].loading_color);
  }
}

const textEls=(id , name ,el_type,value ,attr ,idset) =>{

  return`<div id="${idset}_lab_g" class="efb m-0 p-0"><label for="textEl" class="efb form-label mt-2 mb-1 efb">${name}<span class="efb  mx-1 efb text-danger">*</span></label>
<input type="${el_type}"  data-id="${idset}" data-atr="${attr}" class="efb  elEdit form-control text-muted border-d rounded-4 h-d-efb mb-1"  data-id="${id}" id="textEl" required value="${value}"></div>`
}

const currencyTypeEls=(idset)=>{
  let op = `<-- options -->`;
  for(let i of currency_efb){
    op += `<option value="${i.toLowerCase()}" ${valj_efb[0].currency.toUpperCase()==i.slice(0, 3) ? 'selected' :''}>${i}</option>`
  }
  return `
  <label for="currencyTypeEl" class="efb mt-3 efb"><i class="efb bi-cash fs-7 ${iconMarginGlobal}"></i>${efb_var.text.currency}</label>
                    <select  data-id="${idset}" class="efb elEdit form-select efb border-d rounded-4"  id="currencyTypeEl"  data-tag="${valj_efb[0].currency}">
                       ${op}
                    </select>
  `

}

const currencyPaypalTypeEls=(idset)=>{
  let op = `<-- options -->`;
  for(let i of currency_paypal_efb){
    op += `<option value="${i.toLowerCase()}" ${valj_efb[0].currency.toUpperCase()==i.slice(0, 3) ? 'selected' :''}>${i}</option>`
  }
  return `
  <label for="currencyTypeEl" class="efb mt-3 efb"><i class="efb bi-cash fs-7 ${iconMarginGlobal}"></i>${efb_var.text.currency}</label>
                    <select  data-id="${idset}" class="efb elEdit form-select efb border-d rounded-4"  id="currencyTypeEl"  data-tag="${valj_efb[0].currency}">
                       ${op}
                    </select>
  `

}

const paymentPersianPayEls =(idset)=>{

  return`<label for="paymentPersianPayEl" class="efb mt-3 efb"><i class="efb bi-wallet2 fs-7 ${iconMarginGlobal}"></i>درگاه</label>
  <select  data-id="${idset}" class="efb elEdit form-select efb border-d rounded-4"  id="paymentPersianPayEl"  data-tag="${valj_efb[0].type}">
  <option value="zarinPal" ${valj_efb[0].persiaPay=='zarinPal' ? 'selected' :''}>زرین پال</option>
  <option disabled value="efb" ${valj_efb[0].persiaPay=='efb' ? 'selected' :''}>وایت استادیو</option>
  <option disabled value="efb" ${valj_efb[0].persiaPay=='melt' ? 'selected' :''}>ملت</option>
  </select>`;
}

const ElementAlignEls = (side ,indx ,idset) => {
  const _isDesktopField = (side === 'label' || side === 'description');
  const _deskHide = _isDesktopField && typeof currentViewEfb !== 'undefined' && currentViewEfb === 'mobile' ? 'd-none' : '';
  const _wrapClass = _isDesktopField ? 'efb-desktop-settings-efb' : '';
  const left = side == 'label' ? 'txt-left' : 'justify-content-start'
  const right = side == 'label' ? 'txt-right' : 'justify-content-end'
  const center = side == 'label' ? 'txt-center' : 'justify-content-center'
  let value = valj_efb[indx].label_align;
  let t = efb_var.text.label
  if (side == 'description') {
    value = valj_efb[indx].message_align;
    t = efb_var.text.description
  }
  const lab = efb_var.text[side] || side;
  return `<div class="efb ${_wrapClass} ${_deskHide}">
  <div class="efb  row">
  <label for="labelPostionEl" class="efb  mt-3 col-12"><i class="efb bi-align-center fs-7 ${iconMarginGlobal}"></i>${side == 'label' ? (efb_var.text.slabelAlign.replace('%s', '') || (lab + ' | ' + efb_var.text.align)) : (efb_var.text.sdescAlign.replace('%s', '') || (lab + ' | ' + efb_var.text.align))}</label>
    <div class="efb  btn-group btn-group-toggle col-12 " data-toggle="buttons" data-side="${side}" data-id="${idset}"  id="ElementAlignEl">
      <label class="efb ntb btn-primary ${value == left ? `active` : ''}" onclick="funSetAlignElEfb('${idset}','${left}','${side}')"><i class="efb bi-align-start fs-7 ${iconMarginGlobal}"></i>
        <input type="radio" name="options" class="efb  opButtonEfb elEdit "  data-id="${idset}"  id="labelPostionEl" value="left" >${efb_var.text.left}</label>
      <span class="efb border-right border border-light "></span>
      <label class="efb ntb btn-primary ${value == center ? `active` : ''}" onclick="funSetAlignElEfb('${idset}','${center}','${side}')"><i class="efb bi-align-center fs-7 ${iconMarginGlobal}"></i>
        <input type="radio" name="options" class="efb opButtonEfb elEdit" data-id="${idset}"  id="labelPostionEl" value="center">${efb_var.text.center}</label>
      <span class="efb border-right border border-light "></span>
      <label class="efb ntb btn-primary ${value == right ? `active` : ''}" onclick="funSetAlignElEfb('${idset}','${right}','${side}')"><i class="efb bi-align-end fs-7 ${iconMarginGlobal}"></i>
        <input type="radio" name="options" class="efb  opButtonEfb elEdit" data-id="${idset}"  id="labelPostionEl" value="right">${efb_var.text.right}</label>
    </div></div></div>`;
}

const countries_list_el_select=(el_type ,idset,indx)=>{

  let opt =`<option selected disabled>${efb_var.text.nothingSelected}</option>`;
  let country = valj_efb[indx].hasOwnProperty("country") ? valj_efb[indx].country : null;
  if (country==null){
    country  = lan_con_efb.hasOwnProperty(efb_var.language) ? lan_con_efb[efb_var.language] :'US';
  }
  counstries_list_efb.sort((a, b) => a.n.localeCompare(b.n));
  for (let i of counstries_list_efb) {
    opt +=`<option value="${i.s2.toLowerCase()}" ${ i.s2.toLowerCase()==country.toLowerCase() ? `selected` : ''}>${i.l} (${i.s2})</option>`
  }
  return `
  <div class="efb mx-1 mt-3">
  <label for="countriesListEl" class="efb mt-3 efb"><i class="efb bi-aspect-ratio fs-7 ${iconMarginGlobal}"></i>${efb_var.text.sctdlosp}</label>
  <select  data-id="${idset}" data-type="${el_type}" class="efb elEdit form-select efb border-d rounded-4"  id="countriesListEl"  data-tag="${valj_efb[indx].type}">
  ${opt}
  </select>
  </div>
  `
}
const state_list_el_select=(el_type ,idset,indx)=>{
  let opt =`<!--efb--->`;
  let country = valj_efb[indx].hasOwnProperty("country") ? valj_efb[indx].country : 'GB';
  let statePov = valj_efb[indx].hasOwnProperty("statePov") ? valj_efb[indx].statePov : 'Antrim_Newtownabbey';
  country= country.toLowerCase();

  if (country==null){
    country  = lan_con_efb.hasOwnProperty(efb_var.language) ? lan_con_efb[efb_var.language] :'US';
  }

    if(country=='gb'){
        state_list_efb=fun_state_of_UK(idset,indx) ;
      for (let i of state_list_efb) {

        opt +=`<option value="${i.s2.toLowerCase()}" ${ i.s2.toLowerCase()==statePov.toLowerCase() ? `selected` : ''}>${i.value} (${i.s2})</option>`
      }
    }else{
      const parent_id = valj_efb[indx].id_;
      const parent_row =valj_efb[indx]
      const state = valj_efb.filter(v => v.parent == parent_id);
      if(state.length === 0){
        opt = callFetchStatesPovEfb('statePovListEl',country,indx,'getStatesPovEfb');
      }else{
        const citySelected = parent_row.value!='' ? parent_row.value.toLowerCase() : '';
        for (let i of state) {
           let value = i.n==i.l || i.l.length<1 ? i.n : `${i.l} (${i.n})`;
            if(parent_row.hasOwnProperty('stylish') && Number(parent_row.stylish)>1){
            value =  Number(parent_row.stylish)==2 && i.l.length>1 ? i.l : i.n;
            }

          opt +=`<option value="${i.id_.toLowerCase()}" ${ i.id_.toLowerCase()==citySelected ? `selected` : ''}>${i.value}</option>`
        }
      }

    }
  return `
  <div class="efb mx-1 mt-1">
  <label for="statePovListEl" class="efb mt-3 efb"><i class="efb bi-aspect-ratio fs-7 ${iconMarginGlobal}"></i>${efb_var.text.sctdlocp}</label>
  <select  data-id="${idset}" data-type="${el_type}" class="efb elEdit form-select efb border-d rounded-4"  id="statePovListEl"  data-tag="${valj_efb[indx].type}">
  ${opt}
  </select>
  </div>
  `
}
const SingleTextEls = (side,idset,indx) => {
  let text = "";
  let t = ""
  if (side == "Next") { text = valj_efb[0].button_Next_text; t = efb_var.text.next; }
  else if (side == "Previous") { text = valj_efb[0].button_Previous_text; t = efb_var.text.previous; }
  else { text = valj_efb[indx].button_single_text }
  side == "Next" ? text = valj_efb[0].button_Next_text : text = valj_efb[0].button_Previous_text;
  side == "" ? text = valj_efb[indx].button_single_text : 0;
  return `<label for="SingleTextEl" class="efb  form-label  mt-2">${t} ${efb_var.text.text}</label>
  <input type="text" data-id="${idset}" class="efb  elEdit text-muted border-d rounded-4 form-control h-d-efb mb-1" data-side="${side}" placeholder="${efb_var.text.text}" id="SingleTextEl" required value="${text ? text : ''}">`
}

const cornerEls = (side,indx,idset) => {

  return `
    <div class="efb  row">
    <label for="cornerEl" class="efb  mt-3 col-12"><i class="efb bi-bounding-box-circles fs-7 ${iconMarginGlobal}"></i>${efb_var.text.corners}>${efb_var.text.rounded}</label>
    <div class="efb  btn-group col-12  btn-group-toggle" style="flex-wrap:wrap;gap:2px;" data-toggle="buttons" data-side="${side}" data-id="${idset}-set" data-tag="${valj_efb[indx].type}" id="cornerEl">
      <label class="efb  ntb  btn-primary ${valj_efb[indx].hasOwnProperty('corner') && valj_efb[indx].corner == 'efb-square' || valj_efb[indx].corner =="0"  ? `active` : ''}" style="flex:1 1 auto;min-width:36px;" onclick="funSetCornerElEfb('${idset}','rounded-0')"><i class="efb bi-app fs-7 ${iconMarginGlobal}"></i>
        <input type="radio" name="options" class="efb  opButtonEfb elEdit "  data-id="${idset}"  id="cornerEl" value="rounded-4" >0</label>
      <label class="efb  ntb  btn-primary ${valj_efb[indx].hasOwnProperty('corner') && valj_efb[indx].corner =="1" ? `active` : ''}" style="flex:1 1 auto;min-width:36px;" onclick="funSetCornerElEfb('${idset}','rounded-1')"><i class="efb bi-app fs-7 ${iconMarginGlobal}"></i>
        <input type="radio" name="options" class="efb  opButtonEfb elEdit "  data-id="${idset}"  id="cornerEl" value="rounded-4" >1</label>
      <label class="efb  ntb  btn-primary ${valj_efb[indx].hasOwnProperty('corner') && valj_efb[indx].corner =="2" ? `active` : ''}" style="flex:1 1 auto;min-width:36px;" onclick="funSetCornerElEfb('${idset}','rounded-2')"><i class="efb bi-app fs-7 ${iconMarginGlobal}"></i>
        <input type="radio" name="options" class="efb  opButtonEfb elEdit "  data-id="${idset}"  id="cornerEl" value="rounded-4" >2</label>
      <label class="efb  ntb  btn-primary ${valj_efb[indx].hasOwnProperty('corner') && valj_efb[indx].corner =="3" ? `active` : ''}" style="flex:1 1 auto;min-width:36px;" onclick="funSetCornerElEfb('${idset}','rounded-3')"><i class="efb bi-app fs-7 ${iconMarginGlobal}"></i>
        <input type="radio" name="options" class="efb  opButtonEfb elEdit "  data-id="${idset}"  id="cornerEl" value="rounded-4" >3</label>
      <label class="efb  ntb  btn-primary ${valj_efb[indx].hasOwnProperty('corner') && valj_efb[indx].corner =="4" ? `active` : ''}" style="flex:1 1 auto;min-width:36px;" onclick="funSetCornerElEfb('${idset}','rounded-4')"><i class="efb bi-app fs-7 ${iconMarginGlobal}"></i>
        <input type="radio" name="options" class="efb  opButtonEfb elEdit "  data-id="${idset}"  id="cornerEl" value="rounded-4" >4</label>
      <label class="efb  ntb  btn-primary ${valj_efb[indx].hasOwnProperty('corner') && valj_efb[indx].corner == 'rounded-4' || valj_efb[indx].corner =="5" ? `active` : ''}" style="flex:1 1 auto;min-width:36px;" onclick="funSetCornerElEfb('${idset}','rounded-5')"><i class="efb bi-app fs-7 ${iconMarginGlobal}"></i>
        <input type="radio" name="options" class="efb  opButtonEfb elEdit "  data-id="${idset}"  id="cornerEl" value="rounded-4" >5</label>
        <!-- <span class="efb  border-right border border-light "></span>
      <label class="efb  ntb btn-primary ${!valj_efb[indx].hasOwnProperty('corner') && valj_efb[indx].corner == 'efb-square' ? `active` : ''}" onclick="funSetCornerElEfb('${idset}','efb-square')"><i class="efb bi-diamond fs-7 ${iconMarginGlobal}"></i>
        <input type="radio" name="options" class="efb  opButtonEfb elEdit" data-id="${idset}"  id="cornerEl" value="efb-square"> ${efb_var.text.square}</label>-->
    </div></div>`
}

const btnColorEls =(idset,indx) =>{

  color = valj_efb[indx].button_color;
  const hex=ColorNameToHexEfbOfElEfb(color.slice(4),indx,'btn')
  addColorTolistEfb(hex);
  idset =  valj_efb[indx].type =="esign" ? idset+'-id' :idset;
  return `<label for="btnColorEl" class="efb mt-3 efb"><i class="efb bi-paint-bucket fs-7 ${iconMarginGlobal}"></i>${efb_var.text.buttonColor}</label>
  <input type="color" id="btnColorEl" class="efb elEdit form-select efb border-d rounded-4" data-id="${idset}" data-el="button" data-type="button"  data-tag="${valj_efb[indx].type}" value="${hex!=''?hex:'#fff000'}" name="btnColorEl"  id="${idset}" >
  `
}

const hrefEls = (idset,indx) => {
  return `<label for="hrefEl" class="efb mt-3 efb"><i class="efb bi-box-arrow-up-right fs-7 ${iconMarginGlobal}"></i>${efb_var.text.link}</label>
  <input type="url" id="hrefEl" class="efb  elEdit text-muted form-control border-d rounded-4 efb mb-3 mb-1" data-id="${idset}" data-el="link" data-type="border" placeholder="https://"  data-tag="${valj_efb[indx].type}" value="${valj_efb[indx].href}" name="hrefEls"  id="${idset}" >
  `
}

const selectBorderColorEls = (forEl,indx,idset) => {
  let color = valj_efb[indx].el_border_color;
  let t = ''
  const hex=ColorNameToHexEfbOfElEfb(color.slice(7),indx,'border');
  addColorTolistEfb(hex);
  return `<span class="efb"><label for="selectBorderColorEl" class="efb mt-3 efb"><i class="efb bi-paint-bucket fs-7 ${iconMarginGlobal}"></i>${efb_var.text.borderColor}</label>
  <input type="color" id="selectBorderColorEl" class="efb elEdit form-select efb border-d rounded-4" data-id="${idset}" data-el="${forEl}" data-type="border"  data-tag="${valj_efb[indx].type}" value="${hex!=''?hex:'#fff000'}" name="selectColorEl"  id="${idset}" ></span>
  `
}
const fontSizeEls = (idset,indx) => {
  return `
    <label for="fontSizeEl" class="efb  mt-3"><i class="efb bi-arrow-down-up fs-7 ${iconMarginGlobal}"></i>${efb_var.text.height}</label>
    <select  data-id="${idset}" class="efb  rounded-4 elEdit form-select"  id="fontSizeEl" data-tag="${valj_efb[indx].type}">
    <option value="display-1"  ${valj_efb[indx].el_text_size ==  'display-1' ? `selected` : ''}>${efb_var.text.xxxlarge}</option>
    <option value="display-2"  ${valj_efb[indx].el_text_size == 'display-2' ? `selected` : ''} >${efb_var.text.xxlarge}</option>
    <option value="display-3"  ${valj_efb[indx].el_text_size == 'display-3' ? `selected` : ''} >${efb_var.text.xlarge}</option>
    <option value="display-4"  ${valj_efb[indx].el_text_size == 'display-4' ? `selected` : ''} >${efb_var.text.large}</option>
    <option value="display-5"  ${valj_efb[indx].el_text_size == 'display-5' ? `selected` : ''} >${efb_var.text.medium }</option>
    <option value="display-6"  ${valj_efb[indx].el_text_size == 'display-6' ? `selected` : ''} >${efb_var.text.small }</option>
    <option value="display-7"  ${valj_efb[indx].el_text_size == 'display-7' ? `selected` : ''} >${efb_var.text.xsmall}</option>
    <option value="display-8"  ${valj_efb[indx].el_text_size == 'display-8' ? `selected` : ''} >${efb_var.text.xxsmall}</option>
    </select>
    `
}
const selectHeightEls = (idset,indx) => {

  return `
    <label for="selectHeightEl" class="efb  mt-3"><i class="efb bi-arrow-down-up fs-7 ${iconMarginGlobal}"></i>${efb_var.text.height}</label>
    <select  data-id="${idset}" class="efb  rounded-4 elEdit form-select"  id="selectHeightEl" data-tag="${valj_efb[indx].type}">
    <option value="h-d-efb" ${ valj_efb[indx].el_height == 'h-d-efb' ? `selected` : ''}>${efb_var.text.default}</option>
    <option value="h-l-efb"  ${ valj_efb[indx].el_height == 'h-l-efb' ? `selected` : ''} >${efb_var.text.large}</option>
    <option value="h-xl-efb"  ${ valj_efb[indx].el_height == 'h-xl-efb' ? `selected` : ''} >${efb_var.text.xlarge}</option>
    <option value="h-xxl-efb"  ${ valj_efb[indx].el_height == 'h-xxl-efb' ? `selected` : ''} >${efb_var.text.xxlarge}</option>
    <option value="h-xxxl-efb"  ${ valj_efb[indx].el_height == 'h-xxxl-efb' ? `selected` : ''} >${efb_var.text.xxxlarge}</option>
    </select>
    `
}
const ElcountriesListSelections = (idset,indx) => {
  const rndm = idset;
 let optn = `<!--opt-->`;
 let selectData =""
 let value ="";
 let c_c =[];
 if(valj_efb[indx].hasOwnProperty("c_c")){
  for(let i of valj_efb[indx].c_c){

   c_c.push(i);
   value +=i + ","
   selectData +=i + " @efb!"
  }
 }
  for (const i of counstries_list_efb) {

    const s2 = i.s2.trim().toLowerCase();
    const v = i.l!=i.n  ? `(${i.l})` :''
    optn += `<tr   class="efb   efblist " data-indx="${indx}" data-id="${s2}" data-code="${i.c_c}" data-name="${s2}" data-row="${s2}" data-state="0" data-visible="1">
    <th scope="row" class="efb ${c_c.indexOf(s2)!=-1 ? 'bi-check-square text-info' : 'bi-square'}" onclick="fun_test(this)" data-indx="${indx}" data-id="${s2}" data-code="${i.c_c}" data-name="${s2}" ></th><td class="efb ms col-12"  onclick="fun_test(this)" data-indx="${indx}" data-id="${s2}" data-code="${i.c_c}" data-name="${s2}">${i.n} ${v}</td>
  </tr>  `

  }
   return `
    <label for="${rndm}-f" class="efb  mt-3"><i class="efb bi-arrow-down-up fs-7 ${iconMarginGlobal}"></i>${efb_var.text.scdnmi}</label>
    <div class="efb col-sm-12 listSelect mx-0 ttEfb show"   id='${rndm}-f' data-id="${rndm}-el" >
    <div class="efb efblist  mx-0  inplist  h-d-efb rounded-4 border-d bi-chevron-down" data-id="menu-${rndm}"   data-no="145" data-min="" data-parent="1" data-icon="1" data-select="${selectData}"  data-vid='${rndm}' id="${rndm}_options" > ${value.length>1 ? value :efb_var.text.selectOption}</div>

    <div class="efb efblist mx-1  listContent d-none rounded-bottom  bg-light" data-id="menu-${rndm}" data-list="menu-${rndm}">
    <table class="efb table menu-${rndm}">
     <thead class="efb efblist">
       <tr> <div class="efb searchSection efblist p-2 bg-light">
       <!-- <i class="efb efblist searchIcon  bi-search text-primary "></i> -->
           <input type="text" class="efb efblist search searchBox my-1 col-12 rounded " data-id="menu-${rndm}" data-tag="search" placeholder="🔍 ${efb_var.text.search}" onkeyup="FunSearchTableEfb('menu-${rndm}')"> </div>
     </tr> </thead>
     <tbody class="efb fs-7">
      ${optn}
     </tbody>
   </table>
  </div>
    `
}
function fun_test(t){
  const idx = t.dataset.indx;
  const c= t.dataset.name;
  const n= t.dataset.code;
 if( valj_efb[idx].hasOwnProperty("c_c")==false){
  valj_efb[idx].c_c=[c]
  valj_efb[idx].c_n=[n];
  return;
 }else{
   const indx = valj_efb[idx].c_c.indexOf(c)
   if(indx!=-1){

    valj_efb[idx].c_c.splice(indx,1)
    valj_efb[idx].c_n.splice(indx,1)
    return;
  }else{
    valj_efb[idx].c_c.push(c)
    valj_efb[idx].c_n.push(n)
  }
 }

}
function show_setting_window_efb(idset) {
  if(document.getElementById('sideBoxEfb').classList.contains('show')){
    sideMenuEfb(0);
    return};
    state_view_efb=1;
    document.getElementById('sideMenuConEfb').innerHTML=efbLoadingCard('',5);
    sideMenuEfb(1)

    let el = idset != "formSet" ? document.querySelector(`[data-id="${idset}"]`) : { dataset: { id: 'formSet', tag: 'formSet' } }
    let body = ``;
    const indx = idset != "button_group" && idset != "formSet" ? valj_efb.findIndex(x => x.dataId == idset) : 0;

    if (indx == 0 && idset != "formSet") el = document.getElementById(`f_btn_send_efb`);

    const labelEls = `<div id="${idset}_lab_g" class="efb m-0 p-0"><label for="labelEl" class="efb form-label mt-2 mb-1 efb">${efb_var.text.label}<span class="efb  mx-1 efb text-danger">*</span></label>
    <input type="text"  data-id="${idset}" class="efb  elEdit form-control text-muted border-d rounded-4 h-d-efb mb-1"  placeholder="${efb_var.text.label}" id="labelEl" required value="${valj_efb[indx].name ? valj_efb[indx].name : ''}"></div>`
    const idHidden = `
    <!-- <input type="hide"  class="efb d-none" data-id="${idset}" data-hide="idhide" id="${valj_efb[indx].id_}" >-->`

    const desEls = `<label for="desEl" class="efb form-label mt-2 mb-1 efb">${efb_var.text.description}</label>
    <input type="text" data-id="${idset}" class="efb elEdit form-control text-muted efb border-d rounded-4 h-d-efb mb-1" placeholder="${efb_var.text.description}" id="desEl" required value="${valj_efb[indx].message ? valj_efb[indx].message : ''}">`

    const miLenEls = ()=>{
    let label =  efb_var.text.min;
    let type = "number"
    if(valj_efb[indx].type=="range" || valj_efb[indx].type=="number") {label = efb_var.text.min}
    else if(valj_efb[indx].type=="date") {
      label = efb_var.text.mindt; ;
      type =  'text'}

    return  `<label for="miLenEl" class="efb form-label mt-2 mb-1 efb">${label}</label>
    <input type="${type}" data-id="${idset}" class="efb elEdit form-control text-muted efb border-d rounded-4 h-d-efb mb-1" placeholder="${label}" id="miLenEl" required value="${valj_efb[indx].hasOwnProperty('milen') ? valj_efb[indx].milen : ''}" min="0">`
  }

  const mLenEls = ()=>{
    let label =  efb_var.text.max;
    let type = "number"
    if(valj_efb[indx].type=="range" || valj_efb[indx].type=="number") {label = efb_var.text.max}
    else if(valj_efb[indx].type=="date") {
      label = efb_var.text.mxdt;
      type =  'text'}
      return `<label for="mLenEl" class="efb form-label mt-2 mb-1 efb">${label}</label>
      <input type="${type}" data-id="${idset}" class="efb elEdit form-control text-muted efb border-d rounded-4 h-d-efb mb-1" placeholder="${label}" id="mLenEl" required value="${valj_efb[indx].hasOwnProperty('mlen') ? valj_efb[indx].mlen : ''}" min="0">`
  }
  const requireEls = `<div class="efb mx-1 my-3 efb">
    <button type="button" id="requiredEl" data-state="off" data-name="disabled" class="efb mx-0 btn h-s-efb  btn-toggle ${valj_efb[indx].hasOwnProperty('required') && Number(valj_efb[indx].required) == 1 ? 'active' : ''}" data-toggle="button" aria-pressed="false" autocomplete="off"  data-id="${idset}"  onclick="fun_switch_form_efb(this)" >
    <div class="efb handle"></div>
    </button>
    <label class="efb form-check-label pt-1" for="requiredEl">${efb_var.text.required}</label>
    </div>`;

    // Custom required message input (PRO feature)
    const customRequiredMsgEls = () => {
      const isPro = (typeof pro_efb !== 'undefined' && pro_efb === true) || (typeof efb_var !== 'undefined' && (efb_var.pro == "1" || efb_var.pro == 1 || efb_var.pro === true));
      const currentValue = valj_efb[indx].hasOwnProperty('customRequiredMsg') ? valj_efb[indx].customRequiredMsg : '';
      // Dynamic label using customMessage with %s placeholder
      const labelTxt = efb_var.text.customMessage
        ? efb_var.text.customMessage.replace('%s', efb_var.text.required || 'Required')
        : 'Custom Required Message';
      const placeholderTxt = efb_var.text.enterTheValueThisField || 'This field is required.';
      // Dynamic hint using customMessageHint with %s placeholder
      const hintTxt = efb_var.text.customMessageHint
        ? efb_var.text.customMessageHint.replace('%s', efb_var.text.message || 'message')
        : 'Leave empty to use default message';
      const isVisible = valj_efb[indx].hasOwnProperty('required') && Number(valj_efb[indx].required) == 1;
      return `<div class="efb mx-1 my-1 efb customRequiredMsgWrapper" style="transition: opacity 0.3s ease, max-height 0.3s ease; overflow: hidden; ${isVisible ? 'opacity: 1; max-height: 200px;' : 'opacity: 0; max-height: 0; padding: 0; margin: 0;'}">
        ${!isPro ? '<div class="efb pro-card"><a type="button" onclick="pro_show_efb(1)" class="efb pro-version-efb" data-bs-toggle="tooltip" data-bs-placement="top" title="' + (efb_var.text.fieldAvailableInProversion || 'PRO') + '"><i class="efb bi-gem text-light"></i></a></div>' : ''}
        <label for="customRequiredMsgEl" class="efb form-label mt-2 mb-1 efb"><i class="efb bi-chat-square-text fs-7 me-1"></i>${labelTxt}</label>
        <input type="text" data-id="${idset}" class="efb elEdit form-control text-muted efb border-d rounded-4 h-d-efb mb-1" placeholder="${placeholderTxt}" id="customRequiredMsgEl" value="${currentValue}" ${!isPro ? 'disabled' : ''}>
        <small class="efb text-muted fs-8 mx-2">${hintTxt}</small>
      </div>`;
    };
    const hiddenEls = `<div class="efb mx-0 my-1 efb">
    <button type="button" id="hiddenEl" data-state="off" data-name="disabled" class="efb mx-0 btn h-s-efb  btn-toggle ${valj_efb[indx].hasOwnProperty('hidden') && Number(valj_efb[indx].hidden) == 1 ? 'active' : ''}" data-toggle="button" aria-pressed="false" autocomplete="off"  data-id="${idset}"  onclick="fun_switch_form_efb(this)" >
    <div class="efb handle"></div>
    </button>
    <label class="efb form-check-label" for="hiddenEl">${efb_var.text.hField}</label>
    </div>`;
    const disabledEls = `<div class="efb mx-0 my-1 efb">
    <button type="button" id="disabledEl" data-state="off" data-name="disabled" class="efb mx-0 btn h-s-efb  btn-toggle ${valj_efb[indx].hasOwnProperty('disabled') && Number(valj_efb[indx].disabled) == 1 ? 'active' : ''}" data-toggle="button" aria-pressed="false" autocomplete="off"  data-id="${idset}"  onclick="fun_switch_form_efb(this)" >
    <div class="efb handle"></div>
    </button>
    <label class="efb form-check-label" for="disabledEl">${efb_var.text.dField}</label>
    </div>`;
    const showInPublicResultsEls = `<div class="efb mx-0 my-1 efb survey-public-results-toggle ${valj_efb[0].type !== 'survey' ? 'd-none' : ''}" id="showInPublicResultsWrapper-${indx}">
    <button type="button" id="showInPublicResultsEl" data-state="off" data-name="showInPublicResults" class="efb mx-0 btn h-s-efb  btn-toggle ${valj_efb[indx].hasOwnProperty('showInPublicResults') && Number(valj_efb[indx].showInPublicResults) == 1 ? 'active' : ''}" data-toggle="button" aria-pressed="false" autocomplete="off"  data-id="${idset}"  onclick="fun_switch_form_efb(this)" >
    <div class="efb handle"></div>
    </button>
    <label class="efb form-check-label" for="showInPublicResultsEl">${efb_var.text.showInPublicResults || 'Show this field in public survey results'}</label>
    </div>`;
    const hideLabelEls = `<div class="efb mx-1 my-3 efb">
    <button type="button" id="hideLabelEl" data-state="off" data-name="disabled" class="efb mx-0 btn h-s-efb  btn-toggle ${valj_efb[indx].hasOwnProperty('hflabel') && Number(valj_efb[indx].hflabel) == 1 ? 'active' : ''}" data-toggle="button" aria-pressed="false" autocomplete="off"  data-id="${idset}"  onclick="fun_switch_form_efb(this)" >
        <div class="efb handle"></div>
      </button>
    <label class="efb form-check-label" for="hideLabelEl">${efb_var.text.hflabel}</label>
    </div>`;
    const cardEls = `<div class="efb mx-1 my-3 efb">
    <button type="button" id="cardEl" data-state="off" data-name="disabled" class="efb mx-0 btn h-s-efb  btn-toggle ${valj_efb[indx].hasOwnProperty('dShowBg') && Number(valj_efb[indx].dShowBg) == 1 ? 'active' : ''}" data-toggle="button" aria-pressed="false" autocomplete="off"  data-id="${idset}"  onclick="fun_switch_form_efb(this)" >
    <div class="efb handle"></div>
    </button>
    <label class="efb form-check-label pt-1" for="cardEl">${efb_var.text.dNotShowBg}</label>
    </div>`;
    const offLineEls = `<div class="efb mx-1 my-3 efb">
    <button type="button" id="offLineEl" data-state="off" data-name="disabled" class="efb mx-0 btn h-s-efb  btn-toggle ${valj_efb[indx].hasOwnProperty('AfLnFrm') && Number(valj_efb[indx].AfLnFrm) == 1 ? 'active' : ''}" data-toggle="button" aria-pressed="false" autocomplete="off"  data-id="${idset}"  onclick="fun_switch_form_efb(this)" >
    <div class="efb handle"></div>
    </button>
    <label class="efb form-check-label" for="offLineEl">${efb_var.text.AfLnFrm}</label>
    </div>`;

    const emailEls = `<div class="efb mx-1 my-3 efb">
    <button type="button" id="SendemailEl" data-state="off" data-name="disabled" class="efb mx-0 btn h-s-efb  btn-toggle ${ (valj_efb[indx].hasOwnProperty('noti') && Number(valj_efb[indx].noti) ==1) ? 'active' : ''}" data-toggle="button" aria-pressed="false" autocomplete="off"  data-id="${idset}" data-vid="${valj_efb[indx].id_}"  onclick="fun_switch_form_efb(this)" >
    <div class="efb handle"></div>
    </button>
    <label class="efb form-check-label pt-1" for="SendemailEl">${efb_var.text.thisEmailNotificationReceive} </label> <i class="efb bi-patch-question fs-7 text-success pointer-efb ec-efb" data-eventform="links" data-linkname="EmailNoti"> </i>
    </div>`;
    const adminFormEmailEls = `

    <label for="adminFormEmailEl" class="efb form-label mt-2 mb-1 efb">${efb_var.text.enterAdminEmailReceiveNoti}<i class="efb bi-patch-question fs-7 text-success pointer-efb ec-efb" data-eventform="links" data-linkname="EmailNoti"> </i></label>
    <input type="text" data-id="${idset}" class="efb elEdit text-muted form-control h-d-efb border-d rounded-4  mb-1 efb" placeholder="${efb_var.text.email}" id="adminFormEmailEl" required value="${valj_efb[0].email ? valj_efb[0].email : ''}">`
    const FormEmailSubjectEls = () =>{
      const value = valj_efb[0].hasOwnProperty('email_sub') ? valj_efb[0].email_sub : '';
      const hintTxt = efb_var.text.customMessageHint
        ? efb_var.text.customMessageHint.replace('%s', efb_var.text.mlsbjt.toLowerCase() || 'email subject')
        : 'Leave empty to use default message';
      return `
      <div class="efb mx-1 efb ">
      ${pro_efb==true ?"":funProEfb()}
      <label for="FormEmailSubjectEl" class="efb form-label mt-2 mb-1 efb">${efb_var.text.mlsbjt}</label>
      <input type="text" data-id="${idset}" class="efb elEdit text-muted form-control h-d-efb border-d rounded-4 efb" placeholder="${efb_var.text.emailSubject || 'Email Subject'}" id="FormEmailSubjectEl" required value="${value}">
      <small class="efb text-muted fs-8 mx-2">${hintTxt}</small>
      </div>`

    }
    const EmailNotiContainsEls =() =>{
      const val = valj_efb[0].hasOwnProperty('email_noti_type') ? valj_efb[indx].email_noti_type : 'cc';

      return `
    <label for="emailNotiContainsEl" class="efb mt-2 mb-1   efb">${efb_var.text.emlc}</label>
                        <select  class="efb elEdit form-select efb border-d rounded-4 mb-1" data-id="${idset}"  id="emailNotiContainsEl" >
                            <option value="cc" ${val == 'cc' ? `selected` : ''}>${efb_var.text.emlacl}</option>
                            <option value="msg" ${val == 'msg' ? `selected` : ''}>${efb_var.text.emlml}</option>
                            <option value="just_msg" ${val == 'just_msg' ? `selected` : ''}>${efb_var.text.emlcc}</option>
                        </select>

    `};
    const trackingCodeEls = `<div class="efb mx-1 my-3 efb">
    <button type="button" id="trackingCodeEl" data-state="off" data-name="disabled" class="efb mx-0 btn h-s-efb  btn-toggle ${valj_efb[indx].hasOwnProperty('trackingCode') && Number(valj_efb[indx].trackingCode) == 1 ? 'active' : ''}" data-toggle="button" aria-pressed="false" autocomplete="off"  data-id="${idset}"  onclick="fun_switch_form_efb(this)" >
    <div class="efb handle"></div>
    </button>
    <label class="efb form-check-label" for="trackingCodeEl">${efb_var.text.activeTrackingCode}</label>
    </div>`;
    const captchaEls = `<div class="efb mx-1 my-3 efb">
    <button type="button" id="captchaEl" data-state="off" data-name="disabled" class="efb mx-0 btn h-s-efb  btn-toggle ${valj_efb[indx].hasOwnProperty('captcha') && Number(valj_efb[indx].captcha) == 1 ? 'active' : ''}" data-toggle="button" aria-pressed="false" autocomplete="off"  data-id="${idset}"  onclick="fun_switch_form_efb(this)" >
    <div class="efb handle"></div>
    </button>
    <label class="efb form-check-label" for="captchaEl">${efb_var.text.addGooglereCAPTCHAtoForm}</label>
    </div>`;
    const stateTrueEfb = (value) => value === true || value === 1 || value === '1' || value === 'true';
    const shieldAvailable = stateTrueEfb(efb_var.shield_available);
    const shieldGlobalEnabled = efb_var.hasOwnProperty('setting') && efb_var.setting != null ? stateTrueEfb(efb_var.setting.shield_silent_captcha) : false;
    const shieldOverrideExists = valj_efb[indx].hasOwnProperty('shield_silent_captcha');
    const shieldOverrideEnabled = stateTrueEfb(valj_efb[indx].shield_silent_captcha);
    const shieldSilentCaptchaActive = shieldOverrideExists ? shieldOverrideEnabled : shieldGlobalEnabled;
    const shieldSilentCaptchaEls = `<div class="efb mx-1 my-3 efb">
    <button type="button" id="shieldSilentCaptchaEl" data-state="off" data-name="disabled" class="efb mx-0 btn h-s-efb  btn-toggle ${shieldSilentCaptchaActive ? 'active' : ''}" data-toggle="button" aria-pressed="false" autocomplete="off" data-id="${idset}" onclick="fun_switch_form_efb(this)" ${shieldAvailable ? '' : 'disabled aria-disabled="true"'}>
    <div class="efb handle"></div>
    </button>
    <label class="efb form-check-label" for="shieldSilentCaptchaEl">${efb_var.text.shieldSilentCaptcha}</label>
    ${shieldAvailable ? '' : `<p class="efb fs-8 mt-1 mb-0 text-muted">${efb_var.text.shieldNotDetected}</p>`}
    </div>`;
    const showSIconsEls = `<div class="efb mx-1 my-3 efb">
    <button type="button" id="showSIconsEl" data-state="off" data-name="disabled" class="efb mx-0 btn h-s-efb  btn-toggle ${valj_efb[indx].hasOwnProperty('show_icon') && Number(valj_efb[indx].show_icon) == 1 ? 'active' : ''}" data-toggle="button" aria-pressed="false" autocomplete="off"  data-id="${idset}"  onclick="fun_switch_form_efb(this)" >
    <div class="efb handle"></div>
    </button>
    <label class="efb form-check-label" for="showSIconsEl">${efb_var.text.dontShowIconsStepsName}</label>
    </div>`;
    const showSprosiEls = `<div class="efb mx-1 my-3 efb">
    <button type="button" id="showSprosiEl" data-state="off" data-name="disabled" class="efb mx-0 btn h-s-efb  btn-toggle ${valj_efb[indx].hasOwnProperty('show_pro_bar') && Number(valj_efb[indx].show_pro_bar) == 1 ? 'active' : ''}" data-toggle="button" aria-pressed="false" autocomplete="off"  data-id="${idset}"  onclick="fun_switch_form_efb(this)" >
    <div class="efb handle"></div>
    </button>
    <label class="efb form-check-label" for="showSprosiEl">${efb_var.text.dontShowProgressBar}</label>
    </div>`;
    let disable =valj_efb[0].type!="register" && valj_efb[0].type!="login"  ? '' : 'disabled';
    const defaultThankYou = typeof getDefaultThankYouByType === 'function' ? getDefaultThankYouByType(valj_efb[0].type) : { thankYou: efb_var.text.thanksFillingOutform, done: efb_var.text.yad };
    const m_tankYouMessage = defaultThankYou.thankYou;
    const m_doneMessage = defaultThankYou.done;
    const thankYouMessageEls = `<div class="efb tnxmsg mt-1  ${valj_efb[0].thank_you=="msg" ? 'd-block' :'d-none'}"><label for="thankYouMessageEl" class="efb form-label mt-2 mb-1 efb">${ efb_var.text.thankYouMessage }</label>
    <input ${disable} type="text" data-id="${idset}" class="efb elEdit text-muted form-control h-d-efb border-d rounded-4  mb-1 efb" placeholder="${efb_var.text.thankYouMessage}" id="thankYouMessageEl" required value="${valj_efb[0].thank_you_message.thankYou ? valj_efb[0].thank_you_message.thankYou : m_tankYouMessage}"></div>`;
    const thankYouMessageDoneEls = `<div class="efb tnxmsg mt-1 ${valj_efb[0].thank_you=="msg" ? 'd-block' :'d-none'}"><label for="thankYouMessageDoneEl" class="efb form-label mt-2 mb-1 efb">${efb_var.text.done} ${efb_var.text.message}</label>
    <input ${disable} type="text" data-id="${idset}" class="efb elEdit text-muted form-control h-d-efb border-d rounded-4  mb-1 efb" placeholder="${efb_var.text.done}" id="thankYouMessageDoneEl" required value="${valj_efb[0].thank_you_message.done ? valj_efb[0].thank_you_message.done : m_doneMessage}"></div>`;
    const thankYouMessageConfirmationCodeEls = `<div class="efb tnxmsg mt-1 ${valj_efb[0].thank_you=="msg" ? 'd-block' :'d-none'}"><label for="thankYouMessageConfirmationCodeEl" class="efb form-label mt-2 mb-1 efb">${efb_var.text.trackingCode} ${efb_var.text.message}</label>
    <input ${disable} type="text" data-id="${idset}" class="efb elEdit text-muted form-control h-d-efb border-d rounded-4  mb-1 efb" placeholder="${efb_var.text.trackingCode}" id="thankYouMessageConfirmationCodeEl" required value="${valj_efb[0].thank_you_message.trackingCode ? valj_efb[0].thank_you_message.trackingCode : efb_var.text.trackingCode}"></div>`;

    const showformLoggedEls = `<div class="efb mx-1 my-3 efb">
    <button type="button" id="showformLoggedEl" data-state="off" data-name="disabled" class="efb mx-0 btn h-s-efb  btn-toggle ${valj_efb[indx].hasOwnProperty('stateForm') && Number(valj_efb[indx].stateForm) == 1 ? 'active' : ''}" data-toggle="button" aria-pressed="false" autocomplete="off"  data-id="${idset}"  onclick="fun_switch_form_efb(this)" >
    <div class="efb handle"></div>
    </button>
    <label class="efb form-check-label" for="showformLoggedEl">${efb_var.text.showTheFormTologgedUsers}</label>
    </div>`;

    const smsEnableEls = `<div class="efb mx-1 my-3 efb">
    <button type="button" id="smsEnableEl" data-state="off" data-name="disabled" class="efb mx-0 btn h-s-efb  btn-toggle ${ (valj_efb[indx].hasOwnProperty('smsnoti') && Number(valj_efb[indx].smsnoti) ==1) ? 'active' : ''}" data-toggle="button" aria-pressed="false" autocomplete="off"  data-id="${idset}" data-vid="${valj_efb[indx].id_}"  onclick="fun_switch_form_efb(this)" >
    <div class="efb handle"></div>
    </button>
    <label class="efb form-check-label pt-1" for="smsEnableEl">${efb_var.text.esmsno} </label> <i class="efb bi-patch-question fs-7 text-success pointer-efb ec-efb" data-eventform="links" data-linkname="SMSNoti"> </i>
    </div>`;

    const telegramEnableEls = `<div class="efb mx-1 my-3 efb">
    <button type="button" id="telegramEnableEl" data-state="off" data-name="disabled" class="efb mx-0 btn h-s-efb  btn-toggle ${ (valj_efb[indx].hasOwnProperty('telegramnoti') && Number(valj_efb[indx].telegramnoti) ==1) ? 'active' : ''}" data-toggle="button" aria-pressed="false" autocomplete="off"  data-id="${idset}" data-vid="${valj_efb[indx].id_}"  onclick="fun_switch_form_efb(this)" >
    <div class="efb handle"></div>
    </button>
    <label class="efb form-check-label pt-1" for="telegramEnableEl">${efb_var.text.etelegramno || 'Enable Telegram notifications'} </label>
    </div>`;
    const enableConEls = `<div class="efb mx-1 my-3 efb">
    <button type="button" id="enableConEl" data-state="off" data-name="disabled" class="efb mx-0 btn h-s-efb  btn-toggle ${ (valj_efb[indx].hasOwnProperty('logic') && Number(valj_efb[indx].logic) ==1) ? 'active' : ''}" data-toggle="button" aria-pressed="false" autocomplete="off"  data-id="${idset}" data-vid="${valj_efb[indx].id_}"  onclick="fun_switch_form_efb(this)" >
    <div class="efb handle"></div>
    </button>
    <label class="efb form-check-label pt-1" for="enableConEl">${efb_var.text.condlogic} </label> <i class="efb bi-patch-question fs-7 text-success pointer-efb ec-efb" data-eventform="links" data-linkname="condi"> </i>
    </div>`;

    const languageSelectPresentEls = `
                     <label for="languageSelectPresentEl" class="efb mt-3 px-1 efb"><i class="efb bi-translate fs-7 ${iconMarginGlobal}"></i>${efb_var.text.stsd}</label>
                      <select  data-id="${idset}" class="efb elEdit form-select efb border-d rounded-4"  id="languageSelectPresentEl"  data-tag="${valj_efb[indx].type}">
                      <option value="1" ${ valj_efb[indx].hasOwnProperty('stylish')==false || valj_efb[indx].stylish == 1 ? `selected` : ''} >${efb_var.text.nlan} (${efb_var.text.elan})</option>
                      <option value="2" ${ valj_efb[indx].stylish == 2 ? `selected` : ''}>${efb_var.text.nlan}</option>
                      <option value="3" ${ valj_efb[indx].stylish == 3 ? `selected` : ''}>${efb_var.text.elan}</option>

                      </select>`;

    const qtyPlcEls = valj_efb[indx].hasOwnProperty('pholder_chl_value')? `<label for="qtyPlclEl" class="efb form-label mt-2 mb-1 efb">${efb_var.text.label}<span class="efb  mx-1 efb text-danger">*</span></label> <input type="text"  data-id="${idset}" class="efb  elEdit form-control text-muted border-d rounded-4 h-d-efb mb-1"  placeholder="${efb_var.text.placeholder}" id="qtyPlcEl" required value="${valj_efb[indx].pholder_chl_value ? valj_efb[indx].pholder_chl_value : ''}">` :'';

    const Nadvanced = `
    ${idHidden}
    ${labelEls}
    ${hideLabelEls}
    ${el.dataset.tag != 'ttlprc' ? requireEls : ''}
    ${el.dataset.tag != 'ttlprc' ? customRequiredMsgEls() : ''}
    ${desEls}`
    const deskHideEfb = typeof currentViewEfb !== 'undefined' && currentViewEfb === 'mobile' ? 'd-none' : '';
    const mobHideEfb = typeof currentViewEfb === 'undefined' || currentViewEfb !== 'mobile' ? 'd-none' : '';

    const labelFontSizeEls = `<div class="efb efb-desktop-settings-efb ${deskHideEfb}">
      <label for="labelFontSizeEl" class="efb mt-3 efb"><i class="efb bi-aspect-ratio fs-7 ${iconMarginGlobal}"></i>${efb_var.text.slabelSize.replace('%s', '') || efb_var.text.labelSize}</label>
                        <select  data-id="${idset}" class="efb elEdit form-select efb border-d rounded-4"  id="labelFontSizeEl"  data-tag="${valj_efb[indx].type}">
                            <option value="fs-6" ${ valj_efb[indx].label_text_size == 'fs-6' ? `selected` : ''}>${efb_var.text.default}</option>
                            <option value="fs-7" ${ valj_efb[indx].label_text_size == 'fs-7' ? `selected` : ''}>${efb_var.text.small}</option>
                            <option value="fs-5" ${ valj_efb[indx].label_text_size == 'fs-5' ? `selected` : ''} >${efb_var.text.large}</option>
                            <option value="fs-4" ${ valj_efb[indx].label_text_size == 'fs-4' ? `selected` : ''} >${efb_var.text.xlarge}</option>
                            <option value="fs-3" ${ valj_efb[indx].label_text_size == 'fs-3' ? `selected` : ''} >${efb_var.text.xxlarge}</option>
                        </select></div>`;
    const optnsStyleEls = `
      <label for="optnsStyleEl" class="efb mt-3 efb"><i class="efb bi-layout-split fs-7 ${iconMarginGlobal}"></i>${efb_var.text.cols}</label>
                        <select  data-id="${idset}" class="efb elEdit form-select efb border-d rounded-4"  id="optnsStyleEl"  data-tag="${valj_efb[indx].type}">
                            <option value="1" ${ !valj_efb[indx].hasOwnProperty('op_style') || valj_efb[indx].op_style == '1' ? `selected` : ''}>${efb_var.text.default}</option>
                            <option value="2" ${ valj_efb[indx].op_style == '2' ? `selected` : ''}>${efb_var.text.col} 2</option>
                            <option value="3" ${ valj_efb[indx].op_style == '3' ? `selected` : ''} >${efb_var.text.col} 3</option>
                        </select>`;

    // Checked color picker for radio/checkbox elements (PRO feature)
    const selectCheckedColorEls = () => {
      let checkedClrHex = valj_efb[indx].hasOwnProperty('checked_color') ? valj_efb[indx].checked_color : '#004cbb';
      const labelText = (efb_var.text.checkedClr || '%s Checked Color').replace('%s', '');
      const isPro = (typeof pro_efb !== 'undefined' && pro_efb === true) || (typeof efb_var !== 'undefined' && (efb_var.pro == "1" || efb_var.pro == 1 || efb_var.pro === true));
      return `<span class="efb">
        <label for="selectCheckedColorEl" class="efb mt-3 efb"><i class="efb bi-check-circle-fill fs-7 ${iconMarginGlobal}"></i>${labelText}</label>
        ${!isPro ? '<div class="efb pro-card"><a type="button" onclick="pro_show_efb(1)" class="efb pro-version-efb" data-bs-toggle="tooltip" data-bs-placement="top" title="' + (efb_var.text.fieldAvailableInProversion || 'PRO') + '"><i class="efb bi-gem text-light"></i></a></div>' : ''}
        <input type="color" id="selectCheckedColorEl" class="efb elEdit form-select efb border-d rounded-4" data-id="${idset}" data-el="checked" data-type="checked" data-tag="${valj_efb[indx].type}" value="${checkedClrHex}" name="selectCheckedColorEl" ${!isPro ? 'disabled' : ''}></span>`;
    };

    // Range thumb color picker (PRO feature)
    const selectRangeThumbColorEls = () => {
      let thumbClrHex = valj_efb[indx].hasOwnProperty('range_thumb_color') ? valj_efb[indx].range_thumb_color : '#004cbb';
      // const labelText = efb_var.text.rangeThumbClr || 'Slider Button Color';
      const labelText = (efb_var.text.scolor.replace('%s', efb_var.text.rangeThumb) || 'Slider Button Color');
      const isPro = (typeof pro_efb !== 'undefined' && pro_efb === true) || (typeof efb_var !== 'undefined' && (efb_var.pro == "1" || efb_var.pro == 1 || efb_var.pro === true));
      return `<span class="efb">
        <label for="selectRangeThumbColorEl" class="efb mt-3 efb"><i class="efb bi-sliders fs-7 ${iconMarginGlobal}"></i>${labelText}</label>
        ${!isPro ? '<div class="efb pro-card"><a type="button" onclick="pro_show_efb(1)" class="efb pro-version-efb" data-bs-toggle="tooltip" data-bs-placement="top" title="' + (efb_var.text.fieldAvailableInProversion || 'PRO') + '"><i class="efb bi-gem text-light"></i></a></div>' : ''}
        <input type="color" id="selectRangeThumbColorEl" class="efb elEdit form-select efb border-d rounded-4" data-id="${idset}" data-el="rangeThumb" data-type="rangeThumb" data-tag="${valj_efb[indx].type}" value="${thumbClrHex}" name="selectRangeThumbColorEl" ${!isPro ? 'disabled' : ''}></span>`;
    };

    // Range value text color picker (PRO feature)
    const selectRangeValueColorEls = () => {
      let valueClrHex = valj_efb[indx].hasOwnProperty('range_value_color') ? valj_efb[indx].range_value_color : '#212529';
     // const labelText = efb_var.text.rangeValueClr || 'Value Text Color';
      const labelText = (efb_var.text.scolor.replace('%s', efb_var.text.rangeValue) || 'Value Text Color');
      const isPro = (typeof pro_efb !== 'undefined' && pro_efb === true) || (typeof efb_var !== 'undefined' && (efb_var.pro == "1" || efb_var.pro == 1 || efb_var.pro === true));
      return `<span class="efb">
        <label for="selectRangeValueColorEl" class="efb mt-3 efb"><i class="efb bi-fonts fs-7 ${iconMarginGlobal}"></i>${labelText}</label>
        ${!isPro ? '<div class="efb pro-card"><a type="button" onclick="pro_show_efb(1)" class="efb pro-version-efb" data-bs-toggle="tooltip" data-bs-placement="top" title="' + (efb_var.text.fieldAvailableInProversion || 'PRO') + '"><i class="efb bi-gem text-light"></i></a></div>' : ''}
        <input type="color" id="selectRangeValueColorEl" class="efb elEdit form-select efb border-d rounded-4" data-id="${idset}" data-el="rangeValue" data-type="rangeValue" data-tag="${valj_efb[indx].type}" value="${valueClrHex}" name="selectRangeValueColorEl" ${!isPro ? 'disabled' : ''}></span>`;
    };

    // Switch on color picker (PRO feature)
    const selectSwitchOnColorEls = () => {
      let switchOnClrHex = valj_efb[indx].hasOwnProperty('switch_on_color') ? valj_efb[indx].switch_on_color : '#3644d2';
      const labelText = (efb_var.text.scolor.replace('%s', efb_var.text.switchs.replace('%s', efb_var.text.on)) || 'Switch On Color');
      const isPro = (typeof pro_efb !== 'undefined' && pro_efb === true) || (typeof efb_var !== 'undefined' && (efb_var.pro == "1" || efb_var.pro == 1 || efb_var.pro === true));
      return `<span class="efb">
        <label for="selectSwitchOnColorEl" class="efb mt-3 efb"><i class="efb bi-toggle-on fs-7 ${iconMarginGlobal}"></i>${labelText}</label>
        ${!isPro ? '<div class="efb pro-card"><a type="button" onclick="pro_show_efb(1)" class="efb pro-version-efb" data-bs-toggle="tooltip" data-bs-placement="top" title="' + (efb_var.text.fieldAvailableInProversion || 'PRO') + '"><i class="efb bi-gem text-light"></i></a></div>' : ''}
        <input type="color" id="selectSwitchOnColorEl" class="efb elEdit form-select efb border-d rounded-4" data-id="${idset}" data-el="switchOn" data-type="switchOn" data-tag="${valj_efb[indx].type}" value="${switchOnClrHex}" name="selectSwitchOnColorEl" ${!isPro ? 'disabled' : ''}></span>`;
    };

    // Switch off color picker (PRO feature)
    const selectSwitchOffColorEls = () => {
      let switchOffClrHex = valj_efb[indx].hasOwnProperty('switch_off_color') ? valj_efb[indx].switch_off_color : '#9290a7';
      const labelText = (efb_var.text.scolor.replace('%s', efb_var.text.switchs.replace('%s', efb_var.text.off)) || 'Switch Off Color');
      const isPro = (typeof pro_efb !== 'undefined' && pro_efb === true) || (typeof efb_var !== 'undefined' && (efb_var.pro == "1" || efb_var.pro == 1 || efb_var.pro === true));
      return `<span class="efb">
        <label for="selectSwitchOffColorEl" class="efb mt-3 efb"><i class="efb bi-toggle-off fs-7 ${iconMarginGlobal}"></i>${labelText}</label>
        ${!isPro ? '<div class="efb pro-card"><a type="button" onclick="pro_show_efb(1)" class="efb pro-version-efb" data-bs-toggle="tooltip" data-bs-placement="top" title="' + (efb_var.text.fieldAvailableInProversion || 'PRO') + '"><i class="efb bi-gem text-light"></i></a></div>' : ''}
        <input type="color" id="selectSwitchOffColorEl" class="efb elEdit form-select efb border-d rounded-4" data-id="${idset}" data-el="switchOff" data-type="switchOff" data-tag="${valj_efb[indx].type}" value="${switchOffClrHex}" name="selectSwitchOffColorEl" ${!isPro ? 'disabled' : ''}></span>`;
    };

    // Switch handle color picker (PRO feature)
    const selectSwitchHandleColorEls = () => {
      let switchHandleClrHex = valj_efb[indx].hasOwnProperty('switch_handle_color') ? valj_efb[indx].switch_handle_color : '#ffffff';
      const labelText = (efb_var.text.scolor.replace('%s', efb_var.text.switchs.replace('%s', efb_var.text.handle)) || 'Switch Handle Color');
      const isPro = (typeof pro_efb !== 'undefined' && pro_efb === true) || (typeof efb_var !== 'undefined' && (efb_var.pro == "1" || efb_var.pro == 1 || efb_var.pro === true));
      return `<span class="efb">
        <label for="selectSwitchHandleColorEl" class="efb mt-3 efb"><i class="efb bi-circle-fill fs-7 ${iconMarginGlobal}"></i>${labelText}</label>
        ${!isPro ? '<div class="efb pro-card"><a type="button" onclick="pro_show_efb(1)" class="efb pro-version-efb" data-bs-toggle="tooltip" data-bs-placement="top" title="' + (efb_var.text.fieldAvailableInProversion || 'PRO') + '"><i class="efb bi-gem text-light"></i></a></div>' : ''}
        <input type="color" id="selectSwitchHandleColorEl" class="efb elEdit form-select efb border-d rounded-4" data-id="${idset}" data-el="switchHandle" data-type="switchHandle" data-tag="${valj_efb[indx].type}" value="${switchHandleClrHex}" name="selectSwitchHandleColorEl" ${!isPro ? 'disabled' : ''}></span>`;
    };

      const thankYouTypeEls = `
      <label for="thankYouTypeEl" class="efb mt-3 bi-card-heading mx-0 mb-2 fs-6 form-text border-secondary  border-bottom text-secondary">${efb_var.text.landingTnx}</label>
                        <select  data-id="thankYouTypeEl" class="efb elEdit form-select efb border-d rounded-4"  id="thankYouTypeEl"  data-tag="${valj_efb[0].thank_you}">
                        <option value="rdrct" ${ valj_efb[0].thank_you == 'rdrct' ? `selected` : ''}>${efb_var.text.redirectPage}</option>
                        <option value="msg" ${ valj_efb[0].thank_you == 'msg' ? `selected` : ''}>${efb_var.text.thankYouMessage}</option>
                        </select>`;

    const thankYouredirectEls = `<div id="tnxrdrct" class="efb tnxrdrct my-1 ${ valj_efb[0].thank_you == 'rdrct'? 'd-block' :'d-none' }">
    ${pro_efb==true ?"":funProEfb()}
    <label for="thankYouredirectEl" class="efb form-label mt-2 mb-1 efb">${efb_var.text.redirectPage} <i class="efb bi-patch-question fs-7 text-success pointer-efb" onclick="Link_emsFormBuilder('redirectPage')"> </i></label>
    <input type="url" data-id="thankYouredirectEl" class="efb elEdit text-muted form-control h-d-efb border-d rounded-4  mb-1 efb" placeholder="${efb_var.text.url}" id="thankYouredirectEl" required value="${ valj_efb[0].hasOwnProperty('rePage') ? valj_efb[0].rePage.replace(/(@efb@)+/g, '/') : ''}"></div>`
    const paymentGetWayEls =()=>{
      return`<label for="paymentGetWayEl" class="efb mt-3 efb"><i class="efb bi-wallet-fill fs-7 ${iconMarginGlobal}"></i>${efb_var.text.paymentGateway}</label>
      <select  data-id="${idset}" class="efb elEdit form-select efb border-d rounded-4"  id="paymentGetWayEl"  data-tag="${valj_efb[0].type}">
          <option value="stripe" selected>${efb_var.text.stripe}</option>
      </select>`;
    }

     const currencyPersianPayEls= `<p for="currencyTypeEl" class="efb text-labelEfb fs-5 mt-3 efb"><i class="efb bi-cash fs-7 ${iconMarginGlobal}"></i>${efb_var.text.currency}: تومان</p>
      `;

    const labelPostionEls = `<div class="efb efb-desktop-settings-efb ${deskHideEfb}">
    <div class="efb row efb">
    <label for="labelPostionEl" class="efb  mt-3 col-12"><i class="efb bi-arrows-angle-contract fs-7 ${iconMarginGlobal}"></i>${efb_var.text.slabelPosition.replace('%s', '') || efb_var.text.labelPostion}</label>
      <div class="efb  btn-group btn-group-toggle col-12 " data-toggle="buttons" data-id="${idset}"  id="labelPostionEl">
        <label class="efb  ntb btn-primary bi-chevron-bar-down ${valj_efb[indx].label_position && valj_efb[indx].label_position == 'up' ? `active` : ''}" onclick="funSetPosElEfb('${idset}','up')">
          <input type="radio" name="options" class="efb  opButtonEfb elEdit "   data-id="${idset}"  id="labelPostionEl" value="up" >${efb_var.text.up}</label>
        <span class="efb  border-right border border-light "></span>
        <label class="efb  ntb btn-primary bi-chevron-bar-right ${valj_efb[indx].label_position && valj_efb[indx].label_position == 'beside' ? `active` : ''}" onclick="funSetPosElEfb('${idset}','besie')">
          <input type="radio" name="options" class="efb  opButtonEfb elEdit" data-id="${idset}"  id="labelPostionEl" value="beside"> ${efb_var.text.beside}
        </label>
      </div></div></div>`;

    const widthEls = `<div class="efb efb-desktop-settings-efb ${deskHideEfb}">
      <label for="sizeEl" class="efb  mt-3"><i class="efb bi-arrow-left-right fs-7 ${iconMarginGlobal}"></i>${efb_var.text.swidth.replace('%s', '') || efb_var.text.width}</label>
      <select  data-id="${idset}" class="efb  rounded-4 elEdit form-select"  id="sizeEl" >
          <option value="8" ${valj_efb[indx].size == 8.3 ? `selected` : ''}>8%</option>
          <option value="17" ${valj_efb[indx].size == 17 ? `selected` : ''}>17%</option>
          <option value="25" ${valj_efb[indx].size == 25 ? `selected` : ''}>25%</option>
          <option value="33" ${valj_efb[indx].size == 33 ? `selected` : ''}>33%</option>
          <option value="42" ${valj_efb[indx].size == 42 ? `selected` : ''}>42%</option>
          <option value="50" ${valj_efb[indx].size == 50 ? `selected` : ''}>50%</option>
          <option value="58" ${valj_efb[indx].size == 58 ? `selected` : ''}>58%</option>
          <option value="67" ${valj_efb[indx].size == 67 ? `selected` : ''}>67%</option>
          <option value="75" ${valj_efb[indx].size == 75 ? `selected` : ''}>75%</option>
          <option value="83" ${valj_efb[indx].size == 80 || valj_efb[indx].size == 83 ? `selected` : ''} >83%</option>
          <option value="92" ${valj_efb[indx].size == 92 ? `selected` : ''} >92%</option>
          <option value="100" ${valj_efb[indx].hasOwnProperty('size')==false || valj_efb[indx].size == 100 ? `selected` : ''} >100%</option>
      </select></div>
      `
    const mobileWidthEls = `<div class="efb efb-mobile-settings-efb ${mobHideEfb}">
      <label for="mobileSizeEl" class="efb  mt-3"><i class="efb bi-phone fs-7 ${iconMarginGlobal}"></i>${efb_var.text.swidth.replace('%s' , efb_var.text.mobile) || 'Mobile Width!'}</label>
      <select  data-id="${idset}" class="efb  rounded-4 elEdit form-select"  id="mobileSizeEl" >
          <option value="8" ${valj_efb[indx].mobile_size == 8 ? `selected` : ''}>8%</option>
          <option value="17" ${valj_efb[indx].mobile_size == 17 ? `selected` : ''}>17%</option>
          <option value="25" ${valj_efb[indx].mobile_size == 25 ? `selected` : ''}>25%</option>
          <option value="33" ${valj_efb[indx].mobile_size == 33 ? `selected` : ''}>33%</option>
          <option value="42" ${valj_efb[indx].mobile_size == 42 ? `selected` : ''}>42%</option>
          <option value="50" ${valj_efb[indx].mobile_size == 50 ? `selected` : ''}>50%</option>
          <option value="58" ${valj_efb[indx].mobile_size == 58 ? `selected` : ''}>58%</option>
          <option value="67" ${valj_efb[indx].mobile_size == 67 ? `selected` : ''}>67%</option>
          <option value="75" ${valj_efb[indx].mobile_size == 75 ? `selected` : ''}>75%</option>
          <option value="83" ${valj_efb[indx].mobile_size == 83 ? `selected` : ''}>83%</option>
          <option value="92" ${valj_efb[indx].mobile_size == 92 ? `selected` : ''}>92%</option>
          <option value="100" ${!valj_efb[indx].hasOwnProperty('mobile_size') || valj_efb[indx].mobile_size == 100 ? `selected` : ''}>100%</option>
      </select></div>
      `
    const mobileLabelPostionEls = `<div class="efb efb-mobile-settings-efb ${mobHideEfb}">
    <div class="efb row efb">
    <label for="mobileLabelPostionEl" class="efb  mt-3 col-12"><i class="efb bi-phone fs-7 ${iconMarginGlobal}"></i>${efb_var.text.slabelPosition.replace('%s', efb_var.text.mobile) || 'Mobile Label Position'}</label>
    <div class="efb  btn-group btn-group-toggle col-12 " data-toggle="buttons" data-id="${idset}"  id="mobileLabelPostionEl">
        <label class="efb  ntb btn-primary bi-chevron-bar-down ${valj_efb[indx].hasOwnProperty('mobile_label_position') && valj_efb[indx].mobile_label_position == 'up' ? `active` : (!valj_efb[indx].hasOwnProperty('mobile_label_position') ? `active` : '')}" onclick="funSetMobilePosElEfb('${idset}','up')">
            <input type="radio" name="mobile_pos_options" class="efb  opButtonEfb elEdit "  data-id="${idset}"  id="mobileLabelPostionEl" value="up" >${efb_var.text.up}</label>
        <span class="efb  border-right border border-light "></span>
        <label class="efb  ntb btn-primary bi-chevron-bar-right ${valj_efb[indx].hasOwnProperty('mobile_label_position') && valj_efb[indx].mobile_label_position == 'beside' ? `active` : ''}" onclick="funSetMobilePosElEfb('${idset}','beside')">
            <input type="radio" name="mobile_pos_options" class="efb  opButtonEfb elEdit" data-id="${idset}"  id="mobileLabelPostionEl" value="beside"> ${efb_var.text.beside}
        </label>
    </div></div></div>`;

    const mobileLabelFontSizeEls = `<div class="efb efb-mobile-settings-efb ${mobHideEfb}">
      <label for="mobileLabelFontSizeEl" class="efb mt-3 efb"><i class="efb bi-phone fs-7 ${iconMarginGlobal}"></i>${efb_var.text.slabelSize.replace('%s', efb_var.text.mobile) || 'Mobile Label size'}</label>
      <select  data-id="${idset}" class="efb elEdit form-select efb border-d rounded-4"  id="mobileLabelFontSizeEl"  data-tag="${valj_efb[indx].type}">
          <option value="fs-6" ${ valj_efb[indx].hasOwnProperty('mobile_label_text_size') && valj_efb[indx].mobile_label_text_size == 'fs-6' ? `selected` : (!valj_efb[indx].hasOwnProperty('mobile_label_text_size') ? `selected` : '')}>${efb_var.text.default}</option>
          <option value="fs-7" ${ valj_efb[indx].hasOwnProperty('mobile_label_text_size') && valj_efb[indx].mobile_label_text_size == 'fs-7' ? `selected` : ''}>${efb_var.text.small}</option>
          <option value="fs-5" ${ valj_efb[indx].hasOwnProperty('mobile_label_text_size') && valj_efb[indx].mobile_label_text_size == 'fs-5' ? `selected` : ''} >${efb_var.text.large}</option>
          <option value="fs-4" ${ valj_efb[indx].hasOwnProperty('mobile_label_text_size') && valj_efb[indx].mobile_label_text_size == 'fs-4' ? `selected` : ''} >${efb_var.text.xlarge}</option>
          <option value="fs-3" ${ valj_efb[indx].hasOwnProperty('mobile_label_text_size') && valj_efb[indx].mobile_label_text_size == 'fs-3' ? `selected` : ''} >${efb_var.text.xxlarge}</option>
      </select></div>`;

    const MobileElementAlignEls = (side, indx, idset) => {
      const _mobHide = typeof currentViewEfb === 'undefined' || currentViewEfb !== 'mobile' ? 'd-none' : '';
      const left = side == 'label' ? 'txt-left' : 'justify-content-start'
      const right = side == 'label' ? 'txt-right' : 'justify-content-end'
      const center = side == 'label' ? 'txt-center' : 'justify-content-center'
      const propName = side == 'label' ? 'mobile_label_align' : 'mobile_message_align'
      let value = valj_efb[indx].hasOwnProperty(propName) ? valj_efb[indx][propName] : (side == 'label' ? valj_efb[indx].label_align : valj_efb[indx].message_align);
      const labText = side == 'label' ? (efb_var.text.slabelAlign.replace('%s', efb_var.text.mobile) || 'Mobile Label | Align') : (efb_var.text.sdescAlign.replace('%s', efb_var.text.mobile) || 'Mobile Description | Align')
      return `<div class="efb efb-mobile-settings-efb ${_mobHide}">
      <div class="efb  row">
      <label for="MobileElementAlignEl" class="efb  mt-3 col-12"><i class="efb bi-phone fs-7 ${iconMarginGlobal}"></i>${labText}</label>
      <div class="efb  btn-group btn-group-toggle col-12 " data-toggle="buttons" data-side="${side}" data-id="${idset}"  id="MobileElementAlignEl">
          <label class="efb ntb btn-primary ${value == left ? `active` : ''}" onclick="funSetMobileAlignElEfb('${idset}','${left}','${side}')"><i class="efb bi-align-start fs-7 ${iconMarginGlobal}"></i>
              <input type="radio" name="mobile_align_options" class="efb  opButtonEfb elEdit "  data-id="${idset}"  id="MobileElementAlignEl" value="left" >${efb_var.text.left}</label>
          <span class="efb border-right border border-light "></span>
          <label class="efb ntb btn-primary ${value == center ? `active` : ''}" onclick="funSetMobileAlignElEfb('${idset}','${center}','${side}')"><i class="efb bi-align-center fs-7 ${iconMarginGlobal}"></i>
              <input type="radio" name="mobile_align_options" class="efb opButtonEfb elEdit" data-id="${idset}"  id="MobileElementAlignEl" value="center">${efb_var.text.center}</label>
          <span class="efb border-right border border-light "></span>
          <label class="efb ntb btn-primary ${value == right ? `active` : ''}" onclick="funSetMobileAlignElEfb('${idset}','${right}','${side}')"><i class="efb bi-align-end fs-7 ${iconMarginGlobal}"></i>
              <input type="radio" name="mobile_align_options" class="efb  opButtonEfb elEdit" data-id="${idset}"  id="MobileElementAlignEl" value="right">${efb_var.text.right}</label>
      </div></div></div>`;
    }

    const classesEls = `
      <label for="cssClasses" class="efb  mt-3"><i class="efb bi-journal-code fs-7 ${iconMarginGlobal}"></i>${efb_var.text.cSSClasses}</label>
      <input type="text"  data-id="${idset}" class="efb  elEdit text-muted form-control border-d rounded-4 efb mb-3 mb-1" id="classesEl" placeholder="${efb_var.text.cSSClasses}"  ${valj_efb[indx].classes && valj_efb[indx].classes.length > 1 ? `value="${valj_efb[indx].classes}"` : ''}>
      `
    const valueEls = `
    <label for="valueEl" class="efb  mt-3"><i class="efb bi-cursor-text fs-7 ${iconMarginGlobal}"></i>${efb_var.text.value}</label>
      <input type="${valj_efb[indx].type!="range" ? "text" :'number' }"  data-id="${idset}" class="efb elEdit text-muted form-control border-d rounded-4 efb mb-3" data-tag="${valj_efb[indx].type}" id="valueEl" placeholder="${efb_var.text.defaultValue}" ${valj_efb[indx].value && valj_efb[indx].value.length > 1 ? `value="${valj_efb[indx].value}"` : ''}>
      `
    const valueTextereaEls = `
    <label for="valueEl" class="efb  mt-3"><i class="efb bi-cursor-text fs-7 ${iconMarginGlobal}"></i>${efb_var.text.value}</label>
      <textarea type="text"  data-id="${idset}" class="efb elEdit text-muted form-control border-d rounded-4 efb mb-3" data-tag="${valj_efb[indx].type}" id="valueEl" placeholder="${efb_var.text.defaultValue}" ${valj_efb[indx].value && valj_efb[indx].value.length > 1 ? `value="${valj_efb[indx].value}"` : ''}  rows="3"></textarea>
      `

    const placeholderEls = `
      <label for="placeholderEl" class="efb  mt-3"><i class="efb bi-patch-exclamation fs-7 ${iconMarginGlobal}"></i>${efb_var.text.placeholder}</label>
      <input type="text"  data-id="${idset}" class="efb  elEdit form-control text-muted border-d rounded-4 h-d-efb mb-1"id="placeholderEl" placeholder="${efb_var.text.placeholder}" ${valj_efb[indx].placeholder && valj_efb[indx].placeholder.length > 1 ? `value="${valj_efb[indx].placeholder}"` : ''}>
      `

    const iconEls = (side) => {
      let icon = "";
      let t = ""
      let iset ="";

      if (side == "Next") {iset=idset=side+"_"; icon = valj_efb[0].button_Next_icon; t = efb_var.text.next; }
      else if (side == "Previous") {iset=idset=side+"_"; icon = valj_efb[0].button_Previous_icon; t = efb_var.text.previous }
      else if ( side =='tnx') {

        if(valj_efb[0].thank_you_message.hasOwnProperty('icon')){
          iset=idset=side="DoneIconEfb"; icon=valj_efb[0].thank_you_message.icon; t=`${efb_var.text.thankYou}`;
        }else{
          return '<!-- Icon not exist for Done message-->';
        }
      }else {
       idset != "button_group" ? iset=idset=valj_efb[indx].id_: iset=idset="button_group_"
        if(isNumericEfb(iset))idset=iset="step-"+iset;
        icon = valj_efb[indx].icon }
      let list =`<tr class="efb efblist text-d" data-id="${iset}" data-name="bi-XXX" data-row="-2" data-state="0" data-visible="1">
      <th scope="row" class="efb bi-XXXXX"></th>
      <td>None</td>
     </tr>`
      const shouldCapitalize = ['en', 'en_US', 'en_GB', 'de', 'de_DE', 'fr', 'fr_FR', 'es', 'es_ES', 'it', 'it_IT', 'pt', 'pt_BR', 'nl', 'nl_NL'].some(lang => efb_var.wp_lan?.startsWith(lang.split('_')[0]));
      const capitalizeWords = (str) => shouldCapitalize ? str.replace(/\b\w/g, c => c.toUpperCase()) : str;
      bootstrap_icons.forEach((e,key )=> {
        const v= capitalizeWords(e.replace(/-/g, ' '));
        list+=`<tr class="efb efblist text-d" data-id="${iset}" data-name="bi-${e}" data-row="${key}" data-state="0" data-visible="1">
        <th scope="row" class="efb bi-${e}"></th>
        <td>${v}</td>
      </tr>`
      });
      let iNo =''
      if (icon.length>1){
         iNo =bootstrap_icons.findIndex(x=>x==icon.replace('bi-',''));
      }

      return `
      <div class="efb ${ side!="DoneIconEfb"? '' :`tnxmsg mt-1 ${valj_efb[0].thank_you=="msg" ? 'd-block' :'d-none'}` }"> <label for="iconEl" class="efb form-label mt-2 mb-0" id="DoneIconEfb"><i class="efb bi-heptagon fs-7 ${iconMarginGlobal}"></i>${t} ${efb_var.text.icon} </label>
          <div class="efb  listSelect my-2">
            <div class="efb  efblist mx-1  p-2 inplist  h-d-efb elEdit border efb border-d rounded-4 bi-chevron-down" id="iconEl"
            data-id="${iset}" data-idset="${idset}" data-side="${side}"  data-no="1" data-parent="1" data-iconset="${iNo}"
            data-select="">${icon=="" ? efb_var.text.selectOption :icon!='bi-undefined'? `<i class="efb ${icon} fs-5"></i>` :'None'}</div>
            <div class="efb  efblist mx-1  listContent d-none rounded-bottom  bg-light border" data-id="${iset}" data-list="${iset}">
            <table class="efb  table ${iset}">
                    <thead class="efb  efblist">
                      <tr><div class="efb  searchSection efblist  p-2 bg-light">
                        <!--  <i class="efb  efblist  searchIcon  bi-search text-primary "></i> -->
                          <input type="text" class="efb  efblist search searchBox my-1 col-12 rounded " data-id="${iset}" data-tag="search" placeholder="🔍 ${efb_var.text.search}" onkeyup="FunSearchTableEfb('${iset}')">
                        </div></tr>
                    </thead> <tbody class="efb bg-white">
                    ${list}
                    </tbody></table>
            </div>
          </div>
      </div>
        `
    }

    const smsContentEls=(type)=>{

      if(type=="WeRecivedUrM"){
        if(valj_efb[0].hasOwnProperty('sms_msg_recived_usr')){
           value = text_nr_efb(valj_efb[0].sms_msg_recived_usr,0) }else{ value = efb_var.text.WeRecivedUrM + `\n ${efb_var.text.trackNo}: [confirmation_code]\n${efb_var.text.url}: [link_response]`};
      }else if(type == 'responsedMessage'){
        if( valj_efb[0].hasOwnProperty('sms_msg_responsed_noti')){value = text_nr_efb(valj_efb[0].sms_msg_responsed_noti,0)}else{value =efb_var.text.newResponse + `\n ${efb_var.text.trackNo}: [confirmation_code]\n${efb_var.text.url}: [link_response]`};
      }else if (type == "newMessageReceived"){
      if(valj_efb[0].hasOwnProperty('sms_msg_new_noti')) { value =text_nr_efb(valj_efb[0].sms_msg_new_noti,0) }else{
          value = efb_var.text.newMessageReceived + `\n ${efb_var.text.trackNo}: [confirmation_code]\n ${efb_var.text.url}: [link_response]`};
      }

      const disable = valj_efb[0].hasOwnProperty('smsnoti') && Number(valj_efb[0].smsnoti) == 1 ? '' : 'disabled d-none';
      const content =`
      <div class="efb smsmsg ${disable}">
      <textarea type="text" data-id="${type}" class="efb elEdit text-muted form-control h-d-efb border-d rounded-4  mb-1 efb  sms-efb" placeholder="${value}" id="smsContentEl" required >${value}</textarea>
      </div>
      `
      return content;
    }

    const smsAdminsPhoneNoEls =()=>{
      let value = valj_efb[0].hasOwnProperty('sms_admins_phone_no') ? valj_efb[0].sms_admins_phone_no : '';
      const disable = valj_efb[0].hasOwnProperty('smsnoti') && Number(valj_efb[0].smsnoti) == 1 ? '' : 'disabled d-none';
      const content =`
      <div class="efb smsmsg ${disable}">
      <label for="smsAdminsPhoneNoEl" class="efb form-label mt-2 mb-1 efb">${efb_var.text.sms_admn_no}</label>
      <input type="text" data-id="smsAdminsPhoneNoEl" class="efb elEdit text-muted form-control h-d-efb border-d rounded-4  mb-1 efb sms-efb" placeholder="+11234567890, +11234567891" id="smsAdminsPhoneNoEl" required value="${value}" >
      </div>
      `
      return content;
    }

    const telegramContentEls=(type)=>{
      let value = '';

      if(type == 'responsedMessage'){
        if( valj_efb[0].hasOwnProperty('telegram_msg_responsed_noti')){value = text_nr_efb(valj_efb[0].telegram_msg_responsed_noti,0)}else{value =efb_var.text.newResponse + `\n ${efb_var.text.trackNo}: [confirmation_code]\n${efb_var.text.url}: [link_response]`};
      }else if (type == "newMessageReceived"){
      if(valj_efb[0].hasOwnProperty('telegram_msg_new_noti')) { value =text_nr_efb(valj_efb[0].telegram_msg_new_noti,0) }else{
          value = efb_var.text.newMessageReceived + `\n ${efb_var.text.trackNo}: [confirmation_code]\n ${efb_var.text.url}: [link_response]`};
      }

      const disable = valj_efb[0].hasOwnProperty('telegramnoti') && Number(valj_efb[0].telegramnoti) == 1 ? '' : 'disabled d-none';
      const content =`
      <div class="efb telegrammsg ${disable}">
      <textarea type="text" data-id="${type}" class="efb elEdit text-muted form-control h-d-efb border-d rounded-4  mb-1 efb  telegram-efb" placeholder="${value}" id="telegramContentEl" required >${value}</textarea>
      </div>
      `
      return content;
    }

    const fileSizeMaxEls =()=>{
      const file_size = valj_efb[indx].hasOwnProperty('max_fsize') ? valj_efb[indx].max_fsize : 8;
      return`
      <div class="efb  mt-3">
      <label for="fileSizeMaxEl" class="efb  mt-3"><i class="efb bi-file-earmark-medical fs-7 ${iconMarginGlobal}"></i>${efb_var.text.maxfs} <small>(MB)</small> <i class="efb bi-patch-question fs-7 text-success pointer-efb" onclick="Link_emsFormBuilder('file_size')"> </i></label>

      <input type="number" min="1" max="300" data-id="${idset}" class="efb  elEdit form-control text-muted border-d rounded-4 h-d-efb mb-1 efb" placeholder=""${efb_var.text.exDot} 8" id="fileSizeMaxEl" required value="${file_size}">
      </div>
      `}

    const fileTypeEls = `
          <label for="fileTypeEl" class="efb  mt-3"><i class="efb bi-file-earmark-medical fs-7 ${iconMarginGlobal}"></i>${efb_var.text.fileType}</label>
          <select  data-id="${idset}" class="efb  elEdit form-select border-d rounded-4"  id="fileTypeEl" data-tag="${valj_efb[indx].type}">
          <option value="allformat" ${!valj_efb[indx].hasOwnProperty('file') || valj_efb[indx].file == 'allformat' ? `selected` : ''} >${efb_var.text.allformat}</option>
          <option value="document" ${valj_efb[indx].hasOwnProperty('file') && valj_efb[indx].file == 'document' ? `selected` : ''} >${efb_var.text.documents}</option>
          <option value="image" ${valj_efb[indx].hasOwnProperty('file') && valj_efb[indx].file == 'image' ? `selected` : ''}>${efb_var.text.image}</option>
          <option value="media" ${valj_efb[indx].hasOwnProperty('file') && valj_efb[indx].file == 'media' ? `selected` : ''} >${efb_var.text.media}</option>
          <option value="zip" ${valj_efb[indx].hasOwnProperty('file') && valj_efb[indx].file == 'zip' ? `selected` : ''} >${efb_var.text.zip}</option>
          ${ valj_efb[indx].type=='dadfile' ? `<option value="customize" ${valj_efb[indx].hasOwnProperty('file') && valj_efb[indx].file == 'customize' ? `selected` : ''} >${efb_var.text.cstm_rd}</option>` :''}
          </select>
      `

    const fileCustomizeTypleEls =()=>{
      let value =  'jpg, png, pdf';
      let show = 'd-none';
      if(valj_efb[indx].file=="customize"){
        value = valj_efb[indx].file_ctype;
        show = 'd-block';
      }

      return`
      <div class="efb mt-3 ${show}" id="fileCustomizeTypleEls">
      <label for="fileCustomizeTypleEl" class="efb  mt-3"><i class="efb bi-file-earmark-medical fs-7 ${iconMarginGlobal}"></i>${efb_var.text.file_cstm}</label>
      <input type="text" data-id="${idset}" class="efb  elEdit form-control text-muted border-d rounded-4 h-d-efb mb-1 efb" placeholder="${efb_var.text.exDot} jpg, png, pdf" id="fileCustomizeTypleEl" required value="${value}">
      </div>
      `

    }

    const selectColorEls = (forEl ,f) => {
      let t = ''
      let color = '';
      let hex=''
      let cls="";
      if (forEl == 'icon') {
        color = valj_efb[indx].icon_color;
        t = efb_var.text.icon;
        if(color!="") hex=ColorNameToHexEfbOfElEfb(color.slice(5),indx,'icon')
      } else if (forEl == 'description') {
        color = valj_efb[indx].message_text_color;
        t = efb_var.text.description
        if(color!="") hex=ColorNameToHexEfbOfElEfb(color.slice(5),indx,'description')
      } else if (forEl == 'label') {
        color = valj_efb[indx].label_text_color;
        t = efb_var.text.label
        if(color!="") hex=ColorNameToHexEfbOfElEfb(color.slice(5),indx,'label')
      } else if (forEl == "el") {
        color = valj_efb[indx].el_text_color;
        t = efb_var.text.field
        if(color!="") hex=ColorNameToHexEfbOfElEfb(color.slice(5),indx,'el')
      }
      else if (forEl == "clrdoniconEfb") {
        color = valj_efb[0].hasOwnProperty("clrdoniconEfb") ? valj_efb[0].clrdoniconEfb :"#ff4b93" ;
        t = efb_var.text.icon
        hex = color;
        if(color!="" && color.includes('#')==false)  hex=ColorNameToHexEfbOfElEfb(color.slice(5),indx,'el')
        cls="tnxmsg";
      }
      else if (forEl == "clrdoneMessageEfb") {
        color = valj_efb[0].hasOwnProperty("clrdoneMessageEfb") ? valj_efb[0].clrdoneMessageEfb :"#000000";
        t = efb_var.text.message
        cls="tnxmsg";
        hex = color;
        if(color!="" && color.includes('#')==false)  hex=ColorNameToHexEfbOfElEfb(color.slice(5),indx,'el')
      }
      else if (forEl == "clrdoneTitleEfb") {
        color = valj_efb[0].hasOwnProperty("clrdoneTitleEfb")? valj_efb[0].clrdoneTitleEfb :"#000000";

        t = efb_var.text.title
        hex = color;
        if(color!="" && color.includes('#')==false) hex=ColorNameToHexEfbOfElEfb(color.slice(5),indx,'el')
        cls="tnxmsg";
      } else if (forEl == "progessbar"){
        color = valj_efb[0].hasOwnProperty("prg_bar_color")==true? valj_efb[0].prg_bar_color :"#4636f1";

         t = efb_var.text.pgbar
         hex = color;
       if(color!="" && color.includes('#')==false){

         hex=ColorNameToHexEfbOfElEfb(color.slice(4),indx,'btn')}

      } else if (forEl == "btnStripe" || forEl == "btnPerisa"){

      }
      addColorTolistEfb(hex);
      return `<span class="efb ${cls}"> <label for="selectColorEl" class="efb mt-3 efb"><i class="efb bi-paint-bucket fs-7 ${iconMarginGlobal}"></i>${t} ${efb_var.text.clr}</label>
      <input type="color" id="selectColorEl" class="efb elEdit form-select efb border-d rounded-4" data-id="${idset}" data-el="${forEl}" data-type="${f}"  data-tag="${valj_efb[indx].type}" value="${hex!=''?hex:'#fff000'}" name="selectColorEl"  id="${idset}" ></span>
      `
    }

    const selectMultiSelectEls = `<label for="labelEl" class="efb form-label mt-2 mb-1 efb">${efb_var.text.maxSelect}</label>
    <input type="number"  data-id="${idset}" class="efb  elEdit form-control text-muted border-d rounded-4 h-d-efb mb-1"  placeholder="${efb_var.text.maxSelect}" id="selectMultiSelectMaxEl"  value="${valj_efb[indx].maxSelect ? valj_efb[indx].maxSelect : '2'}" >
    <label for="labelEl" class="efb form-label mt-2 mb-1 efb">${efb_var.text.minSelect}</label>
    <input type="number"  data-id="${idset}" class="efb  elEdit form-control text-muted border-d rounded-4 h-d-efb mb-1"  placeholder="${efb_var.text.minSelect}" id="selectMultiSelectMinEl"  value="${valj_efb[indx].minSelect ? valj_efb[indx].minSelect : '0'}" >`

    switch (el.dataset.tag) {
      case 'email':
      case 'text':
      case 'password':
      case 'tel':
      case 'number':
      case 'url':
      case "textarea":
      case 'pdate':
      case 'ardate':
      case 'mobile':
      case 'prcfld':
        body = `
                <div class="efb  mb-3">
                <!--  not   advanced-->
                ${Nadvanced}
                ${placeholderEls}
                ${el.dataset.tag == "mobile" ? smsEnableEls : ''}
                ${el.dataset.tag == "email" ? emailEls : ''}
                ${el.dataset.tag == "mobile" ? ElcountriesListSelections(idset,indx) : ''}
                <!--  not   advanced-->
                <div class="efb  d-grid gap-2">
                  <button class="efb btn btn-outline-light mt-3" id="advanced_collapse" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAdvanced" aria-expanded="true" aria-controls="collapseAdvanced">
                        <i class="efb  bi-arrow-down-circle-fill me-1" id="advanced_collapse_id"></i>${efb_var.text.advanced}
                    </button>
                </div>
                <div class="efb mb-3 mt-3 collapse show" id="collapseAdvanced">
                        <div class="efb  mb-3 px-3 row">

                        ${labelFontSizeEls}
    ${mobileLabelFontSizeEls}
                        ${selectColorEls('label','text')}
                        ${selectColorEls('description','text')}
                        ${selectColorEls('el','text')}
                        ${selectBorderColorEls('element',indx,idset)}
                        ${ el.dataset.tag != "ardate"  && el.dataset.tag != "pdate" && el.dataset.tag != "mobile"  ? miLenEls() :''}
                        ${el.dataset.tag != "textarea" && el.dataset.tag != "ardate"  && el.dataset.tag != "pdate"  && el.dataset.tag != "mobile" ? mLenEls() :''}

                        ${labelPostionEls}
    ${mobileLabelPostionEls}
                        ${ElementAlignEls('label',indx,idset)}
    ${MobileElementAlignEls('label',indx,idset)}
                        ${ElementAlignEls('description',indx,idset)}
    ${MobileElementAlignEls('description',indx,idset)}
                        ${widthEls}
                        ${mobileWidthEls}
                        ${selectHeightEls(idset,indx)}
                        ${cornerEls('',indx,idset)}
                        ${el.dataset.tag != "textarea" ? valueEls : valueTextereaEls}
                        ${classesEls}
                        ${disabledEls}
                        ${hiddenEls}
                        ${showInPublicResultsEls}
                        ${typeof efbActiveAutoFillEls !== 'undefined' ? efbActiveAutoFillEls(indx) : '<!--efb-->'}
                        </div>
                    </div>
                </div><div class="efb  clearfix"></div>
                `
        break;
      case "heading":
        body = `
                <div class="efb  mb-3">
                <!--  not   advanced-->
                ${valueEls}
                ${selectColorEls('el','heading')}
                ${fontSizeEls(idset,indx)}
                ${widthEls}
                ${mobileWidthEls}
                ${classesEls}
                <div class="efb  clearfix"></div>
                `
        break;
      case "link":
        body = `
                <div class="efb  mb-3">
                <!--  not   advanced-->
                ${valueEls}
                ${hrefEls(idset,indx)}
                ${selectColorEls('el','link')}
                ${selectHeightEls(idset,indx)}
                ${widthEls}
                ${mobileWidthEls}
                ${classesEls}
                <div class="efb  clearfix"></div>
                `
        break;
      case "radio":
      case "checkbox":
      case "select":
      case "multiselect":
      case "conturyList":
      case "stateProvince":
      case "cityList":
      case "payCheckbox":
      case "payRadio":
      case "paySelect":
      case "chlCheckBox":
      case "chlRadio":
      case "payMultiselect":
      case "imgRadio":
      case "trmCheckbox":
        const objOptions = valj_efb.filter(obj => {
          return obj.parent === el.id
        })
        let s = el.dataset.tag;
        let o_c = s=="chlRadio" || s=="chlCheckBox" || s=="payRadio" || s=="payCheckbox" || s=="checkbox" || s=="radio" || s=="trmCheckbox"  ? true :false
        s= s=="payCheckbox" || s=="payRadio" || s=="paySelect" || s=="payMultiselect" ? true :false
        const newRndm = Math.random().toString(36).substr(2, 9);
        let opetions = `<!-- options -->`;
        const col = s==true ||  form_type_emsFormBuilder=="smart"  ?'col-md-7':'col-md-12'
        if (objOptions.length > 0) {

          const ftyp=el.dataset.tag.includes("pay") ? 'payment':'';
          opetions=  efb_add_opt_setting(objOptions, el ,s ,newRndm ,ftyp)

        }

        body = `
                <div class="efb  mb-3">
                <!--notAdvanced-->
                ${Nadvanced}
                ${el.dataset.tag=="stateProvince" || el.dataset.tag=='cityList' ? countries_list_el_select(el.dataset.tag,idset,indx):""}
                ${el.dataset.tag=='cityList' ? state_list_el_select('statePovListEl',idset,indx):""}
                ${el.dataset.tag=="stateProvince" || el.dataset.tag=='cityList' ? languageSelectPresentEls:""}
                ${ el.dataset.tag == 'multiselect' ||el.dataset.tag == 'payMultiselect'? selectMultiSelectEls :''}
                <div class="efb m-0 p-0 col-md-12 row">
                <div for="optionListefb" class="efb  col-md-6">${efb_var.text.options}
                <button type="button" id="addOption" onclick="add_option_edit_pro_efb('${el.id.trim()}','${el.dataset.tag.trim()}' ,${valj_efb.length})" data-parent="${el.id}" data-tag="${el.dataset.tag}" data-id="${newRndm}"   class="efb btn efb btn-edit btn-sm elEdit" data-bs-toggle="tooltip" title="${efb_var.text.add}" >
                <i class="efb  bi-plus-circle  text-success"></i>
                  </button>
                </div>
                <div class="efb col-md-6 text-darkb align-self-center text-decoration-underline fs-7 show" id="showAtrEls" onclick="funShowAttrElsEfb(this)">${efb_var.text.shwattr}</div>
                </div>

                <div id="optionListeHeadfb" class="efb  mx-1 col-md-12 row ">
                    <div class="efb  col-md-7 text-capitalize">${efb_var.text.title}</div>

                    ${el.dataset.tag.includes('pay')?`<div class="efb  col-md-3">${efb_var.text.price}</div>`:''}
                </div>

                <div class="efb  mb-3"  id="optionListefb" data-idstate="false">
                 ${opetions}
                </div>
                ${qtyPlcEls}
                <!--notAdvanced-->

                <!--advanced-->
                <div class="efb  d-grid gap-2">
                  <button class="efb btn btn-outline-light mt-3" id="advanced_collapse" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAdvanced" aria-expanded="true" aria-controls="collapseAdvanced">
                    <i class="efb  bi-arrow-down-circle-fill me-1" id="advanced_collapse_id"></i>${efb_var.text.advanced}
                  </button>
                </div>
                <div class="efb mb-3 mt-3 collapse show" id="collapseAdvanced">
                        <div class="efb  mb-3 px-3 row">

                        ${o_c ? optnsStyleEls :''}
                        ${o_c ? selectCheckedColorEls() :''}
                        ${labelFontSizeEls}
    ${mobileLabelFontSizeEls}
                        ${selectColorEls('label','text')}
                        ${selectColorEls('description','text')}
                        ${fun_el_select_in_efb(el.dataset.tag)  ? cornerEls('',indx,idset) : ''}
                        ${fun_el_select_in_efb(el.dataset.tag) ? selectBorderColorEls('element',indx,idset) : ''}
                        ${el.dataset.tag != 'multiselect' && el.dataset.tag != 'payMultiselect' && el.dataset.tag != 'imgRadio'? selectColorEls('el','text') : ''}
                        ${labelPostionEls}
    ${mobileLabelPostionEls}
                        ${ElementAlignEls('label',indx,idset)}
    ${MobileElementAlignEls('label',indx,idset)}
                        ${ElementAlignEls('description',indx,idset)}
    ${MobileElementAlignEls('description',indx,idset)}
                        ${widthEls}
                        ${mobileWidthEls}
                        ${fun_el_select_in_efb(el.dataset.tag) ? selectHeightEls(idset,indx) : ''}
                        ${classesEls}
                        ${disabledEls}
                        ${hiddenEls}
                        ${showInPublicResultsEls}
                        ${typeof efbActiveAutoFillEls !== 'undefined' ? efbActiveAutoFillEls(indx) : '<!--efb-->'}
                        </div>
                    </div>

                </div>
                <div class="efb  clearfix"></div>
                `
        break;
      case "date":
      case "color":
      case "range":
      case "esign":
      case "rating":
      case "switch":

        body = `
        <div class="efb  mb-3">
        <!--  not   advanced-->
        ${Nadvanced}
        <!--  not   advanced-->
        <div class="efb  d-grid gap-2">
            <button class="efb btn btn-outline-light mt-3" id="advanced_collapse" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAdvanced" aria-expanded="true" aria-controls="collapseAdvanced">
              <i class="efb  bi-arrow-down-circle-fill me-1" id="advanced_collapse_id"></i>${efb_var.text.advanced}
            </button>
        </div>
        <div class="efb mb-3 mt-3 collapse show" id="collapseAdvanced">
                <div class="efb  mb-3 px-3 row">

                ${el.dataset.tag == "switch" ?textEls(el.id.trim(),efb_var.text.lson ,'text',valj_efb[indx].on ,'on' ,idset):''}
                ${el.dataset.tag == "switch" ?textEls(el.id.trim(), efb_var.text.lsoff,'text',valj_efb[indx].off,'off',idset):''}
                ${el.dataset.tag == "switch" ? selectSwitchOnColorEls() :''}
                ${el.dataset.tag == "switch" ? selectSwitchOffColorEls() :''}
                ${el.dataset.tag == "switch" ? selectSwitchHandleColorEls() :''}
                ${labelFontSizeEls}
    ${mobileLabelFontSizeEls}
                ${selectColorEls('label','text')}
                ${selectColorEls('description','text')}

                ${el.dataset.tag == 'rating' || el.dataset.tag == 'range'  || el.dataset.tag == 'switch' ? "" : selectBorderColorEls('element',indx,idset)}
                ${labelPostionEls}
    ${mobileLabelPostionEls}
                ${ElementAlignEls('label',indx,idset)}
    ${MobileElementAlignEls('label',indx,idset)}
                ${ElementAlignEls('description',indx,idset)}
    ${MobileElementAlignEls('description',indx,idset)}
                ${el.dataset.tag == "range" || el.dataset.tag == "date" ?miLenEls():''}
                ${el.dataset.tag == "range" || el.dataset.tag == "date" ? mLenEls() :''}
                ${el.dataset.tag == "range" ?valueEls:''}
                ${el.dataset.tag == "range" ? selectRangeThumbColorEls() :''}
                ${el.dataset.tag == "range" ? selectRangeValueColorEls() :''}

                ${el.dataset.tag == 'rating' ? '' : widthEls}
                ${el.dataset.tag == 'rating' ? '' : mobileWidthEls}
                ${el.dataset.tag != 'range' ? selectHeightEls(idset,indx) :''}
                ${el.dataset.tag == 'rating' || el.dataset.tag == 'switch' || el.dataset.tag == 'range' ? '' : cornerEls('',indx,idset)}
                ${ ''}
                ${el.dataset.tag == 'esign' ? iconEls('') : ''}
                ${el.dataset.tag == 'esign' ? btnColorEls(idset,indx) : ''}
                ${el.dataset.tag == 'esign' ? SingleTextEls('',idset,indx) : ''}
                ${disabledEls}
                ${hiddenEls}
                ${showInPublicResultsEls}
                ${typeof efbActiveAutoFillEls !== 'undefined' ? efbActiveAutoFillEls(indx) : '<!--efb-->'}
                </div>
            </div>
        </div><div class="efb  clearfix"></div>
        `
        break;
      case "file":
      case "dadfile":

        body = `
        <div class="efb  mb-3">
        <!--  not   advanced-->
        ${Nadvanced}
        ${fileTypeEls }
        ${el.dataset.tag == 'dadfile' ? fileCustomizeTypleEls() : '<!--efb.app-->'}
        ${fileSizeMaxEls()}
        <!--  not   advanced-->
        <div class="efb  d-grid gap-2">
          <button class="efb btn btn-outline-light mt-3" id="advanced_collapse" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAdvanced" aria-expanded="true" aria-controls="collapseAdvanced">
            <i class="efb  bi-arrow-down-circle-fill me-1" id="advanced_collapse_id"></i>${efb_var.text.advanced}
          </button>
        </div>
        <div class="efb mb-3 mt-3 collapse show" id="collapseAdvanced">
                <div class="efb  mb-3 px-3 row">

                ${labelFontSizeEls}
    ${mobileLabelFontSizeEls}
                ${selectColorEls('label','text')}
                ${selectColorEls('description','text')}
                ${el.dataset.tag == 'dadfile' ? selectColorEls('icon','text') : ''}
                ${el.dataset.tag == 'dadfile' ? btnColorEls(idset,indx) : ''}
                ${selectBorderColorEls('element',indx,idset)}
                ${labelPostionEls}
    ${mobileLabelPostionEls}
                ${ElementAlignEls('label',indx,idset)}
    ${MobileElementAlignEls('label',indx,idset)}
                ${ElementAlignEls('description',indx,idset)}
    ${MobileElementAlignEls('description',indx,idset)}
                ${widthEls}
                ${mobileWidthEls}
                ${selectHeightEls(idset,indx)}
                ${cornerEls("",indx,idset)}
                ${classesEls}
                ${disabledEls}
                ${hiddenEls}
                <!-- select type of file -->
                </div>
            </div>
        </div><div class="efb  clearfix"></div>
        `
        break;
      case "maps":

        body = `
        <div class="efb  mb-3">
        <!--  not   advanced-->
        ${Nadvanced}
        <label for="letEl" class="efb  form-label  mt-2">${efb_var.text.latitude}</label>
        <input type="text" data-id="${idset}" class="efb elEdit text-muted form-control border-d rounded-4 efb h-d-efb mb-1" placeholder="${efb_var.text.exDot} 49.24803870604257" id="letEl" required value="${valj_efb[indx].lat}">
        <label for="lonEl" class="efb  form-label  mt-2">${efb_var.text.longitude}</label>
        <input type="text" data-id="${idset}" class="efb elEdit text-muted form-control border-d rounded-4 efb h-d-efb mb-1" placeholder="${efb_var.text.exDot}  -123.10512829684463" id="lonEl" required value="${valj_efb[indx].lng}">
        <label for="lonEl" class="efb  form-label  mt-2">${efb_var.text.zoom}</label>
        <input type="text" data-id="${idset}" class="efb elEdit text-muted form-control border-d rounded-4 efb h-d-efb mb-1" placeholder="13 " id="zoomMapEl" required value="${valj_efb[indx].zoom}">
        <label for="marksEl" class="efb  form-label  mt-2">${efb_var.text.points.toUpperCase()}
        <i class="efb bi-patch-question fs-7 text-success pointer-efb" onclick="Link_emsFormBuilder('pickupByUser')"> </i>
        </label>
        <input type="text" data-id="${idset}" class="efb elEdit text-muted form-control border-d rounded-4 efb h-d-efb mb-1" placeholder=${efb_var.text.exDot}  1" id="marksEl" required value="${valj_efb[indx].mark}">
        <!--  not   advanced-->
        <div class="efb  d-grid gap-2">
          <button class="efb btn btn-outline-light mt-3" id="advanced_collapse" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAdvanced" aria-expanded="true" aria-controls="collapseAdvanced">
            <i class="efb  bi-arrow-down-circle-fill me-1" id="advanced_collapse_id"></i>${efb_var.text.advanced}
          </button>
        </div>
        <div class="efb mb-3 mt-3 collapse show" id="collapseAdvanced">
                <div class="efb  mb-3 px-3 row">

                ${labelPostionEls}
    ${mobileLabelPostionEls}
                ${ElementAlignEls('label',indx,idset)}
    ${MobileElementAlignEls('label',indx,idset)}
                ${ElementAlignEls('description',indx,idset)}
    ${MobileElementAlignEls('description',indx,idset)}
                ${widthEls}
                ${mobileWidthEls}
                ${labelFontSizeEls}
    ${mobileLabelFontSizeEls}
                ${selectColorEls('label','text')}
                ${selectColorEls('description','text')}
                ${disabledEls}
                ${hiddenEls}
                </div>
            </div>
        </div><div class="efb  clearfix"></div>
        `

        break;
      case "html":
        let valHTML = valj_efb[indx].value.replace(/@!/g,`"`);
         valHTML = valj_efb[indx].value.replace(/@efb@nq#/g,`\n`);

        body = `
        <div class="efb  mb-3">
        <!--  not   advanced-->
        <label for="htmlCodeEl" class="efb  form-label mt-2 mb-1"><i class="efb  bi-code-square fs-7 ${iconMarginGlobal}" ></i>${efb_var.text.code}</label>
        <small class="efb text-info text-danger bg-muted  efb">${efb_var.text.pleaseDoNotAddJsCode}</small>
        <textarea placeholder="${efb_var.text.htmlCode}"
        class="efb elEdit form-control efb  h-d-efb   mb-1"
         data-id="${valj_efb[indx].id_}" id="htmlCodeEl" rows="13" >${valHTML}</textarea>
        </div><div class="efb  clearfix"></div>
        `
        break;
      case "yesNo":
        body = `
        <div class="efb  mb-3">
        <!--  not   advanced-->
        ${Nadvanced}
        <!--  not   advanced-->
        <div class="efb  d-grid gap-2">
          <button class="efb btn btn-outline-light mt-3" id="advanced_collapse" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAdvanced" aria-expanded="true" aria-controls="collapseAdvanced">
            <i class="efb  bi-arrow-down-circle-fill me-1" id="advanced_collapse_id"></i>${efb_var.text.advanced}
          </button>
        </div>
        <div class="efb mb-3 mt-3 collapse show" id="collapseAdvanced">
                <div class="efb  mb-3 px-3 row">
                ${labelFontSizeEls}
    ${mobileLabelFontSizeEls}
                ${selectColorEls('label','text')}
                ${selectColorEls('description','text')}
                ${selectColorEls('el','text')}
                ${btnColorEls(idset,indx)}
                ${labelPostionEls}
    ${mobileLabelPostionEls}
                ${ElementAlignEls('label',indx,idset)}
    ${MobileElementAlignEls('label',indx,idset)}
                ${ElementAlignEls('description',indx,idset)}
    ${MobileElementAlignEls('description',indx,idset)}

                ${widthEls}
                ${mobileWidthEls}
                ${selectHeightEls(idset,indx)}
                ${cornerEls('yesNo',indx,idset)}
                <label for="valueEl" class="efb  mt-3 mb-0"><i class="efb bi-cursor-text fs-7 ${iconMarginGlobal}"></i>${efb_var.text.button1Value}</label>
                <input type="text"  data-id="${idset}" class="efb elEdit border-d rounded-4 text-muted form-control efb mb-3" id="valueEl" data-tag="yesNo" data-no="1" placeholder="${efb_var.text.exDot} ${efb_var.text.yes}" value="${valj_efb[indx].button_1_text}">
                <label for="valueEl" class="efb  mt-0 mb-1"><i class="efb bi-cursor-text fs-7 ${iconMarginGlobal}"></i>${efb_var.text.button2Value}</label>
                <input type="text"  data-id="${idset}" class="efb elEdit border-d rounded-4 text-muted form-control efb mb-3" id="valueEl" data-tag="yesNo" data-no="2" placeholder="${efb_var.text.exDot} ${efb_var.text.no}" value="${valj_efb[indx].button_2_text}">
                ${classesEls}
                ${disabledEls}
                ${hiddenEls}
                ${showInPublicResultsEls}
                 ${typeof efbActiveAutoFillEls !== 'undefined' ? efbActiveAutoFillEls(indx) : '<!--efb-->'}
                </div>
            </div>
        </div><div class="efb  clearfix"></div>
        `
        break;
      case "ttlprc":
        body = `
        <div class="efb  mb-3">
        <!--  not   advanced-->
        ${Nadvanced}
        ${currencyTypeEls(idset)}
        <!--  not   advanced-->
        <div class="efb  d-grid gap-2">
          <button class="efb btn btn-outline-light mt-3" id="advanced_collapse" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAdvanced" aria-expanded="true" aria-controls="collapseAdvanced">
            <i class="efb  bi-arrow-down-circle-fill me-1" id="advanced_collapse_id"></i>${efb_var.text.advanced}
          </button>
        </div>
        <div class="efb mb-3 mt-3 collapse show" id="collapseAdvanced">
                <div class="efb  mb-3 px-3 row">
                ${labelFontSizeEls}
    ${mobileLabelFontSizeEls}
                ${selectColorEls('label','text')}
                ${selectColorEls('description','text')}
                ${selectColorEls('el','text')}

                ${labelPostionEls}
    ${mobileLabelPostionEls}
                ${ElementAlignEls('label',indx,idset)}
    ${MobileElementAlignEls('label',indx,idset)}
                ${ElementAlignEls('description',indx,idset)}
    ${MobileElementAlignEls('description',indx,idset)}

                ${widthEls}
                ${mobileWidthEls}
                ${selectHeightEls(idset,indx)}

                ${classesEls}
                ${hiddenEls}
                </div>
            </div>
        </div><div class="efb  clearfix"></div>
        `
        break;
      case "booking":
        break;
      case "steps":
        idset=Number(idset);
        const logic_steps =idset>1 && false ? logic_section(idset) :"<!--efb-->";
        body = `
        <div class="efb  mb-3">
        <!--  not   advanced-->
        ${labelEls}
        ${desEls}

        </div>
        <!--  not   advanced-->
        <div class="efb  d-grid gap-2">
          <button class="efb btn btn-outline-light mt-3" id="advanced_collapse" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAdvanced" aria-expanded="true" aria-controls="collapseAdvanced">
            <i class="efb  bi-arrow-down-circle-fill me-1" id="advanced_collapse_id"></i>${efb_var.text.advanced}
          </button>
        </div>
        <div class="efb mb-3 mt-3 collapse show" id="collapseAdvanced">
                <div class="efb  mb-3 px-3 row">
                ${iconEls('')}

                ${selectColorEls('label','text')}
                ${selectColorEls('description','text')}
                ${selectColorEls('icon','text')}
                <!-- {classesEls} -->
                </div>
            </div>
        </div>
        ${logic_steps}
        <div class="efb  clearfix"></div>
        `
        break;
      case "buttonNav":

        let content = `
        ${SingleTextEls('',idset,indx)}
        ${iconEls('')}
        ${selectColorEls('el','text')}
        ${selectColorEls('icon','text')}
        ${btnColorEls(idset,indx)}
        ${ElementAlignEls('buttons',indx,idset)}
        ${cornerEls('Next',indx,idset)}
        ${selectHeightEls(idset,indx)}
        `

        if (valj_efb[0].button_state != "single") {
          content = `
             ${SingleTextEls("Previous",idset,indx)}
             ${iconEls("Previous")}
             ${SingleTextEls("Next",idset,indx)}
             ${iconEls("Next")}
             ${selectColorEls('el','text')}
             ${selectColorEls('icon','text')}
             ${btnColorEls(idset,indx)}
             ${ElementAlignEls('buttons',indx,idset)}
             ${cornerEls('Next',indx,idset)}
             ${selectHeightEls(idset,indx)}
             `
        }
        body = `
        <div class="efb  mb-3">
        <!--  not   advanced-->
                    ${content}
        </div>
        `
        break;
      case 'formSet':
        deactive_element_efb();
        body = `
          <label for="formNameEl" class="efb form-label mt-2 mb-1 efb">${efb_var.text.formName}<span class="efb  mx-1 efb text-danger">*</span></label>
           <input type="text"  data-id="${idset}" class="efb elEdit text-muted form-control efb  h-d-efb  mb-1"  placeholder="${efb_var.text.formName}" id="formNameEl" required value="${valj_efb[0].formName}">
          ${trackingCodeEls}
          ${valj_efb[0].type=="payment" ? '<!--efb-->' : captchaEls}
          ${shieldSilentCaptchaEls}
          ${showSIconsEls}
          ${showSprosiEls}
          ${showformLoggedEls}
          ${cardEls}
          ${offLineEls}
          ${adminFormEmailEls}
          ${FormEmailSubjectEls()}
          ${valj_efb[0].type=="form" || valj_efb[0].type=="payment" ?  EmailNotiContainsEls() :'<!--efb-->'}
          ${selectColorEls('progessbar','btn')}

          <!-- sms section -->
          <div class="efb d-grid gap-2">
            <button class="efb btn btn-outline-light mt-3" id="sms_collapse" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSMS" aria-expanded="false" aria-controls="collapseSMS">
              <i class="efb bi-chat-left-dots me-1" id="sms_collapse_id"></i>${efb_var.text.sms}
            </button>
          </div>
          <div class="efb mb-3 mt-3 collapse" id="collapseSMS">
            <div class="efb mb-3 px-3 row">
              ${smsEnableEls}
              ${smsAdminsPhoneNoEls()}
              ${`<span class="efb my-3 fs-7 smsmsg ${valj_efb[0].hasOwnProperty('smsnoti') && Number(valj_efb[0].smsnoti) == 1 ? '' : 'd-none'}">${efb_var.text.messages}</span>`}
              ${smsContentEls('newMessageReceived')}
              ${valj_efb[0].type != "login" && valj_efb[0].type != "register" ? smsContentEls('WeRecivedUrM') : ''}
              ${valj_efb[0].type != "login" && valj_efb[0].type != "register" ? smsContentEls('responsedMessage') : ''}
            </div>
          </div>
        <!-- sms section end -->

          ${(efb_var.addons.hasOwnProperty('AdnTLG') && Number(efb_var.addons.AdnTLG) === 1) ? `
          <!-- telegram section -->
          <div class="efb d-grid gap-2">
            <button class="efb btn btn-outline-light mt-3" id="telegram_collapse" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTelegram" aria-expanded="false" aria-controls="collapseTelegram">
              <i class="efb bi-telegram me-1" id="telegram_collapse_id"></i>${efb_var.text.telegram || 'Telegram'}
            </button>
          </div>
          <div class="efb mb-3 mt-3 collapse" id="collapseTelegram">
            <div class="efb mb-3 px-3 row">
              ${telegramEnableEls}
              ${`<span class="efb my-3 fs-7 telegrammsg ${valj_efb[0].hasOwnProperty('telegramnoti') && Number(valj_efb[0].telegramnoti) == 1 ? '' : 'd-none'}">${efb_var.text.messages || 'Messages'}</span>`}
              ${telegramContentEls('newMessageReceived')}
              ${valj_efb[0].type != "login" && valj_efb[0].type != "register" ? telegramContentEls('responsedMessage') : ''}
            </div>
          </div>
        <!-- telegram section end -->` : '<!-- telegram addon not active -->'}
          <!-- condi section
          <div class="efb d-grid gap-2">
            <button class="efb btn btn-outline-light mt-3" id="login_collapse" type="button" data-bs-toggle="collapse" data-bs-target="#collapseLogic" aria-expanded="false" aria-controls="collapseLogic">
              <i class="efb bi-chat-left-dots me-1" id="sms_collapse_id"></i>${efb_var.text.conlog}
            </button>
          </div>
          <div class="efb mb-3 mt-3 collapse" id="collapseLogic">
            <div class="efb mb-3 px-3 row">
              ${enableConEls}
            </div>
          </div>
          -->
        <!-- condi section  end -->
          <div class="efb  d-grid gap-2">
            <button class="efb btn btn-outline-light mt-3" id="advanced_collapse" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAdvanced" aria-expanded="true" aria-controls="collapseAdvanced">
            <i class="efb  bi-arrow-down-circle-fill me-1" id="advanced_collapse_id"></i>${efb_var.text.advanced}
            </button>
          </div>
          <div class="efb mb-3 mt-3 collapse show" id="collapseAdvanced">
              <div class="efb  mb-3 px-3 row">
          ${thankYouTypeEls}
          ${valj_efb[0].type!="login" ? iconEls('tnx'):''}
          ${valj_efb[0].type!="register" && valj_efb[0].type!="login" ? thankYouMessageDoneEls :''}
          ${valj_efb[0].type!="login" ? thankYouMessageEls :''}
          ${valj_efb[0].type!="register" && valj_efb[0].type!="login"  ? thankYouMessageConfirmationCodeEls :''}
          ${valj_efb[0].type!="login" ?selectColorEls('clrdoneTitleEfb','text'):''}
          ${valj_efb[0].type!="login" ?selectColorEls('clrdoniconEfb','text'):''}
          ${valj_efb[0].type!="login" ?selectColorEls('clrdoneMessageEfb','text'):''}
          ${thankYouredirectEls}
          ${formTypeEls()}
          ${surveyChartTypeEls()}
          ${loadingTypeEls()}
          ${typeof efbActiveAutoFillEls !== 'undefined' ? efbActiveAutoFillEls(0) : '<!--efb-->'}
         <!-- content_colors_setting_efb() -->
          </div>
          </div>
      </div>

      <div class="efb  clearfix"></div>

          `
        break;
      case 'stripe':

        body = `<div class="efb  mb-3">
        <!--  not   advanced-->
          <h2 class="efb  text-muted">${efb_var.text.stripe}</h2>
          ${valj_efb[0].type=="payment" ? currencyTypeEls(idset) :''}
          ${valj_efb[0].type=="payment" ? paymentMethodEls(idset) :''}
        <div class="efb  clearfix"></div>
        </div>`

        break;
        case 'paypal':

          body = `<div class="efb  mb-3">
          <!--  not   advanced-->
            <h2 class="efb  text-muted">${efb_var.text.paypal}</h2>
            ${valj_efb[0].type=="payment" ? currencyPaypalTypeEls(idset) :''}
            ${valj_efb[0].type=="payment" ? paymentMethodEls(idset) :''}
          <div class="efb  clearfix"></div>
          </div>`

        break;
        case 'persiaPay':
          body = `<div class="efb  mb-3">
          <h2 class="efb  text-muted">${efb_var.text.paymentGateway}</h2>
          <!--  not   advanced-->
                ${valj_efb[0].type=="payment" ? currencyPersianPayEls :''}
            ${valj_efb[0].type=="payment" ? paymentPersianPayEls(idset) :''}
          <div class="efb  clearfix"></div>
          </div>`
        break;
        case "table_matrix":
          const obj_r_matrix = valj_efb.filter(obj => {
            return obj.parent === el.id
          })

          const newRndmm = Math.random().toString(36).substr(2, 9);
          let r_matrixs = `<!-- options -->`;

          if (obj_r_matrix.length > 0) {

            for (let ob of obj_r_matrix) {
              let cont = ` <div class="efb  btn-edit-holder newop" id="deleteOption" data-parent_id="${ob.parent}">
                <button type="button" id="deleteOption"  onclick="delete_option_efb('${ob.id_op}')" data-parent="${el.id}" data-tag="${el.dataset.tag}"  data-id="${ob.id_op}" class="efb btn efb btn-edit btn-sm elEdit" data-bs-toggle="tooltip" title="${efb_var.text.delete}">
                    <i class="efb  efb bi-x-lg text-danger"></i>
                </button>
                <button type="button" id="addOption" onclick="add_r_matrix_edit_pro_efb('${el.id.trim()}','${el.dataset.tag.trim()}' ,${valj_efb.length})" data-parent="${el.id}" data-tag="${el.dataset.tag}" data-id="${newRndmm}" class="efb btn efb btn-edit btn-sm elEdit" data-bs-toggle="tooltip" title="${efb_var.text.add}" >
                    <i class="efb  bi-plus-circle  text-success"></i>
                </button>

              </div>`
              r_matrixs += `<div id="${ob.id_op}-gs" class="efb mx-0 col-sm-12 row opt">
              <div id="${ob.id_op}-v" class="efb col-sm-12 mx-0 px-0">
              <input type="text" placeholder="${efb_var.text.name}" id="EditOption"  value="${ob.value}" data-parent="${el.id}" data-op="${el.id}" data-id="${ob.id_op}" data-tag="${el.dataset.tag}" class="efb  col-md-12  text-muted mb-1 fs-6 border-d rounded-4 elEdit">
              ${cont}
              </div>
              </div>`
            }
          }

          body = `
                    <div class="efb  mb-3">
                    <!--notAdvanced-->
                    ${Nadvanced}

                    ${el.dataset.tag == 'multiselect' || el.dataset.tag == 'payMultiselect' ? selectMultiSelectEls : ''}
                    <label for="optionListefb" class="efb  ">${efb_var.text.options}

                    </label>
                    <div id="optionListeHeadfb" class="efb  mx-1 col-md-12 row ">
                        <div class="efb  col-md-7 text-capitalize">${efb_var.text.title}</div>
                    </div>
                    <div class="efb  mb-3" id="optionListefb" data-idstate="false">
                     ${r_matrixs}
                    </div>
                    ${type_field_efb == "radio" ? addOtherslEls : ''}
                    <!--notAdvanced-->

                    <!--advanced-->
                    <div class="efb  d-grid gap-2">
                      <button class="efb btn btn-outline-light mt-3" id="advanced_collapse" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAdvanced" aria-expanded="true" aria-controls="collapseAdvanced">
                        <i class="efb  bi-arrow-down-circle-fill me-1" id="advanced_collapse_id"></i>${efb_var.text.advanced}
                      </button>
                    </div>
                    <div class="efb mb-3 mt-3 collapse show" id="collapseAdvanced">
                            <div class="efb  mb-3 px-3 row">

                            ${labelFontSizeEls}
    ${mobileLabelFontSizeEls}

                            ${labelPostionEls}
    ${mobileLabelPostionEls}
                            ${ElementAlignEls('label',indx,idset)}
    ${MobileElementAlignEls('label',indx,idset)}
                            ${ElementAlignEls('description',indx,idset)}
    ${MobileElementAlignEls('description',indx,idset)}
                            <!-- ${widthEls} -->

                            ${classesEls}
                            ${showInPublicResultsEls}
                            </div>
                        </div>

                    </div>
                    <div class="efb  clearfix"></div>
                    `
          break;
        case "pointr10":
        case "pointr5":

              body = `
                        <div class="efb  mb-3" >
                        <!--  not   advanced-->
                        ${Nadvanced}
                        <!--  not   advanced-->
                        <div class="efb  d-grid gap-2">
                          <button class="efb btn btn-outline-light mt-3" id="advanced_collapse" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAdvanced" aria-expanded="true" aria-controls="collapseAdvanced">
                           <i class="efb  bi-arrow-down-circle-fill me-1" id="advanced_collapse_id"></i>${efb_var.text.advanced}
                          </button>
                        </div>
                        <div class="efb mb-3 mt-3 collapse show" id="collapseAdvanced">
                                <div class="efb  mb-3 px-3 row">
                                ${labelPostionEls}
    ${mobileLabelPostionEls}
                                ${ElementAlignEls('label',indx,idset)}
    ${MobileElementAlignEls('label',indx,idset)}
                                ${ElementAlignEls('description',indx,idset)}
    ${MobileElementAlignEls('description',indx,idset)}
                                <!-- ${widthEls} -->
                                ${classesEls}
                                ${showInPublicResultsEls}
                                </div>
                            </div>
                        </div><div class="efb  clearfix"></div>
                        `
              break;

    }

   const len = valj_efb.length;
   const timeout = len>600 ? 4200 : len>500 ? 2600 : len >400 ? 1800 : len>300 ? 1200 : len>200 ? 800 : len>100 ? 200 : len>50 ? 100 : len>25 ? 50 : len>10 ? 20 : 0;

    const loading = '<div  class="efb m-0 p-0 " id="loadingSideMenuConEfb">'+efbLoadingCard('',5)+'</div>';
    document.getElementById('sideMenuConEfb').innerHTML=loading;
    document.getElementById('sideMenuConEfb').innerHTML+='<div class="efb m-0 p-0 d-none" id="childsSideMenuConEfb">'+body+'</div>';
    for (const el of document.querySelectorAll(`.elEdit`)) {
      if(el.tagName!="DIV"){el.addEventListener("change", (e) => { change_el_edit_Efb(el);})}
      else{ }
    }
    setTimeout(() => {
      document.getElementById('loadingSideMenuConEfb').classList.add('d-none');
      document.getElementById('childsSideMenuConEfb').classList.remove('d-none');
     }, timeout);
  }

function creator_form_builder_Efb() {
  if (valj_efb.length < 2) {
    const btn_pois = Number(efb_var.rtl) == 1 ? 'justify-content-center' : 'justify-content-center';
    step_el_efb = 1;
    valj_efb.push({
      type: form_type_emsFormBuilder, steps: 1, formName: efb_var.text.form, email: '', trackingCode: true, EfbVersion: 2,
      button_single_text: efb_var.text.submit, button_color: pub_bg_button_color_efb, icon: 'bi-ui-checks-grid', button_Next_text: efb_var.text.next, button_Previous_text: efb_var.text.previous,
      button_Next_icon: 'bi-chevron-right', button_Previous_icon: 'bi-chevron-left', button_state: 'single',  label_text_color: pub_label_text_color_efb,
      el_text_color: pub_txt_button_color_efb, message_text_color: pub_message_text_color_efb, icon_color: pub_txt_button_color_efb, el_height: 'h-d-efb', email_to: false, show_icon: true,
      show_pro_bar: true, captcha: false, private: false, sendEmail: false, font: true, stateForm: 0,dShowBg:true, btns_align: btn_pois,
      thank_you: 'msg',
      thank_you_message: { icon: 'bi-hand-thumbs-up', thankYou: efb_var.text.thanksFillingOutform, done: efb_var.text.yad, trackingCode: efb_var.text.trackingCode, error: efb_var.text.error, pleaseFillInRequiredFields: efb_var.text.pleaseFillInRequiredFields }, email_temp: '', font: true,
    });

    if (form_type_emsFormBuilder == "payment") {
      Object.assign(valj_efb[0], { getway: 'stripe', currency: 'usd', paymentmethod: 'charge' })
    }

  }

  let els = "<!--efb.app-->";
  let dragab = true;
  let disable = "disable";
  let formType = valj_efb[0].type

  const ond = `onclick="alert_message_efb('${efb_var.text.error}','${efb_var.text.thisElemantNotAvailable}',7,'danger')"`
  if (formType == "login") {
    dragab = false;
    disable = ond;
  }

  const isPackageTypeLimited = pro_efb == false && Number(setting_emsFormBuilder.package_type) == 2;
  const packageLimitMessage = `onclick='pro_show_efb(3)'`

  if( efb_var.language=='fa_IR')fields_efb.push( { name: efb_var.text.persiaPayment, icon: 'bi-credit-card-2-front', id: 'persiaPay', pro: true, tag:'payment all' });
  for (let ob of fields_efb) {

    if (formType == "login") { if (ob.id == "html" || ob.id == "link" || ob.id == "heading") { dragab = true; disable = "disable" } else { dragab = false; disable = ond } }
    if(ob.id=="stripe" && efb_var.addons.AdnSPF !=1){
      const msg =efb_var.text.IMAddonPMsg.replace('%s',`<b>${efb_var.text.stripe}</b>`) + ' '+ efb_var.text.INAddonMsg.replace('%s',`<b>${efb_var.text.stripe}</b>`).toLowerCase()
      disable = `onclick="alert_message_efb('${efb_var.text.iaddon}', '${msg}', 20 , 'info')"`
      dragab = false;
    }else if(ob.id=="persiaPay" && efb_var.addons.AdnPPF !=1){

      disable = `onclick="alert_message_efb('${efb_var.text.iaddons}', '${efb_var.text.IMAddonP}', 20 , 'info')"`
      dragab = false;
    }else if (ob.id=="pdate" && (efb_var.addons.hasOwnProperty('AdnPDP')==false || efb_var.addons.AdnPDP !=1)){

      disable = `onclick="alert_message_efb('${efb_var.text.iaddon}', '${efb_var.text.IMAddonPD}', 20 , 'info')"`
      dragab = false;
    }else if (ob.id=="ardate" && (efb_var.addons.hasOwnProperty('AdnADP')==false || efb_var.addons.AdnADP !=1)){

      disable = `onclick="alert_message_efb('${efb_var.text.iaddon}', '${efb_var.text.IMAddonAD}', 20 , 'info')"`
      dragab = false;
    }else if (ob.id =='paypal' && (efb_var.addons.hasOwnProperty('AdnPAP')==false || efb_var.addons.AdnPAP !=1)){
      const msg =efb_var.text.IMAddonPMsg.replace('%s',`<b>${efb_var.text.paypal}</b>`) + ' '+ efb_var.text.INAddonMsg.replace('%s',`<b>${efb_var.text.paypal}</b>`).toLowerCase()
      disable = `onClick="alert_message_efb('${efb_var.text.iaddon}', '${msg}', 20 , 'info')"`
      dragab = false;
    }

    if (isPackageTypeLimited && ob.pro == true) {
      disable = packageLimitMessage;
      dragab = false;
    }

    els += `
    <div class="efb tag efb-col-3 draggable-efb ${ob.tag}" draggable="${dragab}" id="${ob.id}" ${mobile_view_efb ? `onclick="add_element_dpz_efb('${ob.id}')"` : ''}>
     ${ob.pro == true && pro_efb == false ? ` <a type="button"  onclick='pro_show_efb(3)' class="efb pro-version-efb" data-bs-toggle="tooltip" data-bs-placement="top" title="${efb_var.text.fieldAvailableInProversion}" data-original-title="${efb_var.text.fieldAvailableInProversion}"><i class="efb  bi-gem text-light"></i></a>` : ''}
      <button type="button" class="efb btn efb btn-select-form float-end ${disable != "disable" ? "btn-muted" : ''}" id="${ob.id}_b" title="${ob.name}" ${disable}><i class="efb bi tagIcon  ${ob.icon}"></i><span class="efb d-block text-capitalize">${ob.name}</span></button>
    </div>
    `
    dragab = true;
    disable = "disable";
  }

  let navs = [
    { name: efb_var.text.save, icon: 'bi-save', fun: `saveFormEfb(1)` },
    { name: efb_var.text.pcPreview, icon: 'bi-display', fun: `previewFormEfb('pc')` },
    { name: efb_var.text.formSetting, icon: 'bi-sliders', fun: `show_setting_window_efb('formSet')` },
    { name: efb_var.text.help, icon: 'bi-question-lg', fun: `Link_emsFormBuilder('createSampleForm')` },
    { name: efb_var.text.prvnt, icon: 'bi-box-arrow-up-right', fun: `previewFormEfb('new')` }

  ]
  let nav = "<!--efb.app-->";
  const st = document.getElementById('navbarSupportedContent') ? 1 :0;
  for (let ob in navs) {
    if( typeof navs[ob] == 'object') {
      nav += `<li id='NavBtnEFB-${ob}' class="efb nav-item ${ob == 4 && st!=1 ? 'd-none' : ''}"><a class="efb btn text-capitalize nav-link ${ob == 2 ? 'BtnSideEfb' : ''} ${ob != 0 ? '' : 'btn-outline-pink text-pink'}  " ${navs[ob].fun.length > 2 ? `onclick="${navs[ob].fun}""` : ''} ><i class="efb ${navs[ob].icon} mx-1 "></i>${navs[ob].name}</a></li>`;
    }
  }

  document.getElementById(`content-efb`).innerHTML = `
  <div class="efb ${mobile_view_efb ? 'my-2 mx-1' : 'my-2 mx-0'}" id="pCreatorEfb" >
  <div id="panel_efb">
      <nav class="efb navbar navbar-expand-lg navbar-light bg-light my-2 bg-response efb">
          <div class="efb container-fluid">
              <button class="efb navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarTogglerDemo01" aria-controls="navbarTogglerDemo01" aria-expanded="false" aria-label="Toggle navigation"><span class="efb navbar-toggler-icon"></span></button>
              <div class="efb collapse navbar-collapse py-1" id="navbarTogglerDemo01"><ul class="efb navbar-nav me-auto mb-2 mb-lg-0">${nav}</ul></div>
          </div>
      </nav>
      <div class="efb row">
      <!-- over page -->
      <div id="overlay_efb" class="efb d-none">${efbLoadingCard('bg-white',4)}</div>
      <!--end  over page -->
          <div class="efb  col-md-4" id="listElEfb">

            <ul class="efb my-2 row" id="listCatEfb">
                <li class="efb efb-col-3">
                  <a class="efb nav-link cat fs-6 efb active all" aria-current="page" onclick="funUpdateLisetElEfb('all')" role="button">${efb_var.text.all}</a>
                </li>
                <li class="efb efb-col-3">
                  <a class="efb nav-link cat fs-6 efb basic" onclick="funUpdateLisetElEfb('basic')"  role="button">${efb_var.text.basic}</a>
                </li>
                <li class="efb efb-col-3">
                  <a class="efb nav-link cat fs-6 efb payment" onclick="funUpdateLisetElEfb('payment')"  role="button">${efb_var.text.payment}</a>
                </li>
                <li class="efb efb-col-3">
                  <a class="efb nav-link cat fs-6 efb advance" onclick="funUpdateLisetElEfb('advance')"  role="button">${efb_var.text.advanced}</a>
                </li>
                <hr class="efb hr">
            </ul>
          <div class="efb row">${els}</div></div>
         <div class="efb  col-md-8 body-dpz-efb">
         <div class="efb d-flex justify-content-center mb-2 d-none" id="viewToggleEfb">
           <div class="efb btn-group" role="group" aria-label="View toggle">
             <button type="button" class="efb btn btn-sm btn-outline-primary active" id="desktopViewBtnEfb" onclick="switchViewEfb('desktop')">
               <i class="efb bi-display me-1"></i>${efb_var.text.desktop || 'Desktop'}
             </button>
             <button type="button" class="efb btn btn-sm btn-outline-primary" id="mobileViewBtnEfb" onclick="switchViewEfb('mobile')">
               <i class="efb bi-phone me-1"></i>${efb_var.text.mobileView || 'Mobile'}
             </button>
           </div>
         </div>
         <div class="efb crd efb  drag-box" id="dragBoxWrapperEfb"><div class="efb card-body dropZoneEFB row items px-0 mx-0" id="dropZoneEFB">

        <div id="efb-dd" class="efb text-center ">
        <h1 class="efb text-muted display-1  bi-plus-circle-dotted"> </h1>
        <div class="efb text-muted fs-5 efb">${!mobile_view_efb ? efb_var.text.dadFieldHere : ''}</div>
        </div>

         </div></div></div></div>
      </div>
  <div class="efb modal fade test" id="settingModalEfb"  aria-labelledby="settingModalEfb"  role="dialog" tabindex="-1" data-backdrop="static" >
      <div class="efb modal-dialog modal-dialog-centered " id="settingModalEfb_" >
          <div class="efb modal-content efb " id="settingModalEfb-sections">
                  <div class="efb modal-header efb"> <h5 class="efb modal-title efb" ><i class="efb bi-ui-checks fs-7 ${iconMarginGlobal}" id="settingModalEfb-icon"></i><span id="settingModalEfb-title" class="efb fs-3">${efb_var.text.editField}</span></h5></div>
                  <div class="efb modal-body" id="settingModalEfb-body">
                     ${efbLoadingCard('',4)}
                  </div>
  </div></div></div>
  </div></div>

  `

  create_dargAndDrop_el();
  items_dd_efb();
}

function funUpdateLisetElEfb(cat){
  change_active_cat_efb(cat);
  change_visible_el_efb(cat);
}

 change_active_cat_efb=(cat)=>{
  let els = document.querySelectorAll('.efb.cat');
  for(let i =0;i<els.length;i++){
    els[i].classList.remove('active');
    els[i].classList.contains(cat) ? els[i].classList.add('active') : '';
  }

}
change_visible_el_efb=(cat)=>{
  let els = document.querySelectorAll('.efb.tag');
  for(let i =0;i<els.length;i++){
    els[i].classList.remove('d-none');
    els[i].classList.contains(cat) ? '' : els[i].classList.add('d-none');
  }
}

function funUpdateLisetcardTitleEfb(cat){
  change_active_cat_efb(cat);
  change_visible_el_efb(cat);
}

items_dd_efb = () => {
  jQuery(function () {

    jQuery(".items").sortable({

      items: "setion:not(.unsortable)",
      start: function (event, ui) {
        ui.item.toggleClass("highlight");
        if (ui.item.hasClass('unsortable')) {
          return;
        }
      },
      stop: function (event, ui) {
        ui.item.hasClass('ui-state-disabled') ? ui.item.removeData('sortableItem') : false;
        ui.item.toggleClass("highlight");
        const container = ui.item.closest('.items')[0];
        if (container) {
          const step1 = container.querySelector('.stepNavEfb[data-step="1"]');
          if (step1 && step1 !== container.firstElementChild) {
            container.insertBefore(step1, container.firstElementChild);
          }
        }
        sort_obj_el_efb_();
      }
    });
    jQuery("#items").disableSelection();
  });
}

items_dd_refresh_efb = () => {
  jQuery(function () {
    jQuery(".items").sortable("refresh");
  })
}

efb_powered_by=()=>{
  const ws = efb_var.language != "fa_IR" ? "https://whitestudio.team/" : 'https://easyformbuilder.ir';
  return `<div class="efb fs-8 p-0  m-0 text-muted" id="wpfooter"><a href="https://wordpress.org/plugins/easy-form-builder/" target="_blank" class="efb nounderline">Easy Form Builder</a> Powered by <a href="https://wordpress.org/" target="_blank" class="efb nounderline">WordPress</a>, <a href="https://getbootstrap.com/" target="_blank" class="efb nounderline">Bootstrap</a> and Bootstrap Icon. Created by <a href="${ws}" target="_blank" class="efb nounderline">Whitestudio.team</a></div>`;
}

efb_add_opt_setting= (objOptions, el ,s ,newRndm ,ftyp)=>{
 const col = s==true ||  form_type_emsFormBuilder=="smart"  ?'col-sm-7':'col-sm-12'

 let t = "radio";
 let opetions = `<!-- options -->`;
 let parent = valj_efb.find(x=>x.id_ == objOptions[0].parent)
 const vl =parent ? parent.value :'';
  let l_b = mobile_view_efb ? 'd-block' : 'd-none';
  const tp = parent.type.toLowerCase();
 for (let ob of objOptions) {
   if(parent){
     if(tp.indexOf("multi")>-1  || tp.includes("checkbox")==true || tp.includes("multiselect")==true  ) t="checkbox"

   }
    const price = ob.hasOwnProperty("price") ? ob.price : 0;
    const id = ob.hasOwnProperty('id') ? ob.id : ob.id_;
    const id_old = ob.hasOwnProperty("id_old") ? ob.id_old :'null'
    let checked= "";

    if((tp.includes("radio")==true ||( tp.includes("select")==true &&  tp.includes("multi")==false))  && (vl == id || vl==id_old)){ checked="checked";
    }else if((tp.includes("multi")==true || tp.includes("checkbox")==true) &&  typeof vl!="string" &&  vl.findIndex(x=>x==id || x==id_old)!=-1 ){checked="checked"
    }else if((tp.includes("stateprovince")==true || tp.includes("conturylist")==true  || tp.includes("citylist")==true ) &&  (vl==id || vl==id_old) ){checked="checked"}

    opetions +=add_option_edit_admin_efb(price,parent.id_,t,ob.id_op,el.dataset.tag.trim(),ob.id_ob,ob.value,col,s,l_b,ftyp,id,checked)
  }
  return opetions
}

const add_option_edit_admin_efb=(price,parentsID,t,idin,tag,id_ob,value,col,s,l_b,ftyp,id_value,checked)=>{
  const fun_imgRadio =()=>{
    let r ='<!-efb-->'
    const u = (url)=>{
      url = url.replace(/(http:@efb@)+/g, 'http://');
      url = url.replace(/(https:@efb@)+/g, 'https://');
      url = url.replace(/(@efb@)+/g, '/');

      return url;
     }

    if(tag=="imgRadio"){
      let row = valj_efb.find(x=>x.id_==id_value);
      if (typeof row == "undefined") r ='<!-efb-->';
      const url = u(row.src);
      r =`
      <input type="text" placeholder="${efb_var.text.description}" id="imgRadio_sub_value"  value="${row.sub_value}" data-value="${value}" data-parent="${parentsID}" data-id="${idin}" data-tag="${tag}" class="efb  ${col}  text-muted mb-1 fs-6 border-d rounded-4 elEdit" >
      <input type="text" placeholder="${efb_var.text.iimgurl}" id="imgRadio_url"  value="${url}" data-value="${url}" data-parent="${parentsID}" data-id="${idin}" data-tag="${tag}" class="efb  ${col}  text-muted mb-1 fs-6 border-d rounded-4 elEdit" >
      `
    }

    return r ;
  }
  const fun_bookingAttr =()=>{
    let r ='<!-efb-->'

    if(valj_efb[0].hasOwnProperty('booking')==true && valj_efb[0].booking==true && (tag=='radio' || tag=='checkbox' || tag=='select' || tag=='imgRadio')){
      let row = valj_efb.find(x=>x.id_==id_value);
      if (typeof row == "undefined") r ='<!-efb-->';
      const date_v =row.hasOwnProperty('dateExp') && row.dateExp.length>1 ? row.dateExp : '';
      r =`
      <input type="date" placeholder="${efb_var.text.date}" id="bookDateExpEl"  value="${date_v}" data-value="${date_v}" data-id="${row.id_}" data-parent="${parentsID}" data-id="${idin}" data-tag="${tag}" class="efb  ${col}  text-muted mb-1 fs-6 border-d rounded-4 elEdit" >
      <input type="number" data-id="${row.id_}" class="efb elEdit form-control text-muted efb border-d rounded-4 h-d-efb mb-1" placeholder="${efb_var.text.max}" id="mLenEl" required value="${row.hasOwnProperty('mlen') ? row.mlen : ''}" >
      `
    }

    return r ;
  }
  const imgRadio = fun_imgRadio();
  const booking = fun_bookingAttr();
  const s_show_id = document.getElementById('optionListefb') && document.getElementById('optionListefb').dataset.idstate =="true" ? true : false;
  let id_v = `  <div class="efb mx-0 px-0 col-sm-12 elIds ${s_show_id==true ? '' :'d-none'}">
    <label  for="ElIdOptions" class="efb form-label mx-1 my-0 py-0 fs-6 col-sm-2 " >${efb_var.text.id}</label>
    <input type="text" placeholder="${efb_var.text.id}" id="ElIdOptions"  value="${id_value}" data-parent="${parentsID}" data-id="${idin}" data-tag="${tag}" class="efb  text-muted mb-1 fs-7 border-d rounded-4 elEdit col-sm-9">

    </div>`
    row_col_size ='col-sm-11'
    const selected_options =() =>{
      if(tag=="table_matrix"){
        row_col_size ='col-sm-12'
        return `<!--efb-->`;
      }
      return    `
      <div id="" class="efb mx-0 px-0 col-sm-1 form-check">
      <input class="efb  emsFormBuilder_v form-check-input  fs-6 m-0 p-0 elEdit newElOp" name="${parentsID}-g" type="${t}" data-parent="${parentsID}" data-id="${idin}" data-tag="${tag}" id="ElvalueOptions" ${checked}>
      <label  for="ElvalueOptions" class="efb form-label mx-1 my-0 py-0 ${l_b} fs-6" >${efb_var.text.dslctd}</label>
      </div>
      `

    }

  return `<div class="efb mx-0 col-sm-12 row opt" id="${idin}-gs">
  ${selected_options()}
  <div id="${id_ob}-v" class="efb ${row_col_size} mx-0 px-0">
  <input type="text" placeholder="${efb_var.text.name}" id="EditOption"  value="${value}" data-value="${value}" data-parent="${parentsID}" data-op="${idin}" data-id="${idin}" data-tag="${tag}" class="efb  ${col}  text-muted mb-1 fs-6 border-d rounded-4 elEdit" >
  ${imgRadio}
  ${booking}
  ${s==true ? `<label  for="paymentOption" class="efb form-label mx-1 ${l_b} fs-6 col-sm-6 my-0 py-0"">${efb_var.text.price}</label><input type="number" placeholder="$"  value='${typeof price=="string" ? price : 0}' data-value="" min="0" id="paymentOption" data-parent="${parentsID}" data-id="${idin}" data-tag="${tag}-payment"  class="efb  ${ mobile_view_efb ? "col-sm-6" :"col-sm-2"} text-muted mb-1 fs-6 border-d rounded-4 elEdit">` :''}
  <div class="efb  btn-edit-holder ${ftyp=="payment" ||  ftyp=="smart" ?'pay':'newop' }" id="deleteOption" data-parent_id="${parentsID}">
  <button type="button" id="deleteOption"  onclick="delete_option_efb('${idin}')" data-parent="${parentsID}" data-tag="${tag}"  data-id="${idin}" class="efb btn efb btn-edit btn-sm elEdit" data-bs-toggle="tooltip" title="${efb_var.text.delete}">
  <i class="efb  efb bi-x-lg text-danger"></i>
  </button>
  <button type="button" id="addOption" onclick="add_option_edit_pro_efb('${parentsID.trim()}','${tag.trim()}' ,${valj_efb.length})" data-parent="${parentsID}" data-tag="${tag}" data-id="${idin}" class="efb btn efb btn-edit btn-sm elEdit" data-bs-toggle="tooltip" title="${efb_var.text.add}" >
  <i class="efb  bi-plus-circle  text-success"></i>
  </button>

  </div>
  ${id_v}
</div>
</div>
`
}

function funShowAttrElsEfb(el){

  let ol = document.getElementById('optionListefb').dataset;
  if(el.classList.contains('show')){
    el.classList.remove('show');
    el.classList.add('hide');
    ol.idstate = "true" ;
    el.innerHTML= efb_var.text.hdattr
  }else{
    el.classList.remove('hide');
    el.classList.add('show');
    ol.idstate = "false" ;
    el.innerHTML= efb_var.text.shwattr
  }

  for(let ob of document.querySelectorAll(`.elIds`)){
    ol.idstate=="true" ? ob.classList.remove('d-none') : ob.classList.add('d-none');
  }

}

const optionSmartforOptionsEls = (idset ,fid , s_op)=>{
  let two ="";

  if(s_op==0 && valj_efb[0].hasOwnProperty('conditions')){
   const step_no= valj_efb[0].conditions.findIndex(x=>x.id_==fid);

   if (step_no!=-1){
    two= valj_efb[0].conditions[step_no].condition[0].two;
    s_op =  valj_efb[0].conditions[step_no].condition[0].one!=""  ? valj_efb[0].conditions[step_no].condition[0].one : 0;

   }else{
    s_op=0;
   }
  }else if (valj_efb[0].hasOwnProperty('conditions')==true){
    const step_no= valj_efb[0].conditions.findIndex(x=>x.id_==fid);

    if(step_no!=-1){
      const row = valj_efb[0].conditions[step_no].condition.findIndex(x=>x.no ==idset);
      if (row !=-1) two = valj_efb[0].conditions[step_no].condition[row].two;

    }
  }

 let row= get_list_name_otions_field_efb(s_op);
 let op =`<option selected disabled>${efb_var.text.nothingSelected}</option>`;

 for (let i =0 ; i< row.length ; i++){

  op +=`<option value="${row[i].id_}"  id="ocsso-${row[i].id_}" data-idset="${idset}" data-fid="${fid}"  data-op="${s_op}" ${ row[i].id_==two ? `selected` : ''} >${row[i].name}</option>`;
 }
 return `<select  data-id="oso-${idset}" data-no="${idset}" data-fid="${fid}" class="efb w-100 elEdit form-select border-d rounded-4 ps-1 pe-4"  id="optiontSmartforOptionsEls" data-tag="list_otiones">
 ${op}
 <select>`
}

const selectSmartforOptionsEls = (idset ,fid)=>{
  let c = -1;
  const n = valj_efb[0].hasOwnProperty('conditions')==true ? valj_efb[0].conditions.findIndex(x=>x.id_ ==fid):-1;

  if(n!=-1){ c= valj_efb[0].conditions[n].condition.find(x=>x.no ==idset);

  }
  if (typeof c =="undefined") c= valj_efb[0].conditions[n].condition[0];
 let row= get_list_name_selecting_field_efb();
 let op =`<option disabled>${efb_var.text.nothingSelected}</option>`;

 for (let i =0 ; i< row.length ; i++){

  op +=`<option value="${row[i].id_}" id="opsso-${row[i].id_}" data-idset="${idset}" data-fid="${fid}" ${c.one == row[i].id_ ? `selected` : ''} >${row[i].name}</option>`;
 }
 return `<select  data-id="sso-${idset}" data-no="${idset}" data-fid="${fid}" class="efb w-100 elEdit form-select border-d rounded-4 ps-1 pe-4"  id="selectSmartforOptionsEls" data-tag="list_selected">
 ${op}
 <select>`
}

fun_translate_check_efb=()=>{
  const l= ['en_US' ,'fa_IR' ,'ar']
  if(l.findIndex(x=>x==efb_var.wp_lan)!=-1 ) return true;
  return false;
}

const test=fun_translate_check_efb();

const fun_state_of_UK =(rndm,iVJ)=>{
  return [{
      "id_": "NIR",
      "dataId": "NIR-id",
      "parent": rndm,
      "type": "option",
      "s2": "NIR",
      "value": "Northern Ireland",
      "id_op": "_N_o_r_t_h_e_r_n_ I_r_e_l_a_n_d_",
      "step": valj_efb[iVJ].step,
      "amount": valj_efb[iVJ].amount
    },
    {
      "id_": "ENG",
      "dataId": "ENG-id",
      "parent": rndm,
      "type": "option",
      "s2": "ENG",
      "value": "England",
      "id_op": "_E_n_g_l_a_n_d_",
      "step": valj_efb[iVJ].step,
      "amount": valj_efb[iVJ].amount
    },
    {
      "id_": "SCO",
      "dataId": "SCO-id",
      "parent": rndm,
      "type": "option",
      "s2": "SCO",
      "value": "Scotland",
      "id_op": "_S_c_o_t_l_a_n_d_",
      "step": valj_efb[iVJ].step,
      "amount": valj_efb[iVJ].amount
    },
    {
      "id_": "WAL",
      "dataId": "WAL-id",
      "parent": rndm,
      "type": "option",
      "s2": "WAL",
      "value": "Wales",
      "id_op": "_W_a_l_e_s_",
      "step": valj_efb[iVJ].step,
      "amount": valj_efb[iVJ].amount
    }
  ];
}

function update_event_elmants_settings(classes){
   for (const el of document.querySelectorAll(`${classes}`)) {
      el.addEventListener("change", (e) => { change_el_edit_Efb(el);})
    }
}

function show_setting_up_easy_form_builder_Efb() {

  const body = `
    <div class="efb-setup-container">
      <!-- Header Content -->
      <div class="efb-setup-header">
        <div class="efb-header-content">
          <h3 class="efb-main-title">
            <i class="bi bi-heart-fill"></i>
            ${efb_var.text.easyFormBuilder}
          </h3>
          <p class="efb-subtitle">
            ${efb_var.text.buildProfessionalForms}
          </p>
        </div>
      </div>

      <!-- Plans Grid -->
      <div class="efb-plans-grid">

        <!-- Free Plan -->
        <div class="efb-plan-card">
          <div class="efb-card-content">
            <div class="efb-plan-header">
              <h6 class="efb-plan-title">${efb_var.text.free}</h6>
              <span class="efb-plan-badge efb-badge-light">${efb_var.text.essentialFeatures}</span>
            </div>

            <p class="efb-plan-description">${efb_var.text.perfectForGettingStarted}</p>

            <ul class="efb-features-list">
              <li class="efb-feature-item efb-feature-included">
                <i class="bi bi-check-circle-fill"></i>
                ${efb_var.text.coreFormFields}
              </li>
              <li class="efb-feature-item efb-feature-included">
                <i class="bi bi-check-circle-fill"></i>
                ${efb_var.text.emailNotifications}
              </li>
              <li class="efb-feature-item efb-feature-locked">
                <i class="bi bi-lock-fill"></i>
                ${efb_var.text.advancedFormFields}
              </li>
              <li class="efb-feature-item efb-feature-locked">
                <i class="bi bi-lock-fill"></i>
                ${efb_var.text.builtInAdvancedFeatures}
              </li>
              <li class="efb-feature-item efb-feature-locked">
                <i class="bi bi-lock-fill"></i>
                ${efb_var.text.addonsExtensions}
              </li>
            </ul>

            <div class="efb-plan-action">
              <button class="efb-btn efb-btn-outline" onclick="handle_setup_modal_action('free')">
                ${efb_var.text.startWithFree}
              </button>
            </div>
          </div>
        </div>

        <!-- Free Plus Plan (Recommended) -->
        <div class="efb-plan-card efb-recommended">
          <div class="efb-card-content">

          <div class="efb-plan-header">
          <h6 class="efb-plan-title">${efb_var.text.freePlus}</h6>
          <span class="efb-recommended-badge">${efb_var.text.recommended}</span>

            </div>

            <p class="efb-plan-description">${efb_var.text.unlockAdvancedFeatures}</p>

            <ul class="efb-features-list">
              <li class="efb-feature-item efb-feature-included">
                <i class="bi bi-check-circle-fill"></i>
                ${efb_var.text.coreAdvancedFormFields}
              </li>
              <li class="efb-feature-item efb-feature-included">
                <i class="bi bi-check-circle-fill"></i>
                ${efb_var.text.emailNotifications}
              </li>
              <li class="efb-feature-item efb-feature-included">
                <i class="bi bi-check-circle-fill"></i>
                ${efb_var.text.builtInAdvancedFeatures}
              </li>
              <li class="efb-feature-item efb-feature-locked">
                <i class="bi bi-lock-fill"></i>
                ${efb_var.text.addonsExtensions}
              </li>
              <li class="efb-feature-item efb-feature-info">
                <i class="bi bi-info-circle-fill"></i>
                ${efb_var.text.poweredByCredit}
              </li>
            </ul>

            <div class="efb-plan-action">
              <button class="efb-btn efb-btn-primary" onclick="handle_setup_modal_action('free_plus')">
                ${efb_var.text.continueWithFreePlus}
              </button>
            </div>
          </div>
        </div>

        <!-- Pro Plan (Premium) -->
        <div class="efb-plan-card efb-pro-highlighted">
          <div class="efb-card-content">
            <span class="efb-pro-badge">${efb_var.text.mostPopular || 'Most Popular'}</span>

            <div class="efb-plan-header">
              <h6 class="efb-plan-title">${efb_var.text.pro}</h6>
              <span class="efb-plan-badge efb-badge-premium">${efb_var.text.advancedAdFree}</span>
            </div>

            <p class="efb-plan-description">${efb_var.text.completeCleanExperience}</p>

            <ul class="efb-features-list">
              <li class="efb-feature-item efb-feature-included">
                <i class="bi bi-check-circle-fill"></i>
                ${efb_var.text.everythingInFreePlus}
              </li>
              <li class="efb-feature-item efb-feature-included">
                <i class="bi bi-check-circle-fill"></i>
                ${efb_var.text.advancedIntegrations}
              </li>
              <li class="efb-feature-item efb-feature-included">
                <i class="bi bi-check-circle-fill"></i>
                ${efb_var.text.addonsIncluded}
              </li>
              <li class="efb-feature-item efb-feature-included">
                <i class="bi bi-check-circle-fill"></i>
                ${efb_var.text.noCreditsPromo}
              </li>
              <li class="efb-feature-item efb-feature-included">
                <i class="bi bi-check-circle-fill"></i>
                ${efb_var.text.premiumExperience}
              </li>
            </ul>

            <div class="efb-plan-action">
              <button class="efb-btn efb-btn-premium" onclick="handle_setup_modal_action('pro')">
                ${efb_var.text.upgradeToPro}
              </button>
            </div>
          </div>
        </div>

      </div>

      <!-- Footer Info -->
      <div class="efb-setup-footer">
        <div class="efb-footer-info">
          <i class="bi bi-info-circle"></i>
          <p>${efb_var.text.canChangeAnytime}</p>
        </div>

        <div class="efb-footer-actions">
          <button class="efb-btn efb-btn-link" onclick="handle_setup_modal_action('later')">
            ${efb_var.text.maybeLater}
          </button>
        </div>
      </div>
    </div>
    <style>
      .efb-setup-container {
        padding: 30px;
        max-width: 100%;
        margin: 0 auto;
      }

      .efb-setup-header {
        text-align: center;
        margin-bottom: 40px;
      }

      .efb-header-content {
        max-width: 600px;
        margin: 0 auto;
      }

      .efb-main-title {
        color: #202a8d;
        font-size: 2.2rem;
        font-weight: 700;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 15px;
      }

      .efb-main-title i {
        color: #633a82;
        font-size: 2rem;
      }

      .efb-subtitle {
        color: #666;
        font-size: 1.1rem;
        margin: 0;
        line-height: 1.5;
      }

      .efb-plans-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 25px;
        margin-bottom: 40px;
        align-items: stretch;
      }

      .efb-plan-card {
        background: white;
        border-radius: 1.2rem;
        border: 2px solid rgba(32, 42, 141, 0.1);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        height: 100%;
        position: relative;
        overflow: visible;
        display: flex;
        flex-direction: column;
      }

      .efb-plan-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 20px 40px rgba(32, 42, 141, 0.15);
        border-color: rgba(32, 42, 141, 0.25);
      }

      .efb-recommended {
        background: linear-gradient(135deg, rgba(32, 42, 141, 0.05) 0%, rgba(99, 58, 130, 0.08) 100%);
        border: 2px solid rgba(32, 42, 141, 0.3);
        box-shadow: 0 10px 30px rgba(32, 42, 141, 0.1);
      }

      .efb-pro-highlighted {
        background: linear-gradient(145deg, rgba(255, 215, 0, 0.05) 0%, rgba(255, 193, 7, 0.08) 100%);
        border: 3px solid #8f8f8f;
        box-shadow: 0 15px 40px rgba(255, 193, 7, 0.2);
        transform: scale(1.05);
        position: relative;
        animation: proGlow 2s ease-in-out infinite alternate;
      }

      .efb-pro-highlighted::before {
        content: '';
        position: absolute;
        top: -3px;
        left: -3px;
        right: -3px;
        bottom: -3px;
        background: linear-gradient(45deg, #cbc8c0, #cadce1, #e0eaef, #fbfbf9);
        background-size: 300% 300%;
        border-radius: 1.2rem;
        z-index: -1;
        animation: gradientShift 7s ease infinite;
      }

      .efb-pro-highlighted:hover {
        transform: translateY(-12px) scale(1.08);
        box-shadow: 0 25px 60px rgba(255, 193, 7, 0.3);
      }

      .efb-pro-badge {
        position: absolute;
        top: -15px;
        left: 50%;
        transform: translateX(-50%);
        background: linear-gradient(135deg, #3F51B5, #2196F3);
        color: white;
        padding: 8px 20px;
        border-radius: 25px;
        font-size: 0.8rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        box-shadow: 0 4px 15px rgba(33, 150, 243, 0.4);
        border: 2px solid white;
        z-index: 10;
      }

      .efb-card-content {
        padding: 30px 25px;
        height: 100%;
        display: flex;
        flex-direction: column;
        min-height: 450px;
      }

      .efb-recommended-badge {
        position: absolute;
        top: 15px;
        right: 15px;
        left: auto;
        background: linear-gradient(135deg, #202a8d, #633a82);
        color: white;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
      }

      [dir="rtl"] .efb-recommended-badge {
        right: auto;
        left: 15px;
      }

      .efb-plan-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 15px;
        flex-wrap: wrap;
        gap: 10px;
        flex-shrink: 0;
      }

      .efb-plan-title {
        font-size: 1.4rem;
        font-weight: 700;
        color: #202a8d;
        margin: 0;
      }

      .efb-plan-badge {
        padding: 4px 12px;
        border-radius: 15px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
      }

      .efb-badge-light {
        background: rgba(108, 117, 125, 0.1);
        color: #6c757d;
      }

      .efb-badge-primary {
        background: rgba(32, 42, 141, 0.1);
        color: #202a8d;
      }

      .efb-badge-dark {
        background: rgba(99, 58, 130, 0.1);
        color: #633a82;
      }

      .efb-plan-description {
        color: #666;
        font-size: 0.95rem;
        line-height: 1.5;
        margin-bottom: 25px;
        flex-shrink: 0;
        padding: 0;
        margin: 0 0 30px 0;
        flex-grow: 1;
      }

      .efb-feature-item {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 12px;
        font-size: 0.9rem;
        line-height: 1.4;
      }

      .efb-feature-item i {
        font-size: 1rem;
        flex-shrink: 0;
      }

      .efb-feature-included {
        color: #333;
      }

      .efb-feature-included i {
        color: #28a745;
      }

      .efb-feature-locked {
        color: #999;
      }

      .efb-feature-locked i {
        color: #999;
      }

      .efb-feature-info {
        color: #666;
      }

      .efb-feature-info i {
        color: #17a2b8;
      }

      .efb-plan-action {
        margin-top: auto;
        flex-shrink: 0;
        padding-top: 20px;
        width: 100%;
      }

      .efb-btn {
        width: 100%;
        padding: 15px 20px;
        border-radius: 12px;
        font-weight: 600;
        font-size: 0.95rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border: 2px solid;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        text-decoration: none;
        display: block;
        text-align: center;
        box-sizing: border-box;
      }

      .efb-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(32, 42, 141, 0.2);
      }

      .efb-btn-primary {
        background: linear-gradient(135deg, #202a8d 0%, #633a82 100%);
        border-color: #202a8d;
        color: white;
      }

      .efb-btn-primary:hover {
        background: linear-gradient(135deg, #1a2478 0%, #552d70 100%);
        color: white;
      }

      .efb-btn-outline {
        background: transparent;
        border-color: rgba(32, 42, 141, 0.3);
        color: #202a8d;
      }

      .efb-btn-outline:hover {
        background: linear-gradient(135deg, #202a8d 0%, #633a82 100%);
        color: white;
        border-color: #202a8d;
      }

      .efb-btn-dark {
        background: transparent;
        border-color: rgba(99, 58, 130, 0.4);
        color: #633a82;
      }

      .efb-btn-dark:hover {
        background: linear-gradient(135deg, #633a82 0%, #202a8d 100%);
        color: white;
        border-color: #633a82;
      }

      .efb-btn-link {
        background: transparent;
        border: none;
        color: #6c757d;
        padding: 10px 15px;
        font-size: 0.9rem;
        text-transform: none;
        letter-spacing: 0;
      }

      .efb-btn-link:hover {
        color: #202a8d;
        transform: none;
        box-shadow: none;
        text-decoration: underline;
      }

      .efb-btn-dark:hover {
        background: linear-gradient(135deg, #633a82 0%, #202a8d 100%);
        color: white;
        border-color: #633a82;
      }

      .efb-btn-link {
        background: transparent;
        border: none;
        color: #6c757d;
        padding: 10px 15px;
        font-size: 0.9rem;
        text-transform: none;
        letter-spacing: 0;
      }

      .efb-btn-link:hover {
        color: #202a8d;
        transform: none;
        box-shadow: none;
        text-decoration: underline;
      }

      .efb-setup-footer {
        border-top: 1px solid rgba(32, 42, 141, 0.1);
        padding-top: 30px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 20px;
      }

      .efb-footer-info {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        flex: 1;
      }

      .efb-footer-info i {
        color: #202a8d;
        margin-top: 3px;
        flex-shrink: 0;
      }

      .efb-footer-info p {
        color: #666;
        font-size: 0.9rem;
        line-height: 1.5;
        margin: 0;
      }

      @media (max-width: 768px) {
        .efb-setup-container {
          padding: 20px 15px;
        }

        .efb-plans-grid {
          grid-template-columns: 1fr;
          gap: 20px;
          margin-bottom: 30px;
        }

        .efb-main-title {
          font-size: 1.8rem;
          flex-direction: column;
          gap: 10px;
        }

        .efb-main-title i {
          font-size: 1.6rem;
        }

        .efb-subtitle {
          font-size: 1rem;
        }

        .efb-card-content {
          padding: 25px 20px;
        }

        .efb-plan-header {
          flex-direction: column;
          align-items: flex-start;
          gap: 8px;
        }

        .efb-setup-footer {
          flex-direction: column;
          text-align: center;
          gap: 15px;
        }
      }

      @media (max-width: 480px) {
        .efb-setup-container {
          padding: 15px 10px;
        }

        .efb-plans-grid {
          grid-template-columns: 1fr;
          gap: 15px;
        }

        .efb-main-title {
          font-size: 1.5rem;
        }

        .efb-card-content {
          padding: 20px 15px;
        }

        .efb-btn {
          padding: 12px 15px;
          font-size: 0.85rem;
        }
      }

      .badge {
        font-size: 0.7rem;
        padding: 0.5rem 0.75rem;
        border-radius: 0.5rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
      }

      .badge.bg-primary {
        background: linear-gradient(135deg, #202a8d 0%, #633a82 100%) !important;
      }

      .badge.text-bg-primary-subtle {
        background: rgba(32, 42, 141, 0.1) !important;
        color: #202a8d !important;
      }

      .badge.text-bg-light {
        background: rgba(162, 176, 213, 0.2) !important;
        color: #633a82 !important;
      }

      .badge.text-bg-dark {
        background: linear-gradient(135deg, #633a82 0%, #202a8d 100%) !important;
        color: white !important;
      }

      .efb p, .efb span, .efb h1, .efb h2, .efb h3, .efb h4, .efb h5, .efb h6, .efb li, .efb .text-muted, .efb .badge, .efb .modal-title, .efb .lead {
        cursor: pointer;
      }

      .efb .modal-title, .efb .badge, .efb .card-body h6 {
        user-select: none;
      }

      .efb-selectable-card {
        cursor: pointer;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
      }

      .efb-selectable-card:hover {
        transform: translateY(-10px) scale(1.03);
        box-shadow: 0 25px 50px rgba(32, 42, 141, 0.2);
      }

      .efb-selectable-card.selected {
        border: 3px solid #202a8d !important;
        box-shadow: 0 20px 40px rgba(32, 42, 141, 0.3) !important;
        transform: translateY(-8px) scale(1.02);
        background: rgba(32, 42, 141, 0.02) !important;
      }

      .efb-selectable-card.selected::after {
        content: '✓';
        position: absolute;
        top: 12px;
        right: 12px;
        background: linear-gradient(135deg, #202a8d 0%, #633a82 100%);
        color: white;
        border-radius: 50%;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 16px;
        z-index: 10;
        box-shadow: 0 4px 12px rgba(32, 42, 141, 0.3);
      }

      .efb-selectable-card.selected .efb-plan-btn {
        background: linear-gradient(135deg, #202a8d 0%, #633a82 100%) !important;
        border-color: #202a8d !important;
        color: white !important;
        box-shadow: 0 6px 20px rgba(32, 42, 141, 0.3);
      }

      .bi-heart-fill {
        color: #633a82 !important;
      }

      .bi-check-circle-fill {
        color: #28a745 !important;
      }

      .bi-lock-fill {
        color: #898aa9 !important;
      }

      .bi-info-circle-fill, .bi-info-circle {
        color: #202a8d !important;
      }

      .text-muted {
        color: #898aa9 !important;
      }

      .text-primary {
        color: #202a8d !important;
      }

      .efb-notification {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        padding: 18px 24px;
        border-radius: 12px;
        box-shadow: 0 8px 30px rgba(32, 42, 141, 0.15);
        background: white;
        border: 1px solid rgba(32, 42, 141, 0.1);
        border-left: 4px solid #28a745;
        animation: slideIn 0.4s cubic-bezier(0.4, 0, 0.2, 1);
      }

      .efb-notification-success {
        border-left-color: #28a745;
      }

      .efb-notification-content {
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 14px;
        color: #333;
        font-weight: 500;
      }

      .efb-notification-content i {
        color: #28a745;
        font-size: 18px;
      }

      .modal-header {
        background: linear-gradient(135deg, rgba(32, 42, 141, 0.03) 0%, rgba(99, 58, 130, 0.05) 100%);
        border-bottom: 1px solid rgba(32, 42, 141, 0.1);
        border-radius: 1.25rem 1.25rem 0 0;
      }

      .modal-title {
        color: #202a8d !important;
        font-weight: 700;
      }

      @keyframes slideIn {
        from {
          transform: translateX(100%) scale(0.9);
          opacity: 0;
        }
        to {
          transform: translateX(0) scale(1);
          opacity: 1;
        }
      }

      @keyframes fadeInUp {
        from {
          transform: translateY(30px);
          opacity: 0;
        }
        to {
          transform: translateY(0);
          opacity: 1;
        }
      }

      .card {
        animation: fadeInUp 0.6s cubic-bezier(0.4, 0, 0.2, 1) both;
      }

      .card:nth-child(1) { animation-delay: 0.1s; }
      .card:nth-child(2) { animation-delay: 0.2s; }
      .card:nth-child(3) { animation-delay: 0.3s; }

      .efb-btn-premium {
        background: linear-gradient(135deg, #3F51B5 0%, #2196F3 100%);
        border: 2px solid #2196F3;
        color: #f0f0f0;
        font-weight: 700;
        text-shadow: none;
        position: relative;
        overflow: hidden;
      }

      .efb-btn-premium:hover {
        background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
        border-color: #1976D2;
        color: white;
        transform: translateY(-4px);
        box-shadow: 0 12px 30px rgba(33, 150, 243, 0.4);
      }

      .efb-btn-premium::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
        transition: left 0.6s;
      }

      .efb-btn-premium:hover::before {
        left: 100%;
      }

      @keyframes proGlow {
        0% {
          box-shadow: 0 15px 40px rgba(63, 81, 181, 0.2);
        }
        100% {
          box-shadow: 0 15px 40px rgba(63, 81, 181, 0.4), 0 0 30px rgba(33, 150, 243, 0.3);
        }
      }

      @keyframes gradientShift {
        0% {
          background-position: 0% 50%;
        }
        50% {
          background-position: 100% 50%;
        }
        100% {
          background-position: 0% 50%;
        }
      }

      .efb-plan-selected {
        border: 3px solid #28a745 !important;
        background: linear-gradient(135deg, rgba(40, 167, 69, 0.05) 0%, rgba(40, 167, 69, 0.08) 100%) !important;
        box-shadow: 0 15px 40px rgba(40, 167, 69, 0.2) !important;
        position: relative;
      }

      .efb-plan-selected::after {
        content: '';
        position: absolute;
        top: -3px;
        left: -3px;
        right: -3px;
        bottom: -3px;
        background: linear-gradient(45deg, #28a745, #20c997, #28a745);
        background-size: 300% 300%;
        border-radius: 1.2rem;
        z-index: -1;
        animation: selectedGlow 2s ease infinite;
      }

      .efb-plan-checkmark {
        margin-top: 15px;
        padding: 10px 15px;
        background: linear-gradient(135deg, #28a745, #20c997);
        color: white;
        border-radius: 10px;
        text-align: center;
        font-weight: 600;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
      }

      .efb-plan-checkmark i {
        font-size: 1.1rem;
      }

      @keyframes selectedGlow {
        0%, 100% {
          background-position: 0% 50%;
          opacity: 0.7;
        }
        50% {
          background-position: 100% 50%;
          opacity: 1;
        }
      }
    </style>
    `;

    return body;

}

function handle_setup_modal_action(plan) {

    plan = (typeof plan === 'string') ? plan.replace(/[^A-Za-z_]/g, '') : '';
    try {
        switch(plan) {
            case 'free':
                savePlanSelection_efb('free', {
                    plan_name: 'Free Plan',
                    features: ['core_form_fields', 'email_notifications'],
                    selected_at: Date.now()
                });
                setupFreePlan_efb();
                show_success_notification_efb(efb_var.text.startWithFree + ' ' + efb_var.text.selected);
                closeSetupOverlay_efb();
                break;

            case 'free_plus':
                savePlanSelection_efb('free_plus', {
                    plan_name: 'Free Plus Plan',
                    features: ['core_form_fields', 'advanced_form_fields', 'email_notifications', 'built_in_features'],
                    show_credit: true,
                    selected_at: Date.now()
                });
                enable_advanced_features_with_credit_efb();
                show_success_notification_efb(efb_var.text.freePlus + ' ' + efb_var.text.selected);
                closeSetupOverlay_efb();
                break;

            case 'pro':
                savePlanSelection_efb('pro', {
                    plan_name: 'Pro Plan',
                    features: ['all_features', 'no_credit', 'premium_support'],
                    selected_at: Date.now()
                });

            case 'later':
                localStorage.setItem('efb_setup_reminder', JSON.stringify({
                    remind_at: Date.now() + (7 * 24 * 60 * 60 * 1000),
                    skipped_at: Date.now()
                }));
                show_info_notification_efb(efb_var.text.setupReminder || 'You can access setup from plugin settings anytime.');
                closeSetupOverlay_efb();
                break;

            default:
                break;
        }

        update_ui_based_on_plan_efb(plan);

        if (typeof gtag !== 'undefined') {
            gtag('event', 'plan_selected', {
                'event_category': 'easy_form_builder',
                'event_label': plan,
                'value': 1
            });
        }

    } catch (error) {
        show_error_notification_efb('An error occurred. Please try again.');
    }
}

function enable_advanced_features_with_credit_efb() {

    if (typeof efb_var !== 'undefined') {
        efb_var.advanced_features = true;
        efb_var.show_credit = true;
    }
}

function savePlanSelection_efb(plan, planData) {
    try {
        const selectionData = {
            selected_plan: plan,
            plan_data: planData,
            timestamp: Date.now()
        };

        if (plan ==='pro' || plan ==='null' || plan ==='free') {
          efb_var.setting.package_type = 2;
        }else if (plan ==='free_plus') {
          efb_var.setting.package_type = 3;
        }
        sendPlanSelectionToServer_efb(selectionData);

    } catch (error) {
    }
}

function getSelectedPlan_efb() {
    if (typeof efb_var === 'undefined' || !efb_var.setting) {
      return { selected_plan: 'null', plan_data: {} };
    }
    const package_type = Number(efb_var.setting.package_type);
    if (package_type === 10) {
      return { selected_plan: 'null', plan_data: {} };
    }else if (package_type === 1) {
      return { selected_plan: 'pro', plan_data: {} };
    }else if (package_type === 2) {
      return { selected_plan: 'free', plan_data: {} };
    }else if (package_type === 3) {
      return { selected_plan: 'free_plus', plan_data: {} };
    }
    return { selected_plan: 'null', plan_data: {} };
}

function highlightSelectedPlan_efb() {
    const selectedPlanData = getSelectedPlan_efb();
    if (!selectedPlanData) return;

    const selectedPlan = selectedPlanData.selected_plan;

    const planCards = document.querySelectorAll('.efb-plan-card');
    planCards.forEach((card, index) => {
        const isSelected = (
            (selectedPlan === 'free' && index === 0)
            || (selectedPlan === 'free_plus' && index === 1)
        );

        if (isSelected) {
            card.classList.add('efb-plan-selected');
            const checkmark = document.createElement('div');
            checkmark.className = 'efb-plan-checkmark';
            checkmark.innerHTML = '<i class="bi bi-check-circle-fill"></i>'+efb_var.text.activated;
            card.querySelector('.efb-card-content').appendChild(checkmark);
        }
    });
}

function setupFreePlan_efb() {
    if (typeof efb_var !== 'undefined') {
        efb_var.current_plan = 'free';
        efb_var.advanced_features = false;
        efb_var.show_credit = false;
    }
}

function redirectToProUpgrade_efb($proUrl) {

    closeSetupOverlay();
    window.open(proUrl, '_blank');
}

function sendPlanSelectionToServer_efb(selectionData) {
    const user_selected = selectionData.selected_plan || 'unknown';
    if(user_selected === 'pro') {
      sessionStorage.setItem('efb_license_selected', '1');
    }else if(user_selected === 'free_plus') {
      sessionStorage.setItem('efb_license_selected', '3');
    }else if(user_selected === 'free') {
      sessionStorage.setItem('efb_license_selected', '2');
    }

    jQuery.ajax({
        url: efb_var.ajax_url,
        type: 'POST',
        dataType: 'json',
        data: {
            action: 'efb_save_plan_selection',
            plan_data: JSON.stringify(selectionData),
            nonce: _efb_nonce_
        },
        success: function(response) {
            if (response.success && response.data) {

                if (response.data.redirect_url) {
                    window.open(response.data.redirect_url, '_blank');
                }

                if (response.data.action) {
                    updatePlanBadge_efb();
                }

            } else if (response.success === false && response.data) {
            }
        },
        error: function(xhr, status, error) {
        }
    });

}

function update_ui_based_on_plan_efb(plan) {

    const planElement = document.querySelector('.efb-current-plan');
    if (planElement) {
        planElement.textContent = plan.replace('_', ' ').toUpperCase();
    }

    const advancedFeatures = document.querySelectorAll('.efb-advanced-feature');
    if (plan === 'free') {
        advancedFeatures.forEach(el => el.style.display = 'none');
    } else {
        advancedFeatures.forEach(el => el.style.display = 'block');
    }
}

function show_success_notification_efb(message) {
    const notification = document.createElement('div');
    notification.className = 'efb-notification efb-notification-success';
    notification.innerHTML = `
        <div class="efb-notification-content">
            <i class="bi bi-check-circle-fill"></i>
            <span>${message}</span>
        </div>
    `;

    document.body.appendChild(notification);

    setTimeout(() => {
        notification.remove();
    }, 3000);
}

function show_info_notification_efb(message) {
}

function show_error_notification_efb(message) {
}

function showSetupAsOverlayPage() {
    const setupContent = show_setting_up_easy_form_builder_Efb();

    const overlayPage = document.createElement('div');
    overlayPage.id = 'efb-setup-overlay';
    overlayPage.className = 'efb-setup-overlay';

    overlayPage.innerHTML = `
        <div class="efb-overlay-container packages">
            <div class="efb-overlay-content">
                ${setupContent}
            </div>
            <button class="efb-overlay-close" onclick="closeSetupOverlay_efb()">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <style>
        .efb-setup-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(10px);
            z-index: 999999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            box-sizing: border-box;
            animation: overlayFadeIn 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .efb-overlay-container {
            background: white;
            border-radius: 1.5rem;
            box-shadow: 0 25px 80px rgba(32, 42, 141, 0.25);
            max-width: 1200px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            overflow-x: hidden;
            position: relative;
            border: 2px solid rgba(32, 42, 141, 0.1);
            animation: overlaySlideIn 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            scrollbar-width: thin;
            scrollbar-color: #b4c0e0 transparent;
        }

        .efb-overlay-container::-webkit-scrollbar {
            width: 5px;
        }

        .efb-overlay-container::-webkit-scrollbar-track {
            background: transparent;
            border-radius: 10px;
            margin: 1.5rem 0;
        }

        .efb-overlay-container::-webkit-scrollbar-thumb {
            background: #b4c0e0;
            border-radius: 10px;
        }

        .efb-overlay-container::-webkit-scrollbar-thumb:hover {
            background: rgba(32, 42, 141, 0.5);
        }

        .efb-overlay-close {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255, 255, 255, 0.9);
            border: none;
            border-radius: 50%;
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            color: #633a82;
            font-size: 1.2rem;
            z-index: 1000;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .efb-overlay-close:hover {
            background: rgba(255, 255, 255, 1);
            transform: scale(1.1);
            color: #202a8d;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }

        .efb-overlay-content {
            padding: 0;
            width: 100%;
            overflow-x: hidden;
        }

        @media (max-width: 1024px) {
            .efb-overlay-container {
                max-width: 95%;
                margin: 20px auto;
            }
        }

        @media (max-width: 768px) {
            .efb-setup-overlay {
                padding: 15px;
                align-items: flex-start;
                padding-top: 30px;
            }

            .efb-overlay-container {
                max-width: 100%;
                max-height: 85vh;
                border-radius: 1rem;
                margin: 0;
                box-shadow: 0 15px 40px rgba(32, 42, 141, 0.2);
            }

            .efb-overlay-close {
                top: 12px;
                right: 12px;
                width: 38px;
                height: 38px;
                font-size: 1rem;
                background: rgba(255, 255, 255, 0.95);
            }

            .efb-overlay-content .efb-plan-card {
                margin-bottom: 15px !important;
                padding: 15px !important;
            }

            .efb-overlay-content .efb-plans-grid {
                display: flex !important;
                overflow-x: auto !important;
                overflow-y: visible !important;
                gap: 15px !important;
                padding: 10px 5px 20px 5px !important;
                scroll-behavior: smooth !important;
                -webkit-overflow-scrolling: touch !important;
                scrollbar-width: thin !important;
                position: relative !important;
            }

            .efb-overlay-content .efb-plans-grid::after {
                content: '← Swipe to see more plans →' !important;
                position: absolute !important;
                bottom: 0 !important;
                left: 50% !important;
                transform: translateX(-50%) !important;
                font-size: 0.7rem !important;
                color: rgba(32, 42, 141, 0.6) !important;
                text-align: center !important;
                animation: fadeInOut 3s ease-in-out !important;
            }

            @keyframes fadeInOut {
                0%, 100% { opacity: 0; }
                50% { opacity: 1; }
            }

            .efb-overlay-content .efb-plans-grid::-webkit-scrollbar {
                height: 6px !important;
            }

            .efb-overlay-content .efb-plans-grid::-webkit-scrollbar-track {
                background: rgba(0, 0, 0, 0.1) !important;
                border-radius: 3px !important;
            }

            .efb-overlay-content .efb-plans-grid::-webkit-scrollbar-thumb {
                background: rgba(32, 42, 141, 0.5) !important;
                border-radius: 3px !important;
            }

            .efb-overlay-content .efb-plan-card {
                flex: 0 0 280px !important;
                margin-bottom: 0 !important;
            }

            .efb-overlay-content .modal-header {
                padding: 20px 15px 15px 15px !important;
                text-align: center;
            }

            .efb-overlay-content .modal-body {
                padding: 15px !important;
            }
        }

        @media (max-width: 576px) {
            .efb-setup-overlay {
                padding: 10px;
                padding-top: 20px;
            }

            .efb-overlay-container {
                max-height: 90vh;
                border-radius: 0.8rem;
                box-shadow: 0 10px 30px rgba(32, 42, 141, 0.15);
            }

            .efb-overlay-close {
                top: 8px;
                right: 8px;
                width: 32px;
                height: 32px;
                font-size: 0.9rem;
            }

            .efb-overlay-content .efb-plan-card {
                padding: 12px !important;
                margin-bottom: 12px !important;
                border-radius: 8px !important;
            }

            .efb-overlay-content .efb-plans-grid {
                gap: 12px !important;
                padding: 8px 3px !important;
            }

            .efb-overlay-content .efb-plan-card {
                flex: 0 0 260px !important;
            }

            .efb-overlay-content .modal-header {
                padding: 15px 10px 10px 10px !important;
            }

            .efb-overlay-content .modal-header h4 {
                font-size: 1.1rem !important;
                line-height: 1.3;
            }

            .efb-overlay-content .modal-body {
                padding: 10px !important;
            }

            .efb-overlay-content .efb-setup-button {
                padding: 8px 16px !important;
                font-size: 0.9rem !important;
                margin: 5px 0 !important;
            }

            .efb-overlay-content .efb-plan-title {
                font-size: 1.1rem !important;
            }

            .efb-overlay-content .efb-plan-description {
                font-size: 0.85rem !important;
                line-height: 1.4;
            }

            .efb-overlay-content .efb-plan-features li {
                font-size: 0.8rem !important;
                margin-bottom: 3px !important;
            }
        }

        @media (max-width: 360px) {
            .efb-setup-overlay {
                padding: 5px;
                padding-top: 15px;
            }

            .efb-overlay-container {
                max-height: 95vh;
                border-radius: 0.5rem;
            }

            .efb-overlay-close {
                top: 5px;
                right: 5px;
                width: 28px;
                height: 28px;
                font-size: 0.8rem;
            }

            .efb-overlay-content .modal-header {
                padding: 10px 8px 8px 8px !important;
            }

            .efb-overlay-content .modal-header h4 {
                font-size: 1rem !important;
            }

            .efb-overlay-content .modal-body {
                padding: 8px !important;
            }

            .efb-overlay-content .efb-plan-card {
                padding: 10px !important;
                margin-bottom: 10px !important;
            }

            .efb-overlay-content .efb-plans-grid {
                gap: 10px !important;
                padding: 6px 2px !important;
            }

            .efb-overlay-content .efb-plan-card {
                flex: 0 0 240px !important;
            }

            .efb-overlay-content .efb-setup-button {
                padding: 6px 12px !important;
                font-size: 0.85rem !important;
                width: 100% !important;
                margin: 3px 0 !important;
            }
        }

        @media (max-height: 600px) and (orientation: landscape) {
            .efb-overlay-container {
                max-height: 95vh;
                overflow-y: auto;
            }

            .efb-overlay-content .modal-header {
                padding: 10px 15px !important;
            }

            .efb-overlay-content .modal-body {
                padding: 10px 15px !important;
            }
        }

        @keyframes overlayFadeIn {
            from {
                opacity: 0;
                backdrop-filter: blur(0px);
            }
            to {
                opacity: 1;
                backdrop-filter: blur(10px);
            }
        }

        @keyframes overlayFadeOut {
            from {
                opacity: 1;
                backdrop-filter: blur(10px);
            }
            to {
                opacity: 0;
                backdrop-filter: blur(0px);
            }
        }
        </style>
    `;

    document.body.appendChild(overlayPage);

    highlightSelectedPlan_efb();

    setTimeout(() => {
        const isMobile = window.innerWidth <= 768;
        if (isMobile) {
            const plansGrid = document.querySelector('.efb-plans-grid');
            const freePlusCard = document.querySelector('.efb-plan-card.efb-recommended');

            if (plansGrid && freePlusCard) {
                const cardOffsetLeft = freePlusCard.offsetLeft;
                const gridWidth = plansGrid.clientWidth;
                const cardWidth = freePlusCard.clientWidth;

                const scrollPosition = cardOffsetLeft - (gridWidth - cardWidth) / 2;

                plansGrid.scrollTo({
                    left: Math.max(0, scrollPosition),
                    behavior: 'smooth'
                });

            }
        }
    }, 300);

    document.body.style.overflow = 'hidden';

    document.addEventListener('keydown', handleOverlayEscape_efb);
}

function closeSetupOverlay_efb() {
    const overlay = document.getElementById('efb-setup-overlay');
    if (overlay) {
        overlay.style.animation = 'overlayFadeOut 0.3s cubic-bezier(0.4, 0, 0.2, 1) forwards';

        setTimeout(() => {
            overlay.remove();
            document.body.style.overflow = '';
            document.removeEventListener('keydown', handleOverlayEscape_efb);
            updatePlanBadge_efb();
        }, 300);
    }
}

sessionStorage.setItem('efb_license_selected', efb_var.setting.package_type);
function getCurrentPlanBadge_efb() {
  const crntPlnLabel = (efb_var.text && efb_var.text.crntPln) || 'Current Plan';
  const pro_type = (Number(efb_var.pro) === 1 && valueJson_ws_setting.activeCode!='') ? 1 : (sessionStorage.getItem('efb_license_selected') ? Number(sessionStorage.getItem('efb_license_selected')) : Number(efb_var.pro));
  let badgeClass = 'bg-secondary';
  let planName = (efb_var.text && efb_var.text.free) || 'Free';
  let icon_mx = 'me-2';
  let div_mx = 'ms-1';
  if(Number(efb_var.rtl)==1){
    icon_mx = 'ms-2';
    div_mx = 'me-1';
  }
  let iconHtml = `<i class="efb bi-tag ${icon_mx}"></i>`;
    if (pro_type === 1 || pro_type === true) {
        badgeClass = 'bg-info';
        iconHtml = `<i class="efb bi-gem ${icon_mx}"></i>`;
        planName = (efb_var.text && efb_var.text.pro) || 'Pro';
    } else if (pro_type === 3) {
        badgeClass = 'bg-primary';
        iconHtml = `<i class="efb bi-star-fill ${icon_mx}"></i>`;
        planName = (efb_var.text && efb_var.text.freePlus) || 'Free Plus';
    } else if (pro_type === 4) {
        badgeClass = 'bg-dark ';
        iconHtml = `<i class="efb bi-hourglass ${icon_mx}"></i>`;
        planName = (efb_var.text && efb_var.text.proPending) || 'Pro Pending';
    }

    return `<span class="efb text-muted fs-6">${crntPlnLabel}:</span>
            <span class="efb badge rounded-4 ${badgeClass} fs-6 ${div_mx} py-2">${iconHtml}${planName}</span>`;
}

function updatePlanBadge_efb() {
    const container = document.getElementById('efbCurrentPlanBadge');
    if (container) {
        container.innerHTML = getCurrentPlanBadge_efb();
    }
}

function handleOverlayEscape_efb(event) {
    if (event.key === 'Escape') {
        closeSetupOverlay_efb();
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const getPlan = getSelectedPlan_efb();
    if (getPlan && getPlan.selected_plan === 'null') {
      setTimeout(() => {
        try {
          showSetupAsOverlayPage();
        } catch (error) {
        }
      }, 1.5);
    }

    // Toggle collapse icons for Advanced button (arrow down/up)
    document.addEventListener('click', function(e) {
        const collapseBtn = e.target.closest('#advanced_collapse');
        if (collapseBtn) {
            const icon = collapseBtn.querySelector('i');
            if (icon) {
                const isExpanded = collapseBtn.getAttribute('aria-expanded') === 'true';
                // When open (expanded), clicking will close, so show UP
                // When closed, clicking will open, so show DOWN
                if (isExpanded) {
                    icon.classList.remove('bi-arrow-down-circle-fill');
                    icon.classList.add('bi-arrow-up-circle-fill');
                } else {
                    icon.classList.remove('bi-arrow-up-circle-fill');
                    icon.classList.add('bi-arrow-down-circle-fill');
                }
            }
        }
    });

});

function forceSetupModalOnNextLoad() {
  sessionStorage.setItem('efb_force_setup_modal', 'true');
}

function resetSetupModal() {
  localStorage.removeItem('efb_setup_modal_shown');
  sessionStorage.setItem('efb_force_setup_modal', 'true');
  location.reload();
}
