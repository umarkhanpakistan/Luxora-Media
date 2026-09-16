<?php
session_start();
require_once 'db.php';

// Get all visible FAQs from database with user info and admin replies
// Only show QUESTIONS (not reviews)
try {
    $db = getDB();
    $stmt = $db->query("
        SELECT f.*, 
               i.replies as inquiry_replies,
               i.name as inquiry_name,
               i.email as inquiry_email,
               i.service_type as inquiry_type
        FROM faqs f
        LEFT JOIN inquiries i ON f.inquiry_id = i.id
        WHERE f.is_visible = 1 
        AND (i.service_type = 'Question' OR i.service_type IS NULL)
        ORDER BY f.display_order ASC, f.created_at DESC
    ");
    $faqs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch featured FAQs for the CTA section
    $stmt = $db->query("SELECT question, answer FROM faqs WHERE is_visible = 1 AND is_featured = 1 ORDER BY display_order ASC LIMIT 3");
    $featuredFaqs = $stmt->fetchAll();
} catch (PDOException $e) {
    $faqs = [];
    $featuredFaqs = [];
}

// Fallback featured FAQs
if (empty($featuredFaqs)) {
    $featuredFaqs = [
        ['question' => 'How quickly do you respond?', 'answer' => 'We reply within 24 hours, usually much faster.'],
        ['question' => 'Do you offer free consultations?', 'answer' => 'Yes – we offer a free 30‑minute consultation for all new clients.'],
        ['question' => 'What happens after I send a message?', 'answer' => 'We\'ll review your inquiry and reach out to schedule a call or meeting.']
    ];
}

// Check if there are any visible FAQs
$hasFaqs = count($faqs) > 0;
?>
<?php include 'admin-bar.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>FAQ – Luxora Media</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="luxora.css" />
  
  <style>
    /* ============================================= */
    /* FAQ ACCORDION - PREMIUM THEME MATCHED UI      */
    /* ============================================= */
    .lux-faq-item {
      margin-bottom: 1.2rem;
      background: linear-gradient(145deg, rgba(20, 20, 20, 0.95), rgba(12, 12, 12, 0.95));
      border-radius: 1.2rem;
      border: 1px solid rgba(212, 175, 55, 0.15);
      overflow: hidden;
      transition: all 0.4s cubic-bezier(0.22, 1, 0.36, 1);
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
    }
    .lux-faq-item:hover {
      border-color: rgba(212, 175, 55, 0.4);
      box-shadow: 0 8px 30px rgba(212, 175, 55, 0.06);
      transform: translateY(-2px);
    }
    .lux-faq-item.lux-open {
      border-color: rgba(212, 175, 55, 0.5);
      box-shadow: 0 10px 40px rgba(212, 175, 55, 0.1);
    }
    
    /* QUESTION HEADER */
    .lux-faq-q {
      padding: 1.2rem 1.5rem;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      color: #ffffff;
      font-weight: 600;
      font-size: 1.05rem;
      margin: 0;
      user-select: none;
      background: transparent;
      position: relative;
      overflow: hidden;
    }
    .lux-faq-q::before {
      content: '';
      position: absolute;
      left: 0;
      top: 0;
      bottom: 0;
      width: 3px;
      background: linear-gradient(180deg, #D4AF37, #F5C84C);
      transform: scaleY(0);
      transition: transform 0.3s ease;
    }
    .lux-faq-item.lux-open .lux-faq-q::before {
      transform: scaleY(1);
    }
    
    .lux-faq-q span:first-child {
      flex: 1;
      line-height: 1.5;
    }
    
    /* ICON CONTAINER */
    .lux-faq-icon-wrapper {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      background: rgba(212, 175, 55, 0.1);
      border: 1px solid rgba(212, 175, 55, 0.2);
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    .lux-faq-icon {
      color: #D4AF37;
      font-size: 0.8rem;
      transition: transform 0.4s ease;
    }
    .lux-faq-item.lux-open .lux-faq-icon-wrapper {
      background: linear-gradient(135deg, #D4AF37, #F5C84C);
    }
    .lux-faq-item.lux-open .lux-faq-icon {
      color: #050505;
      transform: rotate(180deg);
    }
    
    /* ANSWER SECTION */
    .lux-faq-a {
      padding: 0 1.5rem;
      max-height: 0;
      overflow: hidden;
      transition: max-height 0.6s cubic-bezier(0.22, 1, 0.36, 1), padding 0.6s ease, opacity 0.4s ease;
      color: #A5A5A5;
      line-height: 1.8;
      background: rgba(10, 10, 10, 0.5);
      opacity: 0;
    }
    .lux-faq-item.lux-open .lux-faq-a {
      padding: 1.5rem;
      max-height: 4000px;
      opacity: 1;
    }
    
    /* META INFO */
    .lux-faq-meta {
      display: flex;
      align-items: center;
      gap: 0.8rem;
      flex-wrap: wrap;
      margin-bottom: 1.2rem;
      padding-bottom: 1rem;
      border-bottom: 1px solid rgba(255,255,255,0.06);
    }
    .lux-faq-meta .user-info {
      display: flex;
      align-items: center;
      gap: 0.7rem;
    }
    .lux-faq-meta .user-avatar {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: linear-gradient(135deg, #D4AF37, #F5C84C);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.9rem;
      font-weight: 700;
      color: #050505;
      box-shadow: 0 4px 15px rgba(212, 175, 55, 0.2);
      flex-shrink: 0;
    }
    .lux-faq-meta .user-name {
      color: #ffffff;
      font-weight: 600;
      font-size: 0.9rem;
      display: block;
    }
    .lux-faq-meta .user-email {
      color: #888;
      font-size: 0.75rem;
    }
    .lux-faq-meta .faq-date {
      color: #888;
      font-size: 0.75rem;
      display: flex;
      align-items: center;
      gap: 0.3rem;
    }
    .lux-faq-meta .faq-date i {
      color: #D4AF37;
      font-size: 0.6rem;
    }
    .lux-faq-meta .faq-badge {
      background: rgba(46, 213, 115, 0.1);
      color: #2ed573;
      font-size: 0.65rem;
      padding: 0.3rem 0.8rem;
      border-radius: 50px;
      border: 1px solid rgba(46, 213, 115, 0.15);
      display: inline-flex;
      align-items: center;
      gap: 0.3rem;
      margin-left: auto;
    }
    .lux-faq-meta .faq-badge.pending {
      background: rgba(255, 165, 0, 0.1);
      color: #ffa502;
      border-color: rgba(255, 165, 0, 0.15);
    }
    
    /* QUESTION CONTENT */
    .lux-faq-question {
      color: #ffffff;
      font-size: 1rem;
      font-weight: 500;
      margin-bottom: 1rem;
      display: flex;
      align-items: flex-start;
      gap: 0.6rem;
      background: rgba(212, 175, 55, 0.03);
      padding: 0.8rem 1rem;
      border-radius: 8px;
      border-left: 3px solid #D4AF37;
    }
    .lux-faq-question i {
      color: #D4AF37;
      margin-top: 0.2rem;
    }
    
    /* ANSWER CONTENT */
    .lux-faq-answer {
      color: #A5A5A5;
      line-height: 1.8;
      padding-left: 0;
    }
    .lux-faq-answer .admin-reply {
      display: flex;
      align-items: flex-start;
      gap: 0.8rem;
      margin-top: 0.8rem;
      padding: 1rem 1.2rem;
      background: rgba(212, 175, 55, 0.04);
      border-radius: 12px;
      border: 1px solid rgba(212, 175, 55, 0.08);
    }
    .lux-faq-answer .admin-reply .admin-icon {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      background: linear-gradient(135deg, #D4AF37, #F5C84C);
      display: flex;
      align-items: center;
      justify-content: center;
      color: #050505;
      font-size: 0.9rem;
      flex-shrink: 0;
    }
    .lux-faq-answer .admin-reply .admin-name {
      color: #D4AF37;
      font-weight: 600;
      font-size: 0.85rem;
      display: block;
      margin-bottom: 0.2rem;
    }
    .lux-faq-answer .admin-reply .reply-text {
      color: #A5A5A5;
      line-height: 1.6;
    }
    .lux-faq-answer .admin-reply .reply-time {
      color: #666;
      font-size: 0.7rem;
      margin-top: 0.4rem;
      display: block;
    }

    /* PENDING STATUS */
    .lux-faq-answer .pending-reply {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      font-style: italic;
      padding: 0.8rem 1rem;
      background: rgba(255, 165, 0, 0.05);
      border-radius: 8px;
      border: 1px dashed rgba(255, 165, 0, 0.2);
      color: #ffa502;
    }

    /* EMPTY STATE */
    .lux-empty-faq {
      text-align: center;
      padding: 4rem 2rem;
      background: rgba(16, 16, 16, 0.4);
      border-radius: 1.5rem;
      border: 1px dashed rgba(212, 175, 55, 0.15);
    }
    .lux-empty-faq .empty-icon {
      font-size: 4.5rem;
      color: #D4AF37;
      opacity: 0.2;
      margin-bottom: 1.2rem;
      display: block;
    }
    .lux-empty-faq h4 {
      color: #ffffff;
      font-weight: 700;
      margin-bottom: 0.5rem;
      font-family: 'Poppins', sans-serif;
    }
    .lux-empty-faq p {
      color: #A5A5A5;
      font-size: 1rem;
      margin-bottom: 1.5rem;
    }
    .lux-empty-faq .btn-primary-custom {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
    }

    /* ============ RESPONSIVE STYLES ============ */
    @media (max-width: 768px) {
      .lux-faq-q {
        padding: 1rem 1.2rem;
        font-size: 0.95rem;
        gap: 0.8rem;
      }
      .lux-faq-a {
        padding: 0 1.2rem;
      }
      .lux-faq-item.lux-open .lux-faq-a {
        padding: 1.2rem;
      }
      .lux-faq-meta {
        gap: 0.5rem;
      }
      .lux-faq-meta .user-avatar {
        width: 34px;
        height: 34px;
        font-size: 0.75rem;
      }
      .lux-faq-meta .faq-badge {
        font-size: 0.6rem;
        margin-left: 0;
        width: 100%;
        justify-content: center;
        margin-top: 0.5rem;
      }
      .lux-faq-question {
        font-size: 0.9rem;
        padding: 0.7rem 0.8rem;
      }
      .lux-faq-answer .admin-reply {
        padding: 0.8rem;
        flex-direction: column;
        gap: 0.5rem;
      }
      .lux-faq-answer .admin-reply .admin-icon {
        width: 30px;
        height: 30px;
        font-size: 0.7rem;
      }
      .lux-empty-faq {
        padding: 2.5rem 1.5rem;
      }
      .lux-empty-faq .empty-icon {
        font-size: 3.5rem;
      }
    }
    @media (max-width: 480px) {
      .lux-faq-q {
        font-size: 0.9rem;
        padding: 0.9rem 1rem;
      }
      .lux-faq-icon-wrapper {
        width: 28px;
        height: 28px;
      }
      .lux-faq-item.lux-open .lux-faq-a {
        padding: 1rem;
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
  <div id="loader">
    <div class="loader-ring"></div>
    <div class="loader-text">LUXORA</div>
    <div class="loader-bar"><div class="loader-bar-fill"></div></div>
  </div>

  <!-- WHATSAPP FLOAT -->
  <a href="https://wa.me/4917646141373" target="_blank" class="whatsapp-float" aria-label="WhatsApp">
    <i class="fab fa-whatsapp fa-2x"></i>
  </a>

  <!-- BACK TO TOP -->
  <button id="backTop" class="back-top" aria-label="Back to top"><i class="fas fa-arrow-up"></i></button>

  <?php include 'user-nav.php'; ?>

  <main>
    <!-- HERO -->
    <section class="d-flex align-items-center" style="min-height:50vh;padding-top:80px;background:radial-gradient(ellipse at 30% 40%, rgba(212,175,55,0.06), transparent 60%);">
      <div class="container">
        <div class="max-w-3xl fade-up visible">
          <span class="section-badge"><i class="fas fa-question-circle me-1"></i> FAQ</span>
          <h1 class="section-title">Frequently Asked <span class="gold">Questions</span></h1>
          <p class="text-secondary fs-5 mt-3">Find answers to the most common questions our clients ask.</p>
        </div>
      </div>
    </section>

    <!-- FAQ SECTION -->
    <section class="py-5">
      <div class="container">
        <div class="row justify-content-center">
          <div class="col-lg-10">
            <?php if ($hasFaqs): ?>
              <?php foreach ($faqs as $index => $faq): 
                $replies = [];
                if (!empty($faq['inquiry_replies'])) {
                    $replies = json_decode($faq['inquiry_replies'], true);
                }
                $has_admin_reply = !empty($replies);
                $user_name = $faq['user_name'] ?? $faq['inquiry_name'] ?? 'Anonymous';
                $user_email = $faq['user_email'] ?? $faq['inquiry_email'] ?? '';
                $created_date = date('F d, Y', strtotime($faq['created_at']));
              ?>
                <!-- PREMIUM FAQ ITEM -->
                <div class="lux-faq-item <?= $index === 0 ? 'lux-open' : '' ?>" id="faq-<?= $faq['id'] ?>">
                  <div class="lux-faq-q" data-id="<?= $faq['id'] ?>">
                    <span><?= htmlspecialchars($faq['question']) ?></span>
                    <span class="lux-faq-icon-wrapper">
                      <i class="fas fa-chevron-down lux-faq-icon"></i>
                    </span>
                  </div>
                  <div class="lux-faq-a">
                    <div class="lux-faq-meta">
                      <div class="user-info">
                        <div class="user-avatar"><?= strtoupper(substr($user_name, 0, 1)) ?></div>
                        <div>
                          <span class="user-name"><?= htmlspecialchars($user_name) ?></span>
                          <?php if (!empty($user_email)): ?>
                            <span class="user-email"><?= htmlspecialchars($user_email) ?></span>
                          <?php endif; ?>
                        </div>
                      </div>
                      <span class="faq-date">
                        <i class="fas fa-calendar-alt"></i> <?= $created_date ?>
                      </span>
                      <?php if ($has_admin_reply): ?>
                        <span class="faq-badge"><i class="fas fa-check-circle"></i> Answered</span>
                      <?php else: ?>
                        <span class="faq-badge pending"><i class="fas fa-clock"></i> Pending</span>
                      <?php endif; ?>
                    </div>

                    <div class="lux-faq-question">
                      <i class="fas fa-question-circle"></i>
                      <span><?= nl2br(htmlspecialchars($faq['question'])) ?></span>
                    </div>

                    <div class="lux-faq-answer">
                      <?php if ($has_admin_reply): ?>
                        <?php foreach ($replies as $reply): ?>
                          <div class="admin-reply">
                            <div class="admin-icon">
                              <i class="fas fa-user-circle"></i>
                            </div>
                            <div>
                              <span class="admin-name">
                                <?= htmlspecialchars($reply['admin'] ?? 'Admin') ?>
                              </span>
                              <div class="reply-text">
                                <?= nl2br(htmlspecialchars($reply['message'])) ?>
                              </div>
                              <span class="reply-time">
                                <?= isset($reply['time']) ? date('F d, Y h:i A', strtotime($reply['time'])) : '' ?>
                              </span>
                            </div>
                          </div>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <div class="pending-reply">
                          <i class="fas fa-hourglass-half"></i>
                          <span>Awaiting admin response...</span>
                        </div>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <!-- EMPTY STATE -->
              <div class="lux-empty-faq">
                <i class="fas fa-question-circle empty-icon"></i>
                <h4>No Questions Yet</h4>
                <p>Have a question? We'd love to answer it!<br>Ask us anything about our services.</p>
                <a href="contact.php" class="btn-primary-custom">
                  <i class="fas fa-pen me-2"></i> Ask a Question
                </a>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </section>

    <!-- STILL HAVE QUESTIONS? -->
    <section class="py-5 position-relative overflow-hidden">
      <div class="position-absolute top-0 start-0 w-100 h-100 bg-primary/5 blur-3xl"></div>
      <div class="container position-relative z-1">
        <div class="glass-gold rounded-4 p-5 text-center max-w-4xl mx-auto fade-up">
          <h2 class="section-title" style="font-size:clamp(2rem,4vw,3.5rem);">Still Have <span class="gold">Questions</span>?</h2>
          <p class="text-secondary fs-5 mt-3">We're here to help. Reach out to us anytime.</p>
          <div class="d-flex flex-wrap justify-content-center gap-3 mt-3">
            <a href="contact.php" class="btn btn-primary-custom"><i class="fas fa-arrow-right me-2"></i> Contact Us</a>
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
  
  <!-- FAQ ACCORDION JS - MULTIPLE ITEMS CAN STAY OPEN -->
  <script>
  document.addEventListener('DOMContentLoaded', function() {
    // FAQ Accordion
    var faqQuestions = document.querySelectorAll('.lux-faq-q');
    
    faqQuestions.forEach(function(question) {
      question.addEventListener('click', function() {
        var item = question.closest('.lux-faq-item');
        var isOpen = item.classList.contains('lux-open');
        
        // Just toggle this item, don't close others
        if (isOpen) {
          item.classList.remove('lux-open');
        } else {
          item.classList.add('lux-open');
        }
      });
    });
    
    // Open first FAQ by default if there are any
    var firstFaq = document.querySelector('.lux-faq-item');
    if (firstFaq) {
      firstFaq.classList.add('lux-open');
    }
  });
  </script>
  
</body>
</html>