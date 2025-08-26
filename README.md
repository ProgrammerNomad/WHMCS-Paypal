# PayPal Custom Gateway for WHMCS

A modern, open-source PayPal payment gateway module for WHMCS (v8.13.1+) with configurable fee options.

## Features
- Set custom PayPal fee percentage and fixed fee per payment (e.g., 5.95% + $0.30)
- Fees are automatically calculated and added to the invoice total
- Simple, user-friendly PayPal payment form
- Open source and ready for community use and improvement

## Installation
1. Download or clone this repository:
	```
	git clone https://github.com/ProgrammerNomad/WHMCS-Paypal.git
	```
2. Copy the `modules/gateways/paypalcustom` folder to your WHMCS installation's `modules/gateways/` directory.
3. In your WHMCS admin area, go to **Setup > Payments > Payment Gateways**.
4. Activate **PayPal Custom Gateway**.
5. Enter your PayPal email, fee percentage, and fixed fee in the gateway configuration.

## Configuration Options
- **PayPal Email**: Your PayPal account email address.
- **PayPal Fee Percentage**: The percentage fee to add (e.g., 5.95 for 5.95%).
- **PayPal Fixed Fee**: The fixed fee to add (e.g., 0.30 for $0.30).

## How It Works
When a client selects PayPal Custom Gateway, the module calculates the total including the configured fees and redirects the client to PayPal to complete payment.

## License
MIT License. See [LICENSE](LICENSE).

## Contributing
Pull requests and suggestions are welcome!

## Author
[ProgrammerNomad](https://github.com/ProgrammerNomad)
# WHMCS-Paypal