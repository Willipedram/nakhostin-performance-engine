# Performance Monitoring

## Scope and terminology

NPE's monitor is an opt-in, sampled diagnostic for **backend PHP generation**. It does not measure network latency or browser-observed Time to First Byte (TTFB), and the dashboard never labels PHP execution time as TTFB. Real-world TTFB additionally includes network, TLS, proxy, web-server, and transfer effects that an ordinary WordPress plugin cannot observe reliably.

## Collection model

Monitoring is disabled by default. When enabled, a configurable percentage of public GET requests is sampled. Administrative, AJAX, authenticated, and state-changing requests are excluded. Collection records request-to-shutdown backend generation, observed WordPress/plugin/theme phases, response generation, database query count, optional database query time, peak PHP memory, and NPE cache state.

NPE does not enable `SAVEQUERIES`, because doing so would materially increase production overhead. Database time can therefore be unavailable while query count remains available.

## Privacy and cardinality

Samples never contain URLs, query strings, request bodies, cookies, headers, user IDs, post IDs, SQL statements, passwords, tokens, nonces, or authorization data. Breakdown dimensions are bounded, sanitized identifiers: page type, template slug, component ID, and cache state. Component IDs are supplied through `npe/performance/components` and capped at twenty per sample.

## Storage and retention

Samples use the `npe_performance_samples` WordPress option. Retention is configurable from 1–90 days and capacity from 10–2,000 samples; defaults are seven days, 500 samples, and a ten-percent sample rate. Old samples are removed during writes. Data is removed at uninstall only when the existing remove-data preference is enabled.

## Dashboard

Performance Overview shows sample count, cache hit ratio, and average, median, minimum, and maximum values. Breakdowns are available for page type, template, component, and cache state. Empty or technically unavailable measurements are explicitly displayed as unavailable.

## Known limitations

- Timings are phase boundaries observable from a normal WordPress plugin, not a profiler trace.
- Sampled full-page hits run the shutdown recorder; sampling should remain low on high-traffic production sites.
- External cache layers may not expose their hit state to NPE, in which case cache state is `unknown`.
- Browser Navigation Timing and synthetic probes are intentionally outside this phase.
