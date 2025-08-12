<?php
class ZiinaPayment {
    private $apiUrl;
    private $secretKey;
    private $testMode;
    
    public function __construct() {
        // Get configuration from config file for better security
        $this->apiUrl = defined('ZIINA_API_URL') ? ZIINA_API_URL : 'https://api-v2.ziina.com/api/payment_intent';
        $this->secretKey = defined('ZIINA_API_KEY') ? ZIINA_API_KEY : '';
        $this->testMode = defined('ZIINA_TEST_MODE') ? ZIINA_TEST_MODE : false;
        
        // Validate that we have the required API key
        if (empty($this->secretKey)) {
            throw new Exception('Ziina API key is not configured');
        }
    }

    public function createPaymentIntent($order_id, $amountAED, $message = 'AleppoGift Order') {
        // Validate inputs
        if (!$order_id || !is_numeric($order_id) || $order_id <= 0) {
            return ['success' => false, 'error' => 'Invalid order ID'];
        }
        
        if (!$amountAED || !is_numeric($amountAED) || $amountAED <= 0) {
            return ['success' => false, 'error' => 'Invalid amount'];
        }
        
        // Prepare payload
        $payload = [
            "amount" => (int) round($amountAED * 100), // Convert to cents
            "currency_code" => "AED",
            "message" => "$message #$order_id",
            "success_url" => "https://www.aleppogift.com/thankyou.php?order=$order_id",
            "cancel_url"  => "https://www.aleppogift.com/checkout.php?order=$order_id",
            "failure_url" => "https://www.aleppogift.com/checkout.php?order=$order_id",
            "test" => $this->testMode,
            "transaction_source" => "directApi",
            "expiry" => (string)(round(microtime(true) * 1000) + 86400000), // 24h in ms
            "allow_tips" => true
        ];

        $headers = [
            "Authorization: Bearer {$this->secretKey}",
            "Content-Type: application/json"
        ];

        // Log the request for debugging (without sensitive data)
        $logPayload = $payload;
        error_log("Ziina Payment Request for Order #$order_id: Amount AED " . $amountAED);

        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'AleppoGift/1.0'
        ]);

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // Handle cURL errors
        if ($error) {
            error_log("Ziina cURL Error for Order #$order_id: $error");
            return ['success' => false, 'error' => "Payment service temporarily unavailable"];
        }

        // Handle HTTP errors
        if ($status >= 400) {
            error_log("Ziina HTTP Error for Order #$order_id: Status $status, Response: $response");
        }

        $json = json_decode($response, true);
        
        // Handle JSON decode errors
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Ziina JSON decode error for Order #$order_id: " . json_last_error_msg());
            return ['success' => false, 'error' => 'Invalid response from payment service'];
        }

        // Success response handling
        if ($status === 201 && isset($json['redirect_url'])) {
            error_log("Ziina Payment Intent created successfully for Order #$order_id");
            return [
                'success' => true,
                'payment_url' => $json['redirect_url'],
                'payment_id' => $json['id'] ?? null,
                'status' => $json['status'] ?? null
            ];
        }

        // Error response handling
        $errorMessage = $json['message'] ?? 'Unknown payment service error';
        error_log("Ziina Payment Failed for Order #$order_id: $errorMessage");
        
        return [
            'success' => false,
            'error' => $errorMessage,
            'status_code' => $status,
            'full_response' => $this->testMode ? $json : null // Only return full response in test mode
        ];
    }
    
    /**
     * Check if payment is in test mode
     */
    public function isTestMode() {
        return $this->testMode;
    }
    
    /**
     * Set test mode (useful for development/staging)
     */
    public function setTestMode($testMode) {
        $this->testMode = (bool) $testMode;
    }
    
    /**
     * Validate webhook payload (if implementing webhooks in the future)
     */
    public function validateWebhook($payload, $signature) {
        // Implementation for webhook validation would go here
        // This is a placeholder for future webhook implementation
        return false;
    }
    
    /**
     * Get payment status from Ziina (if API supports it)
     */
    public function getPaymentStatus($payment_id) {
        // This would be used to check payment status
        // Placeholder for future implementation
        return ['success' => false, 'error' => 'Not implemented'];
    }
}
// Usage example:
// $ziina = new ZiinaPayment();
// $result = $ziina->createPaymentIntent($order_id, $amount, $message);