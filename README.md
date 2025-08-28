# PayPal Custom API Gateway for WHMCS

A modern, secure, open-source PayPal payment gateway module for WHMCS (v8.13.1+) using the PayPal REST API, with configurable dynamic fees, client-specific exemptions via "DontChargeFee" checkbox, and full webhook support for automatic invoice marking.

## Features (Completed)

- [x] Modern PayPal REST API integration (no insecure HTML forms)
- [x] Set custom PayPal fee percentage and fixed fee per payment (e.g., 5.95% + $0.30) – fees are calculated dynamically based on invoice amount
- [x] Client-specific fee exemption via "DontChargeFee" custom field checkbox (fees are skipped for exempted clients)
- [x] Fees are added to the invoice as line items **after** payment completion (not upfront), ensuring other payment methods see only the original amount
- [x] Supports both Live and Sandbox (testing) modes
- [x] Secure webhook handler with signature verification (auto-marks invoices as paid)
- [x] Open source and ready for community use and improvement

## To Do / Ideas for Enhancement

- [ ] Refund support: Add a function to process refunds via the PayPal API from WHMCS admin
- [ ] Transaction logging: Log all API requests/responses for easier debugging and compliance
- [ ] Multi-language support: Add language files for gateway messages
- [ ] Error handling: Improve user-facing error messages and admin notifications
- [ ] More webhook events: Support additional PayPal events (e.g., refunds, disputes)
- [ ] Unit/integration tests: Add tests for easier maintenance and contributions
- [ ] UI enhancements: Add more details to the payment button or invoice notes

## Installation & Setup

### 1. Download or Clone

```bash
git clone https://github.com/ProgrammerNomad/WHMCS-Paypal.git
```

### 2. Copy Files

Copy the `modules/gateways/paypalcustom.php` file to your WHMCS installation's `modules/gateways/` directory.
Copy the `modules/gateways/callback/paypalcustom.php` file to your WHMCS installation's `modules/gateways/callback/` directory.

### 3. Create the "DontChargeFee" Custom Field

To enable client-specific fee exemptions:
1. In your WHMCS admin area, go to **Setup > Custom Client Fields**.
2. Click **Add New Custom Field**.
3. Set the following:
   - **Field Name**: DontChargeFee
   - **Field Type**: Tick Box
   - **Description**: Check this box to exempt the client from PayPal processing fees.
   - **Show on Order Form**: Unchecked (optional, based on your preference)
   - **Admin Only**: Checked (to restrict access to admins)
4. Save the field. This creates a checkbox in each client's profile under **Clients > View/Search Clients > [Client Name] > Custom Fields**.

### 4. Activate Gateway

In your WHMCS admin area, go to **Setup > Payments > Payment Gateways**.
Activate **PayPal Custom API Gateway**.

### 5. Configure Gateway

Fill in the following fields:

- **PayPal Client ID**: Your PayPal REST API Client ID (from developer.paypal.com)
- **PayPal Client Secret**: Your PayPal REST API Client Secret
- **PayPal Webhook ID**: The Webhook ID you get when you create a webhook in your PayPal app
- **Mode**: Select `Live` for production or `Sandbox` for testing
- **PayPal Fee Percentage**: The percentage fee to add (e.g., 5.95 for 5.95%)
- **PayPal Fixed Fee**: The fixed fee to add (e.g., 0.30 for $0.30)

### 6. Set Up PayPal Webhook

1. In your PayPal Developer Dashboard, go to your app and add a webhook.
2. Set the webhook URL to:
    ```
    https://yourdomain.com/modules/gateways/callback/paypalcustom.php
    ```
3. Select at least the following event: `CHECKOUT.ORDER.APPROVED`
4. Copy the Webhook ID and enter it in the gateway configuration.

## How It Works

When a client selects PayPal Custom API Gateway:
1. The module creates a PayPal order via the REST API with the original invoice amount (fees are not added upfront to avoid showing them to other payment methods).
2. The client is redirected to PayPal to complete payment.
3. Upon payment completion, PayPal sends a webhook to your WHMCS.
4. The webhook handler:
   - Verifies the webhook signature for security.
   - Captures the payment.
   - Checks the client's "DontChargeFee" custom field:
     - If checked (enabled), no fees are added, and the invoice is marked as paid for the original amount.
     - If not checked (disabled), dynamic fees (percentage + fixed) are calculated based on the original amount, added as a new line item to the invoice, the total is recalculated, and the invoice is marked as paid for the full amount (original + fees).
5. The invoice is automatically marked as paid, and fees (if applicable) appear as a separate line item.

This ensures:
- Other payment methods see only the original invoice amount.
- Fees are applied dynamically and only after successful PayPal payment.
- Exempted clients (via DontChargeFee) are not charged fees.

## Testing

### 1. Sandbox Mode
Switch to Sandbox mode and use sandbox API credentials to test payments and webhook notifications.

### 2. Test Fee Addition
- Create a test invoice for a client **without** DontChargeFee checked.
- Pay via PayPal – verify fees are added as a line item after payment.
- Check WHMCS logs for fee addition details.

### 3. Test Fee Exemption
- Create a test invoice for a client **with** DontChargeFee checked.
- Pay via PayPal – verify no fees are added.
- Check WHMCS logs for the exemption message.

### 4. Test Other Payment Methods
- Ensure other gateways (e.g., credit card) show only the original amount (no fees).

## License

MIT License. See [LICENSE](LICENSE).

## Contributing

Pull requests and suggestions are welcome! Please test thoroughly, especially the DontChargeFee logic and fee calculations.

## Author

[ProgrammerNomad](https://github.com/ProgrammerNomad)
# WHMCS-Paypal