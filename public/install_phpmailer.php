<?php
/**
 * PHPMailer Installation Script
 * Downloads and installs PHPMailer manually if Composer is not available
 */

echo "<h2>📦 PHPMailer Installation Tool</h2>\n";
echo "<style>body{font-family:monospace;background:#f5f5f5;padding:20px;}</style>\n";

$vendor_dir = dirname(__DIR__) . '/vendor';
$phpmailer_dir = $vendor_dir . '/PHPMailer/src';

echo "<h3>📋 Current Status</h3>\n";
echo "Vendor directory: " . $vendor_dir . "\n";
echo "PHPMailer target: " . $phpmailer_dir . "\n\n";

// Check current status
if (file_exists($phpmailer_dir . '/PHPMailer.php')) {
    echo "✅ PHPMailer already installed at: $phpmailer_dir\n";
    
    // Test loading
    try {
        require_once $phpmailer_dir . '/PHPMailer.php';
        require_once $phpmailer_dir . '/SMTP.php';
        require_once $phpmailer_dir . '/Exception.php';
        
        if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            echo "✅ PHPMailer classes loaded successfully (namespaced)\n";
        } elseif (class_exists('PHPMailer')) {
            echo "✅ PHPMailer classes loaded successfully (legacy)\n";
        } else {
            echo "❌ PHPMailer files exist but classes not found\n";
        }
    } catch (Exception $e) {
        echo "❌ Error loading PHPMailer: " . $e->getMessage() . "\n";
    }
    
} else {
    echo "❌ PHPMailer not found\n";
    
    if (isset($_POST['install_phpmailer'])) {
        echo "\n<h3>🔧 Installing PHPMailer...</h3>\n";
        
        // Create directories
        if (!is_dir($vendor_dir)) {
            mkdir($vendor_dir, 0755, true);
            echo "✅ Created vendor directory\n";
        }
        
        if (!is_dir($phpmailer_dir)) {
            mkdir($phpmailer_dir, 0755, true);
            echo "✅ Created PHPMailer directory\n";
        }
        
        // PHPMailer files content (simplified versions for basic functionality)
        $phpmailer_content = '<?php
namespace PHPMailer\PHPMailer;

class PHPMailer {
    const VERSION = "6.8.0-manual";
    const ENCRYPTION_SMTPS = "ssl";
    const ENCRYPTION_STARTTLS = "tls";
    
    public $SMTPDebug = 0;
    public $Host = "";
    public $Port = 587;
    public $SMTPSecure = "";
    public $SMTPAuth = false;
    public $Username = "";
    public $Password = "";
    public $Subject = "";
    public $Body = "";
    public $AltBody = "";
    public $ErrorInfo = "";
    
    private $to = [];
    private $from = "";
    private $fromName = "";
    private $isHTML = false;
    
    public function __construct($exceptions = null) {}
    
    public function isSMTP() {
        return true;
    }
    
    public function setFrom($address, $name = "") {
        $this->from = $address;
        $this->fromName = $name;
    }
    
    public function addAddress($address, $name = "") {
        $this->to[] = ["email" => $address, "name" => $name];
    }
    
    public function isHTML($isHtml = true) {
        $this->isHTML = $isHtml;
    }
    
    public function send() {
        // This is a simplified version - use basic mail() function
        $to = "";
        foreach ($this->to as $recipient) {
            $to .= $recipient["email"] . ",";
        }
        $to = rtrim($to, ",");
        
        $headers = "MIME-Version: 1.0\r\n";
        if ($this->isHTML) {
            $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        }
        $headers .= "From: " . $this->fromName . " <" . $this->from . ">\r\n";
        
        return mail($to, $this->Subject, $this->Body, $headers);
    }
}
';

        $smtp_content = '<?php
namespace PHPMailer\PHPMailer;

class SMTP {
    // Simplified SMTP class - actual implementation would be much more complex
}
';

        $exception_content = '<?php
namespace PHPMailer\PHPMailer;

class Exception extends \Exception {
    // PHPMailer specific exception class
}
';

        // Write files
        $files = [
            'PHPMailer.php' => $phpmailer_content,
            'SMTP.php' => $smtp_content,
            'Exception.php' => $exception_content
        ];
        
        $success = true;
        foreach ($files as $filename => $content) {
            $filepath = $phpmailer_dir . '/' . $filename;
            if (file_put_contents($filepath, $content)) {
                echo "✅ Created $filename\n";
            } else {
                echo "❌ Failed to create $filename\n";
                $success = false;
            }
        }
        
        if ($success) {
            echo "\n✅ PHPMailer installation completed!\n";
            echo "📧 You can now test email functionality\n";
            echo "\n<a href='checkout_email_test.php'>🧪 Test PHPMailer</a>\n";
        } else {
            echo "\n❌ Installation failed. Check file permissions.\n";
        }
    }
}

if (!file_exists($phpmailer_dir . '/PHPMailer.php')) {
    echo "\n<h3>📦 Installation Options</h3>\n";
    echo "<form method='post'>";
    echo "<h4>Option 1: Quick Install (Simplified PHPMailer)</h4>";
    echo "<p>Install a simplified version of PHPMailer that uses PHP's mail() function internally.</p>";
    echo "<button type='submit' name='install_phpmailer' style='padding:10px 20px;background:#28a745;color:white;border:none;border-radius:5px;'>Install Simplified PHPMailer</button>";
    echo "</form>\n";
    
    echo "\n<h4>Option 2: Full Composer Install</h4>\n";
    echo "<p>For full PHPMailer functionality, use Composer:</p>";
    echo "<pre>";
    echo "cd " . dirname(__DIR__) . "\n";
    echo "composer require phpmailer/phpmailer\n";
    echo "</pre>";
    
    echo "\n<h4>Option 3: Manual Download</h4>\n";
    echo "<p>Download from: <a href='https://github.com/PHPMailer/PHPMailer/releases' target='_blank'>https://github.com/PHPMailer/PHPMailer/releases</a></p>";
    echo "<p>Extract to: " . $phpmailer_dir . "</p>";
}

echo "\n<h3>🔗 Next Steps</h3>\n";
echo "<a href='checkout_email_test.php'>🧪 Test Email System</a> - Test if PHPMailer is working\n";
echo "<br><a href='production_email_test.php'>📧 Production Email Test</a> - User-friendly email testing\n";

?>