<?php

/**
 function paypalcustom_MetaData()
{
    return [
        'DisplayName' => 'PayPal Custom API Gateway',
        'APIVersion' => '1.1',
        'DisableLocalCreditCardInput' => true,
        'TokenisedStorage' => false,
    ];
}Custom API Gateway for WHMCS
 * Version: 1.0.0
 * Author: ProgrammerNomad
 * Open Source: https://github.com/ProgrammerNomad/WHMCS-Paypal
 *
 * Modern PayPal gateway using REST API, with configurable fees and webhook support.
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

function paypalcustom_MetaData()
{
    return [
        'DisplayName' => 'PayPal Custom Gateway',
        'APIVersion' => '1.1',
        'DisableLocalCredtCardInput' => true,
        'TokenisedStorage' => false,
    ];
}

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
            'Description' => 'Enter your PayPal Webhook ID for payment notifications.',
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

// Helper: Get PayPal API Access Token
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


// Payment Link: Create PayPal Order and redirect
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
    $orderData = [
        'intent' => 'CAPTURE',
        'purchase_units' => [[
            'reference_id' => $invoiceId,
            'description' => $description,
            'amount' => [
                'currency_code' => $currency,
                'value' => number_format($totalAmount, 2, '.', ''),
            ],
        ]],
        'application_context' => [
            'return_url' => $systemUrl . 'modules/gateways/callback/paypalcustom.php?success=1&invoiceid=' . $invoiceId,
            'cancel_url' => $systemUrl . 'modules/gateways/callback/paypalcustom.php?cancel=1&invoiceid=' . $invoiceId,
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
    $html = '<a href="' . htmlspecialchars($approveUrl) . '" class="btn btn-primary" target="_blank">Pay with PayPal (Total: ' . $totalAmount . ' ' . htmlspecialchars($currency) . ')</a>';
    $html .= '<br><small>PayPal fees applicable: ' . $feePercent . '% + $' . $feeFixed . ' (Total fee: $' . $fee . ')</small>';
    return $html;
}

// Webhook/Callback Handler (to be placed in callback/paypalcustom.php)
// This file should verify the webhook signature and mark invoice as paid in WHMCS.
// See README for implementation details.
