# RATEB POS V2 — Configuration Schema

**Version:** 1.0.0  
**Storage:** `rateb_pos_settings.settings_json` (existing table, additive keys)  
**Schema ID:** `https://rateb.sa/schemas/pos/v2/settings.json`

---

## 1. Schema Version

```json
{
  "$schema": "https://json-schema.org/draft/2020-12/schema",
  "$id": "https://rateb.sa/schemas/pos/v2/settings.json",
  "title": "RATEB POS V2 Settings",
  "type": "object",
  "required": ["schema_version", "v2"],
  "properties": {
    "schema_version": { "type": "integer", "minimum": 1, "default": 1 },
    "v2": { "$ref": "#/$defs/v2Root" }
  }
}
```

---

## 2. Validation

- Validated on save via `PosSettingsValidator` (V2)
- Invalid config → 422 with JSON Schema error paths
- Unknown keys allowed under `v2.extensions.{id}` (passthrough)

---

## 3. Migration Strategy

| Step | Action |
|------|--------|
| 1 | Read `schema_version` |
| 2 | Run migrator chain `V1→V2`, `V2.0→V2.1`, etc. |
| 3 | Merge defaults for missing keys |
| 4 | Write back with updated `schema_version` |
| 5 | Emit `PosSettingsMigrated` event |

**Rule:** Never delete keys on downgrade; ignore unknown on read.

---

## 4. Full Schema (`v2` root)

```json
{
  "$defs": {
    "v2Root": {
      "type": "object",
      "required": ["enabled", "profile"],
      "properties": {
        "enabled": { "type": "boolean", "default": false },
        "profile": {
          "type": "string",
          "enum": ["retail", "restaurant", "pharmacy", "fashion", "enterprise"],
          "default": "retail"
        },
        "ui": { "$ref": "#/$defs/ui" },
        "hardware": { "$ref": "#/$defs/hardware" },
        "discounts": { "$ref": "#/$defs/discounts" },
        "approvals": { "$ref": "#/$defs/approvals" },
        "profiles": { "$ref": "#/$defs/profiles" },
        "printing": { "$ref": "#/$defs/printing" },
        "offline": { "$ref": "#/$defs/offline" },
        "restaurant": { "$ref": "#/$defs/restaurant" },
        "pharmacy": { "$ref": "#/$defs/pharmacy" },
        "licensing": { "$ref": "#/$defs/licensing" },
        "diagnostics": { "$ref": "#/$defs/diagnostics" },
        "sync": { "$ref": "#/$defs/sync" },
        "notifications": { "$ref": "#/$defs/notifications" },
        "extensions": { "type": "object", "additionalProperties": true }
      }
    },
    "ui": {
      "type": "object",
      "properties": {
        "locale_default": { "type": "string", "default": "ar" },
        "rtl": { "type": "boolean", "default": true },
        "touch_target_min_px": { "type": "integer", "default": 48 },
        "catalog_columns": { "type": "integer", "minimum": 2, "maximum": 6, "default": 4 },
        "show_product_images": { "type": "boolean", "default": true },
        "charge_button_label": { "type": "string", "default": "Charge" },
        "idle_timeout_minutes": { "type": "integer", "default": 15 }
      }
    },
    "hardware": {
      "type": "object",
      "properties": {
        "discovery_mode": { "enum": ["configured", "auto", "simulated"], "default": "configured" },
        "drivers": {
          "type": "array",
          "items": {
            "type": "object",
            "required": ["driver_id", "device_type"],
            "properties": {
              "driver_id": { "type": "string" },
              "device_type": { "type": "string" },
              "enabled": { "type": "boolean", "default": true },
              "config": { "type": "object" }
            }
          }
        }
      }
    },
    "discounts": {
      "type": "object",
      "properties": {
        "max_cashier_percent": { "type": "number", "default": 10 },
        "max_supervisor_percent": { "type": "number", "default": 25 },
        "require_reason_above_percent": { "type": "number", "default": 5 },
        "allow_line_discount": { "type": "boolean", "default": true },
        "allow_cart_discount": { "type": "boolean", "default": true }
      }
    },
    "approvals": {
      "type": "object",
      "properties": {
        "token_ttl_seconds": { "type": "integer", "default": 60 },
        "methods": {
          "type": "array",
          "items": { "enum": ["pin", "badge_scan", "manager_login"] },
          "default": ["pin"]
        },
        "require_for": {
          "type": "object",
          "properties": {
            "price_override": { "type": "boolean", "default": true },
            "discount_above_percent": { "type": "number", "default": 10 },
            "return_without_receipt": { "type": "boolean", "default": true },
            "shift_variance_above": { "type": "number", "default": 50 },
            "void_order": { "type": "boolean", "default": true }
          }
        }
      }
    },
    "profiles": {
      "type": "object",
      "properties": {
        "retail": { "type": "object", "properties": { "returns_enabled": { "type": "boolean", "default": true } } },
        "fashion": { "type": "object", "properties": { "matrix_variants": { "type": "boolean", "default": true } } }
      }
    },
    "printing": {
      "type": "object",
      "properties": {
        "auto_print_receipt": { "type": "boolean", "default": true },
        "copies": { "type": "integer", "minimum": 1, "maximum": 3, "default": 1 },
        "include_barcode": { "type": "boolean", "default": true },
        "footer_text": { "type": "string", "maxLength": 500 },
        "kitchen_routing": { "type": "object", "additionalProperties": { "type": "string" } }
      }
    },
    "offline": {
      "type": "object",
      "properties": {
        "mode": { "enum": ["disabled", "read_only_banner", "cash_only_emergency"], "default": "read_only_banner" },
        "emergency_max_sale_amount": { "type": "number", "default": 500 },
        "emergency_max_items": { "type": "integer", "default": 20 },
        "sync_on_reconnect": { "type": "boolean", "default": true }
      }
    },
    "restaurant": {
      "type": "object",
      "properties": {
        "table_map_enabled": { "type": "boolean", "default": true },
        "default_service_mode": { "enum": ["dine_in", "takeaway", "delivery"], "default": "dine_in" },
        "tips_enabled": { "type": "boolean", "default": true },
        "tip_presets_percent": { "type": "array", "items": { "type": "number" }, "default": [5, 10, 15] },
        "kitchen_stations": { "type": "array", "items": { "type": "string" } }
      }
    },
    "pharmacy": {
      "type": "object",
      "properties": {
        "rx_validation_required": { "type": "boolean", "default": true },
        "controlled_drug_approval": { "type": "boolean", "default": true },
        "batch_selection_required": { "type": "boolean", "default": true },
        "regulatory_region": { "type": "string", "default": "SA-SFDA" }
      }
    },
    "licensing": {
      "type": "object",
      "properties": {
        "terminal_binding_required": { "type": "boolean", "default": true },
        "validation_interval_hours": { "type": "integer", "default": 6 },
        "grace_period_hours": { "type": "integer", "default": 24 }
      }
    },
    "diagnostics": {
      "type": "object",
      "properties": {
        "health_check_interval_minutes": { "type": "integer", "default": 15 },
        "log_level": { "enum": ["error", "warning", "info", "debug"], "default": "warning" },
        "expose_device_status_to_cashier": { "type": "boolean", "default": false }
      }
    },
    "sync": {
      "type": "object",
      "properties": {
        "batch_size": { "type": "integer", "default": 50 },
        "conflict_strategy": { "enum": ["manual", "server_wins", "newest_wins"], "default": "manual" },
        "retry_max_attempts": { "type": "integer", "default": 5 }
      }
    },
    "notifications": {
      "type": "object",
      "properties": {
        "email_receipt_enabled": { "type": "boolean", "default": true },
        "sms_receipt_enabled": { "type": "boolean", "default": false },
        "whatsapp_enabled": { "type": "boolean", "default": false },
        "manager_approval_push": { "type": "boolean", "default": true }
      }
    }
  }
}
```

---

## 5. Defaults (minimal retail)

```json
{
  "schema_version": 1,
  "v2": {
    "enabled": false,
    "profile": "retail",
    "ui": { "locale_default": "ar", "rtl": true },
    "offline": { "mode": "read_only_banner" },
    "approvals": { "token_ttl_seconds": 60 },
    "discounts": { "max_cashier_percent": 10 }
  }
}
```

---

## 6. Example (restaurant + hardware)

```json
{
  "schema_version": 1,
  "v2": {
    "enabled": true,
    "profile": "restaurant",
    "ui": { "catalog_columns": 3, "charge_button_label": "Charge" },
    "hardware": {
      "discovery_mode": "configured",
      "drivers": [
        { "driver_id": "epson.escpos.1", "device_type": "receipt_printer", "config": { "host": "192.168.1.50" } },
        { "driver_id": "epson.escpos.kitchen.1", "device_type": "kitchen_printer", "config": { "host": "192.168.1.51" } }
      ]
    },
    "restaurant": {
      "table_map_enabled": true,
      "tips_enabled": true,
      "kitchen_stations": ["grill", "cold", "bar"]
    },
    "printing": {
      "kitchen_routing": { "grill": "192.168.1.51", "bar": "192.168.1.52" }
    }
  }
}
```

---

## 7. Company vs Terminal Override

| Level | Key path | Precedence |
|-------|----------|------------|
| Company | `rateb_pos_settings` (company scope) | Base |
| Terminal | `rateb_pos_terminals.device_meta.v2` | Overrides company |

Merge: deep merge with terminal winning on conflict.

---

*End of POS-V2-CONFIGURATION-SCHEMA.md*
