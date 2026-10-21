import { Plugin } from 'ckeditor5/src/core';
import { Widget, toWidget } from 'ckeditor5/src/widget';
import InsertEmbeddedContentCommand from './command';
let previewSequence = 0;

export default class EmbeddedContentEditing extends Plugin {
  static get requires() { return [Widget]; }
  static get pluginName() { return 'insideEmbeddedContentEditing'; }
  init() {
    const { editor } = this;
    this.options = editor.config.get('insideEmbeddedContent');
    this.requests = new Map();
    this.domElements = new Map();
    this.listenTo(editor.model.document, 'change:data', () => {
      this.domElements.forEach((element, modelElement) => {
        if (modelElement.root.rootName === '$graveyard') {
          Drupal.detachBehaviors(element, drupalSettings, 'unload');
          this.domElements.delete(modelElement);
          this.requests.get(modelElement)?.abort();
        }
      });
    });
    this.attrs = { dataPluginConfig: 'data-plugin-config', dataPluginId: 'data-plugin-id', dataButtonId: 'data-button-id' };
    for (const [viewName, modelName, inline] of [
      ['embedded-content', 'insideEmbeddedContent', false],
      ['embedded-content-inline', 'insideEmbeddedContentInline', true],
    ]) {
      editor.model.schema.register(modelName, { inheritAllFrom: inline ? '$inlineObject' : '$blockObject', allowAttributes: Object.keys(this.attrs) });
      editor.conversion.for('upcast').elementToElement({ view: { name: viewName }, model: modelName });
      editor.conversion.for('dataDowncast').elementToElement({ model: modelName, view: { name: viewName } });
      editor.conversion.for('editingDowncast').elementToElement({
        model: { name: modelName, attributes: Object.keys(this.attrs) },
        view: (modelElement, { writer }) => {
          const container = writer.createContainerElement(inline ? 'span' : 'figure', { class: `inside-embedded-content-preview-wrapper ${viewName}` });
          writer.setCustomProperty('insideEmbeddedContent', true, container);
          const previewId = `inside-embedded-preview-${++previewSequence}`;
          const raw = writer.createRawElement('span', { id: previewId, class: 'inside-embedded-content-preview', 'data-inside-embedded-content-preview': 'loading' }, domElement => {
            const previousElement = this.domElements.get(modelElement);
            if (previousElement) Drupal.detachBehaviors(previousElement, drupalSettings, 'unload');
            this.domElements.set(modelElement, domElement);
            const previous = this.requests.get(modelElement);
            previous?.abort();
            const controller = new AbortController();
            this.requests.set(modelElement, controller);
            const configuration = modelElement.getAttribute('dataPluginConfig');
            fetch(this.options.previewUrl, {
              method: 'POST', signal: controller.signal,
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: new URLSearchParams({ plugin_id: modelElement.getAttribute('dataPluginId'), plugin_config: configuration, preview_id: previewId, _drupal_ajax: '1', 'ajax_page_state[theme]': drupalSettings.ajaxPageState.theme, 'ajax_page_state[theme_token]': drupalSettings.ajaxPageState.theme_token, 'ajax_page_state[libraries]': drupalSettings.ajaxPageState.libraries }),
            }).then(response => {
              if (!response.ok) throw new Error('Preview request failed');
              return response.json();
            }).then(async commands => {
              if (controller.signal.aborted || modelElement.root.rootName === '$graveyard' || configuration !== modelElement.getAttribute('dataPluginConfig')) return;
              // Core AJAX commands load plugin attachments and attach their behaviors.
              // The unique RawElement ID prevents an old response targeting a new preview.
              const ajax = Drupal.ajax({ url: this.options.previewUrl, progress: false });
              try { await ajax.success(commands, 'success'); }
              finally { Drupal.ajax.instances[ajax.instanceIndex] = null; }
              if (!controller.signal.aborted) domElement.dataset.insideEmbeddedContentPreview = 'ready';
            }).catch(error => {
              if (error.name !== 'AbortError') domElement.textContent = Drupal.t('Preview failed. Save your work and reload to retry.');
            }).finally(() => {
              if (this.requests.get(modelElement) === controller) this.requests.delete(modelElement);
            });
          });
          writer.insert(writer.createPositionAt(container, 0), raw);
          return toWidget(container, writer, { label: Drupal.t('Embedded component') });
        },
      });
      for (const [key, attribute] of Object.entries(this.attrs)) {
        const mapping = { model: { key, name: modelName }, view: { name: viewName, key: attribute } };
        editor.conversion.for('upcast').attributeToAttribute(mapping);
        editor.conversion.for('dataDowncast').attributeToAttribute(mapping);
      }
    }
    editor.commands.add('insertInsideEmbeddedContent', new InsertEmbeddedContentCommand(editor));
  }
  destroy() {
    this.requests.forEach(controller => controller.abort());
    this.requests.clear();
    this.domElements.forEach(element => Drupal.detachBehaviors(element, drupalSettings, 'unload'));
    this.domElements.clear();
    return super.destroy();
  }
}
