// Manual two-phase test: run with ROUNDTRIP_BROWSER_PHASE=create, then verify.
const { test, expect } = require('@playwright/test');
const fs = require('node:fs');
const { login, editor } = require('./helpers');
const directory = '/tests/drupal/private/roundtrip';
test('contributed content survives the module switch', async ({ page }) => {
  if (process.env.ROUNDTRIP_BROWSER_PHASE === 'create') {
    await login(page);
    await page.getByLabel('Text format', { exact: true }).selectOption('legacy_roundtrip');
    await page.locator('.ck-button_embedded-content__default').waitFor();
    await page.waitForFunction(() => Drupal.CKEditor5Instances.size > 0);
    for (const plugin of ['example.call_to_action', 'example.counter', 'example.footnote']) {
      await editor(page, ed => {
        ed.model.change(writer => {
          const paragraph = writer.createElement('paragraph');
          writer.append(paragraph, ed.model.document.getRoot());
          writer.setSelection(paragraph, 0);
        });
      });
      await page.locator('.ck-button_embedded-content__default').click();
      const dialog = page.locator('.ui-dialog:visible');
      await Promise.all([
        page.waitForResponse(r => r.request().method() === 'POST' && r.url().includes('embedded-content')),
        dialog.locator('select[name="config[plugin_id]"]').selectOption(plugin),
      ]);
      if (plugin === 'example.call_to_action') {
        await dialog.locator('input[name="config[plugin_config][example.call_to_action][url]"]').fill('https://www.drupal.org');
        await dialog.locator('input[name="config[plugin_config][example.call_to_action][text]"]').fill('Legacy round-trip call to action');
      }
      if (plugin === 'example.counter') await dialog.locator('input[name="config[plugin_config][example.counter][start_at]"]').fill('17');
      await dialog.getByRole('button', { name: 'Embed', exact: true }).click();
      await expect(dialog).toHaveCount(0);
    }
    await expect(page.locator('figure.ck-widget')).toHaveCount(3);
    await page.getByLabel('Title', { exact: true }).fill('Contributed module round-trip test');
    await page.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(page.locator('article')).toContainText('Legacy round-trip call to action');
    const nid = page.url().match(/\/node\/(\d+)/)[1];
    fs.writeFileSync(`${directory}/browser-new.json`, JSON.stringify({ nid }));
    await page.locator('article').screenshot({ path: `${directory}/contrib-new.png` });
    await expect(page.locator('article a', { hasText: 'Legacy round-trip call to action' })).toHaveAttribute('style', /background:blue/);
  } else if (process.env.ROUNDTRIP_BROWSER_PHASE === 'verify') {
    const { nid } = JSON.parse(fs.readFileSync(`${directory}/browser-new.json`));
    await page.goto(`/node/${nid}`);
    await expect(page.locator('article')).toContainText('Legacy round-trip call to action');
    await expect(page.locator('article .counter')).toHaveCount(1);
    await page.locator('article').screenshot({ path: `${directory}/inside-new.png` });
    // Record the known visual mismatch explicitly; stored data is checked separately.
    await expect(page.locator('article .inside-call-to-action')).toHaveCount(1);
    await page.goto(`/node/${nid}/edit`);
    await expect(page.getByLabel('Text format', { exact: true })).toHaveValue('legacy_roundtrip');
    await expect(page.locator('.ck-button_inside-embedded-content__default')).toHaveCount(1);
    await expect(page.locator('figure.ck-widget')).toHaveCount(2);
    await expect(page.locator('.ck-editor__editable')).toContainText('Some footnote');
    await page.locator('figure.ck-widget').first().click();
    await page.locator('.ck-button_inside-embedded-content__edit').click();
    await expect(page.locator('input[name="config[plugin_config][example.call_to_action][text]"]')).toHaveValue('Legacy round-trip call to action');
    await page.goto('/node/27');
    const image = page.locator('article img').first();
    await expect(image).toBeVisible();
    await expect.poll(() => image.evaluate(el => el.naturalWidth)).toBeGreaterThan(0);
  } else test.skip(true, 'Requires an explicit manual round-trip phase.');
});
