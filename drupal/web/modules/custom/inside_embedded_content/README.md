## Inside Embedded Content

This module provides a way to allow editors to insert styled components into CKEditor without having
to grant the editors permission

- Styling of each component can be
- Editors don't require permission to set classes to HTML
- Developers can create more complex components
- Thanks to Ckeditor5 the elements are immediately upcasted and visible in the editor.

## Usage

Enable the Inside Embedded Content text filter:

1. Create a new text filter or edit an existing one and enable CKEditor 5.
2. Drag the 'Inside Embedded Content' icon to the Ckeditor Toolbar
3. Enable the 'Inside Embedded Content' filter.

Create one or more 'Embeddedcontent' plugins.
- Enable inside_embedded_content_examples for a working example.

## Development

This module has been tested using build of node version 18. Use nvm to install and/or change to version 18.

1. npm install
2. npm run build
