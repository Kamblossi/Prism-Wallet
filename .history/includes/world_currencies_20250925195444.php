<?php
/**
 * Comprehensive world currencies database
 * This file contains all major world currencies with proper symbols and codes
 * organized by region for better user experience
 */

function getAllWorldCurrencies() {
    return [
        // Major/Popular currencies (most commonly used worldwide)
        'USD' => ['name' => 'US Dollar', 'symbol' => '$', 'code' => 'USD'],
        'EUR' => ['name' => 'Euro', 'symbol' => '€', 'code' => 'EUR'],
        'GBP' => ['name' => 'British Pound Sterling', 'symbol' => '£', 'code' => 'GBP'],
        'JPY' => ['name' => 'Japanese Yen', 'symbol' => '¥', 'code' => 'JPY'],
        'CAD' => ['name' => 'Canadian Dollar', 'symbol' => 'C$', 'code' => 'CAD'],
        'AUD' => ['name' => 'Australian Dollar', 'symbol' => 'A$', 'code' => 'AUD'],
        'CHF' => ['name' => 'Swiss Franc', 'symbol' => 'CHF', 'code' => 'CHF'],
        'CNY' => ['name' => 'Chinese Yuan', 'symbol' => '¥', 'code' => 'CNY'],
        
        // African currencies
        'DZD' => ['name' => 'Algerian Dinar', 'symbol' => 'د.ج', 'code' => 'DZD'],
        'AOA' => ['name' => 'Angolan Kwanza', 'symbol' => 'Kz', 'code' => 'AOA'],
        'BWP' => ['name' => 'Botswanan Pula', 'symbol' => 'P', 'code' => 'BWP'],
        'BIF' => ['name' => 'Burundian Franc', 'symbol' => 'FBu', 'code' => 'BIF'],
        'CVE' => ['name' => 'Cape Verdean Escudo', 'symbol' => '$', 'code' => 'CVE'],
        'KMF' => ['name' => 'Comorian Franc', 'symbol' => 'CF', 'code' => 'KMF'],
        'CDF' => ['name' => 'Congolese Franc', 'symbol' => 'FC', 'code' => 'CDF'],
        'DJF' => ['name' => 'Djiboutian Franc', 'symbol' => 'Fdj', 'code' => 'DJF'],
        'EGP' => ['name' => 'Egyptian Pound', 'symbol' => '£', 'code' => 'EGP'],
        'ERN' => ['name' => 'Eritrean Nakfa', 'symbol' => 'Nfk', 'code' => 'ERN'],
        'SZL' => ['name' => 'Eswatini Lilangeni', 'symbol' => 'L', 'code' => 'SZL'],
        'ETB' => ['name' => 'Ethiopian Birr', 'symbol' => 'Br', 'code' => 'ETB'],
        'GMD' => ['name' => 'Gambian Dalasi', 'symbol' => 'D', 'code' => 'GMD'],
        'GHS' => ['name' => 'Ghanaian Cedi', 'symbol' => '₵', 'code' => 'GHS'],
        'GNF' => ['name' => 'Guinean Franc', 'symbol' => 'FG', 'code' => 'GNF'],
        'GWP' => ['name' => 'Guinea-Bissau Peso', 'symbol' => 'P', 'code' => 'GWP'],
        'KES' => ['name' => 'Kenyan Shilling', 'symbol' => 'KEh', 'code' => 'KES'],
        'LSL' => ['name' => 'Lesotho Loti', 'symbol' => 'L', 'code' => 'LSL'],
        'LRD' => ['name' => 'Liberian Dollar', 'symbol' => 'L$', 'code' => 'LRD'],
        'LYD' => ['name' => 'Libyan Dinar', 'symbol' => 'ل.د', 'code' => 'LYD'],
        'MGA' => ['name' => 'Malagasy Ariary', 'symbol' => 'Ar', 'code' => 'MGA'],
        'MWK' => ['name' => 'Malawian Kwacha', 'symbol' => 'MK', 'code' => 'MWK'],
        'MRU' => ['name' => 'Mauritanian Ouguiya', 'symbol' => 'UM', 'code' => 'MRU'],
        'MUR' => ['name' => 'Mauritian Rupee', 'symbol' => '₨', 'code' => 'MUR'],
        'MAD' => ['name' => 'Moroccan Dirham', 'symbol' => 'DH', 'code' => 'MAD'],
        'MZN' => ['name' => 'Mozambican Metical', 'symbol' => 'MT', 'code' => 'MZN'],
        'NAD' => ['name' => 'Namibian Dollar', 'symbol' => 'N$', 'code' => 'NAD'],
        'NGN' => ['name' => 'Nigerian Naira', 'symbol' => '₦', 'code' => 'NGN'],
        'RWF' => ['name' => 'Rwandan Franc', 'symbol' => 'R₣', 'code' => 'RWF'],
        'STN' => ['name' => 'São Tomé and Príncipe Dobra', 'symbol' => 'Db', 'code' => 'STN'],
        'SCR' => ['name' => 'Seychellois Rupee', 'symbol' => '₨', 'code' => 'SCR'],
        'SLL' => ['name' => 'Sierra Leonean Leone', 'symbol' => 'Le', 'code' => 'SLL'],
        'SOS' => ['name' => 'Somali Shilling', 'symbol' => 'S', 'code' => 'SOS'],
        'ZAR' => ['name' => 'South African Rand', 'symbol' => 'R', 'code' => 'ZAR'],
        'SSP' => ['name' => 'South Sudanese Pound', 'symbol' => '£', 'code' => 'SSP'],
        'SDG' => ['name' => 'Sudanese Pound', 'symbol' => 'ج.س.', 'code' => 'SDG'],
        'TZS' => ['name' => 'Tanzanian Shilling', 'symbol' => 'TSh', 'code' => 'TZS'],
        'TOP' => ['name' => 'Tongan Paʻanga', 'symbol' => 'T$', 'code' => 'TOP'],
        'TND' => ['name' => 'Tunisian Dinar', 'symbol' => 'د.ت', 'code' => 'TND'],
        'UGX' => ['name' => 'Ugandan Shilling', 'symbol' => 'USh', 'code' => 'UGX'],
        'ZMW' => ['name' => 'Zambian Kwacha', 'symbol' => 'ZK', 'code' => 'ZMW'],
        'ZWL' => ['name' => 'Zimbabwean Dollar', 'symbol' => 'Z$', 'code' => 'ZWL'],
        
        // Asian currencies
        'AFN' => ['name' => 'Afghan Afghani', 'symbol' => '؋', 'code' => 'AFN'],
        'AMD' => ['name' => 'Armenian Dram', 'symbol' => '֏', 'code' => 'AMD'],
        'AZN' => ['name' => 'Azerbaijani Manat', 'symbol' => '₼', 'code' => 'AZN'],
        'BHD' => ['name' => 'Bahraini Dinar', 'symbol' => '.د.ب', 'code' => 'BHD'],
        'BDT' => ['name' => 'Bangladeshi Taka', 'symbol' => '৳', 'code' => 'BDT'],
        'BTN' => ['name' => 'Bhutanese Ngultrum', 'symbol' => 'Nu.', 'code' => 'BTN'],
        'BND' => ['name' => 'Brunei Dollar', 'symbol' => 'B$', 'code' => 'BND'],
        'KHR' => ['name' => 'Cambodian Riel', 'symbol' => '៛', 'code' => 'KHR'],
        'GEL' => ['name' => 'Georgian Lari', 'symbol' => '₾', 'code' => 'GEL'],
        'HKD' => ['name' => 'Hong Kong Dollar', 'symbol' => 'HK$', 'code' => 'HKD'],
        'INR' => ['name' => 'Indian Rupee', 'symbol' => '₹', 'code' => 'INR'],
        'IDR' => ['name' => 'Indonesian Rupiah', 'symbol' => 'Rp', 'code' => 'IDR'],
        'IRR' => ['name' => 'Iranian Rial', 'symbol' => '﷼', 'code' => 'IRR'],
        'IQD' => ['name' => 'Iraqi Dinar', 'symbol' => 'ع.د', 'code' => 'IQD'],
        'ILS' => ['name' => 'Israeli New Shekel', 'symbol' => '₪', 'code' => 'ILS'],
        'JOD' => ['name' => 'Jordanian Dinar', 'symbol' => 'د.ا', 'code' => 'JOD'],
        'KZT' => ['name' => 'Kazakhstani Tenge', 'symbol' => '₸', 'code' => 'KZT'],
        'KWD' => ['name' => 'Kuwaiti Dinar', 'symbol' => 'د.ك', 'code' => 'KWD'],
        'KGS' => ['name' => 'Kyrgyzstani Som', 'symbol' => 'лв', 'code' => 'KGS'],
        'LAK' => ['name' => 'Lao Kip', 'symbol' => '₭', 'code' => 'LAK'],
        'LBP' => ['name' => 'Lebanese Pound', 'symbol' => 'ل.ل', 'code' => 'LBP'],
        'MOP' => ['name' => 'Macanese Pataca', 'symbol' => 'MOP$', 'code' => 'MOP'],
        'MYR' => ['name' => 'Malaysian Ringgit', 'symbol' => 'RM', 'code' => 'MYR'],
        'MVR' => ['name' => 'Maldivian Rufiyaa', 'symbol' => '.ރ', 'code' => 'MVR'],
        'MNT' => ['name' => 'Mongolian Tugrik', 'symbol' => '₮', 'code' => 'MNT'],
        'MMK' => ['name' => 'Myanmar Kyat', 'symbol' => 'Ks', 'code' => 'MMK'],
        'NPR' => ['name' => 'Nepalese Rupee', 'symbol' => '₨', 'code' => 'NPR'],
        'KPW' => ['name' => 'North Korean Won', 'symbol' => '₩', 'code' => 'KPW'],
        'OMR' => ['name' => 'Omani Rial', 'symbol' => 'ر.ع.', 'code' => 'OMR'],
        'PKR' => ['name' => 'Pakistani Rupee', 'symbol' => '₨', 'code' => 'PKR'],
        'PHP' => ['name' => 'Philippine Peso', 'symbol' => '₱', 'code' => 'PHP'],
        'QAR' => ['name' => 'Qatari Riyal', 'symbol' => 'ر.ق', 'code' => 'QAR'],
        'SAR' => ['name' => 'Saudi Riyal', 'symbol' => '﷼', 'code' => 'SAR'],
        'SGD' => ['name' => 'Singapore Dollar', 'symbol' => 'S$', 'code' => 'SGD'],
        'KRW' => ['name' => 'South Korean Won', 'symbol' => '₩', 'code' => 'KRW'],
        'LKR' => ['name' => 'Sri Lankan Rupee', 'symbol' => '₨', 'code' => 'LKR'],
        'SYP' => ['name' => 'Syrian Pound', 'symbol' => '£', 'code' => 'SYP'],
        'TWD' => ['name' => 'New Taiwan Dollar', 'symbol' => 'NT$', 'code' => 'TWD'],
        'TJS' => ['name' => 'Tajikistani Somoni', 'symbol' => 'SM', 'code' => 'TJS'],
        'THB' => ['name' => 'Thai Baht', 'symbol' => '฿', 'code' => 'THB'],
        'TMT' => ['name' => 'Turkmenistani Manat', 'symbol' => 'T', 'code' => 'TMT'],
        'AED' => ['name' => 'UAE Dirham', 'symbol' => 'د.إ', 'code' => 'AED'],
        'UZS' => ['name' => 'Uzbekistani Som', 'symbol' => 'лв', 'code' => 'UZS'],
        'VND' => ['name' => 'Vietnamese Dong', 'symbol' => '₫', 'code' => 'VND'],
        'YER' => ['name' => 'Yemeni Rial', 'symbol' => '﷼', 'code' => 'YER'],
        
        // European currencies
        'ALL' => ['name' => 'Albanian Lek', 'symbol' => 'L', 'code' => 'ALL'],
        'BAM' => ['name' => 'Bosnia-Herzegovina Convertible Mark', 'symbol' => 'KM', 'code' => 'BAM'],
        'BGN' => ['name' => 'Bulgarian Lev', 'symbol' => 'лв', 'code' => 'BGN'],
        'HRK' => ['name' => 'Croatian Kuna', 'symbol' => 'kn', 'code' => 'HRK'],
        'CZK' => ['name' => 'Czech Republic Koruna', 'symbol' => 'Kč', 'code' => 'CZK'],
        'DKK' => ['name' => 'Danish Krone', 'symbol' => 'kr', 'code' => 'DKK'],
        'HUF' => ['name' => 'Hungarian Forint', 'symbol' => 'Ft', 'code' => 'HUF'],
        'ISK' => ['name' => 'Icelandic Króna', 'symbol' => 'kr', 'code' => 'ISK'],
        'MKD' => ['name' => 'Macedonian Denar', 'symbol' => 'ден', 'code' => 'MKD'],
        'MDL' => ['name' => 'Moldovan Leu', 'symbol' => 'L', 'code' => 'MDL'],
        'NOK' => ['name' => 'Norwegian Krone', 'symbol' => 'kr', 'code' => 'NOK'],
        'PLN' => ['name' => 'Polish Zloty', 'symbol' => 'zł', 'code' => 'PLN'],
        'RON' => ['name' => 'Romanian Leu', 'symbol' => 'lei', 'code' => 'RON'],
        'RSD' => ['name' => 'Serbian Dinar', 'symbol' => 'Дин.', 'code' => 'RSD'],
        'SEK' => ['name' => 'Swedish Krona', 'symbol' => 'kr', 'code' => 'SEK'],
        'TRY' => ['name' => 'Turkish Lira', 'symbol' => '₺', 'code' => 'TRY'],
        'UAH' => ['name' => 'Ukrainian Hryvnia', 'symbol' => '₴', 'code' => 'UAH'],
        'RUB' => ['name' => 'Russian Ruble', 'symbol' => '₽', 'code' => 'RUB'],
        'BYN' => ['name' => 'Belarusian Ruble', 'symbol' => 'Br', 'code' => 'BYN'],
        
        // American currencies
        'ARS' => ['name' => 'Argentine Peso', 'symbol' => '$', 'code' => 'ARS'],
        'BOB' => ['name' => 'Bolivian Boliviano', 'symbol' => '$b', 'code' => 'BOB'],
        'BRL' => ['name' => 'Brazilian Real', 'symbol' => 'R$', 'code' => 'BRL'],
        'CLP' => ['name' => 'Chilean Peso', 'symbol' => '$', 'code' => 'CLP'],
        'COP' => ['name' => 'Colombian Peso', 'symbol' => '$', 'code' => 'COP'],
        'CRC' => ['name' => 'Costa Rican Colón', 'symbol' => '₡', 'code' => 'CRC'],
        'CUP' => ['name' => 'Cuban Peso', 'symbol' => '₱', 'code' => 'CUP'],
        'DOP' => ['name' => 'Dominican Peso', 'symbol' => 'RD$', 'code' => 'DOP'],
        'XCD' => ['name' => 'East Caribbean Dollar', 'symbol' => '$', 'code' => 'XCD'],
        'SVC' => ['name' => 'Salvadoran Colón', 'symbol' => '$', 'code' => 'SVC'],
        'GTQ' => ['name' => 'Guatemalan Quetzal', 'symbol' => 'Q', 'code' => 'GTQ'],
        'GYD' => ['name' => 'Guyanaese Dollar', 'symbol' => '$', 'code' => 'GYD'],
        'HTG' => ['name' => 'Haitian Gourde', 'symbol' => 'G', 'code' => 'HTG'],
        'HNL' => ['name' => 'Honduran Lempira', 'symbol' => 'L', 'code' => 'HNL'],
        'JMD' => ['name' => 'Jamaican Dollar', 'symbol' => 'J$', 'code' => 'JMD'],
        'MXN' => ['name' => 'Mexican Peso', 'symbol' => '$', 'code' => 'MXN'],
        'NIO' => ['name' => 'Nicaraguan Córdoba', 'symbol' => 'C$', 'code' => 'NIO'],
        'PAB' => ['name' => 'Panamanian Balboa', 'symbol' => 'B/.', 'code' => 'PAB'],
        'PYG' => ['name' => 'Paraguayan Guarani', 'symbol' => 'Gs', 'code' => 'PYG'],
        'PEN' => ['name' => 'Peruvian Sol', 'symbol' => 'S/', 'code' => 'PEN'],
        'SRD' => ['name' => 'Surinamese Dollar', 'symbol' => '$', 'code' => 'SRD'],
        'TTD' => ['name' => 'Trinidad and Tobago Dollar', 'symbol' => 'TT$', 'code' => 'TTD'],
        'UYU' => ['name' => 'Uruguayan Peso', 'symbol' => '$U', 'code' => 'UYU'],
        'VES' => ['name' => 'Venezuelan Bolívar', 'symbol' => 'Bs.S', 'code' => 'VES'],
        
        // Oceanian currencies
        'NZD' => ['name' => 'New Zealand Dollar', 'symbol' => 'NZ$', 'code' => 'NZD'],
        'FJD' => ['name' => 'Fijian Dollar', 'symbol' => 'FJ$', 'code' => 'FJD'],
        'PGK' => ['name' => 'Papua New Guinean Kina', 'symbol' => 'K', 'code' => 'PGK'],
        'SBD' => ['name' => 'Solomon Islands Dollar', 'symbol' => '$', 'code' => 'SBD'],
        'VUV' => ['name' => 'Vanuatu Vatu', 'symbol' => 'Vt', 'code' => 'VUV'],
        'WST' => ['name' => 'Samoan Tala', 'symbol' => 'WS$', 'code' => 'WST'],
    ];
}

/**
 * Get currencies organized by region for better UX
 * @return array Currencies grouped by geographical regions
 */
function getCurrenciesByRegion() {
    $currencies = getAllWorldCurrencies();
    
    return [
        'Popular' => ['USD', 'EUR', 'GBP', 'JPY', 'CAD', 'AUD', 'CHF', 'CNY'],
        'Africa' => ['DZD', 'AOA', 'BWP', 'BIF', 'CVE', 'KMF', 'CDF', 'DJF', 'EGP', 'ERN', 'SZL', 'ETB', 'GMD', 'GHS', 'GNF', 'GWP', 'KES', 'LSL', 'LRD', 'LYD', 'MGA', 'MWK', 'MRU', 'MUR', 'MAD', 'MZN', 'NAD', 'NGN', 'RWF', 'STN', 'SCR', 'SLL', 'SOS', 'ZAR', 'SSP', 'SDG', 'TZS', 'TND', 'UGX', 'ZMW', 'ZWL'],
        'Asia' => ['AFN', 'AMD', 'AZN', 'BHD', 'BDT', 'BTN', 'BND', 'KHR', 'CNY', 'GEL', 'HKD', 'INR', 'IDR', 'IRR', 'IQD', 'ILS', 'JPY', 'JOD', 'KZT', 'KWD', 'KGS', 'LAK', 'LBP', 'MOP', 'MYR', 'MVR', 'MNT', 'MMK', 'NPR', 'KPW', 'OMR', 'PKR', 'PHP', 'QAR', 'SAR', 'SGD', 'KRW', 'LKR', 'SYP', 'TWD', 'TJS', 'THB', 'TMT', 'AED', 'UZS', 'VND', 'YER'],
        'Europe' => ['EUR', 'ALL', 'BAM', 'BGN', 'HRK', 'CZK', 'DKK', 'GBP', 'HUF', 'ISK', 'MKD', 'MDL', 'NOK', 'PLN', 'RON', 'RSD', 'SEK', 'CHF', 'TRY', 'UAH', 'RUB', 'BYN'],
        'Americas' => ['USD', 'ARS', 'BOB', 'BRL', 'CAD', 'CLP', 'COP', 'CRC', 'CUP', 'DOP', 'XCD', 'SVC', 'GTQ', 'GYD', 'HTG', 'HNL', 'JMD', 'MXN', 'NIO', 'PAB', 'PYG', 'PEN', 'SRD', 'TTD', 'UYU', 'VES'],
        'Oceania' => ['AUD', 'NZD', 'FJD', 'PGK', 'SBD', 'VUV', 'WST', 'TOP']
    ];
}

/**
 * Convert currency array to the format expected by registration.php
 * @return array Currency array formatted for database insertion
 */
function getCurrenciesForRegistration() {
    $currencies = getAllWorldCurrencies();
    $formattedCurrencies = [];
    $id = 1;
    
    foreach ($currencies as $code => $currency) {
        $formattedCurrencies[] = [
            'id' => $id++,
            'name' => $currency['name'],
            'symbol' => $currency['symbol'],
            'code' => $currency['code']
        ];
    }
    
    return $formattedCurrencies;
}

/**
 * Render currency dropdown with regional grouping
 * @param string $selectedCode Currently selected currency code
 * @param string $name HTML name attribute for the select element
 * @param string $id HTML id attribute for the select element
 * @param array $attributes Additional HTML attributes
 * @return string HTML select dropdown
 */
function renderCurrencyDropdown($selectedCode = '', $name = 'currency_code', $id = 'currency_code', $attributes = []) {
    $currencies = getAllWorldCurrencies();
    $regions = getCurrenciesByRegion();
    
    $attrString = '';
    foreach ($attributes as $attr => $value) {
        $attrString .= " $attr=\"$value\"";
    }
    
    $html = "<select name=\"$name\" id=\"$id\"$attrString>";
    $html .= '<option value="">Select Currency</option>';
    
    foreach ($regions as $regionName => $codes) {
        $html .= "<optgroup label=\"$regionName\">";
        foreach ($codes as $code) {
            if (isset($currencies[$code])) {
                $currency = $currencies[$code];
                $selected = ($code === $selectedCode) ? 'selected' : '';
                $html .= "<option value=\"$code\" $selected>{$currency['name']} ({$currency['symbol']}) - $code</option>";
            }
        }
        $html .= "</optgroup>";
    }
    
    $html .= '</select>';
    return $html;
}
?>