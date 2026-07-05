# RATEB POS V2 — DTO Catalog

**Version:** 1.0.0  
**Rule:** No raw arrays in UseCase signatures. All I/O via typed DTOs in `DTO/V2/`.

---

## 1. Conventions

| Aspect | Rule |
|--------|------|
| Request DTOs | Suffix `Request`; immutable; validated in constructor or `fromArray()` |
| Response DTOs | Suffix `Response`; always serializable to JSON |
| Nested data | Child DTOs, not associative arrays |
| Money | `MoneyDto { amount: string, currency: string }` (decimal string) |
| IDs | `int` for internal IDs; `string` UUID for tokens |
| Validation | Laravel `Validator` or Symfony `Assert` in factory |
| Serialization | `toArray(): array`, `jsonSerialize(): array` |

---

## 2. Shared DTOs

### MoneyDto
```json
{ "amount": "125.50", "currency": "SAR" }
```
Validation: `amount` decimal string, scale 2; `currency` ISO 4217.

### PaginationDto
```json
{ "page": 1, "per_page": 24, "total": 100, "last_page": 5 }
```

### ErrorDto
```json
{
  "code": "POS_CART_EMPTY",
  "message": "Cart is empty",
  "field": null,
  "details": {}
}
```

### ApprovalTokenDto
```json
{
  "token": "uuid",
  "expires_at": "2026-07-05T12:01:00Z",
  "action": "price_override"
}
```

### RegisterContextDto
```json
{
  "terminal_id": 5,
  "branch_id": 2,
  "warehouse_id": 10,
  "shift_id": 100,
  "session_id": 200,
  "profile": "retail",
  "locale": "ar",
  "permissions": ["pos.cart.modify"]
}
```

---

## 3. Register & Shift

### OpenShiftRequest
| Field | Type | Validation |
|-------|------|------------|
| terminal_id | int | required, exists |
| opening_cash | MoneyDto | optional |
| notes | string | max 500 |

### ShiftResponse
| Field | Type |
|-------|------|
| shift_id | int |
| status | enum: open, closed |
| opened_at | ISO8601 |
| opening_cash | MoneyDto |

### CloseShiftRequest
| Field | Type | Validation |
|-------|------|------------|
| shift_id | int | required |
| counted_cash | MoneyDto | required |
| notes | string | optional |
| approval_token | string | if variance |

### RegisterBootstrapResponse
| Field | Type |
|-------|------|
| context | RegisterContextDto |
| cart | CartResponse |
| settings | PosSettingsSummaryDto |
| catalog | CatalogBootstrapDto |

---

## 4. Catalog

### CatalogSearchRequest
| Field | Type | Validation |
|-------|------|------------|
| query | string | max 100 |
| category_id | int | optional |
| page | int | min 1 |
| per_page | int | 1-48 |

### CatalogProductDto
```json
{
  "id": 1,
  "sku": "SKU001",
  "name": "Product",
  "price": { "amount": "10.00", "currency": "SAR" },
  "image_url": "/...",
  "in_stock": true,
  "requires_weight": false
}
```

### CatalogSearchResponse
| Field | Type |
|-------|------|
| products | CatalogProductDto[] |
| pagination | PaginationDto |

---

## 5. Cart

### AddLineRequest
| Field | Type | Validation |
|-------|------|------------|
| product_id | int | required |
| qty | string | decimal > 0 |
| modifiers | ModifierSelectionDto[] | optional |
| note | string | max 200 |
| prescription_token | string | pharmacy profile |

### CartLineDto
```json
{
  "line_id": "uuid",
  "product_id": 1,
  "name": "Product",
  "qty": "2",
  "unit_price": { "amount": "10.00", "currency": "SAR" },
  "line_total": { "amount": "20.00", "currency": "SAR" },
  "discount": null,
  "modifiers": [],
  "note": null
}
```

### CartTotalsDto
```json
{
  "subtotal": { "amount": "100.00", "currency": "SAR" },
  "discount": { "amount": "5.00", "currency": "SAR" },
  "tax": { "amount": "14.25", "currency": "SAR" },
  "total": { "amount": "109.25", "currency": "SAR" }
}
```

### CartResponse
| Field | Type |
|-------|------|
| lines | CartLineDto[] |
| totals | CartTotalsDto |
| customer | CustomerSummaryDto \| null |
| item_count | int |

### UpdateLineRequest / RemoveLineRequest / ClearCartRequest
Standard session + line_id + mutation fields.

### OverridePriceRequest
| Field | Type |
|-------|------|
| line_id | string |
| new_unit_price | MoneyDto |
| reason | string |
| approval_token | string optional |

---

## 6. Customer

### CustomerSearchRequest
`query` string min 2; `limit` int max 20.

### CustomerSummaryDto
```json
{ "id": 1, "name": "Ahmed", "phone": "+966...", "loyalty_points": 0 }
```

### AttachCustomerRequest
`customer_id` int required.

---

## 7. Discounts

### ApplyDiscountRequest
| Field | Type |
|-------|------|
| scope | enum: line, cart |
| line_id | string optional |
| type | enum: percent, fixed, coupon |
| value | string |
| coupon_code | string optional |
| reason | string optional |

---

## 8. Payment

### InitiateChargeRequest
`session_id` int.

### PaymentSheetResponse
| Field | Type |
|-------|------|
| totals | CartTotalsDto |
| allowed_methods | PaymentMethodDto[] |
| balance_due | MoneyDto |

### RecordPaymentRequest
| Field | Type |
|-------|------|
| method | string |
| amount | MoneyDto |
| reference | string optional |

### PaymentBalanceResponse
| Field | Type |
|-------|------|
| payments | PaymentLineDto[] |
| balance_due | MoneyDto |
| change_due | MoneyDto |

### CompleteSaleRequest
| Field | Type | Validation |
|-------|------|------------|
| idempotency_key | string | required, uuid |
| session_id | int | required |
| payments | PaymentLineDto[] | min 1 |
| send_receipt | DigitalReceiptPreferenceDto | optional |

### CompleteSaleResponse
```json
{
  "order_id": 1,
  "order_no": "POS-2026-001",
  "totals": { "...": "CartTotalsDto" },
  "receipt": { "preview_url": "...", "print_job_id": "uuid" },
  "change_due": { "amount": "5.00", "currency": "SAR" }
}
```

### TerminalPaymentRequest / TerminalPaymentResponse
Terminal-specific amounts, auth codes, masked PAN last4.

---

## 9. Returns

### ReturnSearchRequest
`query` string (order_no or barcode).

### ReturnOrderResponse
Original order lines with returnable qty.

### ProcessReturnRequest
| Field | Type |
|-------|------|
| order_id | int |
| lines | ReturnLineDto[] |
| refund_method | string |
| reason | string |
| approval_token | string optional |

### ReturnCompleteResponse
return_id, refund_amount, receipt reference.

---

## 10. Suspend

### SuspendSaleRequest
`label` string optional; `session_id`.

### SuspendedSaleDto
id, label, item_count, total, suspended_at.

### ResumeSaleRequest
`suspended_id` int.

---

## 11. Receipt & Print

### PrintReceiptRequest
`order_id` int; `copies` int optional.

### PrintJobResponse
```json
{ "job_id": "uuid", "status": "queued|printing|completed|failed" }
```

### DigitalReceiptRequest
`order_id`, `channel` enum, `destination` string.

---

## 12. Approvals

### RequestApprovalRequest
`action` string; `payload` ApprovalPayloadDto.

### ApproveActionRequest
`request_id` int; `pin` or `badge_token`; `approver_id` from auth.

---

## 13. Hardware

### OpenDrawerRequest
`reason` enum: sale, no_sale, return.

### DeviceStatusResponse
```json
{
  "devices": [
    { "type": "receipt_printer", "driver_id": "epson.escpos.1", "status": "online" }
  ]
}
```

---

## 14. Restaurant

### OpenTableRequest
`table_id` int; `guest_count` int optional.

### SplitBillRequest
`order_id`; `mode` enum: equal, by_item, custom; `splits` SplitDefinitionDto[].

### KitchenTicketRequest
`order_id`; `station` string optional.

---

## 15. Pharmacy

### ValidatePrescriptionRequest
`rx_number` string; `patient_ref` string optional.

### PrescriptionValidationResponse
`valid` bool; `token` string; `expires_at` ISO8601.

### SelectBatchRequest
`line_id`; `batch_id` int.

---

## 16. Enterprise

### SyncBatchRequest
`items` SyncItemDto[]; `terminal_id`.

### SyncBatchResponse
`processed` int; `conflicts` SyncConflictDto[].

### AuditTimelineRequest
`from`, `to` ISO8601; pagination.

### AuditEventDto
id, action, actor, entity, occurred_at, payload summary.

---

## 17. Serialization Example (PHP)

```php
final readonly class CartResponse implements \JsonSerializable
{
    /** @param list<CartLineDto> $lines */
    public function __construct(
        public array $lines,
        public CartTotalsDto $totals,
        public ?CustomerSummaryDto $customer,
        public int $itemCount,
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'lines' => array_map(fn (CartLineDto $l) => $l->jsonSerialize(), $this->lines),
            'totals' => $this->totals->jsonSerialize(),
            'customer' => $this->customer?->jsonSerialize(),
            'item_count' => $this->itemCount,
        ];
    }
}
```

---

*End of POS-V2-DTO-CATALOG.md*
