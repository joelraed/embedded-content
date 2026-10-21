(function (Drupal, once) {
  Drupal.behaviors.insideEmbeddedContentIcons = {
    attach(context) {
      once('inside-embedded-content-icon', '[class*="ckeditor5-toolbar-item-insideEmbeddedContent"]', context).forEach(element => {
        const id = element.dataset.id?.replace('insideEmbeddedContent__', '');
        const span = element.querySelector('span.ckeditor5-toolbar-button');
        if (id && span) span.style.backgroundImage = `url(${Drupal.url('inside-embedded-content/icon/' + id)})`;
      });
    },
  };
})(Drupal, once);
