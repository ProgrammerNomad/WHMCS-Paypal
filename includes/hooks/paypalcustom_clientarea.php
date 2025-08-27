<?php
/**
 * PayPal Custom Gateway - Client Area Hook
 * 
 * This file should be placed in: includes/hooks/paypalcustom_clientarea.php
 * 
 * It adds JavaScript and CSS to handle payment status messages on invoice pages
 */

add_hook('ClientAreaPageViewInvoice', 1, function($vars) {
    // Only add scripts if this is an invoice page and PayPal Custom gateway is involved
    $gateway = $_GET['gateway'] ?? '';
    $status = $_GET['status'] ?? '';
    
    if ($gateway === 'paypalcustom' && !empty($status)) {
        // Add CSS styles
        $css = '
        <style>
        .paypal-status-message {
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
            font-weight: bold;
            text-align: center;
        }
        .paypal-status-waiting {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
        .paypal-status-cancelled {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .paypal-status-success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .paypal-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(0,0,0,.3);
            border-radius: 50%;
            border-top-color: #007bff;
            animation: spin 1s ease-in-out infinite;
            margin-right: 10px;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .paypal-countdown {
            font-size: 14px;
            margin-top: 10px;
            opacity: 0.8;
        }
        </style>';
        
        // Add JavaScript
        $js = '
        <script>
        document.addEventListener("DOMContentLoaded", function() {
            var gateway = new URLSearchParams(window.location.search).get("gateway");
            var status = new URLSearchParams(window.location.search).get("status");
            
            if (gateway === "paypalcustom" && status) {
                showPayPalStatus(status);
            }
        });
        
        function showPayPalStatus(status) {
            var messageContainer = document.createElement("div");
            var invoiceContent = document.querySelector(".invoice-details, .panel-body, .card-body") || document.body;
            
            if (status === "waitingconfirmation") {
                messageContainer.className = "paypal-status-message paypal-status-waiting";
                messageContainer.innerHTML = `
                    <div>
                        <span class="paypal-spinner"></span>
                        <strong>Payment Verification in Progress</strong>
                    </div>
                    <div>Your PayPal payment is being verified. Please wait...</div>
                    <div class="paypal-countdown">This page will automatically refresh in <span id="countdown">30</span> seconds.</div>
                `;
                
                // Auto-refresh countdown
                var countdownElement = messageContainer.querySelector("#countdown");
                var timeLeft = 30;
                var countdownTimer = setInterval(function() {
                    timeLeft--;
                    if (countdownElement) {
                        countdownElement.textContent = timeLeft;
                    }
                    if (timeLeft <= 0) {
                        clearInterval(countdownTimer);
                        // Remove status parameters and refresh
                        var url = new URL(window.location);
                        url.searchParams.delete("status");
                        url.searchParams.delete("gateway");
                        url.searchParams.delete("token");
                        url.searchParams.delete("PayerID");
                        window.location.href = url.toString();
                    }
                }, 1000);
                
            } else if (status === "cancelled") {
                messageContainer.className = "paypal-status-message paypal-status-cancelled";
                messageContainer.innerHTML = `
                    <div><strong>Payment Cancelled</strong></div>
                    <div>You cancelled the PayPal payment. You can try again or choose a different payment method.</div>
                `;
                
                // Auto-hide after 10 seconds
                setTimeout(function() {
                    if (messageContainer.parentNode) {
                        messageContainer.style.opacity = "0";
                        setTimeout(function() {
                            messageContainer.remove();
                            // Clean up URL
                            var url = new URL(window.location);
                            url.searchParams.delete("status");
                            url.searchParams.delete("gateway");
                            window.history.replaceState({}, document.title, url.toString());
                        }, 500);
                    }
                }, 10000);
                
            } else if (status === "success") {
                messageContainer.className = "paypal-status-message paypal-status-success";
                messageContainer.innerHTML = `
                    <div><strong>Payment Successful!</strong></div>
                    <div>Your PayPal payment has been processed successfully.</div>
                `;
                
                // Auto-hide after 5 seconds
                setTimeout(function() {
                    if (messageContainer.parentNode) {
                        messageContainer.style.opacity = "0";
                        setTimeout(function() {
                            messageContainer.remove();
                            // Clean up URL
                            var url = new URL(window.location);
                            url.searchParams.delete("status");
                            url.searchParams.delete("gateway");
                            url.searchParams.delete("token");
                            url.searchParams.delete("PayerID");
                            window.history.replaceState({}, document.title, url.toString());
                        }, 500);
                    }
                }, 5000);
            }
            
            // Insert the message at the top of the invoice content
            if (invoiceContent && invoiceContent.firstChild) {
                invoiceContent.insertBefore(messageContainer, invoiceContent.firstChild);
            }
        }
        </script>';
        
        return array_merge($vars, [
            'paypalcustom_css' => $css,
            'paypalcustom_js' => $js
        ]);
    }
    
    return $vars;
});

// Hook to add the CSS and JS to the page header
add_hook('ClientAreaHeadOutput', 1, function($vars) {
    $gateway = $_GET['gateway'] ?? '';
    $status = $_GET['status'] ?? '';
    
    if ($gateway === 'paypalcustom' && !empty($status)) {
        $output = '';
        
        // Add CSS
        $output .= '
        <style>
        .paypal-status-message {
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
            font-weight: bold;
            text-align: center;
            position: relative;
            z-index: 1000;
        }
        .paypal-status-waiting {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
        .paypal-status-cancelled {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .paypal-status-success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .paypal-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(0,0,0,.3);
            border-radius: 50%;
            border-top-color: #007bff;
            animation: spin 1s ease-in-out infinite;
            margin-right: 10px;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .paypal-countdown {
            font-size: 14px;
            margin-top: 10px;
            opacity: 0.8;
        }
        </style>';
        
        // Add JavaScript
        $output .= '
        <script>
        document.addEventListener("DOMContentLoaded", function() {
            var gateway = new URLSearchParams(window.location.search).get("gateway");
            var status = new URLSearchParams(window.location.search).get("status");
            
            if (gateway === "paypalcustom" && status) {
                showPayPalStatus(status);
            }
        });
        
        function showPayPalStatus(status) {
            var messageContainer = document.createElement("div");
            
            // Try to find the best place to insert the message
            var targetElements = [
                document.querySelector(".invoice-details"),
                document.querySelector(".panel-body"),
                document.querySelector(".card-body"),
                document.querySelector(".content"),
                document.querySelector("#main-body"),
                document.querySelector("body")
            ];
            
            var invoiceContent = null;
            for (var i = 0; i < targetElements.length; i++) {
                if (targetElements[i]) {
                    invoiceContent = targetElements[i];
                    break;
                }
            }
            
            if (status === "waitingconfirmation") {
                messageContainer.className = "paypal-status-message paypal-status-waiting";
                messageContainer.innerHTML = `
                    <div>
                        <span class="paypal-spinner"></span>
                        <strong>Payment Verification in Progress</strong>
                    </div>
                    <div>Your PayPal payment is being verified. Please wait...</div>
                    <div class="paypal-countdown">This page will automatically refresh in <span id="countdown">30</span> seconds.</div>
                `;
                
                // Auto-refresh countdown
                var countdownElement = messageContainer.querySelector("#countdown");
                var timeLeft = 30;
                var countdownTimer = setInterval(function() {
                    timeLeft--;
                    if (countdownElement) {
                        countdownElement.textContent = timeLeft;
                    }
                    if (timeLeft <= 0) {
                        clearInterval(countdownTimer);
                        // Remove status parameters and refresh
                        var url = new URL(window.location);
                        url.searchParams.delete("status");
                        url.searchParams.delete("gateway");
                        url.searchParams.delete("token");
                        url.searchParams.delete("PayerID");
                        window.location.href = url.toString();
                    }
                }, 1000);
                
            } else if (status === "cancelled") {
                messageContainer.className = "paypal-status-message paypal-status-cancelled";
                messageContainer.innerHTML = `
                    <div><strong>Payment Cancelled</strong></div>
                    <div>You cancelled the PayPal payment. You can try again or choose a different payment method.</div>
                `;
                
                // Auto-hide after 10 seconds
                setTimeout(function() {
                    if (messageContainer.parentNode) {
                        messageContainer.style.opacity = "0";
                        setTimeout(function() {
                            messageContainer.remove();
                            // Clean up URL
                            var url = new URL(window.location);
                            url.searchParams.delete("status");
                            url.searchParams.delete("gateway");
                            window.history.replaceState({}, document.title, url.toString());
                        }, 500);
                    }
                }, 10000);
                
            } else if (status === "success") {
                messageContainer.className = "paypal-status-message paypal-status-success";
                messageContainer.innerHTML = `
                    <div><strong>Payment Successful!</strong></div>
                    <div>Your PayPal payment has been processed successfully.</div>
                `;
                
                // Auto-hide after 5 seconds
                setTimeout(function() {
                    if (messageContainer.parentNode) {
                        messageContainer.style.opacity = "0";
                        setTimeout(function() {
                            messageContainer.remove();
                            // Clean up URL
                            var url = new URL(window.location);
                            url.searchParams.delete("status");
                            url.searchParams.delete("gateway");
                            url.searchParams.delete("token");
                            url.searchParams.delete("PayerID");
                            window.history.replaceState({}, document.title, url.toString());
                        }, 500);
                    }
                }, 5000);
            }
            
            // Insert the message at the top of the content
            if (invoiceContent && invoiceContent.firstChild) {
                invoiceContent.insertBefore(messageContainer, invoiceContent.firstChild);
            } else if (invoiceContent) {
                invoiceContent.appendChild(messageContainer);
            }
        }
        </script>';
        
        return $output;
    }
    
    return '';
});
?>
