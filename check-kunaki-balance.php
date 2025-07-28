#!/usr/bin/php -q
<?php
 /***********************************************************************************************************************************************************
  * Programmer: Mike Patrick
  *    Purpose: Script logs into Kunaki and checks pending order balance due and alerts if it is over $0.00.  It sends email and Slack messages.
  *      Input: None
  *     Output: Writes to stdout and logs to a log file (/tmp/kunaki_order_debug.log) unless otherwise defined.
  *    Version: 1.1
  **********************************************************************************************************************************************************/

  require __DIR__ . "/config.php";
  require __DIR__ . "/functions.php";

  ignore_user_abort(true);
  set_time_limit(0);

  // Kunaki URLs
  $loginURL = "https://kunaki.com/accounting/CheckLogin.asp";
  $pageURL = "https://kunaki.com/accounting/XMLBilling.ASP";

  // Email Headers
  $headers1 = "From: ".$email."\r\nReply-To: ".$email."\r\nX-Mailer: PHP/".phpversion();
  $headers2 = "-f ".$email;

  //set the directory for the cookie using defined document root var
  $dir = "/tmp";

  //login form action url
  $postinfo = "Email=".urlencode($email)."&Password=".urlencode($password);

  $cookie_file_path = $dir."/cookie.txt";

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_HEADER, false);
  curl_setopt($ch, CURLOPT_NOBODY, false);
  curl_setopt($ch, CURLOPT_URL, $loginURL);
  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

  curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file_path);
  //set the cookie the site has for certain features, this is optional
  curl_setopt($ch, CURLOPT_COOKIE, "cookiename=0");
  curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.0; en-US; rv:1.7.12) Gecko/20050915 Firefox/1.0.7");
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_REFERER, $_SERVER['REQUEST_URI']);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);

  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
  curl_setopt($ch, CURLOPT_POST, 1);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $postinfo);
  curl_exec($ch) or die("There was an error logging in...");

  //page with the content I want to grab
  curl_setopt($ch, CURLOPT_URL, $pageURL);

  //do stuff with the info with DomDocument() etc
  $html = curl_exec($ch);
  curl_close($ch);

  $startStr = "Your current credit balance is: ";
  $endStr = "</STRONG>";
  preg_match("#".preg_quote($startStr, '#')."(.*?)".preg_quote($endStr, '#')."#s", $html, $matches);
  $balance = str_replace("$", "", trim($matches[1]));

  $startStr = ">Total due now to manufacture/ship pending orders: ";
  $endStr = "</STRONG>";
  preg_match("#".preg_quote($startStr, '#')."(.*?)".preg_quote($endStr, '#')."#s", $html, $matches);
  $amountDue = str_replace("$", "", trim($matches[1]));

  if ((int)$amountDue > 0) { 
    $subject = "Kunaki Needs Money!";
    $message = "You have a balance of $".$balance." with $".$amountDue." due to manufacture and ship existing orders!";
    if ($alert_to_email) mail($email, $subject, $message, $headers1, $headers2);
    if ($alert_to_slack) sendToSlack($slackChannel, $message, $slackAPIkey);
  }
?>
