/**
 * Woo PDF Catalog – Frontend JavaScript
 *
 * Adds a loading state while the PDF is being generated.
 *
 * @package Woo_PDF_Catalog
 * @since   1.0.0
 */
(function ($) {
    'use strict';

    $(function () {
        $('#wpc-frontend-download-btn').on('click', function () {
            var $btn  = $(this);
            var $text = $btn.find('.wpc-btn__text');
            var orig  = $text.text();

            $btn.addClass('wpc-btn--loading');
            $text.text($btn.data('loading-text') || 'Generating PDF…');

            setTimeout(function () {
                $btn.removeClass('wpc-btn--loading');
                $text.text(orig);
            }, 7000);
        });
    });

}(jQuery));
