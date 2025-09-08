(function($, wp) {
    'use strict';

    $(function() {
        // Ensure we are on a page with the wholesale form.
        if ( ! $('#wholesale-order-form-container').length ) {
            return;
        }

        const wholesaleForm = {
            searchTimeout: null,
            searchField: $('#wholesale-product-search'),
            categoryField: $('#wholesale-product-category-filter'),
            resultsContainer: $('#wholesale-product-list'),
            form: $('#wholesale-order-form'),
            messagesContainer: $('#wholesale-order-form-messages'),
            template: wp.template('wholesale-product-row'),

            init: function() {
                this.searchField.on('keyup', this.debounceSearch.bind(this));
                this.categoryField.on('change', this.triggerSearch.bind(this));
                this.form.on('submit', this.handleFormSubmit.bind(this));
            },

            debounceSearch: function() {
                clearTimeout(this.searchTimeout);
                this.searchTimeout = setTimeout(this.triggerSearch.bind(this), 500);
            },

            triggerSearch: function() {
                const self = this;
                const searchTerm = self.searchField.val();
                const categoryId = self.categoryField.val();

                if (searchTerm.length > 0 && searchTerm.length < 3 && !categoryId) {
                    self.resultsContainer.html('<tr><td colspan="4" style="text-align: center; padding: 20px;">' + wwb_params.i18n.prompt + '</td></tr>');
                    return;
                }

                if( searchTerm.length === 0 && !categoryId ) {
                     self.resultsContainer.html('<tr><td colspan="4" style="text-align: center; padding: 20px;">' + wwb_params.i18n.prompt_initial + '</td></tr>');
                    return;
                }

                self.resultsContainer.html('<tr><td colspan="4" style="text-align: center; padding: 20px;">' + wwb_params.i18n.loading + '</td></tr>');

                $.ajax({
                    url: wwb_params.ajax_url,
                    method: 'POST',
                    data: {
                        action: 'wwb_search_products',
                        nonce: wwb_params.nonce,
                        search: searchTerm,
                        category: categoryId
                    },
                    success: function(response) {
                        self.resultsContainer.empty();
                        if (response.success && response.data.length) {
                            $.each(response.data, function(index, product) {
                                self.resultsContainer.append(self.template(product));
                            });
                        } else {
                            const message = response.data.message ? response.data.message : wwb_params.i18n.no_results;
                            self.resultsContainer.html('<tr><td colspan="4" style="text-align: center; padding: 20px;">' + message + '</td></tr>');
                        }
                    },
                    error: function() {
                         self.resultsContainer.html('<tr><td colspan="4" style="text-align: center; padding: 20px;">' + wwb_params.i18n.error + '</td></tr>');
                    }
                });
            },

            handleFormSubmit: function(e) {
                e.preventDefault();
                const self = this;
                const submitButton = self.form.find('button[type="submit"]');

                submitButton.addClass('loading');
                self.messagesContainer.empty();

                $.ajax({
                    url: wwb_params.ajax_url,
                    method: 'POST',
                    data: self.form.serialize(),
                    success: function(response) {
                        if (response.success) {
                            // Update cart fragments (e.g., mini cart)
                            if (response.data.fragments) {
                                $.each(response.data.fragments, function(key, value) {
                                    $(key).replaceWith(value);
                                });
                            }

                            // Trigger event for other plugins
                            $(document.body).trigger('wc_cart_changed', [response.data.fragments, response.data.cart_hash]);
                            $(document.body).trigger('added_to_cart', [response.data.fragments, response.data.cart_hash]);

                            // Show success message
                            const message = response.data.message + ' <a href="' + wwb_params.cart_url + '" class="button wc-forward">' + wwb_params.view_cart_text + '</a>';
                            self.messagesContainer.html('<div class="woocommerce-message">' + message + '</div>');

                            // Clear quantities
                            self.form.find('.qty').val('');

                        } else {
                            self.messagesContainer.html('<div class="woocommerce-error">' + response.data.message + '</div>');
                        }
                    },
                    error: function() {
                        self.messagesContainer.html('<div class="woocommerce-error">' + wwb_params.i18n.error + '</div>');
                    },
                    complete: function() {
                        submitButton.removeClass('loading');
                        // Scroll to top to see the message
                        $('html, body').animate({
                            scrollTop: self.messagesContainer.offset().top - 100
                        }, 500);
                    }
                });
            }
        };

        wholesaleForm.init();
    });

})(jQuery, wp);
