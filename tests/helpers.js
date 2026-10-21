const { execFileSync } = require('node:child_process');
exports.login = async (page) => {
  const url = process.env.TEST_LOGIN_URL || execFileSync('docker', ['compose', 'exec', '-T', 'web', 'vendor/bin/drush', 'uli', '--uri=http://localhost:8088', '--no-browser'], { encoding: 'utf8' }).trim();
  await page.goto(url);
  await page.goto('/node/add/page');
  await page.locator('.ck-editor__editable').waitFor();
};
exports.editor = (page, fn, arg) => page.evaluate(({source, value}) => {
  const editor = [...Drupal.CKEditor5Instances.values()][0];
  return (new Function('editor', 'value', `return (${source})(editor, value);`))(editor, value);
}, { source: fn.toString(), value: arg });
