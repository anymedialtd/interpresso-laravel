import { defineConfig } from '@playwright/test';
export default defineConfig({
    testDir: '.', testMatch: '*.case.js', workers: 1, retries: 0,
    reporter: 'json', outputDir: '../../../test-results/health-canaries',
    use: { browserName: 'chromium', baseURL: `http://127.0.0.1:${process.env.E2E_PORT ?? '8099'}` },
});
