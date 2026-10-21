const { execFileSync } = require('node:child_process');
exports.login = async (page) => {
  await page.goto('/node/add/page');
  await page.locator('.ck-editor__editable').waitFor();
};
exports.editor = (page, fn, arg) => page.evaluate(({source, value}) => {
  const editor = [...Drupal.CKEditor5Instances.values()][0];
  return (new Function('editor', 'value', `return (${source})(editor, value);`))(editor, value);
}, { source: fn.toString(), value: arg });
