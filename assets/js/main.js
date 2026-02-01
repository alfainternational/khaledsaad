/**
 * ملف JavaScript الرئيسي
 */

document.addEventListener('DOMContentLoaded', function() {
    initHeader();
    initMobileMenu();
    initThemeToggle();
    initScrollToTop();
    initChatbot();
    initForms();
    initPromo();
});

// Header scroll effect
function initHeader() {
    const header = document.getElementById('siteHeader');
    if (!header) return;

    window.addEventListener('scroll', function() {
        if (window.scrollY > 50) {
            header.classList.add('scrolled');
        } else {
            header.classList.remove('scrolled');
        }
    });
}

// Mobile menu
function initMobileMenu() {
    const toggle = document.getElementById('menuToggle');
    const menu = document.getElementById('navMenu');
    if (!toggle || !menu) return;

    toggle.addEventListener('click', function() {
        const expanded = this.getAttribute('aria-expanded') === 'true';
        this.setAttribute('aria-expanded', !expanded);
        menu.classList.toggle('active');
        document.body.style.overflow = menu.classList.contains('active') ? 'hidden' : '';
    });

    // Dropdown for mobile
    document.querySelectorAll('.has-dropdown').forEach(function(item) {
        item.querySelector('.nav-link').addEventListener('click', function(e) {
            if (window.innerWidth <= 992) {
                e.preventDefault();
                item.classList.toggle('open');
            }
        });
    });
}

// Theme toggle
function initThemeToggle() {
    const toggle = document.getElementById('themeToggle');
    if (!toggle) return;

    const savedTheme = localStorage.getItem('theme') || 
        (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    
    if (savedTheme === 'dark') {
        document.body.classList.add('dark-mode');
    }

    toggle.addEventListener('click', function() {
        document.body.classList.toggle('dark-mode');
        const isDark = document.body.classList.contains('dark-mode');
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
        document.cookie = 'darkMode=' + isDark + ';path=/;max-age=31536000';
    });
}

// Scroll to top
function initScrollToTop() {
    const btn = document.getElementById('scrollToTop');
    if (!btn) return;

    window.addEventListener('scroll', function() {
        if (window.scrollY > 300) {
            btn.classList.add('visible');
        } else {
            btn.classList.remove('visible');
        }
    });

    btn.addEventListener('click', function() {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
}

// Chatbot
function initChatbot() {
    const widget = document.getElementById('chatbotWidget');
    const toggle = document.getElementById('chatbotToggle');
    const close = document.querySelector('.chatbot-close');
    const form = document.getElementById('chatbotForm');
    const messages = document.getElementById('chatbotMessages');
    
    if (!widget || !toggle) return;

    toggle.addEventListener('click', function() {
        widget.classList.toggle('active');
        toggle.querySelector('.chatbot-badge').style.display = 'none';
    });

    if (close) {
        close.addEventListener('click', function() {
            widget.classList.remove('active');
        });
    }

    // Quick replies
    document.querySelectorAll('.quick-replies button').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const message = this.dataset.message;
            sendChatMessage(message);
        });
    });

    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const input = this.querySelector('input');
            const message = input.value.trim();
            if (message) {
                sendChatMessage(message);
                input.value = '';
            }
        });
    }
}

async function sendChatMessage(message) {
    const messages = document.getElementById('chatbotMessages');
    if (!messages) return;

    // Add user message
    messages.innerHTML += '<div class="chat-message user"><div class="message-content"><p>' + escapeHtml(message) + '</p></div></div>';
    messages.scrollTop = messages.scrollHeight;

    // Hide quick replies
    const quickReplies = messages.querySelector('.quick-replies');
    if (quickReplies) quickReplies.style.display = 'none';

    // Show typing indicator
    messages.innerHTML += '<div class="chat-message bot typing"><div class="message-content"><p><i class="fas fa-ellipsis-h"></i></p></div></div>';
    messages.scrollTop = messages.scrollHeight;

    try {
        const response = await fetch('/api/chatbot.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message: message, session_id: getSessionId() })
        });
        const data = await response.json();

        // Remove typing indicator
        const typing = messages.querySelector('.typing');
        if (typing) typing.remove();

        // Add bot response
        const botResponse = data.data?.response || 'شكراً لرسالتك!';
        messages.innerHTML += '<div class="chat-message bot"><div class="message-content"><p>' + botResponse.replace(/\n/g, '</p><p>') + '</p></div></div>';
        messages.scrollTop = messages.scrollHeight;
    } catch (e) {
        const typing = messages.querySelector('.typing');
        if (typing) typing.remove();
        messages.innerHTML += '<div class="chat-message bot"><div class="message-content"><p>عذراً، حدث خطأ. يرجى المحاولة مرة أخرى.</p></div></div>';
    }
}

function getSessionId() {
    let id = localStorage.getItem('chatSessionId');
    if (!id) {
        id = 'chat_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        localStorage.setItem('chatSessionId', id);
    }
    return id;
}

// Forms
function initForms() {
    document.querySelectorAll('form[data-validate]').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            let isValid = true;
            this.querySelectorAll('[required]').forEach(function(field) {
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            if (!isValid) e.preventDefault();
        });
    });
}

// Promo banner
function initPromo() {
    const banner = document.getElementById('promoBanner');
    if (!banner) return;
    if (sessionStorage.getItem('promoClosed')) {
        banner.classList.add('hidden');
    }
}

function closePromo() {
    const banner = document.getElementById('promoBanner');
    if (banner) {
        banner.classList.add('hidden');
        sessionStorage.setItem('promoClosed', 'true');
    }
}

// Notification
function showNotification(type, message, duration = 5000) {
    const existing = document.querySelector('.notification');
    if (existing) existing.remove();

    const notification = document.createElement('div');
    notification.className = 'notification notification-' + type;
    notification.innerHTML = '<i class="fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + '"></i><span>' + message + '</span><button onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>';
    notification.style.cssText = 'position:fixed;top:100px;right:20px;padding:16px 20px;background:' + (type === 'success' ? '#10b981' : '#ef4444') + ';color:white;border-radius:8px;display:flex;align-items:center;gap:12px;z-index:9999;animation:slideIn 0.3s ease;box-shadow:0 4px 12px rgba(0,0,0,0.15);';
    document.body.appendChild(notification);

    setTimeout(function() { notification.remove(); }, duration);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Export for global use
window.showNotification = showNotification;
window.closePromo = closePromo;
