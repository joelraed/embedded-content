<?php
/** Enable core CKEditor media embedding in the custom plugin body format. */
use Drupal\editor\Entity\Editor;
use Drupal\filter\Entity\FilterFormat;
$format = FilterFormat::load('embed_content');
$editor = Editor::load('embed_content');
if (!$format || !$editor) { throw new \RuntimeException('Run custom-plugin-setup.php first.'); }
$directory = '/var/www/html/private/custom-plugins';
if (!is_dir($directory)) { mkdir($directory, 0770, TRUE); }
$backup = $directory . '/before-core-media-embed.json';
if (!file_exists($backup)) {
  file_put_contents($backup, json_encode(['format' => $format->toArray(), 'editor' => $editor->toArray()], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
}
$filters = $format->get('filters');
$tag = '<drupal-media data-entity-type data-entity-uuid data-view-mode data-align data-caption alt>';
if (!str_contains($filters['filter_html']['settings']['allowed_html'], '<drupal-media')) {
  $filters['filter_html']['settings']['allowed_html'] .= ' ' . $tag;
}
$filters['media_embed'] = [
  'id' => 'media_embed', 'provider' => 'media', 'status' => TRUE, 'weight' => 100,
  'settings' => ['default_view_mode' => 'default', 'allowed_view_modes' => [], 'allowed_media_types' => ['image' => 'image', 'remote_video' => 'remote_video']],
];
$format->set('filters', $filters)->save();
$settings = $editor->getSettings();
$original = json_decode(file_get_contents($backup), TRUE, 512, JSON_THROW_ON_ERROR);
$settings['toolbar']['items'] = array_merge(['drupalMedia'], array_values(array_filter($original['editor']['settings']['toolbar']['items'], fn($item) => $item !== 'drupalMedia')));
$settings['plugins']['media_media'] = ['allow_view_mode_override' => FALSE];
$editor->setSettings($settings)->save();
$role = \Drupal\user\Entity\Role::load('inside_content_editor');
if ($role) { $role->grantPermission('use text format embed_content')->save(); }
$violations = \Drupal::service('config.typed')->createFromNameAndData('editor.editor.embed_content', \Drupal::config('editor.editor.embed_content')->getRawData())->validate();
if (count($violations)) { throw new \RuntimeException((string) $violations); }
echo "Core Insert Media enabled; editor configuration valid.\n";
