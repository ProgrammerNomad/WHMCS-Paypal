<?php
/**
 * PayPal Custom API Gateway Webhook/Callback Handler for WHMCS
 *
 * Place this file at modules/gateways/callback/paypalcustom.php
 *
 * This script handles PayPal webhooks and marks invoices as paid in WHMCS.
 * You must configure your PayPal app to send webhooks to this URL.
 *
 * For full security, implement webhook signature verification as per PayPal docs.
 */

require_once '../../../init.php';
require_once '../../../includes/gatewayfunctions.php';
require_once '../../../includes/invoicefunctions.php';

$gatewayModuleName = 'paypalcustom';
$gatewayParams = getGatewayVariables($gatewayModuleName);
if (!$gatewayParams['type']) {
    die('Module Not Activated');
}

// Handle PayPal Webhook
$body = file_get_contents('php://input');
$headers = getallheaders();
$event = json_decode($body, true);


// Webhook signature verification
$paypalMode = $gatewayParams['mode'] ?? 'live';
$paypalClientId = $gatewayParams['clientId'];
$paypalClientSecret = $gatewayParams['clientSecret'];
$paypalWebhookId = $gatewayParams['webhookId'];
$paypalApiUrl = $paypalMode === 'sandbox'
    ? 'https://api.sandbox.paypal.com/v1/notifications/verify-webhook-signature'
    : 'https://api.paypal.com/v1/notifications/verify-webhook-signature';

// Get PayPal access token
function paypalcustom_getAccessToken_callback($clientId, $clientSecret, $mode) {
    $url = $mode === 'sandbox'
        ? 'https://api.sandbox.paypal.com/v1/oauth2/token'
        : 'https://api.paypal.com/v1/oauth2/token';
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

$accessToken = paypalcustom_getAccessToken_callback($paypalClientId, $paypalClientSecret, $paypalMode);
if (!$accessToken) {
    header('HTTP/1.1 401 Unauthorized');
    echo 'PayPal API authentication failed.';
    exit;
}

$verificationData = [
    'auth_algo' => $headers['Paypal-Auth-Algo'] ?? '',
    'cert_url' => $headers['Paypal-Cert-Url'] ?? '',
    'transmission_id' => $headers['Paypal-Transmission-Id'] ?? '',
    'transmission_sig' => $headers['Paypal-Transmission-Sig'] ?? '',
    'transmission_time' => $headers['Paypal-Transmission-Time'] ?? '',
    'webhook_id' => $paypalWebhookId,
    'webhook_event' => $event,
];

$ch = curl_init($paypalApiUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $accessToken,
]);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($verificationData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
$verifyResult = curl_exec($ch);
if (curl_errno($ch)) {
    header('HTTP/1.1 400 Bad Request');
    echo 'PayPal webhook verification error: ' . curl_error($ch);
    exit;
}
curl_close($ch);
$verifyData = json_decode($verifyResult, true);
if (empty($verifyData['verification_status']) || $verifyData['verification_status'] !== 'SUCCESS') {
    header('HTTP/1.1 400 Bad Request');
    echo 'PayPal webhook signature verification failed.';
    exit;
}

if (isset($event['event_type']) && $event['event_type'] === 'CHECKOUT.ORDER.APPROVED') {
    $invoiceId = $event['resource']['purchase_units'][0]['reference_id'];
    $txnId = $event['resource']['id'];
    $paymentAmount = $event['resource']['purchase_units'][0]['amount']['value'];
    $paymentCurrency = $event['resource']['purchase_units'][0]['amount']['currency_code'];

    // Validate invoice ID
    $invoiceId = checkCbInvoiceID($invoiceId, $gatewayParams['name']);
    // Check for duplicate transaction
    checkCbTransID($txnId);

    // Add payment to invoice
    addInvoicePayment(
        $invoiceId,
        $txnId,
        $paymentAmount,
        '',
        $gatewayModuleName
    );
    header('HTTP/1.1 200 OK');
    echo 'Webhook processed.';
    exit;
}

header('HTTP/1.1 400 Bad Request');
echo 'Invalid webhook.';
