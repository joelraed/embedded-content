const { test, expect } = require('@playwright/test');
const { login, editor } = require('./helpers');
const fs = require('node:fs');
const panel = page => page.locator('#drupal-off-canvas');
for (const plugin of ['message', 'accordion']) {
  test(`core Insert Media inside ${plugin}: insert, cancel, save and reopen`, async ({page}) => {
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await login(page);
    await page.getByLabel('Text format',{exact:true}).selectOption('inside_embedded');
    await page.locator('.ck-button_inside-embedded-content__default').click();
    await panel(page).locator('[name="config[plugin_id]"]').selectOption(plugin);
    await expect(panel(page).locator(`[data-inside-plugin="${plugin}"]`)).toBeVisible();
    if (plugin === 'message') {
      await panel(page).getByLabel('Message Type',{exact:false}).selectOption('info');
      await panel(page).getByLabel('Message Block Title',{exact:true}).fill('Message with core media embed');
    } else {
      await panel(page).getByLabel('Accordion Item Title',{exact:true}).fill('Accordion with core media embed');
      await panel(page).getByLabel('Text format',{exact:true}).selectOption('embed_content');
    }
    const body = panel(page).locator('textarea[name$="[body][value]"]');
    const bodyId = await body.getAttribute('id');
    await page.waitForFunction(id => [...Drupal.CKEditor5Instances.values()].some(ed => ed.sourceElement.id === id),bodyId);
    const nested = panel(page).locator('.ck-editor');
    await nested.locator('.ck-editor__editable').click();
    await nested.getByRole('button',{name:'Insert Media',exact:true}).click();
    const library = page.locator('#drupal-modal');
    const modal = page.locator('.ui-dialog').filter({has:library});
    await expect(library).toBeVisible();
    await expect(panel(page)).toBeVisible();
    await library.locator('.js-media-library-item input[type=checkbox]').first().check();
    await modal.getByRole('button',{name:'Insert selected',exact:true}).click();
    await expect(library).not.toBeVisible();
    await expect(panel(page)).toBeVisible();
    await expect(nested.locator('[data-drupal-media-preview="ready"]')).toHaveCount(1);
    await expect.poll(() => nested.locator('img').first().evaluate(el=>el.complete && el.naturalWidth>0)).toBeTruthy();
    const nestedData = await page.evaluate(id => [...Drupal.CKEditor5Instances.values()].find(ed=>ed.sourceElement.id===id).getData(),bodyId);
    expect(nestedData).toContain('<drupal-media');
    expect(nestedData).toContain('data-entity-uuid=');
    // Opening and cancelling another nested library must preserve the first embed.
    await nested.getByRole('button',{name:'Insert Media',exact:true}).click();
    await expect(library).toBeVisible();
    await modal.getByRole('button',{name:'Close',exact:true}).click();
    await expect(panel(page)).toBeVisible();
    expect(await page.evaluate(id => [...Drupal.CKEditor5Instances.values()].find(ed=>ed.sourceElement.id===id).getData(),bodyId)).toBe(nestedData);
    await panel(page).getByRole('button',{name:'Embed',exact:true}).click();
    await expect(panel(page)).not.toBeVisible();
    await expect(page.locator('[data-inside-embedded-content-preview="ready"]')).toHaveCount(1);
    await expect.poll(() => page.locator('.inside-embedded-content-preview img').first().evaluate(el=>el.complete && el.naturalWidth>0)).toBeTruthy();
    const outerData = await editor(page,ed=>ed.getData());
    expect(outerData).toContain('drupal-media');
    await page.getByLabel('Title',{exact:true}).fill(`Core Insert Media inside ${plugin}`);
    await page.getByRole('button',{name:'Save',exact:true}).click();
    const nodeUrl = page.url();
    await expect.poll(() => page.locator('article img').first().evaluate(el=>el.complete && el.naturalWidth>0)).toBeTruthy();
    await page.goto(nodeUrl+'/edit');
    await expect(page.locator('[data-inside-embedded-content-preview="ready"]')).toHaveCount(1);
    await page.locator('figure.ck-widget').first().click();
    const edit = page.locator('.ck-button_inside-embedded-content__edit');
    await edit.focus();
    await edit.press('Enter');
    await expect(panel(page).locator('[name="config[plugin_id]"]')).toHaveValue(plugin);
    await expect(panel(page).locator('[data-drupal-media-preview="ready"]')).toHaveCount(1);
    await expect.poll(() => panel(page).locator('.ck-editor__editable img').first().evaluate(el=>el.complete && el.naturalWidth>0)).toBeTruthy();
    const reopenedBodyId = await panel(page).locator('textarea[name$="[body][value]"]').getAttribute('id');
    expect(await page.evaluate(id => [...Drupal.CKEditor5Instances.values()].find(ed=>ed.sourceElement.id===id).getData(),reopenedBodyId)).toBe(nestedData);
    // Saving the reopened component preserves the embedded media through a second page save.
    await panel(page).getByRole('button',{name:'Embed',exact:true}).click();
    await expect(panel(page)).not.toBeVisible();
    await page.getByRole('button',{name:'Save',exact:true}).click();
    await expect.poll(() => page.locator('article img').first().evaluate(el=>el.complete && el.naturalWidth>0)).toBeTruthy();
    expect(errors).toEqual([]);
    fs.writeFileSync(`/tests/drupal/private/custom-plugins/core-media-${plugin}.json`,JSON.stringify({nodeUrl,nestedData}));
  });
}
