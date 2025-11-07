// Core JavaScript utilities for FunaGig
// Shared utilities, API calls, localStorage management

// API Configuration
const API_BASE_URL = '/funagig/php/api.php';

// Loading State Utilities
const Loading = {
    show(element, text = 'Loading...') {
        if (!element) return;
        
        // Create loading overlay
        const overlay = document.createElement('div');
        overlay.className = 'loading-overlay';
        overlay.innerHTML = `
            <div style="text-align: center;">
                <div class="loading-spinner" style="margin: 0 auto 12px;"></div>
                <div class="subtle">${text}</div>
            </div>
        `;
        
        // Make parent relative if not already
        const position = window.getComputedStyle(element).position;
        if (position === 'static') {
            element.style.position = 'relative';
        }
        
        element.appendChild(overlay);
        return overlay;
    },
    
    hide(element) {
        if (!element) return;
        const overlay = element.querySelector('.loading-overlay');
        if (overlay) {
            overlay.remove();
        }
    },
    
    setButtonLoading(button, isLoading) {
        if (!button) return;
        
        if (isLoading) {
            button.classList.add('loading');
            button.disabled = true;
            button.dataset.originalText = button.textContent;
        } else {
            button.classList.remove('loading');
            button.disabled = false;
            if (button.dataset.originalText) {
                button.textContent = button.dataset.originalText;
            }
        }
    }
};

// Confirmation Dialog Utilities
const Confirm = {
    show(options) {
        return new Promise((resolve) => {
            // Create modal if it doesn't exist
            let modal = document.getElementById('confirmationModal');
            if (!modal) {
                modal = document.createElement('div');
                modal.id = 'confirmationModal';
                modal.className = 'confirmation-modal';
                modal.innerHTML = `
                    <div class="confirmation-modal-content">
                        <div class="confirmation-modal-icon" id="confirmIcon">⚠️</div>
                        <div class="confirmation-modal-title" id="confirmTitle">Confirm Action</div>
                        <div class="confirmation-modal-message" id="confirmMessage">Are you sure you want to proceed?</div>
                        <div class="confirmation-modal-actions">
                            <button class="btn secondary" id="confirmCancel">Cancel</button>
                            <button class="btn" id="confirmOk">Confirm</button>
                        </div>
                    </div>
                `;
                document.body.appendChild(modal);
                
                // Event listeners will be set up per-show() call
                // They're handled in the show() method itself
            }
            
            // Update modal content
            const icon = document.getElementById('confirmIcon');
            const title = document.getElementById('confirmTitle');
            const message = document.getElementById('confirmMessage');
            const okBtn = document.getElementById('confirmOk');
            const cancelBtn = document.getElementById('confirmCancel');
            
            icon.textContent = options.icon || '⚠️';
            title.textContent = options.title || 'Confirm Action';
            message.textContent = options.message || 'Are you sure you want to proceed?';
            okBtn.textContent = options.okText || 'Confirm';
            cancelBtn.textContent = options.cancelText || 'Cancel';
            
            // Style OK button based on type
            if (options.type === 'danger') {
                okBtn.classList.add('btn-danger');
                okBtn.classList.remove('btn');
            } else {
                okBtn.classList.remove('btn-danger');
                okBtn.classList.add('btn');
            }
            
            // Store resolve for cleanup
            modal._currentResolve = resolve;
            
            // Initialize handlers if first time
            if (!modal._initialized) {
                // Setup permanent button handlers
                document.getElementById('confirmCancel').addEventListener('click', () => {
                    if (modal._currentResolve) {
                        modal.classList.remove('active');
                        if (modal._escapeHandler) {
                            document.removeEventListener('keydown', modal._escapeHandler);
                            modal._escapeHandler = null;
                        }
                        modal._currentResolve(false);
                        modal._currentResolve = null;
                    }
                });
                
                document.getElementById('confirmOk').addEventListener('click', () => {
                    if (modal._currentResolve) {
                        modal.classList.remove('active');
                        if (modal._escapeHandler) {
                            document.removeEventListener('keydown', modal._escapeHandler);
                            modal._escapeHandler = null;
                        }
                        modal._currentResolve(true);
                        modal._currentResolve = null;
                    }
                });
                
                // Backdrop click
                modal.addEventListener('click', (e) => {
                    if (e.target === modal && modal._currentResolve) {
                        modal.classList.remove('active');
                        if (modal._escapeHandler) {
                            document.removeEventListener('keydown', modal._escapeHandler);
                            modal._escapeHandler = null;
                        }
                        modal._currentResolve(false);
                        modal._currentResolve = null;
                    }
                });
                
                modal._initialized = true;
            }
            
            // Store resolve for this instance
            modal._currentResolve = resolve;
            
            // Setup escape key handler
            const handleEscape = (e) => {
                if (e.key === 'Escape' && modal.classList.contains('active') && modal._currentResolve) {
                    modal.classList.remove('active');
                    document.removeEventListener('keydown', handleEscape);
                    modal._currentResolve(false);
                    modal._currentResolve = null;
                    modal._escapeHandler = null;
                }
            };
            
            // Remove previous escape handler if exists
            if (modal._escapeHandler) {
                document.removeEventListener('keydown', modal._escapeHandler);
            }
            modal._escapeHandler = handleEscape;
            document.addEventListener('keydown', handleEscape);
            
            // Show modal
            modal.classList.add('active');
        });
    },
    
    delete(message = 'This action cannot be undone.') {
        return this.show({
            icon: '🗑️',
            title: 'Confirm Delete',
            message: message,
            okText: 'Delete',
            cancelText: 'Cancel',
            type: 'danger'
        });
    },
    
    action(title, message, okText = 'Confirm') {
        return this.show({
            title: title,
            message: message,
            okText: okText,
            cancelText: 'Cancel'
        });
    }
};

// Application Modal Utilities
const ApplicationModal = {
    show(gigTitle, placeholder = 'Tell the business why you\'re interested in this gig...') {
        return new Promise((resolve) => {
            // Create modal if it doesn't exist
            let modal = document.getElementById('applicationModal');
            if (!modal) {
                modal = document.createElement('div');
                modal.id = 'applicationModal';
                modal.className = 'application-modal';
                modal.innerHTML = `
                    <div class="application-modal-content">
                        <div class="application-modal-title" id="applicationModalTitle">Apply to Gig</div>
                        <div class="application-modal-message" id="applicationModalMessage"></div>
                        <textarea id="applicationMessage" placeholder="${placeholder}" required></textarea>
                        <div class="application-modal-actions">
                            <button class="btn secondary" id="applicationCancel">Cancel</button>
                            <button class="btn" id="applicationSubmit">Submit Application</button>
                        </div>
                    </div>
                `;
                document.body.appendChild(modal);
                
                // Setup event listeners
                document.getElementById('applicationCancel').addEventListener('click', () => {
                    modal.classList.remove('active');
                    resolve(null);
                });
                
                document.getElementById('applicationSubmit').addEventListener('click', () => {
                    const message = document.getElementById('applicationMessage').value.trim();
                    if (!message) {
                        showNotification('Please add a message to your application', 'error');
                        return;
                    }
                    modal.classList.remove('active');
                    resolve(message);
                });
                
                // Close on backdrop click
                modal.addEventListener('click', (e) => {
                    if (e.target === modal) {
                        modal.classList.remove('active');
                        resolve(null);
                    }
                });
                
                // Submit on Enter+Ctrl/Cmd
                document.getElementById('applicationMessage').addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                        e.preventDefault();
                        document.getElementById('applicationSubmit').click();
                    }
                });
            }
            
            // Update modal content
            document.getElementById('applicationModalTitle').textContent = `Apply to "${gigTitle}"`;
            document.getElementById('applicationModalMessage').textContent = 'Add a message to your application:';
            document.getElementById('applicationMessage').value = '';
            document.getElementById('applicationMessage').placeholder = placeholder;
            
            // Focus on textarea
            setTimeout(() => {
                document.getElementById('applicationMessage').focus();
            }, 100);
            
            // Show modal
            modal.classList.add('active');
        });
    }
};

// Sort Utilities
const Sort = {
    byDate(array, field = 'created_at', order = 'desc') {
        return [...array].sort((a, b) => {
            const dateA = new Date(a[field] || 0);
            const dateB = new Date(b[field] || 0);
            return order === 'asc' ? dateA - dateB : dateB - dateA;
        });
    },
    
    byNumber(array, field, order = 'desc') {
        return [...array].sort((a, b) => {
            const numA = parseFloat(a[field] || 0);
            const numB = parseFloat(b[field] || 0);
            return order === 'asc' ? numA - numB : numB - numA;
        });
    },
    
    byString(array, field, order = 'asc') {
        return [...array].sort((a, b) => {
            const strA = (a[field] || '').toLowerCase();
            const strB = (b[field] || '').toLowerCase();
            if (order === 'asc') {
                return strA.localeCompare(strB);
            } else {
                return strB.localeCompare(strA);
            }
        });
    },
    
    custom(array, sortFn) {
        return [...array].sort(sortFn);
    },
    
    byField(array, field, order = 'asc', type = 'auto') {
        if (array.length === 0) return array;
        
        const firstValue = array[0][field];
        let detectedType = type;
        
        if (type === 'auto') {
            if (firstValue instanceof Date || (typeof firstValue === 'string' && !isNaN(Date.parse(firstValue)))) {
                detectedType = 'date';
            } else if (typeof firstValue === 'number' || !isNaN(parseFloat(firstValue))) {
                detectedType = 'number';
            } else {
                detectedType = 'string';
            }
        }
        
        switch (detectedType) {
            case 'date':
                return this.byDate(array, field, order);
            case 'number':
                return this.byNumber(array, field, order);
            case 'string':
            default:
                return this.byString(array, field, order);
        }
    }
};

// Pagination Utilities
const Pagination = {
    create(currentPage, totalPages, onPageChange, options = {}) {
        if (totalPages <= 1 && !options.alwaysShow) {
            return '';
        }
        
        const maxVisible = options.maxVisible || 5;
        const container = document.createElement('div');
        container.className = 'pagination';
        
        let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2));
        let endPage = Math.min(totalPages, startPage + maxVisible - 1);
        
        if (endPage - startPage < maxVisible - 1) {
            startPage = Math.max(1, endPage - maxVisible + 1);
        }
        
        let html = '';
        
        // First page button
        if (startPage > 1) {
            html += `<button class="pagination-btn" data-page="1" ${currentPage === 1 ? 'disabled' : ''}>First</button>`;
        }
        
        // Previous button
        html += `<button class="pagination-btn" data-page="${currentPage - 1}" ${currentPage === 1 ? 'disabled' : ''}>‹ Prev</button>`;
        
        // Page numbers
        for (let i = startPage; i <= endPage; i++) {
            html += `<button class="pagination-btn ${i === currentPage ? 'active' : ''}" data-page="${i}">${i}</button>`;
        }
        
        // Next button
        html += `<button class="pagination-btn" data-page="${currentPage + 1}" ${currentPage === totalPages ? 'disabled' : ''}>Next ›</button>`;
        
        // Last page button
        if (endPage < totalPages) {
            html += `<button class="pagination-btn" data-page="${totalPages}" ${currentPage === totalPages ? 'disabled' : ''}>Last</button>`;
        }
        
        // Page info
        html += `<span class="pagination-info">Page ${currentPage} of ${totalPages}</span>`;
        
        container.innerHTML = html;
        
        // Add event listeners
        container.querySelectorAll('.pagination-btn').forEach(btn => {
            if (!btn.disabled) {
                btn.addEventListener('click', () => {
                    const page = parseInt(btn.dataset.page);
                    if (page !== currentPage && page >= 1 && page <= totalPages) {
                        onPageChange(page);
                    }
                });
            }
        });
        
        return container;
    },
    
    paginate(array, page, perPage) {
        const start = (page - 1) * perPage;
        const end = start + perPage;
        return {
            data: array.slice(start, end),
            total: array.length,
            page: page,
            perPage: perPage,
            totalPages: Math.ceil(array.length / perPage),
            hasNext: end < array.length,
            hasPrev: page > 1
        };
    }
};

// Debounce Utility with loading indicator support
const Debounce = {
    create(func, wait = 300, options = {}) {
        let timeout;
        const loadingElement = options.loadingElement || null;
        const showLoading = options.showLoading === true;
        
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                
                // Show loading indicator if option is provided
                if (showLoading && loadingElement) {
                    loadingElement.classList.add('loading');
                }
                
                // Execute the function
                const result = func(...args);
                
                // Hide loading indicator after a short delay
                if (showLoading && loadingElement) {
                    setTimeout(() => {
                        if (loadingElement) {
                            loadingElement.classList.remove('loading');
                        }
                    }, 300);
                }
                
                return result;
            };
            
            clearTimeout(timeout);
            
            // Clear loading if user continues typing
            if (showLoading && loadingElement) {
                loadingElement.classList.remove('loading');
            }
            
            timeout = setTimeout(later, wait);
        };
    }
};

// URL State Management Utility
const URLState = {
    // Get URL parameter value
    get(key, defaultValue = null) {
        const params = new URLSearchParams(window.location.search);
        return params.get(key) || defaultValue;
    },
    
    // Set URL parameter (updates URL without reload)
    set(key, value, options = {}) {
        const params = new URLSearchParams(window.location.search);
        
        if (value === null || value === '' || value === undefined) {
            params.delete(key);
        } else {
            params.set(key, value);
        }
        
        // Update URL
        const newUrl = options.replace !== false 
            ? `${window.location.pathname}${params.toString() ? '?' + params.toString() : ''}${window.location.hash}`
            : window.location.href.split('?')[0] + (params.toString() ? '?' + params.toString() : '') + window.location.hash;
        
        if (options.push !== false) {
            window.history.pushState({}, '', newUrl);
        } else {
            window.history.replaceState({}, '', newUrl);
        }
        
        // Trigger custom event for state change
        window.dispatchEvent(new CustomEvent('urlstatechange', { 
            detail: { key, value, params: Object.fromEntries(params) } 
        }));
    },
    
    // Set multiple parameters at once
    setMultiple(paramsObj, options = {}) {
        Object.entries(paramsObj).forEach(([key, value]) => {
            this.set(key, value, { ...options, push: false }); // Only push on last update
        });
        // Push state once after all updates
        if (options.push !== false) {
            const params = new URLSearchParams(window.location.search);
            const newUrl = `${window.location.pathname}${params.toString() ? '?' + params.toString() : ''}${window.location.hash}`;
            window.history.pushState({}, '', newUrl);
        }
    },
    
    // Remove URL parameter
    remove(key) {
        this.set(key, null);
    },
    
    // Get all URL parameters as object
    getAll() {
        const params = new URLSearchParams(window.location.search);
        return Object.fromEntries(params);
    },
    
    // Clear all URL parameters
    clear() {
        window.history.pushState({}, '', window.location.pathname + window.location.hash);
    },
    
    // Sync form fields with URL parameters
    syncFromURL(fieldMapping, options = {}) {
        const params = this.getAll();
        Object.entries(fieldMapping).forEach(([urlKey, fieldSelector]) => {
            const value = params[urlKey];
            if (value !== undefined) {
                const field = document.querySelector(fieldSelector);
                if (field) {
                    field.value = value;
                    // Trigger change event if option is set
                    if (options.triggerChange) {
                        field.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }
            }
        });
    },
    
    // Sync form fields to URL parameters
    syncToURL(fieldMapping, options = {}) {
        const updates = {};
        Object.entries(fieldMapping).forEach(([urlKey, fieldSelector]) => {
            const field = document.querySelector(fieldSelector);
            if (field) {
                const value = field.value || null;
                if (value) {
                    updates[urlKey] = value;
                } else {
                    updates[urlKey] = null; // Remove from URL
                }
            }
        });
        this.setMultiple(updates, options);
    },
    
    // Watch for URL changes (e.g., browser back/forward)
    watch(callback) {
        window.addEventListener('popstate', callback);
        window.addEventListener('urlstatechange', callback);
    }
};

// Error Recovery Utility
const ErrorRecovery = {
    // Create retry button
    createRetryButton(onRetry, label = 'Retry') {
        const button = document.createElement('button');
        button.className = 'btn';
        button.textContent = label;
        button.onclick = () => {
            button.disabled = true;
            button.textContent = 'Retrying...';
            onRetry().finally(() => {
                button.disabled = false;
                button.textContent = label;
            });
        };
        return button;
    },
    
    // Show error with retry option
    showError(container, error, onRetry, customMessage = null) {
        if (!container) return;
        
        const errorMessage = customMessage || error.message || 'An error occurred. Please try again.';
        
        EmptyState.show(
            container,
            '⚠️',
            'Something Went Wrong',
            errorMessage,
            onRetry ? this.createRetryButton(onRetry).outerHTML : null
        );
    },
    
    // Handle API error with automatic retry option
    handleApiError(error, container, retryFn, options = {}) {
        const maxRetries = options.maxRetries || 3;
        const retryCount = options.retryCount || 0;
        
        if (retryCount < maxRetries && error.status >= 500) {
            // Server error - offer retry
            return this.showError(
                container,
                error,
                () => {
                    return retryFn().catch(err => {
                        return this.handleApiError(err, container, retryFn, {
                            ...options,
                            retryCount: retryCount + 1
                        });
                    });
                },
                `Server error (${error.status}). Would you like to try again?`
            );
        } else if (error.status === 0 || error.message?.includes('Failed to fetch')) {
            // Network error
            return this.showError(
                container,
                error,
                () => {
                    return retryFn().catch(err => {
                        return this.handleApiError(err, container, retryFn, {
                            ...options,
                            retryCount: retryCount + 1
                        });
                    });
                },
                'Unable to connect to server. Please check your internet connection.'
            );
        } else {
            // Other errors - show without retry
            return this.showError(
                container,
                error,
                null,
                error.message || 'An error occurred. Please refresh the page.'
            );
        }
    }
};

// Keyboard Shortcuts Utility
const KeyboardShortcuts = {
    shortcuts: new Map(),
    
    register(key, handler, options = {}) {
        const combo = {
            key: key.toLowerCase(),
            ctrl: options.ctrl || false,
            shift: options.shift || false,
            alt: options.alt || false,
            meta: options.meta || false,
            handler: handler,
            preventDefault: options.preventDefault !== false, // Default to true
            target: options.target || document
        };
        
        const keyId = `${combo.ctrl ? 'ctrl+' : ''}${combo.shift ? 'shift+' : ''}${combo.alt ? 'alt+' : ''}${combo.meta ? 'meta+' : ''}${combo.key}`;
        this.shortcuts.set(keyId, combo);
        
        // Add event listener
        combo.target.addEventListener('keydown', (e) => {
            // Skip if user is typing in input, textarea, or contenteditable
            if (options.ignoreInputs !== false) {
                const target = e.target;
                if (target.tagName === 'INPUT' || 
                    target.tagName === 'TEXTAREA' || 
                    target.isContentEditable ||
                    target.closest('[contenteditable="true"]')) {
                    return;
                }
            }
            
            const pressedKey = e.key.toLowerCase();
            const matchCtrl = !combo.ctrl || e.ctrlKey;
            const matchShift = !combo.shift || e.shiftKey;
            const matchAlt = !combo.alt || e.altKey;
            const matchMeta = !combo.meta || e.metaKey;
            const matchKey = pressedKey === combo.key;
            
            if (matchKey && matchCtrl && matchShift && matchAlt && matchMeta) {
                if (combo.preventDefault) {
                    e.preventDefault();
                }
                combo.handler(e);
            }
        });
    },
    
    unregister(keyId) {
        this.shortcuts.delete(keyId);
    },
    
    init() {
        // Register global shortcuts
        this.register('k', () => {
            // Focus search input if available
            const searchInputs = document.querySelectorAll('input[type="text"][placeholder*="Search" i], input[type="text"][id*="search" i]');
            if (searchInputs.length > 0) {
                searchInputs[0].focus();
            }
        }, { ctrl: true, meta: true, ignoreInputs: false });
        
        // Escape to close modals
        this.register('Escape', () => {
            // Close confirmation modal
            const confirmModal = document.getElementById('confirmationModal');
            if (confirmModal && confirmModal.classList.contains('active')) {
                confirmModal.classList.remove('active');
            }
            
            // Close application modal
            const appModal = document.getElementById('applicationModal');
            if (appModal && appModal.classList.contains('active')) {
                appModal.classList.remove('active');
            }
            
            // Close any other modals with class 'active'
            document.querySelectorAll('.modal.active, [class*="modal"].active').forEach(modal => {
                modal.classList.remove('active');
            });
        }, { ignoreInputs: false });
    }
};

// Retry Utility for API calls
const Retry = {
    async execute(fn, maxRetries = 3, delay = 1000, backoff = true) {
        let lastError;
        
        for (let attempt = 1; attempt <= maxRetries; attempt++) {
            try {
                return await fn();
            } catch (error) {
                lastError = error;
                
                // Don't retry on client errors (4xx) except 429 (rate limit)
                if (error.status >= 400 && error.status < 500 && error.status !== 429) {
                    throw error;
                }
                
                // Don't retry on last attempt
                if (attempt === maxRetries) {
                    break;
                }
                
                // Calculate delay with exponential backoff if enabled
                const waitTime = backoff ? delay * Math.pow(2, attempt - 1) : delay;
                
                // Wait before retrying
                await new Promise(resolve => setTimeout(resolve, waitTime));
            }
        }
        
        throw lastError;
    }
};

// Empty State Utilities
const EmptyState = {
    create(icon = '📭', title = 'No items found', message = 'There are no items to display at this time.', actionButton = null) {
        const emptyState = document.createElement('div');
        emptyState.className = 'empty-state';
        emptyState.innerHTML = `
            <div class="empty-state-icon">${icon}</div>
            <div class="empty-state-title">${title}</div>
            <div class="empty-state-message">${message}</div>
            ${actionButton ? `<div class="empty-state-action">${actionButton}</div>` : ''}
        `;
        return emptyState;
    },
    
    show(container, icon, title, message, actionButton = null) {
        if (!container) return;
        container.innerHTML = '';
        container.appendChild(this.create(icon, title, message, actionButton));
    }
};

// Toast Notification System
const Toast = {
    notifications: [],
    container: null,
    
    init() {
        if (!this.container) {
            this.container = document.createElement('div');
            this.container.id = 'toast-container';
            this.container.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 10000;
                display: flex;
                flex-direction: column;
                gap: 12px;
                pointer-events: none;
            `;
            document.body.appendChild(this.container);
        }
    },
    
    show(message, type = 'info', duration = 3000, options = {}) {
        this.init();
        
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        
        const icons = {
            success: '✓',
            error: '✕',
            warning: '⚠',
            info: 'ℹ'
        };
        
        const colors = {
            success: '#10b981',
            error: '#ef4444',
            warning: '#f59e0b',
            info: '#3b82f6'
        };
        
        toast.innerHTML = `
            <div class="toast-icon" style="background: ${colors[type] || colors.info}20; color: ${colors[type] || colors.info};">
                ${icons[type] || icons.info}
            </div>
            <div class="toast-content">
                <div class="toast-message">${message}</div>
            </div>
            ${options.closable !== false ? '<button class="toast-close" onclick="this.parentElement.remove()">×</button>' : ''}
        `;
        
        toast.style.cssText = `
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            background: white;
            color: var(--text);
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            min-width: 300px;
            max-width: 400px;
            animation: slideInRight 0.3s ease;
            pointer-events: auto;
            border-left: 4px solid ${colors[type] || colors.info};
        `;
        
        this.container.appendChild(toast);
        this.notifications.push(toast);
        
        // Auto remove after duration
        if (duration > 0) {
            setTimeout(() => {
                this.remove(toast);
            }, duration);
        }
        
        return toast;
    },
    
    remove(toast) {
        if (toast && toast.parentElement) {
            toast.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.remove();
                }
                this.notifications = this.notifications.filter(n => n !== toast);
            }, 300);
        }
    },
    
    success(message, duration = 3000) {
        return this.show(message, 'success', duration);
    },
    
    error(message, duration = 4000) {
        return this.show(message, 'error', duration);
    },
    
    warning(message, duration = 3500) {
        return this.show(message, 'warning', duration);
    },
    
    info(message, duration = 3000) {
        return this.show(message, 'info', duration);
    },
    
    clear() {
        this.notifications.forEach(toast => this.remove(toast));
    }
};

// Backward compatibility
function showNotification(message, type = 'info') {
    Toast.show(message, type);
}

// API Fetch wrapper with retry logic
async function apiFetch(endpoint, options = {}) {
    const url = `${API_BASE_URL}${endpoint}`;
    const defaultOptions = {
        headers: {
            'Content-Type': 'application/json',
        },
    };
    
    // Extract retry options from config
    const retryDisabled = options.retry === false;
    const maxRetries = options.maxRetries || 3;
    const showErrorNotification = options.showError !== false; // Default to true
    const silent = options.silent === true;
    
    // Remove retry options from fetch config to avoid passing them to fetch
    const fetchOptions = { ...options };
    delete fetchOptions.retry;
    delete fetchOptions.maxRetries;
    delete fetchOptions.showError;
    delete fetchOptions.silent;
    
    const config = { ...defaultOptions, ...fetchOptions };
    
    const fetchFn = async () => {
        const response = await fetch(url, config);
        
        // Check if response is ok
        if (!response.ok) {
            let errorMessage = 'Request failed';
            let errorData = null;
            
            try {
                errorData = await response.json();
                errorMessage = errorData.error || errorMessage;
            } catch (parseError) {
                errorMessage = `HTTP ${response.status}: ${response.statusText}`;
            }
            
            const error = new Error(errorMessage);
            error.status = response.status;
            error.data = errorData;
            throw error;
        }
        
        // Try to parse JSON response
        try {
            return await response.json();
        } catch (parseError) {
            const error = new Error('Invalid response format from server');
            error.status = 0; // Indicates parsing error
            throw error;
        }
    };
    
    try {
        let data;
        if (retryDisabled) {
            data = await fetchFn();
        } else {
            data = await Retry.execute(fetchFn, maxRetries, 1000, true);
        }
        return data;
    } catch (error) {
        console.error('API Fetch Error:', error);
        
        // Only show notification if enabled and it's the final error
        if (showErrorNotification && !silent) {
            // Show user-friendly error message
            let userMessage = 'Network error. Please try again.';
            const errorStatus = error.status || 0;
            const errorMsg = error.message || '';
            
            if (errorMsg.includes('Failed to fetch') || errorStatus === 0) {
                userMessage = 'Unable to connect to server. Please check your internet connection.';
            } else if (errorMsg.includes('Invalid response format')) {
                userMessage = 'Server returned invalid data. Please try again.';
            } else if (errorMsg) {
                userMessage = errorMsg;
            }
            
            Toast.error(userMessage);
        }
        
        throw error;
    }
}

// Local Storage utilities
const Storage = {
    set(key, value) {
        try {
            if (typeof key !== 'string') {
                throw new Error('Storage key must be a string');
            }
            localStorage.setItem(key, JSON.stringify(value));
            return true;
        } catch (error) {
            console.error('Storage error:', error);
            showNotification('Failed to save data locally', 'error');
            return false;
        }
    },
    
    get(key, defaultValue = null) {
        try {
            if (typeof key !== 'string') {
                throw new Error('Storage key must be a string');
            }
            const item = localStorage.getItem(key);
            return item ? JSON.parse(item) : defaultValue;
        } catch (error) {
            console.error('Storage error:', error);
            return defaultValue;
        }
    },
    
    remove(key) {
        try {
            if (typeof key !== 'string') {
                throw new Error('Storage key must be a string');
            }
            localStorage.removeItem(key);
            return true;
        } catch (error) {
            console.error('Storage error:', error);
            return false;
        }
    },
    
    clear() {
        try {
            localStorage.clear();
            return true;
        } catch (error) {
            console.error('Storage error:', error);
            return false;
        }
    },
    
    // Check if localStorage is available
    isAvailable() {
        try {
            const test = '__localStorage_test__';
            localStorage.setItem(test, test);
            localStorage.removeItem(test);
            return true;
        } catch (error) {
            return false;
        }
    }
};

// Authentication utilities
const Auth = {
    isLoggedIn() {
        try {
            const user = Storage.get('user');
            return user !== null && typeof user === 'object';
        } catch (error) {
            console.error('Auth check error:', error);
            return false;
        }
    },
    
    getUser() {
        try {
            const user = Storage.get('user');
            if (user && typeof user === 'object') {
                return user;
            }
            return null;
        } catch (error) {
            console.error('Get user error:', error);
            return null;
        }
    },
    
    setUser(user) {
        try {
            if (!user || typeof user !== 'object') {
                throw new Error('Invalid user data');
            }
            Storage.set('user', user);
            return true;
        } catch (error) {
            console.error('Set user error:', error);
            showNotification('Failed to save user data', 'error');
            return false;
        }
    },
    
    logout() {
        try {
            // Call server logout
            apiFetch('/logout', {
                method: 'POST'
            }).catch(error => {
                console.error('Server logout error:', error);
                // Continue with client logout even if server call fails
            });
            
            // Clear local storage
            Storage.remove('user');
            Storage.remove('userType');
            Storage.remove('isLoggedIn');
            Storage.clear(); // Clear all stored data
            window.location.href = 'index.html';
        } catch (error) {
            console.error('Logout error:', error);
            // Force redirect even if storage fails
            window.location.href = 'index.html';
        }
    },
    
    requireAuth() {
        if (!this.isLoggedIn()) {
            window.location.href = 'auth.html';
            return false;
        }
        return true;
    },
    
    requireUserType(userType) {
        if (!this.requireAuth()) {
            return false;
        }
        
        const user = this.getUser();
        const storedUserType = localStorage.getItem('userType');
        
        if (user && user.type !== userType && storedUserType !== userType) {
            // Redirect to appropriate dashboard
            if (userType === 'business') {
                window.location.href = 'student-dashboard.html';
            } else {
                window.location.href = 'business-dashboard.html';
            }
            return false;
        }
        
        return true;
    },
    
    async verifySession() {
        try {
            const response = await apiFetch('/profile');
            if (response.success && response.user) {
                this.setUser(response.user);
                localStorage.setItem('userType', response.user.type);
                return response.user;
            }
            return null;
        } catch (error) {
            // Session invalid, logout
            this.logout();
            return null;
        }
    }
};

// Form validation utilities
const Validation = {
    email(email) {
        const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return regex.test(email);
    },
    
    password(password) {
        return password.length >= 6;
    },
    
    required(value) {
        return value && value.trim().length > 0;
    }
};

// UI utilities
const UI = {
    showLoading(element) {
        element.innerHTML = '<div class="loading">Loading...</div>';
    },
    
    hideLoading(element, content) {
        element.innerHTML = content;
    },
    
    formatDate(date) {
        return new Date(date).toLocaleDateString();
    },
    
    formatCurrency(amount) {
        return new Intl.NumberFormat('en-UG', {
            style: 'currency',
            currency: 'UGX'
        }).format(amount);
    }
};

// Sidebar toggle functionality for mobile
const Sidebar = {
    init() {
        const sidebar = document.querySelector('.sidebar');
        const toggleBtn = document.querySelector('.sidebar-toggle');
        const overlay = document.querySelector('.sidebar-overlay');
        
        if (!sidebar) return;
        
        // Create toggle button if it doesn't exist
        if (!toggleBtn && window.innerWidth <= 900) {
            this.createToggleButton();
        }
        
        // Create overlay if it doesn't exist
        if (!overlay) {
            this.createOverlay();
        }
        
        // Handle toggle button click
        if (toggleBtn) {
            toggleBtn.addEventListener('click', () => {
                this.toggle();
            });
        }
        
        // Handle overlay click
        if (overlay) {
            overlay.addEventListener('click', () => {
                this.close();
            });
        }
        
        // Close sidebar when clicking on nav items (mobile only)
        if (window.innerWidth <= 900) {
            const navItems = sidebar.querySelectorAll('.navitem');
            navItems.forEach(item => {
                item.addEventListener('click', () => {
                    this.close();
                });
            });
        }
        
        // Handle window resize
        window.addEventListener('resize', () => {
            if (window.innerWidth > 900) {
                this.close();
            }
        });
    },
    
    createToggleButton() {
        const navbar = document.querySelector('.navbar');
        if (!navbar) return;
        
        const toggleBtn = document.createElement('button');
        toggleBtn.className = 'sidebar-toggle';
        toggleBtn.innerHTML = '☰';
        toggleBtn.setAttribute('aria-label', 'Toggle menu');
        navbar.appendChild(toggleBtn);
    },
    
    createOverlay() {
        const overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        document.body.appendChild(overlay);
    },
    
    toggle() {
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.querySelector('.sidebar-overlay');
        
        if (sidebar) {
            sidebar.classList.toggle('open');
            if (overlay) {
                overlay.classList.toggle('active');
            }
        }
    },
    
    open() {
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.querySelector('.sidebar-overlay');
        
        if (sidebar) {
            sidebar.classList.add('open');
            if (overlay) {
                overlay.classList.add('active');
            }
        }
    },
    
    close() {
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.querySelector('.sidebar-overlay');
        
        if (sidebar) {
            sidebar.classList.remove('open');
            if (overlay) {
                overlay.classList.remove('active');
            }
        }
    }
};

// Notification badge management
const NotificationBadge = {
    refreshInterval: null,
    
    init() {
        // Add badge HTML to navbar if it doesn't exist
        const navbar = document.querySelector('.navbar');
        if (navbar && !document.getElementById('notificationBadge')) {
            const navbarActions = navbar.querySelector('.navbar-actions');
            if (!navbarActions) {
                const actionsDiv = document.createElement('div');
                actionsDiv.className = 'navbar-actions';
                navbar.appendChild(actionsDiv);
            }
            
            const badgeContainer = document.createElement('div');
            badgeContainer.className = 'notification-badge-container';
            badgeContainer.id = 'notificationBadge';
            badgeContainer.style.display = 'none';
            badgeContainer.onclick = () => {
                // Scroll to notifications section or navigate to dashboard
                const notificationsSection = document.querySelector('#notifications, .section:has(#notifications)');
                if (notificationsSection) {
                    notificationsSection.scrollIntoView({ behavior: 'smooth' });
                } else {
                    // Navigate to dashboard if not on dashboard page
                    const userType = localStorage.getItem('userType');
                    if (userType === 'business') {
                        window.location.href = 'business-dashboard.html';
                    } else if (userType === 'student') {
                        window.location.href = 'student-dashboard.html';
                    }
                }
            };
            
            const badge = document.createElement('span');
            badge.className = 'notification-badge';
            badge.id = 'notificationCount';
            badge.textContent = '0';
            
            badgeContainer.appendChild(badge);
            const actionsDiv = navbar.querySelector('.navbar-actions') || navbar.appendChild(document.createElement('div'));
            actionsDiv.className = 'navbar-actions';
            actionsDiv.appendChild(badgeContainer);
        }
        
        // Load notification count if user is logged in
        if (Auth.isLoggedIn()) {
            this.loadCount();
            
            // Refresh notification count every 30 seconds
            this.refreshInterval = setInterval(() => {
                this.loadCount();
            }, 30000);
        }
    },
    
    async loadCount() {
        try {
            const response = await apiFetch('/notifications');
            if (response.success) {
                this.updateCount(response.unread_count || 0);
            }
        } catch (error) {
            console.error('Failed to load notification count:', error);
        }
    },
    
    updateCount(count) {
        const badgeContainer = document.getElementById('notificationBadge');
        const badgeCount = document.getElementById('notificationCount');
        
        if (badgeContainer && badgeCount) {
            if (count > 0) {
                badgeContainer.style.display = 'block';
                badgeCount.textContent = count > 99 ? '99+' : count;
            } else {
                badgeContainer.style.display = 'none';
            }
        }
    },
    
    destroy() {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
            this.refreshInterval = null;
        }
    }
};

// Initialize app
document.addEventListener('DOMContentLoaded', function() {
    // Add smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({ behavior: 'smooth' });
            }
        });
    });
    
    // Initialize sidebar toggle
    Sidebar.init();
    
    // Initialize notification badge
    NotificationBadge.init();
    
    // Initialize keyboard shortcuts
    KeyboardShortcuts.init();
    
    // Initialize toast notification system
    Toast.init();
    
    // Initialize tooltips and other UI enhancements
    initializeTooltips();
});

function initializeTooltips() {
    // Add tooltip functionality if needed
    console.log('App initialized');
}

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { apiFetch, Storage, Auth, Validation, UI, showNotification };
}

