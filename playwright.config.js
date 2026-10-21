const { defineConfig } = require('@playwright/test');
module.exports = defineConfig({ testDir: './tests', workers: 1, timeout: 60000, use: { baseURL: process.env.TEST_BASE_URL || 'http://localhost:8088', headless: true, trace: 'retain-on-failure' } });
