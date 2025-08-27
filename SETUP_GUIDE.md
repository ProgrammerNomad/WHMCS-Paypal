# PayPal Custom Gateway for WHMCS - Complete Setup Guide

## 📁 File Structure

```
WHMCS-Paypal/
├── modules/gateways/
│   ├── paypalcustom.php                    # Main gateway module
│   ├── callback/
│   │   └── paypalcustom.php               # Webhook & return URL handler
│   └── paypalcustom/
│       └── assets/
│           └── paypal_status.js           # Status message JavaScript
├── includes/hooks/
│   └── paypalcustom_clientarea.php        # WHMCS hook for status messages
├── docs/                                  # Documentation (optional)
├── composer.json                          # Package metadata
├── whmcs.json                            # WHMCS marketplace metadata
└── README.md                             # Setup instructions
```

## 🚀 Installation Steps

### 1. Upload Files to WHMCS
Copy the following files to your WHMCS installation:

```bash
# Core gateway files
modules/gateways/paypalcustom.php
modules/gateways/callback/paypalcustom.php

# Assets (for status messages)
modules/gateways/paypalcustom/assets/paypal_status.js

# Hook for status messages (optional but recommended)
includes/hooks/paypalcustom_clientarea.php
```

### 2. PayPal App Configuration

1. **Create PayPal App**:
   - Go to https://developer.paypal.com/
   - Create a new app
   - Get Client ID and Client Secret

2. **Configure Webhook**:
   - Set webhook URL: `https://yourdomain.com/modules/gateways/callback/paypalcustom.php`
   - Enable events: `PAYMENT.CAPTURE.COMPLETED`, `CHECKOUT.ORDER.APPROVED`
   - Get Webhook ID

3. **Set Return URLs** (handled automatically):
   - Return URL: `https://yourdomain.com/modules/gateways/callback/paypalcustom.php?id={invoice_id}`
   - Cancel URL: `https://yourdomain.com/modules/gateways/callback/paypalcustom.php?id={invoice_id}&cancel=1`

### 3. WHMCS Gateway Configuration

1. **Activate Gateway**:
   - Go to **Setup > Payments > Payment Gateways**
   - Find "PayPal Custom" and click **Activate**

2. **Configure Settings**:
   ```
   Display Name: PayPal
   Sandbox Mode: ☑ (for testing) / ☐ (for live)
   PayPal Client ID: [Your Client ID]
   PayPal Client Secret: [Your Client Secret]
   PayPal Webhook ID: [Your Webhook ID]
   PayPal Fee Percentage: 5.95
   PayPal Fixed Fee: 0.30
   ```

### 4. Status Messages Setup (Optional)

Choose ONE method:

#### Method A: WHMCS Hook (Automatic)
- File already included: `includes/hooks/paypalcustom_clientarea.php`
- No additional setup required

#### Method B: Manual JavaScript Include
Add to your theme footer (`templates/your-theme/footer.tpl`):
```html
<script src="{$WEB_ROOT}/modules/gateways/paypalcustom/assets/paypal_status.js"></script>
```

## 🔄 Payment Flow

### User Experience:
1. **Customer views invoice**: Shows original amount (e.g., ₹1,000.00)
2. **Clicks "Pay with PayPal"**: Redirected to PayPal for total amount including fees (₹1,084.40)
3. **Completes payment**: Returns to invoice with "Payment verification in progress" message
4. **Auto-refresh after 30 seconds**: Shows updated invoice with payment marked
5. **Payment cancelled**: Shows "Payment cancelled" message with option to retry

### Technical Flow:
1. **Payment Request**: Creates PayPal order with fees included
2. **Return URL**: Handles user return from PayPal (GET request)
3. **Webhook**: Processes payment notification (POST request)
4. **Fee Addition**: Adds PayPal fee as invoice line item
5. **Payment Marking**: Marks invoice as paid with correct total

## 💰 Fee Handling

### Automatic Fee Calculation:
- **Percentage Fee**: 5.95% of invoice amount
- **Fixed Fee**: $0.30 (converted to invoice currency)
- **Total Fee**: (Amount × 5.95%) + Fixed Fee

### Example (INR Invoice):
```
Original Invoice: ₹1,000.00
PayPal Fee: ₹84.40 (5.95% + ₹24.90)
Total Charged: ₹1,084.40

Invoice Line Items:
- Web Hosting Service: ₹1,000.00
- PayPal Processing Fee: ₹84.40
- Total: ₹1,084.40
- Status: Paid
```

### Fee Transparency:
- Fees are added as separate line items
- Customers see exact fee breakdown
- No hidden charges
- No negative balances

## 🎨 Status Messages

### Waiting for Confirmation:
- Blue message with spinner
- "Payment Verification in Progress"
- Auto-refreshes after 30 seconds
- URL: `?status=waitingconfirmation&gateway=paypalcustom`

### Payment Cancelled:
- Red message
- "Payment Cancelled - You can try again"
- Auto-hides after 10 seconds
- URL: `?status=cancelled&gateway=paypalcustom`

### Payment Successful:
- Green message
- "Payment Successful!"
- Auto-hides after 5 seconds
- URL: `?status=success&gateway=paypalcustom`

## 🔧 Configuration Options

### Gateway Parameters:
```php
'sandbox' => 'on/off'              // Sandbox mode toggle
'clientId' => 'string'             // PayPal Client ID
'clientSecret' => 'string'         // PayPal Client Secret
'webhookId' => 'string'            // PayPal Webhook ID
'feePercent' => '5.95'             // Fee percentage
'feeFixed' => '0.30'               // Fixed fee in USD
```

### Webhook Events:
- `CHECKOUT.ORDER.APPROVED` - Order approved
- `PAYMENT.CAPTURE.COMPLETED` - Payment captured (invoice marked as paid)

## 🧪 Testing

### Sandbox Testing:
1. Enable sandbox mode
2. Use PayPal sandbox credentials
3. Use sandbox webhook URL
4. Test with sandbox PayPal accounts

### Test Scenarios:
- Regular payment completion
- Payment cancellation
- Multi-currency invoices
- Fee calculation accuracy
- Webhook signature verification

## 🔒 Security Features

### Webhook Verification:
- PayPal signature verification
- Duplicate transaction prevention
- WHMCS invoice validation
- Secure API communications

### Error Handling:
- Comprehensive logging
- Graceful failure modes
- User-friendly error messages
- Admin notifications

## 📊 Logging & Debug

### Transaction Logs:
- All PayPal interactions logged
- Fee calculations tracked
- Payment status changes recorded
- Error conditions documented

### Debug Information:
Check WHMCS transaction logs for:
- Fee parameter retrieval
- PayPal API responses
- Webhook processing
- Payment marking results

## ⚡ Performance

### Optimizations:
- Minimal database queries
- Efficient webhook processing
- Cached API tokens
- Streamlined user redirects

### Resource Usage:
- Lightweight JavaScript
- Efficient PHP processing
- Minimal server overhead
- Fast user experience

## 🆘 Troubleshooting

### Common Issues:

**Status messages not showing:**
- Check JavaScript file path
- Verify hook file placement
- Check browser console for errors

**Fees not being added:**
- Verify gateway configuration
- Check transaction logs
- Confirm webhook URL setup

**Webhook failures:**
- Verify webhook URL accessibility
- Check PayPal app configuration
- Confirm SSL certificate validity

**Payment not marking:**
- Check WHMCS callback functions
- Verify invoice ID tracking
- Confirm webhook signature verification

## 📞 Support

For issues or questions:
1. Check WHMCS transaction logs
2. Verify PayPal webhook logs
3. Review gateway configuration
4. Test in sandbox mode first

## 🎉 Features Summary

✅ **Modern PayPal REST API**  
✅ **Automatic fee calculation**  
✅ **Transparent fee disclosure**  
✅ **Multi-currency support**  
✅ **User-friendly status messages**  
✅ **Comprehensive logging**  
✅ **Webhook security**  
✅ **WHMCS 8.13.1+ compatible**  
✅ **Production-ready**  

Your PayPal gateway is now ready for production use! 🚀
