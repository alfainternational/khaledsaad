/**
 * ملف الرسوم المتحركة
 * موقع خالد سعد للاستشارات
 */

// ============================================
// Scroll Animations
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    initScrollAnimations();
    initCounterAnimations();
    initParallaxEffects();
    initHoverAnimations();
    initLoadingAnimations();
});

// ============================================
// Scroll Reveal Animations
// ============================================
function initScrollAnimations() {
    // Intersection Observer for fade-in animations
    const observerOptions = {
        root: null,
        rootMargin: '0px',
        threshold: 0.1
    };

    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-visible');

                // Handle staggered children
                const children = entry.target.querySelectorAll('[data-stagger]');
                children.forEach(function(child, index) {
                    child.style.transitionDelay = `${index * 100}ms`;
                    child.classList.add('animate-visible');
                });

                // Unobserve after animation
                if (!entry.target.dataset.animateRepeat) {
                    observer.unobserve(entry.target);
                }
            }
        });
    }, observerOptions);

    // Observe all elements with animation classes
    document.querySelectorAll('[data-animate]').forEach(function(el) {
        observer.observe(el);
    });

    // Add CSS for animations
    if (!document.getElementById('animationStyles')) {
        const styles = document.createElement('style');
        styles.id = 'animationStyles';
        styles.textContent = `
            [data-animate] {
                opacity: 0;
                transition: opacity 0.6s ease, transform 0.6s ease;
            }

            [data-animate="fade-up"] {
                transform: translateY(30px);
            }

            [data-animate="fade-down"] {
                transform: translateY(-30px);
            }

            [data-animate="fade-left"] {
                transform: translateX(30px);
            }

            [data-animate="fade-right"] {
                transform: translateX(-30px);
            }

            [data-animate="zoom-in"] {
                transform: scale(0.9);
            }

            [data-animate="zoom-out"] {
                transform: scale(1.1);
            }

            [data-animate].animate-visible {
                opacity: 1;
                transform: translateY(0) translateX(0) scale(1);
            }

            [data-stagger] {
                opacity: 0;
                transform: translateY(20px);
                transition: opacity 0.5s ease, transform 0.5s ease;
            }

            [data-stagger].animate-visible {
                opacity: 1;
                transform: translateY(0);
            }
        `;
        document.head.appendChild(styles);
    }
}

// ============================================
// Counter Animations
// ============================================
function initCounterAnimations() {
    const counters = document.querySelectorAll('[data-counter]');

    if (counters.length === 0) return;

    const observerOptions = {
        threshold: 0.5
    };

    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) {
                animateCounter(entry.target);
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    counters.forEach(function(counter) {
        observer.observe(counter);
    });

    function animateCounter(element) {
        const target = parseInt(element.dataset.counter);
        const duration = parseInt(element.dataset.duration) || 2000;
        const suffix = element.dataset.suffix || '';
        const prefix = element.dataset.prefix || '';

        let start = 0;
        const startTime = performance.now();

        function updateCounter(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);

            // Easing function (ease-out)
            const easeOut = 1 - Math.pow(1 - progress, 3);
            const current = Math.floor(easeOut * target);

            element.textContent = prefix + formatNumber(current) + suffix;

            if (progress < 1) {
                requestAnimationFrame(updateCounter);
            } else {
                element.textContent = prefix + formatNumber(target) + suffix;
            }
        }

        requestAnimationFrame(updateCounter);
    }

    function formatNumber(num) {
        return num.toLocaleString('ar-SA');
    }
}

// ============================================
// Parallax Effects
// ============================================
function initParallaxEffects() {
    const parallaxElements = document.querySelectorAll('[data-parallax]');

    if (parallaxElements.length === 0) return;

    function updateParallax() {
        const scrollTop = window.pageYOffset;

        parallaxElements.forEach(function(element) {
            const speed = parseFloat(element.dataset.parallax) || 0.5;
            const rect = element.getBoundingClientRect();
            const elementTop = rect.top + scrollTop;
            const offset = (scrollTop - elementTop) * speed;

            element.style.transform = `translateY(${offset}px)`;
        });
    }

    window.addEventListener('scroll', throttle(updateParallax, 10));
}

// ============================================
// Hover Animations
// ============================================
function initHoverAnimations() {
    // Card tilt effect
    const tiltCards = document.querySelectorAll('[data-tilt]');

    tiltCards.forEach(function(card) {
        card.addEventListener('mousemove', function(e) {
            const rect = card.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;

            const centerX = rect.width / 2;
            const centerY = rect.height / 2;

            const rotateX = (y - centerY) / 20;
            const rotateY = (centerX - x) / 20;

            card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) scale3d(1.02, 1.02, 1.02)`;
        });

        card.addEventListener('mouseleave', function() {
            card.style.transform = 'perspective(1000px) rotateX(0) rotateY(0) scale3d(1, 1, 1)';
        });
    });

    // Magnetic buttons
    const magneticButtons = document.querySelectorAll('[data-magnetic]');

    magneticButtons.forEach(function(button) {
        button.addEventListener('mousemove', function(e) {
            const rect = button.getBoundingClientRect();
            const x = e.clientX - rect.left - rect.width / 2;
            const y = e.clientY - rect.top - rect.height / 2;

            button.style.transform = `translate(${x * 0.3}px, ${y * 0.3}px)`;
        });

        button.addEventListener('mouseleave', function() {
            button.style.transform = 'translate(0, 0)';
        });
    });

    // Ripple effect
    const rippleButtons = document.querySelectorAll('.btn-primary, .btn-secondary');

    rippleButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            const rect = button.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;

            const ripple = document.createElement('span');
            ripple.className = 'ripple-effect';
            ripple.style.left = x + 'px';
            ripple.style.top = y + 'px';

            button.appendChild(ripple);

            setTimeout(function() {
                ripple.remove();
            }, 600);
        });
    });

    // Add ripple styles
    if (!document.getElementById('rippleStyles')) {
        const styles = document.createElement('style');
        styles.id = 'rippleStyles';
        styles.textContent = `
            .btn-primary, .btn-secondary {
                position: relative;
                overflow: hidden;
            }

            .ripple-effect {
                position: absolute;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.4);
                transform: scale(0);
                animation: ripple 0.6s linear;
                pointer-events: none;
            }

            @keyframes ripple {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }

            [data-tilt] {
                transition: transform 0.1s ease;
            }

            [data-magnetic] {
                transition: transform 0.2s ease;
            }
        `;
        document.head.appendChild(styles);
    }
}

// ============================================
// Loading Animations
// ============================================
function initLoadingAnimations() {
    // Skeleton loading
    const skeletons = document.querySelectorAll('.skeleton');

    skeletons.forEach(function(skeleton) {
        // Remove skeleton class when content is loaded
        const img = skeleton.querySelector('img');
        if (img) {
            img.addEventListener('load', function() {
                skeleton.classList.remove('skeleton');
            });
        }
    });

    // Page loader
    const pageLoader = document.getElementById('pageLoader');
    if (pageLoader) {
        window.addEventListener('load', function() {
            pageLoader.classList.add('loaded');
            setTimeout(function() {
                pageLoader.remove();
            }, 500);
        });
    }
}

// ============================================
// Text Animations
// ============================================
function animateText(element, options = {}) {
    const text = element.textContent;
    const delay = options.delay || 50;
    const type = options.type || 'letter';

    element.textContent = '';
    element.style.visibility = 'visible';

    const items = type === 'word' ? text.split(' ') : text.split('');

    items.forEach(function(item, index) {
        const span = document.createElement('span');
        span.textContent = type === 'word' ? item + ' ' : item;
        span.style.opacity = '0';
        span.style.display = 'inline-block';
        span.style.animation = `fadeInUp 0.5s ease forwards`;
        span.style.animationDelay = `${index * delay}ms`;
        element.appendChild(span);
    });

    // Add animation keyframes
    if (!document.getElementById('textAnimationStyles')) {
        const styles = document.createElement('style');
        styles.id = 'textAnimationStyles';
        styles.textContent = `
            @keyframes fadeInUp {
                from {
                    opacity: 0;
                    transform: translateY(20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
        `;
        document.head.appendChild(styles);
    }
}

// ============================================
// Progress Bar Animation
// ============================================
function animateProgressBar(element, targetValue, duration = 1000) {
    const bar = element.querySelector('.progress-fill') || element;
    const valueDisplay = element.querySelector('.progress-value');

    let start = 0;
    const startTime = performance.now();

    function update(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);

        const easeOut = 1 - Math.pow(1 - progress, 3);
        const current = easeOut * targetValue;

        bar.style.width = current + '%';

        if (valueDisplay) {
            valueDisplay.textContent = Math.round(current) + '%';
        }

        if (progress < 1) {
            requestAnimationFrame(update);
        }
    }

    requestAnimationFrame(update);
}

// ============================================
// Utility Functions
// ============================================

function throttle(func, limit) {
    let inThrottle;
    return function(...args) {
        if (!inThrottle) {
            func.apply(this, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

// ============================================
// Export for global use
// ============================================
window.animateText = animateText;
window.animateProgressBar = animateProgressBar;
