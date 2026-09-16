<?php
session_start();
require_once __DIR__ . '/db.php';

// Fetch dynamic stats from database
try {
    $db = getDB();
    
    // Total Projects
    $stmt = $db->query("SELECT COUNT(*) as total FROM projects");
    $totalProjects = $stmt->fetch()['total'] ?? 0;
    
    // Active Projects (not cancelled)
    $stmt = $db->query("SELECT COUNT(*) as total FROM projects WHERE status != 'Cancelled'");
    $activeProjects = $stmt->fetch()['total'] ?? 0;
    
    // Total Clients (excluding admins)
    $stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE role != 'admin'");
    $totalClients = $stmt->fetch()['total'] ?? 0;
    
    // Total Inquiries
    $stmt = $db->query("SELECT COUNT(*) as total FROM inquiries");
    $totalInquiries = $stmt->fetch()['total'] ?? 0;
    
    // Satisfaction Rate - Calculate from projects with status 'Paid'
    $stmt = $db->query("SELECT COUNT(*) as total FROM projects");
    $allProjects = $stmt->fetch()['total'] ?? 0;
    $stmt = $db->query("SELECT COUNT(*) as total FROM projects WHERE status = 'Paid'");
    $paidProjects = $stmt->fetch()['total'] ?? 0;
    $satisfactionRate = $allProjects > 0 ? round(($paidProjects / $allProjects) * 100) : 98;
    if ($satisfactionRate > 98 && $allProjects > 0) {
        $satisfactionRate = 98;
    }
    
    // Years of Experience - Calculate from earliest project OR company founding
    $yearsExperience = 4; // Default fallback
    try {
        $stmt = $db->query("SELECT year FROM timeline_events ORDER BY year ASC LIMIT 1");
        $firstEvent = $stmt->fetch();
        if ($firstEvent && !empty($firstEvent['year'])) {
            $yearStr = preg_replace('/[^0-9]/', '', $firstEvent['year']);
            if (!empty($yearStr) && is_numeric($yearStr) && $yearStr > 1900) {
                $foundingYear = intval($yearStr);
                $yearsExperience = max(1, date('Y') - $foundingYear);
            }
        }
    } catch (PDOException $e) {
        // Fallback to projects
    }
    
    if ($yearsExperience == 4) {
        try {
            $stmt = $db->query("SELECT MIN(created_at) as first_project FROM projects");
            $firstProjectDate = $stmt->fetch()['first_project'] ?? null;
            if ($firstProjectDate) {
                $yearsExperience = max(1, floor((time() - strtotime($firstProjectDate)) / (365 * 24 * 60 * 60)));
            }
        } catch (PDOException $e) {
            // Keep default
        }
    }
    
    // Fetch core values from database if table exists
    $coreValues = [];
    try {
        $stmt = $db->query("SELECT * FROM core_values WHERE is_active = 1 ORDER BY display_order ASC LIMIT 4");
        $coreValues = $stmt->fetchAll();
    } catch (PDOException $e) {
        $coreValues = [];
    }

    // Fetch timeline items
    $timelineItems = [];
    try {
        $stmt = $db->query("SELECT * FROM timeline_events ORDER BY year ASC LIMIT 4");
        $timelineItems = $stmt->fetchAll();
    } catch (PDOException $e) {
        $timelineItems = [];
    }

    // Fetch social links from database
    $socialLinks = [];
    try {
        $stmt = $db->query("SELECT platform, url FROM social_links WHERE is_active = 1 ORDER BY display_order ASC");
        $socialLinks = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (PDOException $e) {
        $socialLinks = [];
    }
    
    // ===== FETCH REVIEWS FROM DATABASE =====
    $reviews = [];
    try {
        $stmt = $db->query("
            SELECT f.*, 
                   i.name as inquiry_name,
                   i.email as inquiry_email,
                   i.rating as inquiry_rating,
                   i.created_at as inquiry_created_at
            FROM faqs f
            LEFT JOIN inquiries i ON f.inquiry_id = i.id
            WHERE f.is_visible = 1 
            AND i.rating IS NOT NULL 
            AND i.rating > 0
            AND i.service_type = 'Review'
            ORDER BY f.created_at DESC
            LIMIT 10
        ");
        $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $reviews = [];
    }
    
    // Calculate average rating
    $avgRating = 0;
    $totalReviews = count($reviews);
    if ($totalReviews > 0) {
        $sum = 0;
        foreach ($reviews as $review) {
            $sum += intval($review['inquiry_rating'] ?? 0);
        }
        $avgRating = round($sum / $totalReviews, 1);
    }
    
} catch (PDOException $e) {
    // Fallback values if database fails
    $totalProjects = 0;
    $activeProjects = 0;
    $totalClients = 0;
    $totalInquiries = 0;
    $satisfactionRate = 98;
    $yearsExperience = 4;
    $coreValues = [];
    $timelineItems = [];
    $socialLinks = [];
    $reviews = [];
    $avgRating = 0;
    $totalReviews = 0;
}

// Fallback core values
if (empty($coreValues)) {
    $coreValues = [
        ['icon' => 'fa-bolt', 'title' => 'Excellence', 'description' => 'We never settle for "good enough".'],
        ['icon' => 'fa-handshake', 'title' => 'Integrity', 'description' => 'Honest, transparent partnerships.'],
        ['icon' => 'fa-rocket', 'title' => 'Innovation', 'description' => 'Pushing boundaries with every project.'],
        ['icon' => 'fa-heart', 'title' => 'Client First', 'description' => 'Your success is our success.']
    ];
}

// Get founding year
$foundingYear = date('Y') - $yearsExperience;

// Fallback timeline
if (empty($timelineItems)) {
    $timelineItems = [
        ['year' => $foundingYear, 'description' => 'Luxora Media founded.'],
        ['year' => $foundingYear + 1, 'description' => 'First 100 clients, expanded services.'],
        ['year' => date('Y') - 2, 'description' => 'Launched AI Chatbot division.'],
        ['year' => date('Y') . '+', 'description' => 'Global expansion, ' . $totalClients . '+ clients and growing.']
    ];
}

// Define team members - STATIC as requested
$teamMembers = [
    [
        'name' => 'Muhammad Umar Khan',
        'role' => 'Lead Developer',
        'email' => 'umarkhanpakistan812@gmail.com',
        'linkedin' => 'https://www.linkedin.com/in/umar-khan-72baa4361/',
        'instagram' => null,
        'twitter' => null
    ],
    [
        'name' => 'Kashan',
        'role' => 'Agency Owner',
        'email' => 'luxoramedia89@gmail.com',
        'linkedin' => null,
        'instagram' => 'https://www.instagram.com/luxoramedia_/',
        'twitter' => null
    ]
];

// Helper function to render star rating
function renderStars($rating, $max = 5) {
    $stars = '';
    $fullStars = floor($rating);
    $halfStar = ($rating - $fullStars) >= 0.5 ? 1 : 0;
    $emptyStars = $max - $fullStars - $halfStar;
    
    for ($i = 0; $i < $fullStars; $i++) {
        $stars .= '<i class="fas fa-star"></i>';
    }
    if ($halfStar) {
        $stars .= '<i class="fas fa-star-half-alt"></i>';
    }
    for ($i = 0; $i < $emptyStars; $i++) {
        $stars .= '<i class="fas fa-star empty"></i>';
    }
    return $stars;
}
?>
<?php include 'admin-bar.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>About – Luxora Media</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="luxora.css" />
  <style>
    .hero-about {
      background: radial-gradient(ellipse at 20% 50%, rgba(212,175,55,0.06), transparent 60%);
    }

    /* Team Card Styles */
    .team-card {
      background: linear-gradient(145deg, rgba(18, 18, 18, 0.95), rgba(10, 10, 10, 0.95));
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      border: 1px solid rgba(212, 175, 55, 0.15);
      border-radius: 1.5rem;
      padding: 2rem 1.5rem;
      text-align: center;
      transition: all 0.5s cubic-bezier(0.22, 1, 0.36, 1);
      height: 100%;
      position: relative;
      overflow: hidden;
    }
    .team-card::before {
      content: '';
      position: absolute;
      top: -50%;
      left: -50%;
      width: 200%;
      height: 200%;
      background: radial-gradient(circle at 50% 30%, rgba(212, 175, 55, 0.04), transparent 60%);
      pointer-events: none;
    }
    .team-card:hover {
      border-color: rgba(212, 175, 55, 0.35);
      transform: translateY(-8px);
      box-shadow: 0 30px 80px rgba(212, 175, 55, 0.06), 0 0 40px rgba(212, 175, 55, 0.04);
    }
    .team-card .team-avatar {
      width: 100px;
      height: 100px;
      border-radius: 50%;
      background: linear-gradient(135deg, #D4AF37, #F5C84C);
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 1rem;
      font-size: 2.8rem;
      font-weight: 800;
      color: #050505;
      box-shadow: 0 8px 30px rgba(212, 175, 55, 0.2);
      transition: all 0.4s ease;
      font-family: 'Poppins', sans-serif;
    }
    .team-card:hover .team-avatar {
      transform: scale(1.05) rotate(-2deg);
      box-shadow: 0 12px 40px rgba(212, 175, 55, 0.3);
    }
    .team-card .team-name {
      font-size: 1.3rem;
      font-weight: 700;
      color: #ffffff;
      font-family: 'Poppins', sans-serif;
      margin-bottom: 0.2rem;
    }
    .team-card .team-role {
      color: #D4AF37;
      font-size: 0.85rem;
      font-weight: 500;
      margin-bottom: 0.5rem;
    }
    .team-card .team-email {
      color: #A5A5A5;
      font-size: 0.8rem;
      margin-bottom: 1rem;
      word-break: break-all;
    }
    .team-card .team-email a {
      color: #A5A5A5;
      text-decoration: none;
      transition: color 0.3s ease;
    }
    .team-card .team-email a:hover {
      color: #D4AF37;
    }
    .team-card .team-socials {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.8rem;
      margin-top: 0.5rem;
    }
    .team-card .team-socials a {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.03);
      border: 1px solid rgba(255, 255, 255, 0.06);
      color: #A5A5A5;
      font-size: 1rem;
      transition: all 0.3s ease;
      text-decoration: none;
    }
    .team-card .team-socials a:hover {
      background: rgba(212, 175, 55, 0.1);
      border-color: #D4AF37;
      color: #D4AF37;
      transform: translateY(-3px);
      box-shadow: 0 8px 25px rgba(212, 175, 55, 0.15);
    }
    .team-card .team-socials a.linkedin:hover {
      background: rgba(0, 119, 181, 0.15);
      border-color: #0a66c2;
      color: #0a66c2;
    }
    .team-card .team-socials a.instagram:hover {
      background: rgba(225, 48, 108, 0.15);
      border-color: #E1306C;
      color: #E1306C;
    }
    .team-card .team-socials a.twitter:hover {
      background: rgba(29, 161, 242, 0.15);
      border-color: #1DA1F2;
      color: #1DA1F2;
    }
    .team-card .badge-role {
      display: inline-block;
      font-size: 0.6rem;
      padding: 0.15rem 0.7rem;
      border-radius: 50px;
      background: rgba(212, 175, 55, 0.1);
      border: 1px solid rgba(212, 175, 55, 0.15);
      color: #D4AF37;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      margin-bottom: 0.5rem;
    }

    /* ===== REVIEW CARDS - SINGLE CLEAN DESIGN ===== */
    .review-card {
      background: linear-gradient(145deg, rgba(18, 18, 18, 0.92), rgba(10, 10, 10, 0.92));
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      border: 1px solid rgba(212, 175, 55, 0.1);
      border-radius: 1.2rem;
      padding: 1.5rem;
      transition: all 0.4s cubic-bezier(0.22, 1, 0.36, 1);
      height: 100%;
      position: relative;
      display: flex;
      flex-direction: column;
    }
    .review-card:hover {
      border-color: rgba(212, 175, 55, 0.25);
      transform: translateY(-6px);
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4), 0 0 40px rgba(212, 175, 55, 0.04);
    }
    
    .review-card .review-stars {
      font-size: 1.1rem;
      letter-spacing: 2px;
      margin-bottom: 0.8rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    .review-card .review-stars i {
      color: #FFD700;
    }
    .review-card .review-stars i.empty {
      color: rgba(255,255,255,0.06);
    }
    .review-card .review-stars .rating-number {
      color: #FFD700;
      font-size: 0.8rem;
      font-weight: 600;
      background: rgba(255, 215, 0, 0.08);
      padding: 0.05rem 0.5rem;
      border-radius: 50px;
      border: 1px solid rgba(255, 215, 0, 0.1);
    }
    
    .review-card .review-text {
      color: #e0e0e0;
      font-size: 0.95rem;
      line-height: 1.7;
      margin-bottom: 0.5rem;
      flex: 1;
      display: -webkit-box;
      -webkit-line-clamp: 4;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }
    .review-card .review-text.expanded {
      -webkit-line-clamp: unset;
      overflow: visible;
    }
    
    .review-card .review-read-more {
      color: #D4AF37;
      font-size: 0.75rem;
      cursor: pointer;
      font-weight: 600;
      transition: color 0.3s ease;
      background: none;
      border: none;
      padding: 0;
      margin-bottom: 0.8rem;
      display: inline-flex;
      align-items: center;
      gap: 0.3rem;
      align-self: flex-start;
    }
    .review-card .review-read-more:hover {
      color: #F5C84C;
    }
    
    .review-card .review-author {
      display: flex;
      align-items: center;
      gap: 0.8rem;
      margin-top: 0.8rem;
      padding-top: 0.8rem;
      border-top: 1px solid rgba(255,255,255,0.05);
    }
    .review-card .review-author .avatar {
      width: 44px;
      height: 44px;
      border-radius: 50%;
      background: linear-gradient(135deg, #D4AF37, #F5C84C);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.1rem;
      font-weight: 700;
      color: #050505;
      flex-shrink: 0;
      box-shadow: 0 4px 15px rgba(212, 175, 55, 0.15);
    }
    .review-card .review-author .name {
      color: #ffffff;
      font-weight: 600;
      font-size: 0.95rem;
    }
    .review-card .review-author .email {
      color: #A5A5A5;
      font-size: 0.75rem;
      display: flex;
      align-items: center;
      gap: 0.3rem;
    }
    .review-card .review-author .email i {
      color: #D4AF37;
      font-size: 0.6rem;
    }
    .review-card .review-author .date {
      color: #666;
      font-size: 0.7rem;
      display: flex;
      align-items: center;
      gap: 0.3rem;
    }
    .review-card .review-author .date i {
      color: #D4AF37;
      font-size: 0.6rem;
    }

    /* ===== AVERAGE RATING BANNER ===== */
    .rating-summary {
      background: linear-gradient(145deg, rgba(18, 18, 18, 0.95), rgba(10, 10, 10, 0.95));
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      border: 1px solid rgba(212, 175, 55, 0.15);
      border-radius: 1.5rem;
      padding: 2rem 2.5rem;
      text-align: center;
      max-width: 500px;
      margin: 0 auto;
      transition: all 0.4s ease;
    }
    .rating-summary:hover {
      border-color: rgba(212, 175, 55, 0.3);
      box-shadow: 0 0 40px rgba(212, 175, 55, 0.04);
    }
    .rating-summary .big-rating {
      font-size: 3.5rem;
      font-weight: 900;
      font-family: 'Poppins', sans-serif;
      background: linear-gradient(135deg, #FFD700, #F5C84C);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      line-height: 1;
    }
    .rating-summary .big-stars {
      font-size: 1.8rem;
      letter-spacing: 3px;
      margin: 0.2rem 0;
    }
    .rating-summary .big-stars i {
      color: #FFD700;
    }
    .rating-summary .review-count {
      color: #A5A5A5;
      font-size: 0.85rem;
      margin-top: 0.3rem;
    }
    .rating-summary .review-count i {
      color: #D4AF37;
      margin-right: 0.3rem;
    }

    /* Empty reviews state */
    .empty-reviews {
      text-align: center;
      padding: 4rem 2rem;
      background: rgba(16, 16, 16, 0.3);
      border-radius: 1.5rem;
      border: 1px dashed rgba(212, 175, 55, 0.1);
      transition: all 0.4s ease;
    }
    .empty-reviews:hover {
      border-color: rgba(212, 175, 55, 0.2);
    }
    .empty-reviews .empty-icon {
      font-size: 3.5rem;
      color: #FFD700;
      opacity: 0.15;
      margin-bottom: 1rem;
      display: block;
    }
    .empty-reviews h5 {
      color: #ffffff;
      font-weight: 600;
      margin-bottom: 0.3rem;
      font-family: 'Poppins', sans-serif;
    }
    .empty-reviews p {
      color: #A5A5A5;
      font-size: 0.95rem;
    }

    @media (max-width: 768px) {
      .team-card .team-avatar {
        width: 80px;
        height: 80px;
        font-size: 2.2rem;
      }
      .team-card .team-name {
        font-size: 1.1rem;
      }
      .rating-summary {
        padding: 1.5rem;
      }
      .rating-summary .big-rating {
        font-size: 2.8rem;
      }
      .rating-summary .big-stars {
        font-size: 1.4rem;
      }
      .review-card {
        padding: 1.2rem;
      }
      .review-card .review-text {
        font-size: 0.9rem;
      }
    }
    @media (max-width: 480px) {
      .team-card {
        padding: 1.5rem 1rem;
      }
      .team-card .team-avatar {
        width: 70px;
        height: 70px;
        font-size: 1.8rem;
      }
      .team-card .team-socials a {
        width: 36px;
        height: 36px;
        font-size: 0.9rem;
      }
      .review-card {
        padding: 1rem;
      }
      .review-card .review-stars {
        font-size: 0.85rem;
      }
      .review-card .review-author .avatar {
        width: 36px;
        height: 36px;
        font-size: 0.9rem;
      }
      .rating-summary {
        padding: 1.2rem;
      }
      .rating-summary .big-rating {
        font-size: 2.2rem;
      }
      .rating-summary .big-stars {
        font-size: 1.2rem;
      }
      .empty-reviews {
        padding: 2.5rem 1.2rem;
      }
      .empty-reviews .empty-icon {
        font-size: 2.8rem;
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
    <!-- HERO BANNER -->
    <section class="hero-about d-flex align-items-center" style="min-height:60vh;padding-top:80px;">
      <div class="container">
        <div class="max-w-3xl fade-up visible">
          <span class="section-badge"><i class="fas fa-info-circle me-1"></i> About Us</span>
          <h1 class="section-title">Crafting Digital <span class="gold">Excellence</span></h1>
          <p class="text-secondary fs-5 mt-3">We're a team of designers, developers, and strategists dedicated to building premium digital experiences that drive real business growth.</p>
        </div>
      </div>
    </section>

    <!-- OUR STORY -->
    <section class="py-5">
      <div class="container">
        <div class="row align-items-center g-5">
          <div class="col-lg-6 fade-up">
            <span class="section-badge"><i class="fas fa-book-open me-1"></i> Our Story</span>
            <h2 class="section-title">Built on <span class="gold">Passion</span></h2>
            <p class="text-secondary fs-5 mt-3">Founded in <?= $foundingYear ?>, Luxora Media started with a simple belief: digital experiences should be beautiful, functional, and results-driven. Today, we're a full-service agency trusted by <?= $totalClients ?>+ clients worldwide.</p>
          </div>
          <div class="col-lg-6 fade-up delay-2">
            <div class="glass-card-gold p-4 text-center">
              <i class="fas fa-star fa-4x text-gold"></i>
              <p class="text-secondary mt-3 fs-5">"We don't just build websites — we build digital futures."</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- MISSION & VISION -->
    <section class="py-5 bg-card/30">
      <div class="container">
        <div class="row g-4">
          <div class="col-md-6 fade-up">
            <div class="glass-card h-100"><i class="fas fa-bullseye fs-1 text-gold mb-3"></i><h3 class="fw-bold">Our Mission</h3><p class="text-secondary mt-2">To empower businesses with premium digital solutions that drive measurable growth and leave a lasting impact.</p></div>
          </div>
          <div class="col-md-6 fade-up delay-2">
            <div class="glass-card h-100"><i class="fas fa-eye fs-1 text-gold mb-3"></i><h3 class="fw-bold">Our Vision</h3><p class="text-secondary mt-2">To become the world's most trusted digital agency, known for excellence, innovation, and gold‑standard client experiences.</p></div>
          </div>
        </div>
      </div>
    </section>

    <!-- CORE VALUES - DYNAMIC -->
    <section class="py-5">
      <div class="container">
        <div class="text-center max-w-2xl mx-auto fade-up">
          <span class="section-badge"><i class="fas fa-heart me-1"></i> Values</span>
          <h2 class="section-title">What We <span class="gold">Stand For</span></h2>
        </div>
        <div class="row g-4 mt-3">
          <?php foreach ($coreValues as $value): ?>
          <div class="col-6 col-md-3 fade-up">
            <div class="glass-card text-center h-100">
              <i class="fas <?= htmlspecialchars($value['icon'] ?? 'fa-star') ?> fs-1 text-gold mb-2"></i>
              <h5 class="fw-bold mt-2"><?= htmlspecialchars($value['title']) ?></h5>
              <p class="text-secondary small"><?= htmlspecialchars($value['description']) ?></p>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <!-- TEAM - MEET THE MAKERS -->
    <section class="py-5 bg-card/30">
      <div class="container">
        <div class="text-center fade-up">
          <span class="section-badge"><i class="fas fa-users me-1"></i> Team</span>
          <h2 class="section-title">Meet the <span class="gold">Makers</span></h2>
          <p class="text-secondary mt-2">The passionate minds behind Luxora Media.</p>
        </div>
        <div class="row g-4 mt-3 justify-content-center">
          <?php foreach ($teamMembers as $member): ?>
          <div class="col-12 col-md-6 col-lg-5 fade-up">
            <div class="team-card">
              <div class="team-avatar"><?= strtoupper(substr($member['name'], 0, 1)) ?></div>
              <span class="badge-role"><?= htmlspecialchars($member['role']) ?></span>
              <h4 class="team-name"><?= htmlspecialchars($member['name']) ?></h4>
              <div class="team-email">
                <i class="fas fa-envelope text-gold me-1" style="font-size:0.7rem;"></i>
                <a href="mailto:<?= htmlspecialchars($member['email']) ?>"><?= htmlspecialchars($member['email']) ?></a>
              </div>
              <div class="team-socials">
                <?php if (!empty($member['linkedin'])): ?>
                  <a href="<?= htmlspecialchars($member['linkedin']) ?>" target="_blank" class="linkedin" title="LinkedIn">
                    <i class="fab fa-linkedin-in"></i>
                  </a>
                <?php endif; ?>
                <?php if (!empty($member['instagram'])): ?>
                  <a href="<?= htmlspecialchars($member['instagram']) ?>" target="_blank" class="instagram" title="Instagram">
                    <i class="fab fa-instagram"></i>
                  </a>
                <?php endif; ?>
                <?php if (!empty($member['twitter'])): ?>
                  <a href="<?= htmlspecialchars($member['twitter']) ?>" target="_blank" class="twitter" title="Twitter">
                    <i class="fab fa-x-twitter"></i>
                  </a>
                <?php endif; ?>
                <a href="mailto:<?= htmlspecialchars($member['email']) ?>" title="Email" style="background:rgba(212,175,55,0.05);border-color:rgba(212,175,55,0.1);">
                  <i class="fas fa-envelope" style="color:#D4AF37;"></i>
                </a>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <!-- ===== REVIEWS SECTION - SINGLE CARD DESIGN ===== -->
    <section class="py-5">
      <div class="container">
        <div class="text-center fade-up">
          <span class="section-badge"><i class="fas fa-star me-1"></i> Reviews</span>
          <h2 class="section-title">What Our Clients <span class="gold">Say</span></h2>
          <p class="text-secondary mt-2">Real reviews from real clients who trusted us.</p>
        </div>

        <!-- Rating Summary -->
        <?php if ($totalReviews > 0): ?>
        <div class="row justify-content-center mt-3">
          <div class="col-lg-6">
            <div class="rating-summary fade-up">
              <div class="big-rating"><?= $avgRating ?></div>
              <div class="big-stars"><?= renderStars($avgRating) ?></div>
              <div class="review-count"><i class="fas fa-comment"></i> Based on <?= $totalReviews ?> review<?= $totalReviews > 1 ? 's' : '' ?></div>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <!-- Reviews Grid - SINGLE CARD -->
        <div class="row g-4 mt-3">
          <?php if (!empty($reviews)): ?>
            <?php foreach ($reviews as $review): 
              $rating = intval($review['inquiry_rating'] ?? 0);
              $name = $review['inquiry_name'] ?? 'Anonymous';
              $email = $review['inquiry_email'] ?? '';
              $message = $review['question'] ?? '';
              $date = date('M d, Y', strtotime($review['created_at']));
              $isLong = strlen($message) > 150;
              $shortMessage = $isLong ? substr($message, 0, 150) . '...' : $message;
            ?>
            <div class="col-12 col-md-6 col-lg-4 fade-up">
              <div class="review-card">
                <!-- Stars Row -->
                <div class="review-stars">
                  <?= renderStars($rating) ?>
                  <span class="rating-number"><?= $rating ?>.0</span>
                </div>
                
                <!-- Review Text -->
                <div class="review-text <?= $isLong ? '' : 'expanded' ?>">
                  <?= nl2br(htmlspecialchars($shortMessage)) ?>
                </div>
                <?php if ($isLong): ?>
                  <button class="review-read-more" onclick="this.previousElementSibling.classList.toggle('expanded'); this.innerHTML = this.previousElementSibling.classList.contains('expanded') ? 'Read Less <i class=\"fas fa-chevron-up\"></i>' : 'Read More <i class=\"fas fa-chevron-down\"></i>';">Read More <i class="fas fa-chevron-down"></i></button>
                <?php endif; ?>
                
                <!-- Author -->
                <div class="review-author">
                  <div class="avatar"><?= strtoupper(substr($name, 0, 1)) ?></div>
                  <div>
                    <div class="name"><?= htmlspecialchars($name) ?></div>
                    <div class="email"><i class="fas fa-envelope"></i> <?= htmlspecialchars($email) ?></div>
                    <div class="date"><i class="far fa-calendar-alt"></i> <?= $date ?></div>
                  </div>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="col-12">
              <div class="empty-reviews fade-up">
                <i class="fas fa-star empty-icon"></i>
                <h5>No Reviews Yet</h5>
                <p>Be the first to leave a review! We'd love to hear about your experience.</p>
                <a href="contact.php" class="btn-primary-custom mt-2" style="display:inline-flex;align-items:center;gap:0.5rem;">
                  <i class="fas fa-pen"></i> Leave a Review
                </a>
              </div>
            </div>
          <?php endif; ?>
        </div>

        <?php if (!empty($reviews)): ?>
        <div class="text-center mt-4 fade-up">
          <a href="contact.php" class="btn-gold-outline" style="display:inline-flex;align-items:center;gap:0.5rem;">
            <i class="fas fa-pen"></i> Leave a Review
          </a>
        </div>
        <?php endif; ?>
      </div>
    </section>

    <!-- TIMELINE - DYNAMIC -->
    <section class="py-5 bg-card/30">
      <div class="container">
        <div class="text-center fade-up">
          <span class="section-badge"><i class="fas fa-timeline me-1"></i> Timeline</span>
          <h2 class="section-title">Our <span class="gold">Journey</span></h2>
        </div>
        <div class="row justify-content-center mt-4">
          <div class="col-lg-8">
            <?php foreach ($timelineItems as $index => $item): ?>
            <div class="d-flex align-items-start gap-3 fade-up">
              <div class="timeline-dot"></div>
              <?php if ($index < count($timelineItems) - 1): ?>
              <div class="timeline-line" style="height:60px;"></div>
              <?php endif; ?>
              <div>
                <h5 class="fw-bold"><?= htmlspecialchars($item['year']) ?></h5>
                <p class="text-secondary"><?= htmlspecialchars($item['description']) ?></p>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </section>

    <!-- STATS - CONSISTENT WITH INDEX.PHP -->
    <section class="py-4 border-y border-white/5 bg-card/20">
      <div class="container">
        <div class="row text-center g-4">
          <div class="col-6 col-md-3">
            <div class="stat-number"><?= max($totalClients, 0) ?>+</div>
            <p class="text-secondary small"><i class="fas fa-users text-gold me-1"></i> Clients</p>
          </div>
          <div class="col-6 col-md-3">
            <div class="stat-number"><?= max($totalProjects, 0) ?>+</div>
            <p class="text-secondary small"><i class="fas fa-folder-open text-gold me-1"></i> Projects</p>
          </div>
          <div class="col-6 col-md-3">
            <div class="stat-number"><?= $satisfactionRate ?>%</div>
            <p class="text-secondary small"><i class="fas fa-smile text-gold me-1"></i> Satisfaction</p>
          </div>
          <div class="col-6 col-md-3">
            <div class="stat-number"><?= $yearsExperience ?>+</div>
            <p class="text-secondary small"><i class="fas fa-calendar-alt text-gold me-1"></i> Years</p>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section class="py-5">
      <div class="container">
        <div class="glass-gold rounded-4 p-5 text-center max-w-4xl mx-auto fade-up">
          <h2 class="section-title" style="font-size:clamp(2rem,4vw,3.5rem);">Let's Create <span class="gold">Together</span></h2>
          <p class="text-secondary fs-5 mt-3">Ready to take your digital presence to the next level?</p>
          <a href="contact.php" class="btn-primary-custom mt-3"><i class="fas fa-arrow-right me-2"></i> Get Started</a>
        </div>
      </div>
    </section>
  </main>

  <!-- FOOTER - DYNAMIC SOCIAL LINKS -->
  <footer class="border-top border-white/5 bg-card/40 py-4">
    <div class="container">
      <div class="row g-4">
        <div class="col-md-3">
          <a href="index.php" class="logo fs-2">LUXORA<span>.</span></a>
          <p class="text-secondary small mt-2">Premium digital agency.</p>
          <div class="d-flex gap-2 mt-2">
            <?php if (!empty($socialLinks)): ?>
              <?php foreach ($socialLinks as $platform => $url): ?>
                <a href="<?= htmlspecialchars($url) ?>" target="_blank" class="text-secondary text-decoration-none hover-gold">
                  <i class="fab fa-<?= strtolower($platform) ?>"></i>
                </a>
              <?php endforeach; ?>
            <?php else: ?>
              <a href="#" class="text-secondary text-decoration-none hover-gold"><i class="fab fa-x-twitter"></i></a>
              <a href="#" class="text-secondary text-decoration-none hover-gold"><i class="fab fa-linkedin-in"></i></a>
              <a href="#" class="text-secondary text-decoration-none hover-gold"><i class="fab fa-youtube"></i></a>
            <?php endif; ?>
          </div>
        </div>
        <div class="col-md-3">
          <h6 class="fw-bold text-uppercase small text-secondary"><i class="fas fa-link me-1"></i> Quick Links</h6>
          <ul class="list-unstyled mt-2 small">
            <li><a href="index.php" class="footer-link">Home</a></li>
            <li><a href="about.php" class="footer-link">About</a></li>
            <li><a href="services.php" class="footer-link">Services</a></li>
            <li><a href="pricing.php" class="footer-link">Pricing</a></li>
            <li><a href="faq.php" class="footer-link">FAQ</a></li>
            <li><a href="contact.php" class="footer-link">Contact</a></li>
          </ul>
        </div>
        <div class="col-md-3">
          <h6 class="fw-bold text-uppercase small text-secondary"><i class="fas fa-cog me-1"></i> Services</h6>
          <ul class="list-unstyled mt-2 small">
            <li><a href="web-development.php" class="footer-link">Web Dev</a></li>
            <li><a href="ai-chatbots.php" class="footer-link">AI Chatbots</a></li>
            <li><a href="graphic-design.php" class="footer-link">Graphic Design</a></li>
          </ul>
        </div>
        <div class="col-md-3">
          <h6 class="fw-bold text-uppercase small text-secondary"><i class="fas fa-envelope me-1"></i> Newsletter</h6>
          <form class="d-flex gap-2" onsubmit="event.preventDefault();showToast('✓ Subscribed!')">
            <input type="email" placeholder="Your email" class="form-input flex-1" style="flex:1;" required />
            <button type="submit" class="btn-primary-custom px-3 py-2"><i class="fas fa-arrow-right"></i></button>
          </form>
        </div>
      </div>
      <div class="border-top border-white/5 mt-4 pt-3 d-flex flex-wrap justify-content-between small text-secondary">
        <span><i class="far fa-copyright me-1"></i> <?= date('Y') ?> Luxora Media. All Rights Reserved.</span>
        <span>Made with <i class="fas fa-heart text-gold"></i> in Berlin</span>
      </div>
    </div>
  </footer>

  <div id="toast" class="toast-notification"><i class="fas fa-check-circle text-gold me-2"></i> Message sent! We'll <span class="gold">get back to you</span> soon.</div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="luxora.js"></script>
</body>
</html>