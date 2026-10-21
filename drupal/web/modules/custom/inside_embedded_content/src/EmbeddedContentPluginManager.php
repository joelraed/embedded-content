<?php

namespace Drupal\inside_embedded_content;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Embedded content plugin manager.
 */
class EmbeddedContentPluginManager extends DefaultPluginManager {

  /**
   * Constructs Inside Embedded Content Plugin Manager.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
          'Plugin/EmbeddedContent',
          $namespaces,
          $module_handler,
          'Drupal\inside_embedded_content\EmbeddedContentInterface',
          'Drupal\inside_embedded_content\Annotation\EmbeddedContent'
      );
    $this->alterInfo('inside_embedded_content_info');
    $this->setCacheBackend($cache_backend, 'inside_embedded_content_plugins');
  }

}
