<?php
session_start();
require_once __DIR__ . '/db.php';

// Fetch pricing plans from database
$pricingPlans = [];
try {
    $db = getDB();
    $stmt = $db->query("SELECT * FROM pricing_plans WHERE is_active = 1 ORDER BY display_order ASC");
    $pricingPlans = $stmt->fetchAll();
} catch (PDOException $e) {
    $pricingPlans = [];
}

// Fallback pricing plans
if (empty($pricingPlans)) {
    $pricingPlans = [
        [
            'name' => 'Starter',
            'price' => 250,
            'period' => 'mo',
            'description' => 'Perfect for getting started',
            'features' => ['Video Editing - 5 videos', 'Logo Design', 'Business Card', 'Thumbnail - 10', '20 Business Posts', 'AI Chatbot - Basic'],
            'popular' => 0,
            'cta_text' => 'Start Now',
            'cta_link' => 'contact.php?service=Starter&plan=Starter&amount=250'
        ],
        [
            'name' => 'Business',
            'price' => 500,
            'period' => 'mo',
            'description' => 'For growing businesses',
            'features' => ['Video Editing - 10 videos', 'Graphic Designing', 'AI Automation', '50 Business Posts', 'SEO - Advanced', 'Business Card', '3 Page Website'],
            'popular' => 1,
            'cta_text' => 'Start Now',
            'cta_link' => 'contact.php?service=Business&plan=Business&amount=500'
        ],
        [
            'name' => 'Pro',
            'price' => 1000,
            'period' => 'mo',
            'description' => 'Full-scale digital agency',
            'features' => ['Video Editing - Unlimited', 'SEO - Premium', 'Full Stack Website', 'Graphic Designing', 'Business Card', 'Content Creation Script', 'Thumbnail - Unlimited', 'AI Automation - Advanced'],
            'popular' => 0,
            'cta_text' => 'Start Now',
            'cta_link' => 'contact.php?service=Pro&plan=Pro&amount=1000'
        ]
    ];
}

// Fetch comparison features from database
$comparisonFeatures = [];
try {
    $stmt = $db->query("SELECT * FROM comparison_features ORDER BY display_order ASC");
    $comparisonFeatures = $stmt->fetchAll();
} catch (PDOException $e) {
    $comparisonFeatures = [];
}

// Fallback comparison features
if (empty($comparisonFeatures)) {
    $comparisonFeatures = [
        ['name' => 'Video Editing', 'starter' => '5', 'business' => '10', 'pro' => '∞'],
        ['name' => 'SEO', 'starter' => '✗', 'business' => 'Advanced', 'pro' => 'Premium'],
        ['name' => 'Website Development', 'starter' => '✗', 'business' => '3 Pages', 'pro' => 'Full Stack'],
        ['name' => 'Graphic Designing', 'starter' => '✗', 'business' => '✓', 'pro' => '✓'],
        ['name' => 'Logo Design', 'starter' => '1', 'business' => '1', 'pro' => '2'],
        ['name' => 'Business Card', 'starter' => '✓', 'business' => '✓', 'pro' => '✓'],
        ['name' => 'Thumbnail', 'starter' => '10', 'business' => '20', 'pro' => '∞'],
        ['name' => 'Business Posts', 'starter' => '20', 'business' => '50', 'pro' => '∞'],
        ['name' => 'AI Chatbot', 'starter' => 'Basic', 'business' => 'Standard', 'pro' => 'Advanced'],
        ['name' => 'AI Automation', 'starter' => '✗', 'business' => 'Standard', 'pro' => 'Advanced'],
        ['name' => 'Content Script', 'starter' => '✗', 'business' => '✗', 'pro' => '✓'],
        ['name' => 'Support', 'starter' => 'Email', 'business' => 'Priority', 'pro' => '24/7']
    ];
}

// ===== FETCH PRICING-RELATED QUESTIONS FROM FAQS =====
$pricingFaqs = [];
try {
    $db = getDB();
    // Fetch questions that have category_slug = 'pricing'
    $stmt = $db->query("
        SELECT f.*, 
               i.name as inquiry_name,
               i.email as inquiry_email,
               i.replies as inquiry_replies
        FROM faqs f
        LEFT JOIN inquiries i ON f.inquiry_id = i.id
        WHERE f.is_visible = 1 
        AND f.category_slug = 'pricing'
        ORDER BY f.created_at DESC
        LIMIT 10
    ");
    $pricingFaqs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $pricingFaqs = [];
}

// ONLY show fallback pricing FAQs if NO dynamic ones exist
// IMPORTANT: Don't show fallback if there are dynamic questions
$showFallbackFaqs = empty($pricingFaqs);
?>
<?php include 'admin-bar.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Pricing – Luxora Media</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <link rel="stylesheet" href="luxora.css" />
  <style>
    /* Pricing Card Custom Styles */
    .pricing-card .feature-list {
      list-style: none;
      padding: 0;
      margin: 0;
    }
    .pricing-card .feature-list li {
      display: flex;
      align-items: center;
      gap: 0.6rem;
      padding: 0.4rem 0;
      font-size: 0.85rem;
      color: #c0c0c0;
      border-bottom: 1px solid rgba(255,255,255,0.03);
    }
    .pricing-card .feature-list li:last-child {
      border-bottom: none;
    }
    .pricing-card .feature-list li i {
      color: #d4af37;
      font-size: 0.7rem;
      flex-shrink: 0;
      width: 16px;
    }
    .pricing-card .feature-list li .feature-badge {
      background: rgba(212, 175, 55, 0.08);
      padding: 0.1rem 0.5rem;
      border-radius: 50px;
      font-size: 0.6rem;
      color: #d4af37;
      margin-left: auto;
      font-weight: 600;
      letter-spacing: 0.3px;
    }
    .pricing-card.popular .feature-list li .feature-badge {
      background: rgba(212, 175, 55, 0.15);
    }

    /* Comparison Table */
    .comparison-wrapper {
      background: rgba(16, 16, 16, 0.6);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      border-radius: 20px;
      border: 1px solid rgba(212, 175, 55, 0.08);
      padding: 0;
      overflow: hidden;
      box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    }
    .comparison-table {
      width: 100%;
      border-collapse: collapse;
    }
    .comparison-table thead th {
      background: rgba(212, 175, 55, 0.04);
      color: #d4af37;
      font-weight: 600;
      font-size: 0.7rem;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      padding: 1.2rem 1rem;
      border-bottom: 2px solid rgba(212, 175, 55, 0.1);
      text-align: center;
      font-family: 'Poppins', sans-serif;
    }
    .comparison-table thead th:first-child {
      text-align: left;
      padding-left: 1.5rem;
    }
    .comparison-table thead th .plan-price {
      display: block;
      font-size: 0.6rem;
      color: #a5a5a5;
      font-weight: 400;
      text-transform: none;
      letter-spacing: 0;
      margin-top: 0.2rem;
    }
    .comparison-table thead th .popular-tag {
      display: inline-block;
      background: linear-gradient(135deg, #d4af37, #f5c84c);
      color: #0a0a0a;
      font-size: 0.45rem;
      font-weight: 700;
      padding: 0.15rem 0.6rem;
      border-radius: 50px;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      margin-left: 0.3rem;
      vertical-align: middle;
    }
    .comparison-table tbody td {
      padding: 0.8rem 1rem;
      color: #e0e0e0;
      font-size: 0.85rem;
      border-bottom: 1px solid rgba(255,255,255,0.03);
      vertical-align: middle;
      text-align: center;
      background: transparent;
      font-family: 'Inter', sans-serif;
    }
    .comparison-table tbody td:first-child {
      text-align: left;
      padding-left: 1.5rem;
      font-weight: 500;
      color: #ffffff;
    }
    .comparison-table tbody tr:last-child td {
      border-bottom: none;
    }
    .comparison-table tbody tr {
      transition: background 0.3s ease;
    }
    .comparison-table tbody tr:hover {
      background: rgba(212, 175, 55, 0.02);
    }
    .comparison-table .feature-icon {
      margin-right: 0.6rem;
      color: #d4af37;
      font-size: 0.85rem;
    }
    .comparison-table .cross {
      color: #ff4757;
      font-size: 1.1rem;
    }
    .comparison-table .gold-check {
      color: #d4af37;
      font-size: 1.1rem;
    }
    .comparison-table .feature-badge-table {
      display: inline-block;
      background: rgba(212, 175, 55, 0.08);
      padding: 0.1rem 0.5rem;
      border-radius: 50px;
      font-size: 0.6rem;
      color: #d4af37;
      font-weight: 600;
    }
    .comparison-table .feature-badge-table.gold {
      background: rgba(212, 175, 55, 0.15);
    }

    .table-responsive-wrap {
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
    }
    .table-responsive-wrap::-webkit-scrollbar {
      height: 4px;
    }
    .table-responsive-wrap::-webkit-scrollbar-track {
      background: rgba(255,255,255,0.02);
    }
    .table-responsive-wrap::-webkit-scrollbar-thumb {
      background: #d4af37;
      border-radius: 12px;
    }

    /* Pricing FAQ Items - Dynamic Questions */
    .pricing-faq-item {
      background: rgba(16, 16, 16, 0.5);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      border: 1px solid rgba(212, 175, 55, 0.08);
      border-radius: 1rem;
      padding: 1.2rem 1.5rem;
      transition: all 0.4s cubic-bezier(0.22, 1, 0.36, 1);
      margin-bottom: 1rem;
    }
    .pricing-faq-item:hover {
      border-color: rgba(212, 175, 55, 0.2);
      transform: translateY(-2px);
      box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    }
    .pricing-faq-item .faq-q {
      display: flex;
      align-items: center;
      justify-content: space-between;
      cursor: pointer;
      gap: 1rem;
      user-select: none;
    }
    .pricing-faq-item .faq-q .question-text {
      color: #ffffff;
      font-weight: 600;
      font-size: 1rem;
      flex: 1;
    }
    .pricing-faq-item .faq-q .faq-icon {
      color: #D4AF37;
      transition: transform 0.3s ease;
      flex-shrink: 0;
    }
    .pricing-faq-item.open .faq-q .faq-icon {
      transform: rotate(180deg);
    }
    .pricing-faq-item .faq-a {
      max-height: 0;
      overflow: hidden;
      transition: max-height 0.4s ease, padding 0.4s ease, opacity 0.3s ease;
      opacity: 0;
      padding: 0;
    }
    .pricing-faq-item.open .faq-a {
      max-height: 500px;
      opacity: 1;
      padding-top: 1rem;
    }
    .pricing-faq-item .faq-a .answer-text {
      color: #A5A5A5;
      line-height: 1.7;
      font-size: 0.95rem;
    }
    .pricing-faq-item .faq-a .answer-text .admin-reply {
      display: block;
      margin-top: 0.5rem;
      padding: 0.8rem 1rem;
      background: rgba(212, 175, 55, 0.03);
      border-radius: 8px;
      border-left: 3px solid #D4AF37;
    }
    .pricing-faq-item .faq-a .answer-text .admin-reply .admin-label {
      color: #D4AF37;
      font-weight: 600;
      font-size: 0.8rem;
    }
    .pricing-faq-item .faq-meta {
      display: flex;
      align-items: center;
      gap: 1rem;
      margin-top: 0.5rem;
      font-size: 0.75rem;
      color: #666;
    }
    .pricing-faq-item .faq-meta .user-name {
      color: #A5A5A5;
    }
    .pricing-faq-item .faq-meta .user-name i {
      color: #D4AF37;
      margin-right: 0.3rem;
    }
    .pricing-faq-item .faq-meta .faq-date {
      color: #666;
    }
    .pricing-faq-item .faq-meta .faq-date i {
      color: #D4AF37;
      margin-right: 0.3rem;
    }
    .pricing-faq-item .faq-status {
      display: inline-flex;
      align-items: center;
      gap: 0.3rem;
      font-size: 0.6rem;
      padding: 0.15rem 0.6rem;
      border-radius: 50px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }
    .pricing-faq-item .faq-status.answered {
      background: rgba(46, 213, 115, 0.1);
      color: #2ed573;
      border: 1px solid rgba(46, 213, 115, 0.15);
    }
    .pricing-faq-item .faq-status.pending {
      background: rgba(255, 165, 0, 0.1);
      color: #ffa502;
      border: 1px solid rgba(255, 165, 0, 0.15);
    }

    .empty-pricing-faqs {
      text-align: center;
      padding: 2.5rem 2rem;
      background: rgba(16, 16, 16, 0.3);
      border-radius: 1.5rem;
      border: 1px dashed rgba(212, 175, 55, 0.1);
    }
    .empty-pricing-faqs .empty-icon {
      font-size: 2.5rem;
      color: #D4AF37;
      opacity: 0.2;
      margin-bottom: 0.8rem;
      display: block;
    }
    .empty-pricing-faqs h6 {
      color: #ffffff;
      font-weight: 600;
      margin-bottom: 0.3rem;
    }
    .empty-pricing-faqs p {
      color: #A5A5A5;
      font-size: 0.9rem;
    }
    .empty-pricing-faqs a {
      color: #D4AF37;
      text-decoration: none;
    }
    .empty-pricing-faqs a:hover {
      text-decoration: underline;
    }

    @media (max-width: 768px) {
      .comparison-table thead th {
        font-size: 0.6rem;
        padding: 0.8rem 0.5rem;
      }
      .comparison-table tbody td {
        font-size: 0.75rem;
        padding: 0.6rem 0.5rem;
      }
      .comparison-table tbody td:first-child {
        padding-left: 0.8rem;
        font-size: 0.75rem;
      }
      .comparison-table thead th:first-child {
        padding-left: 0.8rem;
      }
      .comparison-table .feature-icon {
        margin-right: 0.3rem;
        font-size: 0.7rem;
      }
      .comparison-table .popular-tag {
        font-size: 0.4rem !important;
        padding: 0.1rem 0.4rem !important;
      }
      .comparison-table .plan-price {
        font-size: 0.5rem !important;
      }
      .comparison-wrapper {
        border-radius: 14px;
      }
      .pricing-card {
        padding: 1.5rem;
      }
      .pricing-card .feature-list li {
        font-size: 0.8rem;
      }
      .pricing-faq-item {
        padding: 1rem;
      }
      .pricing-faq-item .faq-q .question-text {
        font-size: 0.9rem;
      }
    }

    @media (max-width: 576px) {
      .comparison-table thead th {
        font-size: 0.5rem;
        padding: 0.5rem 0.3rem;
        letter-spacing: 0.04em;
      }
      .comparison-table tbody td {
        font-size: 0.65rem;
        padding: 0.4rem 0.3rem;
      }
      .comparison-table tbody td:first-child {
        padding-left: 0.5rem;
        font-size: 0.65rem;
        min-width: 70px;
      }
      .comparison-table thead th:first-child {
        padding-left: 0.5rem;
        min-width: 70px;
      }
      .comparison-table .feature-icon {
        margin-right: 0.2rem;
        font-size: 0.6rem;
      }
      .comparison-table .gold-check,
      .comparison-table .cross {
        font-size: 0.8rem;
      }
      .comparison-table .feature-badge-table {
        font-size: 0.5rem;
        padding: 0.05rem 0.3rem;
      }
      .comparison-wrapper {
        border-radius: 10px;
      }
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
  <div id="loader"><div class="loader-ring"></div><div class="loader-text">LUXORA</div><div class="loader-bar"><div class="loader-bar-fill"></div></div></div>

  <!-- BACK TO TOP -->
  <button id="backTop" class="back-top"><i class="fas fa-arrow-up"></i></button>

<?php include 'user-nav.php'; ?>

  <main>
    <!-- HERO -->
    <section class="d-flex align-items-center" style="min-height:40vh;padding-top:80px;background:radial-gradient(ellipse at 30% 40%, rgba(212,175,55,0.06), transparent 60%);">
      <div class="container">
        <div class="max-w-3xl fade-up visible">
          <span class="section-badge"><i class="fas fa-tag me-1"></i> Pricing</span>
          <h1 class="section-title">Choose Your <span class="gold">Plan</span></h1>
          <p class="text-secondary fs-5 mt-3">Flexible pricing for every budget. All plans include premium support and a 30‑day satisfaction guarantee.</p>
        </div>
      </div>
    </section>

    <!-- PRICING CARDS - DYNAMIC -->
    <section class="py-5">
      <div class="container">
        <div class="row g-4">
          <?php foreach ($pricingPlans as $plan): 
            $features = is_string($plan['features']) ? json_decode($plan['features'], true) : $plan['features'];
            if (!is_array($features)) {
                $features = [];
            }
            $isPopular = isset($plan['popular']) && $plan['popular'] == 1;
            $planName = htmlspecialchars($plan['name']);
            $planLink = htmlspecialchars($plan['cta_link'] ?? 'contact.php?service=' . urlencode($planName) . '&plan=' . urlencode($planName) . '&amount=' . $plan['price']);
          ?>
          <div class="col-12 col-md-4 fade-up">
            <div class="pricing-card <?= $isPopular ? 'popular' : '' ?>">
              <?php if ($isPopular): ?>
                <span class="popular-badge"><i class="fas fa-star me-1"></i> Most Popular</span>
              <?php endif; ?>
              <h5 class="fw-bold"><?= $planName ?></h5>
              <div class="fs-1 fw-black mt-2">$<?= number_format($plan['price']) ?><span class="text-secondary fs-6 fw-normal">/<?= htmlspecialchars($plan['period'] ?? 'mo') ?></span></div>
              <p class="text-secondary small mt-1"><?= htmlspecialchars($plan['description'] ?? '') ?></p>
              <ul class="feature-list mt-3">
                <?php foreach ($features as $feature): ?>
                  <li><i class="fas fa-check-circle"></i> <?= htmlspecialchars($feature) ?></li>
                <?php endforeach; ?>
              </ul>
              <a href="<?= $planLink ?>" class="btn <?= $isPopular ? 'btn-primary-custom' : 'btn-gold-outline' ?> w-100 justify-content-center mt-3">
                <i class="fas fa-arrow-right me-2"></i> <?= htmlspecialchars($plan['cta_text'] ?? 'Start Now') ?>
              </a>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <div class="text-center mt-3">
          <p class="text-secondary small"><i class="fas fa-comment text-gold me-1"></i> Need a custom plan? <a href="contact.php" class="text-gold text-decoration-none">Contact us</a></p>
        </div>
      </div>
    </section>

    <!-- FEATURE COMPARISON - DYNAMIC -->
    <section class="py-5 bg-card/30">
      <div class="container">
        <div class="text-center fade-up">
          <span class="section-badge"><i class="fas fa-table me-1"></i> Compare</span>
          <h2 class="section-title">What's <span class="gold">Included</span></h2>
          <p class="text-secondary mt-2">Compare features across all plans to find the perfect fit for your business.</p>
        </div>
        
        <?php
        // Get plan names for comparison headers
        $planNames = array_column($pricingPlans, 'name');
        $planPrices = array_column($pricingPlans, 'price');
        $planPopular = array_column($pricingPlans, 'popular');
        ?>
        <div class="comparison-wrapper mt-4">
          <div class="table-responsive-wrap">
            <table class="comparison-table">
              <thead>
                <tr>
                  <th>Feature</th>
                  <?php foreach ($planNames as $idx => $name): ?>
                    <th>
                      <?= htmlspecialchars($name) ?>
                      <?php if (isset($planPopular[$idx]) && $planPopular[$idx]): ?>
                        <span class="popular-tag">Popular</span>
                      <?php endif; ?>
                      <span class="plan-price">$<?= number_format($planPrices[$idx] ?? 0) ?>/mo</span>
                    </th>
                  <?php endforeach; ?>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($comparisonFeatures as $feature): 
                  $featureName = htmlspecialchars($feature['name']);
                  $starterVal = htmlspecialchars($feature['starter'] ?? '');
                  $businessVal = htmlspecialchars($feature['business'] ?? '');
                  $proVal = htmlspecialchars($feature['pro'] ?? '');
                  $icon = '';
                  if (strpos($featureName, 'Video') !== false) $icon = 'fa-film';
                  elseif (strpos($featureName, 'SEO') !== false) $icon = 'fa-magnifying-glass-chart';
                  elseif (strpos($featureName, 'Website') !== false) $icon = 'fa-code';
                  elseif (strpos($featureName, 'Graphic') !== false) $icon = 'fa-palette';
                  elseif (strpos($featureName, 'Logo') !== false) $icon = 'fa-pen-fancy';
                  elseif (strpos($featureName, 'Business Card') !== false) $icon = 'fa-id-card';
                  elseif (strpos($featureName, 'Thumbnail') !== false) $icon = 'fa-image';
                  elseif (strpos($featureName, 'Business Posts') !== false) $icon = 'fa-newspaper';
                  elseif (strpos($featureName, 'AI Chatbot') !== false) $icon = 'fa-robot';
                  elseif (strpos($featureName, 'AI Automation') !== false) $icon = 'fa-gear';
                  elseif (strpos($featureName, 'Content Script') !== false) $icon = 'fa-pencil';
                  elseif (strpos($featureName, 'Support') !== false) $icon = 'fa-headset';
                  else $icon = 'fa-cube';
                ?>
                <tr>
                  <td><i class="fas <?= $icon ?> feature-icon"></i> <?= $featureName ?></td>
                  <td><?= formatComparisonValue($starterVal) ?></td>
                  <td><?= formatComparisonValue($businessVal) ?></td>
                  <td><?= formatComparisonValue($proVal) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        
        <div class="text-center mt-3">
          <p class="text-secondary small"><i class="fas fa-star text-gold me-1"></i> All plans include a 30-day satisfaction guarantee</p>
        </div>
      </div>
    </section>

    <!-- PRICING FAQ - DYNAMIC QUESTIONS FROM DATABASE -->
    <section class="py-5">
      <div class="container">
        <div class="text-center fade-up">
          <span class="section-badge"><i class="fas fa-question-circle me-1"></i> FAQ</span>
          <h2 class="section-title">Pricing <span class="gold">Questions</span></h2>
          <p class="text-secondary mt-2">Common questions about our pricing plans, answered.</p>
        </div>
        <div class="row justify-content-center mt-3">
          <div class="col-lg-8">
            <?php if (!empty($pricingFaqs)): ?>
              <?php foreach ($pricingFaqs as $index => $faq):
                // Check if there are admin replies
                $replies = [];
                if (!empty($faq['inquiry_replies'])) {
                    $replies = json_decode($faq['inquiry_replies'], true);
                }
                $hasAdminReply = !empty($replies);
                
                // ONLY mark as answered if there are actual admin replies
                // NOT if the answer is just "Waiting for admin response..."
                $isAnswered = $hasAdminReply;
                
                $userName = $faq['inquiry_name'] ?? 'Anonymous';
                $createdDate = date('M d, Y', strtotime($faq['created_at'] ?? 'now'));
                $answerText = $faq['answer'] ?? '';
              ?>
                <div class="pricing-faq-item <?= $index === 0 ? 'open' : '' ?>" id="pricingFaq_<?= $faq['id'] ?? $index ?>">
                  <div class="faq-q" onclick="togglePricingFaq(<?= $faq['id'] ?? $index ?>)">
                    <span class="question-text"><?= htmlspecialchars($faq['question'] ?? '') ?></span>
                    <span class="faq-icon"><i class="fas fa-chevron-down"></i></span>
                  </div>
                  <div class="faq-a">
                    <div class="answer-text">
                      <?php if ($isAnswered): ?>
                        <?php foreach ($replies as $reply): ?>
                          <div class="admin-reply">
                            <span class="admin-label"><i class="fas fa-user-circle"></i> <?= htmlspecialchars($reply['admin'] ?? 'Admin') ?>:</span>
                            <?= nl2br(htmlspecialchars($reply['message'] ?? '')) ?>
                          </div>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <p class="text-secondary" style="font-style:italic;">Awaiting admin response...</p>
                      <?php endif; ?>
                    </div>
                    <div class="faq-meta">
                      <span class="user-name"><i class="fas fa-user"></i> <?= htmlspecialchars($userName) ?></span>
                      <span class="faq-date"><i class="far fa-calendar-alt"></i> <?= $createdDate ?></span>
                      <span class="faq-status <?= $isAnswered ? 'answered' : 'pending' ?>">
                        <i class="fas <?= $isAnswered ? 'fa-check-circle' : 'fa-clock' ?>"></i>
                        <?= $isAnswered ? 'Answered' : 'Pending' ?>
                      </span>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <!-- FALLBACK: Only shown when NO dynamic pricing questions exist -->
              <div class="empty-pricing-faqs">
                <i class="fas fa-question-circle empty-icon"></i>
                <h6>No Pricing Questions Yet</h6>
                <p>Have a question about our pricing? <a href="contact.php" class="text-gold text-decoration-none">Ask us</a> and we'll answer it here!</p>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section class="py-5 position-relative overflow-hidden">
      <div class="position-absolute top-0 start-0 w-100 h-100 bg-primary/5 blur-3xl"></div>
      <div class="container position-relative z-1">
        <div class="glass-gold rounded-4 p-5 text-center max-w-4xl mx-auto fade-up">
          <h2 class="section-title" style="font-size:clamp(2rem,4vw,3.5rem);">Start Your <span class="gold">Project</span> Today</h2>
          <p class="text-secondary fs-5 mt-3">Get started with a plan that fits your needs and budget.</p>
          <div class="d-flex flex-wrap justify-content-center gap-3 mt-3">
            <a href="contact.php" class="btn btn-primary-custom"><i class="fas fa-arrow-right me-2"></i> Get Started</a>
            <a href="contact.php" class="btn btn-secondary-custom"><i class="fas fa-calendar-check me-2"></i> Book Free Consultation</a>
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
        <div class="col-md-3"><h6 class="fw-bold text-uppercase small text-secondary"><i class="fas fa-envelope me-1"></i> Newsletter</h6><form class="d-flex gap-2" onsubmit="event.preventDefault();showToast('✓ Subscribed!')"><input type="email" placeholder="Your email" class="form-input flex-1" style="flex:1;" required /><button type="submit" class="btn btn-primary-custom px-3 py-2"><i class="fas fa-arrow-right"></i></button></form></div>
      </div>
      <div class="border-top border-white/5 mt-4 pt-3 d-flex flex-wrap justify-content-between small text-secondary"><span><i class="far fa-copyright me-1"></i> 2026 Luxora Media. All Rights Reserved.</span><span>Made with <i class="fas fa-heart text-gold"></i> in Berlin</span></div>
    </div>
  </footer>

  <div id="toast" class="toast-notification"><i class="fas fa-check-circle text-gold me-2"></i> Message sent! We'll <span class="gold">get back to you</span> soon.</div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="luxora.js"></script>
  
  <?php
  // Helper function to format comparison values
  function formatComparisonValue($value) {
      if ($value === '✓') {
          return '<i class="fas fa-check gold-check"></i>';
      } elseif ($value === '✗') {
          return '<i class="fas fa-times cross"></i>';
      } elseif ($value === '∞') {
          return '<i class="fas fa-infinity gold-check"></i>';
      } else {
          $badgeClass = 'feature-badge-table';
          if (strpos($value, 'Advanced') !== false || strpos($value, 'Premium') !== false || 
              strpos($value, 'Unlimited') !== false || strpos($value, 'Full Stack') !== false ||
              strpos($value, '24/7') !== false || strpos($value, 'Priority') !== false) {
              $badgeClass .= ' gold';
          }
          return '<span class="' . $badgeClass . '">' . htmlspecialchars($value) . '</span>';
      }
  }
  ?>

  <!-- Pricing FAQ Toggle Script -->
  <script>
  function togglePricingFaq(id) {
    var item = document.getElementById('pricingFaq_' + id);
    if (item) {
      item.classList.toggle('open');
    }
  }

  // Auto-open first FAQ
  document.addEventListener('DOMContentLoaded', function() {
    var firstFaq = document.querySelector('.pricing-faq-item');
    if (firstFaq) {
      firstFaq.classList.add('open');
    }
  });
  </script>
  
</body>
</html>