// eslint-disable-next-line import/no-unresolved
import { Plugin } from 'ckeditor5/src/core';
import { IconPencil } from '@ckeditor/ckeditor5-icons';
// eslint-disable-next-line import/no-unresolved
import { isWidget, WidgetToolbarRepository } from 'ckeditor5/src/widget';
// eslint-disable-next-line import/no-unresolved
import { ButtonView } from 'ckeditor5/src/ui';
import { openDialog } from './utils';

export default class EmbeddedContentToolbar extends Plugin {
  /**
   * @inheritdoc
   */
  static get requires() {
    return [WidgetToolbarRepository];
  }

  /**
   * @inheritdoc
   */
  init() {
    const { editor } = this;
    const options = editor.config.get('insideEmbeddedContent');

    editor.ui.componentFactory.add('insideEmbeddedContentEdit', (locale) => {
      const buttonView = new ButtonView(locale);

      buttonView.set({
        label: editor.t('Edit'),
        icon: IconPencil,
        tooltip: true,
        class: 'ck-button_inside-embedded-content__edit',
      });

      this.listenTo(buttonView, 'execute', () => {
        const selectedElement =
          editor.model.document.selection.getSelectedElement();

        // Support embedded content created by ckeditor5_inside_embedded_content
        // that doesn't have a dataButtonId attribute.
        const buttonId = selectedElement.getAttribute('dataButtonId') ?? 'default';
        const button = options.buttons[buttonId];
        button.editor_id = editor.id;
        const existingValues = {
          plugin_id: selectedElement.getAttribute('dataPluginId'),
          plugin_config: selectedElement.getAttribute('dataPluginConfig'),
          editor_id: editor.id,
        };

        openDialog(
          button,
          ({ attributes, element }) => {
            editor.execute('insertEmbeddedContent', attributes, element);
            editor.editing.view.focus();
          },
          existingValues,
        );
      });

      return buttonView;
    });
  }

  /**
   * @inheritdoc
   */
  afterInit() {
    const { editor } = this;
    if (!editor.plugins.has('WidgetToolbarRepository')) {
      return;
    }
    const widgetToolbarRepository = editor.plugins.get(WidgetToolbarRepository);

    widgetToolbarRepository.register('insideEmbeddedContent', {
      ariaLabel: Drupal.t('EmbeddedContent toolbar'),
      items: ['insideEmbeddedContentEdit'],
      getRelatedElement(selection) {
        const viewElement = selection.getSelectedElement();
        if (!viewElement) {
          return null;
        }
        if (!isWidget(viewElement)) {
          return null;
        }
        if (!viewElement.getCustomProperty('insideEmbeddedContent')) {
            return null
        }

        return viewElement;
      },
    });
  }

  /**
   * @inheritdoc
   */
  static get pluginName() {
    return 'EmbeddedContentToolbar';
  }
}
