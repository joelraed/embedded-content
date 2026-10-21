<?php
use Drupal\editor\Entity\Editor;
use Drupal\filter\Entity\FilterFormat;
$format = FilterFormat::load('inside_embedded') ?: FilterFormat::create([
  'format' => 'inside_embedded', 'name' => 'Inside Embedded Content',
  'filters' => [
    'filter_html' => ['id' => 'filter_html', 'status' => TRUE, 'weight' => -10, 'settings' => ['allowed_html' => '<br> <p> <h2> <h3> <strong> <em> <a href> <embedded-content data-plugin-id data-plugin-config data-button-id> <embedded-content-inline data-plugin-id data-plugin-config data-button-id>', 'filter_html_help' => FALSE, 'filter_html_nofollow' => FALSE]],
    'inside_embedded_content' => ['id' => 'inside_embedded_content', 'status' => TRUE, 'weight' => 100],
  ],
]);
$format->save();
$editor = Editor::load('inside_embedded') ?: Editor::create(['format' => 'inside_embedded', 'editor' => 'ckeditor5']);
$editor->setSettings(['toolbar' => ['items' => ['heading', 'bold', 'italic', 'link', 'insideEmbeddedContent__default', 'sourceEditing', 'undo', 'redo']], 'plugins' => ['ckeditor5_heading' => ['enabled_headings' => ['heading2', 'heading3']], 'ckeditor5_sourceEditing' => ['allowed_tags' => []]]]);
$editor->setImageUploadSettings(['status' => FALSE]);
$editor->save();
$violations = \Drupal::service('config.typed')->createFromNameAndData('editor.editor.inside_embedded', \Drupal::config('editor.editor.inside_embedded')->getRawData())->validate();
if (count($violations)) { throw new \RuntimeException((string) $violations); }
echo "Inside format and CKEditor configuration valid.\n";
$role = \Drupal\user\Entity\Role::load('inside_content_editor') ?: \Drupal\user\Entity\Role::create(['id' => 'inside_content_editor', 'label' => 'Inside content editor']);
foreach (['access content', 'create page content', 'edit own page content', 'use text format inside_embedded', 'use default inside embedded content button'] as $permission) {
  $role->grantPermission($permission);
}
$role->save();
