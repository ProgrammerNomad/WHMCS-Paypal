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

// TODO: Verify webhook signature using $headers and $gatewayParams['webhookId']
// See https://developer.paypal.com/docs/api/webhooks/v1/#verify-webhook-signature

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
