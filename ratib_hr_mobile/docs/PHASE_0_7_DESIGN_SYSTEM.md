# Phase 0.7 — UI Design System Lock

Enterprise Material 3 design system for RATEB HR Mobile.

## Tokens

`lib/core/theme/tokens/` — colors, typography, spacing, radius, elevation, shadows, icons, motion.

## Components

`lib/shared/design_system/` — buttons, cards, states, fields, nav, feedback, KPI, avatar, badges.

## Responsive

`lib/core/theme/app_responsive.dart` — phone / tablet / foldable / wide rules. No desktop ERP layout.

## Rules

- Presentation only
- No ERP / API / Login / business data
- Labels passed by caller
- Min touch target 48
- RTL via locale (Arabic first)
