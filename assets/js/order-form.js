jQuery(document).ready(function($) {
    const productSearch = $('#rj-product-search');
    const selectedProducts = $('#rj-selected-products');
    const orderItems = $('#rj-order-items');
    let searchTimeout;
    let searchResults;

    // Initialize search results container
    function initSearchResults() {
        if (!searchResults) {
            searchResults = $('<div class="rj-search-results"></div>');
            productSearch.parent().append(searchResults);
            searchResults.hide();
        }
    }

    // Product search handler
    productSearch.on('input', function() {
        const query = $(this).val().trim();
        
        clearTimeout(searchTimeout);
        
        if (query.length < 2) {
            searchResults.hide();
            return;
        }

        searchTimeout = setTimeout(function() {
            $.ajax({
                url: rj_admin_order_data.ajax_url,
                type: 'GET',
                data: {
                    action: 'rj_search_products',
                    term: query,
                    nonce: rj_admin_order_data.nonce
                },
                success: function(response) {
                    if (response.success && response.data.results) {
                        displaySearchResults(response.data.results);
                    }
                }
            });
        }, 300);
    });

    // Display search results
    function displaySearchResults(results) {
        initSearchResults();
        searchResults.empty();

        results.forEach(function(product) {
            const item = $('<div class="rj-search-item"></div>')
                .text(product.text)
                .data('product', product);

            item.on('click', function() {
                const productData = $(this).data('product');
                if (productData.has_variations) {
                    loadVariations(productData);
                } else {
                    addProduct(productData);
                }
                productSearch.val('');
                searchResults.hide();
            });

            searchResults.append(item);
        });

        searchResults.show();
    }

    // Load product variations
    function loadVariations(product) {
        $.ajax({
            url: rj_admin_order_data.ajax_url,
            type: 'GET',
            data: {
                action: 'rj_get_variations',
                product_id: product.id,
                nonce: rj_admin_order_data.nonce
            },
            success: function(response) {
                if (response.success && response.data.variations) {
                    showVariationsModal(product, response.data.variations);
                }
            }
        });
    }

    // Show variations modal
    function showVariationsModal(product, variations) {
        const modal = $(`
            <div class="rj-variations-modal">
                <div class="rj-variations-content">
                    <span class="rj-variations-close">&times;</span>
                    <h3>${product.text}</h3>
                    <div class="rj-variations-list"></div>
                </div>
            </div>
        `);

        const variationsList = modal.find('.rj-variations-list');

        variations.forEach(function(variation) {
            const variationItem = $('<div class="rj-variation-item"></div>')
                .text(variation.text + ' - ₹' + variation.price)
                .data('variation', variation)
                .data('product', product);

            variationItem.on('click', function() {
                const varData = $(this).data('variation');
                const prodData = $(this).data('product');
                addProduct({
                    ...prodData,
                    variation_id: varData.variation_id,
                    price: varData.price,
                    text: prodData.text + ' - ' + varData.text
                });
                modal.remove();
            });

            variationsList.append(variationItem);
        });

        modal.find('.rj-variations-close').on('click', function() {
            modal.remove();
        });

        $('body').append(modal);
        modal.fadeIn();
    }

    // Add product to order
    function addProduct(product) {
        const productId = product.variation_id || product.id;
        const existingProduct = $(`#product-${productId}`);

        if (existingProduct.length) {
            const qtyInput = existingProduct.find('.rj-quantity-input');
            qtyInput.val(parseInt(qtyInput.val()) + 1).trigger('change');
            return;
        }

        const productItem = $(`
            <div id="product-${productId}" class="rj-product-item">
                <div class="rj-product-info">
                    <strong>${product.text}</strong>
                    <div>₹${parseFloat(product.price).toFixed(2)}</div>
                </div>
                <div class="rj-product-controls">
                    <input type="number" class="rj-quantity-input" value="1" min="1">
                    <span class="rj-remove-product dashicons dashicons-trash"></span>
                </div>
                <input type="hidden" name="products[]" value='${JSON.stringify({
                    id: product.id,
                    variation_id: product.variation_id || null,
                    price: product.price,
                    quantity: 1
                })}'>
            </div>
        `);

        // Quantity change handler
        productItem.find('.rj-quantity-input').on('change', function() {
            const qty = parseInt($(this).val());
            if (qty < 1) {
                $(this).val(1);
                return;
            }
            updateProductData(productItem, qty);
            updateOrderSummary();
        });

        // Remove product handler
        productItem.find('.rj-remove-product').on('click', function() {
            productItem.remove();
            updateOrderSummary();
        });

        selectedProducts.append(productItem);
        updateOrderSummary();
    }

    // Update product data
    function updateProductData(productItem, quantity) {
        const hiddenInput = productItem.find('input[name="products[]"]');
        const data = JSON.parse(hiddenInput.val());
        data.quantity = quantity;
        hiddenInput.val(JSON.stringify(data));
    }

    // Update order summary
    function updateOrderSummary() {
        let subtotal = 0;
        
        selectedProducts.find('.rj-product-item').each(function() {
            const data = JSON.parse($(this).find('input[name="products[]"]').val());
            subtotal += parseFloat(data.price) * parseInt(data.quantity);
        });

        $('#rj-order-subtotal').text('₹' + subtotal.toFixed(2));
        $('#rj-order-total').text('₹' + subtotal.toFixed(2));
    }

    // Phone number validation
    $('#phone').on('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);
    });

    // Close search results when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.rj-admin-order-product-search').length) {
            searchResults && searchResults.hide();
        }
    });

    // Form submission handler
    $('.rj-admin-order-form').on('submit', function(e) {
        if (!selectedProducts.find('.rj-product-item').length) {
            e.preventDefault();
            alert('Please select at least one product.');
            return;
        }

        const phone = $('#phone').val();
        if (phone.length !== 10) {
            e.preventDefault();
            alert('Please enter a valid 10-digit phone number.');
            return;
        }
    });
}); 