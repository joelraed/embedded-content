<?php

namespace Drupal\inside_ckeditor5_embedded_content\Plugin\EmbeddedContent;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\inside_embedded_content\EmbeddedContentInterface;
use Drupal\inside_embedded_content\EmbeddedContentPluginBase;
use Drupal\media\Entity\Media;

/**
 * @EmbeddedContent(
 *   id = "video",
 *   label = @Translation("Video"),
 *   description = @Translation("Renders a video."),
 * )
 */
class Video extends EmbeddedContentPluginBase implements EmbeddedContentInterface {

  use StringTranslationTrait;

  public function isInline(): bool {
    return FALSE;
  }

  public function defaultConfiguration(): array {
    return ['yt_video' => NULL, 'ms_video' => NULL, 'alignment' => 'default', 'width' => '100'];
  }

  public function build(): array {
    $yvideo = $ytitle = $src = $mstitle = NULL;
    $youtube_id = $this->configuration['yt_video'] ?? NULL;
    if ($youtube_id && ($media = Media::load($youtube_id))) {
      $ytitle = $media->getName();
      $view = \Drupal::service('entity_type.manager')->getViewBuilder('media')->view($media, '100_width');
      $html = \Drupal::service('renderer')->renderRoot($view);
      $dom = new \DOMDocument();
      if (@$dom->loadHTML($html)) {
        $iframe = $dom->getElementsByTagName('iframe')->item(0);
        $yvideo = $iframe ? $dom->saveHTML($iframe) : NULL;
      }
    }

    $stream_id = $this->configuration['ms_video'] ?? NULL;
    if ($stream_id && ($media = Media::load($stream_id)) && $media->hasField('field_ms_stream_embed_iframe')) {
      $stream_url = $media->get('field_ms_stream_embed_iframe')->value;
      $src = preg_match('/<iframe[^>]+src="([^"]+)"/', $stream_url, $matches) ? $matches[1] : $stream_url;
      $mstitle = $media->getName();
    }

    return [
      '#theme' => 'inside_ckeditor5_embedded_content_video', '#yt_video' => $yvideo, '#yt_title' => $ytitle,
      '#ms_video' => $src, '#ms_title' => $mstitle, '#alignment' => $this->configuration['alignment'] ?? 'default',
      '#width' => $this->configuration['width'] ?? '100',
    ];
  }

  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['alignment'] = ['#type' => 'select', '#title' => $this->t('Alignment'), '#options' => ['default' => $this->t('Break text (Default)'), 'left' => $this->t('Align left and wrap text'), 'center' => $this->t('Align center and break text'), 'right' => $this->t('Align right and wrap text')], '#default_value' => $this->configuration['alignment'] ?? 'default', '#required' => TRUE];
    $form['width'] = ['#type' => 'select', '#title' => $this->t('Video Width'), '#options' => ['25' => $this->t('25% width'), '33' => $this->t('33% width'), '50' => $this->t('50% width'), '75' => $this->t('75% width'), '100' => $this->t('100% width (Default)')], '#default_value' => $this->configuration['width'] ?? '100', '#required' => TRUE];
    $form['yt_video'] = ['#type' => 'media_library', '#allowed_bundles' => ['remote_video'], '#title' => $this->t('Upload a YouTube Video'), '#default_value' => $this->configuration['yt_video'] ?? NULL, '#description' => $this->t('Select YouTube video from the media library.')];
    $form['ms_video'] = ['#type' => 'media_library', '#allowed_bundles' => ['ms_stream_sharepoint'], '#title' => $this->t('Upload a MS Stream Video'), '#default_value' => $this->configuration['ms_video'] ?? NULL, '#description' => $this->t('Select MS Stream video from the media library.')];
    return $form;
  }

}
