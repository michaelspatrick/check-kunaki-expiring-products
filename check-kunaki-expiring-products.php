#!/usr/bin/php -q
<?php
 /***********************************************************************************************************************************************************
  * Programmer: Mike Patrick
  *    Purpose: Logs into Kunaki, scrapes content, and alerts to any products over a defined number of days old.  (Kunaki deletes products after 180 days.)
  *             Script will send email and Slack messages.  
  *             It will also place an order.
  *      Input: None
  *     Output: Writes to stdout and logs to a log file (/tmp/kunaki_order_debug.log) unless otherwise defined.
  *    Version: 1.1
  **********************************************************************************************************************************************************/

  require __DIR__ . "/config.php";
  require __DIR__ . "/functions.php";

  // Kunaki URLs
  $loginURL = "https://kunaki.com/accounting/CheckLogin.asp";
  $pageURL = "https://kunaki.com/accounting/ProductsList.asp";

  // Email Headers
  $headers1 = "From: ".$email."\r\nReply-To: ".$email."\r\nX-Mailer: PHP/".phpversion();
  $headers2 = "-f ".$email;

  // Make sure script doesn't prematurely abort
  ignore_user_abort(true);
  set_time_limit(0);

  //set the directory for the cookie using defined document root var
  $dir = "/tmp";

  // Determine how long before products expire
  $life_left = 180 - $life_threshold;

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

  $startStr = "<TD>&nbsp;&nbsp;Active&nbsp;&nbsp;</TD><TD align=right>&nbsp;&nbsp;";
  $endStr = "&nbsp;</TD>";
  preg_match_all("#".preg_quote($startStr, '#')."(.*?)".preg_quote($endStr, '#')."#s", $html, $expirations);

  $startStr = "<TD width=320px align=left>&nbsp;&nbsp;<STRONG>";
  $endStr = "</STRONG>";
  preg_match_all("#".preg_quote($startStr, '#')."(.*?)".preg_quote($endStr, '#')."#s", $html, $titles);

  $startStr = "<TD align=left>";
  $endStr = "</TD>";
  preg_match_all("#".preg_quote($startStr, '#')."(.*?)".preg_quote($endStr, '#')."#s", $html, $IDS);

  $warnings = [];
  $productIds = [];
  for ($i=0; $i < count($expirations[1]); $i++) {
    if ((int) $expirations[1][$i] >= $life_threshold) {
      if ((int) $expirations[1][$i] == $life_threshold) $productIds[] = str_replace("&nbsp;","",$IDS[1][$i]);
      $warnings[] = $titles[1][$i].str_replace("&nbsp;","","[".$IDS[1][$i])."]: ".$expirations[1][$i];
    }
  }

  if (count($productIds) > 0) {
    echo "Submitting Kunkai order...\n";
    $result = submitKunakiXmlOrder($productIds, $email, $password, $mode, $shipping_method, $recipient);
    if ($result['success']) {
        $message = "✅ *Kunaki Wholesale Renewal Order Submitted Successfully*\nOrder ID: `{$result['order_id']}`";
    } else {
        $errorCode = $result['error_code'] ?? 'N/A';
        $errorText = $result['error_text'] ?? ($result['error'] ?? 'Unknown error');
        $message = "❌ *Kunaki Wholesale Renewal Order Failed*\nError Code: `{$errorCode}`\nMessage: `{$errorText}`";
    }
    if ($alert_to_slack) sendToSlack($slackChannel, $message, $slackAPIkey);

    print_r($result);
  }

  if (count($warnings) > 0) {
    $warnings_str = implode("\n", $warnings);
    $subject = "You have Kunaki products that will expire in less than ".$life_left." days!";
    if ($alert_to_email) mail($email, $subject, $warnings_str, $headers1, $headers2);
    if ($alert_to_slack) sendToSlack($slackChannel, $subject."\n".$warnings_str, $slackAPIkey);
  }
?>
