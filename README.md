TeleSign PHP Class
==========================


Description
-----------
[TeleSign](http://www.telesign.com "Telesign") provides phone number verification
for your websites or web applications. This class is designed to help you easily
integrate TeleSign's services.

Requirements
------------

* A [TeleSign](http://www.telesign.com "TeleSign") account
* Your TeleSign Customer ID 
* Your TeleSign Authentication ID

Your Customer ID and Authenticaion ID can be found [here](https://portal.telesign.com/account_integration.php "TeleSign Customer Portal").

Installation & Basic Usage
--------------------------
    <?php 
    
    // Include the class
    require_once('class.telesign.php');
    
    // Setup and instantiate the class
    $config = array(
    	'customer_id' => 'YOUR CUSTOMER ID', 
			'authentication_id' => 'YOUR AUTHENTICATION ID',
    );
    
    $telesign = new Telesign($config);

		// Get the calling code for the user's country
		//--------------------------------------------
		$calling_code = $telesign->get_calling_code('US');
		
    // Generate a 4 digit PIN 
    //--------------------------------------------
    $pin = $telesign->generate_pin(4);
    
    // Clean a phone number 
    // (removes all non-numeric characters)
    //--------------------------------------------
    $phone_number  = '555-555-1234';
    $phone_number = $telesign->clean_number($phone_number);
    
    // Send an SMS 
    //--------------------------------------------
		$send_sms = $ts->request_sms($calling_code, $phone_number, $pin);
    
    if( $send_sms == false ) {
    	// Error sending the SMS
    } else {
    	// Success
    }
    
    // Call a phone number
    //--------------------------------------------
		$call_phone = $ts->request_call($calling_code, $phone_number, $pin);
    
    if( $call_phone == false ) {
    	// Error calling phone number
    } else {
    	// Success
    }
    
    // Validate a PIN that a user has submitted
    //--------------------------------------------
    $pin = $_POST['pin'];
    
    $verified = $telesign->validate_pin($pin);
    
    if( $verified ) {
    	// PIN is correct
    } else {
    	// PIN is not correct
    }
    
    // Get any errors that were returned
    //--------------------------------------------
		foreach( $telesign->errors() as $error ) {
			echo $error . '<br />';
		}
		
		?>

Plugin Author
--------------
This plugin was created by Mike Everhart. You can find me around the web at:

* My Personal Site: [MikeEverhart.net](http://www.mikeeverhart.net "My personal site")
* My Side Project: [plasticbrain.net](http://www.plasticbrain.net "My part time project")
* My Social Life: [Facebook](https://www.facebook.com/plasticbrain "Friend me on Facebook!")

Reporting Bugs
--------------
So you found a bug? Hey, nobody's perfect, right?

Please use [GitHub's Issue Tracker](https://github.com/plasticbrain/HelpSpot-Custom-Widget-1.0/issues/new "Submit a Bug") to submit a bug report.

Suggestions & Improvements
--------------------------
Got an idea to make this plugin even better? Well, lucky for you, you have two choices!

* Email your suggestions to **[feedback@plasticbrain.net](mailto:feedback@plasticbrain.net "Submit Feedback")**
* Or, you can even fork this plugin and create your own version!