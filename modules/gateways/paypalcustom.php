<?php
/**
 * PayPal Custom API Gateway for WHMCS
 * 
 * A modern, secure PayPal payment gateway using REST API with configurable fees
 * 
 * @package    WHMCS
 * @author     ProgrammerNomad <https://github.com/ProgrammerNomad>
 * @copyright  2025 ProgrammerNomad
 * @license    MIT License
 * @version    1.0.0
 * @link       https://github.com/ProgrammerNomad/WHMCS-Paypal
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

/**
 * Gateway Metadata
 * 
 * Defines the gateway properties and capabilities
 *
 * @return array
 */
function paypalcustom_MetaData()
{
    return [
        'DisplayName' => 'PayPal Custom API Gateway',
        'APIVersion' => '1.1',
        'DisableLocalCreditCardInput' => true,
        'TokenisedStorage' => false,
        'failedEmail' => 'Credit Card Payment Failed',
        'successEmail' => 'Credit Card Payment Confirmation',
        'pendingEmail' => 'Credit Card Payment Pending',
    ];
}

/**
 * Gateway Configuration Options
 * 
 * Defines the configuration fields shown in WHMCS admin
 *
 * @return array
 */
function paypalcustom_config()
{
    return [
        'FriendlyName' => [
            'Type' => 'System',
            'Value' => 'PayPal Custom API Gateway',
        ],
        'clientId' => [
            'FriendlyName' => 'PayPal Client ID',
            'Type' => 'text',
            'Size' => '50',
            'Description' => 'Enter your PayPal REST API Client ID.',
        ],
        'clientSecret' => [
            'FriendlyName' => 'PayPal Client Secret',
            'Type' => 'password',
            'Size' => '50',
            'Description' => 'Enter your PayPal REST API Client Secret.',
        ],
        'webhookId' => [
            'FriendlyName' => 'PayPal Webhook ID',
            'Type' => 'text',
            'Size' => '50',
            'Description' => 'Enter your PayPal Webhook ID for payment notifications.<br><strong>Webhook URL to use in PayPal:</strong> ' . (isset($_SERVER['HTTP_HOST']) ? 'https://' . $_SERVER['HTTP_HOST'] : 'https://yourdomain.com') . '/modules/gateways/callback/paypalcustom.php',
        ],
        'feePercent' => [
            'FriendlyName' => 'PayPal Fee Percentage',
            'Type' => 'text',
            'Size' => '5',
            'Default' => '5.95',
            'Description' => 'Enter the percentage fee (e.g., 5.95 for 5.95%)',
        ],
        'feeFixed' => [
            'FriendlyName' => 'PayPal Fixed Fee',
            'Type' => 'text',
            'Size' => '5',
            'Default' => '0.30',
            'Description' => 'Enter the fixed fee (e.g., 0.30 for $0.30)',
        ],
        'mode' => [
            'FriendlyName' => 'Mode',
            'Type' => 'dropdown',
            'Options' => [
                'live' => 'Live',
                'sandbox' => 'Sandbox',
            ],
            'Default' => 'live',
            'Description' => 'Select Live for production or Sandbox for testing.',
        ],
    ];
}

/**
 * Get PayPal API Access Token
 * 
 * Obtains an access token from PayPal for API authentication
 *
 * @param array $params Gateway configuration parameters
 * @return string|false Access token on success, false on failure
 */
function paypalcustom_getAccessToken($params)
{
    $clientId = $params['clientId'];
    $clientSecret = $params['clientSecret'];
    $mode = $params['mode'] ?? 'live';
    $url = $mode === 'sandbox' ? 'https://api.sandbox.paypal.com/v1/oauth2/token' : 'https://api.paypal.com/v1/oauth2/token';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, $clientId . ":" . $clientSecret);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        return false;
    }
    curl_close($ch);
    $data = json_decode($result, true);
    return $data['access_token'] ?? false;
}

/**
 * Generate Payment Link
 * 
 * Creates a PayPal order and returns the payment button/link
 *
 * @param array $params Gateway and invoice parameters from WHMCS
 * @return string HTML payment button or error message
 */
function paypalcustom_link($params)
{
    $clientId = $params['clientId'];
    $clientSecret = $params['clientSecret'];
    $mode = $params['mode'] ?? 'live';
    $feePercent = (float)$params['feePercent'];
    $feeFixed = (float)$params['feeFixed'];
    $invoiceId = $params['invoiceid'];
    $description = $params['description'];
    $amount = $params['amount'];
    $currency = $params['currency'];
    $systemUrl = $params['systemurl'];

    $fee = round(($amount * $feePercent / 100) + $feeFixed, 2);
    $totalAmount = round($amount + $fee, 2);

    $accessToken = paypalcustom_getAccessToken($params);
    if (!$accessToken) {
        return '<div class="alert alert-danger">Unable to connect to PayPal API. Please contact support.</div>';
    }

    $apiUrl = $mode === 'sandbox' ? 'https://api.sandbox.paypal.com/v2/checkout/orders' : 'https://api.paypal.com/v2/checkout/orders';

    // Generate a unique invoice_id for PayPal (to avoid DUPLICATE_INVOICE_ID)
    // Keep the original invoice ID in custom_id for webhook retrieval
    $uniqueInvoiceId = $invoiceId . '_' . time() . '_' . substr(md5(uniqid()), 0, 8); // Unique per attempt

    $orderData = [
        'intent' => 'CAPTURE',
        'purchase_units' => [[
            'reference_id' => $invoiceId, // Original invoice ID
            'custom_id' => $invoiceId,    // Original invoice ID for webhook
            'invoice_id' => $uniqueInvoiceId, // Unique ID for PayPal
            'description' => $description,
            'amount' => [
                'currency_code' => $currency,
                'value' => number_format($totalAmount, 2, '.', ''),
            ],
        ]],
        'application_context' => [
            'return_url' => $systemUrl . 'modules/gateways/callback/paypalcustom.php?id=' . $invoiceId,
            'cancel_url' => $systemUrl . 'modules/gateways/callback/paypalcustom.php?id=' . $invoiceId . '&cancel=1',
        ],
    ];

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $accessToken,
    ]);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($orderData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        return '<div class="alert alert-danger">PayPal API error: ' . curl_error($ch) . '</div>';
    }
    curl_close($ch);
    $data = json_decode($result, true);
    if (empty($data['links'])) {
        return '<div class="alert alert-danger">PayPal API error: Invalid response.</div>';
    }
    $approveUrl = '';
    foreach ($data['links'] as $link) {
        if ($link['rel'] === 'approve') {
            $approveUrl = $link['href'];
            break;
        }
    }
    if (!$approveUrl) {
        return '<div class="alert alert-danger">PayPal API error: No approval link found.</div>';
    }
    // Add message logic here, before the payment button
    $messages = [];
    if (isset($_GET['cancelled']) && $_GET['cancelled'] == 1) {
        $messages[] = '<div class="alert alert-warning"><strong>Payment Cancelled:</strong> You have cancelled the PayPal payment. Please try again.</div>';
    }
    if (isset($_GET['status']) && $_GET['status'] == 'waitingconfirmation') {
        $messages[] = '<div class="alert alert-info"><strong>Please Wait:</strong> Payment is processing. Please wait for confirmation.<br><div>Page will refresh in <span id="countdown">30</span> seconds.</div></div>';
        $messages[] = '<script>
            var timeLeft = 30;
            var countdownElement = document.getElementById("countdown");
            var timer = setInterval(function() {
                timeLeft--;
                if (countdownElement) countdownElement.textContent = timeLeft;
                if (timeLeft <= 0) {
                    clearInterval(timer);
                    window.location.reload();  // Reload the current URL with all parameters intact
                }
            }, 1000);
        </script>';
    }

    $messageHtml = '';
    if (!empty($messages)) {
        $messageHtml = implode('', $messages);
    }

    // Now build the full HTML, including messages before the button
    $html = $messageHtml . '<a href="' . htmlspecialchars($approveUrl) . '" class="btn btn-primary">Pay with PayPal (Total: ' . $totalAmount . ' ' . htmlspecialchars($currency) . ')</a>';
    $html .= '<br><small>PayPal fees applicable: ' . $feePercent . '% + $' . $feeFixed . ' (Total fee: $' . $fee . ')</small>';
    return $html;
}

// Webhook/Callback Handler (to be placed in callback/paypalcustom.php)
// This file should verify the webhook signature and mark invoice as paid in WHMCS.
// See README for implementation details.
