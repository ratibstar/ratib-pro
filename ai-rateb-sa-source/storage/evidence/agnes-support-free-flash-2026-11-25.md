# Agnes Support Confirmation — Free API Access for agnes-video-2.5-flash

**Captured:** 2026-11-25  
**Account context:** RATEB AI Free/Default on platform.agnes-ai.com  
**Channel:** Agnes Support confirmation (operator-provided verbatim reply)

## Confirmation (verbatim)

Yes. For Free/Default users, agnes-video-2.5-flash is currently free at $0 / second during the limited-time promotion.

Limits that still apply:

- RPM limit: Free users have a dynamic RPM limit for video models (not the paid 5 RPM). The exact limit is adjusted based on platform capacity and shown in the console.
- Daily quota: Free users do not receive the Token Plan quota of 500 seconds per day. Usage is subject to the dynamic RPM limit only.
- No card required: You can use the free tier without adding a payment method.

Expiry / promotion notes:

- The $0/sec pricing is a limited-time free promotion. It may change or end without guaranteed notice.
- The model documentation states: "It is currently free for a limited time."
- List price remains $0.025 / second for 720P output.

Other Flash-specific limits (still enforced):

- size must be "720P"
- At most 5 reference images
- At most 3 reference audio clips
- No reference video input supported
- Duration: "4"–"12" seconds per task

For the most current RPM limit and promotion status, check the Agnes AI platform console.

## Operator interpretation used for RATEB

- Agnes Support has explicitly confirmed that `agnes-video-2.5-flash` is currently $0/sec for Free/Default users, without a payment method.
- Evidence basis for setting `RATEB_AGNES_FREE_ENTITLEMENT=1` (Agnes only).
- Does **not** authorize generation until a separate approval step.
- Does **not** authorize Replicate/Runware entitlements or Campaign 89 changes.

## Console verification (2026-11-25, logged-in Chrome)

- URL session: `platform.agnes-ai.com`
- API key: personal key **RATEB AI** present (`sk-…` masked); Last used `---`
- Enterprise Keys table: **No data** (personal free-key pool in use)
- Usage: Total Tokens/Images/Video Seconds/Requests = **0**
- Balance: **$0.00**; voucher $0; auto-recharge not linked; **no payment method**; payment history empty
- Profile: email present; badge shown as **Enterprise Verified**; Account ID present
- Numeric video RPM value: **not displayed** as an absolute number in Usage/API Keys/Balance
- Console text only: Enterprise keys have **2× RPM** of free personal user keys
- Docs reference (tokenplan): Free/Default video RPM = **Dynamic** (capacity-adjusted); Token Plan video effective RPM = 5; older public catalog reference Free video public 2 / actual 1 RPM

## Server entitlement change (2026-11-25)

- Backup: `/home/admin/domains/ai.rateb.sa/storage/backup-agnes-entitlement-20261125-075937/rateb-ai-env.php`
- Set: `RATEB_AGNES_FREE_ENTITLEMENT=1` only
- Unchanged: Replicate/Runware entitlements absent; no generation; Campaign 89 untouched

## Agnes-only free test prep (2026-11-25)

- Backup: `/home/admin/domains/ai.rateb.sa/storage/backup-agnes-free-only-20261125-080243/rateb-ai-env.php`
- Set:
  - `RATEB_VIDEO_FREE_ONLY=1`
  - `RATEB_VIDEO_PROVIDER=free_chain`
  - `RATEB_AGNES_VIDEO_MODEL=agnes-video-2.5-flash`
  - `RATEB_AGNES_FREE_ENTITLEMENT=1`
- Explicitly absent: `RATEB_REPLICATE_FREE_ENTITLEMENT`, `RATEB_RUNWARE_FREE_ENTITLEMENT`
- Probe: `ELIGIBLE_PROVIDERS=agnes`, `AGNES_ONLY_ELIGIBLE=yes`, `PREP_OK=yes`
- No generation submitted; Campaign 89 stages/content untouched
