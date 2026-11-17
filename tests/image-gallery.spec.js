const {test,expect}=require('@playwright/test');
const {login,editor}=require('./helpers');
const fs=require('node:fs');
const panel=page=>page.locator('#drupal-off-canvas');
const items=page=>panel(page).locator('[data-inside-plugin="imagegallery"] details');
const caption=item=>item.locator('input[name$="[caption]"]');
async function chooseImage(page,item,upload=false,selectionIndex=0) {
  await item.getByRole('button',{name:'Add media',exact:true}).click();
  const library=page.locator('#drupal-modal');const modal=page.locator('.ui-dialog').filter({has:library});
  await expect(library).toBeVisible();await expect(panel(page)).toBeVisible();
  if(upload) {
    await library.locator('input[type=file]').setInputFiles('tests/fixtures/media-library.png');
    await library.getByLabel('Alternative text',{exact:false}).fill('Image Gallery upload test');
    await modal.getByRole('button',{name:'Save',exact:true}).click();
  } else await library.locator('.js-media-library-item input[type=checkbox]').nth(selectionIndex).check();
  await modal.getByRole('button',{name:'Insert selected',exact:true}).click();
  await expect(library).not.toBeVisible();await expect(item.locator('.media-library-item')).toHaveCount(1);
}
async function reopen(page) {
  await expect(page.locator('[data-inside-embedded-content-preview="ready"]')).toHaveCount(1);
  await page.locator('figure.ck-widget').first().click();
  const edit=page.locator('.ck-button_inside-embedded-content__edit');await edit.focus();await edit.press('Enter');
  await expect(panel(page)).toBeVisible();
}
test('Image Gallery: upload, select, remove middle item, save and reopen',async({page})=>{
  const errors=[];page.on('pageerror',error=>errors.push(error.message));
  await login(page);await page.getByLabel('Text format',{exact:true}).selectOption('inside_embedded');
  await page.locator('.ck-button_inside-embedded-content__default').click();
  await expect(panel(page).locator('option[value="imagegallery"]')).toHaveText('Image Gallery');
  await panel(page).locator('[name="config[plugin_id]"]').selectOption('imagegallery');
  await expect(items(page)).toHaveCount(1);
  await panel(page).getByRole('button',{name:'Embed',exact:true}).click();
  await expect(panel(page)).toContainText('Select an image you have permission to view.');
  await chooseImage(page,items(page).nth(0),true);await caption(items(page).nth(0)).fill('Gallery Alpha');
  await items(page).nth(0).locator('input[name$="[credit]"]').fill('Gallery credit');
  for(const title of ['Gallery Bravo','Gallery Charlie']) {
    const count=await items(page).count();await panel(page).getByRole('button',{name:'Add item',exact:true}).click();
    await expect(items(page)).toHaveCount(count+1);
    await chooseImage(page,items(page).nth(count),false,count);await caption(items(page).nth(count)).fill(title);
  }
  await items(page).nth(1).getByRole('button',{name:'Remove item',exact:true}).click();
  await expect(items(page)).toHaveCount(2);
  await expect(caption(items(page).nth(0))).toHaveValue('Gallery Alpha');
  await expect(caption(items(page).nth(1))).toHaveValue('Gallery Charlie');
  await expect(panel(page).locator('.media-library-item')).toHaveCount(2);
  // Cancel another selection without losing either selected image.
  await panel(page).getByRole('button',{name:'Add item',exact:true}).click();await expect(items(page)).toHaveCount(3);
  await items(page).nth(2).getByRole('button',{name:'Add media',exact:true}).click();
  await page.locator('.ui-dialog').filter({has:page.locator('#drupal-modal')}).getByRole('button',{name:'Close',exact:true}).click();
  await expect(panel(page)).toBeVisible();await expect(panel(page).locator('.media-library-item')).toHaveCount(2);
  await items(page).nth(2).getByRole('button',{name:'Remove item',exact:true}).click();await expect(items(page)).toHaveCount(2);
  // Remove and reselect a media value inside a surviving gallery item.
  await items(page).nth(1).locator('.media-library-item__remove').click();
  await expect(items(page).nth(1).locator('.media-library-item')).toHaveCount(0);
  await chooseImage(page,items(page).nth(1),false,2);
  await expect(caption(items(page).nth(1))).toHaveValue('Gallery Charlie');
  await panel(page).getByRole('button',{name:'Embed',exact:true}).click();await expect(panel(page)).not.toBeVisible();
  const config=await editor(page,ed=>JSON.parse(new DOMParser().parseFromString(ed.getData(),'text/html').querySelector('embedded-content').getAttribute('data-plugin-config')));
  expect(config.items.map(item=>item.caption)).toEqual(['Gallery Alpha','Gallery Charlie']);
  expect(config.items.every(item=>Number.isInteger(item.image)&&item.image>0)).toBeTruthy();
  expect(new Set(config.items.map(item=>item.image)).size).toBe(2);
  await page.getByLabel('Title',{exact:true}).fill('Image Gallery Media Library test');await page.getByRole('button',{name:'Save',exact:true}).click();
  const url=page.url();await expect(page.locator('article img')).toHaveCount(2);
  await expect.poll(()=>page.locator('article img').evaluateAll(images=>images.every(img=>img.complete&&img.naturalWidth>0))).toBeTruthy();
  await expect(page.locator('article')).toContainText('Gallery credit');
  await expect(page.locator('article')).not.toContainText('Gallery Bravo');
  await page.goto(url+'/edit');await reopen(page);await expect(items(page)).toHaveCount(2);
  await expect(panel(page).locator('.media-library-item')).toHaveCount(2);
  await expect(caption(items(page).nth(1))).toHaveValue('Gallery Charlie');
  await panel(page).getByRole('button',{name:'Embed',exact:true}).click();await expect(panel(page)).not.toBeVisible();
  await page.getByRole('button',{name:'Save',exact:true}).click();await expect(page.locator('article img')).toHaveCount(2);
  expect(errors).toEqual([]);
  fs.writeFileSync('/tests/drupal/private/custom-plugins/gallery-library-result.json',JSON.stringify({url,config}));
});
test('Image Gallery: legacy aliased path renders and reopens in the Media Library widget',async({page})=>{
  const {nid}=JSON.parse(fs.readFileSync('/tests/drupal/private/custom-plugins/gallery-legacy.json'));
  await page.goto(`/node/${nid}`);await expect(page.locator('article')).toContainText('Legacy gallery caption');
  await expect.poll(()=>page.locator('article img').evaluate(img=>img.complete&&img.naturalWidth>0)).toBeTruthy();
  await page.goto(`/node/${nid}/edit`);await reopen(page);
  await expect(items(page).first().locator('.media-library-item')).toHaveCount(1);
  await expect(caption(items(page).first())).toHaveValue('Legacy gallery caption');
  await panel(page).getByRole('button',{name:'Embed',exact:true}).click();await expect(panel(page)).not.toBeVisible();
  const config=await editor(page,ed=>JSON.parse(new DOMParser().parseFromString(ed.getData(),'text/html').querySelector('embedded-content').getAttribute('data-plugin-config')));
  expect(Number.isInteger(config.items[0].image)).toBeTruthy();
  await page.getByRole('button',{name:'Save',exact:true}).click();await expect(page.locator('article')).toContainText('Legacy gallery caption');
  await expect.poll(()=>page.locator('article img').evaluate(img=>img.complete&&img.naturalWidth>0)).toBeTruthy();
});
