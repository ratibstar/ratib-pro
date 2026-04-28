# Worker Tracking Mobile API Contract

Endpoint: `POST /api/worker-tracking/update-location.php`
Additional endpoints:
- `POST /api/worker-tracking/sos.php`
- `GET /api/worker-tracking/history.php`

Auth/context:
- Tenant context must resolve for request host/domain.
- Worker must exist in current tenant (`workers.id`, not deleted).

## Online single update

```json
{
  "worker_id": 123,
  "lat": -6.2088,
  "lng": 106.8456,
  "accuracy": 10.2,
  "speed": 1.8,
  "battery": 84,
  "source": "gps",
  "timestamp": "2026-04-25T11:00:00Z"
}
```

## Offline batch sync

```json
{
  "worker_id": 123,
  "is_offline_batch": true,
  "source": "cached",
  "locations": [
    { "lat": -6.2088, "lng": 106.8456, "timestamp": "2026-04-25T10:40:00Z", "accuracy": 20, "battery": 90 },
    { "lat": -6.2091, "lng": 106.8462, "timestamp": "2026-04-25T10:40:20Z", "accuracy": 18, "battery": 89 }
  ]
}
```

## Recommended mobile behavior

- Dynamic interval:
  - moving: every `10s`
  - idle: every `60s`
  - offline: queue locally
- Battery save:
  - if battery `< 20%`, increase interval by `2x`
- Offline: append to local queue.
- Retry with exponential backoff.
- On reconnect: send batched queue oldest-first.
- Drop malformed points (invalid lat/lng).

Security headers/payload:
- `X-Tracking-Token: <device-token>` (when `TRACKING_REQUIRE_TOKEN=1`)
- Body includes `device_id` string
- Optional single-device enforcement with `TRACKING_SINGLE_DEVICE_ENFORCED=1`

## EventBus integration

Server emits into `system_events`:
- `WORKER_LOCATION_UPDATE`
- `WORKER_OFFLINE`
- `WORKER_IDLE_ALERT`
- `WORKER_ANOMALY`
- `WORKER_SOS` (reserved for future SOS endpoint)

Metadata keys include:
- `worker_id`
- `tenant_id`
- `lat` / `lng`
- `status`
- `battery`
- `duration_ms`
- `request_id`
