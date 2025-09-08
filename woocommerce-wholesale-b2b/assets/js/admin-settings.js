jQuery(function($) {
    'use strict';

    // Ensure the table exists before running the script.
    if ( ! $('#wwb-category-minimums-table').length ) {
        return;
    }

    /**
     * Initialize WooCommerce's enhanced select (Select2) on a given row.
     * @param {Object} row The jQuery object for the row.
     */
    function initSelect2( row ) {
        row.find('.wc-enhanced-select-new').each(function() {
            const select = $(this);
            select.removeClass('wc-enhanced-select-new').addClass('wc-enhanced-select');
            $(document.body).trigger('wc-enhanced-select-init');
        });
    }

    // Add Rule
    $('#wwb-category-minimums-table').on('click', '.wwb-add-rule-button', function() {
        const tableBody = $('#wwb-minimums-repeater-body');
        const template = wp.template('wwb-minimums-repeater-row');

        // Find the highest existing index to ensure the new one is unique.
        let rowIndex = 0;
        tableBody.find('tr').each(function() {
            const name = $(this).find('select, input').first().attr('name');
            if (name) {
                const match = name.match(/\[(\d+)\]/);
                if (match && parseInt(match[1]) >= rowIndex) {
                    rowIndex = parseInt(match[1]) + 1;
                }
            }
        });

        const newRow = $(template({ index: rowIndex }));
        tableBody.append(newRow);
        initSelect2(newRow);
    });

    // Remove Rule
    $('#wwb-category-minimums-table').on('click', '.wwb-remove-rule-button', function(e) {
        e.preventDefault();
        $(this).closest('tr').remove();
    });
});
