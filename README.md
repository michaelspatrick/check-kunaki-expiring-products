# 🛒 Kunaki Automation Suite

A modular PHP-based monitoring and automation toolkit for Kunaki publishers. This suite includes tools to monitor product expiration, account balances, and submit wholesale renewal orders — with optional Slack and email notifications.

---

## 📦 Included Scripts

### `slack-expiring-products.php`
- Logs into Kunaki
- Scrapes product expiration data
- Alerts for products nearing the 180-day deletion window
- Automatically places a wholesale order for expiring products
- Posts summary to Slack and/or email

### `check-kunaki-balance.php`
- Logs into Kunaki
- Checks if money is due for pending orders
- Sends Slack/email alerts if the balance due > $0

---

## ⚙️ Configuration

All global settings are defined in:

### `config.php`
```php
$email            = "your_email@gmail.com";   // Kunaki account login
$password         = "your_password";          // Kunaki password
$slackAPIkey      = "xoxb-...";               // Slack Bot Token
$slackChannel     = "#notifications";         // Slack channel for alerts
$alert_to_email   = true;
$alert_to_slack   = true;
$life_threshold   = 170;                      // Days before expiration to trigger action
$shipping_method  = "UPS Ground";             // Valid Kunaki shipping option
$mode             = "Live";                   // "Test" or "Live"
$recipient = [                                 // Shipping info for renewal orders
    'Name'           => 'John Doe',
    'Company'        => '',
    'Address1'       => '123 Main St',
    'Address2'       => '',
    'City'           => 'New York',
    'State_Province' => 'NY',
    'PostalCode'     => '10001',
    'Country'        => 'United States',
];
```

---

## 🧠 Shared Logic

### `functions.php`
Contains:
- `sendToSlack($channel, $message, $token)`
- `submitKunakiXmlOrder($productIds, $userId, $password, $mode, $shipping, $recipient, $logPath)`
- Helper logic for parsing responses, constructing XML, and error handling.

---

## 🧪 Testing Mode

Set in `config.php`:
```php
$mode = "Test";
```
In test mode, orders are not billed and will return:
```xml
<OrderId>00000</OrderId>
```

---

## 🚀 Cron Automation

Add the following to your server's crontab to run daily:

```bash
0 6 * * * /usr/bin/php /path/to/slack-expiring-products.php >> /var/log/kunaki-products.log 2>&1
0 7 * * * /usr/bin/php /path/to/check-kunaki-balance.php >> /var/log/kunaki-balance.log 2>&1
```

---

## 📄 Log Files

Default log output goes to:
```
/tmp/kunaki_order_debug.log
```

You can modify this path in each script if needed.

---

## 🛑 Important

- Kunaki deletes products 180 days after last duplication.
- This script helps prevent accidental deletions by renewing products around day 170.
- You must have sufficient funds in your Kunaki account for renewals to process.

---

## 📜 License

MIT License

---

## 🙋 Author

Created by [Mike Patrick](https://dragonsociety.com)
