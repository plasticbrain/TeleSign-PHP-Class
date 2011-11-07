<?php
require_once('class.telesign.php'); 

//------------------------------------------------------------------------------
// Variables, arrays, etc that we need
//------------------------------------------------------------------------------
$errors = array();
$pin_was_sent = false;

// Default method to use
$method = 'sms';

// how long should the PIN be? 3-5 digits
$pin_length = 4; 

// Where should we redirect the user once their number is verified?
$redirect_on_success = '#';

// URL to contact form/support
$support_link = '#';

//------------------------------------------------------------------------------
// Instantiate the TeleSign class
//------------------------------------------------------------------------------
$ts_config = array(
	'customer_id' => '', 
	'authentication_id' => ''
);
$ts = new Telesign($ts_config);

//------------------------------------------------------------------------------
// Make sure the user is supposed to be on this page. IE: make sure the
// required GET parameters are included.
//------------------------------------------------------------------------------
if( !isset($_GET['id']) || !isset($_GET['phone']) || !isset($_GET['country']) || !isset($_GET['time']) || !isset($_GET['token']) ) {

	$errors[] = 'Missing Data';

} else {
	
	$id = strip_tags( urldecode($_GET['id']) );
	$time = strip_tags( urldecode($_GET['time']) );
	$phone = strip_tags( urldecode($_GET['phone']) );
	$country = strip_tags( urldecode($_GET['country']) );
	$token = strip_tags( urldecode($_GET['token']) );
	$calling_code = $ts->get_calling_code($country);
	
	// Make sure we have a valid ID
	if( !ctype_digit($id) ) $error[] = "Invalid ID";

	// Make sure we have a valid token
	if( $token != sha1($id . $time . $phone . $country . md5('phr34k')) ) $errors[] = 'Invalid token';
	
	// What step are we on?
	if( isset($_GET['step']) ) {
		
		// Step 2 - Validity of user information
		//--------------------------------------------
		if( $_GET['step'] == '2' ) {
			if( !isset($_GET['correct']) ) {
				$errors[] = 'Missing parameter for step 2';
			} else {
				if( $_GET['correct'] != '1' && $_GET['correct'] != '0' ) $errors[] = 'Invalid parameter for step 2';
			}
		
		// Step 3 - Generate and send PIN
		//--------------------------------------------
		} elseif( $_GET['step'] == '3' ) {
			if( !isset($_GET['method']) ) {
				$errors[] = 'Missing parameter for step 3';
			} else {
				if( $_GET['method'] != 'sms' && $_GET['method'] != 'call' ) {
					$errors[] = 'Invalid parameter for step 3';
				} else {
					
					$method = $_GET['method'];
					
					// generate the PIN
					$pin = $ts->generate_pin($pin_length);
					
					if( $method == 'sms' ) {
					
						// Send an SMS 
						$res = $ts->request_sms($calling_code, $phone, $pin);

					} else {
						
						// Call the user's phone
						$res = $ts->request_call($calling_code, $phone, $pin);

					}
						
					// error?
					if( $res == false ) {
						foreach($ts->errors() as $err) $msg->add('e', $err);

					// Success
					} else {
						$pin_was_sent = true;
					}
					
				}
			}
		
		
		// Step 4 - Validate PIN
		//--------------------------------------------
		} elseif( $_GET['step'] == '4' ) {
			if( !isset($_GET['method']) ) {
				$errors[] = 'Missing parameter for step 4';
			} else {
				if( $_GET['method'] != 'sms' && $_GET['method'] != 'call' ) {
					$errors[] = 'Invalid parameter for step 4';
				} else {
					$method = $_GET['method'];
				}
			}
			
			if( !isset($_GET['pin']) ) {
				$errors[] = 'Missing parameter for step 4';
			} else {
				if( strlen(trim($_GET['pin'])) != $pin_length ) {
					$errors[] = 'Invalid PIN';
				} else {
					$pin = strip_tags(urldecode($_GET['pin']));
				}
			}
			
			if( !isset($_GET['ref']) ) {
				$errors[] = 'Missing parameter for step 4';
			} else {
				$ref = strip_tags(urldecode($_GET['ref']));
			}
			
			// No errors so far? Let's validate the PIN
			if( empty($errors) ) {
				
				$ts->validate_pin($pin, $ref);
				
				// PIN was correct
				if( $ts->validate_pin($pin, $ref) ) {
					header("Location: $redirect_on_success"); 
					exit();
				}
				
				// PIN was NOT correct	
			} else {
				$errors[] = 'The Verification Code you entered was not correct!';
			}
		}
	}
}
//------------------------------------------------------------------------------
// Print out the page
//------------------------------------------------------------------------------
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<title>Phone Number Verfication</title>
	</head>
	<body>
		
		<div id="phone-verification">
			
			<h1>Please Verify Your Phone Number</h1>			

			
			<?php if( empty($errors) ): ?>                                                                                                                                                                                                                                                            
				
				<?php
				//----------------------------------------------------------------------
				// Step 1 - Validate user's info
				//----------------------------------------------------------------------
				if( !isset($_GET['step']) || $_GET['step'] == '1' ):
				?>
				
					<p>In order for your application to be considered, you must first verify your phone number. This process is simple, and should only take a couple of minutes. To begin, please verify that the information that we have for you below is correct:</p>
			  	
					<ul>
						<li><b>Your Country:</b> <?php echo $country; ?></li>
						<li><b>Your Phone Number:</b> <?php echo $calling_code . ' ' . $phone; ?></li>
					</ul>
					
					
					<h4>Is this information correct?</h4>
					
					<form action="" method="get">
						<p style="padding-left: 10px;">
							<label for="yes"><input checked="checked" id="yes" type="radio" name="correct" value="1" /> Yes, this information <b>is</b> correct</label><br />
							<label for="no"><input id="no" type="radio" name="correct" value="0" /> No, this information <b>is not</b> correct</label>
						</p>
						
						<input type="hidden" name="step" value="2" />
						<input type="hidden" name="id" value="<?php echo $id; ?>" />
						<input type="hidden" name="time" value="<?php echo $time; ?>" />
						<input type="hidden" name="phone" value="<?php echo $phone; ?>" />
						<input type="hidden" name="country" value="<?php echo $country; ?>" />
						<input type="hidden" name="token" value="<?php echo $token; ?>" />
						<input class="button" type="submit" value="Next Step &rarr;" />
						
					</form>
				
				<?php
				//----------------------------------------------------------------------
				// Step 2 - Verification Method
				//----------------------------------------------------------------------
				elseif( isset($_GET['step']) && $_GET['step'] == '2' ):
				?>
					
					<?php 
					// If the information is NOT correct...
					if( $_GET['correct'] == '0' ): ?>
					
						<p>We're sorry that you're having trouble verifying your phone number. The information that was previously displayed was from our records that we have for you. If this information is incorrect, then it means the account information we have on file for you is also incorrect. In order to continue, you will need to <a href="contact.php">contact support</a> to correct the information that we have for you.</p>
					
					<?php 
					// The information IS correct
					else: ?>
					
						<p>Great! Now, all we need to do is send you a verification code. Please choose how you would like to receive your code:</p>
						<form action="" method="get">
							
							<p style="padding-left: 10px;">
								<label for="sms"><input checked="checked" id="sms" type="radio" name="method" value="sms" /> SMS (Text Message)</label><br />
								<label for="call"><input id="call" type="radio" name="method" value="call" /> Phone Call</label>
							</p>
							
							<input type="hidden" name="step" value="3" />
							<input type="hidden" name="id" value="<?php echo $id; ?>" />
							<input type="hidden" name="time" value="<?php echo $time; ?>" />
							<input type="hidden" name="phone" value="<?php echo $phone; ?>" />
							<input type="hidden" name="country" value="<?php echo $country; ?>" />
							<input type="hidden" name="token" value="<?php echo $token; ?>" />
							<input class="button" type="submit" value="Send Verification Code" />
							
						</form>
						
						<br />
						<hr />
						<small>Fine print legal talk that mentions how we don't charge for this service, however standard text messaging rates do still apply.</small>
					
					<?php endif; ?>
					
					
				<?php
				//----------------------------------------------------------------------
				// Step 3 - Generate and Send PIN
				//----------------------------------------------------------------------
				elseif( isset($_GET['step']) && $_GET['step'] == '3' ):
				?>	
				
					<?php 
					$params = array('id' => $id, 'method' => $method, 'step' => 3, 'time' => $time, 'phone' => $phone, 'country' => $country, 'token' => $token); 
					$params = http_build_query($params);
					$resend_link = $_SERVER['PHP_SELF'] . '?' . $params;
					?>  
					
					<?php if( $pin_was_sent ): ?>
						
						<?php if( $method == 'call' ): ?>

							<p>You should receive a call shortly at <b><?php echo "$calling_code $phone"; ?></b>. When you answer, your <?php echo $pin_length; ?>-digit verification code will be read to you. Once you receive your verification code, please enter it below.</p>
							<p>If you have not received a call after 5 minutes, please <a href="<?php echo $resend_link; ?>">click here to try again</a>.</p>
								
						<?php else: ?>
							
							<p>A text message/SMS with your <?php echo $pin_length; ?>-digit verification number has been sent to you at <b><?php echo "$calling_code $phone"; ?></b>. Once you receive your verification code, please enter it below.</p>
							<p>If you have not received your verification code after 5 minutes, please <a href="<?php echo $resend_link; ?>">click here to resend</a> the code to your phone.</p>								
							
						<?php endif; ?>	
						
						<h4>Enter Your Verification Code:</h4>
						
						<form action="" method="">
						
							<p>
								<input style="width: 100px; font-size: 15px; padding: 2px 1px;" type="text" class="text" name="pin" value="" /> 
								<input class="button" type="submit" value="Check Code" /> 
							</p>
								
							<input type="hidden" name="step" value="4" />
							<input type="hidden" name="id" value="<?php echo $id; ?>" />
							<input type="hidden" name="ref" value="<?php echo $ts->reference_id(); ?>" />
							<input type="hidden" name="method" value="<?php echo $method; ?>" />
							<input type="hidden" name="time" value="<?php echo $time; ?>" />
							<input type="hidden" name="phone" value="<?php echo $phone; ?>" />
							<input type="hidden" name="country" value="<?php echo $country; ?>" />
							<input type="hidden" name="token" value="<?php echo $token; ?>" />
						</form>
						
					<?php else: ?>
						<p>We're sorry, but an error has occurred. Your verification code could not be sent to your phone. Please <a href="<?php echo $support_link; ?>">contact support</a>.</p>
					<?php endif; ?>
				
				<?php
				//----------------------------------------------------------------------
				// Step 4 - Validate PIN
				//----------------------------------------------------------------------
				elseif( isset($_GET['step']) && $_GET['step'] == '4' ):
				?>	
					
					<?php 
					$params = array('id' => $id, 'method' => $method, 'step' => 3, 'time' => $time, 'phone' => $phone, 'country' => $country, 'token' => $token); 
					$params = http_build_query($params);
					$resend_link = $_SERVER['PHP_SELF'] . '?' . $params;
					?> 
					
					<p>The verification code you entered was not correct. Please <a href="<?php echo $resend_link; ?>">click here to retry</a>.</p>
					
				<?php
				//----------------------------------------------------------------------
				// Unknown/invalid step
				//----------------------------------------------------------------------
				else:
				?>
				
				<p>We're sorry, but your request could not be completed due to an error that has occurred. Please <a href="<?php echo $support_link; ?>">contact support</a>.</p>
				
				<?php endif; ?>
			
			<?php else: ?>

				<p>We're sorry, but your request could not be completed due to an error that has occurred. Please <a href="<?php echo $support_link; ?>">contact support</a>.</p>
				<blockquote>
					<h4>Error(s):</h4>
					<ul>
					<?php foreach( $errors as $err ): ?>
						<li style="color: #c00;"><?php echo $err; ?></li>
					<?php endforeach; ?>
				</blockquote>
				
			<?php endif; ?>
			
		
		</div>
		<!-- end phone varification -->
	
	</body>
</html>