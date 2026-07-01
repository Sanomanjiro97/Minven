document.addEventListener('DOMContentLoaded', function() {
    console.log('Surat Jalan JavaScript loaded successfully');
    
    // Print functionality
    const printButton = document.getElementById('print-surat-jalan');
    if (printButton) {
        printButton.addEventListener('click', function() {
            window.print();
        });
    }
    
    // Quantity validation
    const quantityInputs = document.querySelectorAll('.quantity-input');
    quantityInputs.forEach(input => {
        input.addEventListener('change', function() {
            const value = parseInt(this.value);
            if (isNaN(value) || value < 0) {
                this.value = 0;
                showToast('Jumlah tidak valid. Harus angka positif.', 'error');
            }
        });
    });
    
    // Form submission handling
    const suratJalanForm = document.getElementById('surat-jalan-form');
    if (suratJalanForm) {
        suratJalanForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Validate all quantities
            let isValid = true;
            const quantities = [];
            
            quantityInputs.forEach(input => {
                const value = parseInt(input.value);
                if (isNaN(value) || value < 0) {
                    isValid = false;
                    input.classList.add('is-invalid');
                } else {
                    input.classList.remove('is-invalid');
                    quantities.push(value);
                }
            });
            
            if (!isValid) {
                showToast('Harap periksa semua jumlah barang. Harus angka positif.', 'error');
                return;
            }
            
            // Check if all quantities are zero
            const totalQuantity = quantities.reduce((sum, qty) => sum + qty, 0);
            if (totalQuantity === 0) {
                showToast('Minimal satu barang harus memiliki jumlah lebih dari 0.', 'error');
                return;
            }
            
            // Submit the form if valid
            this.submit();
        });
    }
    
    // Status change handling
    const statusSelect = document.getElementById('status');
    if (statusSelect) {
        statusSelect.addEventListener('change', function() {
            const selectedStatus = this.value;
            const statusBadge = document.querySelector('.status-badge');
            
            if (statusBadge) {
                // Remove all status classes
                statusBadge.classList.remove('status-draft', 'status-sent', 'status-cancelled');
                
                // Add appropriate class based on selected status
                switch(selectedStatus) {
                    case 'draft':
                        statusBadge.classList.add('status-draft');
                        break;
                    case 'sent':
                        statusBadge.classList.add('status-sent');
                        break;
                    case 'cancelled':
                        statusBadge.classList.add('status-cancelled');
                        break;
                }
                
                statusBadge.textContent = this.options[this.selectedIndex].text;
            }
        });
    }
    
    // Auto-calculate totals
    function calculateTotals() {
        const rows = document.querySelectorAll('.items-table tbody tr:not(.total-row)');
        let totalQuantity = 0;
        
        rows.forEach(row => {
            const quantityInput = row.querySelector('.quantity-input');
            if (quantityInput) {
                const quantity = parseInt(quantityInput.value) || 0;
                totalQuantity += quantity;
            }
        });
        
        // Update total display
        const totalElement = document.getElementById('total-quantity');
        if (totalElement) {
            totalElement.textContent = totalQuantity.toLocaleString('id-ID');
        }
    }
    
    // Initialize totals calculation
    calculateTotals();
    
    // Listen for quantity changes
    quantityInputs.forEach(input => {
        input.addEventListener('input', calculateTotals);
    });
    
    // Toast notification function
    function showToast(message, type = 'info') {
        // Create toast element
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white bg-${type === 'error' ? 'danger' : 'success'} border-0`;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');
        
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas ${type === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle'} me-2"></i>
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        `;
        
        // Add to toast container or create one
        let toastContainer = document.getElementById('toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'toast-container';
            toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
            toastContainer.style.zIndex = '9999';
            document.body.appendChild(toastContainer);
        }
        
        toastContainer.appendChild(toast);
        
        // Initialize and show toast
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();
        
        // Remove toast after it's hidden
        toast.addEventListener('hidden.bs.toast', function() {
            toast.remove();
        });
    }
    
    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl+P or Cmd+P for print
        if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
            e.preventDefault();
            window.print();
        }
        
        // Escape key to close modals
        if (e.key === 'Escape') {
            const openModals = document.querySelectorAll('.modal.show');
            openModals.forEach(modal => {
                bootstrap.Modal.getInstance(modal).hide();
            });
        }
    });
    
    // Responsive table handling
    function handleResponsiveTables() {
        const tables = document.querySelectorAll('.items-table');
        tables.forEach(table => {
            if (table.offsetWidth < table.scrollWidth) {
                table.parentElement.classList.add('table-responsive');
            } else {
                table.parentElement.classList.remove('table-responsive');
            }
        });
    }
    
    // Initialize responsive tables
    handleResponsiveTables();
    window.addEventListener('resize', handleResponsiveTables);
    
    console.log('Surat Jalan JavaScript initialized');
});