/**
 * Run paired Lighthouse trials or summarize their medians.
 * Install Lighthouse separately and set NPE_LIGHTHOUSE_BIN to its CLI path.
 */
import { spawnSync } from 'node:child_process';
import { mkdirSync, readFileSync, readdirSync } from 'node:fs';
import { join } from 'node:path';

const audits = {
  lcpMs: 'largest-contentful-paint',
  cls: 'cumulative-layout-shift',
  tbtMs: 'total-blocking-time',
  fcpMs: 'first-contentful-paint',
  speedIndexMs: 'speed-index',
};

function run(address, directory, count = '3') {
  const url = new URL(address);
  const cli = process.env.NPE_LIGHTHOUSE_BIN;
  const trials = Number(count);
  if (!directory || !cli || !['https:', 'http:'].includes(url.protocol) ||
      url.username || url.password || url.search || url.hash ||
      !Number.isInteger(trials) || trials < 3 || trials > 10) {
    throw new Error('Use a public page URL without a query or fragment, an output directory, and 3–10 trials.');
  }
  mkdirSync(directory);
  for (let index = 1; index <= trials; index++) {
    for (const profile of ['mobile', 'desktop']) {
      const output = join(directory, `${profile}-${index}.json`);
      const args = [
        cli, url.href, '--only-categories=performance',
        '--output=json', `--output-path=${output}`, '--quiet',
        '--chrome-flags=' + (process.env.NPE_LIGHTHOUSE_CHROME_FLAGS || '--headless'),
      ];
      if (profile === 'desktop') args.push('--preset=desktop');
      const trial = spawnSync(process.execPath, args, { stdio: 'inherit', timeout: 180_000 });
      if (trial.status !== 0) throw trial.error || new Error(`Lighthouse failed for ${profile} trial ${index}`);
      console.log(`Recorded ${profile} trial ${index}/${trials}`);
    }
  }
}

function summarize(directory) {
  const groups = { mobile: [], desktop: [] };
  for (const filename of readdirSync(directory).filter(name => /^(mobile|desktop)-\d+\.json$/.test(name))) {
    const report = JSON.parse(readFileSync(join(directory, filename), 'utf8'));
    const profile = report.configSettings?.formFactor;
    const score = report.categories?.performance?.score;
    if (report.runtimeError || !Object.hasOwn(groups, profile) || !Number.isFinite(score)) {
      throw new Error(`Incomplete Lighthouse report in ${filename}`);
    }
    const metrics = Object.fromEntries(Object.entries(audits).map(([key, id]) => [key, report.audits?.[id]?.numericValue]));
    if (Object.values(metrics).some(value => !Number.isFinite(value))) throw new Error(`Missing metric in ${filename}`);
    groups[profile].push({
      url: report.finalDisplayedUrl || report.finalUrl,
      version: report.lighthouseVersion,
      config: JSON.stringify(report.configSettings),
      values: { score: 100 * score, ...metrics },
    });
  }
  return Object.fromEntries(Object.entries(groups).map(([profile, runs]) => {
    if (runs.length < 3 || runs.some(run => run.url !== runs[0].url || run.version !== runs[0].version || run.config !== runs[0].config)) {
      throw new Error(`Expected at least three comparable ${profile} trials in ${directory}`);
    }
    const medians = Object.fromEntries(Object.keys(runs[0].values).map(metric => {
      const sorted = runs.map(run => run.values[metric]).sort((a, b) => a - b);
      const middle = Math.floor(sorted.length / 2);
      const median = sorted.length % 2 ? sorted[middle] : (sorted[middle - 1] + sorted[middle]) / 2;
      return [metric, Number(median.toFixed(3))];
    }));
    return [profile, { url: runs[0].url, version: runs[0].version, config: runs[0].config, runs: runs.length, medians }];
  }));
}

const [action, ...args] = process.argv.slice(2);
if (action === 'run') {
  run(...args);
} else if (action === 'compare' && args.length === 2) {
  const [before, after] = args.map(summarize);
  const rows = Object.entries(before).map(([profile, baseline]) => {
    const candidate = after[profile];
    if (baseline.url !== candidate.url || baseline.version !== candidate.version ||
        baseline.config !== candidate.config || baseline.runs !== candidate.runs) {
      throw new Error(`Mismatched ${profile} benchmark configuration`);
    }
    return { profile, runsPerPhase: baseline.runs, baseline: baseline.medians, candidate: candidate.medians };
  });
  console.log(JSON.stringify(rows, null, 2));
} else {
  throw new Error('Usage: run <url> <new-output-directory> [trials] | compare <baseline-directory> <candidate-directory>');
}
