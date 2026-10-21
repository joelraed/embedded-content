export const openDialog = (button, saveCallback, existingValues) => {
    const ckeditorAjaxDialog = Drupal.ajax(
        {
            dialog: {
                width: button.dialogSettings.width,
                height: button.dialogSettings.height,
                class: button.class,
            },
            dialogType:
            button.dialogSettings.renderer === 'off_canvas' ? 'dialog' : 'modal',
            dialogRenderer:
            button.dialogSettings.renderer === 'off_canvas' ? 'off_canvas' : null,
            selector: button.dialogSettings.selector,
            url: button.dialogUrl,
            progress: { type: 'fullscreen' },
            submit: existingValues,
        }
    );
  ckeditorAjaxDialog.execute();

  // Store the save callback to be executed when this dialog is closed.

  // We already take into account the possibility of supporting multiple modals.
  // @see https://www.drupal.org/project/drupal/issues/2741877
  if (Drupal.ckeditor5.saveCallback instanceof Map) {
    Drupal.ckeditor5.saveCallback.set('#inside-embedded-content-dialog-form-' + button.editor_id, saveCallback);
  } else {
    Drupal.ckeditor5.saveCallback = saveCallback;
  }
};

export const getSvg = (url) => {
    const xmlHttp = new XMLHttpRequest();
    xmlHttp.open('GET', url, false);
    xmlHttp.send();
    return xmlHttp.responseText;
};

export const createEmbeddedContent = (writer, attributes, element) => {
    return writer.createElement(element, attributes);
};
