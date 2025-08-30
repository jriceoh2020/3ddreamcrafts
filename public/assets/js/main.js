/**
 * 3DDreamCrafts Website - Main JavaScript
 * Handles interactive features and responsive behavior
 */

// Performance monitoring
const performanceMonitor = {
    startTime: performance.now(),
    
    logPageLoad: function() {
        window.addEventListener('load', () => {
            const loadTime = performance.now() - this.startTime;
            console.log(`Page load time: ${loadTime.toFixed(2)}ms`);
            
            // Send to server if needed (for analytics)
            if (loadTime > 3000) { // Log slow pages
                console.warn(`Slow page load detected: ${loadTime.toFixed(2)}ms`);
            }
        });
    },
    
    init: function() {
        this.logPageLoad();
    }
};

// Initialize performance monitoring
performanceMonitor.init();

document.addEventListener('DOMContentLoaded', function() {
    // Mobile menu toggle functionality (for future use)
    const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
    const navMenu = document.querySelector('.nav-menu');
    
    if (mobileMenuToggle && navMenu) {
        mobileMenuToggle.addEventListener('click', function() {
            navMenu.classList.toggle('active');
        });
    }
    
    // Smooth scrolling for anchor links
    const anchorLinks = document.querySelectorAll('a[href^="#"]');
    anchorLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            const targetId = this.getAttribute('href').substring(1);
            const targetElement = document.getElementById(targetId);
            
            if (targetElement) {
                e.preventDefault();
                targetElement.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
    
    // Enhanced image lazy loading with WebP support
    const images = document.querySelectorAll('img[data-src], img.lazy-load');
    
    // Check WebP support
    const supportsWebP = (function() {
        const canvas = document.createElement('canvas');
        canvas.width = 1;
        canvas.height = 1;
        return canvas.toDataURL('image/webp').indexOf('data:image/webp') === 0;
    })();
    
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                let src = img.dataset.src || img.src;
                
                // Try to load WebP version if supported
                if (supportsWebP && src && !src.includes('.webp')) {
                    const webpSrc = src.replace(/\.(jpg|jpeg|png)$/i, '.webp');
                    
                    // Test if WebP version exists
                    const testImg = new Image();
                    testImg.onload = function() {
                        img.src = webpSrc;
                        img.classList.add('loaded');
                    };
                    testImg.onerror = function() {
                        img.src = src;
                        img.classList.add('loaded');
                    };
                    testImg.src = webpSrc;
                } else {
                    img.src = src;
                    img.classList.add('loaded');
                }
                
                img.removeAttribute('data-src');
                observer.unobserve(img);
            }
        });
    }, {
        rootMargin: '50px 0px', // Start loading 50px before image enters viewport
        threshold: 0.1
    });
    
    images.forEach(img => {
        img.classList.add('lazy-loading');
        imageObserver.observe(img);
    });
    
    // Form validation helpers
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('error');
                    isValid = false;
                } else {
                    field.classList.remove('error');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields.');
            }
        });
    });
    
    // Social media link tracking (for analytics)
    const socialLinks = document.querySelectorAll('.social-link');
    socialLinks.forEach(link => {
        link.addEventListener('click', function() {
            const platform = this.classList.contains('facebook') ? 'Facebook' : 'Instagram';
            console.log(`Social media click: ${platform}`);
            // Add analytics tracking here if needed
        });
    });
    
    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => {
                alert.remove();
            }, 300);
        }, 5000);
    });
});

// Utility functions
function showLoading(element) {
    element.classList.add('loading');
    element.innerHTML += ' <span class="spinner"></span>';
}

function hideLoading(element) {
    element.classList.remove('loading');
    const spinner = element.querySelector('.spinner');
    if (spinner) {
        spinner.remove();
    }
}

function showAlert(message, type = 'info') {
    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;
    alert.textContent = message;
    
    const container = document.querySelector('.container') || document.body;
    container.insertBefore(alert, container.firstChild);
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
        alert.style.opacity = '0';
        setTimeout(() => {
            alert.remove();
        }, 300);
    }, 5000);
}