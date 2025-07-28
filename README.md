# 🛒 Kunaki Product Renewal & Alert Script

This script monitors your active products on [Kunaki](https://kunaki.com), alerts you when they are nearing expiration (default: 170 days), and automatically places a wholesale renewal order before they are deleted at 180 days.

It supports both Slack and email notifications and can be scheduled to run via cron.

---

## 📦 Features

- ✅ Scrapes your active Kunaki products and checks their age
- 🔔 Sends alerts for expiring products via Slack and/or email
- 🔄 Automatically submits a wholesale XML order for renewal
- 🔐 Securely logs into your Kunaki account via cURL
- 🧾 Logs full API requests/responses to `/tmp/kunaki_order_debug.log`
- 🛠 Configurable shipping method, alert threshold, and order mode (Test or Live)

---

## 📋 Requirements

- PHP 7.0+
- cURL extension enabled
- Slack bot token (if using Slack alerts)
- A Kunaki account with valid product IDs

---

## ⚙️ Configuration

Open the script and edit the configuration section at the top:

```php
$email           = "your_email@gmail.com";     // Your Kunaki login email
$password        = "your_password";            // Your Kunaki password
$slackAPIkey     = "xoxb-XXX";                 // Your Slack Bot API token
$slackChannel    = "#notifications";           // Slack channel for alerts
$life_threshold  = 170;                        // Days before expiration to act
$shipping_method = "UPS Ground";               // Valid Kunaki shipping method
$mode            = "Live";                     // "Test" or "Live" XML order mode
```

Update the recipient shipping info if needed:

```php
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
```

---

## 🧪 Testing

You can safely test the XML submission without placing a real order by setting:

```php
$mode = "Test";
```

Kunaki will return a successful response with `OrderId = 00000`.

---

## 🚀 Cron Example

Run this script daily via cron:

```bash
0 6 * * * /usr/bin/php /path/to/kunaki-renewal.php >> /var/log/kunaki.log 2>&1
```

---

## 🛑 Important Notes

- Kunaki deletes products after 180 days of inactivity.
- Products ordered within 10 days of expiration are saved.
- This script automatically renews only those products that hit the `$life_threshold`.

---

## 📄 License

MIT License

---

## 🙋‍♂️ Author

Created by [Mike Patrick](https://dragonsociety.com)
