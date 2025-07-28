<?php
  $alert_to_email = true;                             // Whether to sendnotifications to eMail

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
?>
