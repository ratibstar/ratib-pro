<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Infrastructure\Voice;

/**
 * Asterisk Manager Interface (AMI) TCP client — production actions + event stream.
 */
final class AmiClient
{
    /** @var resource|null */
    private $socket = null;

    private bool $loggedIn = false;

    /** @var array<string, mixed> */
    private array $config;

    /** @param array<string, mixed>|null $config */
    public function __construct(?array $config = null)
    {
        $this->config = $config ?? $this->loadConfig();
    }

    public function connect(): void
    {
        if ($this->socket !== null && $this->loggedIn) {
            return;
        }

        $host = (string) $this->config['host'];
        $port = (int) $this->config['port'];
        $timeout = (float) $this->config['connect_timeout'];

        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client(
            'tcp://' . $host . ':' . $port,
            $errno,
            $errstr,
            $timeout
        );

        if ($socket === false) {
            throw new \RuntimeException('AMI connect failed: ' . $errstr . ' (' . $errno . ')');
        }

        stream_set_blocking($socket, true);
        stream_set_timeout($socket, (int) floor($timeout), (int) (($timeout - floor($timeout)) * 1_000_000));
        $this->socket = $socket;

        $banner = $this->readPacket();
        if (!isset($banner['Response']) || $banner['Response'] !== 'Success') {
            $this->disconnect();
            throw new \RuntimeException('AMI banner invalid.');
        }

        $login = $this->sendAction([
            'Action' => 'Login',
            'Username' => (string) $this->config['username'],
            'Secret' => (string) $this->config['secret'],
            'Events' => 'on',
        ]);

        if (($login['Response'] ?? '') !== 'Success') {
            $msg = (string) ($login['Message'] ?? 'Login failed');
            $this->disconnect();
            throw new \RuntimeException('AMI login failed: ' . $msg);
        }

        $this->loggedIn = true;
    }

    public function disconnect(): void
    {
        if ($this->socket !== null && $this->loggedIn) {
            try {
                $this->sendAction(['Action' => 'Logoff']);
            } catch (\Throwable $e) {
                // socket may already be closed
            }
        }
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }
        $this->socket = null;
        $this->loggedIn = false;
    }

    public function isConnected(): bool
    {
        return $this->socket !== null && $this->loggedIn;
    }

    /**
     * @param array<string, string> $params
     * @return array<string, string>
     */
    public function sendAction(array $params): array
    {
        $this->ensureConnected();
        $payload = '';
        foreach ($params as $key => $value) {
            $payload .= $key . ': ' . $value . "\r\n";
        }
        $payload .= "\r\n";
        $written = fwrite($this->socket, $payload);
        if ($written === false) {
            throw new \RuntimeException('AMI write failed.');
        }
        return $this->readPacket();
    }

    /**
     * @param array<string, string> $params
     */
    public function sendActionAsync(array $params): void
    {
        $this->ensureConnected();
        $params['Async'] = 'true';
        $payload = '';
        foreach ($params as $key => $value) {
            $payload .= $key . ': ' . $value . "\r\n";
        }
        $payload .= "\r\n";
        fwrite($this->socket, $payload);
    }

    /**
     * Read next AMI packet (action response or event).
     *
     * @return array<string, string>
     */
    public function readPacket(): array
    {
        if (!is_resource($this->socket)) {
            return [];
        }

        $lines = [];
        while (!feof($this->socket)) {
            $line = fgets($this->socket);
            if ($line === false) {
                break;
            }
            $line = rtrim($line, "\r\n");
            if ($line === '') {
                break;
            }
            $lines[] = $line;
        }

        $packet = [];
        foreach ($lines as $line) {
            $pos = strpos($line, ':');
            if ($pos === false) {
                continue;
            }
            $key = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));
            $packet[$key] = $value;
        }

        return $packet;
    }

    public function pjsipEndpoint(int $tenantId, string $extension): string
    {
        $prefix = (string) ($this->config['pjsip_endpoint_prefix'] ?? 'agent');
        return 'PJSIP/' . $prefix . $tenantId . '_' . $extension;
    }

    public function tenantContext(int $tenantId): string
    {
        return (string) ($this->config['context_prefix'] ?? 'rcc') . '-tenant-' . $tenantId;
    }

    private function ensureConnected(): void
    {
        if (!$this->isConnected()) {
            $this->connect();
        }
    }

    /** @return array<string, mixed> */
    private function loadConfig(): array
    {
        $path = dirname(__DIR__, 3) . '/config/asterisk.php';
        return is_file($path) ? (array) require $path : [];
    }
}
