# Phase K — Store listing assets checklist

**Product:** RATIB HR Mobile (ESS) · `sa.rateb.hr.mobile`  
**Do not invent marketing copy here** — fill with approved Legal / Branding content before upload.

## Google Play

| Asset | Spec (minimum) | Owner | Status |
|-------|----------------|-------|--------|
| App icon | 512×512 PNG, 32-bit | Branding | ☐ |
| Feature graphic | 1024×500 PNG/JPEG | Branding | ☐ |
| Phone screenshots | ≥2, 16:9 or 9:16 | Product | ☐ |
| 7" tablet screenshots | Optional | Product | ☐ |
| 10" tablet screenshots | Optional | Product | ☐ |
| Short description | ≤80 chars (AR + EN) | Product | ☐ |
| Full description | ≤4000 chars (AR + EN) | Product | ☐ |
| App category | Business / Productivity | Product | ☐ |
| Contact email | Support | Ops | ☐ |
| Privacy Policy URL | HTTPS public | Legal | ☐ |
| Terms of Service URL | HTTPS public | Legal | ☐ |
| Data safety form | See COMPLIANCE.md | Legal / Eng | ☐ |

## Apple App Store Connect

| Asset | Spec (minimum) | Owner | Status |
|-------|----------------|-------|--------|
| App icon | 1024×1024 PNG (no alpha) | Branding | ☐ |
| Splash / launch | Uses `LaunchScreen.storyboard` in binary | Eng | ☑ in app |
| iPhone screenshots | 6.7" + required sizes for current OS | Product | ☐ |
| iPad screenshots | If iPad listed | Product | ☐ |
| Name | ≤30 chars | Product | ☐ |
| Subtitle | ≤30 chars | Product | ☐ |
| Keywords | ≤100 chars comma-separated | Product | ☐ |
| Description | Full text AR/EN | Product | ☐ |
| Promotional text | Optional | Marketing | ☐ |
| Support URL | HTTPS | Ops | ☐ |
| Marketing URL | Optional HTTPS | Marketing | ☐ |
| Privacy Policy URL | HTTPS required | Legal | ☐ |
| Terms of Use (EULA) | Custom or standard | Legal | ☐ |
| App Privacy labels | See COMPLIANCE.md | Legal / Eng | ☐ |

## In-repo launch assets (engineering)

| Asset | Path | Notes |
|-------|------|-------|
| Android launcher icon | `android/app/src/main/res/mipmap-*` | Replace before store |
| Android splash | `drawable/launch_background.xml` | Branding pass |
| iOS AppIcon | `ios/Runner/Assets.xcassets/AppIcon.appiconset` | Replace 1024 before store |
| iOS LaunchScreen | `ios/Runner/Base.lproj/LaunchScreen.storyboard` | Present |

## Suggested public URLs (placeholders — confirm with Legal)

- Privacy Policy: `https://rateb.sa/privacy` (verify live page)
- Terms: `https://rateb.sa/terms` (verify live page)
- Support: `https://rateb.sa/support` or support email
- Marketing: `https://rateb.sa`
