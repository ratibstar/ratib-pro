# RATEB POS V2 — Phase 1 Folder Structure

**Version:** 1.0.0  
**Date:** 2026-07-06  
**Status:** Planned — **no files created yet**

All paths relative to `rateb-erp/modules/pos/` unless noted.

---

## 1. Complete V2 Tree

```
rateb-erp/
├── migrations/
│   ├── 169_pos_v2_phase1.sql                    [NEW]
│   └── 170_pos_v2_permissions.sql               [NEW]
│
├── public/
│   └── assets/
│       └── pos/
│           └── v2/                              [NEW — entire subtree]
│               ├── css/
│               │   ├── pos-v2-tokens.css
│               │   ├── pos-v2-base.css
│               │   ├── pos-v2-shell.css
│               │   ├── pos-v2-shift-gate.css
│               │   ├── pos-v2-ticket.css
│               │   ├── pos-v2-catalog.css
│               │   ├── pos-v2-payment.css
│               │   ├── pos-v2-customer.css
│               │   ├── pos-v2-approval.css
│               │   └── pos-v2-receipt.css
│               └── js/
│                   ├── app.js
│                   ├── api/
│                   │   └── client.js
│                   ├── state/
│                   │   └── store.js
│                   ├── modules/
│                   │   ├── cart.js
│                   │   ├── catalog.js
│                   │   ├── customer.js
│                   │   ├── payment.js
│                   │   ├── approval.js
│                   │   ├── shift-gate.js
│                   │   └── receipt.js
│                   └── utils/
│                       ├── money.js
│                       ├── dom.js
│                       └── i18n.js
│
├── public/index.php                               [MODIFY — additive require only]
│
├── routes/
│   ├── api.php                                    [MODIFY — additive require only]
│   ├── pos-v2.php                                 [NEW]
│   └── pos-api-v2.php                             [NEW]
│
└── modules/pos/
    ├── PosModule.php                              [OPTIONAL — additive method only]
    │
    ├── config/
    │   └── v2/                                    [NEW]
    │       ├── settings-schema.json
    │       ├── permissions.php
    │       └── queue.php
    │
    ├── routes/                                    [EXISTING — untouched]
    │   ├── pos.php                                [NO CHANGES]
    │   └── pos-api.php                            [NO CHANGES]
    │
    ├── docs/
    │   └── v2/
    │       └── implementation/                    [NEW — planning docs]
    │
    ├── views/
    │   ├── register/                              [EXISTING V1 — untouched]
    │   ├── layouts/                               [EXISTING V1 — untouched]
    │   ├── partials/                              [EXISTING V1 — untouched]
    │   └── v2/                                    [NEW — entire subtree]
    │       ├── register/
    │       │   └── index.php
    │       ├── layouts/
    │       │   └── pos-v2-shell.php
    │       └── partials/
    │           ├── shift-gate.php
    │           ├── ticket.php
    │           ├── catalog.php
    │           ├── payment-sheet.php
    │           ├── customer-sheet.php
    │           ├── approval-sheet.php
    │           ├── receipt-preview.php
    │           ├── connectivity-banner.php
    │           └── register-bootstrap.php
    │
    └── app/
        ├── Contracts/                             [EXISTING — untouched]
        ├── Controllers/                           [EXISTING V1 — untouched]
        │   └── V2/                                [NEW]
        │       ├── PosV2ApiController.php
        │       ├── RegisterController.php
        │       ├── RegisterApiController.php
        │       ├── ShiftApiController.php
        │       ├── CatalogApiController.php
        │       ├── CartApiController.php
        │       ├── CustomerApiController.php
        │       ├── DiscountApiController.php
        │       ├── PaymentApiController.php
        │       ├── ApprovalApiController.php
        │       ├── ReceiptApiController.php
        │       └── SettingsApiController.php
        │
        ├── UseCases/
        │   └── V2/
        │       ├── Register/
        │       │   └── AccessRegisterUseCase.php
        │       ├── Shift/
        │       │   └── OpenShiftUseCase.php
        │       ├── Catalog/
        │       │   ├── SearchCatalogUseCase.php
        │       │   └── GetCatalogProductUseCase.php
        │       ├── Cart/
        │       │   ├── AddLineUseCase.php
        │       │   ├── UpdateLineUseCase.php
        │       │   ├── RemoveLineUseCase.php
        │       │   ├── ClearCartUseCase.php
        │       │   └── OverridePriceUseCase.php
        │       ├── Customer/
        │       │   ├── SearchCustomerUseCase.php
        │       │   ├── AttachCustomerUseCase.php
        │       │   └── DetachCustomerUseCase.php
        │       ├── Discount/
        │       │   ├── ApplyDiscountUseCase.php
        │       │   └── RemoveDiscountUseCase.php
        │       ├── Payment/
        │       │   ├── InitiateChargeUseCase.php
        │       │   ├── RecordPaymentUseCase.php
        │       │   └── CompleteSaleUseCase.php
        │       ├── Approval/
        │       │   ├── RequestApprovalUseCase.php
        │       │   ├── ApproveActionUseCase.php
        │       │   └── DenyActionUseCase.php
        │       └── Receipt/
        │           └── PrintReceiptUseCase.php
        │
        ├── Domain/
        │   └── V2/
        │       ├── Enums/
        │       │   ├── PosErrorCode.php
        │       │   ├── RegisterState.php
        │       │   ├── PaymentMethod.php
        │       │   ├── ApprovalAction.php
        │       │   └── ApprovalStatus.php
        │       ├── ValueObjects/
        │       │   ├── Money.php
        │       │   └── IdempotencyKey.php
        │       └── Exceptions/
        │           ├── PosDomainException.php
        │           ├── CartEmptyException.php
        │           ├── ApprovalRequiredException.php
        │           ├── ShiftNotOpenException.php
        │           └── IdempotencyConflictException.php
        │
        ├── DTO/
        │   └── V2/
        │       ├── Shared/
        │       │   ├── MoneyDto.php
        │       │   ├── ErrorDto.php
        │       │   ├── PaginationDto.php
        │       │   ├── RegisterContextDto.php
        │       │   └── ApprovalTokenDto.php
        │       ├── Register/
        │       │   ├── RegisterBootstrapResponse.php
        │       │   └── PosSettingsSummaryDto.php
        │       ├── Shift/
        │       │   ├── OpenShiftRequest.php
        │       │   └── ShiftResponse.php
        │       ├── Catalog/
        │       │   ├── CatalogSearchRequest.php
        │       │   ├── CatalogSearchResponse.php
        │       │   └── CatalogProductDto.php
        │       ├── Cart/
        │       │   ├── AddLineRequest.php
        │       │   ├── UpdateLineRequest.php
        │       │   ├── RemoveLineRequest.php
        │       │   ├── ClearCartRequest.php
        │       │   ├── OverridePriceRequest.php
        │       │   ├── CartResponse.php
        │       │   ├── CartLineDto.php
        │       │   └── CartTotalsDto.php
        │       ├── Customer/
        │       │   ├── CustomerSearchRequest.php
        │       │   ├── CustomerSearchResponse.php
        │       │   ├── AttachCustomerRequest.php
        │       │   └── CustomerSummaryDto.php
        │       ├── Discount/
        │       │   ├── ApplyDiscountRequest.php
        │       │   └── RemoveDiscountRequest.php
        │       ├── Payment/
        │       │   ├── InitiateChargeRequest.php
        │       │   ├── PaymentSheetResponse.php
        │       │   ├── RecordPaymentRequest.php
        │       │   ├── PaymentBalanceResponse.php
        │       │   ├── CompleteSaleRequest.php
        │       │   ├── CompleteSaleResponse.php
        │       │   └── PaymentLineDto.php
        │       ├── Approval/
        │       │   ├── RequestApprovalRequest.php
        │       │   ├── ApproveActionRequest.php
        │       │   ├── DenyActionRequest.php
        │       │   ├── ApprovalTokenResponse.php
        │       │   └── ApprovalPendingResponse.php
        │       └── Receipt/
        │           ├── PrintReceiptRequest.php
        │           └── PrintJobResponse.php
        │
        ├── Repositories/
        │   └── V2/
        │       ├── Contracts/
        │       │   ├── CartRepositoryInterface.php
        │       │   ├── SessionRepositoryInterface.php
        │       │   ├── ShiftRepositoryInterface.php
        │       │   ├── OrderRepositoryInterface.php
        │       │   ├── PaymentRepositoryInterface.php
        │       │   ├── ApprovalRepositoryInterface.php
        │       │   └── PrintJobRepositoryInterface.php
        │       └── Adapters/
        │           ├── CartRepository.php
        │           ├── SessionRepository.php
        │           ├── ShiftRepository.php
        │           ├── OrderRepository.php
        │           ├── PaymentRepository.php
        │           ├── ApprovalRepository.php
        │           └── PrintJobRepository.php
        │
        ├── Policies/
        │   └── V2/
        │       ├── RegisterPolicy.php
        │       ├── CartPolicy.php
        │       ├── PaymentPolicy.php
        │       └── ApprovalPolicy.php
        │
        ├── Events/
        │   └── V2/
        │       ├── DomainEventInterface.php
        │       ├── EventDispatcher.php
        │       ├── Register/
        │       │   ├── ShiftOpened.php
        │       │   └── ChargeInitiated.php
        │       ├── Cart/
        │       │   ├── LineAdded.php
        │       │   ├── LineUpdated.php
        │       │   ├── LineRemoved.php
        │       │   └── CartCleared.php
        │       ├── Payment/
        │       │   ├── OrderCreated.php
        │       │   ├── OrderCompleted.php
        │       │   └── PaymentRecorded.php
        │       ├── Approval/
        │       │   ├── ApprovalRequested.php
        │       │   ├── ApprovalGranted.php
        │       │   └── ApprovalDenied.php
        │       └── Listeners/
        │           ├── AuditListener.php
        │           └── ReceiptPrintListener.php
        │
        ├── Jobs/
        │   └── V2/
        │       ├── WriteAuditEventJob.php
        │       └── PrintReceiptJob.php
        │
        ├── Services/
        │   └── V2/
        │       ├── PosV2FeatureService.php
        │       ├── PosV2SettingsValidator.php
        │       ├── PosV2ServiceContainer.php
        │       ├── ApprovalWorkflowService.php
        │       ├── ApprovalTokenService.php
        │       ├── PaymentCollectionService.php
        │       └── PrintQueueService.php
        │
        ├── Adapters/
        │   └── V1/
        │       ├── PosCartServiceAdapter.php
        │       ├── PosCheckoutServiceAdapter.php
        │       ├── PosPricingServiceAdapter.php
        │       └── PosContextServiceAdapter.php
        │
        └── Models/
            └── V2/
                └── PosV2Models.php
```

---

## 2. Test Tree (Phase 1)

```
rateb-erp/tests/
├── Unit/Pos/V2/
│   ├── UseCases/
│   │   ├── CompleteSaleUseCaseTest.php
│   │   ├── AddLineUseCaseTest.php
│   │   └── ApproveActionUseCaseTest.php
│   ├── DTO/
│   │   └── MoneyDtoTest.php
│   └── Services/
│       └── PosV2FeatureServiceTest.php
└── Feature/Pos/V2/
    ├── RegisterBootstrapTest.php
    ├── CartFlowTest.php
    ├── PaymentCompleteTest.php
    └── ApprovalFlowTest.php
```

---

## 3. File Count Summary

| Category | New files (approx) |
|----------|-------------------|
| Migrations | 2 |
| Controllers V2 | 12 |
| UseCases | 18 |
| DTOs | 35 |
| Repositories | 14 |
| Policies | 4 |
| Events + Listeners | 15 |
| Jobs | 2 |
| Services V2 | 7 |
| Adapters V1 | 4 |
| Models V2 | 1 barrel (~5 classes) |
| Views V2 | 11 |
| CSS | 10 |
| JS | 12 |
| Config V2 | 3 |
| Routes | 2 new + 2 modified |
| Tests | ~12 |
| **Total** | **~152 new files** |

---

## 4. Explicitly NOT Created in Phase 1

```
app/Controllers/V2/Restaurant*
app/Controllers/V2/Pharmacy*
app/UseCases/V2/Returns/*
app/UseCases/V2/Suspend/*
app/Extensions/*
app/Hardware/Drivers/Epson*
views/v2/restaurant/*
public/assets/pos/v2/js/modules/table-map.js
```

---

## 5. V1 Paths — Zero New Files

These directories receive **no new files** in Phase 1:

- `modules/pos/views/register/`
- `modules/pos/views/partials/` (except no v2 nested here — v2 is separate)
- `modules/pos/routes/pos.php`
- `modules/pos/routes/pos-api.php`
- `modules/pos/app/Controllers/` (V1 controllers)
- `modules/pos/app/Services/` (V1 services — not modified)
- `public/assets/pos/css/` (V1 CSS)
- `public/assets/pos/js/` (V1 JS)

---

*End of PHASE1_FOLDER_STRUCTURE.md*
