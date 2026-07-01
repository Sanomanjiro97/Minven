/**
 * Menu Access Control JavaScript
 * Menangani kontrol akses untuk tombol-tombol dan form
 */

class MenuAccessControl {
    constructor() {
        const metaBase = document.querySelector('meta[name="minven-base"]');
        const computedBase = metaBase ? metaBase.getAttribute('content') : '/';
        this.basePath = (computedBase && computedBase !== '/') ? (computedBase.endsWith('/') ? computedBase : computedBase + '/') : '/';
        this.init();
    }

    init() {
        this.setupAccessControl();
        this.setupFormAccess();
    }

    // Setup kontrol akses untuk tombol dengan data-access
    setupAccessControl() {
        document.addEventListener('click', (e) => {
            const button = e.target.closest('[data-access]');
            if (button) {
                e.preventDefault();
                const accessData = button.getAttribute('data-access');
                const [menuName, action] = accessData.split(':');
                
                this.checkAccess(menuName, action, () => {
                    // Jika memiliki akses, jalankan aksi
                    const href = button.getAttribute('href');
                    if (href) {
                        window.location.href = href;
                    } else {
                        button.removeAttribute('data-access');
                        button.click();
                    }
                });
            }
        });
    }

    // Setup kontrol akses untuk form
    setupFormAccess() {
        document.addEventListener('submit', (e) => {
            const form = e.target.closest('form[data-access]');
            if (form) {
                e.preventDefault();
                const accessData = form.getAttribute('data-access');
                const [menuName, action] = accessData.split(':');
                
                this.checkAccess(menuName, action, () => {
                    // Jika memiliki akses, submit form
                    form.removeAttribute('data-access');
                    form.submit();
                });
            }
        });
    }

    // Check akses via AJAX
    checkAccess(menuName, action, callback) {
        const url = this.basePath + 'ajax/check_menu_access.php';
        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `menu_name=${encodeURIComponent(menuName)}&action=${encodeURIComponent(action)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.has_access) {
                callback();
            } else {
                this.showUnauthorizedMessage(menuName, action);
            }
        })
        .catch(error => {
            console.error('Error checking access:', error);
            this.showUnauthorizedMessage(menuName, action);
        });
    }

    // Tampilkan pesan unauthorized
    showUnauthorizedMessage(menuName, action) {
        const actionNames = {
            'view': 'melihat',
            'add': 'menambah',
            'edit': 'mengedit',
            'delete': 'menghapus'
        };
        
        const actionText = actionNames[action] || action;
        const menuNames = {
            'barang': 'Barang',
            'gudang': 'Gudang',
            'kategori': 'Kategori',
            'mapping_items': 'Mapping Items',
            'satuan': 'Satuan',
            'supplier': 'Supplier',
            'stok_masuk': 'Stok Masuk',
            'stok_keluar': 'Stok Keluar',
            'stok_transfer': 'Stok Transfer',
            'purchase_order': 'Purchase Order',
            'pembelian_direct': 'Pembelian Direct'
        };
        
        const menuText = menuNames[menuName] || menuName;
        
        alert(`Anda tidak memiliki akses untuk ${actionText} menu ${menuText}!`);
    }

    // Helper function untuk menambahkan data-access ke tombol
    static addAccessControl(selector, menuName, action) {
        const elements = document.querySelectorAll(selector);
        elements.forEach(element => {
            element.setAttribute('data-access', `${menuName}:${action}`);
        });
    }

    // Helper function untuk menambahkan data-access ke form
    static addFormAccessControl(selector, menuName, action) {
        const forms = document.querySelectorAll(selector);
        forms.forEach(form => {
            form.setAttribute('data-access', `${menuName}:${action}`);
        });
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.menuAccessControl = new MenuAccessControl();
});

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = MenuAccessControl;
} 
