# ZiinaPayment Integration - Documentation

## Overview
The ZiinaPayment class provides secure integration with Ziina's payment gateway for processing AED payments in the AleppoGift e-commerce system.

## Security Improvements Made

### 1. **API Key Security**
- ✅ Moved API credentials from hardcoded values to config file
- ✅ Added validation to ensure API key is configured
- ✅ Throws exception if API key is missing

### 2. **Enhanced Error Handling**
- ✅ Input validation for order ID and amount
- ✅ Proper cURL error handling
- ✅ HTTP status code validation
- ✅ JSON decode error handling
- ✅ Comprehensive error logging

### 3. **Improved Configuration**
- ✅ Test mode can be easily toggled via config
- ✅ All configuration centralized in config.php
- ✅ Added ZIINA_TEST_MODE constant

## Configuration

### config.php Settings
```php
// Ziina Payment API
define('ZIINA_API_URL', 'https://api-v2.ziina.com/api/payment_intent');
define('ZIINA_API_KEY', 'your_secret_key_here');
define('ZIINA_TEST_MODE', false); // Set to true for testing
```

## Usage Examples

### Basic Usage
```php
try {
    $ziina = new ZiinaPayment();
    $result = $ziina->createPaymentIntent($order_id, $amount_aed, $message);
    
    if ($result['success']) {
        // Redirect to payment URL
        header("Location: " . $result['payment_url']);
    } else {
        // Handle error
        echo "Payment failed: " . $result['error'];
    }
} catch (Exception $e) {
    echo "Configuration error: " . $e->getMessage();
}
```

### Response Format

#### Success Response
```php
[
    'success' => true,
    'payment_url' => 'https://pay.ziina.com/payment_intent/xxx',
    'payment_id' => 'payment_intent_id',
    'status' => 'requires_payment_instrument'
]
```

#### Error Response
```php
[
    'success' => false,
    'error' => 'Error message',
    'status_code' => 400,
    'full_response' => [...] // Only in test mode
]
```

## Payment Flow

1. **Customer initiates payment** → checkout.php
2. **Create payment intent** → ZiinaPayment::createPaymentIntent()
3. **Redirect to Ziina** → Customer completes payment
4. **Success callback** → thankyou.php (payment marked as paid)
5. **Cancel/Failure** → checkout.php (retry payment)

## URL Structure

- **Success URL**: `https://www.aleppogift.com/thankyou.php?order={order_id}`
- **Cancel URL**: `https://www.aleppogift.com/checkout.php?order={order_id}`
- **Failure URL**: `https://www.aleppogift.com/checkout.php?order={order_id}`

## Methods Available

### createPaymentIntent($order_id, $amountAED, $message)
Creates a payment intent with Ziina.

**Parameters:**
- `$order_id` (int): Unique order identifier
- `$amountAED` (float): Amount in UAE Dirhams
- `$message` (string): Payment description

### isTestMode()
Returns whether the payment gateway is in test mode.

### setTestMode($testMode)
Enables/disables test mode programmatically.

## Error Logging

All payment-related errors are logged to PHP error log:
- Payment creation attempts
- API communication errors
- Invalid responses
- Configuration issues

## Security Recommendations

### For Production:
1. **Environment Variables**: Move API keys to environment variables
2. **HTTPS Only**: Ensure all URLs use HTTPS
3. **Webhook Validation**: Implement webhook signature validation
4. **Rate Limiting**: Add rate limiting for payment attempts
5. **Monitoring**: Set up monitoring for failed payments

### For Development:
1. **Test Mode**: Always use `ZIINA_TEST_MODE = true`
2. **Separate Keys**: Use separate API keys for testing
3. **Debug Logging**: Enable detailed logging in test environment

## Troubleshooting

### Common Issues:

1. **"Ziina API key is not configured"**
   - Check config.php has ZIINA_API_KEY defined
   - Verify API key is not empty

2. **"Payment service temporarily unavailable"**
   - Check internet connectivity
   - Verify Ziina API URL is accessible
   - Check API key validity

3. **"Invalid response from payment service"**
   - Check API response format
   - Verify JSON is valid
   - Check for API changes

### Debug Mode:
Set `ZIINA_TEST_MODE = true` in config.php to get detailed error responses.

## File Structure
```
includes/
├── ZiinaPayment.php          # Main payment class
├── test_ziina.php            # Test script
└── Ziina API details.txt     # Original API documentation

config/
└── config.php                # Configuration with API keys

public/
├── checkout.php              # Payment initiation
└── thankyou.php              # Payment success handling
```

## Testing

Use `includes/test_ziina.php` to test the payment integration:

```bash
php test_ziina.php
```

This will create a test payment intent and display the results.

## Version History

- **v1.0**: Initial implementation with hardcoded credentials
- **v2.0**: Security improvements, better error handling, configurable test mode
