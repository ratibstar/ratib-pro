# CRM Migration Map (228–239)

| # | File | Purpose |
|---|------|---------|
| 228 | quotations | Quote headers/lines |
| 231 | conversions / status history | Sales transitions |
| 232 | loss reasons, outcomes, reminders, automation_log | Pipeline outcomes + automation log |
| 233 | revenue events, forecast snapshots, activity types, automation rules | Revenue ops |
| 234 | teams, territories, ownership, lifecycle, stage transitions | Sales ops |
| 235 | score history, saved report filters | Intelligence execution |
| 236 | forecast change log, governance settings, DQ issues, health history | Governance |
| 237 | stage governance, quality snapshots, saved dashboards, schedules | RevOps CC |
| 238 | predictive rules, insights, merges, freshness + indexes | Intelligence readiness |
| 239 | quote/automation/opp/task indexes + revops.run / insights.manage + automation_safety | **Hardening** |

## Rules

- Additive / guarded `CREATE INDEX IF` pattern only.
- No DROP / TRUNCATE / destructive ALTER.
- Apply in order 228→239 on production after verifying prior set.
