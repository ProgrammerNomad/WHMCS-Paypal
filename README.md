
# PayPal Custom API Gateway for WHMCS

A modern, secure, open-source PayPal payment gateway module for WHMCS (v8.13.1+) using the PayPal REST API, with configurable fee options, full webhook support, and both Live/Sandbox modes.

## Features

- Modern PayPal REST API integration (no insecure HTML forms)
- Set custom PayPal fee percentage and fixed fee per payment (e.g., 5.95% + $0.30)
- Fees are automatically calculated and added to the invoice total
- Supports both Live and Sandbox (testing) modes
- Secure webhook handler with signature verification (auto-marks invoices as paid)
- Open source and ready for community use and improvement

## Installation & Setup

### 1. Download or Clone

```bash
git clone https://github.com/ProgrammerNomad/WHMCS-Paypal.git
```

### 2. Copy Files

Copy the `modules/gateways/paypalcustom` folder to your WHMCS installation's `modules/gateways/` directory.
Copy the `modules/gateways/callback/paypalcustom.php` file to your WHMCS installation's `modules/gateways/callback/` directory.

### 3. Activate Gateway

In your WHMCS admin area, go to **Setup > Payments > Payment Gateways**.
Activate **PayPal Custom API Gateway**.

### 4. Configure Gateway

Fill in the following fields:

- **PayPal Client ID**: Your PayPal REST API Client ID (from developer.paypal.com)
- **PayPal Client Secret**: Your PayPal REST API Client Secret
- **PayPal Webhook ID**: The Webhook ID you get when you create a webhook in your PayPal app
- **Mode**: Select `Live` for production or `Sandbox` for testing
- **PayPal Fee Percentage**: The percentage fee to add (e.g., 5.95 for 5.95%)
- **PayPal Fixed Fee**: The fixed fee to add (e.g., 0.30 for $0.30)

### 5. Set Up PayPal Webhook

1. In your PayPal Developer Dashboard, go to your app and add a webhook.
2. Set the webhook URL to:
	```
	https://yourdomain.com/modules/gateways/callback/paypalcustom.php
	```
3. Select at least the following event: `CHECKOUT.ORDER.APPROVED`
4. Copy the Webhook ID and enter it in the gateway configuration.

### 6. Test Payments

Switch to Sandbox mode and use sandbox API credentials to test payments and webhook notifications.

## How It Works

When a client selects PayPal Custom API Gateway, the module creates a PayPal order via the REST API, including the configured fees. The client is redirected to PayPal to complete payment. When PayPal notifies your WHMCS via webhook, the invoice is automatically marked as paid (after verifying the webhook signature for security).

## License

MIT License. See [LICENSE](LICENSE).

## Contributing

Pull requests and suggestions are welcome!

## Author

[ProgrammerNomad](https://github.com/ProgrammerNomad)
# WHMCS-Paypal