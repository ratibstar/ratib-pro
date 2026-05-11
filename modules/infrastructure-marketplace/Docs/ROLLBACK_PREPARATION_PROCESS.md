Rollback Preparation Process

Before release:
- generate rollback checklist from CLI verifier output
- verify kill-switch operation path
- ensure provider activation snapshots are retrievable

During rollback event:
1) set `RATIB_INFRA_EXECUTION_KILL_SWITCH=1`
2) capture queue/deployment snapshots
3) pause infrastructure workers
4) verify core SaaS/payment flows remain unaffected
5) replay/reconcile jobs only after stabilization

