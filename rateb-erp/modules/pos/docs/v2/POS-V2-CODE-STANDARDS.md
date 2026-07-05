# RATEB POS V2 — Code Standards

**Version:** 1.0.0  
**Mandatory for all V2 contributions**

---

## 1. Folder Rules

| Layer | Path | May import |
|-------|------|------------|
| Controllers | `Controllers/V2/` | UseCases, DTOs, Policies |
| UseCases | `UseCases/V2/{Domain}/` | Domain, DTOs, Repository interfaces |
| Domain | `Domain/V2/` | Domain only |
| DTOs | `DTO/V2/` | Other DTOs only |
| Repositories | `Repositories/V2/` | Domain, V1 adapters |
| Policies | `Policies/V2/` | Auth, Domain enums |
| Events | `Events/V2/` | DTOs for payloads |
| Jobs | `Jobs/V2/` | UseCases, Services |
| Views | `views/v2/` | PHP layout only |
| CSS | `public/assets/pos/v2/css/` | — |
| JS | `public/assets/pos/v2/js/` | — |

**Forbidden:** V2 files in V1 folders without `V2` subfolder/namespace.

---

## 2. Naming Conventions

| Artifact | Convention | Example |
|----------|------------|---------|
| UseCase | `{Verb}{Noun}UseCase` | `CompleteSaleUseCase` |
| Request DTO | `{Verb}{Noun}Request` | `CompleteSaleRequest` |
| Response DTO | `{Noun}Response` | `CartResponse` |
| Repository interface | `{Entity}RepositoryInterface` | `OrderRepositoryInterface` |
| Repository impl | `{Entity}Repository` | `OrderRepository` |
| Policy | `{Entity}Policy` | `CartPolicy` |
| Domain event | Past tense PascalCase | `OrderCompleted` |
| Job | `{Action}Job` | `PrintReceiptJob` |
| Controller | `{Area}Controller` | `RegisterApiController` |
| CSS file | `pos-v2-{component}.css` | `pos-v2-ticket.css` |
| JS module | `{feature}.js` ES modules | `cart.js` |

---

## 3. Dependency Rules

1. **Domain** has zero framework imports except PHP standard + domain exceptions.
2. **UseCases** inject interfaces; never concrete V1 services directly (use adapter).
3. **Controllers** max 15 lines per action — delegate to UseCase.
4. **No circular dependencies** between domains — use events.
5. **V1 service access** only through `Adapters/V1/` or existing Bridge services.

---

## 4. Service Rules

- Services contain orchestration logic reusable across use cases.
- Single responsibility: one reason to change.
- Stateless unless explicitly a registry (HardwareManager).
- No HTTP concerns (Request/Response) in services.
- No direct `$_GET`/`$_POST`.

---

## 5. Repository Rules

- One repository per aggregate root.
- Return domain entities or DTOs — never raw PDO rows to UseCase.
- All writes in explicit methods (`save`, `delete`) — no generic `update(array)`.
- Transactions owned by UseCase, not Repository.

---

## 6. DTO Rules

- Immutable (`readonly` properties PHP 8.2+).
- No business logic except lightweight validation in factory.
- Never pass `array` where DTO exists.
- API responses always built from Response DTOs.

---

## 7. Policy Rules

- One policy class per aggregate/resource.
- Methods: `view`, `create`, `update`, `delete`, domain-specific (`complete`, `override`).
- Register in `AuthServiceProvider` equivalent.
- Controller: `$this->authorize('modify', $cart)`.

---

## 8. Migration Rules

- Additive only: new tables, nullable columns, indexes.
- Prefix new tables: `rateb_pos_`
- Never drop V1 columns in V2 migrations.
- Migration filename: `{next}_pos_v2_{description}.sql`
- Rollback script required in comment block.

---

## 9. Controller Rules

```php
// REQUIRED pattern
public function complete(CompleteSaleRequest $request): JsonResponse
{
    $this->authorize('complete', PosOrder::class);
    $response = $this->completeSaleUseCase->execute($request);
    return response()->json($response, 200);
}
```

- FormRequest or DTO factory for input
- No business logic in controller
- Consistent error envelope (see OpenAPI)

---

## 10. Testing Rules

| Layer | Test type | Location |
|-------|-----------|----------|
| Domain | Unit | `tests/Unit/Pos/V2/Domain/` |
| UseCase | Unit + mocks | `tests/Unit/Pos/V2/UseCases/` |
| Repository | Integration | `tests/Integration/Pos/V2/` |
| API | Feature | `tests/Feature/Pos/V2/` |
| JS | Vitest (Phase 2+) | `tests/Js/Pos/V2/` |

- Minimum 80% UseCase coverage before Phase 2 release
- Every Policy has deny/allow tests

---

## 11. CSS Rules

- **No inline CSS** in views
- BEM-like: `.pos-v2-{block}__{element}--{modifier}`
- Design tokens in `pos-v2-tokens.css` only
- RTL: logical properties (`margin-inline-start`)
- No `!important` except print stylesheet
- Max specificity: 0-3-0

---

## 12. JavaScript Rules

- **No inline JavaScript** in views
- ES modules only; no global pollution except `window.RatebPosV2` bootstrap
- State in single store module; no scattered `localStorage`
- All API calls via `api/client.js`
- `data-pos-*` hooks preserved for V1 parity
- Touch targets min 48px (configurable)
- No jQuery in V2

---

## 13. PHP Rules

- PSR-12 formatting
- `declare(strict_types=1);` in all V2 files
- Type hints on all parameters and returns
- No `@` error suppression
- Exceptions: domain exceptions mapped to HTTP in handler
- Max method length: 30 lines (extract private methods)

---

## 14. Documentation Rules

- Every UseCase: one-line docblock with trigger
- OpenAPI must match controller routes (CI check)
- ADR for architectural changes (POS-V2-DECISIONS.md)
- README in `docs/v2/` index linking all docs

---

## 15. Git & Review Checklist

- [ ] V1 routes untouched
- [ ] Feature flag gated
- [ ] No inline CSS/JS
- [ ] DTOs used (no raw arrays)
- [ ] Policy authorized
- [ ] Events after commit
- [ ] Audit for sensitive actions
- [ ] OpenAPI updated
- [ ] Tests added

---

*End of POS-V2-CODE-STANDARDS.md*
