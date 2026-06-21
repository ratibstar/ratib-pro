#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * RCC Real-Time Hub — TCP ingest (9701) + WebSocket server (9702).
 *
 * Usage: php bin/rcc-realtime-hub.php
 *
 * EventBus pushes JSON lines to TCP :9701
 * Dashboard clients connect via WebSocket :9702
 */
if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}

define('RCC_ROOT', dirname(__DIR__));
require RCC_ROOT . '/bootstrap.php';

use Ratib\ContactCenter\App\Infrastructure\Realtime\WebSocketGateway;
use Ratib\ContactCenter\App\Infrastructure\Realtime\WebSocketProtocol;

$tcpHost = getenv('RCC_REALTIME_HUB_HOST') ?: '127.0.0.1';
$tcpPort = (int) (getenv('RCC_REALTIME_HUB_PORT') ?: 9701);
$wsHost = getenv('RCC_WEBSOCKET_HOST') ?: '0.0.0.0';
$wsPort = (int) (getenv('RCC_WEBSOCKET_PORT') ?: 9702);

/** @var array<int, array{socket: resource, rooms: list<string>, buffer: string}> */
$wsClients = [];

$tcpServer = stream_socket_server('tcp://' . $tcpHost . ':' . $tcpPort, $errno, $errstr);
if ($tcpServer === false) {
    fwrite(STDERR, "TCP bind failed: $errstr\n");
    exit(1);
}
stream_set_blocking($tcpServer, false);

$wsServer = stream_socket_server('tcp://' . $wsHost . ':' . $wsPort, $errno, $errstr);
if ($wsServer === false) {
    fwrite(STDERR, "WebSocket bind failed: $errstr\n");
    exit(1);
}
stream_set_blocking($wsServer, false);

fwrite(STDOUT, "RCC Realtime Hub — TCP {$tcpHost}:{$tcpPort} | WS {$wsHost}:{$wsPort}\n");

/** @param list<string> $rooms */
function rcc_ws_broadcast(array $wsClients, array $rooms, string $json): void
{
    foreach ($wsClients as $id => $client) {
        $sub = $client['rooms'] ?? [];
        if ($sub !== [] && array_intersect($rooms, $sub) === []) {
            continue;
        }
        @fwrite($client['socket'], WebSocketProtocol::encode($json));
    }
}

while (true) {
    $read = [$tcpServer, $wsServer];
    foreach ($wsClients as $c) {
        $read[] = $c['socket'];
    }
    $write = null;
    $except = null;
    if (@stream_select($read, $write, $except, 1) === false) {
        continue;
    }

    if (in_array($tcpServer, $read, true)) {
        $conn = @stream_socket_accept($tcpServer, 0);
        if ($conn !== false) {
            $line = trim((string) stream_get_contents($conn));
            fclose($conn);
            if ($line !== '') {
                $msg = json_decode($line, true);
                if (is_array($msg) && isset($msg['rooms'], $msg['event'])) {
                    $rooms = is_array($msg['rooms']) ? $msg['rooms'] : [];
                    $json = json_encode($msg['event'], JSON_UNESCAPED_UNICODE);
                    if ($json !== false) {
                        rcc_ws_broadcast($wsClients, $rooms, $json);
                    }
                }
            }
        }
    }

    if (in_array($wsServer, $read, true)) {
        $conn = @stream_socket_accept($wsServer, 0);
        if ($conn !== false) {
            stream_set_blocking($conn, false);
            $wsClients[(int) $conn] = ['socket' => $conn, 'rooms' => [], 'buffer' => '', 'handshake' => false];
        }
    }

    foreach ($wsClients as $id => $client) {
        if (!in_array($client['socket'], $read, true)) {
            continue;
        }
        $chunk = fread($client['socket'], 8192);
        if ($chunk === false || $chunk === '') {
            fclose($client['socket']);
            WebSocketGateway::unregisterLocalClient($client['socket']);
            unset($wsClients[$id]);
            continue;
        }

        if (empty($client['handshake'])) {
            $client['buffer'] .= $chunk;
            if (strpos($client['buffer'], "\r\n\r\n") !== false) {
                $response = WebSocketProtocol::handshakeResponse($client['buffer']);
                if ($response === null) {
                    fclose($client['socket']);
                    unset($wsClients[$id]);
                    continue;
                }
                fwrite($client['socket'], $response);
                $client['handshake'] = true;
                $client['buffer'] = '';
                $wsClients[$id] = $client;
            }
            continue;
        }

        $payload = WebSocketProtocol::decode($chunk);
        if ($payload === null) {
            continue;
        }
        $cmd = json_decode($payload, true);
        if (!is_array($cmd)) {
            continue;
        }
        if (($cmd['action'] ?? '') === 'subscribe' && is_array($cmd['rooms'] ?? null)) {
            $rooms = array_map('strval', $cmd['rooms']);
            $client['rooms'] = $rooms;
            WebSocketGateway::registerLocalClient($client['socket'], $rooms);
            $wsClients[$id] = $client;
            fwrite($client['socket'], WebSocketProtocol::encode(json_encode([
                'type' => 'SUBSCRIBED',
                'rooms' => $rooms,
            ], JSON_UNESCAPED_UNICODE)));
        }
    }
}
