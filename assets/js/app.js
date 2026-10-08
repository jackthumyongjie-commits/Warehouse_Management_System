/**
 * Warehouse Management System - Client Side JavaScript
 * Vanilla JavaScript (No jQuery or external frameworks)
 */

document.addEventListener('DOMContentLoaded', () => {
    // 0. Login page: click a demo account to fill username and password
    const accountButtons = document.querySelectorAll('.js-fill-account');
    accountButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const username = document.getElementById('username');
            const password = document.getElementById('password');
            if (username) username.value = btn.getAttribute('data-username') || '';
            if (password) password.value = btn.getAttribute('data-password') || '';
            accountButtons.forEach(el => el.classList.remove('active'));
            btn.classList.add('active');
        });
    });

    // 1. Mobile Sidebar Toggle Functionality
    const mobileToggleBtn = document.getElementById('mobileSidebarToggle');
    const sidebar = document.getElementById('appSidebar');

    if (mobileToggleBtn && sidebar) {
        mobileToggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('open');
        });

        // Close sidebar when clicking outside on mobile view
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(e.target) && !mobileToggleBtn.contains(e.target) && sidebar.classList.contains('open')) {
                    sidebar.classList.remove('open');
                }
            }
        });
    }

    // 2. Safe Delete Confirmations
    const deleteButtons = document.querySelectorAll('.js-confirm-delete');
    deleteButtons.forEach(btn => {
        btn.addEventListener('click', (e) => {
            const itemName = btn.getAttribute('data-name') || 'this item';
            const confirmMsg = btn.getAttribute('data-message') || `Are you sure you want to delete ${itemName}? This action cannot be undone.`;
            if (!confirm(confirmMsg)) {
                e.preventDefault();
            }
        });
    });

    // 3. Auto-hide flash alert messages after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });

    // 4. Client-side Item Code generator or helper functions
    const generateCodeBtn = document.getElementById('js-generate-item-code');
    if (generateCodeBtn) {
        generateCodeBtn.addEventListener('click', () => {
            const inputField = document.getElementById('item_code');
            if (inputField) {
                const randomNum = Math.floor(1000 + Math.random() * 9000);
                inputField.value = `ITEM-${randomNum}`;
            }
        });
    }

    // 5. Dynamic Stock Movement Validation (Prevent client-side submitting OUT > current quantity)
    const stockOutForm = document.getElementById('stockMovementForm');
    if (stockOutForm) {
        const typeSelect = document.getElementById('movement_type');
        const qtyInput = document.getElementById('movement_quantity');
        const itemSelect = document.getElementById('item_id');

        const currentStock = () => {
            if (!itemSelect || itemSelect.selectedIndex < 0) {
                return 0;
            }
            const option = itemSelect.options[itemSelect.selectedIndex];
            return parseInt(option.getAttribute('data-stock') || '0', 10) || 0;
        };

        stockOutForm.addEventListener('submit', (e) => {
            if (!typeSelect || typeSelect.value !== 'OUT' || !qtyInput || !itemSelect || !itemSelect.value) {
                return;
            }
            const requestedQty = parseInt(qtyInput.value, 10);
            const maxQty = currentStock();
            if (Number.isFinite(requestedQty) && requestedQty > maxQty) {
                e.preventDefault();
                alert(`Stock OUT quantity (${requestedQty}) cannot exceed current available stock (${maxQty}).`);
            }
        });
    }
});

/**
 * Open Modal helper function
 */
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('show');
    }
}

/**
 * Close Modal helper function
 */
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('show');
    }
}
