<?php
	
/* Email Address */	
$to = 'mail@companyname.com';

/* Subject */
$subject = 'Companyname Newsletter Form';

/* Headers */
$email = $_POST['newsletter-email'];

if(isset($email) && !empty($email)){
	if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
		$message = 'Email: '.$email;
		echo mail($to, $subject, $message);
	}else{
		echo 2;
	}
	
}

?>