# RATEB POS V2 — Hardware SDK

**Version:** 1.0.0  
**Pattern:** Plugin-based driver registry (extends existing `PosHardwareManager`)

---

## 1. Design Goals

- **Pluggable drivers** without modifying POS core
- **Uniform contracts** for all device types
- **Graceful degradation** via Null drivers (existing pattern)
- **Health visibility** for Screen 27/29 diagnostics
- **Test doubles** for CI and local dev

---

## 2. Core Interfaces

### 2.1 `PosHardwareDriverInterface`

```php
interface PosHardwareDriverInterface
{
    public function getDriverId(): string;
    public function getDeviceType(): DeviceType;
    public function boot(array $config): void;
    public function shutdown(): void;
    public function healthCheck(): HealthCheckResult;
    public function getCapabilities(): DriverCapabilities;
}
```

### 2.2 Device-specific contracts (extend base)

| Interface | Methods |
|-----------|---------|
| `PosPrinterInterface` (existing) | `print(PrintPayload $payload): PrintResult`, `getStatus(): PrinterStatus` |
| `PosKitchenPrinterInterface` | `printTicket(KitchenTicketPayload $payload): PrintResult` |
| `PosCashDrawerHardwareInterface` (existing) | `open(DrawerOpenReason $reason): void`, `isOpen(): bool` |
| `PosScannerInterface` (existing) | `subscribe(ScannerCallback $cb): void`, `unsubscribe(): void` |
| `PosScaleInterface` (existing) | `readWeight(): WeightReading`, `zero(): void`, `tare(): void` |
| `PosCustomerDisplayInterface` | `showLine1(string $text): void`, `showTotal(Money $total): void`, `clear(): void` |
| `PosPaymentTerminalInterface` | `initiatePayment(TerminalPaymentRequest $req): TerminalPaymentResult`, `cancel(): void`, `getTerminalStatus(): TerminalStatus` |
| `PosNfcInterface` (existing) | `readBadge(): NfcReadResult`, `waitForTap(int $timeoutMs): NfcReadResult` |

---

## 3. Driver Contract Requirements

Every driver MUST:

1. Implement `PosHardwareDriverInterface`
2. Declare `driver_id` (unique, namespaced: `vendor.product.version`)
3. Accept config schema validated against driver JSON schema
4. Return structured errors (`HardwareErrorCode` enum)
5. Be stateless between calls (state in manager)
6. Support `healthCheck()` < 5s timeout
7. Log via `PosHardwareLogger` (no raw echo)
8. Never block HTTP request > 500ms (async for long ops)

---

## 4. Driver Lifecycle

```
Registration → Discovery → Boot → Ready → Execute → HealthCheck → Shutdown
                  ↑                                    │
                  └────────── Reboot on config change ─┘
```

| Phase | Owner | Description |
|-------|-------|-------------|
| Registration | DriverServiceProvider | Declares driver class + metadata |
| Discovery | PosHardwareManager | Reads terminal `device_meta`, matches drivers |
| Boot | PosHardwareManager | Injects config, opens connections |
| Ready | HealthCheckJob | Marks device online/offline |
| Execute | Use cases via manager | print, open drawer, etc. |
| Shutdown | Session end / terminal lock | Release connections |

---

## 5. Registration

```php
// In driver service provider
PosHardwareManager::register(
    driverClass: EpsonEscPosDriver::class,
    metadata: new DriverMetadata(
        id: 'epson.escpos.1',
        type: DeviceType::ReceiptPrinter,
        name: 'Epson ESC/POS',
        configSchema: 'schemas/drivers/epson-escpos.json'
    )
);
```

**Storage:** `rateb_pos_terminals.device_meta.hardware.drivers[]`

---

## 6. Discovery

1. Load terminal `device_meta`
2. For each configured device entry:
   - Match `driver_id` to registry
   - Validate config against schema
   - Instantiate driver
3. Unknown driver → `Null*` fallback + diagnostic warning

**Discovery modes:**
- `configured` — from device_meta (production)
- `auto` — USB/network scan (Phase 3+, optional)
- `simulated` — all Null drivers (dev)

---

## 7. Health Checks

`HealthCheckResult`:
```json
{
  "status": "online|degraded|offline",
  "last_check_at": "ISO8601",
  "latency_ms": 42,
  "details": { "paper_low": false, "cover_open": false },
  "error_code": null
}
```

**Schedule:** `DeviceHealthCheckJob` every 15 min + on-demand from diagnostics screen.

---

## 8. Testing

| Layer | Approach |
|-------|----------|
| Unit | Mock interfaces |
| Driver integration | `SimulatedEpsonDriver` with fixture payloads |
| E2E | `NullPosPrinter` default in staging |
| Contract tests | Each driver must pass `HardwareDriverContractTest` |

**Test command:** `php artisan pos:hardware:test --terminal={id} --device=receipt_printer`

---

## 9. Built-in Drivers

### 9.1 Receipt Printers

| Driver ID | Protocol | Status |
|-----------|----------|--------|
| `null.printer.1` | No-op | Existing |
| `epson.escpos.1` | ESC/POS USB/Ethernet | Phase 3 |
| `star.line.1` | Star Line Mode | Phase 3 |
| `pdf.fallback.1` | Browser print / PDF | Phase 2 |

### 9.2 Kitchen Printers

| Driver ID | Notes |
|-----------|-------|
| `null.kitchen.1` | No-op |
| `epson.escpos.kitchen.1` | Route by station |
| `cloud.kds.1` | Kitchen Display API (future) |

### 9.3 Cash Drawer

| Driver ID | Notes |
|-----------|-------|
| `null.drawer.1` | Existing |
| `escpos.drawer.1` | Pulse via printer port |
| `usb.drawer.1` | Direct USB relay |

### 9.4 Barcode Scanner

| Driver ID | Notes |
|-----------|-------|
| `null.scanner.1` | Existing |
| `keyboard.wedge.1` | HID keyboard input |
| `serial.scanner.1` | Serial port |

### 9.5 Scale

| Driver ID | Notes |
|-----------|-------|
| `null.scale.1` | Existing |
| `serial.scale.1` | Generic serial |
| `cas.pdii.1` | CAS PD-II |

### 9.6 Customer Display

| Driver ID | Notes |
|-----------|-------|
| `null.display.1` | No-op |
| `serial.pole.1` | 2-line pole display |
| `usb.customer.1` | USB customer display |

### 9.7 Payment Terminal

| Driver ID | Notes |
|-----------|-------|
| `null.terminal.1` | Existing |
| `ngenius.terminal.1` | N-Genius integration |
| `geidea.terminal.1` | Geidea (future) |

### 9.8 NFC Badge

| Driver ID | Notes |
|-----------|-------|
| `null.nfc.1` | Existing |
| `usb.nfc.1` | USB reader for staff badge login |

---

## 10. Future Drivers

| Device | Interface | Priority |
|--------|-----------|----------|
| Label printer | `PosLabelPrinterInterface` | Phase 5 |
| Biometric | `PosBiometricInterface` | Enterprise |
| Coin dispenser | `PosCoinDispenserInterface` | Enterprise |
| Drive-through headset | `PosHeadsetInterface` | Future |

Extension SDK allows third-party driver registration.

---

## 11. Error Codes

| Code | Meaning | User action |
|------|---------|-------------|
| `HW_OFFLINE` | Device unreachable | Check connection |
| `HW_PAPER_OUT` | No paper | Reload paper |
| `HW_TIMEOUT` | Operation timeout | Retry |
| `HW_DRIVER_MISSING` | Unknown driver | Reconfigure terminal |
| `HW_TERMINAL_DECLINED` | Card declined | Different tender |
| `HW_CONFIG_INVALID` | Bad device_meta | Admin fix |

---

## 12. Security

- Payment terminal drivers never log PAN/track data
- device_meta secrets encrypted at rest (`encrypted:` prefix)
- Driver code runs server-side only (no client-side ESC/POS in Phase 1)
- USB access restricted to designated print server agent (Phase 3 architecture)

---

*End of POS-V2-HARDWARE-SDK.md*
