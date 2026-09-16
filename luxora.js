// luxora.js - Galaxy/Space 3D Motion UI

document.addEventListener('DOMContentLoaded', function() {
    
    // ===== 3D PARALLAX ON MOUSE MOVE =====
    const scene3d = document.getElementById('scene-3d');
    const stars = document.querySelectorAll('.star');
    const nebulas = document.querySelectorAll('.nebula-cloud');
    const particles = document.querySelectorAll('.cosmic-particle');
    
    document.addEventListener('mousemove', function(e) {
        const x = (e.clientX / window.innerWidth - 0.5) * 15;
        const y = (e.clientY / window.innerHeight - 0.5) * 15;
        
        if (scene3d) {
            scene3d.style.transform = `rotateX(${y * 0.3}deg) rotateY(${-x * 0.3}deg)`;
        }
        
        stars.forEach(function(star, index) {
            const speed = 0.2 + (index % 3) * 0.1;
            const xMove = x * speed;
            const yMove = y * speed;
            star.style.transform = `translate(${xMove}px, ${yMove}px)`;
        });
        
        nebulas.forEach(function(nebula, index) {
            const speed = 0.15 + (index * 0.05);
            const xMove = x * speed;
            const yMove = y * speed;
            nebula.style.transform = `translate(${xMove}px, ${yMove}px) scale(${1 + (Math.abs(x) + Math.abs(y)) * 0.001})`;
        });
        
        particles.forEach(function(particle, index) {
            const speed = 0.3 + (index * 0.04);
            const xMove = x * speed;
            const yMove = y * speed;
            particle.style.transform = `translate(${xMove}px, ${yMove}px)`;
        });
    });
    
    // ===== 3D CARD TILT EFFECT =====
    const tiltCards = document.querySelectorAll('.glass-card, .glass-card-gold, .pricing-card, .testimonial-card');
    
    tiltCards.forEach(function(card) {
        card.addEventListener('mousemove', function(e) {
            const rect = card.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            const centerX = rect.width / 2;
            const centerY = rect.height / 2;
            const rotateX = ((y - centerY) / centerY) * 8;
            const rotateY = ((x - centerX) / centerX) * 8;
            
            card.style.transform = `perspective(1200px) rotateX(${-rotateX}deg) rotateY(${rotateY}deg) translateY(-8px) scale(1.01)`;
        });
        
        card.addEventListener('mouseleave', function() {
            card.style.transform = 'perspective(1200px) rotateX(0deg) rotateY(0deg) translateY(0) scale(1)';
        });
    });
    
    // ===== SHOOTING STARS SPAWNER =====
    function createShootingStar() {
        const container = document.getElementById('scene-3d');
        if (!container) return;
        
        const star = document.createElement('div');
        star.className = 'shooting-star';
        star.style.top = Math.random() * 80 + '%';
        star.style.left = Math.random() * 80 + '%';
        star.style.animationDuration = (6 + Math.random() * 4) + 's';
        star.style.animationDelay = Math.random() * 10 + 's';
        container.appendChild(star);
        
        setTimeout(() => {
            star.remove();
        }, 15000);
    }
    
    setInterval(createShootingStar, 5000);
    
    // ===== NAVBAR SCROLL EFFECT =====
    const navbar = document.getElementById('navbar');
    let lastScroll = 0;

    window.addEventListener('scroll', function() {
        const currentScroll = window.pageYOffset || document.documentElement.scrollTop;
        
        if (currentScroll > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
        
        lastScroll = currentScroll;
    });

    // ===== MOBILE MENU TOGGLE =====
    const mobileToggle = document.getElementById('mobileToggle');
    const mobileMenu = document.getElementById('mobileMenu');
    const mobileClose = document.getElementById('mobileClose');

    if (mobileToggle && mobileMenu) {
        mobileToggle.addEventListener('click', function() {
            mobileMenu.classList.toggle('active');
            mobileToggle.classList.toggle('open');
            document.body.style.overflow = mobileMenu.classList.contains('active') ? 'hidden' : '';
        });
    }

    if (mobileClose && mobileMenu) {
        mobileClose.addEventListener('click', function() {
            mobileMenu.classList.remove('active');
            mobileToggle.classList.remove('open');
            document.body.style.overflow = '';
        });
    }

    const mobileLinks = mobileMenu ? mobileMenu.querySelectorAll('a') : [];
    mobileLinks.forEach(function(link) {
        link.addEventListener('click', function() {
            mobileMenu.classList.remove('active');
            mobileToggle.classList.remove('open');
            document.body.style.overflow = '';
        });
    });

    // ===== BACK TO TOP BUTTON =====
    const backTop = document.getElementById('backTop');
    
    window.addEventListener('scroll', function() {
        if (window.pageYOffset > 400) {
            backTop.classList.add('visible');
        } else {
            backTop.classList.remove('visible');
        }
    });

    if (backTop) {
        backTop.addEventListener('click', function() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    // ===== TOAST NOTIFICATION =====
    window.showToast = function(message, type) {
        const toast = document.getElementById('toast');
        if (toast) {
            toast.innerHTML = message || '✓ Message sent! We\'ll get back to you soon.';
            toast.className = 'toast-notification';
            if (type === 'error') {
                toast.style.borderColor = 'rgba(255,71,87,0.3)';
                toast.querySelector('i').className = 'fas fa-exclamation-circle me-2';
                toast.querySelector('i').style.color = '#ff4757';
            } else {
                toast.style.borderColor = 'rgba(46,213,115,0.3)';
                toast.querySelector('i').className = 'fas fa-check-circle me-2';
                toast.querySelector('i').style.color = '#2ed573';
            }
            toast.classList.add('show');
            setTimeout(function() {
                toast.classList.remove('show');
            }, 4000);
        }
    };

    // ===== FORM HANDLING - DISABLED FOR CONTACT FORM =====
    const forms = document.querySelectorAll('form[data-ajax="true"]:not(#contactForm)');
    
    forms.forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const btn = form.querySelector('button[type="submit"]');
            const originalText = btn.innerHTML;
            
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Sending...';
            btn.disabled = true;
            
            setTimeout(function() {
                showToast('✓ Message sent successfully! We\'ll get back to you soon.');
                form.reset();
                btn.innerHTML = originalText;
                btn.disabled = false;
            }, 1500);
        });
    });

    // ===== FAQ SEARCH =====
    window.filterFaqs = function() {
        const searchInput = document.getElementById('faqSearch');
        if (!searchInput) return;
        
        const query = searchInput.value.toLowerCase();
        const items = document.querySelectorAll('.faq-item');
        
        items.forEach(function(item) {
            const text = item.textContent.toLowerCase();
            const match = text.includes(query);
            item.style.display = match ? '' : 'none';
            
            if (match && query.length > 0) {
                item.style.borderColor = 'rgba(212, 175, 55, 0.2)';
            } else {
                item.style.borderColor = '';
            }
        });
    };

    // ===== FADE-UP ON SCROLL =====
    const fadeElements = document.querySelectorAll('.fade-up');
    
    const observerOptions = {
        threshold: 0.15,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
            }
        });
    }, observerOptions);
    
    fadeElements.forEach(function(el) {
        observer.observe(el);
    });

    // ===== LOADING SCREEN =====
    window.addEventListener('load', function() {
        const loader = document.getElementById('loader');
        if (loader) {
            setTimeout(function() {
                loader.classList.add('hidden');
            }, 800);
        }
    });

    // ===== COUNTER ANIMATION =====
    const statNumbers = document.querySelectorAll('.stat-number');
    
    const counterObserver = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) {
                const el = entry.target;
                const text = el.textContent;
                const number = parseInt(text.replace(/[^0-9]/g, ''));
                const suffix = text.replace(/[0-9]/g, '');
                
                if (!isNaN(number)) {
                    let current = 0;
                    const increment = Math.ceil(number / 50);
                    const duration = 1500;
                    const stepTime = Math.floor(duration / 50);
                    
                    const timer = setInterval(function() {
                        current += increment;
                        if (current >= number) {
                            current = number;
                            clearInterval(timer);
                        }
                        el.textContent = current + suffix;
                    }, stepTime);
                }
                counterObserver.unobserve(el);
            }
        });
    }, { threshold: 0.5 });
    
    statNumbers.forEach(function(el) {
        counterObserver.observe(el);
    });

});