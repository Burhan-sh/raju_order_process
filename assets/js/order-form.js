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
            searchResults && searchResults.hide();
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
                beforeSend: function() {
                    productSearch.addClass('loading');
                },
                success: function(response) {
                    if (response.success && response.data.results) {
                        displaySearchResults(response.data.results);
                    }
                },
                complete: function() {
                    productSearch.removeClass('loading');
                }
            });
        }, 300);
    });

    // Display search results
    function displaySearchResults(results) {
        initSearchResults();
        searchResults.empty();

        if (results.length === 0) {
            searchResults.append('<div class="rj-no-results">No products found</div>');
            searchResults.show();
            return;
        }

        results.forEach(function(product) {
            const resultItem = $(`
                <div class="rj-search-item">
                    <div class="rj-search-item-content">
                        ${product.image ? `<img src="${product.image}" alt="${product.text}" class="rj-product-image">` : ''}
                        <div class="rj-product-details">
                            <div class="rj-product-name">${product.text}</div>
                            <div class="rj-product-price">₹${parseFloat(product.price).toFixed(2)}</div>
                        </div>
                    </div>
                </div>
            `);

            resultItem.on('click', function() {
                if (product.type === 'variable' && product.variations) {
                    showVariationsModal(product);
                } else {
                    addProduct({
                        id: product.id,
                        text: product.text,
                        price: product.price,
                        image: product.image
                    });
                }
                searchResults.hide();
                productSearch.val('');
            });

            searchResults.append(resultItem);
        });

        searchResults.show();
    }

    // Show variations modal
    function showVariationsModal(product) {
        const modal = $(`
            <div class="rj-variations-modal">
                <div class="rj-variations-content">
                    <div class="rj-variations-header">
                        <h3>${product.text}</h3>
                        <span class="rj-variations-close">&times;</span>
                    </div>
                    <div class="rj-variations-list"></div>
                </div>
            </div>
        `);

        const variationsList = modal.find('.rj-variations-list');

        product.variations.forEach(function(variation) {
            const variationItem = $(`
                <div class="rj-variation-item">
                    ${variation.image ? `<img src="${variation.image}" alt="${variation.text}" class="rj-variation-image">` : ''}
                    <div class="rj-variation-details">
                        <div class="rj-variation-name">${variation.text}</div>
                        <div class="rj-variation-price">₹${parseFloat(variation.price).toFixed(2)}</div>
                    </div>
                </div>
            `);

            variationItem.on('click', function() {
                addProduct({
                    id: product.id,
                    variation_id: variation.id,
                    text: product.text + ' - ' + variation.text,
                    price: variation.price,
                    image: variation.image
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
                    ${product.image ? `<img src="${product.image}" alt="${product.text}" class="rj-product-thumbnail">` : ''}
                    <div class="rj-product-details">
                        <strong class="rj-product-name">${product.text}</strong>
                        <div class="rj-product-price">₹${parseFloat(product.price).toFixed(2)}</div>
                    </div>
                </div>
                <div class="rj-product-controls">
                    <input type="number" class="rj-quantity-input" value="1" min="1">
                    <button type="button" class="rj-remove-product">
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                </div>
                <input type="hidden" name="products[]" value='${JSON.stringify({
                    id: product.id,
                    variation_id: product.variation_id || null,
                    price: product.price,
                    quantity: 1
                })}'>
            </div>
        `);

        // Add to order items table
        updateOrderItem(productId, product, 1);

        // Quantity change handler
        productItem.find('.rj-quantity-input').on('change', function() {
            const qty = parseInt($(this).val());
            if (qty < 1) {
                $(this).val(1);
                return;
            }
            updateProductData(productItem, qty);
            updateOrderItem(productId, product, qty);
            updateOrderSummary();
        });

        // Remove product handler
        productItem.find('.rj-remove-product').on('click', function() {
            productItem.fadeOut(300, function() {
                $(this).remove();
                $(`#order-item-${productId}`).remove();
                updateOrderSummary();
            });
        });

        selectedProducts.append(productItem);
        updateOrderSummary();
    }

    // Update product data in hidden input
    function updateProductData(productItem, quantity) {
        const hiddenInput = productItem.find('input[name="products[]"]');
        const data = JSON.parse(hiddenInput.val());
        data.quantity = quantity;
        hiddenInput.val(JSON.stringify(data));
    }

    // Update order item in the summary table
    function updateOrderItem(productId, product, quantity) {
        const price = parseFloat(product.price);
        const total = price * quantity;
        
        let orderItem = $(`#order-item-${productId}`);
        if (orderItem.length === 0) {
            orderItem = $(`
                <tr id="order-item-${productId}">
                    <td>${product.text}</td>
                    <td class="qty">${quantity}</td>
                    <td class="price">₹${price.toFixed(2)}</td>
                    <td class="total">₹${total.toFixed(2)}</td>
                </tr>
            `);
            orderItems.append(orderItem);
        } else {
            orderItem.find('.qty').text(quantity);
            orderItem.find('.total').text(`₹${total.toFixed(2)}`);
        }
    }

    // Update order summary
    function updateOrderSummary() {
        let subtotal = 0;
        
        orderItems.find('tr').each(function() {
            const total = parseFloat($(this).find('.total').text().replace('₹', ''));
            subtotal += total;
        });

        $('#rj-order-subtotal, #rj-order-total').text(`₹${subtotal.toFixed(2)}`);
    }

    // Close search results when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.rj-admin-order-product-search').length) {
            searchResults && searchResults.hide();
        }
    });

    // Form submission handler
    $('.rj-admin-order-form').on('submit', function(e) {
        let isValid = true;
        const errorMessages = [];

        // Validate phone number
        const phone = $('#phone').val().replace(/[^0-9]/g, '');
        if (phone.length !== 10 || phone[0] === '0') {
            isValid = false;
            errorMessages.push('Phone number must be exactly 10 digits without any leading zeros or country code.');
        }

        // Validate landmark
        const landmark = $('#landmark').val().trim();
        if (!landmark) {
            isValid = false;
            errorMessages.push('Landmark / Area is required.');
        }

        // Validate products
        const selectedProductsCount = $('#rj-selected-products .rj-product-item').length;
        if (selectedProductsCount === 0) {
            isValid = false;
            errorMessages.push('Please select at least one product.');
        }

        // Log validation state for debugging
        console.log('Selected products count:', selectedProductsCount);
        console.log('Products validation passed:', selectedProductsCount > 0);
        console.log('Form is valid:', isValid);

        if (!isValid) {
            e.preventDefault();
            alert(errorMessages.join('\n'));
            return false;
        }
    });

    // Phone number validation
    $('#phone').on('input', function() {
        let value = this.value.replace(/[^0-9]/g, '');
        
        // Remove leading zeros
        while (value.length > 0 && value.charAt(0) === '0') {
            value = value.substring(1);
        }
        
        // Take only first 10 digits
        value = value.slice(0, 10);
        
        this.value = value;
    });
}); 