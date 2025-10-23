// Core JavaScript utilities for FunaGig
// Shared utilities, API calls, localStorage management

// API Configuration
const API_BASE_URL = '/funagig/php/api.php';

// Utility Functions
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 12px 20px;
        background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
        color: white;
        border-radius: 8px;
        z-index: 1000;
        animation: slideIn 0.3s ease;
    `;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

// API Fetch wrapper
async function apiFetch(endpoint, options = {}) {
    const url = `${API_BASE_URL}${endpoint}`;
    const defaultOptions = {
        headers: {
            'Content-Type': 'application/json',
        },
    };
    
    const config = { ...defaultOptions, ...options };
    
    try {
        const response = await fetch(url, config);
        
        // Check if response is ok
        if (!response.ok) {
            let errorMessage = 'Request failed';
            try {
                const errorData = await response.json();
                errorMessage = errorData.error || errorMessage;
            } catch (parseError) {
                errorMessage = `HTTP ${response.status}: ${response.statusText}`;
            }
            throw new Error(errorMessage);
        }
        
        // Try to parse JSON response
        let data;
        try {
            data = await response.json();
        } catch (parseError) {
            throw new Error('Invalid response format from server');
        }
        
        return data;
    } catch (error) {
        console.error('API Error:', error);
        
        // Show user-friendly error message
        let userMessage = 'Network error. Please try again.';
        if (error.message.includes('Failed to fetch')) {
            userMessage = 'Unable to connect to server. Please check your internet connection.';
        } else if (error.message.includes('Invalid response format')) {
            userMessage = 'Server returned invalid data. Please try again.';
        } else if (error.message) {
            userMessage = error.message;
        }
        
        showNotification(userMessage, 'error');
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

