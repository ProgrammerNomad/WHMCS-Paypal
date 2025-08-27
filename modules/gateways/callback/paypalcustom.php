<?php
/**
 * PayPal Custom API Gateway Webhook/Callback Handler for WHMCS
 *
 * Place this file at modules/gateways/callback/paypalcustom.php
 *
 * This script handles PayPal webhooks and marks invoices as paid in WHMCS.
 * You must configure your PayPal app to send webhooks to this URL.
 */

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

$gatewayModuleName = 'paypalcustom';
$gatewayParams = getGatewayVariables($gatewayModuleName);
if (!$gatewayParams['type']) {
    die('Module Not Activated');
}

// Handle PayPal Return URLs (success/cancel)
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $invoiceId = (int)$_GET['invoiceid'];
    if ($invoiceId) {
        header('Location: ' . $CONFIG['SystemURL'] . '/viewinvoice.php?id=' . $invoiceId);
        exit;
    }
}

if (isset($_GET['cancel']) && $_GET['cancel'] == '1') {
    $invoiceId = (int)$_GET['invoiceid'];
    if ($invoiceId) {
        header('Location: ' . $CONFIG['SystemURL'] . '/viewinvoice.php?id=' . $invoiceId . '&paymentfailed=1');
        exit;
    }
}

// Handle PayPal Webhook
$body = file_get_contents('php://input');
$headers = getallheaders();
$event = json_decode($body, true);

// Log all webhook events for debugging
logTransaction($gatewayParams['paymentmethod'], $event, 'Webhook Received: ' . ($event['event_type'] ?? 'Unknown'));

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

// Handle Payment Capture Completed (AUTO-CAPTURE)
if (isset($event['event_type']) && $event['event_type'] === 'PAYMENT.CAPTURE.COMPLETED') {
    $captureId = $event['resource']['id'];
    $paymentAmount = $event['resource']['amount']['value'];
    
    // Get invoice ID from custom_id or supplementary_data
    $invoiceId = null;
    if (isset($event['resource']['custom_id'])) {
        $invoiceId = $event['resource']['custom_id'];
    } elseif (isset($event['resource']['supplementary_data']['related_ids']['order_id'])) {
        // If custom_id not available, we need to get it from the order
        $orderId = $event['resource']['supplementary_data']['related_ids']['order_id'];
        // Fetch order details to get reference_id (invoice ID)
        $orderUrl = $paypalMode === 'sandbox' 
            ? "https://api.sandbox.paypal.com/v2/checkout/orders/{$orderId}"
            : "https://api.paypal.com/v2/checkout/orders/{$orderId}";
        
        $ch = curl_init($orderUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
        $orderResult = curl_exec($ch);
        curl_close($ch);
        
        $orderData = json_decode($orderResult, true);
        if (isset($orderData['purchase_units'][0]['reference_id'])) {
            $invoiceId = $orderData['purchase_units'][0]['reference_id'];
        }
    }
    
    if (!$invoiceId) {
        logTransaction($gatewayParams['paymentmethod'], $event, 'Payment Capture Completed - Invoice ID not found');
        header('HTTP/1.1 400 Bad Request');
        echo 'Invoice ID not found in payment data.';
        exit;
    }
    
    // Validate invoice ID
    $invoiceId = checkCbInvoiceID($invoiceId, $gatewayParams['paymentmethod']);
    // Check for duplicate transaction
    checkCbTransID($captureId);
    
    // Log the successful payment
    logTransaction($gatewayParams['paymentmethod'], $event, 'Payment Completed Successfully (Auto-Capture)');
    
    // Add payment to invoice
    addInvoicePayment(
        $invoiceId,
        $captureId,
        $paymentAmount,
        0, // Payment fee (if any)
        $gatewayParams['paymentmethod']
    );
    
    header('HTTP/1.1 200 OK');
    echo 'Payment completed and invoice marked as paid.';
    exit;
}

// Handle other events if needed
if (isset($event['event_type'])) {
    logTransaction($gatewayParams['paymentmethod'], $event, 'Unhandled Webhook Event: ' . $event['event_type']);
}

header('HTTP/1.1 200 OK');
echo 'Webhook received but not processed.';
