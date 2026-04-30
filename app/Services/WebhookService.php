<?php
declare(strict_types=1);

namespace App\Services;

use App\Events\ViolationDetected;
use App\Events\WorkerCreated;
use App\Events\WorkflowExecutionCompleted;
use App\Repositories\WebhookRepository;

final class WebhookService
{
    private const MAX_ATTEMPTS = 6;

    public function __construct(private readonly WebhookRepository $webhooks)
    {
    }

    public function enqueueWorkerCreated(WorkerCreated $event): void
    {
        $this->enqueueForEvent('worker_created', [
            'worker_id' => $event->workerId,
            'worker_name' => $event->workerName,
        ]);
    }

    public function enqueueViolationDetected(ViolationDetected $event): void
    {
        $this->enqueueForEvent('violation_detected', [
            'worker_id' => $event->workerId,
            'violation_type' => $event->violationType,
            'severity' => $event->severity,
        ]);
    }

    public function enqueueWorkflowCompleted(WorkflowExecutionCompleted $event): void
    {
        $this->enqueueForEvent('workflow_completed', [
            'workflow_id' => $event->workflowId,
            'workflow_name' => $event->workflowName,
            'mode' => $event->mode,
            'actor' => $event->actor,
            'sequence_number' => $event->sequenceNumber,
            'timestamp' => $event->timestamp,
            'event_chain' => $event->eventChain,
        ]);
    }

    public function dispatchPending(int $limit = 25): array
    {
        $rows = $this->webhooks->takePendingDeliveries($limit);
        $sent = 0;
        $retried = 0;
        $failed = 0;

        foreach ($rows as $row) {
            $deliveryId = (int) ($row['id'] ?? 0);
            $attempts = (int) ($row['attempts'] ?? 0);
            $target = (string) ($row['target_url'] ?? '');
            if ($deliveryId <= 0 || $target === '') {
                continue;
            }
            $payload = json_decode((string) ($row['payload_json'] ?? '{}'), true);
            if (!is_array($payload)) {
                $payload = [];
            }
            $eventName = (string) ($row['event_name'] ?? '');
            $secret = (string) ($row['secret'] ?? '');
            $timeout = (int) ($row['timeout_seconds'] ?? 10);
            $timeout = max(3, min(30, $timeout));
            $body = json_encode([
                'event' => $eventName,
                'delivery_id' => $deliveryId,
                'timestamp' => gmdate('c'),
                'payload' => $payload,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($body === false) {
                $body = '{"event":"' . addslashes($eventName) . '","payload":{}}';
            }

            $ch = curl_init($target);
            $headers = ['Content-Type: application/json'];
            if ($secret !== '') {
                $headers[] = 'X-Webhook-Signature: sha256=' . hash_hmac('sha256', $body, $secret);
            }
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => 5,
            ]);
            $resp = curl_exec($ch);
            $curlErr = curl_error($ch);
            $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $ok = ($curlErr === '' && $http >= 200 && $http < 300);
            $respText = $curlErr !== '' ? ('curl_error: ' . $curlErr) : (string) ($resp ?: '');
            if ($ok) {
                $this->webhooks->markDelivered($deliveryId, $http, $respText);
                $sent++;
                continue;
            }
            if (($attempts + 1) >= self::MAX_ATTEMPTS) {
                $this->webhooks->markFailed($deliveryId, $http > 0 ? $http : 0, $respText);
                $failed++;
            } else {
                $this->webhooks->markRetry($deliveryId, $http > 0 ? $http : 0, $respText, $attempts + 1);
                $retried++;
            }
        }

        return [
            'processed' => count($rows),
            'sent' => $sent,
            'retried' => $retried,
            'failed' => $failed,
        ];
    }

    /** @param array<string, mixed> $payload */
    private function enqueueForEvent(string $eventName, array $payload): void
    {
        $subs = $this->webhooks->listActiveByEvent($eventName);
        foreach ($subs as $sub) {
            $wid = (int) ($sub['id'] ?? 0);
            if ($wid <= 0) {
                continue;
            }
            $this->webhooks->enqueueDelivery($wid, $eventName, $payload);
        }
    }
}
