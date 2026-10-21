<?php
/** Integration assertions against the bootstrapped, installed custom module. */
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\filter\Entity\FilterFormat;
use Drupal\inside_embedded_content\Entity\EmbeddedContentButton;
use Drupal\inside_embedded_content\Form\EmbeddedContentDialogForm;
function verify($condition, string $message): void {
  if (!$condition) { throw new \RuntimeException($message); }
  echo "PASS: $message\n";
}
verify(!\Drupal::moduleHandler()->moduleExists('embedded_content'), 'contributed implementation disabled');
$manager = \Drupal::service('plugin.manager.inside_embedded_content');
verify(count($manager->getDefinitions()) === 6, 'six compatible example plugins discovered');
$filter = \Drupal::service('plugin.manager.filter')->createInstance('inside_embedded_content');
$config = ['items' => ['one' => ['title' => '<script>escaped</script>', 'body' => 'First & second'], 'two' => ['title' => 'Second item', 'body' => 'Nested retained']]];
$markup = '<embedded-content data-plugin-id="inside.accordion" data-plugin-config="' . Html::escape(Json::encode($config)) . '"></embedded-content>';
$context = new RenderContext();
$result = \Drupal::service('renderer')->executeInRenderContext($context, fn() => $filter->process($markup, 'en'));
$html = (string) $result;
verify(str_contains($html, '&lt;script&gt;escaped&lt;/script&gt;'), 'plugin output escapes user text');
verify(str_contains($html, 'Second item') && str_contains($html, 'Nested retained'), 'nested configuration renders all accordion items');
verify(!str_contains($html, '<body>') && !str_contains($html, '<embedded-content'), 'filter replaces tags without nested body elements');
$partial = '<embedded-content></embedded-content>' . $markup;
$partialResult = \Drupal::service('renderer')->executeInRenderContext(new RenderContext(), fn() => $filter->process($partial, 'en'));
verify(str_contains((string) $partialResult, 'Second item'), 'malformed component does not prevent later valid components rendering');
$counter = '<embedded-content data-plugin-id="example.counter" data-plugin-config="{&quot;start_at&quot;:10}"></embedded-content>';
$counterResult = \Drupal::service('renderer')->executeInRenderContext(new RenderContext(), fn() => $filter->process($counter, 'en'));
verify(in_array('inside_embedded_content_examples/counter', $counterResult->getAttachments()['library'] ?? [], TRUE), 'plugin libraries bubble through the text filter');
$button = EmbeddedContentButton::load('default');
$settings = $button->getSettings();
$settings['dialog_settings']['renderer'] = 'modal';
$button->set('settings', $settings);
verify($button->getDialogSetting('renderer') === 'off_canvas', 'old modal configuration cannot activate a modal');
$form = EmbeddedContentDialogForm::create(\Drupal::getContainer());
verify(!$form->checkAccess(new AnonymousUserSession(), $button, FilterFormat::load('inside_embedded'))->isAllowed(), 'anonymous account denied dialog access');
verify(!$form->checkAccess(\Drupal\user\Entity\User::load(1), $button, FilterFormat::load('full_html'))->isAllowed(), 'format without Inside filter denied dialog access');
$unsafe = $manager->createInstance('example.call_to_action', ['url' => 'javascript:alert(1)', 'text' => 'Unsafe URL'])->build();
$unsafeHtml = (string) \Drupal::service('renderer')->renderRoot($unsafe);
verify(!str_contains($unsafeHtml, 'javascript:'), 'CTA rejects unsafe URI schemes even in forged stored configuration');
$typed = \Drupal::service('config.typed')->createFromNameAndData('editor.editor.inside_embedded', \Drupal::config('editor.editor.inside_embedded')->getRawData());
verify(count($typed->validate()) === 0, 'CKEditor configuration schema and plugin requirements valid');
