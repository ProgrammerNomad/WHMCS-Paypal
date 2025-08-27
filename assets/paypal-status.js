/**
 * PayPal Custom Gateway - Payment Status Handler
 * 
 * This script handles payment status display and auto-refresh for pending payments
 */
(function() {
    'use strict';
    
    // Check if we're on an invoice page with PayPal status
    const urlParams = new URLSearchParams(window.location.search);
    const status = urlParams.get('status');
    const gateway = urlParams.get('gateway');
    const invoiceId = urlParams.get('id');
    
    if (gateway !== 'paypalcustom' || !status || !invoiceId) {
        return; // Not a PayPal payment status page
    }
    
    // Create status message container
    function createStatusMessage(type, title, message, autoRefresh = false) {
        // Remove any existing status messages
        const existingAlert = document.querySelector('.paypal-status-alert');
        if (existingAlert) {
            existingAlert.remove();
        }
        
        const alertClass = type === 'waiting' ? 'alert-info' : 
                          type === 'cancelled' ? 'alert-warning' : 'alert-success';
        
        const iconClass = type === 'waiting' ? 'fa-clock-o' : 
                         type === 'cancelled' ? 'fa-times-circle' : 'fa-check-circle';
        
        const statusHtml = `
            <div class="alert ${alertClass} paypal-status-alert" style="margin: 20px 0; padding: 15px; border-radius: 5px;">
                <i class="fa ${iconClass}" style="margin-right: 10px;"></i>
                <strong>${title}</strong><br>
                ${message}
                ${autoRefresh ? '<div class="progress-bar" style="margin-top: 10px;"><div class="progress-fill"></div></div>' : ''}
            </div>
        `;
        
        // Insert at the top of the invoice content
        const invoiceContent = document.querySelector('.invoice-content') || 
                              document.querySelector('#invoice') || 
                              document.querySelector('.panel-body');
        
        if (invoiceContent) {
            invoiceContent.insertAdjacentHTML('afterbegin', statusHtml);
        }
        
        // Add CSS for progress bar if auto-refresh
        if (autoRefresh) {
            const style = document.createElement('style');
            style.textContent = `
                .progress-bar {
                    width: 100%;
                    height: 4px;
                    background-color: #e0e0e0;
                    border-radius: 2px;
                    overflow: hidden;
                }
                .progress-fill {
                    height: 100%;
                    background-color: #007bff;
                    border-radius: 2px;
                    animation: progressAnimation 30s linear forwards;
                }
                @keyframes progressAnimation {
                    from { width: 0%; }
                    to { width: 100%; }
                }
            `;
            document.head.appendChild(style);
        }
    }
    
    // Handle different status types
    switch (status) {
        case 'waitingconfirmation':
            createStatusMessage(
                'waiting',
                'Payment Verification in Progress',
                'Your PayPal payment is being processed. Please wait while we verify your payment. This page will automatically refresh in 30 seconds.',
                true
            );
            
            // Auto-refresh after 30 seconds
            setTimeout(() => {
                // Remove status parameter and refresh
                const newUrl = window.location.pathname + '?id=' + invoiceId;
                window.location.href = newUrl;
            }, 30000);
            
            // Also check payment status via AJAX every 10 seconds
            let checkCount = 0;
            const maxChecks = 6; // Check for 1 minute
            const checkInterval = setInterval(() => {
                checkCount++;
                fetch(`${window.location.origin}/modules/gateways/callback/paypalcustom.php?check=1&invoiceid=${invoiceId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'paid') {
                            clearInterval(checkInterval);
                            // Show success message and redirect
                            createStatusMessage(
                                'success',
                                'Payment Confirmed!',
                                'Your payment has been successfully verified. Redirecting...'
                            );
                            setTimeout(() => {
                                window.location.href = window.location.pathname + '?id=' + invoiceId;
                            }, 2000);
                        }
                    })
                    .catch(error => {
                        console.log('Payment check failed:', error);
                    });
                
                if (checkCount >= maxChecks) {
                    clearInterval(checkInterval);
                }
            }, 10000);
            break;
            
        case 'cancelled':
            createStatusMessage(
                'cancelled',
                'Payment Cancelled',
                'Your PayPal payment was cancelled. You can try again by clicking the PayPal payment button below.'
            );
            
            // Remove status parameter from URL after showing message
            setTimeout(() => {
                const newUrl = window.location.pathname + '?id=' + invoiceId;
                window.history.replaceState({}, document.title, newUrl);
            }, 1000);
            break;
            
        case 'success':
            createStatusMessage(
                'success',
                'Payment Successful!',
                'Your PayPal payment has been completed successfully. Thank you!'
            );
            
            // Remove status parameter from URL after showing message
            setTimeout(() => {
                const newUrl = window.location.pathname + '?id=' + invoiceId;
                window.history.replaceState({}, document.title, newUrl);
            }, 5000);
            break;
    }
    
    // Add manual refresh button for waiting status
    if (status === 'waitingconfirmation') {
        setTimeout(() => {
            const alert = document.querySelector('.paypal-status-alert');
            if (alert) {
                const refreshButton = document.createElement('button');
                refreshButton.className = 'btn btn-primary btn-sm';
                refreshButton.style.marginTop = '10px';
                refreshButton.innerHTML = '<i class="fa fa-refresh"></i> Check Payment Status';
                refreshButton.onclick = () => {
                    window.location.href = window.location.pathname + '?id=' + invoiceId;
                };
                alert.appendChild(refreshButton);
            }
        }, 5000);
    }
})();
