import { Plugin } from 'ckeditor5/src/core';
import { ButtonView } from 'ckeditor5/src/ui';
import { openDialog, getSvg } from './utils';

export default class EmbeddedContentUI extends Plugin {
  init() {
    const { editor } = this;
    const command = editor.commands.get('insertInsideEmbeddedContent');
    const options = editor.config.get('insideEmbeddedContent');
    Object.entries(options.buttons).forEach(([id, button]) => {
      editor.ui.componentFactory.add(`insideEmbeddedContent__${id}`, locale => {
        const view = new ButtonView(locale);
        view.set({ label: button.label, icon: button.icon || getSvg(), tooltip: true, class: `ck-button_inside-embedded-content__${id}` });
        view.bind('isEnabled').to(command, 'isEnabled');
        this.listenTo(view, 'execute', () => {
          const selected = editor.model.document.selection.getSelectedElement();
          const target = ['insideEmbeddedContent', 'insideEmbeddedContentInline'].includes(selected?.name) ? selected : null;
          openDialog(editor, button, target);
        });
        return view;
      });
    });
  }
  static get pluginName() { return 'InsideEmbeddedContentUI'; }
}
