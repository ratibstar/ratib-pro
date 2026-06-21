<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Infrastructure\Realtime;

/**
 * Minimal RFC6455 helpers (no external dependencies).
 */
final class WebSocketProtocol
{
    public static function handshakeResponse(string $requestHeaders): ?string
    {
        if (!preg_match('#Sec-WebSocket-Key:\s*(.+)\r\n#i', $requestHeaders, $m)) {
            return null;
        }
        $key = trim($m[1]);
        $accept = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
        return "HTTP/1.1 101 Switching Protocols\r\n"
            . "Upgrade: websocket\r\n"
            . "Connection: Upgrade\r\n"
            . "Sec-WebSocket-Accept: {$accept}\r\n\r\n";
    }

    public static function encode(string $payload): string
    {
        $len = strlen($payload);
        $frame = chr(0x81);
        $mask = random_bytes(4);

        if ($len <= 125) {
            $frame .= chr(0x80 | $len);
        } elseif ($len <= 65535) {
            $frame .= chr(0x80 | 126) . pack('n', $len);
        } else {
            $frame .= chr(0x80 | 127) . pack('J', $len);
        }

        $frame .= $mask;
        for ($i = 0; $i < $len; $i++) {
            $frame .= $payload[$i] ^ $mask[$i % 4];
        }
        return $frame;
    }

    public static function decode(string $data): ?string
    {
        $len = strlen($data);
        if ($len < 2) {
            return null;
        }
        $opcode = ord($data[0]) & 0x0f;
        if ($opcode === 0x8) {
            return null;
        }
        $masked = (ord($data[1]) & 0x80) !== 0;
        $payloadLen = ord($data[1]) & 0x7f;
        $offset = 2;
        if ($payloadLen === 126) {
            $payloadLen = unpack('n', substr($data, 2, 2))[1];
            $offset = 4;
        } elseif ($payloadLen === 127) {
            $payloadLen = unpack('J', substr($data, 2, 8))[1];
            $offset = 10;
        }
        if ($masked) {
            $mask = substr($data, $offset, 4);
            $offset += 4;
            $payload = substr($data, $offset, $payloadLen);
            $out = '';
            for ($i = 0; $i < $payloadLen; $i++) {
                $out .= $payload[$i] ^ $mask[$i % 4];
            }
            return $out;
        }
        return substr($data, $offset, $payloadLen);
    }
}
