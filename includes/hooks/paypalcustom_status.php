<?php

/**
 * PayPal Custom Gateway - Client Area Hook
 * 
 * This file should be placed in /includes/hooks/
 * It handles payment status display and includes necessary JavaScript
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

// Hook to add PayPal status handling to invoice pages
add_hook('ClientAreaPageViewInvoice', 1, function($vars) {
    // Check if PayPal Custom gateway is being used
    $invoiceId = $vars['invoiceid'];
    
    // Only add script if we have status parameters
    if (isset($_GET['status']) && isset($_GET['gateway']) && $_GET['gateway'] === 'paypalcustom') {
        return [
            'paypalStatusScript' => '<script src="' . $vars['systemurl'] . 'modules/gateways/assets/paypal-status.js"></script>'
        ];
    }
    
    return [];
});

// Hook to add status message display
add_hook('ClientAreaPageViewInvoice', 2, function($vars) {
    if (isset($_GET['status']) && isset($_GET['gateway']) && $_GET['gateway'] === 'paypalcustom') {
        $status = $_GET['status'];
        $statusMessage = '';
        
        switch ($status) {
            case 'waitingconfirmation':
                $statusMessage = '
                    <div class="alert alert-info" style="margin: 20px 0;">
                        <i class="fa fa-clock-o"></i> 
                        <strong>Payment Verification in Progress</strong><br>
                        Your PayPal payment is being processed. Please wait while we verify your payment.
                        This page will automatically refresh shortly.
                    </div>
                ';
                break;
                
            case 'cancelled':
                $statusMessage = '
                    <div class="alert alert-warning" style="margin: 20px 0;">
                        <i class="fa fa-times-circle"></i> 
                        <strong>Payment Cancelled</strong><br>
                        Your PayPal payment was cancelled. You can try again using the payment options below.
                    </div>
                ';
                break;
                
            case 'success':
                $statusMessage = '
                    <div class="alert alert-success" style="margin: 20px 0;">
                        <i class="fa fa-check-circle"></i> 
                        <strong>Payment Successful!</strong><br>
                        Your PayPal payment has been completed successfully. Thank you!
                    </div>
                ';
                break;
        }
        
        return [
            'paypalStatusMessage' => $statusMessage
        ];
    }
    
    return [];
});

// Hook to check payment status via AJAX
add_hook('ClientAreaPage', 1, function($vars) {
    if ($vars['filename'] === 'viewinvoice' && isset($_GET['check']) && isset($_GET['invoiceid'])) {
        $invoiceId = (int)$_GET['invoiceid'];
        
        // Get invoice details
        $invoice = localAPI('GetInvoice', ['invoiceid' => $invoiceId]);
        
        header('Content-Type: application/json');
        
        if ($invoice['result'] === 'success') {
            echo json_encode([
                'status' => $invoice['status'],
                'balance' => $invoice['balance']
            ]);
        } else {
            echo json_encode(['status' => 'unknown']);
        }
        exit;
    }
});

?>
