// Order Confirmation Page JavaScript

// Auto-scroll to top on page load
window.scrollTo(0, 0);

// Print functionality
function printReceipt() {
    const printContent = document.querySelector('.container').innerHTML;
    const originalContent = document.body.innerHTML;
    
    document.body.innerHTML = `
        <html>
        <head>
            <title>Order Receipt</title>
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    margin: 20px; 
                    color: #333;
                }
                .success-header { 
                    text-align: center; 
                    margin-bottom: 30px; 
                    background: none !important;
                    color: #333 !important;
                }
                .order-items-list { 
                    margin-bottom: 20px; 
                }
                .btn, button, .no-print { 
                    display: none !important; 
                }
                .quick-actions,
                .whats-next {
                    display: none !important;
                }
                * {
                    box-shadow: none !important;
                }
            </style>
        </head>
        <body>${printContent}</body>
        </html>
    `;
    
    window.print();
    document.body.innerHTML = originalContent;
    location.reload();
}

// Confetti effect
function createConfetti() {
    const colors = ['#f43f5e', '#3b82f6', '#10b981', '#f59e0b', '#8b5cf6'];
    const confettiCount = 50;
    
    for (let i = 0; i < confettiCount; i++) {
        const confetti = document.createElement('div');
        confetti.style.cssText = `
            position: fixed;
            top: -10px;
            left: ${Math.random() * 100}%;
            width: 10px;
            height: 10px;
            background: ${colors[Math.floor(Math.random() * colors.length)]};
            border-radius: 50%;
            pointer-events: none;
            z-index: 9999;
            animation: confetti-fall ${Math.random() * 3 + 2}s linear forwards;
        `;
        
        document.body.appendChild(confetti);
        
        setTimeout(() => confetti.remove(), 5000);
    }
}

// Add confetti animation CSS if not already present
if (!document.querySelector('#confetti-styles')) {
    const style = document.createElement('style');
    style.id = 'confetti-styles';
    style.textContent = `
        @keyframes confetti-fall {
            to {
                transform: translateY(100vh) rotate(360deg);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);
}

// Trigger confetti on page load
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(createConfetti, 500);
});

// Smooth scroll for any anchor links (if needed)
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});

// Optional: Add fade-in animation to content sections
function addFadeInAnimation() {
    const sections = document.querySelectorAll('.order-items-section, .shipping-section, .order-summary, .quick-actions, .whats-next');
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    });
    
    sections.forEach(section => {
        section.style.opacity = '0';
        section.style.transform = 'translateY(20px)';
        section.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(section);
    });
}

// Initialize fade-in animation on load
document.addEventListener('DOMContentLoaded', addFadeInAnimation);