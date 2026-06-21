<?php
declare(strict_types=1);

/**
 * AI Copilot — rule/heuristic config (advisory only; no routing override).
 * Replace analyzers with LLM providers via env RCC_AI_PROVIDER when ready.
 */
return [
    'enabled' => (getenv('RCC_AI_ASSISTANT_ENABLED') ?: '1') !== '0',

    'sentiment' => [
        'angry' => ['angry', 'furious', 'unacceptable', 'worst', 'complaint', 'disgusted', 'hate', 'غاضب', 'سيء'],
        'negative' => ['bad', 'problem', 'issue', 'disappointed', 'frustrated', 'not happy', 'مشكلة', 'غير راض'],
        'positive' => ['thanks', 'thank you', 'great', 'excellent', 'happy', 'شكر', 'ممتاز'],
    ],

    'intent_patterns' => [
        'complaint' => ['complaint', 'complain', 'unhappy', 'disappointed', 'شكوى'],
        'refund_request' => ['refund', 'money back', 'chargeback', 'استرداد', 'إرجاع'],
        'sales_opportunity' => ['buy', 'purchase', 'pricing', 'quote', 'demo', 'شراء', 'سعر'],
        'technical_issue' => ['error', 'bug', 'not working', 'broken', 'login', 'خطأ', 'لا يعمل'],
        'account_registration' => ['register', 'regster', 'sign up', 'signup', 'create account', 'join', 'تسجيل', 'انضم'],
        'cancellation_risk' => ['cancel', 'terminate', 'leave', 'switch provider', 'إلغاء'],
    ],

    'risk_weights' => [
        'sentiment_angry' => 0.35,
        'sentiment_negative' => 0.20,
        'intent_complaint' => 0.25,
        'intent_cancellation_risk' => 0.30,
        'sla_yellow' => 0.15,
        'sla_red' => 0.35,
    ],

    'actions' => [
        'ESCALATE_TO_SUPERVISOR' => ['min_risk' => 0.75, 'sentiments' => ['angry'], 'intents' => ['complaint', 'cancellation_risk']],
        'CREATE_TICKET' => ['min_risk' => 0.55, 'sentiments' => ['angry', 'negative'], 'intents' => ['complaint', 'technical_issue']],
        'OFFER_DISCOUNT' => ['intents' => ['cancellation_risk'], 'erp_flag' => 'high_value_company'],
        'TRANSFER_SENIOR' => ['intents' => ['technical_issue'], 'min_risk' => 0.50],
        'CLOSE_CONVERSATION' => ['sentiments' => ['positive'], 'intents' => ['sales_opportunity']],
    ],

    'auto_ticket' => [
        'enabled' => true,
        'require_sentiment' => ['angry'],
        'require_intent' => ['complaint'],
        'require_sla' => ['yellow', 'red'],
    ],

    'reply_templates' => [
        'complaint' => [
            'whatsapp' => 'We understand your concern and apologize for the inconvenience. Our team is reviewing your case now.',
            'email' => "Dear Customer,\n\nThank you for reaching out. We sincerely apologize for the inconvenience and are prioritizing your case.\n\nBest regards,\nSupport Team",
            'chat' => 'Sorry about this — I am looking into it right now and will update you shortly.',
            'voice' => 'Acknowledge frustration, apologize sincerely, confirm you are escalating internally.',
        ],
        'refund_request' => [
            'whatsapp' => 'We received your refund request. Please share your order/reference number so we can process it quickly.',
            'email' => "Dear Customer,\n\nWe have noted your refund request. Please reply with your reference number.\n\nRegards,\nBilling Team",
            'chat' => 'I can help with your refund — could you share your order or invoice number?',
            'voice' => 'Confirm refund request, ask for reference number, set expectation on review timeline.',
        ],
        'technical_issue' => [
            'whatsapp' => 'Thanks for reporting this. Can you describe what happens and share any error message you see?',
            'email' => "Dear Customer,\n\nWe are sorry you are experiencing a technical issue. Please describe the steps and any error messages.\n\nIT Support",
            'chat' => 'Let me troubleshoot this — what exactly happens when you try?',
            'voice' => 'Gather steps to reproduce, error text, and device/browser details.',
        ],
        'account_registration' => [
            'whatsapp' => 'You can register at rateb.sa — open Sign Up, enter your company details, and verify your email. Need help with a specific step?',
            'email' => "Dear Customer,\n\nTo register with Rateb, visit rateb.sa and choose Sign Up. Complete your profile and verify your email.\n\nOnboarding Team",
            'chat' => 'Registration is at rateb.sa → Sign Up. Enter your business details and verify your email. Which step do you need help with?',
            'voice' => 'Direct customer to rateb.sa registration; offer to stay on the line while they complete Sign Up.',
        ],
        'default' => [
            'whatsapp' => 'Thank you for contacting us. How can I assist you today?',
            'email' => "Dear Customer,\n\nThank you for your message. We are here to help.\n\nSupport Team",
            'chat' => 'Thanks for reaching out — how can I help you today?',
            'voice' => 'Greet warmly, confirm customer identity, ask how you can help.',
        ],
    ],
];
