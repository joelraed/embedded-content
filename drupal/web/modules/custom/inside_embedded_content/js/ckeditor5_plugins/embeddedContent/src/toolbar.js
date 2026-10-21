import { Plugin } from 'ckeditor5/src/core';
import { WidgetToolbarRepository } from 'ckeditor5/src/widget';
import { ButtonView } from 'ckeditor5/src/ui';
import { openDialog } from './utils';

export default class EmbeddedContentToolbar extends Plugin {
  static get requires() { return [WidgetToolbarRepository]; }
  init() {
    const { editor } = this;
    editor.ui.componentFactory.add('insideEmbeddedContentEdit', locale => {
      const button = new ButtonView(locale);
      button.set({ label: Drupal.t('Edit component'), withText: true, tooltip: true, class: 'ck-button_inside-embedded-content__edit' });
      this.listenTo(button, 'execute', () => {
        const target = editor.model.document.selection.getSelectedElement();
        if (!target) return;
        const options = editor.config.get('insideEmbeddedContent');
        const settings = options.buttons[target.getAttribute('dataButtonId') || 'default'];
        if (settings) openDialog(editor, settings, target);
      });
      return button;
    });
    editor.ui.componentFactory.add('insideEmbeddedContentRemove', locale => {
      const button = new ButtonView(locale);
      button.set({ label: Drupal.t('Remove component'), withText: true, class: 'ck-button_inside-embedded-content__remove' });
      button.bind('isEnabled').to(editor, 'isReadOnly', value => !value);
      this.listenTo(button, 'execute', () => {
        const target = editor.model.document.selection.getSelectedElement();
        if (target && ['insideEmbeddedContent', 'insideEmbeddedContentInline'].includes(target.name)) editor.model.change(writer => writer.remove(target));
        editor.editing.view.focus();
      });
      return button;
    });
  }
  afterInit() {
    this.editor.plugins.get(WidgetToolbarRepository).register('insideEmbeddedContent', {
      ariaLabel: Drupal.t('Embedded component toolbar'),
      items: ['insideEmbeddedContentEdit', 'insideEmbeddedContentRemove'],
      getRelatedElement: selection => {
        const element = selection.getSelectedElement();
        return element?.getCustomProperty('insideEmbeddedContent') ? element : null;
      },
    });
  }
  static get pluginName() { return 'InsideEmbeddedContentToolbar'; }
}
