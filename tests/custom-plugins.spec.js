const { test, expect } = require('@playwright/test');
const { login } = require('./helpers');
const fs = require('node:fs');
const panel = page => page.locator('#drupal-off-canvas');
const field = (page, plugin, key) => panel(page).locator(`[name="config[plugin_config][${plugin}][${key}]"]`);
async function richText(page, locator, html) {
  const id = await locator.getAttribute('id');
  await page.waitForFunction(id => [...Drupal.CKEditor5Instances.values()].some(ed => ed.sourceElement.id === id), id);
  await page.evaluate(({id,html}) => [...Drupal.CKEditor5Instances.values()].find(ed => ed.sourceElement.id === id).setData(html), {id,html});
}
for (const plugin of ['calltoaction', 'message', 'accordion', 'imagegallery', 'videogallery', 'video']) {
  test(`custom plugins: create, save and reopen ${plugin}`, async ({ page }) => {
    const fixtures = JSON.parse(fs.readFileSync('/tests/drupal/private/custom-plugins/fixtures.json'));
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await login(page);
    await page.getByLabel('Text format', {exact:true}).selectOption('inside_embedded');
    await page.locator('.ck-button_inside-embedded-content__default').click();
    await panel(page).locator('[name="config[plugin_id]"]').selectOption(plugin);
    await expect(panel(page).locator(`[data-inside-plugin="${plugin}"]`)).toBeVisible();
    if (plugin === 'calltoaction') {
      await field(page,plugin,'label').fill('Custom CTA test');
      await field(page,plugin,'type').selectOption('external');
      await field(page,plugin,'destination_uri').fill('https://www.drupal.org');
      await field(page,plugin,'tooltip').fill('Visit Drupal');
    } else if (plugin === 'message') {
      await field(page,plugin,'type').selectOption('info');
      await field(page,plugin,'title').fill('Custom message test');
      await richText(page,field(page,plugin,'body][value'),'<p>Message body preserved.</p>');
    } else if (plugin === 'accordion') {
      await field(page,plugin,'items][0][head').fill('First accordion item');
      await richText(page,field(page,plugin,'items][0][body][value'),'<p>Accordion body preserved.</p>');
      await panel(page).getByRole('button',{name:'Add item',exact:true}).click();
      await expect(field(page,plugin,'items][1][head')).toBeVisible();
      await expect(field(page,plugin,'items][0][head')).toHaveValue('First accordion item');
      await field(page,plugin,'items][1][head').fill('Second accordion item');
      await richText(page,field(page,plugin,'items][1][body][value'),'<p>Second body preserved.</p>');
    } else if (plugin === 'imagegallery') {
      await field(page,plugin,'width').selectOption('50');
      await field(page,plugin,'items][0][image').fill(`/media/${fixtures.image}`);
      await field(page,plugin,'items][0][caption').fill('Custom gallery caption');
      await field(page,plugin,'items][0][credit').fill('Gallery credit');
    } else if (plugin === 'videogallery') {
      await field(page,plugin,'width').selectOption('50');
      await field(page,plugin,'items][0][yt_video').fill(`/media/${fixtures.video}`);
    } else {
      await field(page,plugin,'width').selectOption('50');
      await panel(page).getByRole('button',{name:'Add media',exact:true}).first().click();
      const library = page.locator('#drupal-modal');
      await library.locator('.js-media-library-item input[type=checkbox]').first().check();
      await page.locator('.ui-dialog').filter({has:library}).getByRole('button',{name:'Insert selected',exact:true}).click();
      await expect(library).not.toBeVisible();
      await expect(panel(page).locator('.media-library-item')).toHaveCount(1);
    }
    await panel(page).getByRole('button',{name:'Embed',exact:true}).click();
    await expect(panel(page)).not.toBeVisible();
    await expect(page.locator('[data-inside-embedded-content-preview="ready"]')).toHaveCount(1);
    await expect(page.locator('.inside-embedded-content-preview')).not.toContainText('There is an issue');
    await page.getByLabel('Title',{exact:true}).fill(`Custom plugin: ${plugin}`);
    await page.getByRole('button',{name:'Save',exact:true}).click();
    const nodeUrl = page.url();
    expect(nodeUrl).toMatch(/\/node\/\d+$/);
    const article = page.locator('article');
    if (plugin === 'calltoaction') await expect(article.locator('a.button--embed')).toHaveAttribute('href','https://www.drupal.org');
    if (plugin === 'message') await expect(article).toContainText('Message body preserved.');
    if (plugin === 'accordion') {
      await expect(article).toContainText('First accordion item');
      await expect(article).toContainText('Second body preserved.');
    }
    if (plugin === 'imagegallery') {
      await expect(article).toContainText('Custom gallery caption');
      await expect.poll(() => article.locator('img').first().evaluate(el=>el.naturalWidth)).toBeGreaterThan(0);
    }
    if (plugin === 'video' || plugin === 'videogallery') await expect(article.locator('iframe')).toHaveCount(1);
    await page.goto(nodeUrl + '/edit');
    await expect(page.locator('[data-inside-embedded-content-preview="ready"]')).toHaveCount(1);
    await page.locator('figure.ck-widget').first().click();
    const edit = page.locator('.ck-button_inside-embedded-content__edit');
    await edit.focus();
    await edit.press('Enter');
    await expect(panel(page).locator('[name="config[plugin_id]"]')).toHaveValue(plugin);
    if (plugin === 'calltoaction') await expect(field(page,plugin,'label')).toHaveValue('Custom CTA test');
    if (plugin === 'message') await expect(field(page,plugin,'title')).toHaveValue('Custom message test');
    if (plugin === 'accordion') await expect(field(page,plugin,'items][1][head')).toHaveValue('Second accordion item');
    if (plugin === 'imagegallery') await expect(field(page,plugin,'items][0][caption')).toHaveValue('Custom gallery caption');
    if (plugin === 'videogallery') await expect(field(page,plugin,'items][0][yt_video')).toHaveValue(`/media/${fixtures.video}`);
    if (plugin === 'video') await expect(panel(page).locator('.media-library-item')).toHaveCount(1);
    expect(errors).toEqual([]);
    fs.mkdirSync('/tests/drupal/private/custom-plugins',{recursive:true});
    fs.writeFileSync(`/tests/drupal/private/custom-plugins/${plugin}.json`,JSON.stringify({nodeUrl}));
  });
}
