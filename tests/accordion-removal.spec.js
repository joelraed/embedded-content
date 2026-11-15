const {test, expect} = require('@playwright/test');
const {login} = require('./helpers');
const panel = page => page.locator('#drupal-off-canvas');
const rows = page => panel(page).locator('[data-inside-plugin="accordion"] details');
async function titles(page) { return rows(page).locator('textarea[name$="[head]"]').evaluateAll(elements => elements.map(el => el.value)); }
async function fillRow(page, row, title) {
  await row.locator('textarea[name$="[head]"]').fill(title);
  const id = await row.locator('textarea[name$="[body][value]"]').getAttribute('id');
  await page.waitForFunction(id => [...Drupal.CKEditor5Instances.values()].some(ed=>ed.sourceElement.id===id),id);
  await page.evaluate(({id,title}) => [...Drupal.CKEditor5Instances.values()].find(ed=>ed.sourceElement.id===id).setData(`<p>Body for ${title}</p>`), {id,title});
}
async function remove(page, index, expected) {
  await rows(page).nth(index).getByRole('button',{name:'Remove item',exact:true}).click();
  await expect.poll(() => titles(page)).toEqual(expected);
}
test('custom accordion removes the selected item and preserves remaining rich text', async ({page}) => {
  const errors=[];page.on('pageerror',error=>errors.push(error.message));
  await login(page);
  await page.getByLabel('Text format',{exact:true}).selectOption('inside_embedded');
  await page.locator('.ck-button_inside-embedded-content__default').click();
  await panel(page).locator('[name="config[plugin_id]"]').selectOption('accordion');
  await expect(rows(page)).toHaveCount(1);
  for(let n=1;n<4;n++) {
    await panel(page).getByRole('button',{name:'Add item',exact:true}).click();
    await expect(rows(page)).toHaveCount(n+1);
  }
  for(const [index,title] of ['Alpha','Bravo','Charlie','Delta'].entries()) await fillRow(page,rows(page).nth(index),title);
  await remove(page,1,['Alpha','Charlie','Delta']);
  await remove(page,0,['Charlie','Delta']);
  await panel(page).getByRole('button',{name:'Add item',exact:true}).click();
  await expect(rows(page)).toHaveCount(3);
  await fillRow(page,rows(page).nth(2),'Echo');
  await remove(page,1,['Charlie','Echo']);
  // Last-item removal must also work after several rebuilds and another add.
  await panel(page).getByRole('button',{name:'Add item',exact:true}).click();
  await expect(rows(page)).toHaveCount(3);
  await fillRow(page,rows(page).nth(2),'Foxtrot');
  await remove(page,2,['Charlie','Echo']);
  await panel(page).getByRole('button',{name:'Embed',exact:true}).click();
  await expect(panel(page)).not.toBeVisible();
  const preview=page.locator('.inside-embedded-content-preview');
  await expect(preview).toContainText('Body for Charlie');
  await expect(preview).toContainText('Body for Echo');
  await expect(preview).not.toContainText('Body for Bravo');
  await expect(preview).not.toContainText('Body for Delta');
  await page.getByLabel('Title',{exact:true}).fill('Accordion item removal regression');
  await page.getByRole('button',{name:'Save',exact:true}).click();
  const nodeUrl=page.url();
  await expect(page.locator('article')).toContainText('Body for Charlie');
  await expect(page.locator('article')).toContainText('Body for Echo');
  await page.goto(nodeUrl+'/edit');
  await expect(page.locator('[data-inside-embedded-content-preview="ready"]')).toHaveCount(1);
  await page.locator('figure.ck-widget').first().click();
  const edit=page.locator('.ck-button_inside-embedded-content__edit');await edit.focus();await edit.press('Enter');
  await expect.poll(() => titles(page)).toEqual(['Charlie','Echo']);
  for(const [index,title] of ['Charlie','Echo'].entries()) {
    const id=await rows(page).nth(index).locator('textarea[name$="[body][value]"]').getAttribute('id');
    await expect.poll(()=>page.evaluate(id=>[...Drupal.CKEditor5Instances.values()].find(ed=>ed.sourceElement.id===id)?.getData(),id)).toContain(`Body for ${title}`);
  }
  // Saved content uses legacy numeric indexes: remove one after reopening too.
  await panel(page).getByRole('button',{name:'Add item',exact:true}).click();
  await expect(rows(page)).toHaveCount(3);
  await fillRow(page,rows(page).nth(2),'Golf');
  await remove(page,1,['Charlie','Golf']);
  await panel(page).getByRole('button',{name:'Embed',exact:true}).click();
  await expect(panel(page)).not.toBeVisible();
  await page.getByRole('button',{name:'Save',exact:true}).click();
  await expect(page.locator('article')).toContainText('Body for Charlie');
  await expect(page.locator('article')).toContainText('Body for Golf');
  await expect(page.locator('article')).not.toContainText('Body for Echo');
  expect(errors).toEqual([]);
});
