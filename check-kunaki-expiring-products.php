#!/usr/bin/php -q
<?php
  /***********************************************************************************************************************************************************
   * Programmer: Mike Patrick
   *    Purpose: Logs into Kunaki, scrapes content, and alerts to any products over a defined number of days old.  (Kunaki deletes products after 180 days.)
   *             Script will send email and Slack messages.
   *             It will also place an order.
   *      Input: None
   *     Output: Writes to stdout and logs to a log file (/tmp/kunaki_order_debug.log) unless otherwise defined.
   *    Version: 1.0
   **********************************************************************************************************************************************************/

  /* ---------------------------------------------------------------- Begin Configuration ----------------------------------------------------------------- */
  $alert to_email = true;                             // Whether to sendnotifications to eMail
  $alert_to_slack = true;                             // Whether to send notifications to Slack
  $slackAPIkey = "xoxb-XXXXXXXXXXXXXXXXXXXXXXXXXXX";  // Slack API Key
  $slackChannel = "#notifications";                   // Slack channel for alerts
  $email = "your_email@gmail.com";                    // Kunaki login
  $password = "your_password";                        // Kunaki password
  $life_threshold = 170;                              // when to start warning and also when to submit a Kunaki order so products don't expire
  $shipping_method = "UPS Ground";                    // Kunaki shipping method
  $mode = "Live";                                     // Live or Test

  // Kunaki Order Recipient Info
  $recipient = [
        'Name'           => 'John Doe',
        'Company'        => '',
        'Address1'       => '123 Main St',
        'Address2'       => '',
        'City'           => 'New York',
        'State_Province' => 'NY',
        'PostalCode'     => '10001',
        'Country'        => 'United States'
  ];
  /* ----------------------------------------------------------------- End Configuration ----------------------------------------------------------------- */

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


  function submitKunakiXmlOrder(array $productIds, $userId, $password, $mode = 'LIVE', $shipping = 'USPS First Class Mail', $recipient = [], $debugLog = '/tmp/kunaki_order_debug.log') {
    $defaults = [
        'Name'            => 'John Smith',
        'Company'         => '',
        'Address1'        => '123 Main St',
        'Address2'        => '',
        'City'            => 'New York',
        'State_Province'  => 'NY',
        'PostalCode'      => '10001',
        'Country'         => 'United States',
    ];
    $recipient = array_merge($defaults, $recipient);

    $doc = new DOMDocument('1.0', 'UTF-8');
    $doc->formatOutput = false;

    $order = $doc->createElement('Order');
    $doc->appendChild($order);

    $fields = [
        'UserId'             => $userId,
        'Password'           => $password,
        'Mode'               => $mode,
        'Name'               => $recipient['Name'],
        'Company'            => $recipient['Company'],
        'Address1'           => $recipient['Address1'],
        'Address2'           => $recipient['Address2'],
        'City'               => $recipient['City'],
        'State_Province'     => $recipient['State_Province'],
        'PostalCode'         => $recipient['PostalCode'],
        'Country'            => $recipient['Country'],
        'ShippingDescription'=> $shipping,
    ];

    foreach ($fields as $key => $value) {
        $node = $doc->createElement($key, htmlspecialchars(trim($value), ENT_XML1, 'UTF-8'));
        $order->appendChild($node);
    }

    foreach ($productIds as $pid) {
        $product = $doc->createElement('Product');

        $productId = $doc->createElement('ProductId', htmlspecialchars(trim($pid), ENT_XML1, 'UTF-8'));
        $quantity = $doc->createElement('Quantity', '1');

        $product->appendChild($productId);
        $product->appendChild($quantity);
        $order->appendChild($product);
    }

    $xmlString = $doc->saveXML();

    // Submit using raw POST body
    $ch = curl_init("https://Kunaki.com/XMLService.ASP");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlString);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: text/xml',
        'Content-Length: ' . strlen($xmlString)
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    // Log
    file_put_contents($debugLog, "Submitted XML:\n$xmlString\n\nResponse:\n$response\n\n", FILE_APPEND);

    if ($error) {
        return ['success' => false, 'error' => $error];
    }

    try {
        preg_match('/<Response>.*<\/Response>/s', $response, $matches);
        if (!isset($matches[0])) {
            throw new Exception("Could not locate <Response> block in XML");
        }
        $resXml = new SimpleXMLElement($matches[0]);

        $errorCode = (string) $resXml->ErrorCode;
        $errorText = (string) $resXml->ErrorText;
        $orderId = (string) $resXml->OrderId;

        return [
            'success' => $errorCode === '0',
            'order_id' => $orderId,
            'error_code' => $errorCode,
            'error_text' => $errorText,
            'raw_response' => $response,
        ];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
  }

  function sendToSlack($channel, $message, $token) {
    $payload = json_encode([
        'channel' => $channel,
        'text' => $message,
    ]);

    $ch = curl_init('https://slack.com/api/chat.postMessage');
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: ' . 'Bearer ' . $token,
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true);

    if ($http_code === 200 && isset($data['ok']) && $data['ok']) {
        return true;
    } else {
        error_log("Slack Error: " . ($data['error'] ?? 'Unknown error'));
        return false;
    }
  }

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
        $message = "  *Kunaki Wholesale Renewal Order Submitted Successfully*\nOrder ID: `{$result['order_id']}`";
    } else {
        $errorCode = $result['error_code'] ?? 'N/A';
        $errorText = $result['error_text'] ?? ($result['error'] ?? 'Unknown error');
        $message = "  *Kunaki Wholesale Renewal Order Failed*\nError Code: `{$errorCode}`\nMessage: `{$errorText}`";
    }
    if ($alert to_slack) sendToSlack($slackChannel, $message, $slackAPIkey);

    print_r($result);
  }

  if (count($warnings) > 0) {
    $warnings_str = implode("\n", $warnings);
    $subject = "You have Kunaki products that will expire in less than ".$life_left." days!";
    if ($alert to_email) mail($email, $subject, $warnings_str, $headers1, $headers2);
    if ($alert to_slack) sendToSlack($slackChannel, $subject."\n".$warnings_str, $slackAPIkey);
  }
?>
