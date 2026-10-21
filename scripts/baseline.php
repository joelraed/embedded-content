<?php
use Drupal\editor\Entity\Editor;
use Drupal\filter\Entity\FilterFormat;
$type = \Drupal\node\Entity\NodeType::load('page');
if (!$type) {
  $type = \Drupal\node\Entity\NodeType::create(['type' => 'page', 'name' => 'Basic page']);
  $type->save();
}
if (!\Drupal\field\Entity\FieldStorageConfig::loadByName('node', 'body')) {
  \Drupal\field\Entity\FieldStorageConfig::create(['entity_type' => 'node', 'field_name' => 'body', 'type' => 'text_with_summary'])->save();
}
if (!\Drupal\field\Entity\FieldConfig::loadByName('node', 'page', 'body')) {
  \Drupal\field\Entity\FieldConfig::create(['entity_type' => 'node', 'bundle' => 'page', 'field_name' => 'body', 'label' => 'Body'])->save();
}
\Drupal::service('entity_display.repository')->getFormDisplay('node', 'page')->setComponent('body', ['type' => 'text_textarea_with_summary'])->save();
\Drupal::service('entity_display.repository')->getViewDisplay('node', 'page')->setComponent('body', ['type' => 'text_default'])->save();
$format = FilterFormat::load('embedded_baseline') ?: FilterFormat::create([
  'format' => 'embedded_baseline', 'name' => 'Embedded Content baseline',
  'filters' => ['embedded_content' => ['id' => 'embedded_content', 'status' => TRUE, 'weight' => 100]],
]);
$format->save();
$editor = Editor::load('embedded_baseline') ?: Editor::create(['format' => 'embedded_baseline', 'editor' => 'ckeditor5']);
$editor->setSettings(['toolbar' => ['items' => ['heading', 'bold', 'italic', 'link', 'embeddedContent__default', 'sourceEditing']], 'plugins' => ['ckeditor5_sourceEditing' => ['allowed_tags' => []]]]);
$editor->save();
$body = '<p>Baseline rendering and editing test.</p><embedded-content data-plugin-id="example.call_to_action" data-plugin-config="' . htmlspecialchars(json_encode(['url' => 'https://www.drupal.org', 'text' => 'Baseline Drupal link']), ENT_QUOTES) . '" data-button-id="default"></embedded-content>';
$node = \Drupal\node\Entity\Node::create(['type' => 'page', 'title' => 'Contributed Embedded Content baseline', 'body' => ['value' => $body, 'format' => 'embedded_baseline'], 'status' => 1]);
$node->save();
echo 'Baseline node: ' . $node->id() . PHP_EOL;
$build = ['#type' => 'processed_text', '#text' => $body, '#format' => 'embedded_baseline'];
$output = (string) \Drupal::service('renderer')->renderRoot($build);
if (!str_contains($output, 'Baseline Drupal link')) { throw new \RuntimeException('Baseline rendering failed'); }
echo "Baseline filter renders CTA successfully.\n";
