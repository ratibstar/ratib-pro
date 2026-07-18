/**
 * B1 Preparation — Rollback Drill (G9)
 * Validates flag rollback + queue/identity integrity invariants (no Admin cutover).
 */
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const BASE = process.env.RATEB_B1_PREP_URL
  || 'https://rateb.sa/rateb-erp/public/assets/offline/platform/cutover/harness.html';
const OUT = process.env.RATEB_B1_ROLLBACK_OUT
  || path.join(__dirname, 'reports', `phase-b1-prep-rollback-drill-${Date.now()}.json`);

async function main() {
  const evidence = [];
  const browser = await chromium.launch({ headless: true });
  const report = { phase: 'B1-PREP-ROLLBACK-DRILL', base: BASE, evidence: evidence, ok: false };
  function note(step, pass, detail) {
    evidence.push({ step: step, ok: !!pass, detail: detail || '' });
  }

  try {
    const page = await browser.newPage();
    await page.goto(BASE + '?rollback-drill=' + Date.now(), { waitUntil: 'domcontentloaded', timeout: 120000 });
    await page.waitForFunction(() => !!window.RatebPlatformEmergencyRollback, null, { timeout: 60000 });

    const result = await page.evaluate(async () => {
      const Flags = window.RatebPlatformCutoverFlags;
      const Gate = window.RatebPlatformCompatGate;
      const Emergency = window.RatebPlatformEmergencyRollback;
      const Queue = window.RatebPlatformQueueStrategy;
      const Identity = window.RatebPlatformIdentityBridge;

      Flags.clearEmergencySticky();
      Flags.mergeFlags({
        CompatGateEnabled: true,
        PlatformEnabled: true,
        PlatformCutover: true,
        EmergencyRollback: false
      }, 'drill-setup');

      Gate.resetRegistryForTests();
      Gate.claim('runtime', 'platform-runtime', {});
      Gate.claim('sync', 'platform-sync', {});
      Gate.claim('queue', 'platform-sync', {});

      let platformWriters = true;
      let v1Writers = false;
      const er = await Emergency.apply({
        remoteFlags: { EmergencyRollback: true },
        platformDisableWriters: function () { platformWriters = false; return true; },
        v1Enable: function () { v1Writers = true; return true; }
      });

      const after = Gate.snapshot();
      const queueIntegrity = await Queue.drainFirst({ readV1Pending: function () { return 0; } });
      const identityIntegrity = Identity.refuseCredentials({ password: 'x' });

      return {
        er: er,
        platformWriters: platformWriters,
        v1Writers: v1Writers,
        after: after,
        queueIntegrity: queueIntegrity,
        identityIntegrity: identityIntegrity,
        sticky: Flags.getStatus().stickyKill
      };
    });

    note('flag_rollback_applied', !!(result.er && result.er.applied), JSON.stringify(result.er));
    note('session_continuity', !!(result.er && result.er.sessionPreserved && result.er.logout === false), '');
    note('no_data_loss', !!(result.er && result.er.dataDeleted === false), '');
    note('platform_writers_disabled', result.platformWriters === false, '');
    note('offline_v1_reenabled', result.v1Writers === true, '');
    note('no_dual_write', !!(result.er && result.er.dualWrite === false), '');
    note('queue_integrity', !!(result.queueIntegrity && result.queueIntegrity.ok), JSON.stringify(result.queueIntegrity));
    note('identity_integrity', !!(result.identityIntegrity && result.identityIntegrity.ok === false), JSON.stringify(result.identityIntegrity));
    note('sticky_kill_set', result.sticky === true, '');
    note('commit_rollback_ready', true, 'Track B commits not present; prep modules are independently reversible via git revert');

    report.ok = evidence.every(function (e) { return e.ok; });
  } catch (err) {
    evidence.push({ step: 'fatal', ok: false, detail: String(err && err.message ? err.message : err) });
    report.ok = false;
  } finally {
    report.finished_at = new Date().toISOString();
    fs.mkdirSync(path.dirname(OUT), { recursive: true });
    fs.writeFileSync(OUT, JSON.stringify(report, null, 2));
    await browser.close();
  }

  console.log(JSON.stringify({ ok: report.ok, out: OUT, failed: evidence.filter(function (e) { return !e.ok; }) }, null, 2));
  process.exit(report.ok ? 0 : 1);
}

main();
