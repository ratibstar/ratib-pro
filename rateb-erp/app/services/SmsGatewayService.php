<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;

final class SmsGatewayService
{
    public function send(string $phone, string $body): bool
    {
        $provider = AutomationSettings::getString('sms_provider', 'log');
        if ($provider === 'log') {
            Logger::info('SMS sent (log provider)', ['phone' => $phone, 'body' => substr($body, 0, 120)]);
            return true;
        }
        if ($provider === 'http') {
            return $this->sendHttp($phone, $body);
        }
        Logger::warning('Unknown SMS provider', ['provider' => $provider]);
        return false;
    }

    private function sendHttp(string $phone, string $body): bool
    {
        $url = AutomationSettings::getString('sms_api_url', '');
        $key = AutomationSettings::getString('sms_api_key', '');
        if ($url === '') {
            return false;
        }
        $payload = json_encode([
            'to' => $phone,
            'message' => $body,
            'sender' => AutomationSettings::getString('sms_sender_id', 'RTAB'),
            'api_key' => $key,
        ], JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            return false;
        }
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $payload,
                'timeout' => 15,
            ],
        ]);
        $result = @file_get_contents($url, false, $ctx);
        return $result !== false;
    }
}
