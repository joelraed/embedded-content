import { Command } from 'ckeditor5/src/core';

export default class InsertEmbeddedContentCommand extends Command {
  refresh() {
    const selected = this.editor.model.document.selection.getSelectedElement();
    this.value = selected?.is('element', 'insideEmbeddedContent') || selected?.is('element', 'insideEmbeddedContentInline') || false;
    this.isEnabled = this.editor.model.schema.checkChild(this.editor.model.document.selection.focus.parent, 'insideEmbeddedContent') || this.value || this.editor.model.schema.checkChild(this.editor.model.document.selection.focus.parent, 'insideEmbeddedContentInline');
  }

  execute(attributes, element, { target = null, position = null } = {}) {
    const { model } = this.editor;
    const attrs = {
      dataPluginId: attributes['data-plugin-id'],
      dataPluginConfig: attributes['data-plugin-config'],
      dataButtonId: attributes['data-button-id'],
    };
    model.change(writer => {
      if (target && target.name === element) {
        writer.setAttributes(attrs, target);
        writer.setSelection(target, 'on');
      } else {
        const replacement = writer.createElement(element, attrs);
        if (target) {
          model.insertContent(replacement, writer.createRangeOn(target));
        } else {
          model.insertContent(replacement, position?.toPosition());
        }
        writer.setSelection(replacement, 'on');
      }
    });
  }
}
