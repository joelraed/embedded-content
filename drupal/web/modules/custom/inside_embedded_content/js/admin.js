/**
 * @file
 * Views admin UI functionality.
 */

(function ($, Drupal) {

    'use strict';

    /**
     * @type {Drupal~behavior}
     */
    Drupal.behaviors.admin_css = {
        attach: function (context, settings) {
            context.querySelectorAll('[class*="ckeditor5-toolbar-item-insideEmbeddedContent"]').forEach(
                function (element) {
                    const id = element.dataset.id.replace('insideEmbeddedContent__' ,'');
                    const child = element.querySelector('span.ckeditor5-toolbar-button');
                    child.setAttribute('style', 'background-image: url(/inside-embedded-content/icon/'+id + ')');
                }
            );
        }
    };

})(jQuery, Drupal);
