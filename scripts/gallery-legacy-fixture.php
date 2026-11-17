<?php
/** Local test fixture: old gallery configuration using an aliased media path. */
use Drupal\Component\Utility\Html;
use Drupal\Component\Serialization\Json;
$ids = \Drupal::entityQuery('media')->accessCheck(FALSE)->condition('bundle', 'image')->range(0, 1)->execute();
$id = reset($ids);
if (!$id) { throw new \RuntimeException('Create an image media item first.'); }
$alias = '/media/gallery-legacy-image';
if (!\Drupal::entityQuery('path_alias')->accessCheck(FALSE)->condition('alias', $alias)->count()->execute()) {
  \Drupal\path_alias\Entity\PathAlias::create(['path' => '/media/' . $id, 'alias' => $alias])->save();
}
$config = ['aid' => 12345, 'width' => '50', 'items' => [['image' => $alias, 'caption' => 'Legacy gallery caption', 'credit' => 'Legacy credit', 'usecaption' => 0, 'usecredit' => 0, 'order' => 0]]];
$node = \Drupal\node\Entity\Node::create(['type' => 'page', 'title' => 'Legacy Image Gallery compatibility test', 'uid' => 1, 'status' => 1, 'body' => [
  'format' => 'inside_embedded', 'value' => '<embedded-content data-plugin-id="imagegallery" data-button-id="default" data-plugin-config="' . Html::escape(Json::encode($config)) . '"></embedded-content>',
]]);
$node->save();
$directory = '/var/www/html/private/custom-plugins';
if (!is_dir($directory)) { mkdir($directory, 0770, TRUE); }
file_put_contents($directory . '/gallery-legacy.json', Json::encode(['nid' => $node->id()]));
echo "Legacy gallery fixture: node {$node->id()}.\n";
