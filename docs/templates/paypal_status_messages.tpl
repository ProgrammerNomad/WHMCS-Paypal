{* PayPal Custom Gateway Status Messages *}
{* Add this code to your WHMCS invoice template (usually in templates/your-theme/viewinvoice.tpl) *}

{if $smarty.get.gateway eq 'paypalcustom' and $smarty.get.status}
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
</style>

{if $smarty.get.status eq 'waitingconfirmation'}
<div class="paypal-status-message paypal-status-waiting" id="paypal-status">
    <div>
        <span class="paypal-spinner"></span>
        <strong>Payment Verification in Progress</strong>
    </div>
    <div>Your PayPal payment is being verified. Please wait...</div>
    <div class="paypal-countdown">This page will automatically refresh in <span id="countdown">30</span> seconds.</div>
</div>

<script>
var timeLeft = 30;
var countdownTimer = setInterval(function() {
    timeLeft--;
    var countdownElement = document.getElementById('countdown');
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
</script>

{elseif $smarty.get.status eq 'cancelled'}
<div class="paypal-status-message paypal-status-cancelled" id="paypal-status">
    <div><strong>Payment Cancelled</strong></div>
    <div>You cancelled the PayPal payment. You can try again or choose a different payment method.</div>
</div>

<script>
// Auto-hide after 10 seconds
setTimeout(function() {
    var statusMessage = document.getElementById('paypal-status');
    if (statusMessage) {
        statusMessage.style.opacity = '0';
        setTimeout(function() {
            statusMessage.remove();
            // Clean up URL
            var url = new URL(window.location);
            url.searchParams.delete('status');
            url.searchParams.delete('gateway');
            window.history.replaceState({}, document.title, url.toString());
        }, 500);
    }
}, 10000);
</script>

{elseif $smarty.get.status eq 'success'}
<div class="paypal-status-message paypal-status-success" id="paypal-status">
    <div><strong>Payment Successful!</strong></div>
    <div>Your PayPal payment has been processed successfully.</div>
</div>

<script>
// Auto-hide after 5 seconds
setTimeout(function() {
    var statusMessage = document.getElementById('paypal-status');
    if (statusMessage) {
        statusMessage.style.opacity = '0';
        setTimeout(function() {
            statusMessage.remove();
            // Clean up URL
            var url = new URL(window.location);
            url.searchParams.delete('status');
            url.searchParams.delete('gateway');
            url.searchParams.delete('token');
            url.searchParams.delete('PayerID');
            window.history.replaceState({}, document.title, url.toString());
        }, 500);
    }
}, 5000);
</script>

{/if}
{/if}
