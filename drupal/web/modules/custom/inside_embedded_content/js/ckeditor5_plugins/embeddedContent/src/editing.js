// eslint-disable-next-line import/no-unresolved
import { Plugin } from 'ckeditor5/src/core';
// eslint-disable-next-line import/no-unresolved
import { Widget, toWidget } from 'ckeditor5/src/widget';
import InsertEmbeddedContentCommand from './command';

export default class EmbeddedContentEditing extends Plugin {
  /**
   * @inheritdoc
   */
  static get requires() {
    return [Widget];
  }

  /**
   * @inheritdoc
   */
  init() {
    this.attrs = {
      dataPluginConfig: 'data-plugin-config',
      dataPluginId: 'data-plugin-id',
      dataButtonId: 'data-button-id',
    };
    const options = this.editor.config.get('insideEmbeddedContent');
    if (!options) {
      throw new Error(
        'Error on initializing insideEmbeddedContent plugin: insideEmbeddedContent config is required.',
      );
    }
    this.options = options;
    this.labelError = Drupal.t('Preview failed');
    this.previewError = `
      <p>${Drupal.t(
        'An error occurred while trying to preview the embedded content. Please save your work and reload this page.',
      )}<p>
    `;

    this._defineSchema();
    this._defineConverters();
    this.editor.commands.add(
      'insertEmbeddedContent',
      new InsertEmbeddedContentCommand(this.editor),
    );
  }

  /**
   * Registers drupalEntity as a block element in the DOM.
   *
   * @private
   */
  _defineSchema() {
    const { schema } = this.editor.model;

    schema.register('insideEmbeddedContent', {
      isObject: true,
      isContent: true,
      isBlock: true,
      allowWhere: '$block',
      allowAttributes: Object.keys(this.attrs),
    });
    schema.register('insideEmbeddedContentInline', {
      isObject: true,
      isContent: true,
      isBlock: true,
      isInline: true,
      allowWhere: '$text',
      allowAttributes: Object.keys(this.attrs),
    });

    this.editor.editing.view.domConverter.blockElements.push(
      'embedded-content',
    );
    this.editor.editing.view.domConverter.blockElements.push(
      'embedded-content-inline',
    );
  }

  /**
   * Defines handling of drupal media element in the content lifecycle.
   *
   * @private
   */
  _defineConverters() {
    const { conversion } = this.editor;

    const displayTypeMapping = {
      'embedded-content': 'insideEmbeddedContent',
      'embedded-content-inline': 'insideEmbeddedContentInline',
    };

    Object.entries(displayTypeMapping).forEach(([viewName, modelName]) => {
      conversion.for('upcast').elementToElement({
        view: {
          name: viewName,
        },
        model: modelName,
      });

      conversion.for('dataDowncast').elementToElement({
        model: modelName,
        view: {
          name: viewName,
        },
      });
      conversion.for('dataDowncast').elementToElement({
        model: modelName,
        view: {
          name: viewName,
        },
      });
      conversion
        .for('editingDowncast')
        .elementToElement({
          model: modelName,
          view: (modelElement, { writer }) => {
            const container = writer.createContainerElement('figure', {
              class: `inside-embedded-content-preview-wrapper ${viewName}`,
            });
            writer.setCustomProperty('insideEmbeddedContent', true, container);
            return toWidget(container, writer, {
              label: Drupal.t('Embedded content'),
            });
          },
        })
        .add((dispatcher) => {
          const converter = (event, data, conversionApi) => {
            const viewWriter = conversionApi.writer;
            const modelElement = data.item;
            const container = conversionApi.mapper.toViewElement(data.item);
            const insideEmbeddedContent = viewWriter.createRawElement('span', {
              'data-inside-embedded-content-preview': 'loading',
              class: `inside-embedded-content-preview ${viewName}`,
            });
            viewWriter.insert(
              viewWriter.createPositionAt(container, 0),
              insideEmbeddedContent,
            );
            this._fetchPreview(modelElement).then((preview) => {
              if (!insideEmbeddedContent) {
                return;
              }
              this.editor.editing.view.change((writer) => {
                const renderFunction = (domElement) => {
                  domElement.innerHTML = preview;
                };
                const insideEmbeddedContentPreview = writer.createRawElement(
                  'span',
                  {
                    class: `inside-embedded-content-preview ${viewName}`,
                    'data-inside-embedded-content-preview': 'ready',
                  },
                  renderFunction,
                );
                writer.insert(
                  writer.createPositionBefore(insideEmbeddedContent),
                  insideEmbeddedContentPreview,
                );
                writer.remove(insideEmbeddedContent);
              });
            });
          };
          dispatcher.on(`attribute:dataPluginId:${modelName}`, converter);
          return dispatcher;
        });

      Object.keys(this.attrs).forEach((modelKey) => {
        const attributeMapping = {
          model: {
            key: modelKey,
            name: modelName,
          },
          view: {
            name: viewName,
            key: this.attrs[modelKey],
          },
        };
        conversion.for('dataDowncast').attributeToAttribute(attributeMapping);
        conversion.for('upcast').attributeToAttribute(attributeMapping);
      });
    });
  }

  /**
   * Fetches the preview.
   *
   * @param {HTMLElement} modelElement
   *   The model element.
   */
  async _fetchPreview(modelElement) {
    const config = {
      plugin_id: modelElement.getAttribute('dataPluginId'),
      plugin_config: modelElement.getAttribute('dataPluginConfig'),
      editor_id: this.editor.id,
    };
    const response = await fetch(this.options.previewUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(config),
    });
    if (response.ok) {
      return response.text();
    }

    return this.themeError;
  }

  /**
   * @inheritdoc
   */
  static get pluginName() {
    return 'insideEmbeddedContentEditing';
  }
}
