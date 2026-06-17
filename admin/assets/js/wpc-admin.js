/**
 * Woo PDF Catalog – Admin JavaScript
 *
 * Initialises Select2 for product selectors, handles the download button
 * loading state, and manages CSV product-ID imports.
 *
 * @package Woo_PDF_Catalog
 * @since   1.0.0
 */
/* global wpcAdmin, jQuery */
(function ($) {
    'use strict';

    $(function () {

        // ── Select2 AJAX product search ───────────────────────────────────
        $('.wpc-product-select').select2({
            width: '100%',
            minimumInputLength: 1,
            placeholder: wpcAdmin.i18n.searching,
            language: {
                inputTooShort: function () {
                    return wpcAdmin.i18n.searching;
                },
                noResults: function () {
                    return wpcAdmin.i18n.noResults;
                },
                searching: function () {
                    return wpcAdmin.i18n.searching;
                }
            },
            ajax: {
                url: wpcAdmin.ajaxUrl,
                dataType: 'json',
                delay: 300,
                data: function (params) {
                    return {
                        action: 'wpc_search_products',
                        nonce: wpcAdmin.searchNonce,
                        q: params.term
                    };
                },
                processResults: function (data) {
                    return {
                        results: data.results || []
                    };
                }
            }
        });

        // ── CSV Import ────────────────────────────────────────────────────

        /**
         * Parse a CSV string and return an array of unique integer IDs.
         *
         * Supports:
         *  - One ID per line
         *  - Comma / semicolon separated IDs
         *  - Multi-column CSVs (uses the first column)
         *  - Skips header rows (non-numeric first value)
         */
        function parseCsvIds(csvText) {
            var ids = {};
            var lines = csvText.split(/\r?\n/);

            for (var i = 0; i < lines.length; i++) {
                var line = lines[i].trim();
                if (!line) continue;

                // Split by comma, semicolon, or tab
                var parts = line.split(/[,;\t]/);

                for (var j = 0; j < parts.length; j++) {
                    var val = parts[j].trim().replace(/^["']|["']$/g, '');
                    var num = parseInt(val, 10);
                    if (!isNaN(num) && num > 0 && String(num) === val) {
                        ids[num] = true;
                    }
                }
            }

            return Object.keys(ids).map(Number);
        }

        // When a file is selected, enable the upload button and show the filename.
        $('.wpc-csv-file-input').on('change', function () {
            var $wrap = $(this).closest('.wpc-csv-upload');
            var $btn = $wrap.find('.wpc-csv-upload-btn');
            var $text = $wrap.find('.wpc-csv-file-text');
            var file = this.files[0];

            if (file) {
                $text.text(file.name);
                $btn.prop('disabled', false);
            } else {
                $text.text(wpcAdmin.i18n.csvChoose);
                $btn.prop('disabled', true);
            }

            // Hide any previous feedback
            $wrap.find('.wpc-csv-feedback').slideUp(150);
        });

        // Process the CSV when the upload button is clicked.
        $('.wpc-csv-upload-btn').on('click', function () {
            var $btn = $(this);
            var $wrap = $btn.closest('.wpc-csv-upload');
            var $fileInput = $wrap.find('.wpc-csv-file-input');
            var $feedback = $wrap.find('.wpc-csv-feedback');
            var targetId = $wrap.data('target');
            var $select = $('#' + targetId);
            var file = $fileInput[0].files[0];

            if (!file) return;

            // Read CSV client-side
            var reader = new FileReader();
            reader.onload = function (e) {
                var ids = parseCsvIds(e.target.result);

                if (ids.length === 0) {
                    showFeedback($feedback, 'error', wpcAdmin.i18n.csvNoIds);
                    return;
                }

                // Disable button during request
                $btn.prop('disabled', true).find('span:last-child, text').last();
                var origHtml = $btn.html();
                $btn.html('<span class="dashicons dashicons-update wpc-spin"></span> ' + wpcAdmin.i18n.csvImporting);

                $.ajax({
                    url: wpcAdmin.ajaxUrl,
                    method: 'POST',
                    data: {
                        action: 'wpc_import_csv_products',
                        nonce: wpcAdmin.csvImportNonce,
                        ids: ids
                    },
                    success: function (response) {
                        if (response.success && response.data) {
                            var found = response.data.found || [];
                            var notFound = response.data.not_found || [];

                            // Add found products to the Select2
                            found.forEach(function (product) {
                                // Skip if already selected
                                if ($select.find('option[value="' + product.id + '"]').length === 0) {
                                    var option = new Option(product.text, product.id, true, true);
                                    $select.append(option);
                                }
                            });
                            $select.trigger('change');

                            // Build feedback message
                            var messages = [];
                            if (found.length > 0) {
                                messages.push(
                                    '<span class="wpc-csv-success">' +
                                    '<span class="dashicons dashicons-yes-alt"></span> ' +
                                    wpcAdmin.i18n.csvImported.replace('%d', found.length) +
                                    '</span>'
                                );
                            }
                            if (notFound.length > 0) {
                                messages.push(
                                    '<span class="wpc-csv-warning">' +
                                    '<span class="dashicons dashicons-warning"></span> ' +
                                    wpcAdmin.i18n.csvNotFound.replace('%s', notFound.join(', ')) +
                                    '</span>'
                                );
                            }

                            showFeedback($feedback, found.length > 0 ? 'success' : 'warning', messages.join('<br>'));
                        } else {
                            var msg = (response.data && response.data.message) ? response.data.message : wpcAdmin.i18n.csvError;
                            showFeedback($feedback, 'error', msg);
                        }
                    },
                    error: function () {
                        showFeedback($feedback, 'error', wpcAdmin.i18n.csvError);
                    },
                    complete: function () {
                        $btn.html(origHtml).prop('disabled', false);
                        // Reset file input
                        $fileInput.val('');
                        $wrap.find('.wpc-csv-file-text').text(wpcAdmin.i18n.csvChoose);
                        $btn.prop('disabled', true);
                    }
                });
            };

            reader.readAsText(file);
        });

        function showFeedback($el, type, html) {
            $el.removeClass('wpc-feedback-success wpc-feedback-error wpc-feedback-warning')
               .addClass('wpc-feedback-' + type)
               .html(html)
               .slideDown(200);
        }

        // ── Download button ───────────────────────────────────────────────
        // The download button is a plain <a> that opens admin-post.php.
        // We add a brief loading state for UX feedback.
        $('#wpc-download-btn').on('click', function () {
            var $btn = $(this);
            $btn.addClass('is-loading').text(wpcAdmin.i18n.generating);

            // Re-enable after 6 seconds (PDF generation may take a moment).
            setTimeout(function () {
                $btn.removeClass('is-loading').html(
                    '<span class="dashicons dashicons-pdf"></span> ' +
                    wpcAdmin.i18n.downloadReady
                );
            }, 6000);
        });

    });

}(jQuery));
