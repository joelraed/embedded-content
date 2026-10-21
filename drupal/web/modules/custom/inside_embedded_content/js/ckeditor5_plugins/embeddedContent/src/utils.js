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

export const getSvg = () => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M3 3h18v18H3V3zm2 2v14h14V5H5zm6 2h2v4h4v2h-4v4h-2v-4H7v-2h4V7z"/></svg>';
