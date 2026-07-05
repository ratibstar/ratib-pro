# RATEB POS V2 — Use Case Catalog

**Version:** 1.0.0  
**Pattern:** One class per use case in `UseCases/V2/`

Each entry follows: **Trigger → Input DTO → Output DTO → Permissions → Validation → Business Rules → Events → Audit → Services → Repositories**

---

## Register & Shift

### OpenShiftUseCase
| Field | Value |
|-------|-------|
| Trigger | Cashier taps Open Shift on Shift Gate |
| Input | `OpenShiftRequest` |
| Output | `ShiftResponse` |
| Permissions | `pos.shift.open` |
| Validation | Terminal assigned; no open shift for terminal |
| Business Rules | Opening cash required if configured; bind session |
| Events | `ShiftOpened` |
| Audit | opening_cash, user_id, terminal_id |
| Services | `PosShiftService`, `RegisterGateService` |
| Repositories | `ShiftRepository`, `SessionRepository` |

### CloseShiftUseCase
| Trigger | Manager/cashier closes shift |
| Input | `CloseShiftRequest` |
| Output | `CloseShiftResponse` |
| Permissions | `pos.shift.close` |
| Validation | No active cart; cash count provided |
| Business Rules | Variance > threshold → approval required |
| Events | `ShiftClosed` |
| Audit | counted_cash, variance, approver |
| Services | `PosShiftService`, `ApprovalWorkflowService` |
| Repositories | `ShiftRepository` |

### AccessRegisterUseCase
| Trigger | Navigate to register after shift open |
| Input | `RegisterContextRequest` |
| Output | `RegisterBootstrapResponse` |
| Permissions | `pos.register.access` |
| Validation | Shift open; terminal licensed |
| Business Rules | Load profile-specific UI flags |
| Events | `SessionStarted` (if new) |
| Audit | register_access |
| Services | `PosContextService`, `PosSessionService` |
| Repositories | `TerminalRepository`, `SessionRepository` |

---

## Cart & Catalog

### SearchCatalogUseCase
| Trigger | Catalog search / category tap |
| Input | `CatalogSearchRequest` |
| Output | `CatalogSearchResponse` |
| Permissions | `pos.catalog.view` |
| Validation | query min 0 chars; pagination bounds |
| Business Rules | Scope to terminal warehouse; hide out-of-stock per settings |
| Events | — |
| Audit | — |
| Services | `PosInventoryBridgeService` |
| Repositories | — (bridge read) |

### AddLineUseCase
| Trigger | Product tap / barcode scan |
| Input | `AddLineRequest` |
| Output | `CartResponse` |
| Permissions | `pos.cart.modify` |
| Validation | product_id, qty > 0 |
| Business Rules | Rx gate (pharmacy); reserve stock; merge duplicate lines |
| Events | `LineAdded` |
| Audit | product_id, qty, price |
| Services | `CartMutationService`, `PosInventoryBridgeService` |
| Repositories | `CartRepository` |

### UpdateLineUseCase
| Trigger | Qty change / note / modifier |
| Input | `UpdateLineRequest` |
| Output | `CartResponse` |
| Permissions | `pos.cart.modify` |
| Events | `LineUpdated` |
| Services | `CartMutationService` |
| Repositories | `CartRepository` |

### RemoveLineUseCase
| Trigger | Swipe delete / remove button |
| Input | `RemoveLineRequest` |
| Output | `CartResponse` |
| Permissions | `pos.cart.modify` |
| Events | `LineRemoved`, stock release |
| Services | `CartMutationService` |
| Repositories | `CartRepository` |

### ClearCartUseCase
| Trigger | Clear ticket |
| Input | `ClearCartRequest` |
| Output | `CartResponse` |
| Permissions | `pos.cart.clear` |
| Events | `CartCleared` |
| Audit | cart_cleared |
| Services | `CartMutationService` |
| Repositories | `CartRepository` |

### OverridePriceUseCase
| Trigger | Price override sheet submit |
| Input | `OverridePriceRequest` + optional `ApprovalToken` |
| Output | `CartResponse` |
| Permissions | `pos.price.override` + approval if required |
| Business Rules | Cannot go below cost if configured |
| Events | `PriceOverrideApplied` |
| Audit | old_price, new_price, reason, approver |
| Services | `CartMutationService`, `ApprovalWorkflowService` |
| Repositories | `CartRepository` |

### AddWeighedProductUseCase
| Trigger | Scale reading captured |
| Input | `WeighedProductRequest` |
| Output | `CartResponse` |
| Permissions | `pos.cart.modify` |
| Services | `PosScaleInterface`, `CartMutationService` |

---

## Customer

### SearchCustomerUseCase
| Input | `CustomerSearchRequest` |
| Output | `CustomerSearchResponse` |
| Permissions | `pos.customer.search` |
| Services | `PosCustomerBridgeService` |

### AttachCustomerUseCase
| Input | `AttachCustomerRequest` |
| Output | `CartResponse` |
| Events | `CustomerAttached` |
| Services | `PosCustomerBridgeService`, `CartRepository` |

### DetachCustomerUseCase
| Input | `DetachCustomerRequest` |
| Output | `CartResponse` |
| Events | `CustomerDetached` |

---

## Discounts

### ApplyDiscountUseCase
| Input | `ApplyDiscountRequest` |
| Output | `CartResponse` |
| Permissions | `pos.discount.apply` |
| Business Rules | Pre-Charge only; max % by role; approval if over threshold |
| Events | `DiscountApplied` or `DiscountApprovalRequired` |
| Services | `DiscountApplicationService` |

### RemoveDiscountUseCase
| Input | `RemoveDiscountRequest` |
| Output | `CartResponse` |
| Events | `DiscountRemoved` |

### ApproveDiscountUseCase
| Input | `ApproveDiscountRequest` |
| Output | `ApprovalTokenResponse` |
| Permissions | `pos.approval.grant` |
| Events | `ApprovalGranted` |

---

## Charge & Payment

### InitiateChargeUseCase
| Trigger | Charge button |
| Input | `InitiateChargeRequest` |
| Output | `PaymentSheetResponse` |
| Validation | Cart not empty; totals fresh |
| Events | `ChargeInitiated` |
| Services | `PosPricingService`, `RegisterOrchestrator` |

### RecordPaymentUseCase
| Input | `RecordPaymentRequest` |
| Output | `PaymentBalanceResponse` |
| Permissions | `pos.payment.record` |
| Events | `PaymentRecorded` |
| Services | `PaymentCollectionService` |

### CompleteSaleUseCase
| Trigger | Payment balance = 0 → Complete |
| Input | `CompleteSaleRequest` |
| Output | `CompleteSaleResponse` |
| Permissions | `pos.sale.complete` |
| Validation | idempotency_key required |
| Business Rules | Single DB transaction; post inventory + order |
| Events | `OrderCreated`, `OrderCompleted`, `PaymentCompleted` |
| Audit | full order snapshot |
| Services | `PosCheckoutService`, `OrderFactoryService` |
| Repositories | `OrderRepository`, `PaymentRepository` |

### ProcessTerminalPaymentUseCase
| Input | `TerminalPaymentRequest` |
| Output | `TerminalPaymentResponse` |
| Events | `TerminalPaymentStarted`, success/fail events |
| Services | `TerminalPaymentService`, `PosPaymentTerminalInterface` |

---

## Suspend & Quote

### SuspendSaleUseCase
| Input | `SuspendSaleRequest` |
| Output | `SuspendedSaleResponse` |
| Permissions | `pos.sale.suspend` |
| Events | `SaleSuspended` |
| Services | `CartSnapshotService` |

### ResumeSaleUseCase
| Input | `ResumeSaleRequest` |
| Output | `CartResponse` |
| Permissions | `pos.sale.resume` |
| Events | `SaleResumed` |

### ListSuspendedSalesUseCase
| Input | `ListSuspendedRequest` |
| Output | `SuspendedListResponse` |

---

## Returns & Exchange

### SearchOrderForReturnUseCase
| Input | `ReturnSearchRequest` |
| Output | `ReturnOrderResponse` |
| Permissions | `pos.return.search` |

### ProcessReturnUseCase
| Input | `ProcessReturnRequest` |
| Output | `ReturnCompleteResponse` |
| Permissions | `pos.return.process` |
| Business Rules | Approval if no receipt / outside window |
| Events | `ReturnCompleted`, `RefundIssued` |
| Services | `ReturnProcessingService` |

### ProcessExchangeUseCase
| Input | `ProcessExchangeRequest` |
| Output | `ExchangeCompleteResponse` |
| Permissions | `pos.return.exchange` |

---

## Receipt & Print

### PrintReceiptUseCase
| Input | `PrintReceiptRequest` |
| Output | `PrintJobResponse` |
| Permissions | `pos.receipt.print` |
| Events | `PrintJobQueued` |
| Services | `PosReceiptService`, `PrintQueueService` |

### ReprintReceiptUseCase
| Input | `ReprintReceiptRequest` |
| Output | `PrintJobResponse` |
| Permissions | `pos.receipt.reprint` |
| Audit | reprint reason required |

### SendDigitalReceiptUseCase
| Input | `DigitalReceiptRequest` |
| Output | `DigitalReceiptResponse` |
| Channels | email, SMS, WhatsApp per config |

---

## Approvals

### RequestApprovalUseCase
| Input | `RequestApprovalRequest` |
| Output | `ApprovalPendingResponse` |
| Events | `ApprovalRequested` |

### ApproveActionUseCase
| Input | `ApproveActionRequest` |
| Output | `ApprovalTokenResponse` |
| Permissions | `pos.approval.grant` |
| Events | `ApprovalGranted` |

### DenyActionUseCase
| Input | `DenyActionRequest` |
| Output | `ApprovalDeniedResponse` |
| Events | `ApprovalDenied` |

---

## Hardware

### OpenCashDrawerUseCase
| Input | `OpenDrawerRequest` |
| Output | `DrawerResponse` |
| Permissions | `pos.drawer.open` |
| Audit | reason required |
| Events | `DrawerOpened` |
| Services | `PosCashDrawerHardwareInterface` |

### TestPrintUseCase
| Input | `TestPrintRequest` |
| Output | `PrintJobResponse` |
| Permissions | `pos.hardware.test` |

### GetDeviceStatusUseCase
| Input | `DeviceStatusRequest` |
| Output | `DeviceStatusResponse` |
| Services | `PosHardwareManager` |

---

## Session & Recovery

### SaveCartSnapshotUseCase
| Input | `CartSnapshotRequest` |
| Output | `SnapshotResponse` |
| Events | `CartSnapshotSaved` |

### RecoverSessionUseCase
| Input | `RecoverSessionRequest` |
| Output | `RegisterBootstrapResponse` |
| Events | `SessionRecovered` |

---

## Restaurant (profile)

### OpenTableUseCase
| Input | `OpenTableRequest` |
| Output | `TableSessionResponse` |
| Events | `TableOpened` |

### SplitBillUseCase
| Input | `SplitBillRequest` |
| Output | `SplitBillResponse` |
| Events | `BillSplit` |

### MergeTablesUseCase
| Input | `MergeTablesRequest` |
| Output | `TableSessionResponse` |
| Events | `TableMerged` |

### SendKitchenTicketUseCase
| Input | `KitchenTicketRequest` |
| Output | `KitchenTicketResponse` |
| Events | `KitchenTicketSent` |
| Queue | `pos-kitchen` |

### AddTipUseCase
| Input | `AddTipRequest` |
| Output | `PaymentBalanceResponse` |
| Events | `TipAdded` |

---

## Pharmacy (profile)

### ValidatePrescriptionUseCase
| Input | `ValidatePrescriptionRequest` |
| Output | `PrescriptionValidationResponse` |
| Events | `PrescriptionValidated` |

### SelectBatchUseCase
| Input | `SelectBatchRequest` |
| Output | `CartResponse` |
| Business Rules | FEFO default |

---

## Enterprise

### SyncOfflineBatchUseCase
| Input | `SyncBatchRequest` |
| Output | `SyncBatchResponse` |
| Events | `OfflineSyncCompleted`, `SyncConflictDetected` |
| Queue | `pos-offline-sync` |

### ResolveSyncConflictUseCase
| Input | `ResolveConflictRequest` |
| Output | `ConflictResolutionResponse` |
| Permissions | `pos.sync.resolve` |

### ValidateLicenseUseCase
| Input | `ValidateLicenseRequest` |
| Output | `LicenseStatusResponse` |
| Queue | `pos-license` |

### EnterEmergencyModeUseCase
| Input | `EmergencyModeRequest` |
| Output | `EmergencyModeResponse` |
| Events | `OfflineModeEntered` |
| Audit | emergency_enabled |

### ListAuditTimelineUseCase
| Input | `AuditTimelineRequest` |
| Output | `AuditTimelineResponse` |
| Permissions | `pos.audit.view` |

---

## Index (alphabetical)

`AccessRegisterUseCase`, `AddLineUseCase`, `AddTipUseCase`, `AddWeighedProductUseCase`, `ApplyDiscountUseCase`, `ApproveActionUseCase`, `ApproveDiscountUseCase`, `AttachCustomerUseCase`, `ClearCartUseCase`, `CloseShiftUseCase`, `CompleteSaleUseCase`, `DenyActionUseCase`, `DetachCustomerUseCase`, `EnterEmergencyModeUseCase`, `GetDeviceStatusUseCase`, `InitiateChargeUseCase`, `ListAuditTimelineUseCase`, `ListSuspendedSalesUseCase`, `MergeTablesUseCase`, `OpenCashDrawerUseCase`, `OpenShiftUseCase`, `OpenTableUseCase`, `OverridePriceUseCase`, `PrintReceiptUseCase`, `ProcessExchangeUseCase`, `ProcessReturnUseCase`, `ProcessTerminalPaymentUseCase`, `RecordPaymentUseCase`, `RecoverSessionUseCase`, `RemoveDiscountUseCase`, `RemoveLineUseCase`, `ReprintReceiptUseCase`, `RequestApprovalUseCase`, `ResolveSyncConflictUseCase`, `ResumeSaleUseCase`, `SaveCartSnapshotUseCase`, `SearchCatalogUseCase`, `SearchCustomerUseCase`, `SearchOrderForReturnUseCase`, `SelectBatchUseCase`, `SendDigitalReceiptUseCase`, `SendKitchenTicketUseCase`, `SplitBillUseCase`, `SuspendSaleUseCase`, `SyncOfflineBatchUseCase`, `TestPrintUseCase`, `UpdateLineUseCase`, `ValidateLicenseUseCase`, `ValidatePrescriptionUseCase`

---

*End of POS-V2-USECASE-CATALOG.md*
