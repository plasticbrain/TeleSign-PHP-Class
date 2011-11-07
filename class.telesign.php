<?php
//------------------------------------------------------------------------------
// Custom class to interact with the TeleSign API
// (c) 2011 Mike Everhart | everhart@eaglewebassets.com | mikeeverhart.net
//
//--- Changelog ----------------------------------------------------------------
//
// 2010-04-11 	- Created the initial class
//------------------------------------------------------------------------------


class Telesign { 
	
	//---------------------------------------------------------------------------
	// Settings/Configuration
	//---------------------------------------------------------------------------
	protected $config = array( 
		
		// Telesign Customer ID
		'customer_id' => '', 
		
		// Telesign Authentication ID
		'authentication_id' => '' ,
		
		// The API URL (where data needs to be POSTed)
		'api_url' =>'https://api.telesign.com/1.x/soap.asmx',
		
		// The URL to the API's WSDL
		'wsdl_url' => 'https://api.telesign.com/1.x/soap.asmx?WSDL',
		
		// How many times to redial if the number is busy or the call fails
		// default = 0. max = 1
		'redial_count' => 1, 
		
		// How long to wait before the call is made
		// in minutes
		'delay_time' => 0,
		
		// Debug mode?
		'debug' => false
		
	);	
	
	protected $errors = array();
	protected $messages = array();
	protected $reference_id;

	//---------------------------------------------------------------------------
	// Constructor
	//---------------------------------------------------------------------------
	// Input:
	// 	$config_array (array) - optional. The settings/configuration for the class
	// 
	// Returns:
	//	(void)
	//---------------------------------------------------------------------------
	public function __construct($config_array=null) {
		if( $config_array ) {
			foreach($config_array as $k => $v) $this->config[$k] = $v;
		}

	}
	
	//---------------------------------------------------------------------------
	// request_sms()
	//---------------------------------------------------------------------------
	// Generates a call to the TeleSign API to request an SMS be sent to the user
	//
	// Input:
	//	$calling_code (string) - The numerical calling code for the user's country
	//	$phone_number (string) - The user's phone number
	//	$pin (int) - the PIN to be sent to the user
	//	$msg (string) - Optional. The name of a custom message to use
	//
	// Returns:
	//	(string) - The reference ID of the request (on success)
	//	(bool) - false (on error)
	//---------------------------------------------------------------------------
	function request_sms($calling_code, $phone_number, $pin, $msg=null) {
		
		// The parameters for our SOAP call
    $soap_params = array(
    	'CustomerID' => $this->config['customer_id'],
    	'AuthenticationID' => $this->config['authentication_id'],
    	'CountryCode' => $calling_code,
    	'PhoneNumber' => $this->clean_number($phone_number),
    	'VerificationCode' => $pin
    );
    
    // Is there a custom message?
    if( $msg ) $soap_params['Message'] = $msg;
    
    // Get the response 
    $res = $this->make_soap_call('RequestSMS', $soap_params);
		$res_code = $res->RequestSMSResult->APIError->Code;
    
    // Success!
		if( $res_code == 0 )  {
			
			// If there is a reference ID, set it now
			$ref = @$res->RequestSMSResult->ReferenceID;
			if( $ref ) $this->reference_id = $ref;
			return true;
			
		// Error 
		} else{ 
			$this->errors[] = $res->RequestSMSResult->APIError->Message;
			return false;
		}
	}
	
	//---------------------------------------------------------------------------
	// request_call()
	//---------------------------------------------------------------------------
	// Generates a call to the TeleSign API to request an phone call to the user
	//
	// Input:
	//	$calling_code (string) - The numerical calling code for the user's country
	//	$phone_number (string) - The user's phone number
	//	$pin (int) - the PIN to be sent to the user
	//	$ext (string) - Optional. The user's extension. You can use commas (,) to signal a pause.
	//	$ext_type (int) - Optional. 1 = live operator. 2 = PBX
	//	$priority (int) - ???
	//	$msg (string) - Optional. The name of a custom message to use
	//
	// Returns:
	//	(string) - The reference ID of the request (on success)
	//	(bool) - false (on error)
	//---------------------------------------------------------------------------
	function request_call($calling_code, $phone_number, $pin, $ext=null, $ext_type=1, $priority=0, $msg=null) {
		
		// The parameters for our SOAP call
    $soap_params = array(
    	'CustomerID' => $this->config['customer_id'],
    	'AuthenticationID' => $this->config['authentication_id'],
    	'CountryCode' => $calling_code,
    	'PhoneNumber' => $this->clean_number($phone_number),
    	'VerificationCode' => $pin,
    	'Priority' => $priority, 
	    'DelayTime' => $this->config['delay_time'],
    	'RedialCount' => $this->config['redial_count']
    );
    
    // Is there an extension?
    if( $ext ) {
    	$soap_params['ExtensionContent'] = $ext;
    	$soap_params['ExtensionType'] = $ext_type;
    }
    
    // Is there a custom message?
    if( $msg ) $soap_params['Message'] = $msg;
    
    // Get the response 
    $res = $this->make_soap_call('RequestCALL', $soap_params);
		$res_code = $res->RequestCALLResult->APIError->Code;
    
    // Success!
		if( $res_code == 0 )  {
			
			// If there is a reference ID, set it now
			$ref = @$res->RequestCALLResult->ReferenceID;
			if( $ref ) $this->reference_id = $ref;
			return true;
			
		// Error 
		} else{ 
			$this->errors[] = $res->RequestCALLResult->APIError->Message;
			return false;
		}
	}
	
	//---------------------------------------------------------------------------
	// validate_pin()
	//---------------------------------------------------------------------------
	// Validates the verification code that the user enters
	//
	// Input:
	//	$pin (int) - The verification code that the user entered
	//	$ref_id (int) - The reference ID  from the "request" API call
	//
	// Returns:
	//	(bool) - TRUE if valid, FALSE if not.
	//---------------------------------------------------------------------------
	function validate_pin($pin, $ref_id) {
		$soap_params = array(
			'CustomerID' => $this->config['customer_id'],
    	'AuthenticationID' => $this->config['authentication_id'],
    	'ReferenceID' => $ref_id,
      'VerificationCode' => $pin
		);
		
		// Get the response 
    $res = $this->make_soap_call('RequestSTATUS', $soap_params);
    
    // 0 = valid, -1 = invalid
    return $res->RequestSTATUSResult->VerificationCodeValid == -1 ? false : true;
	}
	
	//---------------------------------------------------------------------------
	// clean_number()
	//---------------------------------------------------------------------------
	// Strips a phone number of any non numerical character
	//
	// Input:
	//	$phone (string) - the phone number to be cleaned
	//
	// Returns:
	//	(int) - the "clean" phone number (numbers only)
	//---------------------------------------------------------------------------
	public function clean_number($phone) {
		return preg_replace('/\D/', '', $phone);
	}
	
	//---------------------------------------------------------------------------
	// generate_pin()
	//---------------------------------------------------------------------------
	// Generates a random PIN to be used for verification
	//
	// Input:
	//	$length (int) - Optional. The length of the PIN. Default is 4
	//
	// Returns:
	//	(int) - The randomly generated PIN
	//---------------------------------------------------------------------------
	public function generate_pin($length=4) {
		if( $length < 3 || $length > 5 ) $length = 4;
		$pin = '';
		for( $i=0; $i < $length; ++$i ) {
			
			// Note: I repeatedly encountered problems when a PIN started with a zero
			$pin .= mt_rand(1,9); 
		}
		return $pin;
	}
	
	//---------------------------------------------------------------------------
	// make_soap_call()
	//---------------------------------------------------------------------------
	// Makes the actual request to TeleSign's API. 
	//
	// Input:
	//	$soap (string) - The SOAP data to be sent
	//
	// Returns: 
	//	(string) - The results from the SOAP call
	//---------------------------------------------------------------------------
	protected function make_soap_call($action, $soap_params) {
		
		// Make the SOAP call
		$soap = new SoapClient($this->config['wsdl_url']);
		$res = $soap->$action($soap_params);
		return $res;
	}
	
	//---------------------------------------------------------------------------
	// get_calling_code()
	//---------------------------------------------------------------------------
	// Returns the calling code for the country specified.
	//
	// Input: 
	//	$country (string) - 2 letter ISO Code for the country
	//
	// Returns:
	//	(int) - calling code for country or
	//	(bool) - false if country not fond
	//---------------------------------------------------------------------------
	public function get_calling_code($country) {
		
		$codes = array(
			'AF' => '93',
			'AX' => '358',
			'AL' => '355',
			'DZ' => '213',
			'AS' => '684',
			'AD' => '376',
			'AO' => '244',
			'AI' => '264',
			'AQ' => '672',
			'AG' => '1',
			'AR' => '54',
			'AM' => '374',
			'AW' => '297',
			'AU' => '61',
			'AT' => '43',
			'AZ' => '994',
			'BS' => '242',
			'BH' => '973',
			'BD' => '880',
			'BB' => '246',
			'BY' => '375',
			'BE' => '32',
			'BZ' => '501',
			'BJ' => '229',
			'BM' => '1',
			'BT' => '975',
			'BO' => '591',
			'BA' => '387',
			'BW' => '267',
			'BV' => '47',
			'BR' => '55',
			'IO' => '246',
			'BN' => '673',
			'BG' => '359',
			'BF' => '226',
			'BI' => '257',
			'KH' => '855',
			'CM' => '237',
			'CA' => '1',
			'CV' => '238',
			'KY' => '1',
			'CF' => '236',
			'TD' => '235',
			'CL' => '56',
			'CN' => '86',
			'CX' => '61',
			'CC' => '61',
			'CO' => '57',
			'KM' => '269',
			'CG' => '242',
			'CD' => '243',
			'CK' => '682',
			'CR' => '506',
			'CI' => '225',
			'HR' => '385',
			'CU' => '53',
			'CY' => '357',
			'CZ' => '420',
			'DK' => '45',
			'DJ' => '253',
			'DM' => '767',
			'DO' => '809',
			'EC' => '593',
			'EG' => '20',
			'SV' => '503',
			'GQ' => '240',
			'ER' => '291',
			'EE' => '372',
			'ET' => '251',
			'FK' => '500',
			'FO' => '298',
			'FJ' => '679',
			'FI' => '358',
			'FR' => '33',
			'GF' => '594',
			'PF' => '689',
			'TF' => '33',
			'GA' => '241',
			'GM' => '220',
			'GE' => '995',
			'DE' => '49',
			'GH' => '233',
			'GI' => '350',
			'GR' => '30',
			'GL' => '299',
			'GD' => '473',
			'GP' => '590',
			'GU' => '671',
			'GT' => '502',
			'GG' => '44',
			'GN' => '224',
			'GW' => '245',
			'GY' => '592',
			'HT' => '509',
			'VA' => '379',
			'HN' => '504',
			'HK' => '852',
			'HU' => '36',
			'IS' => '354',
			'IN' => '91',
			'ID' => '62',
			'IN' => '91',
			'IR' => '98',
			'IQ' => '964',
			'IE' => '353',
			'IM' => '44',
			'IL' => '972',
			'IT' => '39',
			'JP' => '81',
			'JE' => '44',
			'JO' => '962',
			'KZ' => '7',
			'KE' => '254',
			'KI' => '686',
			'KP' => '850',
			'KR' => '82',
			'KW' => '965',
			'KG' => '996',
			'LV' => '371',
			'LB' => '961',
			'LS' => '266',
			'LR' => '231',
			'LY' => '218',
			'LI' => '423',
			'LT' => '370',
			'LU' => '352',
			'MO' => '853',
			'MK' => '289',
			'MG' => '261',
			'MW' => '265',
			'MY' => '60',
			'MV' => '960',
			'ML' => '223',
			'MT' => '356',
			'MH' => '692',
			'MQ' => '596',
			'MR' => '222',
			'MU' => '230',
			'YT' => '262',
			'MX' => '52',
			'FM' => '691',
			'MD' => '373',
			'MC' => '377',
			'MN' => '976',
			'ME' => '381',
			'MA' => '212',
			'MZ' => '258',
			'MM' => '95',
			'NA' => '264',
			'NR' => '674',
			'NP' => '977',
			'NL' => '31',
			'AN' => '599',
			'NC' => '687',
			'NZ' => '64',
			'NI' => '505',
			'NE' => '227',
			'NG' => '234',
			'NU' => '683',
			'NF' => '672',
			'MP' => '670',
			'NO' => '47',
			'OM' => '968',
			'PK' => '92',
			'PW' => '680',
			'PS' => '970',
			'PA' => '507',
			'PG' => '675',
			'PY' => '595',
			'PE' => '51',
			'PH' => '63',
			'PL' => '48',
			'PT' => '351',
			'PR' => '1',
			'QA' => '974',
			'RE' => '262',
			'RO' => '40',
			'RU' => '7',
			'RW' => '250',
			'BL' => '1',
			'KN' => '1',
			'LC' => '1',
			'MF' => '1',
			'PM' => '1',
			'VC' => '1',
			'MS' => '685',
			'SM' => '378',
			'ST' => '239',
			'SA' => '966',
			'SN' => '221',
			'RS' => '381',
			'SC' => '248',
			'SL' => '232',
			'SG' => '65',
			'SK' => '421',
			'SI' => '386',
			'SB' => '677',
			'SO' => '252',
			'ZA' => '27',
			'GS' => '995',
			'ES' => '34',
			'LK' => '94',
			'SH' => '290',
			'SD' => '249',
			'SR' => '597',
			'SZ' => '268',
			'SE' => '46',
			'CH' => '41',
			'SY' => '963',
			'TW' => '886',
			'TJ' => '992',
			'TZ' => '225',
			'TH' => '66',
			'TL' => '670',
			'TG' => '228',
			'TK' => '690',
			'TO' => '676',
			'TT' => '868',
			'TN' => '216',
			'TR' => '90',
			'TM' => '993',
			'TC' => '649',
			'TV' => '688',
			'UG' => '256',
			'UA' => '380',
			'AE' => '971',
			'UK' => '44',
			'US' => '1',
			'UY' => '598',
			'UM' => '1',
			'UZ' => '998',
			'VU' => '678',
			'VE' => '58',
			'VN' => '84',
			'VG' => '44',
			'VI' => '1',
			'WF' => '681',
			'YE' => '967',
			'ZM' => '260',
			'ZW' => '263',
		);
		
		$country = strtoupper(trim($country));
		return array_key_exists($country, $codes) ? $codes[$country] : false;
	}
	
	//---------------------------------------------------------------------------
	// Return any errors, messages, etc
	//---------------------------------------------------------------------------
	public function errors(){ return $this->errors; }
	public function messages(){ return $this->messages; }
	public function reference_id(){ return $this->reference_id; }
		
	//---------------------------------------------------------------------------
	// Setters/Getters
	//---------------------------------------------------------------------------
	public function __get($key) {
		return array_key_exists($key, $this->config) ? $this->config[$key] : 'Invalid Property "' . $key . '"';
	}
	
	public function __set($key, $val) {
		if( array_key_exists($key, $this->config) ) { 
			return $this->config[$key] = $val;	
		} else {
			return 'Invalid Property "' . $key . '"';
		}
	}

} // end class	
?>