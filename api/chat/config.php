<?php
/**
 * EN: Handles API endpoint/business logic in `api/chat/config.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/chat/config.php`.
 */
/**
 * WhatsApp Chat Configuration
 * Using WhatsApp Business Cloud API (Meta - Official, Free)
 */

return [
    // Enable/Disable WhatsApp forwarding
    'enabled' => true,
    
    // Your WhatsApp number (with country code)
    'phone_number' => '+966599863868',
    
    // WhatsApp Business Cloud API Configuration (Meta - Official, FREE)
    // Get these from: https://developers.facebook.com/apps/
    'whatsapp_business_access_token' => '', // Permanent Access Token from Meta
    'whatsapp_business_phone_id' => '', // Phone Number ID from Meta Business
    'whatsapp_business_api_version' => 'v18.0', // API Version
    
    // Alternative: Twilio Configuration (if not using Meta API)
    'twilio' => [
        'account_sid' => '', // Get from Twilio Dashboard
        'auth_token' => '',  // Get from Twilio Dashboard
        'whatsapp_from' => 'whatsapp:+14155238886', // Twilio WhatsApp number
    ],
    
    // Webhook URL for receiving replies
    'webhook_url' => 'https://bangladesh.out.ratib.sa/api/chat/webhook.php',
    
    // Message format template
    'message_template' => "📱 *New Chat Message from Website*\n\n*From:* {user_name}\n*Email:* {user_email}\n*Phone:* {user_phone}\n*Time:* {timestamp}\n\n*Message:*\n{message}",
];
