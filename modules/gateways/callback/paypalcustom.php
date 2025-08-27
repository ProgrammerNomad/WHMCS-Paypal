<?php

/**
 * PayPal Custom Payment Gateway Webhook Callback
 * 
 * For WHMCS 8.13.1+
 * Place this file at modules/gateways/callback/paypalcustom.php
 * 
 * This script handles PayPal webhooks and marks invoices as paid in WHMCS.
 * You must configure your PayPal app to send webhooks to this URL.
 */

// WHMCS Gateway Callback File Template
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

// Get invoice details for currency conversion
function paypalcustom_getInvoiceDetails($invoiceId) {
    $command = 'GetInvoice';
    $postData = array(
        'invoiceid' => $invoiceId,
    );
    $results = localAPI($command, $postData);
    return $results;
}

// Add PayPal fee as invoice line item
function paypalcustom_addPayPalFeeToInvoice($invoiceId, $feeAmount, $feePercent, $feeFixed, $originalAmount, $currency) {
    if ($feeAmount <= 0) {
        return true; // No fee to add
    }
    
    // Check if PayPal fee item already exists to avoid duplicates
    $command = 'GetInvoice';
    $postData = array('invoiceid' => $invoiceId);
    $invoice = localAPI($command, $postData);
    
    // Check if PayPal fee already added
    if (isset($invoice['items']['item'])) {
        $items = $invoice['items']['item'];
        // Handle both single item and multiple items cases
        if (!isset($items[0])) {
            $items = array($items); // Convert single item to array
        }
        
        foreach ($items as $item) {
            if (strpos($item['description'], 'PayPal Payment Gateway Fee') !== false || 
                strpos($item['description'], 'PayPal Processing Fee') !== false) {
                return true; // Fee already added
            }
        }
    }
    
    // Add PayPal fee as new invoice line item using the correct API
    $command = 'AddInvoiceItem';
    $postData = array(
        'invoiceid' => $invoiceId,
        'description' => "PayPal Processing Fee ({$feePercent}% + {$currency} {$feeFixed})",
        'amount' => number_format($feeAmount, 2, '.', ''),
        'taxed' => false // Usually payment gateway fees are not taxed
    );
    
    $result = localAPI($command, $postData);
    
    if ($result['result'] === 'success') {
        logTransaction('paypalcustom', [
            'invoice_id' => $invoiceId,
            'fee_amount' => $feeAmount,
            'fee_percent' => $feePercent,
            'fee_fixed' => $feeFixed,
            'original_amount' => $originalAmount,
            'currency' => $currency,
            'api_result' => $result
        ], 'PayPal Fee Added to Invoice Successfully');
        return true;
    } else {
        logTransaction('paypalcustom', [
            'invoice_id' => $invoiceId,
            'error' => $result,
            'fee_amount' => $feeAmount,
            'command_used' => $command,
            'post_data' => $postData
        ], 'Failed to Add PayPal Fee to Invoice');
        return false;
    }
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

// Handle Order Approved - Capture Payment Automatically
if (isset($event['event_type']) && $event['event_type'] === 'CHECKOUT.ORDER.APPROVED') {
    $orderId = $event['resource']['id'];
    $invoiceId = $event['resource']['purchase_units'][0]['custom_id'] ?? $event['resource']['purchase_units'][0]['reference_id'];
    $paypalAmount = $event['resource']['purchase_units'][0]['amount']['value'];
    $paypalCurrency = $event['resource']['purchase_units'][0]['amount']['currency_code'];
    
    if (!$invoiceId) {
        logTransaction($gatewayParams['paymentmethod'], $event, 'Order Approved - Invoice ID not found');
        header('HTTP/1.1 400 Bad Request');
        echo 'Invoice ID not found in order data.';
        exit;
    }
    
    // Get invoice details to check original currency and amount
    $invoiceDetails = paypalcustom_getInvoiceDetails($invoiceId);
    if ($invoiceDetails['result'] !== 'success') {
        logTransaction($gatewayParams['paymentmethod'], $event, 'Order Approved - Could not fetch invoice details');
        header('HTTP/1.1 400 Bad Request');
        echo 'Could not fetch invoice details.';
        exit;
    }
    
    $originalCurrency = $invoiceDetails['currency'];
    $originalAmount = $invoiceDetails['total'];
    
    // Calculate PayPal fees (from gateway configuration)
    $feePercent = (float)($gatewayParams['feePercent'] ?? 0);
    $feeFixed = (float)($gatewayParams['feeFixed'] ?? 0);
    $calculatedFee = round(($originalAmount * $feePercent / 100) + $feeFixed, 2);
    
    // Capture the order to complete payment
    $captureUrl = $paypalMode === 'sandbox' 
        ? "https://api.sandbox.paypal.com/v2/checkout/orders/{$orderId}/capture"
        : "https://api.paypal.com/v2/checkout/orders/{$orderId}/capture";
    
    $ch = curl_init($captureUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $accessToken,
    ]);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, '{}'); // Empty JSON body for capture
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
    $captureResult = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $captureData = json_decode($captureResult, true);
    
    if ($httpCode !== 201 || empty($captureData['status']) || $captureData['status'] !== 'COMPLETED') {
        logTransaction($gatewayParams['paymentmethod'], [
            'order_id' => $orderId,
            'capture_response' => $captureData,
            'http_code' => $httpCode
        ], 'Order Capture Failed');
        header('HTTP/1.1 400 Bad Request');
        echo 'Failed to capture PayPal order.';
        exit;
    }
    
    // Extract capture details
    $captureId = $captureData['purchase_units'][0]['payments']['captures'][0]['id'];
    $capturedAmount = $captureData['purchase_units'][0]['payments']['captures'][0]['amount']['value'];
    $capturedCurrency = $captureData['purchase_units'][0]['payments']['captures'][0]['amount']['currency_code'];
    
    // Validate invoice ID
    $invoiceId = checkCbInvoiceID($invoiceId, $gatewayParams['paymentmethod']);
    // Check for duplicate transaction
    checkCbTransID($captureId);
    
    // Step 1: Add PayPal fee to invoice as line item (BEFORE marking as paid)
    $feeAdded = paypalcustom_addPayPalFeeToInvoice(
        $invoiceId, 
        $calculatedFee, 
        $feePercent, 
        $feeFixed, 
        $originalAmount, 
        $originalCurrency
    );
    
    if (!$feeAdded) {
        logTransaction($gatewayParams['paymentmethod'], [
            'invoice_id' => $invoiceId,
            'error' => 'Failed to add PayPal fee to invoice'
        ], 'Warning: Could not add PayPal fee to invoice');
        
        // If we can't add the fee, just mark invoice as paid with original amount
        $amountToAdd = ($originalCurrency === $capturedCurrency) ? $originalAmount : $originalAmount;
    } else {
        // Fee added successfully, get updated invoice total
        $updatedInvoiceDetails = paypalcustom_getInvoiceDetails($invoiceId);
        if ($updatedInvoiceDetails['result'] === 'success') {
            $newInvoiceTotal = $updatedInvoiceDetails['total'];
            // Use the new invoice total (original + fee)
            $amountToAdd = ($originalCurrency === $capturedCurrency) ? $capturedAmount : $newInvoiceTotal;
        } else {
            // Fallback: use calculated amount
            $amountToAdd = ($originalCurrency === $capturedCurrency) ? $capturedAmount : ($originalAmount + $calculatedFee);
        }
    }
    
    // Step 3: Log the successful payment with currency and fee details
    logTransaction($gatewayParams['paymentmethod'], [
        'order_id' => $orderId,
        'capture_id' => $captureId,
        'paypal_amount' => $capturedAmount,
        'paypal_currency' => $capturedCurrency,
        'invoice_amount' => $originalAmount,
        'invoice_currency' => $originalCurrency,
        'calculated_fee' => $calculatedFee,
        'fee_added_to_invoice' => $feeAdded,
        'amount_added_to_invoice' => $amountToAdd,
        'capture_data' => $captureData
    ], 'Payment Captured Successfully with Fee Added to Invoice');
    
    // Step 4: Add payment to invoice (now including the fee in total)
    addInvoicePayment(
        $invoiceId,
        $captureId,
        $amountToAdd, // Total amount including fees
        0, // Payment fee (set to 0 since we added it as line item)
        $gatewayParams['paymentmethod']
    );
    
    header('HTTP/1.1 200 OK');
    echo 'Payment captured, PayPal fee added to invoice, and invoice marked as paid.';
    exit;
}

// Handle Payment Capture Completed (if you also want to listen for this)
if (isset($event['event_type']) && $event['event_type'] === 'PAYMENT.CAPTURE.COMPLETED') {
    $captureId = $event['resource']['id'];
    $paypalAmount = $event['resource']['amount']['value'];
    $paypalCurrency = $event['resource']['amount']['currency_code'];
    
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
    
    // Get invoice details to check original currency and amount
    $invoiceDetails = paypalcustom_getInvoiceDetails($invoiceId);
    if ($invoiceDetails['result'] !== 'success') {
        logTransaction($gatewayParams['paymentmethod'], $event, 'Payment Capture Completed - Could not fetch invoice details');
        header('HTTP/1.1 400 Bad Request');
        echo 'Could not fetch invoice details.';
        exit;
    }
    
    $originalCurrency = $invoiceDetails['currency'];
    $originalAmount = $invoiceDetails['total'];
    
    // Calculate PayPal fees (from gateway configuration)
    $feePercent = (float)($gatewayParams['feePercent'] ?? 0);
    $feeFixed = (float)($gatewayParams['feeFixed'] ?? 0);
    $calculatedFee = round(($originalAmount * $feePercent / 100) + $feeFixed, 2);
    
    // Validate invoice ID
    $invoiceId = checkCbInvoiceID($invoiceId, $gatewayParams['paymentmethod']);
    // Check for duplicate transaction
    checkCbTransID($captureId);
    
    // Step 1: Add PayPal fee to invoice as line item (BEFORE marking as paid)
    $feeAdded = paypalcustom_addPayPalFeeToInvoice(
        $invoiceId, 
        $calculatedFee, 
        $feePercent, 
        $feeFixed, 
        $originalAmount, 
        $originalCurrency
    );
    
    // Step 2: Determine the amount to add as payment
    if ($originalCurrency === $paypalCurrency) {
        $amountToAdd = $paypalAmount;
    } else {
        $amountToAdd = $originalAmount + $calculatedFee;
    }
    
    // Step 3: Log the successful payment with currency and fee details
    logTransaction($gatewayParams['paymentmethod'], [
        'capture_id' => $captureId,
        'paypal_amount' => $paypalAmount,
        'paypal_currency' => $paypalCurrency,
        'invoice_amount' => $originalAmount,
        'invoice_currency' => $originalCurrency,
        'calculated_fee' => $calculatedFee,
        'fee_added_to_invoice' => $feeAdded,
        'amount_added_to_invoice' => $amountToAdd,
        'event_data' => $event
    ], 'Payment Completed Successfully with Fee Added to Invoice (Auto-Capture)');
    
    // Step 4: Add payment to invoice (now including the fee in total)
    addInvoicePayment(
        $invoiceId,
        $captureId,
        $amountToAdd, // Total amount including fees
        0, // Payment fee (set to 0 since we added it as line item)
        $gatewayParams['paymentmethod']
    );
    
    header('HTTP/1.1 200 OK');
    echo 'Payment completed, PayPal fee added to invoice, and invoice marked as paid.';
    exit;
}

// Handle other events if needed
if (isset($event['event_type'])) {
    logTransaction($gatewayParams['paymentmethod'], $event, 'Unhandled Webhook Event: ' . $event['event_type']);
}

header('HTTP/1.1 200 OK');
echo 'Webhook received but not processed.';
