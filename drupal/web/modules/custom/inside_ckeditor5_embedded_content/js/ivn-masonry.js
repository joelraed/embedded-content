(function ($, Drupal, drupalSettings) {

  Drupal.behaviors.ivnMasonry = {
    attach: function (context, settings) {
      // Custom code to be run on page load and on Ajax load
      $('.media-gallery').imagesLoaded( function(){
        $('.media-gallery').masonry({
            columnWidth: '.grid-sizer',
            itemSelector: '.grid-item',
            percentPosition: true
        });
      });

    }

  };
})(jQuery, Drupal, drupalSettings);
