<?php
function sendAdminWhatsApp($order_id, $name, $total, $method = 'COD') {
    $phone = '971561125320';
    $apikey = '5574813';

    $methodLabel = ($method === 'COD') ? '💵 COD' : '💳 Paid';

    // Build plain message first (raw, with line breaks)
    $message = "📦 New Order - AleppoGift\nOrder ID: #$order_id\n$methodLabel\nCustomer: $name\nTotal: AED $total";

    // URL-encode the whole message
    $messageEncoded = urlencode($message);

    $url = "https://api.callmebot.com/whatsapp.php?phone=$phone&text=$messageEncoded&apikey=$apikey";

    $ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$result = curl_exec($ch);

	if (curl_errno($ch)) {
		error_log("❗ cURL error for WhatsApp API (Order #$order_id): " . curl_error($ch));
		$result = false;
	}
	curl_close($ch);


    if ($result === false) {
        error_log("❗ WhatsApp API call failed for Order #$order_id");
    } else {
        error_log("✅ WhatsApp sent for Order #$order_id");
    }
}
