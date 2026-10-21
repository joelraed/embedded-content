const path = require('node:path');
const webpack = require('webpack');
const moduleRoot = path.resolve(__dirname, 'drupal/web/modules/custom/inside_embedded_content');
module.exports = {
  mode: 'production',
  entry: path.join(moduleRoot, 'js/ckeditor5_plugins/embeddedContent/src/index.js'),
  output: { path: path.join(moduleRoot, 'js/build'), filename: 'insideEmbeddedContent.js', library: ['CKEditor5', 'insideEmbeddedContent'], libraryTarget: 'umd', libraryExport: 'default' },
  plugins: [new webpack.DllReferencePlugin({ manifest: require('ckeditor5/build/ckeditor5-dll.manifest.json'), scope: 'ckeditor5/src', name: 'CKEditor5.dll' })],
};
