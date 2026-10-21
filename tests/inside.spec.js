const { test, expect } = require('@playwright/test');
const { login, editor } = require('./helpers');
const panel = page => page.locator('#drupal-off-canvas');
const field = (page, plugin, name) => panel(page).locator(`[name="config[plugin_config][${plugin}][${name}]"]`);
async function open(page, plugin) {
  await editor(page, ed => ed.editing.view.focus());
  await page.locator('.ck-button_inside-embedded-content__default').click();
  await expect(panel(page)).toBeVisible();
  if (plugin) {
    await panel(page).locator('[name="config[plugin_id]"]').selectOption(plugin);
    await expect(panel(page).locator(`[data-inside-plugin="${plugin}"]`)).toBeVisible();
  }
}
async function save(page) { await panel(page).getByRole('button', { name: 'Embed', exact: true }).click(); }
async function close(page) { await page.locator('.ui-dialog-off-canvas .ui-dialog-titlebar-close').click(); await expect(panel(page)).not.toBeVisible(); }
async function init(page) {
  await login(page);
  await page.getByLabel('Text format', { exact: true }).selectOption('inside_embedded');
  await page.locator('.ck-button_inside-embedded-content__default').waitFor();
}
test('inside: off-canvas insert, identity edit, cancel, save/reopen and render', async ({page}) => {
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  await init(page);
  await open(page, 'example.call_to_action');
  await expect(page.locator('.ui-widget-overlay')).toHaveCount(0);
  await expect(page.locator('.ck-editor__editable')).toBeVisible();
  await field(page, 'example.call_to_action', 'url').fill('https://www.drupal.org');
  await field(page, 'example.call_to_action', 'text').fill('Inside original');
  await save(page);
  await expect(page.locator('.inside-embedded-content-preview')).toContainText('Inside original');
  await page.locator('figure.ck-widget').click();
  await page.locator('.ck-button_inside-embedded-content__edit').click();
  await field(page, 'example.call_to_action', 'text').fill('Inside edited');
  // Move selection away while the tray is open; the original object remains target.
  await editor(page, ed => ed.model.change(w => { const p = w.createElement('paragraph'); w.append(p, ed.model.document.getRoot()); w.setSelection(p, 0); }));
  await save(page);
  await expect(page.locator('figure.ck-widget')).toHaveCount(1);
  await expect(page.locator('.inside-embedded-content-preview')).toContainText('Inside edited');
  const before = await editor(page, ed => ed.getData());
  await page.locator('figure.ck-widget').click();
  await page.locator('.ck-button_inside-embedded-content__edit').click();
  await field(page, 'example.call_to_action', 'text').fill('Cancelled');
  await close(page);
  expect(await editor(page, ed => ed.getData())).toBe(before);
  await page.getByLabel('Title', {exact:true}).fill('Inside browser lifecycle');
  await page.getByRole('button', {name:'Save',exact:true}).click();
  await expect(page.locator('article')).toContainText('Inside edited');
  await page.goto(page.url() + '/edit');
  await page.locator('figure.ck-widget').waitFor();
  await page.locator('figure.ck-widget').click();
  await page.locator('.ck-button_inside-embedded-content__edit').click();
  await expect(field(page, 'example.call_to_action', 'text')).toHaveValue('Inside edited');
  await close(page);
  expect(errors).toEqual([]);
});

test('inside: nested AJAX settings and validation', async ({page}) => {
  await init(page);
  await open(page, 'inside.notice');
  await field(page, 'inside.notice', 'title').fill('Hi');
  await save(page);
  await expect(panel(page)).toContainText('at least three characters');
  await field(page, 'inside.notice', 'title').fill('AJAX notice');
  await field(page, 'inside.notice', 'mode').selectOption('detailed');
  await field(page, 'inside.notice', 'details][message').fill('Nested value survives');
  await save(page);
  await expect(page.locator('.inside-embedded-content-preview')).toContainText('Nested value survives');
  await page.locator('figure.ck-widget').click();
  await page.locator('.ck-button_inside-embedded-content__edit').click();
  await expect(field(page, 'inside.notice', 'details][message')).toHaveValue('Nested value survives');
});

test('inside: accordion add/remove beyond two items retains nested values', async ({page}) => {
  await init(page);
  await open(page, 'inside.accordion');
  for (let i = 0; i < 4; i++) {
    await panel(page).getByLabel('Item title', {exact:true}).nth(i).fill(`Title ${i}`);
    await panel(page).getByLabel('Item body', {exact:true}).nth(i).fill(`Body ${i}`);
    if (i < 3) {
      await panel(page).getByRole('button',{name:'Add item',exact:true}).click();
      await expect(panel(page).getByLabel('Item title',{exact:true})).toHaveCount(i+2);
    }
  }
  await panel(page).getByRole('button',{name:'Remove item',exact:true}).nth(1).click();
  await expect(panel(page).getByLabel('Item title',{exact:true})).toHaveCount(3);
  await expect(panel(page).getByLabel('Item title',{exact:true}).nth(1)).toHaveValue('Title 2');
  await save(page);
  await expect(page.locator('.inside-embedded-content-preview')).toContainText('Title 3');
  await page.locator('figure.ck-widget').click();
  await page.locator('.ck-button_inside-embedded-content__edit').click();
  await expect(panel(page).getByLabel('Item title',{exact:true})).toHaveCount(3);
  await expect(panel(page).getByLabel('Item body',{exact:true}).nth(2)).toHaveValue('Body 3');
});

test('inside: multiple components, remove/undo, inline replacement and stale callbacks', async ({page}) => {
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  await init(page);
  await open(page, 'example.call_to_action');
  await field(page, 'example.call_to_action', 'url').fill('https://www.drupal.org');
  await field(page, 'example.call_to_action', 'text').fill('First component');
  await save(page);
  await expect(page.locator('figure.ck-widget')).toHaveCount(1);
  await editor(page, ed => ed.model.change(w => { const p = w.createElement('paragraph'); w.append(p, ed.model.document.getRoot()); w.setSelection(p, 0); }));
  await open(page, 'example.counter');
  await field(page, 'example.counter', 'start_at').fill('15');
  await save(page);
  await expect(page.locator('figure.ck-widget')).toHaveCount(2);
  await page.locator('figure.ck-widget').first().click();
  await page.locator('.ck-button_inside-embedded-content__remove').click();
  await expect(page.locator('figure.ck-widget')).toHaveCount(1);
  await editor(page, ed => ed.execute('undo'));
  await expect(page.locator('figure.ck-widget')).toHaveCount(2);
  await page.locator('figure.ck-widget').first().click();
  await page.locator('.ck-button_inside-embedded-content__edit').click();
  await panel(page).locator('[name="config[plugin_id]"]').selectOption('example.footnote');
  await expect(panel(page).locator('[data-inside-plugin="example.footnote"]')).toBeVisible();
  await save(page);
  await expect(page.locator('.inside-embedded-content-preview-wrapper')).toHaveCount(2);
  await expect.poll(() => editor(page, ed => ed.getData())).toContain('<embedded-content-inline');

  await editor(page, ed => ed.model.change(w => { const p = w.createElement('paragraph'); w.append(p, ed.model.document.getRoot()); w.setSelection(p, 0); }));
  await open(page, 'example.counter');
  const oldId = await panel(page).locator('[name="request_id"]').inputValue();
  const beforeCancel = await editor(page, ed => ed.getData());
  await close(page);
  await editor(page, (ed, id) => new Drupal.AjaxCommands().insideEmbeddedContentSave({}, {editorId:ed.id, requestId:id, value:{element:'insideEmbeddedContent',attributes:{'data-plugin-id':'example.counter','data-plugin-config':'{}','data-button-id':'default'}}}), oldId);
  expect(await editor(page, ed => ed.getData())).toBe(beforeCancel);
  await open(page, 'example.counter');
  const before = await editor(page, ed => ed.getData());
  await editor(page, (ed, id) => new Drupal.AjaxCommands().insideEmbeddedContentSave({}, {
    editorId: ed.id, requestId: id, value: { element:'insideEmbeddedContent', attributes:{'data-plugin-id':'example.counter','data-plugin-config':'{"start_at":999}','data-button-id':'default'} }
  }), oldId);
  expect(await editor(page, ed => ed.getData())).toBe(before);
  await expect(panel(page)).toBeVisible();
  await close(page);
  expect(errors).toEqual([]);
});

test('inside: close/reopen keyboard focus, nested Drupal dialog, CSRF access', async ({page}) => {
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  await init(page);
  for (let i=0;i<3;i++) {
    await open(page, 'example.call_to_action');
    await expect(panel(page).locator(':focus')).toHaveCount(1);
    await field(page, 'example.call_to_action', 'text').focus();
    await page.keyboard.press('Escape');
    await expect(panel(page)).not.toBeVisible();
    await expect(page.locator('.ck-editor__editable')).toBeFocused();
  }
  await open(page, 'example.call_to_action');
  await page.evaluate(() => {
    const element = document.createElement('div');
    element.id = 'inside-nested-dialog-test';
    element.innerHTML = '<label>Nested input <input></label>';
    document.body.append(element);
    window.insideNestedTestDialog = Drupal.dialog(element, {title: 'Nested Drupal dialog'});
    window.insideNestedTestDialog.showModal();
  });
  await expect(page.locator('#inside-nested-dialog-test')).toBeVisible();
  await page.evaluate(() => window.insideNestedTestDialog.close());
  await expect(panel(page)).toBeVisible();
  await field(page, 'example.call_to_action', 'url').fill('https://www.drupal.org');
  await field(page, 'example.call_to_action', 'text').fill('After nested dialog');
  await save(page);
  await expect(page.locator('.inside-embedded-content-preview')).toContainText('After nested dialog');
  expect((await page.request.post('/inside-embedded-content/dialog/default/inside_embedded')).status()).toBe(403);
  expect((await page.request.post('/inside-embedded-content/preview/inside_embedded', {data:{}})).status()).toBe(403);
  expect(errors).toEqual([]);
});

test('inside: styled list nested CKEditors retain values across AJAX rebuilds', async ({page}) => {
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  await init(page);
  await open(page, 'example.styled_list');
  for (let i=0;i<3;i++) {
    await expect(panel(page).locator('.ck-editor__editable')).toHaveCount(i+1);
    await panel(page).locator('.ck-editor__editable').nth(i).click();
    await page.keyboard.type(`Nested item ${i}`);
    if (i<2) {
      await panel(page).getByRole('button', {name:'Add item',exact:true}).click();
    }
  }
  await panel(page).getByRole('button', {name:'Remove item',exact:true}).nth(1).click();
  await expect(panel(page).locator('.ck-editor__editable')).toHaveCount(2);
  await expect(panel(page).locator('.ck-editor__editable').nth(1)).toContainText('Nested item 2');
  await save(page);
  await expect(page.locator('.inside-embedded-content-preview')).toContainText('Nested item 0');
  await expect(page.locator('.inside-embedded-content-preview')).toContainText('Nested item 2');
  await page.locator('figure.ck-widget').click();
  await page.locator('.ck-button_inside-embedded-content__edit').click();
  await expect(panel(page).locator('.ck-editor__editable')).toHaveCount(2);
  await expect(panel(page).locator('.ck-editor__editable').nth(1)).toContainText('Nested item 2');
  await close(page);
  expect(errors).toEqual([]);
});

test('inside: stale document and target changes reject save; preview behaviors work', async ({page}) => {
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  await init(page);
  await open(page, 'example.counter');
  await field(page, 'example.counter', 'start_at').fill('20');
  await editor(page, ed => ed.model.change(w => w.insertText('New editor change', ed.model.document.getRoot().getChild(0), 0)));
  await save(page);
  await expect(page.locator('figure.ck-widget')).toHaveCount(0);
  await expect(page.locator('#drupal-live-announce')).toContainText('document changed');
  await close(page);
  await open(page, 'example.counter');
  await field(page, 'example.counter', 'start_at').fill('20');
  await save(page);
  await expect(page.locator('.inside-counter')).toBeVisible();
  await expect.poll(async () => Number(await page.locator('.inside-counter').textContent())).toBeGreaterThan(20);
  await page.locator('figure.ck-widget').click();
  await page.locator('.ck-button_inside-embedded-content__edit').click();
  await field(page, 'example.counter', 'start_at').fill('50');
  await editor(page, ed => ed.model.change(w => {
    const target = [...ed.model.document.getRoot().getChildren()].find(child => child.name==='insideEmbeddedContent');
    w.setAttribute('dataPluginConfig', '{"start_at":30}', target);
  }));
  await save(page);
  await expect(page.locator('#drupal-live-announce')).toContainText('component changed');
  expect(await editor(page, ed => ed.getData())).toContain('30');
  await close(page);
  await expect(page.locator('figure.ck-widget')).toHaveCount(1);
  expect(errors).toEqual([]);
});
