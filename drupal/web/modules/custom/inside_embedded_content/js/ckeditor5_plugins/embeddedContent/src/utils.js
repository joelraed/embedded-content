import { ModelLivePosition as LivePosition } from 'ckeditor5/src/engine';

/** Request-scoped bridge between Drupal AJAX and supported CKEditor model APIs. */
let current = null;
let installed = false;

const dispose = (session, focus = true) => {
  session.position?.detach();
  if (current === session) current = null;
  session.editor.off('destroy', session.onDestroy);
  if (focus && session.editor.state !== 'destroyed') session.editor.editing.view.focus();
};

const install = () => {
  if (installed) return;
  installed = true;
  Drupal.AjaxCommands.prototype.insideEmbeddedContentSave = (ajax, response) => {
    const session = current;
    if (!session || response.requestId !== session.id || response.editorId !== session.editor.id) return;
    const { editor, target, snapshot, version } = session;
    if (editor.state === 'destroyed' || editor.isReadOnly) return;
    // Never resurrect a deleted target or overwrite a target edited in the meantime.
    if (target && (target.root.rootName === '$graveyard' || JSON.stringify([...target.getAttributes()]) !== snapshot)) {
      Drupal.announce(Drupal.t('This component changed while the panel was open. Close and reopen it to edit the current version.'));
      return;
    }
    // A new insert is conservative: typing elsewhere invalidates the old insert.
    if (!target && editor.model.document.version !== version) {
      Drupal.announce(Drupal.t('The document changed. Close and reopen the panel before inserting.'));
      return;
    }
    editor.execute('insertInsideEmbeddedContent', response.value.attributes, response.value.element, { target, position: session.position });
    new Drupal.AjaxCommands().closeDialog(ajax, { selector: '#drupal-off-canvas' });
    dispose(session);
  };
  // Drupal 11 dispatches native lifecycle events from the dialog element.
  // Invalidate before close: AJAX can remove the element before afterclose bubbles.
  window.addEventListener('dialog:beforeclose', event => {
    if (event.target.id === 'drupal-off-canvas' && current) dispose(current);
  });
};

export const openDialog = (editor, button, target = null) => {
  install();
  if (current) {
    const previous = current;
    dispose(previous, false);
    if (document.querySelector('#drupal-off-canvas')) Drupal.dialog(document.querySelector('#drupal-off-canvas')).close();
  }
  const position = target ? null : LivePosition.fromPosition(editor.model.document.selection.getFirstPosition());
  const session = {
    editor, target, position,
    id: `${editor.id}-${Date.now()}-${Math.random().toString(36).slice(2)}`,
    snapshot: target ? JSON.stringify([...target.getAttributes()]) : null,
    version: editor.model.document.version,
  };
  session.onDestroy = () => dispose(session, false);
  editor.on('destroy', session.onDestroy);
  current = session;
  const ajax = Drupal.ajax({
    url: button.dialogUrl,
    dialogType: 'dialog', dialogRenderer: 'off_canvas',
    dialog: { width: parseInt(button.dialogSettings?.width, 10) || 480 },
    progress: { type: 'throbber' },
    submit: {
      editor_id: editor.id, request_id: session.id,
      plugin_id: target?.getAttribute('dataPluginId') || '',
      plugin_config: target?.getAttribute('dataPluginConfig') || '{}',
    },
  });
  const coreOpen = ajax.commands.openDialog;
  ajax.commands.openDialog = function (...args) {
    if (current === session) return coreOpen.apply(this, args);
  };
  ajax.execute().catch(() => { if (current === session) dispose(session); }).always(() => { Drupal.ajax.instances[ajax.instanceIndex] = null; });
};

export const getSvg = () => "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 512 512\"><!--! Font Awesome Pro 6.1.2 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license (Commercial License) Copyright 2022 Fonticons, Inc. --><path d=\"M500.3 7.251C507.7 13.33 512 22.41 512 31.1V175.1C512 202.5 483.3 223.1 447.1 223.1C412.7 223.1 383.1 202.5 383.1 175.1C383.1 149.5 412.7 127.1 447.1 127.1V71.03L351.1 90.23V207.1C351.1 234.5 323.3 255.1 287.1 255.1C252.7 255.1 223.1 234.5 223.1 207.1C223.1 181.5 252.7 159.1 287.1 159.1V63.1C287.1 48.74 298.8 35.61 313.7 32.62L473.7 .6198C483.1-1.261 492.9 1.173 500.3 7.251H500.3zM74.66 303.1L86.5 286.2C92.43 277.3 102.4 271.1 113.1 271.1H174.9C185.6 271.1 195.6 277.3 201.5 286.2L213.3 303.1H239.1C266.5 303.1 287.1 325.5 287.1 351.1V463.1C287.1 490.5 266.5 511.1 239.1 511.1H47.1C21.49 511.1-.0019 490.5-.0019 463.1V351.1C-.0019 325.5 21.49 303.1 47.1 303.1H74.66zM143.1 359.1C117.5 359.1 95.1 381.5 95.1 407.1C95.1 434.5 117.5 455.1 143.1 455.1C170.5 455.1 191.1 434.5 191.1 407.1C191.1 381.5 170.5 359.1 143.1 359.1zM440.3 367.1H496C502.7 367.1 508.6 372.1 510.1 378.4C513.3 384.6 511.6 391.7 506.5 396L378.5 508C372.9 512.1 364.6 513.3 358.6 508.9C352.6 504.6 350.3 496.6 353.3 489.7L391.7 399.1H336C329.3 399.1 323.4 395.9 321 389.6C318.7 383.4 320.4 376.3 325.5 371.1L453.5 259.1C459.1 255 467.4 254.7 473.4 259.1C479.4 263.4 481.6 271.4 478.7 278.3L440.3 367.1zM116.7 219.1L19.85 119.2C-8.112 90.26-6.614 42.31 24.85 15.34C51.82-8.137 93.26-3.642 118.2 21.83L128.2 32.32L137.7 21.83C162.7-3.642 203.6-8.137 231.6 15.34C262.6 42.31 264.1 90.26 236.1 119.2L139.7 219.1C133.2 225.6 122.7 225.6 116.7 219.1H116.7z\"/></svg>";
