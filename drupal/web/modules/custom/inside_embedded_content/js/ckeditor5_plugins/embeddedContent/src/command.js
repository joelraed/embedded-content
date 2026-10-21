// eslint-disable-next-line import/no-unresolved
import { Command } from 'ckeditor5/src/core';
import { createEmbeddedContent } from './utils';

export default class InsertEmbeddedContentCommand extends Command {
  execute(attributes, element) {
    const { model } = this.editor;
    const insideEmbeddedContentEditing = this.editor.plugins.get(
      'insideEmbeddedContentEditing',
    );

    const dataAttributeMapping = Object.fromEntries(
      Object.entries(insideEmbeddedContentEditing.attrs).map(([key, value]) => [
        value,
        key,
      ]),
    );

    // \Drupal\inside_embedded_content\Form\EmbeddedContentDialog returns data in keyed by
    // data-attributes used in view data. This converts data-attribute keys to
    // keys used in model.
    const modelAttributes = Object.fromEntries(
      Object.keys(dataAttributeMapping)
        .filter((attribute) => attributes[attribute])
        .map((attribute) => [
          dataAttributeMapping[attribute],
          attributes[attribute],
        ]),
    );

    model.change((writer) => {
      model.insertContent(
        createEmbeddedContent(writer, modelAttributes, element),
      );
    });
  }
}
