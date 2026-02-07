<?php
// File: includes/generate_payment_link.php
// Generate secure payment links for orders

class PaymentLinkGenerator {
    private $db;
    private $baseUrl;
    
    public function __construct($db) {
        $this->db = $db;
        $this->baseUrl = defined('SITE_URL') ? SITE_URL : 'https://aleppogift.ae';
    }
    
    /**
     * Generate a secure payment link for an order
     * 
     * @param int $orderId Order ID
     * @param float $amount Order total amount
     * @return string Payment link URL
     */
    public function generatePaymentLink($orderId, $amount) {
        // Create a secure token for this order
        $token = $this->generateSecureToken($orderId);
        
        // Store the token in the database for verification
        $this->storePaymentToken($orderId, $token);
        
        // Build payment link - remove trailing slash from base URL if present
        $baseUrl = rtrim($this->baseUrl, '/');
        $paymentUrl = $baseUrl . '/payment.php?order=' . $orderId . '&token=' . $token;
        
        return $paymentUrl;
    }
    
    /**
     * Generate a secure token for order payment
     * 
     * @param int $orderId Order ID
     * @return string Secure token
     */
    private function generateSecureToken($orderId) {
        // Create a unique token using order ID and random data
        $randomData = bin2hex(random_bytes(16));
        $timestamp = time();
        
        // Create hash of order ID + timestamp + random data + secret key
        $secret = defined('DB_PASS') ? DB_PASS : 'aleppogift_secret';
        $token = hash('sha256', $orderId . $timestamp . $randomData . $secret);
        
        return $token;
    }
    
    /**
     * Store payment token in database
     * 
     * @param int $orderId Order ID
     * @param string $token Payment token
     */
    private function storePaymentToken($orderId, $token) {
        try {
            // Check if payment_tokens table exists, create if not
            $this->ensurePaymentTokensTable();
            
            // Store token with expiration (7 days from now)
            $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));
            
            $this->db->query("
                INSERT INTO payment_tokens (order_id, token, expires_at, created_at)
                VALUES (:order_id, :token, :expires_at, NOW())
                ON DUPLICATE KEY UPDATE 
                    token = :token,
                    expires_at = :expires_at,
                    created_at = NOW()
            ", [
                'order_id' => $orderId,
                'token' => $token,
                'expires_at' => $expiresAt
            ]);
            
        } catch (Exception $e) {
            error_log("Failed to store payment token: " . $e->getMessage());
        }
    }
    
    /**
     * Ensure payment_tokens table exists
     */
    private function ensurePaymentTokensTable() {
        try {
            $this->db->query("
                CREATE TABLE IF NOT EXISTS payment_tokens (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    order_id INT NOT NULL UNIQUE,
                    token VARCHAR(255) NOT NULL,
                    expires_at DATETIME NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    used_at DATETIME NULL,
                    INDEX idx_order_id (order_id),
                    INDEX idx_token (token)
                )
            ");
        } catch (Exception $e) {
            error_log("Failed to create payment_tokens table: " . $e->getMessage());
        }
    }
    
    /**
     * Verify payment token
     * 
     * @param int $orderId Order ID
     * @param string $token Token to verify
     * @return bool True if valid, false otherwise
     */
    public function verifyPaymentToken($orderId, $token) {
        try {
            $result = $this->db->query("
                SELECT id, expires_at, used_at 
                FROM payment_tokens 
                WHERE order_id = :order_id 
                AND token = :token
            ", [
                'order_id' => $orderId,
                'token' => $token
            ])->fetch(PDO::FETCH_ASSOC);
            
            if (!$result) {
                return false;
            }
            
            // Check if token has expired
            if (strtotime($result['expires_at']) < time()) {
                return false;
            }
            
            // Check if token has already been used (optional - remove if you want reusable links)
            // if ($result['used_at']) {
            //     return false;
            // }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Token verification error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Mark payment token as used
     * 
     * @param int $orderId Order ID
     * @param string $token Token
     */
    public function markTokenAsUsed($orderId, $token) {
        try {
            $this->db->query("
                UPDATE payment_tokens 
                SET used_at = NOW() 
                WHERE order_id = :order_id 
                AND token = :token
            ", [
                'order_id' => $orderId,
                'token' => $token
            ]);
        } catch (Exception $e) {
            error_log("Failed to mark token as used: " . $e->getMessage());
        }
    }
    
    /**
     * Generate WhatsApp payment link message
     * 
     * @param int $orderId Order ID
     * @param string $customerName Customer name
     * @param float $amount Order amount
     * @param string $paymentLink Payment URL
     * @return string WhatsApp message
     */
    public function generateWhatsAppMessage($orderId, $customerName, $amount, $paymentLink) {
        $message = "🛍️ *AleppoGift Order #" . $orderId . "*\n\n";
        $message .= "Dear " . $customerName . ",\n\n";
        $message .= "Thank you for your order!\n\n";
        $message .= "📦 Order Total: *AED " . number_format($amount, 2) . "*\n\n";
        $message .= "💳 Complete your payment here:\n";
        $message .= $paymentLink . "\n\n";
        $message .= "Questions? Reply to this message!\n\n";
        $message .= "🌟 _AleppoGift - Premium Quality_";
        
        return $message;
    }
}

/**
 * Send payment link email to customer
 * 
 * @param string $toEmail Customer email
 * @param string $toName Customer name
 * @param int $orderId Order ID
 * @param string $paymentLink Payment URL
 * @param string|null $invoicePath Path to invoice PDF
 * @return bool True if sent successfully
 */
function sendPaymentLinkEmail($toEmail, $toName, $orderId, $paymentLink, $invoicePath = null) {
    error_log("📧 Sending payment link email for Order #$orderId to $toEmail");
    
    try {
        $subject = "Complete Your Payment - Order #$orderId - AleppoGift";
        
        $message = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Payment Link</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
                    <h1 style='color: white; margin: 0; font-size: 28px;'>🛍️ AleppoGift</h1>
                </div>
                
                <div style='background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px;'>
                    <h2 style='color: #667eea; margin-top: 0;'>Complete Your Payment</h2>
                    
                    <p>Dear " . htmlspecialchars($toName) . ",</p>
                    
                    <p>Thank you for your order! Your order <strong>#$orderId</strong> has been created and is ready for payment.</p>
                    
                    <div style='background: white; padding: 20px; margin: 20px 0; border-radius: 8px; border-left: 4px solid #667eea;'>
                        <h3 style='margin-top: 0; color: #667eea;'>📦 Order Details</h3>
                        <p>Order Number: <strong>#$orderId</strong></p>
                        <p>Your invoice is attached to this email.</p>
                    </div>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='" . htmlspecialchars($paymentLink) . "' 
                           style='background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); 
                                  color: white; 
                                  padding: 15px 40px; 
                                  text-decoration: none; 
                                  border-radius: 25px; 
                                  font-weight: bold; 
                                  font-size: 16px;
                                  display: inline-block;'>
                            💳 Complete Payment Now
                        </a>
                    </div>
                    
                    <div style='background: #fff3cd; padding: 15px; margin: 20px 0; border-radius: 5px; border-left: 4px solid #ffc107;'>
                        <p style='margin: 0;'><strong>⏰ Payment Link Validity:</strong> This link is valid for 7 days.</p>
                    </div>
                    
                    <h3 style='color: #667eea;'>What Happens Next?</h3>
                    <ul style='line-height: 1.8;'>
                        <li>Click the button above to complete your payment securely</li>
                        <li>Once payment is confirmed, we'll process your order immediately</li>
                        <li>You'll receive a shipping confirmation with tracking details</li>
                    </ul>
                    
                    <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;'>
                        <p style='margin: 0; color: #666; font-size: 14px;'>
                            <strong>Need Help?</strong><br>
                            Email: " . (defined('EMAIL_FROM') ? EMAIL_FROM : 'info@aleppogift.ae') . "<br>
                            Phone: +971 56 112 5320<br>
                            Website: <a href='" . (defined('SITE_URL') ? SITE_URL : 'https://aleppogift.ae') . "' style='color: #667eea;'>aleppogift.ae</a>
                        </p>
                    </div>
                </div>
                
                <div style='text-align: center; margin-top: 20px; color: #999; font-size: 12px;'>
                    <p>© " . date('Y') . " AleppoGift. Premium Quality Products.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: " . (defined('EMAIL_FROM_NAME') ? EMAIL_FROM_NAME : 'AleppoGift') . 
                    " <" . (defined('EMAIL_FROM') ? EMAIL_FROM : 'noreply@aleppogift.ae') . ">" . "\r\n";
        
        // If invoice path is provided, we should use a mail library that supports attachments
        // For now, we'll send without attachment and rely on the payment page to show invoice
        
        $result = mail($toEmail, $subject, $message, $headers);
        
        if ($result) {
            error_log("✅ Payment link email sent successfully to $toEmail");
        } else {
            error_log("❌ Failed to send payment link email to $toEmail");
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("❌ Error sending payment link email: " . $e->getMessage());
        return false;
    }
}
?>
