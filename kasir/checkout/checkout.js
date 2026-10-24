<?php
/**
 * Checkout JavaScript Handler
 * Keyboard shortcuts, barcode scanning, product search
 */

$(document).ready(function() {
    let cart = [];
    let selectedVoucher = null;

    // Barcode/Product Search Handler
    $('#productSearch').on('keypress', function(e) {
        if (e.which === 13) { // Enter key
            const query = $(this).val();
            searchProduct(query);
            $(this).val('');
        }
    });

    // Function to search product
    function searchProduct(query) {
        $.ajax({
            url: '../api/search_product.php',
            method: 'POST',
            data: { q: query },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data) {
                    addToCart(response.data);
                } else {
                    alert('Produk tidak ditemukan');
                }
            }
        });
    }

    // Add to cart
    function addToCart(product) {
        const existingItem = cart.find(item => item.id === product.id);
        
        if (existingItem) {
            existingItem.qty += 1;
        } else {
            cart.push({
                id: product.id,
                name: product.name,
                price: product.price,
                qty: 1
            });
        }
        
        updateCartDisplay();
    }

    // Update cart display
    function updateCartDisplay() {
        let html = '';
        let subtotal = 0;

        cart.forEach((item, index) => {
            const itemTotal = item.price * item.qty;
            subtotal += itemTotal;
            html += `
                <tr>
                    <td>${item.name}</td>
                    <td>
                        <input type="number" value="${item.qty}" min="1" 
                            onchange="updateQty(${index}, this.value)">
                    </td>
                    <td>Rp ${number_format(item.price)}</td>
                    <td>Rp ${number_format(itemTotal)}</td>
                    <td>
                        <button class="btn btn-sm btn-danger" 
                            onclick="removeFromCart(${index})">Hapus</button>
                    </td>
                </tr>
            `;
        });

        $('#cartBody').html(html);
        updateSummary(subtotal);
    }

    // Update summary
    function updateSummary(subtotal) {
        const discount = selectedVoucher ? selectedVoucher.discount_value : 0;
        const total = subtotal - discount;

        $('#subtotal').text('Rp ' + number_format(subtotal));
        $('#discount').text('Rp ' + number_format(discount));
        $('#total').text('Rp ' + number_format(total));
    }

    // Keyboard shortcuts
    $(document).on('keydown', function(e) {
        if (e.altKey) {
            switch(e.key.toLowerCase()) {
                case 'p': // Alt+P for Payment
                    $('#paymentBtn').click();
                    break;
                case 'h': // Alt+H for Hold
                    $('#holdBtn').click();
                    break;
                case 'v': // Alt+V for Voucher
                    $('#voucherBtn').click();
                    break;
            }
        }
    });

    // Payment handler
    $('#paymentBtn').on('click', function() {
        if (cart.length === 0) {
            alert('Keranjang kosong');
            return;
        }
        // Redirect to payment module
        window.location.href = '../payments/process.php?items=' + JSON.stringify(cart);
    });

    // Hold transaction
    $('#holdBtn').on('click', function() {
        if (cart.length === 0) {
            alert('Keranjang kosong');
            return;
        }
        // Save and hold transaction
        saveHeldTransaction(cart);
    });

    // Voucher handler
    $('#voucherBtn').on('click', function() {
        // Open voucher selection modal
        window.location.href = '../vouchers_promo/select.php';
    });
});

function number_format(num) {
    return new Intl.NumberFormat('id-ID').format(num);
}

// Stub functions - implement later
function updateQty(index, qty) {
    // Update quantity
}

function removeFromCart(index) {
    // Remove item from cart
}

function saveHeldTransaction(cart) {
    // Save to database and clear cart
}
