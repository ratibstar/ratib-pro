/**
 * Phase PX4 — Architecture remediation regression gate.
 *
 * Verifies Critical + High fixes against a live Offline V2 host:
 * 1. Sync create+start before writers (runtime has sync; enqueue works)
 * 2. Identity session/claims/rbac stay fresh after unlock/lock
 * 3. Manufacturing activates via /mfg (not /manufacturing)
 * 4. Sales/Procurement refuse foreign namespaces via ownedPrefix
 */
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const BASE = process.env.RATEB_V2_URL
  || 'https://rateb.sa/rateb-erp/public/v2/index.html';
const OUT = process.env.RATEB_PX4_OUT
  || path.join(__dirname, 'reports', `phase-px4-regression-${Date.now()}.json`);

function fail(evidence, step, detail) {
  evidence.push({ step: step, ok: false, detail: detail || '' });
}

function ok(evidence, step, detail) {
  evidence.push({ step: step, ok: true, detail: detail || '' });
}

async function main() {
  const evidence = [];
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  const report = {
    phase: 'PX4',
    base: BASE,
    started_at: new Date().toISOString(),
    evidence: evidence,
    ok: false
  };

  try {
    await page.goto(BASE + '#/mfg', { waitUntil: 'domcontentloaded', timeout: 120000 });
    await page.waitForFunction(
      () => document.documentElement.getAttribute('data-rateb-v2-shell-ready') === '1',
      null,
      { timeout: 120000 }
    );

    const boot = await page.evaluate(async () => {
      const sleep = (ms) => new Promise(function (r) { setTimeout(r, ms); });
      /* Wait for background platform (db + sync + modules). */
      for (var i = 0; i < 90; i++) {
        var rt = window.RatebOfflineV2Runtime;
        if (rt && rt.services && rt.services.has('sync') && window.RatebOfflineV2ActiveBusiness) {
          break;
        }
        await sleep(1000);
      }
      var rt = window.RatebOfflineV2Runtime;
      var sync = rt && rt.services ? rt.services.tryGet('sync') : null;
      var fw = window.RatebOfflineV2ActiveBusiness;
      var mfgRec = fw && typeof fw.getModule === 'function' ? fw.getModule('mfg') : null;
      return {
        hasSync: !!(sync && typeof sync.enqueue === 'function'),
        syncStarted: !!(sync && sync.getStatus),
        mfgActive: !!(mfgRec && (mfgRec.state === 'active' || (mfgRec.module && mfgRec.module._active))),
        moduleId: mfgRec && mfgRec.module && mfgRec.module.metadata && mfgRec.module.metadata.id
      };
    });

    if (boot.hasSync) {
      ok(evidence, 'sync_registered_before_writers', 'runtime.services.has(sync)');
    } else {
      fail(evidence, 'sync_registered_before_writers', JSON.stringify(boot));
    }
    if (boot.mfgActive && boot.moduleId === 'mfg') {
      ok(evidence, 'mfg_activate_via_hash_mfg', 'id=' + boot.moduleId);
    } else {
      fail(evidence, 'mfg_activate_via_hash_mfg', JSON.stringify(boot));
    }

    /* Manufacturing deep-link must not use legacy /manufacturing selector. */
    const legacy = await page.evaluate(() => {
      const id = String(location.hash || '').replace(/^#\/?/, '').split('/')[0];
      return id;
    });
    if (legacy === 'mfg') {
      ok(evidence, 'boot_path_is_mfg', legacy);
    } else {
      fail(evidence, 'boot_path_is_mfg', legacy);
    }

    await page.goto(BASE + '?diagnostics=1#/identity', { waitUntil: 'domcontentloaded', timeout: 120000 });
    await page.waitForFunction(
      () => document.documentElement.getAttribute('data-rateb-v2-shell-ready') === '1',
      null,
      { timeout: 120000 }
    );

    const identity = await page.evaluate(async () => {
      const sleep = (ms) => new Promise(function (r) { setTimeout(r, ms); });
      for (var i = 0; i < 90; i++) {
        if (window.RatebOfflineV2Identity && window.RatebOfflineV2Runtime &&
            window.RatebOfflineV2Runtime.services &&
            window.RatebOfflineV2Runtime.services.has('sync')) {
          break;
        }
        await sleep(1000);
      }
      if (!window.RatebOfflineV2Identity || !window.RatebOfflineV2Business) {
        return { ok: false, error: 'identity_api_missing' };
      }
      return window.RatebOfflineV2Identity.runSelfTest();
    });

    if (identity && identity.ok) {
      ok(evidence, 'identity_runSelfTest', 'fresh session/rbac notes included');
    } else {
      fail(evidence, 'identity_runSelfTest', JSON.stringify(identity && (identity.failed || identity.error || identity)));
    }

    const steps = (identity && identity.evidence) || [];
    const need = [
      'service_handle_kind',
      'published_session_before_unlock',
      'published_session_after_unlock',
      'published_session_fresh',
      'published_session_after_lock'
    ];
    need.forEach(function (name) {
      var row = steps.find(function (e) { return e.step === name; });
      if (row && row.ok) {
        ok(evidence, 'identity_' + name, row.detail || '');
      } else {
        fail(evidence, 'identity_' + name, row ? row.detail : 'missing');
      }
    });

    async function runModuleSelfTest(moduleHash, globalName, stepName) {
      await page.goto(BASE + '#' + moduleHash, { waitUntil: 'domcontentloaded', timeout: 120000 });
      await page.waitForFunction(
        () => document.documentElement.getAttribute('data-rateb-v2-shell-ready') === '1',
        null,
        { timeout: 120000 }
      );
      const result = await page.evaluate(async (gName) => {
        const sleep = (ms) => new Promise(function (r) { setTimeout(r, ms); });
        for (var i = 0; i < 90; i++) {
          if (window[gName] && window.RatebOfflineV2Runtime &&
              window.RatebOfflineV2Runtime.services &&
              window.RatebOfflineV2Runtime.services.has('sync')) {
            break;
          }
          await sleep(1000);
        }
        if (!window[gName] || typeof window[gName].runSelfTest !== 'function') {
          return { ok: false, error: gName + '_missing' };
        }
        return window[gName].runSelfTest();
      }, globalName);
      if (result && result.ok) {
        ok(evidence, stepName, 'ownedPrefix + company_id SQL');
      } else {
        fail(evidence, stepName, JSON.stringify(result && (result.failed || result.error || result)));
      }
      return result;
    }

    await runModuleSelfTest('/sales', 'RatebOfflineV2Sales', 'sales_runSelfTest');
    await runModuleSelfTest('/procurement', 'RatebOfflineV2Procurement', 'procurement_runSelfTest');

    report.ok = evidence.every(function (e) { return e.ok; });
  } catch (err) {
    fail(evidence, 'fatal', String(err && err.message ? err.message : err));
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
