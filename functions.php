<?php
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
?>
