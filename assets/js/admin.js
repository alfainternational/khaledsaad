/**
 * Admin Panel JavaScript
 * خالد سعد - لوحة التحكم
 */

document.addEventListener('DOMContentLoaded', function() {
    initSidebar();
    initThemeToggle();
    initModals();
    initConfirmDelete();
    initTabs();
    initFileUpload();
    initDataTables();
});

/**
 * Sidebar Toggle
 */
function initSidebar() {
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('adminSidebar');
    const sidebarClose = document.getElementById('sidebarClose');

    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', () => {
            sidebar.classList.add('active');
        });
    }

    if (sidebarClose && sidebar) {
        sidebarClose.addEventListener('click', () => {
            sidebar.classList.remove('active');
        });
    }

    // Close sidebar on outside click
    document.addEventListener('click', (e) => {
        if (sidebar && sidebar.classList.contains('active') &&
            !sidebar.contains(e.target) &&
            !menuToggle.contains(e.target)) {
            sidebar.classList.remove('active');
        }
    });
}

/**
 * Theme Toggle
 */
function initThemeToggle() {
    const themeToggle = document.getElementById('themeToggle');

    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            document.body.classList.toggle('dark-mode');
            const isDark = document.body.classList.contains('dark-mode');
            document.cookie = `darkMode=${isDark};path=/;max-age=31536000`;
        });
    }
}

/**
 * Modal Functions
 */
function initModals() {
    // Close modal on overlay click
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                closeModal(overlay.id);
            }
        });
    });

    // Close modal on close button
    document.querySelectorAll('.modal-close').forEach(btn => {
        btn.addEventListener('click', () => {
            const modal = btn.closest('.modal-overlay');
            if (modal) {
                modal.classList.remove('active');
            }
        });
    });

    // Close on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.active').forEach(modal => {
                modal.classList.remove('active');
            });
        }
    });
}

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
    }
}

/**
 * Confirm Delete
 */
function initConfirmDelete() {
    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', (e) => {
            const message = el.dataset.confirm || 'هل أنت متأكد من هذا الإجراء؟';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });
}

/**
 * Tabs
 */
function initTabs() {
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const tabGroup = btn.closest('.tabs');
            const contentGroup = tabGroup.nextElementSibling;
            const target = btn.dataset.tab;

            // Remove active from all tabs
            tabGroup.querySelectorAll('.tab-btn').forEach(t => t.classList.remove('active'));
            btn.classList.add('active');

            // Show target content
            if (contentGroup) {
                contentGroup.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                const targetContent = contentGroup.querySelector(`[data-tab-content="${target}"]`);
                if (targetContent) {
                    targetContent.classList.add('active');
                }
            }
        });
    });
}

/**
 * File Upload
 */
function initFileUpload() {
    document.querySelectorAll('.file-upload').forEach(upload => {
        const input = upload.querySelector('input[type="file"]');

        upload.addEventListener('click', () => {
            input?.click();
        });

        upload.addEventListener('dragover', (e) => {
            e.preventDefault();
            upload.classList.add('dragover');
        });

        upload.addEventListener('dragleave', () => {
            upload.classList.remove('dragover');
        });

        upload.addEventListener('drop', (e) => {
            e.preventDefault();
            upload.classList.remove('dragover');
            if (input && e.dataTransfer.files.length) {
                input.files = e.dataTransfer.files;
                handleFileSelect(input);
            }
        });

        if (input) {
            input.addEventListener('change', () => handleFileSelect(input));
        }
    });
}

function handleFileSelect(input) {
    const file = input.files[0];
    if (!file) return;

    const upload = input.closest('.file-upload');
    const preview = upload.querySelector('.file-preview');

    if (preview && file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = (e) => {
            preview.innerHTML = `<img src="${e.target.result}" alt="Preview" style="max-width:200px;max-height:200px;">`;
        };
        reader.readAsDataURL(file);
    }
}

/**
 * Data Tables
 */
function initDataTables() {
    // Select all checkbox
    document.querySelectorAll('.select-all').forEach(checkbox => {
        checkbox.addEventListener('change', (e) => {
            const table = checkbox.closest('table');
            table.querySelectorAll('.select-item').forEach(item => {
                item.checked = e.target.checked;
            });
            updateBulkActions();
        });
    });

    // Individual checkbox
    document.querySelectorAll('.select-item').forEach(checkbox => {
        checkbox.addEventListener('change', updateBulkActions);
    });
}

function updateBulkActions() {
    const selected = document.querySelectorAll('.select-item:checked').length;
    const bulkActions = document.querySelector('.bulk-actions');

    if (bulkActions) {
        bulkActions.style.display = selected > 0 ? 'flex' : 'none';
        const countSpan = bulkActions.querySelector('.selected-count');
        if (countSpan) {
            countSpan.textContent = selected;
        }
    }
}

function getSelectedIds() {
    return Array.from(document.querySelectorAll('.select-item:checked')).map(el => el.value);
}

/**
 * Notifications
 */
function showNotification(type, message) {
    const container = document.getElementById('notifications');
    if (!container) return;

    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'exclamation-triangle'}"></i>
        <span>${message}</span>
    `;

    container.appendChild(notification);

    setTimeout(() => {
        notification.style.animation = 'slideIn 0.3s ease reverse';
        setTimeout(() => notification.remove(), 300);
    }, 4000);
}

/**
 * AJAX Form Submit
 */
function submitForm(form, callback) {
    const formData = new FormData(form);
    const submitBtn = form.querySelector('[type="submit"]');

    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الحفظ...';
    }

    fetch(form.action, {
        method: form.method || 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('success', data.message || 'تم الحفظ بنجاح');
            if (callback) callback(data);
        } else {
            showNotification('error', data.message || 'حدث خطأ');
        }
    })
    .catch(error => {
        showNotification('error', 'حدث خطأ في الاتصال');
    })
    .finally(() => {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-save"></i> حفظ';
        }
    });
}

/**
 * Delete Item
 */
function deleteItem(url, itemId, itemName) {
    if (!confirm(`هل أنت متأكد من حذف "${itemName}"؟`)) return;

    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=delete&id=${itemId}&<?= CSRF_TOKEN_NAME ?>=<?= Security::generateCSRFToken() ?>`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('success', 'تم الحذف بنجاح');
            const row = document.querySelector(`tr[data-id="${itemId}"]`);
            if (row) {
                row.style.animation = 'fadeOut 0.3s ease';
                setTimeout(() => row.remove(), 300);
            } else {
                location.reload();
            }
        } else {
            showNotification('error', data.message || 'فشل الحذف');
        }
    })
    .catch(error => {
        showNotification('error', 'حدث خطأ في الاتصال');
    });
}

/**
 * Format Numbers
 */
function formatNumber(num) {
    return new Intl.NumberFormat('ar-SA').format(num);
}

/**
 * Format Date
 */
function formatDate(date) {
    return new Intl.DateTimeFormat('ar-SA', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    }).format(new Date(date));
}

/**
 * Slug Generator
 */
function generateSlug(text) {
    return text
        .toString()
        .toLowerCase()
        .trim()
        .replace(/\s+/g, '-')
        .replace(/[^\w\-ا-ي]+/g, '')
        .replace(/\-\-+/g, '-');
}

// Auto generate slug from title
document.querySelectorAll('[data-slug-source]').forEach(input => {
    const source = document.getElementById(input.dataset.slugSource);
    if (source) {
        source.addEventListener('input', () => {
            if (!input.dataset.manual) {
                input.value = generateSlug(source.value);
            }
        });
        input.addEventListener('input', () => {
            input.dataset.manual = 'true';
        });
    }
});

/**
 * Character Counter
 */
document.querySelectorAll('[data-max-length]').forEach(input => {
    const max = parseInt(input.dataset.maxLength);
    const counter = document.createElement('span');
    counter.className = 'char-counter';
    counter.style.cssText = 'font-size:0.75rem;color:var(--admin-text-muted);float:left;';
    input.parentNode.appendChild(counter);

    const updateCounter = () => {
        const remaining = max - input.value.length;
        counter.textContent = `${remaining} حرف متبقي`;
        counter.style.color = remaining < 20 ? 'var(--admin-danger)' : 'var(--admin-text-muted)';
    };

    input.addEventListener('input', updateCounter);
    updateCounter();
});

/**
 * Auto Save Draft
 */
let autoSaveTimer;
function initAutoSave(form, interval = 30000) {
    form.addEventListener('input', () => {
        clearTimeout(autoSaveTimer);
        autoSaveTimer = setTimeout(() => {
            const formData = new FormData(form);
            formData.append('auto_save', '1');

            fetch(form.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Draft saved');
                }
            });
        }, interval);
    });
}
