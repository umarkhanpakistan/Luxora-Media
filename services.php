<?php
session_start();
// Include admin bar for admins
include 'admin-bar.php';

// Fetch services from database
require_once __DIR__ . '/db.php';

$services = [];
try {
    $db = getDB();
    $stmt = $db->query("SELECT * FROM services WHERE status = 'active' ORDER BY id ASC");
    $services = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Fetch services error: " . $e->getMessage());
    $services = [];
}

// Fetch service categories if table exists
$serviceCategories = [];
try {
    $stmt = $db->query("SELECT * FROM service_categories WHERE is_active = 1 ORDER BY display_order ASC");
    $serviceCategories = $stmt->fetchAll();
} catch (PDOException $e) {
    $serviceCategories = [];
}

// Fetch why choose us items
$whyChooseUs = [];
try {
    $stmt = $db->query("SELECT * FROM why_choose_us WHERE is_active = 1 ORDER BY display_order ASC LIMIT 4");
    $whyChooseUs = $stmt->fetchAll();
} catch (PDOException $e) {
    $whyChooseUs = [];
}

// Fallback why choose us
if (empty($whyChooseUs)) {
    $whyChooseUs = [
        ['icon' => 'fa-bolt', 'title' => 'Speed', 'description' => 'Fast delivery without compromising quality.'],
        ['icon' => 'fa-bullseye', 'title' => 'Results', 'description' => 'Data-driven strategies that deliver ROI.'],
        ['icon' => 'fa-shield-halved', 'title' => 'Trust', 'description' => 'Transparent pricing & honest communication.'],
        ['icon' => 'fa-headset', 'title' => 'Support', 'description' => '24/7 support & dedicated account managers.']
    ];
}

// Function to generate service page filename
function getServicePage($serviceName) {
    // Convert service name to slug
    $slug = strtolower(trim($serviceName));
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    
    // Page mapping - specific service pages
    $pageMap = [
        'web-development' => 'web-development.php',
        'ai-chatbots' => 'ai-chatbots.php',
        'graphic-design' => 'graphic-design.php',
        'video-editing' => 'video-editing.php',
        'social-media' => 'social-media.php',
        'branding' => 'branding.php',
        'seo' => 'seo.php',
        'digital-marketing' => 'digital-marketing.php',
        'web-dev' => 'web-development.php',
        'chatbots' => 'ai-chatbots.php',
        'graphic-designing' => 'graphic-design.php',
        'social-media-management' => 'social-media.php',
        'search-engine-optimization' => 'seo.php',
        'online-marketing' => 'digital-marketing.php',
    ];
    
    // Check if we have a specific page mapping
    if (isset($pageMap[$slug])) {
        return $pageMap[$slug];
    }
    
    // Check if a file exists with the service name
    $possibleFiles = [
        $slug . '.php',
        str_replace('-', '', $slug) . '.php',
        $slug . '-service.php'
    ];
    
    foreach ($possibleFiles as $file) {
        if (file_exists(__DIR__ . '/' . $file)) {
            return $file;
        }
    }
    
    // Default to contact page with service parameter
    return 'contact.php?service=' . urlencode($serviceName);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Services – Luxora Media</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="luxora.css" />
  <style>
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

    /* Dynamic service card styles */
    .service-card-link {
      text-decoration: none;
      display: block;
      height: 100%;
    }
    .service-card-link .glass-card {
      height: 100%;
      transition: all 0.5s cubic-bezier(0.22, 1, 0.36, 1);
    }
    .service-card-link .glass-card:hover {
      transform: translateY(-8px) scale(1.01);
      border-color: rgba(212, 175, 55, 0.25);
      box-shadow: 0 30px 80px rgba(0,0,0,0.5), 0 0 40px rgba(212, 175, 55, 0.04);
    }
    .service-card-link .glass-card .icon-circle {
      background: rgba(212, 175, 55, 0.06);
      border: 1px solid rgba(212, 175, 55, 0.08);
      transition: all 0.5s cubic-bezier(0.22, 1, 0.36, 1);
    }
    .service-card-link .glass-card:hover .icon-circle {
      background: rgba(212, 175, 55, 0.12);
      border-color: rgba(212, 175, 55, 0.25);
      transform: scale(1.08) rotate(-5deg);
      box-shadow: 0 0 40px rgba(212, 175, 55, 0.08);
    }

    /* Category filter */
    .service-category-filter {
      display: flex;
      flex-wrap: wrap;
      gap: 0.6rem;
      justify-content: center;
      margin-bottom: 2rem;
    }
    .service-category-filter .filter-btn {
      background: rgba(255,255,255,0.03);
      border: 1px solid rgba(255,255,255,0.08);
      border-radius: 50px;
      padding: 0.5rem 1.2rem;
      color: #A5A5A5;
      font-size: 0.8rem;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.3s ease;
    }
    .service-category-filter .filter-btn:hover,
    .service-category-filter .filter-btn.active {
      background: rgba(212, 175, 55, 0.1);
      border-color: #D4AF37;
      color: #D4AF37;
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
          <span class="section-badge"><i class="fas fa-cogs me-1"></i> Our Services</span>
          <h1 class="section-title">End-to-End <span class="gold">Digital Solutions</span></h1>
          <p class="text-secondary fs-5 mt-3">From web development to AI chatbots, graphic design to video editing – we've got you covered.</p>
          <div class="d-flex flex-wrap gap-3 mt-4">
            <a href="contact.php" class="btn-primary-custom"><i class="fas fa-arrow-right me-2"></i> Get Started</a>
            <a href="contact.php" class="btn-secondary-custom"><i class="fas fa-calendar-check me-2"></i> Book Free Consultation</a>
          </div>
        </div>
      </div>
    </section>

    <!-- ALL SERVICES - DYNAMIC FROM DATABASE -->
    <section class="py-5">
      <div class="container">
        <div class="text-center fade-up">
          <span class="section-badge"><i class="fas fa-list me-1"></i> What We Do</span>
          <h2 class="section-title">Our <span class="gold">Services</span></h2>
        </div>
        
        <!-- Category Filter -->
        <?php if (!empty($serviceCategories)): ?>
        <div class="service-category-filter">
          <button class="filter-btn active" data-category="all">All</button>
          <?php foreach ($serviceCategories as $category): ?>
            <button class="filter-btn" data-category="<?= htmlspecialchars($category['slug']) ?>">
              <?= htmlspecialchars($category['name']) ?>
            </button>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <?php if (empty($services)): ?>
          <div class="text-center py-5">
            <i class="fas fa-cubes fa-4x text-gold mb-3" style="opacity:0.3;"></i>
            <h4 class="fw-bold">No Services Available</h4>
            <p class="text-secondary small">Please check back later for our services.</p>
          </div>
        <?php else: ?>
          <div class="row g-4 mt-3">
            <?php foreach ($services as $service): 
              // Get the link for this service
              $serviceLink = getServicePage($service['name']);
              $category_slug = $service['category_slug'] ?? '';
            ?>
              <div class="col-md-4 fade-up service-item" data-category="<?= htmlspecialchars($category_slug) ?>">
                <a href="<?= $serviceLink ?>" class="service-card-link">
                  <div class="glass-card d-block text-center h-100">
                    <div class="icon-circle mx-auto mb-2">
                      <i class="fas <?= htmlspecialchars($service['icon'] ?? 'fa-cube') ?> fa-xl"></i>
                    </div>
                    <h4 class="fw-bold mt-2"><?= htmlspecialchars($service['name']) ?></h4>
                    <p class="text-secondary small"><?= htmlspecialchars($service['description']) ?></p>
                    <?php if (!empty($service['price'])): ?>
                      <span class="text-gold fw-bold small">From $<?= number_format($service['price']) ?></span>
                    <?php endif; ?>
                  </div>
                </a>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        
        <!-- Add a note if there are more services than shown -->
        <?php if (count($services) > 6): ?>
          <div class="text-center mt-4">
            <p class="text-secondary small">And more services available. <a href="contact.php" class="text-gold">Contact us</a> for details.</p>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <!-- WHY US - DYNAMIC -->
    <section class="py-5 bg-card/30">
      <div class="container">
        <div class="text-center fade-up">
          <span class="section-badge"><i class="fas fa-star me-1"></i> Why Us</span>
          <h2 class="section-title">Why Choose <span class="gold">Luxora</span></h2>
        </div>
        <div class="row g-4 mt-3">
          <?php foreach ($whyChooseUs as $item): ?>
            <div class="col-md-3 fade-up">
              <div class="glass-card text-center h-100">
                <i class="fas <?= htmlspecialchars($item['icon'] ?? 'fa-star') ?> fs-1 text-gold mb-2"></i>
                <h4 class="fw-bold"><?= htmlspecialchars($item['title']) ?></h4>
                <p class="text-secondary small"><?= htmlspecialchars($item['description']) ?></p>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section class="py-5 position-relative overflow-hidden">
      <div class="position-absolute top-0 start-0 w-100 h-100 bg-primary/5 blur-3xl"></div>
      <div class="container position-relative z-1">
        <div class="glass-gold rounded-4 p-5 text-center max-w-4xl mx-auto fade-up">
          <h2 class="section-title" style="font-size:clamp(2rem,4vw,3.5rem);">Ready to <span class="gold">Elevate</span> Your Brand?</h2>
          <p class="text-secondary fs-5 mt-3">Let's discuss your project and find the perfect solution.</p>
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
  
  <!-- Category Filter Script -->
  <script>
  document.addEventListener('DOMContentLoaded', function() {
    var filterBtns = document.querySelectorAll('.filter-btn');
    var serviceItems = document.querySelectorAll('.service-item');
    
    if (filterBtns.length > 0 && serviceItems.length > 0) {
      filterBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
          // Update active state
          filterBtns.forEach(function(b) { b.classList.remove('active'); });
          btn.classList.add('active');
          
          var category = btn.dataset.category;
          
          serviceItems.forEach(function(item) {
            var itemCategory = item.dataset.category || '';
            if (category === 'all' || itemCategory === category) {
              item.style.display = '';
            } else {
              item.style.display = 'none';
            }
          });
        });
      });
    }
  });
  </script>
</body>
</html>