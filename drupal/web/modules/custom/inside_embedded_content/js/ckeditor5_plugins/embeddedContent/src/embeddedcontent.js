// eslint-disable-next-line import/no-unresolved
import { Plugin } from 'ckeditor5/src/core';
import EmbeddedContentEditing from './editing';
import EmbeddedContentToolbar from './toolbar';
import EmbeddedContentUI from './ui';

export default class EmbeddedContent extends Plugin {
    static get requires()
    {
        return [EmbeddedContentEditing, EmbeddedContentUI, EmbeddedContentToolbar];
    }

    /**
     * @inheritdoc
     */
    static get pluginName()
    {
        return 'EmbeddedContent';
    }
}
