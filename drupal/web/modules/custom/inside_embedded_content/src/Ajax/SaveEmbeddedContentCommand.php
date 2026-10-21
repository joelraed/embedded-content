<?php

namespace Drupal\inside_embedded_content\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/** Delivers a save only to the editor and panel session that requested it. */
final class SaveEmbeddedContentCommand implements CommandInterface {
  public function __construct(private string $editorId, private string $requestId, private array $value) {}

  public function render() {
    return ['command' => 'insideEmbeddedContentSave', 'editorId' => $this->editorId, 'requestId' => $this->requestId, 'value' => $this->value];
  }
}
