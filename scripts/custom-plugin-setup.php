<?php
/** Local prerequisites for the supplied custom plugin module. */
use Drupal\filter\Entity\FilterFormat;
use Drupal\editor\Entity\Editor;
use Drupal\linkit\Entity\Profile;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
if (getenv('DELETE_TEST_PAGES') === 'yes') {
  $ids = \Drupal::entityQuery('node')->accessCheck(FALSE)->execute();
  \Drupal::entityTypeManager()->getStorage('node')->delete(Node::loadMultiple($ids));
  echo 'Deleted ' . count($ids) . " old test pages. Media retained for plugin tests.\n";
}
if (!FilterFormat::load('embed_content')) {
  $format = FilterFormat::load('basic_html')->createDuplicate();
  $format->set('format', 'embed_content')->set('name', 'Embedded component body')->save();
  $editor = Editor::load('basic_html')->createDuplicate();
  $editor->set('format', 'embed_content')->save();
}
if (!Profile::load('admin')) {
  $profile = Profile::load('default')->createDuplicate();
  $profile->set('id', 'admin')->set('label', 'Component links')->save();
}
// The supplied plugin expects this site-specific bundle and iframe field.
if (!\Drupal\media\Entity\MediaType::load('ms_stream_sharepoint')) {
  $type = \Drupal\media\Entity\MediaType::load('remote_video')->createDuplicate();
  $type->set('id', 'ms_stream_sharepoint')->set('label', 'MS Stream / SharePoint')->set('field_map', [])->save();
  \Drupal\field\Entity\FieldConfig::create([
    'field_storage' => \Drupal\field\Entity\FieldStorageConfig::loadByName('media', 'field_media_oembed_video'),
    'bundle' => 'ms_stream_sharepoint', 'label' => 'Remote video URL',
  ])->save();
  $storage = \Drupal\field\Entity\FieldStorageConfig::loadByName('media', 'field_ms_stream_embed_iframe') ?: \Drupal\field\Entity\FieldStorageConfig::create([
    'field_name' => 'field_ms_stream_embed_iframe', 'entity_type' => 'media', 'type' => 'string_long',
  ]);
  $storage->save();
  \Drupal\field\Entity\FieldConfig::create(['field_storage' => $storage, 'bundle' => 'ms_stream_sharepoint', 'label' => 'MS Stream iframe'])->save();
}
$ids = \Drupal::entityQuery('media')->accessCheck(FALSE)->condition('bundle', 'remote_video')->execute();
if (!$ids) {
  $media = Media::create(['bundle' => 'remote_video', 'name' => 'Drupal video test', 'status' => 1, 'uid' => 1, 'field_media_oembed_video' => 'https://www.youtube.com/watch?v=aqz-KE-bpKQ']);
  $media->save();
  echo "Created remote video fixture {$media->id()}.\n";
}
$images = \Drupal::entityQuery('media')->accessCheck(FALSE)->condition('bundle', 'image')->sort('mid')->execute();
$videos = \Drupal::entityQuery('media')->accessCheck(FALSE)->condition('bundle', 'remote_video')->sort('mid')->execute();
$directory = '/var/www/html/private/custom-plugins';
if (!is_dir($directory)) { mkdir($directory, 0770, TRUE); }
file_put_contents($directory . '/fixtures.json', json_encode(['image' => (int) reset($images), 'video' => (int) reset($videos)], JSON_THROW_ON_ERROR));
echo "Custom plugin prerequisites ready.\n";
