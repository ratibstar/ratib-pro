/**
 * RATEB ERP Phase 6 — Performance benchmark (staging only).
 *
 * Prerequisites: k6 (https://k6.io), Apache Bench (ab), staging URL.
 *
 * Quick start:
 *   export RATEB_STAGING_URL=https://staging.example.com/rateb-erp/public
 *   k6 run bin/enterprise-perf/k6-load.js
 *   ab -n 1000 -c 100 ${RATEB_STAGING_URL}/erp-health.php?probe=ping
 *
 * MySQL analysis (on staging DB):
 *   SET GLOBAL slow_query_log = 1;
 *   SET GLOBAL long_query_time = 1;
 *   -- run load test, then inspect slow query log
 *   EXPLAIN ANALYZE SELECT ... ;
 *
 * Load tiers to test: 100, 250, 500, 1000 concurrent users (VUs in k6).
 */
