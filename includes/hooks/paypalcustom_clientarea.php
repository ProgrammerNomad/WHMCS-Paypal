<?php
/**
 * PayPal Custom Gateway - Simple Status Messages
 * 
 * Shows status messages based on URL parameters
 */

add_hook('ClientAreaHeadOutput', 1, function($vars) {
    $gateway = $_GET['gateway'] ?? '';
    $status = $_GET['status'] ?? '';
    
    if ($gateway === 'paypalcustom' && !empty($status)) {
        return '
        <style>
        .paypal-status {
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
            font-weight: bold;
            text-align: center;
        }
        .paypal-waiting {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
        .paypal-cancelled {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .paypal-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        </style>
        
        <script>
        document.addEventListener("DOMContentLoaded", function() {
            var status = "' . $status . '";
            var message = "";
            var className = "";
            
            if (status === "waitingconfirmation") {
                message = "<strong>Payment Verification in Progress</strong><br>Your PayPal payment is being verified. Please wait...<br><span id=\"timer\">Page will refresh in 30 seconds</span>";
                className = "paypal-status paypal-waiting";
                
                var timeLeft = 30;
                setInterval(function() {
                    timeLeft--;
                    document.getElementById("timer").innerHTML = "Page will refresh in " + timeLeft + " seconds";
                    if (timeLeft <= 0) {
                        var url = new URL(window.location);
                        url.searchParams.delete("status");
                        url.searchParams.delete("gateway");
                        url.searchParams.delete("token");
                        url.searchParams.delete("PayerID");
                        window.location.href = url.toString();
                    }
                }, 1000);
                
            } else if (status === "cancelled") {
                message = "<strong>Payment Cancelled</strong><br>You cancelled the PayPal payment. You can try again or choose a different payment method.";
                className = "paypal-status paypal-cancelled";
                
            } else if (status === "success") {
                message = "<strong>Payment Successful!</strong><br>Your PayPal payment has been processed successfully.";
                className = "paypal-status paypal-success";
            }
            
            if (message) {
                var div = document.createElement("div");
                div.className = className;
                div.innerHTML = message;
                
                var target = document.querySelector(".invoice-details") || 
                            document.querySelector(".panel-body") || 
                            document.querySelector("body");
                
                if (target && target.firstChild) {
                    target.insertBefore(div, target.firstChild);
                }
            }
        });
        </script>';
    }
    
    return '';
});
?>
