<?php

class StoreCountry extends StoreRegion {
	/** @var string */
	public $isoCode3 = null;
	/** @var StoreRegion[] */
	public $regions = array();

	public function setISOCode3($code) {
		$this->isoCode3 = $code;
		return $this;
	}

	/**
	 * @param StoreRegion $region
	 * @return StoreCountry
	 */
	public function addRegion(StoreRegion $region) {
		$this->regions[] = $region;
		return $this;
	}
	
	/**
	 * @param string $code country code.
	 * @return StoreCountry
	 */
	public static function findByCode($code) {
		if ($code) foreach (self::buildList() as $li) {
			if ($li->code == $code) return $li;
		}
		return null;
	}

	/**
	 * @param string $isoCode3 country code.
	 * @return StoreCountry
	 */
	public static function findByIsoCode3($isoCode3) {
		if ($isoCode3) foreach (self::buildList() as $li) {
			if ($li->isoCode3 == $isoCode3) return $li;
		}
		return null;
	}


	/**
	 * @param string $countryCode country code.
	 * @param string $regionName region name.
	 * @return array
	 */
	public static function findCountryAndRegion($countryCode, $regionName) {
		$country = $region = null;
		if ($countryCode) {
			foreach (self::buildList() as $li) {
				if ($li->code == $countryCode) {
					$country = $li;
					if( $regionName ) {
						foreach( $country->regions as $r ) {
							if( $r->name == $regionName ) {
								$region = $r;
								break;
							}
						}
					}
					break;
				}
			}
		}
		return array($country, $region);
	}

	/** @return StoreCountry[] */
	public static function buildList() {
		return array(
			StoreCountry::create('AF', 'Afghanistan')->setISOCode3('AFG'),
			StoreCountry::create('AX', 'Åland Islands')->setISOCode3('ALA'),
			StoreCountry::create('AL', 'Albania')->setISOCode3('ALB'),
			StoreCountry::create('DZ', 'Algeria')->setISOCode3('DZA'),
			StoreCountry::create('AS', 'American Samoa')->setISOCode3('ASM'),
			StoreCountry::create('AD', 'Andorra')->setISOCode3('AND'),
			StoreCountry::create('AO', 'Angola')->setISOCode3('AGO'),
			StoreCountry::create('AI', 'Anguilla')->setISOCode3('AIA'),
			StoreCountry::create('AQ', 'Antarctica')->setISOCode3('ATA'),
			StoreCountry::create('AG', 'Antigua and Barbuda')->setISOCode3('ATG'),
			StoreCountry::create('AR', 'Argentina')->setISOCode3('ARG'),
			StoreCountry::create('AM', 'Armenia')->setISOCode3('ARM'),
			StoreCountry::create('AW', 'Aruba')->setISOCode3('ABW'),
			StoreCountry::create('AU', 'Australia')->setISOCode3('AUS'),
			StoreCountry::create('AT', 'Austria')->setISOCode3('AUT'),
			StoreCountry::create('AZ', 'Azerbaijan')->setISOCode3('AZE'),
			StoreCountry::create('BS', 'Bahamas')->setISOCode3('BHS'),
			StoreCountry::create('BH', 'Bahrain')->setISOCode3('BHR'),
			StoreCountry::create('BD', 'Bangladesh')->setISOCode3('BGD'),
			StoreCountry::create('BB', 'Barbados')->setISOCode3('BRB'),
			StoreCountry::create('BY', 'Belarus')->setISOCode3('BLR'),
			StoreCountry::create('BE', 'Belgium')->setISOCode3('BEL'),
			StoreCountry::create('BZ', 'Belize')->setISOCode3('BLZ'),
			StoreCountry::create('BJ', 'Benin')->setISOCode3('BEN'),
			StoreCountry::create('BM', 'Bermuda')->setISOCode3('BMU'),
			StoreCountry::create('BT', 'Bhutan')->setISOCode3('BTN'),
			StoreCountry::create('BO', 'Bolivia')->setISOCode3('BOL'),
			StoreCountry::create('BA', 'Bosnia and Herzegovina')->setISOCode3('BIH'),
			StoreCountry::create('BW', 'Botswana')->setISOCode3('BWA'),
			StoreCountry::create('BV', 'Bouvet Island')->setISOCode3('BVT'),
			StoreCountry::create('BR', 'Brazil')->setISOCode3('BRA'),
			StoreCountry::create('BQ', 'British Antarctic Territory')->setISOCode3('BES'),
			StoreCountry::create('IO', 'British Indian Ocean Territory')->setISOCode3('IOT'),
			StoreCountry::create('VG', 'British Virgin Islands')->setISOCode3('VGB'),
			StoreCountry::create('BN', 'Brunei')->setISOCode3('BRN'),
			StoreCountry::create('BG', 'Bulgaria')->setISOCode3('BGR'),
			StoreCountry::create('BF', 'Burkina Faso')->setISOCode3('BFA'),
			StoreCountry::create('BI', 'Burundi')->setISOCode3('BDI'),
			StoreCountry::create('KH', 'Cambodia')->setISOCode3('KHM'),
			StoreCountry::create('CM', 'Cameroon')->setISOCode3('CMR'),
			StoreCountry::create('CA', 'Canada')->setISOCode3('CAN'),
			StoreCountry::create('CT', 'Canton and Enderbury Islands'),
			StoreCountry::create('CV', 'Cape Verde')->setISOCode3('CPV'),
			StoreCountry::create('KY', 'Cayman Islands')->setISOCode3('CYM'),
			StoreCountry::create('CF', 'Central African Republic')->setISOCode3('CAF'),
			StoreCountry::create('TD', 'Chad')->setISOCode3('TCD'),
			StoreCountry::create('CL', 'Chile')->setISOCode3('CHL'),
			StoreCountry::create('CN', 'China')->setISOCode3('CHN'),
			StoreCountry::create('CX', 'Christmas Island')->setISOCode3('CXR'),
			StoreCountry::create('CC', 'Cocos [Keeling] Islands')->setISOCode3('CCK'),
			StoreCountry::create('CO', 'Colombia')->setISOCode3('COL'),
			StoreCountry::create('KM', 'Comoros')->setISOCode3('COM'),
			StoreCountry::create('CG', 'Congo - Brazzaville')->setISOCode3('COG'),
			StoreCountry::create('CD', 'Congo - Kinshasa')->setISOCode3('COD'),
			StoreCountry::create('CK', 'Cook Islands')->setISOCode3('COK'),
			StoreCountry::create('CR', 'Costa Rica')->setISOCode3('CRI'),
			StoreCountry::create('CI', 'Côte d’Ivoire')->setISOCode3('CIV'),
			StoreCountry::create('HR', 'Croatia')->setISOCode3('HRV'),
			StoreCountry::create('CU', 'Cuba')->setISOCode3('CUB'),
			StoreCountry::create('CY', 'Cyprus')->setISOCode3('CYP'),
			StoreCountry::create('CZ', 'Czech Republic')->setISOCode3('CZE'),
			StoreCountry::create('DK', 'Denmark')->setISOCode3('DNK'),
			StoreCountry::create('DJ', 'Djibouti')->setISOCode3('DJI'),
			StoreCountry::create('DM', 'Dominica')->setISOCode3('DMA'),
			StoreCountry::create('DO', 'Dominican Republic')->setISOCode3('DOM'),
			StoreCountry::create('NQ', 'Dronning Maud Land'),
			StoreCountry::create('DD', 'East Germany'),
			StoreCountry::create('EC', 'Ecuador')->setISOCode3('ECU'),
			StoreCountry::create('EG', 'Egypt')->setISOCode3('EGY'),
			StoreCountry::create('SV', 'El Salvador')->setISOCode3('SLV'),
			StoreCountry::create('GQ', 'Equatorial Guinea')->setISOCode3('GNQ'),
			StoreCountry::create('ER', 'Eritrea')->setISOCode3('ERI'),
			StoreCountry::create('EE', 'Estonia')->setISOCode3('EST'),
			StoreCountry::create('ET', 'Ethiopia')->setISOCode3('ETH'),
			StoreCountry::create('FK', 'Falkland Islands')->setISOCode3('FLK'),
			StoreCountry::create('FO', 'Faroe Islands')->setISOCode3('FRO'),
			StoreCountry::create('FJ', 'Fiji')->setISOCode3('FJI'),
			StoreCountry::create('FI', 'Finland')->setISOCode3('FIN'),
			StoreCountry::create('FR', 'France')->setISOCode3('FRA'),
			StoreCountry::create('GF', 'French Guiana')->setISOCode3('GUF'),
			StoreCountry::create('PF', 'French Polynesia')->setISOCode3('PYF'),
			StoreCountry::create('FQ', 'French Southern and Antarctic Territories'),
			StoreCountry::create('TF', 'French Southern Territories')->setISOCode3('ATF'),
			StoreCountry::create('GA', 'Gabon')->setISOCode3('GAB'),
			StoreCountry::create('GM', 'Gambia')->setISOCode3('GMB'),
			StoreCountry::create('GE', 'Georgia')->setISOCode3('GEO'),
			StoreCountry::create('DE', 'Germany')->setISOCode3('DEU'),
			StoreCountry::create('GH', 'Ghana')->setISOCode3('GHA'),
			StoreCountry::create('GI', 'Gibraltar')->setISOCode3('GIB'),
			StoreCountry::create('GR', 'Greece')->setISOCode3('GRC'),
			StoreCountry::create('GL', 'Greenland')->setISOCode3('GRL'),
			StoreCountry::create('GD', 'Grenada')->setISOCode3('GRD'),
			StoreCountry::create('GP', 'Guadeloupe')->setISOCode3('GLP'),
			StoreCountry::create('GU', 'Guam')->setISOCode3('GUM'),
			StoreCountry::create('GT', 'Guatemala')->setISOCode3('GTM'),
			StoreCountry::create('GG', 'Guernsey')->setISOCode3('GGY'),
			StoreCountry::create('GN', 'Guinea')->setISOCode3('GIN'),
			StoreCountry::create('GW', 'Guinea-Bissau')->setISOCode3('GNB'),
			StoreCountry::create('GY', 'Guyana')->setISOCode3('GUY'),
			StoreCountry::create('HT', 'Haiti')->setISOCode3('HTI'),
			StoreCountry::create('HM', 'Heard Island and McDonald Islands')->setISOCode3('HMD'),
			StoreCountry::create('HN', 'Honduras')->setISOCode3('HND'),
			StoreCountry::create('HK', 'Hong Kong SAR China')->setISOCode3('HKG'),
			StoreCountry::create('HU', 'Hungary')->setISOCode3('HUN'),
			StoreCountry::create('IS', 'Iceland')->setISOCode3('ISL'),
			StoreCountry::create('IN', 'India')->setISOCode3('IND'),
			StoreCountry::create('ID', 'Indonesia')->setISOCode3('IDN'),
			StoreCountry::create('IR', 'Iran')->setISOCode3('IRN'),
			StoreCountry::create('IQ', 'Iraq')->setISOCode3('IRQ'),
			StoreCountry::create('IE', 'Ireland')->setISOCode3('IRL'),
			StoreCountry::create('IM', 'Isle of Man')->setISOCode3('IMN'),
			StoreCountry::create('IL', 'Israel')->setISOCode3('ISR'),
			StoreCountry::create('IT', 'Italy')->setISOCode3('ITA'),
			StoreCountry::create('JM', 'Jamaica')->setISOCode3('JAM'),
			StoreCountry::create('JP', 'Japan')->setISOCode3('JPN'),
			StoreCountry::create('JE', 'Jersey')->setISOCode3('JEY'),
			StoreCountry::create('JT', 'Johnston Island'),
			StoreCountry::create('JO', 'Jordan')->setISOCode3('JOR'),
			StoreCountry::create('KZ', 'Kazakhstan')->setISOCode3('KAZ'),
			StoreCountry::create('KE', 'Kenya')->setISOCode3('KEN'),
			StoreCountry::create('KI', 'Kiribati')->setISOCode3('KIR'),
			StoreCountry::create('KW', 'Kuwait')->setISOCode3('KWT'),
			StoreCountry::create('KG', 'Kyrgyzstan')->setISOCode3('KGZ'),
			StoreCountry::create('LA', 'Laos')->setISOCode3('LAO'),
			StoreCountry::create('LV', 'Latvia')->setISOCode3('LVA'),
			StoreCountry::create('LB', 'Lebanon')->setISOCode3('LBN'),
			StoreCountry::create('LS', 'Lesotho')->setISOCode3('LSO'),
			StoreCountry::create('LR', 'Liberia')->setISOCode3('LBR'),
			StoreCountry::create('LY', 'Libya')->setISOCode3('LBY'),
			StoreCountry::create('LI', 'Liechtenstein')->setISOCode3('LIE'),
			StoreCountry::create('LT', 'Lithuania')->setISOCode3('LTU'),
			StoreCountry::create('LU', 'Luxembourg')->setISOCode3('LUX'),
			StoreCountry::create('MO', 'Macau SAR China')->setISOCode3('MAC'),
			StoreCountry::create('MK', 'Macedonia')->setISOCode3('MKD'),
			StoreCountry::create('MG', 'Madagascar')->setISOCode3('MDG'),
			StoreCountry::create('MW', 'Malawi')->setISOCode3('MWI'),
			StoreCountry::create('MY', 'Malaysia')->setISOCode3('MYS'),
			StoreCountry::create('MV', 'Maldives')->setISOCode3('MDV'),
			StoreCountry::create('ML', 'Mali')->setISOCode3('MLI'),
			StoreCountry::create('MT', 'Malta')->setISOCode3('MLT'),
			StoreCountry::create('MH', 'Marshall Islands')->setISOCode3('MHL'),
			StoreCountry::create('MQ', 'Martinique')->setISOCode3('MTQ'),
			StoreCountry::create('MR', 'Mauritania')->setISOCode3('MRT'),
			StoreCountry::create('MU', 'Mauritius')->setISOCode3('MUS'),
			StoreCountry::create('YT', 'Mayotte')->setISOCode3('MYT'),
			StoreCountry::create('FX', 'Metropolitan France'),
			StoreCountry::create('MX', 'Mexico')->setISOCode3('MEX'),
			StoreCountry::create('FM', 'Micronesia')->setISOCode3('FSM'),
			StoreCountry::create('MI', 'Midway Islands'),
			StoreCountry::create('MD', 'Moldova')->setISOCode3('MDA'),
			StoreCountry::create('MC', 'Monaco')->setISOCode3('MCO'),
			StoreCountry::create('MN', 'Mongolia')->setISOCode3('MNG'),
			StoreCountry::create('ME', 'Montenegro')->setISOCode3('MNE'),
			StoreCountry::create('MS', 'Montserrat')->setISOCode3('MSR'),
			StoreCountry::create('MA', 'Morocco')->setISOCode3('MAR'),
			StoreCountry::create('MZ', 'Mozambique')->setISOCode3('MOZ'),
			StoreCountry::create('MM', 'Myanmar [Burma]')->setISOCode3('MMR'),
			StoreCountry::create('NA', 'Namibia')->setISOCode3('NAM'),
			StoreCountry::create('NR', 'Nauru')->setISOCode3('NRU'),
			StoreCountry::create('NP', 'Nepal')->setISOCode3('NPL'),
			StoreCountry::create('NL', 'Netherlands')->setISOCode3('NLD'),
			StoreCountry::create('AN', 'Netherlands Antilles'),
			StoreCountry::create('NT', 'Neutral Zone'),
			StoreCountry::create('NC', 'New Caledonia')->setISOCode3('NCL'),
			StoreCountry::create('NZ', 'New Zealand')->setISOCode3('NZL'),
			StoreCountry::create('NI', 'Nicaragua')->setISOCode3('NIC'),
			StoreCountry::create('NE', 'Niger')->setISOCode3('NER'),
			StoreCountry::create('NG', 'Nigeria')->setISOCode3('NGA'),
			StoreCountry::create('NU', 'Niue')->setISOCode3('NIU'),
			StoreCountry::create('NF', 'Norfolk Island')->setISOCode3('NFK'),
			StoreCountry::create('KP', 'North Korea')->setISOCode3('PRK'),
			StoreCountry::create('VD', 'North Vietnam'),
			StoreCountry::create('MP', 'Northern Mariana Islands')->setISOCode3('MNP'),
			StoreCountry::create('NO', 'Norway')->setISOCode3('NOR'),
			StoreCountry::create('OM', 'Oman')->setISOCode3('OMN'),
			StoreCountry::create('PC', 'Pacific Islands Trust Territory'),
			StoreCountry::create('PK', 'Pakistan')->setISOCode3('PAK'),
			StoreCountry::create('PW', 'Palau')->setISOCode3('PLW'),
			StoreCountry::create('PS', 'Palestinian Territories')->setISOCode3('PSE'),
			StoreCountry::create('PA', 'Panama')->setISOCode3('PAN'),
			StoreCountry::create('PZ', 'Panama Canal Zone'),
			StoreCountry::create('PG', 'Papua New Guinea')->setISOCode3('PNG'),
			StoreCountry::create('PY', 'Paraguay')->setISOCode3('PRY'),
			StoreCountry::create('YD', 'People\'s Democratic Republic of Yemen'),
			StoreCountry::create('PE', 'Peru')->setISOCode3('PER'),
			StoreCountry::create('PH', 'Philippines')->setISOCode3('PHL'),
			StoreCountry::create('PN', 'Pitcairn Islands')->setISOCode3('PCN'),
			StoreCountry::create('PL', 'Poland')->setISOCode3('POL'),
			StoreCountry::create('PT', 'Portugal')->setISOCode3('PRT'),
			StoreCountry::create('PR', 'Puerto Rico')->setISOCode3('PRI'),
			StoreCountry::create('QA', 'Qatar')->setISOCode3('QAT'),
			StoreCountry::create('RE', 'Réunion')->setISOCode3('REU'),
			StoreCountry::create('RO', 'Romania')->setISOCode3('ROU'),
			StoreCountry::create('RU', 'Russia')->setISOCode3('RUS'),
			StoreCountry::create('RW', 'Rwanda')->setISOCode3('RWA'),
			StoreCountry::create('BL', 'Saint Barthélemy')->setISOCode3('BLM'),
			StoreCountry::create('SH', 'Saint Helena')->setISOCode3('SHN'),
			StoreCountry::create('KN', 'Saint Kitts and Nevis')->setISOCode3('KNA'),
			StoreCountry::create('LC', 'Saint Lucia')->setISOCode3('LCA'),
			StoreCountry::create('MF', 'Saint Martin')->setISOCode3('MAF'),
			StoreCountry::create('PM', 'Saint Pierre and Miquelon')->setISOCode3('SPM'),
			StoreCountry::create('VC', 'Saint Vincent and the Grenadines')->setISOCode3('VCT'),
			StoreCountry::create('WS', 'Samoa')->setISOCode3('WSM'),
			StoreCountry::create('SM', 'San Marino')->setISOCode3('SMR'),
			StoreCountry::create('ST', 'São Tomé and Príncipe')->setISOCode3('STP'),
			StoreCountry::create('SA', 'Saudi Arabia')->setISOCode3('SAU'),
			StoreCountry::create('SN', 'Senegal')->setISOCode3('SEN'),
			StoreCountry::create('RS', 'Serbia')->setISOCode3('SRB'),
			StoreCountry::create('SC', 'Seychelles')->setISOCode3('SYC'),
			StoreCountry::create('SL', 'Sierra Leone')->setISOCode3('SLE'),
			StoreCountry::create('SG', 'Singapore')->setISOCode3('SGP'),
			StoreCountry::create('SK', 'Slovakia')->setISOCode3('SVK'),
			StoreCountry::create('SI', 'Slovenia')->setISOCode3('SVN'),
			StoreCountry::create('SB', 'Solomon Islands')->setISOCode3('SLB'),
			StoreCountry::create('SO', 'Somalia')->setISOCode3('SOM'),
			StoreCountry::create('ZA', 'South Africa')->setISOCode3('ZAF'),
			StoreCountry::create('GS', 'South Georgia and the South Sandwich Islands')->setISOCode3('SGS'),
			StoreCountry::create('KR', 'South Korea')->setISOCode3('KOR'),
			StoreCountry::create('ES', 'Spain')->setISOCode3('ESP'),
			StoreCountry::create('LK', 'Sri Lanka')->setISOCode3('LKA'),
			StoreCountry::create('SD', 'Sudan')->setISOCode3('SDN'),
			StoreCountry::create('SR', 'Suriname')->setISOCode3('SUR'),
			StoreCountry::create('SJ', 'Svalbard and Jan Mayen')->setISOCode3('SJM'),
			StoreCountry::create('SZ', 'Swaziland')->setISOCode3('SWZ'),
			StoreCountry::create('SE', 'Sweden')->setISOCode3('SWE'),
			StoreCountry::create('CH', 'Switzerland')->setISOCode3('CHE'),
			StoreCountry::create('SY', 'Syria')->setISOCode3('SYR'),
			StoreCountry::create('TW', 'Taiwan')->setISOCode3('TWN'),
			StoreCountry::create('TJ', 'Tajikistan')->setISOCode3('TJK'),
			StoreCountry::create('TZ', 'Tanzania')->setISOCode3('TZA'),
			StoreCountry::create('TH', 'Thailand')->setISOCode3('THA'),
			StoreCountry::create('TL', 'Timor-Leste')->setISOCode3('TLS'),
			StoreCountry::create('TG', 'Togo')->setISOCode3('TGO'),
			StoreCountry::create('TK', 'Tokelau')->setISOCode3('TKL'),
			StoreCountry::create('TO', 'Tonga')->setISOCode3('TON'),
			StoreCountry::create('TT', 'Trinidad and Tobago')->setISOCode3('TTO'),
			StoreCountry::create('TN', 'Tunisia')->setISOCode3('TUN'),
			StoreCountry::create('TR', 'Turkey')->setISOCode3('TUR'),
			StoreCountry::create('TM', 'Turkmenistan')->setISOCode3('TKM'),
			StoreCountry::create('TC', 'Turks and Caicos Islands')->setISOCode3('TCA'),
			StoreCountry::create('TV', 'Tuvalu')->setISOCode3('TUV'),
			StoreCountry::create('UM', 'U.S. Minor Outlying Islands')->setISOCode3('UMI'),
			StoreCountry::create('PU', 'U.S. Miscellaneous Pacific Islands'),
			StoreCountry::create('VI', 'U.S. Virgin Islands')->setISOCode3('VIR'),
			StoreCountry::create('UG', 'Uganda')->setISOCode3('UGA'),
			StoreCountry::create('UA', 'Ukraine')->setISOCode3('UKR'),
			StoreCountry::create('SU', 'Union of Soviet Socialist Republics'),
			StoreCountry::create('AE', 'United Arab Emirates')->setISOCode3('ARE'),
			StoreCountry::create('GB', 'United Kingdom')->setISOCode3('GBR'),
			StoreCountry::create('US', 'United States')->setISOCode3('USA')
				->addRegion(new StoreRegion('AL', 'Alabama'))
				->addRegion(new StoreRegion('AK', 'Alaska'))
				->addRegion(new StoreRegion('AZ', 'Arizona'))
				->addRegion(new StoreRegion('AR', 'Arkansas'))
				->addRegion(new StoreRegion('CA', 'California'))
				->addRegion(new StoreRegion('CO', 'Colorado'))
				->addRegion(new StoreRegion('CT', 'Connecticut'))
				->addRegion(new StoreRegion('DE', 'Delaware'))
				->addRegion(new StoreRegion('FL', 'Florida'))
				->addRegion(new StoreRegion('GA', 'Georgia'))
				->addRegion(new StoreRegion('HI', 'Hawaii'))
				->addRegion(new StoreRegion('ID', 'Idaho'))
				->addRegion(new StoreRegion('IL', 'Illinois'))
				->addRegion(new StoreRegion('IN', 'Indiana'))
				->addRegion(new StoreRegion('IA', 'Iowa'))
				->addRegion(new StoreRegion('KS', 'Kansas'))
				->addRegion(new StoreRegion('KY', 'Kentucky'))
				->addRegion(new StoreRegion('LA', 'Louisiana'))
				->addRegion(new StoreRegion('ME', 'Maine'))
				->addRegion(new StoreRegion('MD', 'Maryland'))
				->addRegion(new StoreRegion('MA', 'Massachusetts'))
				->addRegion(new StoreRegion('MI', 'Michigan'))
				->addRegion(new StoreRegion('MN', 'Minnesota'))
				->addRegion(new StoreRegion('MS', 'Mississippi'))
				->addRegion(new StoreRegion('MO', 'Missouri'))
				->addRegion(new StoreRegion('MT', 'Montana'))
				->addRegion(new StoreRegion('NE', 'Nebraska'))
				->addRegion(new StoreRegion('NV', 'Nevada'))
				->addRegion(new StoreRegion('NH', 'New Hampshire'))
				->addRegion(new StoreRegion('NJ', 'New Jersey'))
				->addRegion(new StoreRegion('NM', 'New Mexico'))
				->addRegion(new StoreRegion('NY', 'New York'))
				->addRegion(new StoreRegion('NC', 'North Carolina'))
				->addRegion(new StoreRegion('ND', 'North Dakota'))
				->addRegion(new StoreRegion('OH', 'Ohio'))
				->addRegion(new StoreRegion('OK', 'Oklahoma'))
				->addRegion(new StoreRegion('OR', 'Oregon'))
				->addRegion(new StoreRegion('PA', 'Pennsylvania'))
				->addRegion(new StoreRegion('RI', 'Rhode Island'))
				->addRegion(new StoreRegion('SC', 'South Carolina'))
				->addRegion(new StoreRegion('SD', 'South Dakota'))
				->addRegion(new StoreRegion('TN', 'Tennessee'))
				->addRegion(new StoreRegion('TX', 'Texas'))
				->addRegion(new StoreRegion('UT', 'Utah'))
				->addRegion(new StoreRegion('VT', 'Vermont'))
				->addRegion(new StoreRegion('VA', 'Virginia'))
				->addRegion(new StoreRegion('WA', 'Washington'))
				->addRegion(new StoreRegion('DC', 'Washington D.C. (District of Columbia)'))
				->addRegion(new StoreRegion('WV', 'West Virginia'))
				->addRegion(new StoreRegion('WI', 'Wisconsin'))
				->addRegion(new StoreRegion('WY', 'Wyoming')),
			StoreCountry::create('ZZ', 'Unknown or Invalid Region'),
			StoreCountry::create('UY', 'Uruguay')->setISOCode3('URY'),
			StoreCountry::create('UZ', 'Uzbekistan')->setISOCode3('UZB'),
			StoreCountry::create('VU', 'Vanuatu')->setISOCode3('VUT'),
			StoreCountry::create('VA', 'Vatican City')->setISOCode3('VAT'),
			StoreCountry::create('VE', 'Venezuela')->setISOCode3('VEN'),
			StoreCountry::create('VN', 'Vietnam')->setISOCode3('VNM'),
			StoreCountry::create('WK', 'Wake Island'),
			StoreCountry::create('WF', 'Wallis and Futuna')->setISOCode3('WLF'),
			StoreCountry::create('EH', 'Western Sahara')->setISOCode3('ESH'),
			StoreCountry::create('YE', 'Yemen')->setISOCode3('YEM'),
			StoreCountry::create('ZM', 'Zambia')->setISOCode3('ZMB'),
			StoreCountry::create('ZW', 'Zimbabwe')->setISOCode3('ZWE')
		);
	}
	
}
