<?php

namespace Drupal\inside_embedded_content\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\editor\Entity\Editor;
use Drupal\inside_embedded_content\EmbeddedContentPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Returns the preview for embedded content.
 */
class EmbeddedContentPreviewController extends ControllerBase {

  use StringTranslationTrait;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * The embedded content plugin manager.
   *
   * @var \Drupal\inside_embedded_content\EmbeddedContentPluginManager
   */
  protected $insideEmbeddedContentPluginManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
          $container->get('plugin.manager.inside_embedded_content'),
          $container->get('renderer')
      );
  }

  /**
   * The controller constructor.
   *
   * @param \Drupal\inside_embedded_content\EmbeddedContentPluginManager $inside_embedded_content_plugin_manager
   *   The embedded content plugin manager.
   * @param \Drupal\Core\Render\Renderer $renderer
   *   The renderer service.
   */
  public function __construct(EmbeddedContentPluginManager $inside_embedded_content_plugin_manager, Renderer $renderer) {
    $this->insideEmbeddedContentPluginManager = $inside_embedded_content_plugin_manager;
    $this->renderer = $renderer;
  }

  /**
   * Controller callback that renders the preview for CKeditor.
   */
  public function preview(Request $request) {
    $content = Json::decode($request->getContent());
    $plugin_id = $content['plugin_id'] ?? NULL;
    try {
      if (!$plugin_id) {
        throw new \Exception();
      }
      $plugin_config = ($plugin_config = $content['plugin_config']) ? Xss::filter($plugin_config) : '';
      $plugin_id = Xss::filter($plugin_id);

      /**
       * @var \Drupal\inside_embedded_content\EmbeddedContentInterface $instance
       */
      $instance = $this->insideEmbeddedContentPluginManager->createInstance($plugin_id, ($plugin_config ? Json::decode($plugin_config) : []) ?? []);
      $build = $instance->build();
    }
    catch (\Exception $e) {
      $build = [
        'markup' => [
          '#type' => 'markup',
          '#markup' => $this->t('Incorrect configuration. Please recreate this embedded content.'),
        ],
      ];
    }
    return new Response($this->renderer->renderRoot($build));
  }

  /**
   * Access callback for viewing the preview.
   *
   * @param \Drupal\Core\Session\AccountProxy $account
   *   The current user.
   * @param \Drupal\editor\Entity\Editor $editor
   *   The editor.
   *
   * @return \Drupal\Core\Access\AccessResult|\Drupal\Core\Access\AccessResultReasonInterface
   *   The acccess result.
   */
  public function checkAccess(AccountProxy $account, Editor $editor) {
    return AccessResult::allowedIfHasPermission($account, 'use text format ' . $editor->getFilterFormat()->id());
  }

}
