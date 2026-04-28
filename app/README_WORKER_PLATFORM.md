# Worker Tracking & Compliance Platform

## Architecture
- `app/Domain`: contracts and entities
- `app/Repositories`: infrastructure data access (PDO)
- `app/Services`: business rules
- `app/Events` and `app/Listeners`: event-driven communication
- `app/Workflows`: step-based workflow orchestration
- `app/Controllers/Http`: thin controllers
- `app/Core`: bootstrap, container, event dispatcher, workflow engine

## Entry Point
- HTTP entry: `public/worker-platform.php`
- Routes: `routes/worker_platform.php`
- Config: `config/worker_tracking.php`

## Example Requests
1) Create worker
```bash
curl -X POST http://localhost/worker-platform.php/workers \
  -H "Content-Type: application/json" \
  -d "{\"name\":\"Ali\",\"passport_number\":\"P-10293\",\"employer_id\":1}"
```

2) Track movement
```bash
curl -X POST http://localhost/worker-platform.php/tracking/move \
  -H "Content-Type: application/json" \
  -d "{\"worker_id\":1,\"latitude\":24.71,\"longitude\":46.67,\"speed_kmh\":130}"
```

3) Run onboarding workflow
```bash
curl -X POST http://localhost/workflows/worker-onboarding \
  -H "Content-Type: application/json" \
  -d "{\"worker\":{\"name\":\"Sara\",\"passport_number\":\"P-20200\",\"employer_id\":1},\"tracking\":{\"latitude\":24.71,\"longitude\":46.67},\"notify_to\":\"ops@gov.local\"}"
```
