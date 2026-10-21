/**
 * @file Registers the entity embed button(s) to the CKEditor instance(s) and binds functionality to it/them.
 */
// eslint-disable-next-line import/no-unresolved
import { Plugin } from 'ckeditor5/src/core';
// eslint-disable-next-line import/no-unresolved
import { ButtonView } from 'ckeditor5/src/ui';
import { openDialog, getSvg } from './utils';

export default class EmbeddedContentUI extends Plugin {
  /**
   * @inheritdoc
   */
  static get requires() {
    return ['Widget'];
  }

  /**
   * @inheritdoc
   */
  init() {
    const { editor } = this;
    const command = editor.commands.get('insertEmbeddedContent');

    const options = editor.config.get('insideEmbeddedContent');
    if (!options) {
      return;
    }
    const { buttons } = options;

    // Register each embed button to the toolbar based on configuration.
    Object.keys(buttons).forEach((id) => {
      editor.ui.componentFactory.add(`insideEmbeddedContent__${id}`, (locale) => {
        const button = buttons[id];
        const buttonView = new ButtonView(locale);
        buttonView.set({
          label: button.label,
          icon: getSvg(button.iconUrl),
          tooltip: true,
          class: `ck-button_inside-embedded-content ck-button_inside-embedded-content__${id}`,
        });
        const dialogSettings = button.dialogSettings || {};
        button.editor_id = editor.id;
        dialogSettings.editor_id = editor.id;
        const callback = () => {
          openDialog(button, ({ attributes, element }) => {
            editor.execute('insertEmbeddedContent', attributes, element);
          },
            dialogSettings,
          );
        };
        buttonView.bind('isOn', 'isEnabled').to(command, 'value', 'isEnabled');
        this.listenTo(buttonView, 'execute', callback);
        return buttonView;
      });
    });
  }

  /**
   * @inheritdoc
   */
  static get pluginName() {
    return 'EmbeddedContentUI';
  }
}
