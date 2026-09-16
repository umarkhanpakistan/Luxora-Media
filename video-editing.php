<?php
session_start();
?>
<?php include 'admin-bar.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Video Editing – Luxora Media</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="luxora.css" />
  <style>
    /* Pricing Card Custom Styles */
    .pricing-card {
      background: rgba(16, 16, 16, 0.7);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      border: 1px solid var(--border-subtle, rgba(255,255,255,0.08));
      border-radius: 1.5rem;
      padding: 2.5rem;
      transition: all 0.5s cubic-bezier(0.22, 1, 0.36, 1);
      position: relative;
      overflow: hidden;
      height: 100%;
      z-index: 1;
    }
    .pricing-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: radial-gradient(circle at 50% 0%, rgba(212, 175, 55, 0.04), transparent 60%);
      pointer-events: none;
    }
    .pricing-card.popular {
      border-color: rgba(212, 175, 55, 0.3);
      box-shadow: 0 0 80px rgba(212, 175, 55, 0.06);
    }
    .pricing-card.popular::before {
      background: radial-gradient(circle at 50% 0%, rgba(212, 175, 55, 0.08), transparent 60%);
    }
    .pricing-card.popular::after {
      content: 'Most Popular';
      position: absolute;
      top: 1.2rem;
      right: 1.2rem;
      background: linear-gradient(135deg, #D4AF37, #F5C84C);
      color: #050505;
      font-size: 0.65rem;
      font-weight: 700;
      padding: 0.25rem 1rem;
      border-radius: 100px;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      z-index: 2;
      box-shadow: 0 4px 20px rgba(212, 175, 55, 0.2);
    }
    .pricing-card:hover {
      transform: translateY(-10px) scale(1.01);
      border-color: rgba(212, 175, 55, 0.2);
      box-shadow: 0 40px 100px rgba(0,0,0,0.5), 0 0 60px rgba(212, 175, 55, 0.04);
    }
    .pricing-card .feature-list {
      list-style: none;
      padding: 0;
      margin: 0;
    }
    .pricing-card .feature-list li {
      display: flex;
      align-items: center;
      gap: 0.6rem;
      padding: 0.5rem 0;
      font-size: 0.85rem;
      color: #c0c0c0;
      border-bottom: 1px solid rgba(255,255,255,0.03);
    }
    .pricing-card .feature-list li:last-child {
      border-bottom: none;
    }
    .pricing-card .feature-list li i {
      color: #D4AF37;
      font-size: 0.75rem;
      flex-shrink: 0;
      width: 18px;
    }
    .pricing-card .feature-list li .feature-badge {
      background: rgba(212, 175, 55, 0.08);
      padding: 0.1rem 0.5rem;
      border-radius: 50px;
      font-size: 0.6rem;
      color: #D4AF37;
      margin-left: auto;
      font-weight: 600;
      letter-spacing: 0.3px;
      white-space: nowrap;
    }
    .pricing-card.popular .feature-list li .feature-badge {
      background: rgba(212, 175, 55, 0.15);
    }
    .pricing-card .btn-gold-outline {
      border: 1.5px solid rgba(212, 175, 55, 0.3);
      color: #D4AF37;
      background: transparent;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      padding: 0.7rem 1.5rem;
      border-radius: 50px;
      font-weight: 600;
      font-size: 0.85rem;
      transition: all 0.3s ease;
      text-decoration: none;
      cursor: pointer;
      width: 100%;
    }
    .pricing-card .btn-gold-outline:hover {
      background: rgba(212, 175, 55, 0.08);
      border-color: #D4AF37;
      transform: translateY(-2px);
    }
    .pricing-card .btn-primary-custom {
      background: linear-gradient(135deg, #D4AF37, #F5C84C);
      color: #050505;
      border: none;
      box-shadow: 0 4px 20px rgba(212, 175, 55, 0.2);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      padding: 0.7rem 1.5rem;
      border-radius: 50px;
      font-weight: 600;
      font-size: 0.85rem;
      transition: all 0.3s ease;
      text-decoration: none;
      cursor: pointer;
      width: 100%;
    }
    .pricing-card .btn-primary-custom:hover {
      transform: translateY(-2px) scale(1.02);
      box-shadow: 0 8px 35px rgba(212, 175, 55, 0.35);
      color: #050505;
    }

    /* ===== PROFILE DROPDOWN ===== */
    .profile-dropdown {
      position: relative;
      display: inline-block;
    }
    .profile-dropdown .dropdown-toggle {
      color: #d4af37 !important;
      border: 1px solid rgba(212, 175, 55, 0.2) !important;
      border-radius: 50px !important;
      padding: 0.4rem 1.2rem !important;
      font-size: 0.85rem !important;
      font-weight: 600 !important;
      transition: all 0.3s ease !important;
      display: inline-flex !important;
      align-items: center !important;
      gap: 0.5rem !important;
      background: transparent !important;
      text-decoration: none !important;
      cursor: pointer;
      font-family: 'Inter', sans-serif;
      position: relative;
      z-index: 1001;
      user-select: none;
      border: none;
    }
    .profile-dropdown .dropdown-toggle:hover {
      background: rgba(212, 175, 55, 0.08) !important;
      border-color: #d4af37 !important;
      transform: translateY(-2px) !important;
    }
    .profile-dropdown .dropdown-toggle i {
      font-size: 1rem;
    }
    .profile-dropdown .dropdown-toggle .fa-chevron-down {
      font-size: 0.6rem;
      opacity: 0.5;
      transition: transform 0.3s ease;
      margin-left: 0.2rem;
    }
    .profile-dropdown.active .dropdown-toggle .fa-chevron-down {
      transform: rotate(180deg);
    }
    
    .profile-dropdown .dropdown-menu {
      position: absolute;
      top: calc(100% + 10px);
      right: 0;
      min-width: 200px;
      background: rgba(10, 10, 10, 0.98);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      border: 1px solid rgba(212, 175, 55, 0.15);
      border-radius: 12px;
      padding: 0.5rem 0;
      z-index: 99999 !important;
      box-shadow: 0 20px 60px rgba(0,0,0,0.9);
      list-style: none;
      margin: 0;
      display: none !important;
      opacity: 0;
      visibility: hidden;
      transform: translateY(-10px) scale(0.95);
      transition: all 0.25s cubic-bezier(0.22, 1, 0.36, 1);
      pointer-events: none;
    }
    .profile-dropdown.active .dropdown-menu {
      display: block !important;
      opacity: 1 !important;
      visibility: visible !important;
      transform: translateY(0) scale(1) !important;
      pointer-events: auto !important;
    }
    .profile-dropdown .dropdown-menu li {
      list-style: none;
      display: block;
      padding: 0;
      margin: 0;
    }
    .profile-dropdown .dropdown-menu a {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.65rem 1.2rem;
      color: rgba(255,255,255,0.85);
      font-size: 0.85rem;
      font-weight: 500;
      transition: all 0.2s ease;
      text-decoration: none;
      border-bottom: none;
      background: transparent;
      cursor: pointer;
      white-space: nowrap;
    }
    .profile-dropdown .dropdown-menu a:hover {
      background: rgba(212, 175, 55, 0.08);
      color: #d4af37;
    }
    .profile-dropdown .dropdown-menu a i {
      width: 20px;
      color: #d4af37;
      font-size: 0.9rem;
      text-align: center;
    }
    .profile-dropdown .dropdown-menu .dropdown-divider {
      height: 1px;
      background: linear-gradient(90deg, transparent, rgba(212,175,55,0.12), transparent);
      margin: 0.3rem 0.8rem;
      padding: 0;
      border: none;
      display: block;
    }
    .profile-dropdown .dropdown-menu .logout-item {
      color: #ff4757 !important;
    }
    .profile-dropdown .dropdown-menu .logout-item i {
      color: #ff4757 !important;
    }
    .profile-dropdown .dropdown-menu .logout-item:hover {
      background: rgba(255, 71, 87, 0.08) !important;
      color: #ff4757 !important;
    }
    .mobile-profile-btn {
      display: flex;
      flex-direction: column;
      gap: 0.5rem;
      margin-top: 0.5rem;
    }
    .mobile-profile-btn a {
      text-align: center;
      padding: 0.7rem;
      border-radius: 50px;
      font-weight: 500;
      transition: all 0.3s ease;
      border: 1px solid rgba(255,255,255,0.08);
      text-decoration: none;
      display: block;
    }
    .mobile-profile-btn .profile-link {
      color: #d4af37;
      border-color: rgba(212, 175, 55, 0.2);
    }
    .mobile-profile-btn .profile-link:hover {
      background: rgba(212, 175, 55, 0.08);
    }
    .mobile-profile-btn .logout-link {
      color: #ff4757;
      border-color: rgba(255, 71, 87, 0.2);
    }
    .mobile-profile-btn .logout-link:hover {
      background: rgba(255, 71, 87, 0.08);
    }

    /* ===== AUTH BUTTONS ===== */
    .auth-buttons {
      display: flex !important;
      align-items: center;
      gap: 0.5rem;
      margin-left: 0.5rem;
    }
    .auth-buttons .btn-sm {
      padding: 0.4rem 1.2rem;
      font-size: 0.85rem;
      border-radius: 50px;
      font-weight: 600;
      transition: all 0.3s ease;
      letter-spacing: 0.3px;
      white-space: nowrap;
    }
    .btn-outline-gold {
      border: 1.5px solid #d4af37 !important;
      color: #d4af37 !important;
      background: transparent !important;
    }
    .btn-outline-gold:hover {
      background: #d4af37 !important;
      color: #0a0a0a !important;
      border-color: #d4af37 !important;
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(212, 175, 55, 0.25);
    }
    .btn-gold-solid {
      background: #d4af37 !important;
      color: #000000 !important;
      border: 1.5px solid #d4af37 !important;
    }
    .btn-gold-solid:hover {
      background: #c19b2e !important;
      border-color: #c19b2e !important;
      color: #000000 !important;
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(212, 175, 55, 0.35);
    }

    .mobile-auth {
      margin-top: 1.5rem;
      display: flex !important;
      flex-direction: column;
      gap: 0.75rem;
      padding: 0 1.5rem;
    }
    .mobile-auth .btn {
      border-radius: 50px;
      font-weight: 600;
      padding: 0.6rem 1.5rem;
      width: 100%;
    }
    .mobile-auth .btn-outline-gold {
      border: 1.5px solid #d4af37 !important;
      color: #d4af37 !important;
      background: transparent !important;
    }
    .mobile-auth .btn-outline-gold:hover {
      background: #d4af37 !important;
      color: #0a0a0a !important;
    }
    .mobile-auth .btn-gold-solid {
      background: #d4af37 !important;
      color: #000000 !important;
      border: 1.5px solid #d4af37 !important;
    }
    .mobile-auth .btn-gold-solid:hover {
      background: #c19b2e !important;
      border-color: #c19b2e !important;
      color: #000000 !important;
    }

    @media (min-width: 993px) {
      .mobile-auth { display: none !important; }
    }
    @media (max-width: 992px) {
      .auth-buttons { display: none !important; }
      .mobile-auth { display: flex !important; }
    }
  </style>
</head>
<body>
  <?php include '3d-scene.php'; ?>

  <!-- LOADING SCREEN -->
  <div id="loader">
    <div class="loader-ring"></div>
    <div class="loader-text">LUXORA</div>
    <div class="loader-bar"><div class="loader-bar-fill"></div></div>
  </div>

  <!-- BACK TO TOP -->
  <button id="backTop" class="back-top" aria-label="Back to top"><i class="fas fa-arrow-up"></i></button>

<?php include 'user-nav.php'; ?>

  <main>
    <!-- HERO -->
    <section class="d-flex align-items-center" style="min-height:50vh;padding-top:80px;background:radial-gradient(ellipse at 30% 40%, rgba(212,175,55,0.06), transparent 60%);">
      <div class="container">
        <div class="max-w-3xl fade-up visible">
          <span class="section-badge"><i class="fas fa-film me-1"></i> Service</span>
          <h1 class="section-title">Video <span class="gold">Editing</span></h1>
          <p class="text-secondary fs-5 mt-3">Cinematic video editing that brings your stories to life and captures your audience's attention.</p>
          <div class="d-flex flex-wrap gap-3 mt-4">
            <a href="contact.php" class="btn-primary-custom"><i class="fas fa-arrow-right me-2"></i> Get Started</a>
            <a href="contact.php" class="btn-secondary-custom"><i class="fas fa-calendar-check me-2"></i> Book Free Consultation</a>
          </div>
        </div>
      </div>
    </section>

    <!-- WHAT WE EDIT -->
    <section class="py-5">
      <div class="container">
        <div class="text-center fade-up">
          <span class="section-badge"><i class="fas fa-video me-1"></i> What We Edit</span>
          <h2 class="section-title">Video <span class="gold">Services</span></h2>
        </div>
        <div class="row g-4 mt-3">
          <div class="col-md-4 fade-up"><div class="glass-card h-100"><i class="fab fa-instagram fs-1 text-gold mb-2"></i><h4 class="fw-bold">Instagram Reels</h4><p class="text-secondary small">Engaging, trend-driven short-form content.</p></div></div>
          <div class="col-md-4 fade-up"><div class="glass-card h-100"><i class="fab fa-youtube fs-1 text-gold mb-2"></i><h4 class="fw-bold">YouTube Videos</h4><p class="text-secondary small">Professional, cinematic long-form content.</p></div></div>
          <div class="col-md-4 fade-up"><div class="glass-card h-100"><i class="fab fa-tiktok fs-1 text-gold mb-2"></i><h4 class="fw-bold">TikTok Content</h4><p class="text-secondary small">Viral-ready short videos for TikTok.</p></div></div>
          <div class="col-md-4 fade-up"><div class="glass-card h-100"><i class="fas fa-ad fs-1 text-gold mb-2"></i><h4 class="fw-bold">Advertisements</h4><p class="text-secondary small">High-impact video ads for campaigns.</p></div></div>
          <div class="col-md-4 fade-up"><div class="glass-card h-100"><i class="fas fa-building fs-1 text-gold mb-2"></i><h4 class="fw-bold">Corporate Videos</h4><p class="text-secondary small">Professional business videos.</p></div></div>
          <div class="col-md-4 fade-up"><div class="glass-card h-100"><i class="fas fa-magic fs-1 text-gold mb-2"></i><h4 class="fw-bold">Motion Graphics</h4><p class="text-secondary small">Animated graphics & visual effects.</p></div></div>
        </div>
      </div>
    </section>

    <!-- PRICING SECTION -->
    <section class="py-5 bg-card/30">
      <div class="container">
        <div class="text-center fade-up">
          <span class="section-badge"><i class="fas fa-tag me-1"></i> Video Pricing</span>
          <h2 class="section-title">Choose Your <span class="gold">Plan</span></h2>
          <p class="text-secondary mt-2">Flexible pricing for every budget. All plans include premium support and a 30‑day satisfaction guarantee.</p>
        </div>
        
        <div class="row g-4 mt-3">
          <!-- BASIC PLAN -->
          <div class="col-12 col-md-4 fade-up">
            <div class="pricing-card">
              <h5 class="fw-bold">Basic</h5>
              <div class="fs-1 fw-black mt-2">$199<span class="text-secondary fs-6 fw-normal">/mo</span></div>
              <p class="text-secondary small mt-1">Perfect for content creators</p>
              <ul class="feature-list mt-3">
                <li><i class="fas fa-check-circle"></i> 5 Videos per Month</li>
                <li><i class="fas fa-check-circle"></i> Basic Color Grading</li>
                <li><i class="fas fa-check-circle"></i> Sound Design</li>
                <li><i class="fas fa-check-circle"></i> 2 Revisions</li>
                <li><i class="fas fa-check-circle"></i> Email Support</li>
              </ul>
              <a href="contact.php?service=Video%20Editing&plan=Basic&amount=199" class="btn-gold-outline w-100 justify-content-center mt-3"><i class="fas fa-arrow-right me-2"></i> Start Now</a>
            </div>
          </div>

          <!-- PRO PLAN -->
          <div class="col-12 col-md-4 fade-up">
            <div class="pricing-card popular">
              <h5 class="fw-bold">Pro</h5>
              <div class="fs-1 fw-black mt-2">$499<span class="text-secondary fs-6 fw-normal">/mo</span></div>
              <p class="text-secondary small mt-1">For growing content creators</p>
              <ul class="feature-list mt-3">
                <li><i class="fas fa-check-circle"></i> 15 Videos per Month</li>
                <li><i class="fas fa-check-circle"></i> Advanced Color Grading</li>
                <li><i class="fas fa-check-circle"></i> Motion Graphics</li>
                <li><i class="fas fa-check-circle"></i> Sound Design & Music</li>
                <li><i class="fas fa-check-circle"></i> 5 Revisions</li>
                <li><i class="fas fa-check-circle"></i> Priority Support</li>
              </ul>
              <a href="contact.php?service=Video%20Editing&plan=Pro&amount=499" class="btn-primary-custom w-100 justify-content-center mt-3"><i class="fas fa-arrow-right me-2"></i> Start Now</a>
            </div>
          </div>

          <!-- ENTERPRISE PLAN -->
          <div class="col-12 col-md-4 fade-up">
            <div class="pricing-card">
              <h5 class="fw-bold">Enterprise</h5>
              <div class="fs-1 fw-black mt-2">Custom</div>
              <p class="text-secondary small mt-1">Full-scale video production</p>
              <ul class="feature-list mt-3">
                <li><i class="fas fa-check-circle"></i> Unlimited Videos</li>
                <li><i class="fas fa-check-circle"></i> Custom Video Strategy</li>
                <li><i class="fas fa-check-circle"></i> Dedicated Editor</li>
                <li><i class="fas fa-check-circle"></i> Full Post-Production</li>
                <li><i class="fas fa-check-circle"></i> Unlimited Revisions</li>
                <li><i class="fas fa-check-circle"></i> 24/7 Support</li>
              </ul>
              <a href="contact.php?service=Video%20Editing&plan=Enterprise" class="btn-gold-outline w-100 justify-content-center mt-3"><i class="fas fa-arrow-right me-2"></i> Contact Us</a>
            </div>
          </div>
        </div>
        
        <div class="text-center mt-3">
          <p class="text-secondary small"><i class="fas fa-comment text-gold me-1"></i> Need a custom plan? <a href="contact.php" class="text-gold text-decoration-none">Contact us</a></p>
        </div>
      </div>
    </section>

    <!-- FAQ -->
    <section class="py-5">
      <div class="container">
        <div class="text-center fade-up">
          <span class="section-badge"><i class="fas fa-question-circle me-1"></i> FAQ</span>
          <h2 class="section-title">Video <span class="gold">Questions</span></h2>
        </div>
        <div class="row justify-content-center mt-3">
          <div class="col-lg-8">
            <div class="faq-item"><div class="faq-q"><span>What types of videos do you edit?</span><span class="faq-icon"><i class="fas fa-plus"></i></span></div><div class="faq-a">Instagram Reels, YouTube videos, TikTok, advertisements, corporate videos, and motion graphics.</div></div>
            <div class="faq-item"><div class="faq-q"><span>How long does editing take?</span><span class="faq-icon"><i class="fas fa-plus"></i></span></div><div class="faq-a">Typically 2‑5 business days per video, depending on complexity.</div></div>
            <div class="faq-item"><div class="faq-q"><span>Do you provide raw footage?</span><span class="faq-icon"><i class="fas fa-plus"></i></span></div><div class="faq-a">We work with your footage and can provide edited versions in any format you need.</div></div>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section class="py-5 position-relative overflow-hidden">
      <div class="position-absolute top-0 start-0 w-100 h-100 bg-primary/5 blur-3xl"></div>
      <div class="container position-relative z-1">
        <div class="glass-gold rounded-4 p-5 text-center max-w-4xl mx-auto fade-up">
          <h2 class="section-title" style="font-size:clamp(2rem,4vw,3.5rem);">Ready for <span class="gold">Cinematic Content</span>?</h2>
          <p class="text-secondary fs-5 mt-3">Let's create videos that captivate your audience.</p>
          <div class="d-flex flex-wrap justify-content-center gap-3 mt-3">
            <a href="contact.php" class="btn-primary-custom"><i class="fas fa-arrow-right me-2"></i> Get Started</a>
            <a href="contact.php" class="btn-secondary-custom"><i class="fas fa-calendar-check me-2"></i> Book Free Consultation</a>
          </div>
        </div>
      </div>
    </section>

  </main>

  <!-- FOOTER -->
  <footer class="border-top border-white/5 bg-card/40 py-4">
    <div class="container">
      <div class="row g-4">
        <div class="col-md-3"><a href="index.php" class="logo fs-2">LUXORA<span>.</span></a><p class="text-secondary small mt-2">Premium digital agency.</p><div class="d-flex gap-2 mt-2"><a href="#" class="text-secondary text-decoration-none hover-gold"><i class="fab fa-x-twitter"></i></a><a href="#" class="text-secondary text-decoration-none hover-gold"><i class="fab fa-linkedin-in"></i></a><a href="#" class="text-secondary text-decoration-none hover-gold"><i class="fab fa-youtube"></i></a></div></div>
        <div class="col-md-3"><h6 class="fw-bold text-uppercase small text-secondary"><i class="fas fa-link me-1"></i> Quick Links</h6><ul class="list-unstyled mt-2 small"><li><a href="index.php" class="footer-link">Home</a></li><li><a href="about.php" class="footer-link">About</a></li><li><a href="services.php" class="footer-link">Services</a></li><li><a href="pricing.php" class="footer-link">Pricing</a></li><li><a href="faq.php" class="footer-link">FAQ</a></li><li><a href="contact.php" class="footer-link">Contact</a></li></ul></div>
        <div class="col-md-3"><h6 class="fw-bold text-uppercase small text-secondary"><i class="fas fa-cog me-1"></i> Services</h6><ul class="list-unstyled mt-2 small"><li><a href="web-development.php" class="footer-link">Web Dev</a></li><li><a href="ai-chatbots.php" class="footer-link">AI Chatbots</a></li><li><a href="graphic-design.php" class="footer-link">Graphic Design</a></li></ul></div>
        <div class="col-md-3"><h6 class="fw-bold text-uppercase small text-secondary"><i class="fas fa-envelope me-1"></i> Newsletter</h6><form class="d-flex gap-2" onsubmit="event.preventDefault();showToast('✓ Subscribed!')"><input type="email" placeholder="Your email" class="form-input flex-1" style="flex:1;" required /><button type="submit" class="btn-primary-custom px-3 py-2"><i class="fas fa-arrow-right"></i></button></form></div>
      </div>
      <div class="border-top border-white/5 mt-4 pt-3 d-flex flex-wrap justify-content-between small text-secondary"><span><i class="far fa-copyright me-1"></i> 2026 Luxora Media. All Rights Reserved.</span><span>Made with <i class="fas fa-heart text-gold"></i> in Berlin</span></div>
    </div>
  </footer>

  <div id="toast" class="toast-notification"><i class="fas fa-check-circle text-gold me-2"></i> Message sent! We'll <span class="gold">get back to you</span> soon.</div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="luxora.js"></script>
  
</body>
</html>