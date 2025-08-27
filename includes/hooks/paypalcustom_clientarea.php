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
            padding: 15px !important;
            margin: 15px 0 !important;
            border-radius: 5px !important;
            font-weight: bold !important;
            text-align: center !important;
            position: relative !important;
            z-index: 1000 !important;
            font-family: Arial, sans-serif !important;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;
        }
        .paypal-status-waiting {
            background-color: #d1ecf1 !important;
            border: 1px solid #bee5eb !important;
            color: #0c5460 !important;
        }
        .paypal-status-cancelled {
            background-color: #f8d7da !important;
            border: 1px solid #f5c6cb !important;
            color: #721c24 !important;
        }
        .paypal-status-success {
            background-color: #d4edda !important;
            border: 1px solid #c3e6cb !important;
            color: #155724 !important;
        }
        .paypal-spinner {
            display: inline-block !important;
            width: 20px !important;
            height: 20px !important;
            border: 3px solid rgba(0,0,0,.3) !important;
            border-radius: 50% !important;
            border-top-color: #007bff !important;
            animation: paypal-spin 1s ease-in-out infinite !important;
            margin-right: 10px !important;
        }
        @keyframes paypal-spin {
            to { transform: rotate(360deg); }
        }
        .paypal-countdown {
            font-size: 14px !important;
            margin-top: 10px !important;
            opacity: 0.8 !important;
        }
        </style>';
        
        // Add JavaScript with enhanced debugging
        $output .= '
        <script>
        console.log("PayPal Custom Gateway: Script loaded");
        console.log("Current URL:", window.location.href);
        
        // Immediate execution - no need to wait for DOM
        (function() {
            var urlParams = new URLSearchParams(window.location.search);
            var gateway = urlParams.get("gateway");
            var status = urlParams.get("status");
            
            console.log("Gateway:", gateway, "Status:", status);
            
            if (gateway === "paypalcustom" && status) {
                console.log("PayPal status detected, showing message for:", status);
                
                // Wait for DOM to be ready
                if (document.readyState === "loading") {
                    document.addEventListener("DOMContentLoaded", function() {
                        showPayPalStatus(status);
                    });
                } else {
                    showPayPalStatus(status);
                }
            }
        })();
        
        function showPayPalStatus(status) {
            console.log("Showing PayPal status:", status);
            
            var messageContainer = document.createElement("div");
            
            // Try multiple strategies to find the best insertion point
            var targetSelectors = [
                ".invoice-details",
                ".invoice-container", 
                ".panel-body",
                ".card-body",
                ".content",
                "#main-body",
                ".container-fluid",
                ".container",
                "main",
                "body"
            ];
            
            var invoiceContent = null;
            for (var i = 0; i < targetSelectors.length; i++) {
                var element = document.querySelector(targetSelectors[i]);
                if (element) {
                    invoiceContent = element;
                    console.log("Found target element:", targetSelectors[i]);
                    break;
                }
            }
            
            if (!invoiceContent) {
                console.error("Could not find suitable container for PayPal message");
                invoiceContent = document.body;
            }
            
            if (status === "waitingconfirmation") {
                messageContainer.className = "paypal-status-message paypal-status-waiting";
                messageContainer.innerHTML = 
                    "<div>" +
                        "<span class=\"paypal-spinner\"></span>" +
                        "<strong>Payment Verification in Progress</strong>" +
                    "</div>" +
                    "<div>Your PayPal payment is being verified. Please wait...</div>" +
                    "<div class=\"paypal-countdown\">This page will automatically refresh in <span id=\"paypal-countdown\">30</span> seconds.</div>";
                
                console.log("Created waiting confirmation message");
                
                // Auto-refresh countdown
                var timeLeft = 30;
                var countdownTimer = setInterval(function() {
                    timeLeft--;
                    var countdownElement = document.getElementById("paypal-countdown");
                    if (countdownElement) {
                        countdownElement.textContent = timeLeft;
                    }
                    if (timeLeft <= 0) {
                        clearInterval(countdownTimer);
                        console.log("Countdown finished, refreshing page");
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
                messageContainer.innerHTML = 
                    "<div><strong>Payment Cancelled</strong></div>" +
                    "<div>You cancelled the PayPal payment. You can try again or choose a different payment method.</div>";
                
                console.log("Created cancelled message");
                
                // Auto-hide after 10 seconds
                setTimeout(function() {
                    if (messageContainer.parentNode) {
                        messageContainer.style.opacity = "0";
                        messageContainer.style.transition = "opacity 0.5s";
                        setTimeout(function() {
                            if (messageContainer.parentNode) {
                                messageContainer.remove();
                            }
                            // Clean up URL
                            var url = new URL(window.location);
                            url.searchParams.delete("status");
                            url.searchParams.delete("gateway");
                            if (window.history && window.history.replaceState) {
                                window.history.replaceState({}, document.title, url.toString());
                            }
                        }, 500);
                    }
                }, 10000);
                
            } else if (status === "success") {
                messageContainer.className = "paypal-status-message paypal-status-success";
                messageContainer.innerHTML = 
                    "<div><strong>Payment Successful!</strong></div>" +
                    "<div>Your PayPal payment has been processed successfully.</div>";
                
                console.log("Created success message");
                
                // Auto-hide after 5 seconds
                setTimeout(function() {
                    if (messageContainer.parentNode) {
                        messageContainer.style.opacity = "0";
                        messageContainer.style.transition = "opacity 0.5s";
                        setTimeout(function() {
                            if (messageContainer.parentNode) {
                                messageContainer.remove();
                            }
                            // Clean up URL
                            var url = new URL(window.location);
                            url.searchParams.delete("status");
                            url.searchParams.delete("gateway");
                            url.searchParams.delete("token");
                            url.searchParams.delete("PayerID");
                            if (window.history && window.history.replaceState) {
                                window.history.replaceState({}, document.title, url.toString());
                            }
                        }, 500);
                    }
                }, 5000);
            }
            
            // Insert the message at the top of the content
            if (invoiceContent) {
                if (invoiceContent.firstChild) {
                    invoiceContent.insertBefore(messageContainer, invoiceContent.firstChild);
                    console.log("Message inserted before first child");
                } else {
                    invoiceContent.appendChild(messageContainer);
                    console.log("Message appended to container");
                }
            } else {
                console.error("No suitable container found for message");
            }
        }
        </script>';
        
        return $output;
    }
    
    return '';
});

// Alternative hook for ClientAreaFooterOutput to ensure the script loads
add_hook('ClientAreaFooterOutput', 1, function($vars) {
    $gateway = $_GET['gateway'] ?? '';
    $status = $_GET['status'] ?? '';
    
    if ($gateway === 'paypalcustom' && !empty($status)) {
        return '
        <script>
        // Immediate execution alternative
        (function() {
            console.log("PayPal Footer Script: Loading for status", "' . $status . '");
            
            // Create and inject CSS if not already present
            if (!document.getElementById("paypal-status-css")) {
                var css = document.createElement("style");
                css.id = "paypal-status-css";
                css.innerHTML = `
                .paypal-status-message {
                    padding: 15px !important;
                    margin: 15px 0 !important;
                    border-radius: 5px !important;
                    font-weight: bold !important;
                    text-align: center !important;
                    position: relative !important;
                    z-index: 1000 !important;
                    font-family: Arial, sans-serif !important;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;
                }
                .paypal-status-waiting {
                    background-color: #d1ecf1 !important;
                    border: 1px solid #bee5eb !important;
                    color: #0c5460 !important;
                }
                .paypal-status-cancelled {
                    background-color: #f8d7da !important;
                    border: 1px solid #f5c6cb !important;
                    color: #721c24 !important;
                }
                .paypal-status-success {
                    background-color: #d4edda !important;
                    border: 1px solid #c3e6cb !important;
                    color: #155724 !important;
                }
                .paypal-spinner {
                    display: inline-block !important;
                    width: 20px !important;
                    height: 20px !important;
                    border: 3px solid rgba(0,0,0,.3) !important;
                    border-radius: 50% !important;
                    border-top-color: #007bff !important;
                    animation: paypal-spin 1s ease-in-out infinite !important;
                    margin-right: 10px !important;
                }
                @keyframes paypal-spin {
                    to { transform: rotate(360deg); }
                }
                .paypal-countdown {
                    font-size: 14px !important;
                    margin-top: 10px !important;
                    opacity: 0.8 !important;
                }
                `;
                document.head.appendChild(css);
            }
            
            // Show status message immediately if DOM is ready, otherwise wait
            if (document.readyState === "loading") {
                document.addEventListener("DOMContentLoaded", function() {
                    showPayPalStatusMessage("' . $status . '");
                });
            } else {
                showPayPalStatusMessage("' . $status . '");
            }
            
            function showPayPalStatusMessage(status) {
                console.log("Showing PayPal status message:", status);
                
                // Remove any existing messages
                var existingMessage = document.querySelector(".paypal-status-message");
                if (existingMessage) {
                    existingMessage.remove();
                }
                
                var messageContainer = document.createElement("div");
                
                // Enhanced target selection with more specific selectors
                var targetSelectors = [
                    ".invoice-container .panel-body",
                    ".invoice-details",
                    ".panel-body",
                    ".card-body", 
                    ".content-wrapper",
                    ".main-content",
                    "#content",
                    ".container-fluid .row",
                    ".container .row",
                    "main",
                    "body"
                ];
                
                var invoiceContent = null;
                for (var i = 0; i < targetSelectors.length; i++) {
                    var element = document.querySelector(targetSelectors[i]);
                    if (element && element.offsetParent !== null) { // Ensure element is visible
                        invoiceContent = element;
                        console.log("Found target element:", targetSelectors[i]);
                        break;
                    }
                }
                
                if (!invoiceContent) {
                    console.warn("No suitable container found, using body");
                    invoiceContent = document.body;
                }
                
                if (status === "waitingconfirmation") {
                    messageContainer.className = "paypal-status-message paypal-status-waiting";
                    messageContainer.innerHTML = 
                        "<div>" +
                            "<span class=\"paypal-spinner\"></span>" +
                            "<strong>Payment Verification in Progress</strong>" +
                        "</div>" +
                        "<div>Your PayPal payment is being verified. Please wait...</div>" +
                        "<div class=\"paypal-countdown\">This page will automatically refresh in <span id=\"paypal-countdown-footer\">30</span> seconds.</div>";
                    
                    // Auto-refresh countdown
                    var timeLeft = 30;
                    var countdownTimer = setInterval(function() {
                        timeLeft--;
                        var countdownElement = document.getElementById("paypal-countdown-footer");
                        if (countdownElement) {
                            countdownElement.textContent = timeLeft;
                        }
                        if (timeLeft <= 0) {
                            clearInterval(countdownTimer);
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
                    messageContainer.innerHTML = 
                        "<div><strong>Payment Cancelled</strong></div>" +
                        "<div>You cancelled the PayPal payment. You can try again or choose a different payment method.</div>";
                    
                } else if (status === "success") {
                    messageContainer.className = "paypal-status-message paypal-status-success";
                    messageContainer.innerHTML = 
                        "<div><strong>Payment Successful!</strong></div>" +
                        "<div>Your PayPal payment has been processed successfully.</div>";
                }
                
                // Insert message
                if (invoiceContent.firstChild) {
                    invoiceContent.insertBefore(messageContainer, invoiceContent.firstChild);
                } else {
                    invoiceContent.appendChild(messageContainer);
                }
                
                console.log("PayPal status message inserted successfully");
            }
        })();
        </script>';
    }
    
    return '';
});
?>
