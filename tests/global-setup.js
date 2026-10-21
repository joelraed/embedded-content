const { chromium } = require('@playwright/test');
const { execFileSync } = require('node:child_process');
module.exports = async () => {
  const url = process.env.TEST_LOGIN_URL || execFileSync('docker', ['compose', 'exec', '-T', 'web', 'vendor/bin/drush', 'uli', '--uri=http://localhost:8088', '--no-browser'], {encoding:'utf8'}).trim();
  const browser = await chromium.launch();
  const page = await browser.newPage();
  await page.goto(url);
  await page.context().storageState({path:'test-results/auth.json'});
  await browser.close();
};
