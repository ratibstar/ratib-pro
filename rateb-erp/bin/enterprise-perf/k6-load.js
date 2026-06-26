import http from 'k6/http';
import { check, sleep } from 'k6';
import { Trend } from 'k6/metrics';

const baseUrl = __ENV.RATEB_STAGING_URL || 'http://localhost/rateb-erp/public';
const responseTime = new Trend('custom_response_time');

export const options = {
  stages: [
    { duration: '30s', target: 50 },
    { duration: '1m', target: 100 },
    { duration: '30s', target: 0 },
  ],
  thresholds: {
    http_req_duration: ['p(95)<3000', 'p(99)<8000'],
    http_req_failed: ['rate<0.05'],
  },
};

export default function () {
  const url = `${baseUrl}/erp-health.php`;
  const res = http.get(url, { tags: { name: 'health' } });
  responseTime.add(res.timings.duration);
  check(res, {
    'status is 200': (r) => r.status === 200,
    'body has status ok': (r) => r.body && r.body.includes('"status":"ok"'),
  });
  sleep(0.3);
}

export function handleSummary(data) {
  const avg = data.metrics.http_req_duration?.values?.avg ?? 0;
  const med = data.metrics.http_req_duration?.values?.med ?? 0;
  const p90 = data.metrics.http_req_duration?.values?.['p(90)'] ?? 0;
  const p95 = data.metrics.http_req_duration?.values?.['p(95)'] ?? 0;
  const p99 = data.metrics.http_req_duration?.values?.['p(99)'] ?? 0;
  return {
    stdout: [
      'RATEB ERP k6 Summary (public health only)',
      `Average: ${avg.toFixed(2)} ms`,
      `Median: ${med.toFixed(2)} ms`,
      `P90: ${p90.toFixed(2)} ms`,
      `P95: ${p95.toFixed(2)} ms`,
      `P99: ${p99.toFixed(2)} ms`,
      '',
      JSON.stringify(data.metrics.http_req_duration?.values ?? {}, null, 2),
    ].join('\n'),
  };
}
