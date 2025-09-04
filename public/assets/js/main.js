// NorthVia E-commerce Platform - Main JavaScript

class NorthViaApp {
    constructor() {
        this.apiBase = '/api';
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.checkAuthStatus();
    }

    setupEventListeners() {
        // Mobile menu toggle
        const menuToggle = document.getElementById('menuToggle');
        const navMenu = document.getElementById('navMenu');
        
        if (menuToggle && navMenu) {
            menuToggle.addEventListener('click', () => {
                navMenu.classList.toggle('show');
            });
        }

        // Form submissions
        const loginForm = document.getElementById('loginForm');
        const registerForm = document.getElementById('registerForm');

        if (loginForm) {
            loginForm.addEventListener('submit', (e) => this.handleLogin(e));
        }

        if (registerForm) {
            registerForm.addEventListener('submit', (e) => this.handleRegister(e));
        }

        // Add to cart buttons
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('add-to-cart')) {
                e.preventDefault();
                this.addToCart(e.target.dataset.productId);
            }
        });

        // Search functionality
        const searchForm = document.getElementById('searchForm');
        if (searchForm) {
            searchForm.addEventListener('submit', (e) => this.handleSearch(e));
        }
    }

    async checkAuthStatus() {
        const token = localStorage.getItem('auth_token');
        if (token) {
            try {
                const response = await this.fetchAPI('/auth/me', {
                    headers: { 'Authorization': `Bearer ${token}` }
                });
                
                if (response.success) {
                    this.updateUIForLoggedInUser(response.data);
                } else {
                    localStorage.removeItem('auth_token');
                }
            } catch (error) {
                console.error('Auth check failed:', error);
                localStorage.removeItem('auth_token');
            }
        }
    }

    async handleLogin(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        
        const loginData = {
            email: formData.get('email'),
            password: formData.get('password')
        };

        try {
            this.showLoading(form);
            const response = await this.fetchAPI('/auth/login', {
                method: 'POST',
                body: JSON.stringify(loginData)
            });

            if (response.success) {
                localStorage.setItem('auth_token', response.data.token);
                this.showMessage('Login successful! Redirecting...', 'success');
                setTimeout(() => {
                    window.location.href = response.data.redirect || '/dashboard.html';
                }, 1500);
            } else {
                this.showMessage(response.message || 'Login failed', 'error');
            }
        } catch (error) {
            this.showMessage('Network error. Please try again.', 'error');
        } finally {
            this.hideLoading(form);
        }
    }

    async handleRegister(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        
        // Validate password confirmation
        if (formData.get('password') !== formData.get('confirm_password')) {
            this.showMessage('Passwords do not match', 'error');
            return;
        }

        const registerData = {
            first_name: formData.get('first_name'),
            last_name: formData.get('last_name'),
            email: formData.get('email'),
            phone: formData.get('phone'),
            password: formData.get('password')
        };

        try {
            this.showLoading(form);
            const response = await this.fetchAPI('/auth/register', {
                method: 'POST',
                body: JSON.stringify(registerData)
            });

            if (response.success) {
                this.showMessage('Registration successful! Please check your email for verification.', 'success');
                form.reset();
                setTimeout(() => {
                    window.location.href = '/login.html';
                }, 2000);
            } else {
                this.showMessage(response.message || 'Registration failed', 'error');
            }
        } catch (error) {
            this.showMessage('Network error. Please try again.', 'error');
        } finally {
            this.hideLoading(form);
        }
    }

    async handleSearch(e) {
        e.preventDefault();
        const form = e.target;
        const query = form.querySelector('input[name="q"]').value.trim();
        
        if (query) {
            window.location.href = `/search.html?q=${encodeURIComponent(query)}`;
        }
    }

    async addToCart(productId) {
        if (!this.isLoggedIn()) {
            this.showMessage('Please login to add items to cart', 'warning');
            setTimeout(() => {
                window.location.href = '/login.html';
            }, 1500);
            return;
        }

        try {
            const response = await this.fetchAPI('/cart/add', {
                method: 'POST',
                body: JSON.stringify({
                    product_id: productId,
                    quantity: 1
                }),
                headers: { 
                    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
                }
            });

            if (response.success) {
                this.showMessage('Item added to cart!', 'success');
                this.updateCartCount();
            } else {
                this.showMessage(response.message || 'Failed to add item to cart', 'error');
            }
        } catch (error) {
            this.showMessage('Network error. Please try again.', 'error');
        }
    }

    async fetchAPI(endpoint, options = {}) {
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                ...options.headers
            }
        };

        const response = await fetch(this.apiBase + endpoint, {
            ...defaultOptions,
            ...options
        });

        return await response.json();
    }

    isLoggedIn() {
        return !!localStorage.getItem('auth_token');
    }

    updateUIForLoggedInUser(userData) {
        // Update navigation for logged-in user
        const guestNav = document.getElementById('guestNav');
        const userNav = document.getElementById('userNav');
        const userName = document.getElementById('userName');
        
        if (guestNav) guestNav.style.display = 'none';
        if (userNav) userNav.style.display = 'flex';
        if (userName) userName.textContent = userData.first_name;

        this.updateCartCount();
    }

    async updateCartCount() {
        try {
            const response = await this.fetchAPI('/cart', {
                headers: { 
                    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
                }
            });

            if (response.success) {
                const cartCount = response.data.items?.length || 0;
                const cartBadge = document.getElementById('cartCount');
                if (cartBadge) {
                    cartBadge.textContent = cartCount;
                    cartBadge.style.display = cartCount > 0 ? 'inline' : 'none';
                }
            }
        } catch (error) {
            console.error('Failed to update cart count:', error);
        }
    }

    logout() {
        localStorage.removeItem('auth_token');
        this.showMessage('Logged out successfully', 'success');
        setTimeout(() => {
            window.location.href = '/';
        }, 1000);
    }

    showMessage(message, type = 'info') {
        // Remove existing messages
        const existing = document.querySelector('.message-toast');
        if (existing) existing.remove();

        const toast = document.createElement('div');
        toast.className = `message-toast message-${type}`;
        toast.textContent = message;
        
        // Add styles for toast
        Object.assign(toast.style, {
            position: 'fixed',
            top: '20px',
            right: '20px',
            padding: '15px 20px',
            borderRadius: '5px',
            color: 'white',
            zIndex: '10000',
            maxWidth: '300px',
            boxShadow: '0 4px 12px rgba(0,0,0,0.15)'
        });

        // Set background color based on type
        const colors = {
            success: '#28a745',
            error: '#dc3545',
            warning: '#ffc107',
            info: '#007bff'
        };
        toast.style.backgroundColor = colors[type] || colors.info;

        document.body.appendChild(toast);

        // Auto remove after 5 seconds
        setTimeout(() => {
            if (toast.parentNode) toast.remove();
        }, 5000);
    }

    showLoading(form) {
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = submitBtn.textContent.replace(/^/, 'Loading... ');
        }
    }

    hideLoading(form) {
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = submitBtn.textContent.replace('Loading... ', '');
        }
    }

    // Utility function for formatting currency
    formatCurrency(amount, currency = 'NGN') {
        const formatter = new Intl.NumberFormat('en-NG', {
            style: 'currency',
            currency: currency,
            minimumFractionDigits: 2
        });
        return formatter.format(amount);
    }

    // Initialize on page load
    static init() {
        return new NorthViaApp();
    }
}

// Initialize app when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.northvia = NorthViaApp.init();
});

// Global helper functions
function formatPrice(price) {
    return window.northvia?.formatCurrency(price) || `₦${price.toFixed(2)}`;
}

function logout() {
    window.northvia?.logout();
}