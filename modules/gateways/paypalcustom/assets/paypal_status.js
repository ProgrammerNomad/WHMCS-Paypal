/**
 * PayPal Custom Gateway - Status Message Handler
 * 
 * Instructions:
 * 1. Upload this file to your WHMCS root directory as "paypal_status.js"
 * 2. Add this line to your WHMCS theme footer (usually in templates/your-theme/footer.tpl):
 *    <script src="{$WEB_ROOT}/paypal_status.js"></script>
 * 
 * OR
 * 
 * 3. Copy the JavaScript code below and paste it directly into your invoice template
 */

document.addEventListener("DOMContentLoaded", function() {
    // Check if we're on an invoice page with PayPal status parameters
    var urlParams = new URLSearchParams(window.location.search);
    var gateway = urlParams.get('gateway');
    var status = urlParams.get('status');
    
    if (gateway === 'paypalcustom' && status) {
        // Add CSS styles dynamically
        var styles = `
        .paypal-status-message {
            padding: 15px !important;
            margin: 15px 0 !important;
            border-radius: 5px !important;
            font-weight: bold !important;
            text-align: center !important;
            position: relative !important;
            z-index: 1000 !important;
            font-family: Arial, sans-serif !important;
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
        
        // Inject CSS
        var styleSheet = document.createElement("style");
        styleSheet.type = "text/css";
        styleSheet.innerText = styles;
        document.head.appendChild(styleSheet);
        
        // Show the appropriate status message
        showPayPalStatus(status);
    }
});

function showPayPalStatus(status) {
    var messageContainer = document.createElement("div");
    
    // Try to find the best place to insert the message
    var targetSelectors = [
        '.invoice-details',
        '.panel-body',
        '.card-body',
        '.content',
        '#main-body',
        '.container',
        '.wrapper',
        'main',
        'body'
    ];
    
    var invoiceContent = null;
    for (var i = 0; i < targetSelectors.length; i++) {
        var element = document.querySelector(targetSelectors[i]);
        if (element) {
            invoiceContent = element;
            break;
        }
    }
    
    if (status === "waitingconfirmation") {
        messageContainer.className = "paypal-status-message paypal-status-waiting";
        messageContainer.innerHTML = 
            '<div>' +
                '<span class="paypal-spinner"></span>' +
                '<strong>Payment Verification in Progress</strong>' +
            '</div>' +
            '<div>Your PayPal payment is being verified. Please wait...</div>' +
            '<div class="paypal-countdown">This page will automatically refresh in <span id="paypal-countdown">30</span> seconds.</div>';
        
        // Auto-refresh countdown
        var timeLeft = 30;
        var countdownTimer = setInterval(function() {
            timeLeft--;
            var countdownElement = document.getElementById('paypal-countdown');
            if (countdownElement) {
                countdownElement.textContent = timeLeft;
            }
            if (timeLeft <= 0) {
                clearInterval(countdownTimer);
                // Remove status parameters and refresh
                var url = new URL(window.location);
                url.searchParams.delete('status');
                url.searchParams.delete('gateway');
                url.searchParams.delete('token');
                url.searchParams.delete('PayerID');
                window.location.href = url.toString();
            }
        }, 1000);
        
    } else if (status === "cancelled") {
        messageContainer.className = "paypal-status-message paypal-status-cancelled";
        messageContainer.innerHTML = 
            '<div><strong>Payment Cancelled</strong></div>' +
            '<div>You cancelled the PayPal payment. You can try again or choose a different payment method.</div>';
        
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
                    url.searchParams.delete('status');
                    url.searchParams.delete('gateway');
                    if (window.history && window.history.replaceState) {
                        window.history.replaceState({}, document.title, url.toString());
                    }
                }, 500);
            }
        }, 10000);
        
    } else if (status === "success") {
        messageContainer.className = "paypal-status-message paypal-status-success";
        messageContainer.innerHTML = 
            '<div><strong>Payment Successful!</strong></div>' +
            '<div>Your PayPal payment has been processed successfully.</div>';
        
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
                    url.searchParams.delete('status');
                    url.searchParams.delete('gateway');
                    url.searchParams.delete('token');
                    url.searchParams.delete('PayerID');
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
        } else {
            invoiceContent.appendChild(messageContainer);
        }
    }
}

// Make function available globally for testing
window.showPayPalStatus = showPayPalStatus;
