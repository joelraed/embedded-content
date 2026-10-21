<?php
/** Local, backed-up module round-trip test; never rewrites existing node bodies. */
use Drupal\Component\Utility\Html;
use Drupal\editor\Entity\Editor;
use Drupal\filter\Entity\FilterFormat;
use Drupal\node\Entity\Node;

$stage = getenv('ROUNDTRIP_STAGE') ?: 'capture';
$GLOBALS['directory'] = $directory = '/var/www/html/private/roundtrip';
if (!is_dir($directory)) { mkdir($directory, 0770, TRUE); }
function rtWrite($name, $data) {
  global $directory;
  file_put_contents($directory . '/' . $name . '.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}
function rtRead($name) {
  global $directory;
  return json_decode(file_get_contents($directory . '/' . $name . '.json'), TRUE);
}
function rtSnapshot($name) {
  $snapshot = [];
  $ids = \Drupal::entityQuery('node')->accessCheck(FALSE)->execute();
  foreach (Node::loadMultiple($ids) as $node) {
    foreach ($node->getTranslationLanguages() as $langcode => $language) {
      $translation = $node->getTranslation($langcode);
      foreach ($translation->getFields() as $field_name => $field) {
        if (!in_array($field->getFieldDefinition()->getType(), ['text', 'text_long', 'text_with_summary'], TRUE)) { continue; }
        foreach ($field as $delta => $item) {
          $value = $item->getValue();
          $document = Html::load($value['value'] ?? '');
          $plugins = [];
          foreach ((new \DOMXPath($document))->query('//embedded-content|//embedded-content-inline') as $embed) { $plugins[] = $embed->getAttribute('data-plugin-id'); }
          try {
            $build = ['#type' => 'processed_text', '#text' => $value['value'] ?? '', '#format' => $value['format'] ?? NULL, '#langcode' => $langcode];
            $html = (string) \Drupal::service('renderer')->renderRoot($build);
          } catch (\Throwable $error) { $html = 'RENDER ERROR: ' . $error->getMessage(); }
          $snapshot[$node->id() . ':' . $langcode . ':' . $field_name . ':' . $delta] = [
            'nid' => (int) $node->id(), 'title' => $node->label(), 'plugins' => $plugins,
            'stored_hash' => hash('sha256', json_encode($value)), 'stored' => $value,
            'rendered' => $html,
          ];
        }
      }
    }
  }
  rtWrite($name, $snapshot);
  echo $name . ': captured ' . count($snapshot) . " formatted text values.\n";
}
function rtNeutralize($provider) {
  foreach (FilterFormat::loadMultiple() as $format) {
    $filters = $format->get('filters');
    if (isset($filters[$provider])) { unset($filters[$provider]); $format->set('filters', $filters)->save(); }
  }
  foreach (Editor::loadMultiple() as $editor) {
    $settings = $editor->getSettings();
    $prefix = $provider === 'inside_embedded_content' ? 'insideEmbeddedContent__' : 'embeddedContent__';
    $settings['toolbar']['items'] = array_values(array_filter($settings['toolbar']['items'] ?? [], fn($id) => !str_starts_with($id, $prefix)));
    $editor->setSettings($settings)->save();
  }
}
function rtApply($formats, $editors, $from, $to) {
  foreach ($formats as $id => $data) {
    if (isset($data['filters'][$from])) { $filter = $data['filters'][$from]; unset($data['filters'][$from]); $filter['id'] = $to; unset($filter['provider']); $data['filters'][$to] = $filter; }
    $format = FilterFormat::load($id) ?: FilterFormat::create(['format' => $id]);
    foreach ($data as $key => $value) { if ($key !== 'dependencies' && $key !== '_core') { $format->set($key, $value); } }
    $format->save();
  }
  foreach ($editors as $id => $data) {
    $settings = $data['settings'];
    $old = $from === 'inside_embedded_content' ? 'insideEmbeddedContent__' : 'embeddedContent__';
    $new = $to === 'inside_embedded_content' ? 'insideEmbeddedContent__' : 'embeddedContent__';
    $settings['toolbar']['items'] = array_map(fn($item) => str_replace($old, $new, $item), $settings['toolbar']['items'] ?? []);
    $editor = Editor::load($id) ?: Editor::create(['format' => $id, 'editor' => $data['editor']]);
    $editor->setSettings($settings)->setImageUploadSettings($data['image_upload'] ?? ['status' => FALSE])->save();
  }
}
if ($stage === 'capture') {
  if (file_exists($directory . '/original-config.json')) { throw new \RuntimeException('Existing round-trip snapshot found. Archive it before starting another run.'); }
  $storage = \Drupal::service('config.storage');
  $config = ['formats' => [], 'editors' => [], 'buttons' => [], 'roles' => []];
  foreach ($storage->listAll('filter.format.') as $name) { $data = $storage->read($name); $config['formats'][$data['format']] = $data; }
  foreach ($storage->listAll('editor.editor.') as $name) { $data = $storage->read($name); $config['editors'][$data['format']] = $data; }
  foreach ($storage->listAll('inside_embedded_content.button.') as $name) { $config['buttons'][$name] = $storage->read($name); }
  foreach ($storage->listAll('user.role.') as $name) { $config['roles'][$name] = $storage->read($name); }
  rtWrite('original-config', $config);
  rtSnapshot('inside-before');
} elseif ($stage === 'neutral-inside') {
  rtNeutralize('inside_embedded_content');
} elseif ($stage === 'contrib-config') {
  $config = rtRead('original-config');
  rtApply($config['formats'], $config['editors'], 'inside_embedded_content', 'embedded_content');
  $format = FilterFormat::create([
    'format' => 'legacy_roundtrip', 'name' => 'Contributed round-trip test',
    'filters' => ['embedded_content' => ['id' => 'embedded_content', 'status' => TRUE, 'weight' => 100]],
  ]);
  $format->save();
  $editor = Editor::create(['format' => 'legacy_roundtrip', 'editor' => 'ckeditor5', 'image_upload' => ['status' => FALSE], 'settings' => [
    'toolbar' => ['items' => ['bold', 'italic', 'link', 'embeddedContent__default', 'sourceEditing']],
    'plugins' => ['ckeditor5_sourceEditing' => ['allowed_tags' => []]],
  ]]);
  $editor->save();
  rtWrite('legacy-config', ['formats' => ['legacy_roundtrip' => \Drupal::config('filter.format.legacy_roundtrip')->getRawData()], 'editors' => ['legacy_roundtrip' => \Drupal::config('editor.editor.legacy_roundtrip')->getRawData()]]);
  rtSnapshot('contrib-existing');
} elseif ($stage === 'contrib-capture') {
  rtSnapshot('contrib-with-new');
} elseif ($stage === 'neutral-contrib') {
  rtNeutralize('embedded_content');
} elseif ($stage === 'restore-inside') {
  $config = rtRead('original-config');
  rtApply($config['formats'], $config['editors'], 'inside_embedded_content', 'inside_embedded_content');
  foreach ($config['buttons'] + $config['roles'] as $name => $data) { \Drupal::service('config.factory')->getEditable($name)->setData($data)->save(); }
  $legacy = rtRead('legacy-config');
  rtApply($legacy['formats'], $legacy['editors'], 'embedded_content', 'inside_embedded_content');
  echo "Original configuration restored; new contrib format mapped to Inside without changing node bodies.\n";
} elseif ($stage === 'final-capture') {
  rtSnapshot('inside-after');
} else { throw new \InvalidArgumentException('Unknown stage'); }
