<?php
$format = \Drupal\filter\Entity\FilterFormat::load('embedded_baseline');
if ($format) {
  $format->setFilterConfig('embedded_content', ['status' => FALSE]);
  $format->save();
}
$editor = \Drupal\editor\Entity\Editor::load('embedded_baseline');
if ($editor) { $editor->delete(); }
