<?php
session_start();
require_once __DIR__ . '/db.php';

// Fetch dynamic stats - CONSISTENT with about.php
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
    
    // Total Revenue from paid projects
    $stmt = $db->query("SELECT SUM(amount) as total FROM projects WHERE status = 'Paid'");
    $totalRevenue = $stmt->fetch()['total'] ?? 0;
    
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

    // Fetch active services
    $stmt = $db->query("SELECT * FROM services WHERE status = 'active' ORDER BY id ASC");
    $publicServices = $stmt->fetchAll();

    // Revenue percentage for dashboard
    $revenuePercent = $totalRevenue > 0 ? min(100, round(($totalRevenue / 5000) * 100)) : 34;
    $conversionPercent = $totalClients > 0 ? min(100, round(($totalClients / 100) * 100)) : 28;
    $trafficPercent = $activeProjects > 0 ? min(100, round(($activeProjects / 10) * 100)) : 47;

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

    // ===== FETCH FAQs FROM DATABASE (same as faq.php) =====
    $faqs = [];
    try {
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
            LIMIT 5
        ");
        $faqs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $faqs = [];
    }

    $hasFaqs = count($faqs) > 0;

} catch (PDOException $e) {
    $totalProjects = 0;
    $activeProjects = 0;
    $totalClients = 0;
    $totalInquiries = 0;
    $totalRevenue = 0;
    $satisfactionRate = 98;
    $yearsExperience = 4;
    $revenuePercent = 34;
    $conversionPercent = 28;
    $trafficPercent = 47;
    $publicServices = [];
    $reviews = [];
    $avgRating = 0;
    $totalReviews = 0;
    $faqs = [];
    $hasFaqs = false;
}

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
  <title>Luxora Media – Premium Digital Agency</title>
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
  <!-- Font Awesome 6 (Free) -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <!-- Bootstrap 5 CDN -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <!-- Custom CSS -->
  <link rel="stylesheet" href="luxora.css" />
  <style>
    .hero-gradient {
      background: radial-gradient(ellipse at 30% 40%, rgba(212,175,55,0.06), transparent 60%),
                  radial-gradient(ellipse at 70% 60%, rgba(212,175,55,0.04), transparent 50%);
    }
    .hero-section {
      min-height: 100vh;
      display: flex;
      align-items: center;
      padding-top: 80px;
    }
    @media (max-width: 768px) {
      .hero-section { padding-top: 60px; min-height: auto; padding-bottom: 60px; }
    }

    /* ===== POLISHED, RESPONSIVE LUXORA DASHBOARD UI ===== */
    .dashboard-card {
      background: linear-gradient(145deg, rgba(18, 18, 18, 0.95), rgba(10, 10, 10, 0.95));
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      border: 1px solid rgba(212, 175, 55, 0.2);
      border-radius: 20px;
      padding: 1.5rem;
      position: relative;
      overflow: hidden;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5), 0 0 40px rgba(212, 175, 55, 0.05);
      max-width: 550px;
      margin: 0 auto;
    }
    .dashboard-card::before {
      content: '';
      position: absolute;
      top: -50%;
      left: -50%;
      width: 200%;
      height: 200%;
      background: radial-gradient(circle at 20% 30%, rgba(212, 175, 55, 0.06), transparent 60%);
      pointer-events: none;
    }
    .dashboard-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 1rem;
    }
    .dashboard-title {
      font-size: 1rem;
      font-weight: 700;
      color: #fff;
      display: flex;
      align-items: center;
      gap: 0.6rem;
    }
    .dashboard-title i {
      color: #D4AF37;
      font-size: 1.1rem;
    }
    .live-indicator {
      display: flex;
      align-items: center;
      gap: 0.4rem;
      background: rgba(46, 213, 115, 0.1);
      border: 1px solid rgba(46, 213, 115, 0.2);
      color: #2ed573;
      font-size: 0.6rem;
      font-weight: 600;
      padding: 0.3rem 0.8rem;
      border-radius: 50px;
      letter-spacing: 0.05em;
      text-transform: uppercase;
    }
    .live-indicator .pulse-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: #2ed573;
      animation: pulse 2s infinite;
    }
    @keyframes pulse {
      0% { box-shadow: 0 0 0 0 rgba(46, 213, 115, 0.6); }
      70% { box-shadow: 0 0 0 10px rgba(46, 213, 115, 0); }
      100% { box-shadow: 0 0 0 0 rgba(46, 213, 115, 0); }
    }
    .dashboard-metrics-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 0.8rem;
      margin-bottom: 1rem;
    }
    .dashboard-metric {
      background: rgba(255, 255, 255, 0.02);
      border: 1px solid rgba(255, 255, 255, 0.06);
      border-radius: 14px;
      padding: 0.8rem;
      transition: all 0.3s ease;
    }
    .dashboard-metric:hover {
      border-color: rgba(212, 175, 55, 0.3);
      background: rgba(212, 175, 55, 0.03);
    }
    .dashboard-metric .metric-icon {
      width: 32px;
      height: 32px;
      border-radius: 10px;
      background: linear-gradient(135deg, #D4AF37, #F5C84C);
      display: flex;
      align-items: center;
      justify-content: center;
      color: #050505;
      font-size: 0.9rem;
      margin-bottom: 0.6rem;
    }
    .dashboard-metric .metric-value {
      font-size: 1.3rem;
      font-weight: 800;
      font-family: 'Poppins', sans-serif;
      color: #fff;
      line-height: 1.2;
    }
    .dashboard-metric .metric-label {
      font-size: 0.7rem;
      color: #A5A5A5;
      font-weight: 500;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      margin-top: 0.2rem;
    }
    .dashboard-sparkline {
      background: rgba(255, 255, 255, 0.02);
      border: 1px solid rgba(255, 255, 255, 0.06);
      border-radius: 14px;
      padding: 0.8rem;
      margin-bottom: 1rem;
    }
    .dashboard-sparkline .sparkline-title {
      font-size: 0.8rem;
      color: #A5A5A5;
      font-weight: 600;
      margin-bottom: 0.5rem;
      display: flex;
      justify-content: space-between;
    }
    .dashboard-sparkline .sparkline-title span {
      color: #D4AF37;
      font-weight: 700;
    }
    .sparkline-bars {
      display: flex;
      align-items: flex-end;
      gap: 4px;
      height: 50px;
    }
    .sparkline-bars .bar {
      flex: 1;
      background: linear-gradient(180deg, #F5C84C, rgba(212, 175, 55, 0.1));
      border-radius: 4px 4px 0 0;
      transition: height 0.5s ease;
    }
    .dashboard-footer {
      border-top: 1px solid rgba(255, 255, 255, 0.06);
      padding-top: 0.8rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .dashboard-footer .stat-item {
      text-align: center;
    }
    .dashboard-footer .stat-item .stat-value {
      font-size: 1rem;
      font-weight: 700;
      color: #fff;
    }
    .dashboard-footer .stat-item .stat-label {
      font-size: 0.6rem;
      color: #A5A5A5;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }
    .dashboard-footer .view-more-btn {
      background: rgba(212, 175, 55, 0.08);
      border: 1px solid rgba(212, 175, 55, 0.2);
      border-radius: 50px;
      padding: 0.4rem 1rem;
      font-size: 0.7rem;
      color: #D4AF37;
      font-weight: 600;
      text-decoration: none;
      transition: all 0.3s ease;
    }
    .dashboard-footer .view-more-btn:hover {
      background: rgba(212, 175, 55, 0.15);
      border-color: #D4AF37;
    }

    @media (max-width: 576px) {
      .dashboard-card { padding: 1rem; }
      .dashboard-metrics-grid { gap: 0.5rem; }
      .dashboard-metric { padding: 0.6rem; }
      .dashboard-metric .metric-icon { width: 28px; height: 28px; font-size: 0.8rem; }
      .dashboard-metric .metric-value { font-size: 1.1rem; }
      .dashboard-footer { flex-direction: column; gap: 0.8rem; }
      .dashboard-footer .view-more-btn { width: 100%; text-align: center; }
    }

    /* ===== CRAFTING DIGITAL EXCELLENCE CARD ===== */
    .crafting-card {
      background: rgba(16, 16, 16, 0.7);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      border: 1px solid rgba(212, 175, 55, 0.12);
      border-radius: 1.5rem;
      padding: 1.8rem;
      transition: all 0.5s cubic-bezier(0.22, 1, 0.36, 1);
      position: relative;
      overflow: hidden;
      height: 100%;
    }
    .crafting-card::before {
      content: '';
      position: absolute;
      top: -50%;
      left: -50%;
      width: 200%;
      height: 200%;
      background: radial-gradient(circle at 70% 60%, rgba(212, 175, 55, 0.04), transparent 60%);
      pointer-events: none;
    }
    .crafting-card:hover {
      border-color: rgba(212, 175, 55, 0.35);
      transform: translateY(-8px) scale(1.01);
      box-shadow: 0 30px 80px rgba(212, 175, 55, 0.08), 0 0 60px rgba(212, 175, 55, 0.04);
    }
    .crafting-card .card-box {
      background: rgba(0, 0, 0, 0.4);
      border: 1px solid rgba(255,255,255,0.06);
      border-radius: 12px;
      padding: 1.2rem;
      text-align: center;
      transition: all 0.3s ease;
    }
    .crafting-card .card-box:hover {
      border-color: rgba(212, 175, 55, 0.2);
      transform: translateY(-4px);
    }
    .crafting-card .card-box i {
      color: #D4AF37;
    }

    /* ===== REVIEW CARDS ===== */
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

    /* ============================================= */
    /* FAQ PREVIEW - DYNAMIC STYLES                  */
    /* ============================================= */
    .dynamic-faq-item {
      margin-bottom: 1rem;
      background: linear-gradient(145deg, rgba(20, 20, 20, 0.95), rgba(12, 12, 12, 0.95));
      border-radius: 1rem;
      border: 1px solid rgba(212, 175, 55, 0.12);
      overflow: hidden;
      transition: all 0.4s cubic-bezier(0.22, 1, 0.36, 1);
    }
    .dynamic-faq-item:hover {
      border-color: rgba(212, 175, 55, 0.3);
      box-shadow: 0 6px 25px rgba(212, 175, 55, 0.05);
    }
    .dynamic-faq-item.open {
      border-color: rgba(212, 175, 55, 0.4);
      box-shadow: 0 10px 35px rgba(212, 175, 55, 0.08);
    }
    .dynamic-faq-q {
      padding: 1.1rem 1.3rem;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      color: #ffffff;
      font-weight: 600;
      font-size: 1rem;
      user-select: none;
      position: relative;
    }
    .dynamic-faq-q::before {
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
    .dynamic-faq-item.open .dynamic-faq-q::before {
      transform: scaleY(1);
    }
    .dynamic-faq-q span:first-child {
      flex: 1;
      line-height: 1.5;
    }
    .dynamic-faq-icon {
      width: 30px;
      height: 30px;
      border-radius: 50%;
      background: rgba(212, 175, 55, 0.1);
      border: 1px solid rgba(212, 175, 55, 0.2);
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    .dynamic-faq-icon i {
      color: #D4AF37;
      font-size: 0.75rem;
      transition: transform 0.4s ease;
    }
    .dynamic-faq-item.open .dynamic-faq-icon {
      background: linear-gradient(135deg, #D4AF37, #F5C84C);
    }
    .dynamic-faq-item.open .dynamic-faq-icon i {
      color: #050505;
      transform: rotate(180deg);
    }
    .dynamic-faq-a {
      padding: 0 1.3rem;
      max-height: 0;
      overflow: hidden;
      transition: max-height 0.5s cubic-bezier(0.22, 1, 0.36, 1), padding 0.5s ease, opacity 0.3s ease;
      color: #A5A5A5;
      line-height: 1.8;
      background: rgba(10, 10, 10, 0.4);
      opacity: 0;
    }
    .dynamic-faq-item.open .dynamic-faq-a {
      padding: 1.3rem;
      max-height: 4000px;
      opacity: 1;
    }
    .dynamic-faq-meta {
      display: flex;
      align-items: center;
      gap: 0.7rem;
      flex-wrap: wrap;
      margin-bottom: 1rem;
      padding-bottom: 0.9rem;
      border-bottom: 1px solid rgba(255,255,255,0.05);
    }
    .dynamic-faq-meta .user-info {
      display: flex;
      align-items: center;
      gap: 0.6rem;
    }
    .dynamic-faq-meta .user-avatar {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      background: linear-gradient(135deg, #D4AF37, #F5C84C);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.8rem;
      font-weight: 700;
      color: #050505;
      flex-shrink: 0;
    }
    .dynamic-faq-meta .user-name {
      color: #ffffff;
      font-weight: 600;
      font-size: 0.85rem;
      display: block;
    }
    .dynamic-faq-meta .user-email {
      color: #888;
      font-size: 0.7rem;
    }
    .dynamic-faq-meta .faq-date {
      color: #888;
      font-size: 0.7rem;
      display: flex;
      align-items: center;
      gap: 0.3rem;
    }
    .dynamic-faq-meta .faq-date i {
      color: #D4AF37;
      font-size: 0.55rem;
    }
    .dynamic-faq-meta .faq-badge {
      background: rgba(46, 213, 115, 0.1);
      color: #2ed573;
      font-size: 0.6rem;
      padding: 0.2rem 0.7rem;
      border-radius: 50px;
      border: 1px solid rgba(46, 213, 115, 0.15);
      display: inline-flex;
      align-items: center;
      gap: 0.25rem;
      margin-left: auto;
    }
    .dynamic-faq-meta .faq-badge.pending {
      background: rgba(255, 165, 0, 0.1);
      color: #ffa502;
      border-color: rgba(255, 165, 0, 0.15);
    }
    .dynamic-faq-question {
      color: #ffffff;
      font-size: 0.95rem;
      font-weight: 500;
      margin-bottom: 0.9rem;
      display: flex;
      align-items: flex-start;
      gap: 0.5rem;
      background: rgba(212, 175, 55, 0.03);
      padding: 0.7rem 0.9rem;
      border-radius: 8px;
      border-left: 3px solid #D4AF37;
    }
    .dynamic-faq-question i {
      color: #D4AF37;
      margin-top: 0.15rem;
      font-size: 0.85rem;
    }
    .dynamic-faq-answer .admin-reply {
      display: flex;
      align-items: flex-start;
      gap: 0.7rem;
      margin-top: 0.7rem;
      padding: 0.9rem 1rem;
      background: rgba(212, 175, 55, 0.04);
      border-radius: 10px;
      border: 1px solid rgba(212, 175, 55, 0.08);
    }
    .dynamic-faq-answer .admin-reply .admin-icon {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      background: linear-gradient(135deg, #D4AF37, #F5C84C);
      display: flex;
      align-items: center;
      justify-content: center;
      color: #050505;
      font-size: 0.8rem;
      flex-shrink: 0;
    }
    .dynamic-faq-answer .admin-reply .admin-name {
      color: #D4AF37;
      font-weight: 600;
      font-size: 0.8rem;
      display: block;
      margin-bottom: 0.15rem;
    }
    .dynamic-faq-answer .admin-reply .reply-text {
      color: #A5A5A5;
      line-height: 1.6;
      font-size: 0.9rem;
    }
    .dynamic-faq-answer .admin-reply .reply-time {
      color: #666;
      font-size: 0.65rem;
      margin-top: 0.3rem;
      display: block;
    }
    .dynamic-faq-answer .pending-reply {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      font-style: italic;
      padding: 0.7rem 0.9rem;
      background: rgba(255, 165, 0, 0.05);
      border-radius: 8px;
      border: 1px dashed rgba(255, 165, 0, 0.2);
      color: #ffa502;
      font-size: 0.85rem;
    }
    .empty-faq-preview {
      text-align: center;
      padding: 3rem 2rem;
      background: rgba(16, 16, 16, 0.3);
      border-radius: 1.5rem;
      border: 1px dashed rgba(212, 175, 55, 0.1);
    }
    .empty-faq-preview .empty-icon {
      font-size: 3rem;
      color: #D4AF37;
      opacity: 0.2;
      margin-bottom: 0.8rem;
      display: block;
    }
    .empty-faq-preview h5 {
      color: #ffffff;
      font-weight: 600;
      margin-bottom: 0.3rem;
    }
    .empty-faq-preview p {
      color: #A5A5A5;
      font-size: 0.9rem;
    }

    @media (max-width: 768px) {
      .review-card {
        padding: 1.2rem;
      }
      .review-card .review-text {
        font-size: 0.9rem;
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
      .dynamic-faq-q {
        padding: 0.9rem 1.1rem;
        font-size: 0.9rem;
      }
      .dynamic-faq-item.open .dynamic-faq-a {
        padding: 1rem;
      }
      .dynamic-faq-meta .faq-badge {
        font-size: 0.55rem;
        margin-left: 0;
        width: 100%;
        justify-content: center;
        margin-top: 0.4rem;
      }
    }
    @media (max-width: 480px) {
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

    /* NAVBAR AUTH BUTTONS */
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
      color: #0a0a0a !important;
      border: 1.5px solid #d4af37 !important;
    }
    .btn-gold-solid:hover {
      background: #c19b2e !important;
      border-color: #c19b2e !important;
      color: #0a0a0a !important;
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
      color: #0a0a0a !important;
      border: 1.5px solid #d4af37 !important;
    }
    .mobile-auth .btn-gold-solid:hover {
      background: #c19b2e !important;
      border-color: #c19b2e !important;
      color: #0a0a0a !important;
    }

    @media (min-width: 993px) {
      .mobile-auth { display: none !important; }
    }
    @media (max-width: 992px) {
      .auth-buttons { display: none !important; }
      .mobile-auth { display: flex !important; }
    }

    .navbar .nav-links {
      display: flex;
      align-items: center;
      gap: 1.5rem;
      margin: 0;
      padding: 0;
      list-style: none;
    }
    .navbar .nav-links li {
      list-style: none;
      display: flex;
      align-items: center;
    }

    .mobile-menu {
      position: fixed;
      top: 0;
      right: -100%;
      width: 80%;
      max-width: 350px;
      height: 100vh;
      background: #0a0a0a;
      padding: 2rem 1.5rem;
      transition: right 0.3s ease;
      z-index: 9999;
      overflow-y: auto;
      border-left: 1px solid rgba(212, 175, 55, 0.1);
    }
    .mobile-menu.active {
      right: 0;
    }
    .mobile-menu a {
      display: block;
      padding: 0.75rem 0;
      color: #e0e0e0;
      text-decoration: none;
      font-weight: 500;
      border-bottom: 1px solid rgba(255, 255, 255, 0.05);
      transition: color 0.3s ease;
    }
    .mobile-menu a:hover,
    .mobile-menu a.active {
      color: #d4af37;
    }
    .mobile-close {
      position: absolute;
      top: 1rem;
      right: 1rem;
      background: none;
      border: none;
      color: #fff;
      font-size: 1.5rem;
      cursor: pointer;
    }
    .nav-cta-mobile {
      background: #d4af37;
      color: #0a0a0a !important;
      padding: 0.75rem 1.5rem;
      border-radius: 50px;
      font-weight: 600;
      text-align: center;
      margin-top: 1rem;
      border: none;
    }
    .nav-cta-mobile:hover {
      background: #c19b2e;
      color: #0a0a0a !important;
    }
    .mobile-toggle {
      display: none;
      flex-direction: column;
      gap: 5px;
      background: none;
      border: none;
      cursor: pointer;
      padding: 5px;
    }
    .mobile-toggle span {
      display: block;
      width: 25px;
      height: 2px;
      background: #fff;
      transition: all 0.3s ease;
    }
    @media (max-width: 992px) {
      .mobile-toggle { display: flex; }
      .navbar .nav-links { display: none; }
    }

    /* ===== PROFILE DROPDOWN - COMPLETELY FIXED ===== */
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
      background: #0a0a0a;
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
  </style>
</head>
<body>
  <?php include '3d-scene.php'; ?>
  <div id="loader"><div class="loader-ring"></div><div class="loader-text">LUXORA</div><div class="loader-bar"><div class="loader-bar-fill"></div></div></div>
  <button id="backTop" class="back-top"><i class="fas fa-arrow-up"></i></button>
  <?php include 'user-nav.php'; ?>

  <main>
    <!-- HERO SECTION -->
    <section class="hero-section hero-gradient position-relative overflow-hidden" id="home">
      <div class="container position-relative z-1">
        <div class="row align-items-center g-5">
          <div class="col-lg-6">
            <div class="fade-up visible"><span class="section-badge"><i class="fas fa-star me-1"></i> Digital Agency</span></div>
            <h1 class="section-title fade-up visible delay-1">We Build Digital<br /><span class="gold">Experiences</span> That<br />Grow Businesses.</h1>
            <p class="text-secondary fs-5 mt-4 fade-up visible delay-2">Luxora Media crafts premium websites, AI chatbots, branding, and marketing solutions.</p>
            <div class="d-flex flex-wrap gap-3 mt-4 hero-buttons fade-up visible delay-3">
              <a href="contact.php" class="btn btn-primary-custom"><i class="fas fa-arrow-right me-2"></i> Get Started</a>
              <a href="contact.php" class="btn btn-secondary-custom"><i class="fas fa-calendar-check me-2"></i> Book Free Consultation</a>
            </div>
            <div class="d-flex align-items-center gap-4 mt-4 text-secondary fade-up visible delay-4">
              <span class="d-flex align-items-center gap-2"><i class="fas fa-star text-gold"></i> 4.9/5</span>
              <span class="vr bg-white/10" style="height:16px;"></span>
              <span><i class="fas fa-users text-gold me-1"></i> <?= $totalClients ?>+ Clients</span>
              <span class="vr bg-white/10" style="height:16px;"></span>
              <span><i class="fas fa-calendar-alt text-gold me-1"></i> <?= $yearsExperience ?>+ Years</span>
            </div>
          </div>
          <div class="col-lg-6">
            <div class="fade-up visible delay-2">
              <div class="dashboard-card float float-delay-1">
                <div class="dashboard-header">
                  <div class="dashboard-title"><i class="fas fa-chart-simple"></i> Luxora Media — Dashboard</div>
                  <div class="live-indicator"><span class="pulse-dot"></span> LIVE</div>
                </div>
                <div class="dashboard-metrics-grid">
                  <div class="dashboard-metric"><div class="metric-icon"><i class="fas fa-dollar-sign"></i></div><div class="metric-value">$<?= number_format($totalRevenue) ?></div><div class="metric-label">Revenue</div></div>
                  <div class="dashboard-metric"><div class="metric-icon"><i class="fas fa-folder-open"></i></div><div class="metric-value"><?= $activeProjects ?></div><div class="metric-label">Active Projects</div></div>
                </div>
                <div class="dashboard-sparkline">
                  <div class="sparkline-title">Weekly Activity <span><i class="fas fa-chart-line"></i> +12%</span></div>
                  <div class="sparkline-bars">
                    <div class="bar" style="height: 40%;"></div><div class="bar" style="height: 65%;"></div>
                    <div class="bar" style="height: 50%;"></div><div class="bar" style="height: 80%;"></div>
                    <div class="bar" style="height: 55%;"></div><div class="bar" style="height: 90%;"></div>
                    <div class="bar" style="height: 75%;"></div>
                  </div>
                </div>
                <div class="dashboard-footer">
                  <div class="d-flex gap-3">
                    <div class="stat-item"><div class="stat-value"><?= $totalClients ?></div><div class="stat-label">Clients</div></div>
                    <div class="stat-item"><div class="stat-value"><?= $totalInquiries ?></div><div class="stat-label">Inquiries</div></div>
                  </div>
                  <a href="admin/dashboard.php" class="view-more-btn">View Full <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- TRUSTED CLIENTS MARQUEE -->
    <section class="py-4 border-y border-white/5">
      <div class="container">
        <p class="text-center text-uppercase text-secondary mb-3" style="font-size:0.7rem;letter-spacing:0.2em;"><i class="fas fa-building me-2"></i> Trusted by <?= $totalClients ?>+ companies worldwide</p>
        <div class="overflow-hidden position-relative">
          <div class="marquee-track d-flex align-items-center">
            <span class="text-white/20 fw-bold fs-5 text-nowrap"><i class="fab fa-google me-2"></i> Google</span>
            <span class="text-white/20 fw-bold fs-5 text-nowrap"><i class="fab fa-microsoft me-2"></i> Microsoft</span>
            <span class="text-white/20 fw-bold fs-5 text-nowrap"><i class="fab fa-amazon me-2"></i> Amazon</span>
            <span class="text-white/20 fw-bold fs-5 text-nowrap"><i class="fas fa-store me-2"></i> Shopify</span>
            <span class="text-white/20 fw-bold fs-5 text-nowrap"><i class="fas fa-bolt me-2"></i> HubSpot</span>
            <span class="text-white/20 fw-bold fs-5 text-nowrap"><i class="fas fa-cloud me-2"></i> Salesforce</span>
            <span class="text-white/20 fw-bold fs-5 text-nowrap"><i class="fas fa-pen-fancy me-2"></i> Adobe</span>
            <span class="text-white/20 fw-bold fs-5 text-nowrap"><i class="fas fa-pencil me-2"></i> Figma</span>
            <span class="text-white/20 fw-bold fs-5 text-nowrap"><i class="fas fa-book me-2"></i> Notion</span>
            <span class="text-white/20 fw-bold fs-5 text-nowrap"><i class="fas fa-credit-card me-2"></i> Stripe</span>
            <span class="text-white/20 fw-bold fs-5 text-nowrap"><i class="fab fa-google me-2"></i> Google</span>
            <span class="text-white/20 fw-bold fs-5 text-nowrap"><i class="fab fa-microsoft me-2"></i> Microsoft</span>
            <span class="text-white/20 fw-bold fs-5 text-nowrap"><i class="fab fa-amazon me-2"></i> Amazon</span>
            <span class="text-white/20 fw-bold fs-5 text-nowrap"><i class="fas fa-store me-2"></i> Shopify</span>
            <span class="text-white/20 fw-bold fs-5 text-nowrap"><i class="fas fa-bolt me-2"></i> HubSpot</span>
            <span class="text-white/20 fw-bold fs-5 text-nowrap"><i class="fas fa-cloud me-2"></i> Salesforce</span>
            <span class="text-white/20 fw-bold fs-5 text-nowrap"><i class="fas fa-pen-fancy me-2"></i> Adobe</span>
            <span class="text-white/20 fw-bold fs-5 text-nowrap"><i class="fas fa-pencil me-2"></i> Figma</span>
            <span class="text-white/20 fw-bold fs-5 text-nowrap"><i class="fas fa-book me-2"></i> Notion</span>
            <span class="text-white/20 fw-bold fs-5 text-nowrap"><i class="fas fa-credit-card me-2"></i> Stripe</span>
          </div>
        </div>
      </div>
    </section>

    <!-- ABOUT PREVIEW (DYNAMIC STATS - CONSISTENT) -->
    <section class="py-5" id="about">
      <div class="container">
        <div class="row align-items-center g-5">
          <div class="col-lg-6 fade-up">
            <span class="section-badge"><i class="fas fa-info-circle me-1"></i> About Luxora</span>
            <h2 class="section-title">Crafting Digital <span class="gold">Excellence</span></h2>
            <p class="text-secondary fs-5 mt-3">We're a team of designers, developers, and strategists who believe in the power of beautiful, functional digital experiences.</p>
            <div class="row g-3 mt-3">
              <div class="col-6"><div class="stat-number"><?= $activeProjects ?>+</div><p class="text-secondary small"><i class="fas fa-check-circle text-gold me-1"></i> Projects Delivered</p></div>
              <div class="col-6"><div class="stat-number"><?= $satisfactionRate ?>%</div><p class="text-secondary small"><i class="fas fa-smile text-gold me-1"></i> Client Satisfaction</p></div>
              <div class="col-6"><div class="stat-number"><?= $yearsExperience ?>+</div><p class="text-secondary small"><i class="fas fa-calendar-alt text-gold me-1"></i> Years of Excellence</p></div>
              <div class="col-6"><div class="stat-number">100%</div><p class="text-secondary small"><i class="fas fa-headset text-gold me-1"></i> Dedicated Support</p></div>
            </div>
            <a href="about.php" class="btn btn-primary-custom mt-3"><i class="fas fa-arrow-right me-2"></i> Learn More</a>
          </div>
          <div class="col-lg-6 fade-up delay-2">
            <div class="crafting-card">
              <div class="row g-3">
                <div class="col-6"><div class="card-box"><i class="fas fa-code fs-1"></i><p class="fw-semibold small mb-0 mt-2">Web Dev</p></div></div>
                <div class="col-6"><div class="card-box"><i class="fas fa-robot fs-1"></i><p class="fw-semibold small mb-0 mt-2">AI Chatbots</p></div></div>
                <div class="col-6"><div class="card-box"><i class="fas fa-palette fs-1"></i><p class="fw-semibold small mb-0 mt-2">Graphic Design</p></div></div>
                <div class="col-6"><div class="card-box"><i class="fas fa-chart-line fs-1"></i><p class="fw-semibold small mb-0 mt-2">Digital Marketing</p></div></div>
              </div>
              <div class="text-center text-secondary small mt-3"><i class="fas fa-star text-gold me-1"></i> Full-service digital agency</div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- SERVICES PREVIEW (DYNAMIC) -->
    <section class="py-5 bg-card/30" id="services">
      <div class="container">
        <div class="text-center max-w-2xl mx-auto fade-up">
          <span class="section-badge"><i class="fas fa-cogs me-1"></i> What We Do</span>
          <h2 class="section-title">Premium <span class="gold">Services</span></h2>
          <p class="text-secondary mt-3">End-to-end digital solutions designed to elevate your brand and drive growth.</p>
        </div>
        <div class="row g-4 mt-3">
          <?php foreach ($publicServices as $service): ?>
          <div class="col-6 col-md-3 fade-up">
            <a href="services.php" class="glass-card d-block text-center text-decoration-none h-100">
              <div class="icon-circle mx-auto mb-2"><i class="fas <?= htmlspecialchars($service['icon'] ?? 'fa-code') ?> fa-xl"></i></div>
              <h5 class="fw-bold mt-2"><?= htmlspecialchars($service['name']) ?></h5>
              <p class="text-secondary small"><?= htmlspecialchars($service['description']) ?></p>
            </a>
          </div>
          <?php endforeach; ?>
        </div>
        <div class="text-center mt-4 fade-up">
          <a href="services.php" class="btn btn-gold-outline"><i class="fas fa-arrow-right me-2"></i> View All Services</a>
        </div>
      </div>
    </section>

    <!-- WHY CHOOSE US -->
    <section class="py-5">
      <div class="container">
        <div class="text-center max-w-2xl mx-auto fade-up">
          <span class="section-badge"><i class="fas fa-question-circle me-1"></i> Why Luxora</span>
          <h2 class="section-title">Built Different. <span class="gold">Built Better.</span></h2>
        </div>
        <div class="row g-4 mt-3">
          <div class="col-md-4 fade-up"><div class="glass-card-gold text-center h-100"><i class="fas fa-bolt fs-1 text-gold mb-3"></i><h5 class="fw-bold">Speed & Performance</h5><p class="text-secondary small">Lightning-fast websites that keep users engaged.</p></div></div>
          <div class="col-md-4 fade-up"><div class="glass-card-gold text-center h-100"><i class="fas fa-bullseye fs-1 text-gold mb-3"></i><h5 class="fw-bold">Conversion Focused</h5><p class="text-secondary small">Every pixel designed to turn visitors into customers.</p></div></div>
          <div class="col-md-4 fade-up"><div class="glass-card-gold text-center h-100"><i class="fas fa-shield-halved fs-1 text-gold mb-3"></i><h5 class="fw-bold">Premium Support</h5><p class="text-secondary small">White-glove service from strategy to launch.</p></div></div>
        </div>
      </div>
    </section>

    <!-- STATISTICS (DYNAMIC - CONSISTENT) -->
    <section class="py-4 border-y border-white/5 bg-card/20">
      <div class="container">
        <div class="row text-center g-4">
          <div class="col-6 col-md-3 fade-up"><div class="stat-number"><?= max($totalClients, 0) ?>+</div><p class="text-secondary small"><i class="fas fa-users text-gold me-1"></i> Clients Worldwide</p></div>
          <div class="col-6 col-md-3 fade-up"><div class="stat-number"><?= max($activeProjects, 0) ?>+</div><p class="text-secondary small"><i class="fas fa-folder-open text-gold me-1"></i> Projects Completed</p></div>
          <div class="col-6 col-md-3 fade-up"><div class="stat-number"><?= $satisfactionRate ?>%</div><p class="text-secondary small"><i class="fas fa-smile text-gold me-1"></i> Satisfaction Rate</p></div>
          <div class="col-6 col-md-3 fade-up"><div class="stat-number">100%</div><p class="text-secondary small"><i class="fas fa-clock text-gold me-1"></i> Dedicated Support</p></div>
        </div>
      </div>
    </section>

    <!-- PROCESS -->
    <section class="py-5">
      <div class="container">
        <div class="text-center max-w-2xl mx-auto fade-up">
          <span class="section-badge"><i class="fas fa-list-check me-1"></i> How We Work</span>
          <h2 class="section-title">Our <span class="gold">Process</span></h2>
        </div>
        <div class="row g-4 mt-3">
          <div class="col-md-3 fade-up"><div class="glass-card position-relative h-100"><div class="position-absolute top-0 end-0 fs-1 fw-black text-gold/20 pe-3 pt-2">01</div><div class="pt-4"><i class="fas fa-clipboard-list fs-2 text-gold mb-2"></i><h5 class="fw-bold mt-2">Discovery</h5><p class="text-secondary small">We learn your business, goals, and audience.</p></div></div></div>
          <div class="col-md-3 fade-up"><div class="glass-card position-relative h-100"><div class="position-absolute top-0 end-0 fs-1 fw-black text-gold/20 pe-3 pt-2">02</div><div class="pt-4"><i class="fas fa-compass-drafting fs-2 text-gold mb-2"></i><h5 class="fw-bold mt-2">Strategy</h5><p class="text-secondary small">Data-driven planning for maximum impact.</p></div></div></div>
          <div class="col-md-3 fade-up"><div class="glass-card position-relative h-100"><div class="position-absolute top-0 end-0 fs-1 fw-black text-gold/20 pe-3 pt-2">03</div><div class="pt-4"><i class="fas fa-gear fs-2 text-gold mb-2"></i><h5 class="fw-bold mt-2">Creation</h5><p class="text-secondary small">Design & development with precision.</p></div></div></div>
          <div class="col-md-3 fade-up"><div class="glass-card position-relative h-100"><div class="position-absolute top-0 end-0 fs-1 fw-black text-gold/20 pe-3 pt-2">04</div><div class="pt-4"><i class="fas fa-rocket fs-2 text-gold mb-2"></i><h5 class="fw-bold mt-2">Launch & Grow</h5><p class="text-secondary small">Deploy, monitor, and scale for success.</p></div></div></div>
        </div>
      </div>
    </section>

    <!-- PRICING PREVIEW -->
    <section class="py-5 bg-card/30" id="pricing">
      <div class="container">
        <div class="text-center max-w-2xl mx-auto fade-up">
          <span class="section-badge"><i class="fas fa-tag me-1"></i> Pricing</span>
          <h2 class="section-title">Choose Your <span class="gold">Plan</span></h2>
          <p class="text-secondary mt-3">Flexible solutions for every budget. All plans include premium support.</p>
        </div>
        <div class="row g-4 mt-3">
          <!-- STARTER PLAN -->
          <div class="col-md-4 fade-up">
            <div class="pricing-card">
              <h5 class="fw-bold">Starter</h5>
              <div class="fs-1 fw-black mt-2">$250<span class="text-secondary fs-6 fw-normal">/mo</span></div>
              <p class="text-secondary small mt-1">Perfect for getting started</p>
              <ul class="feature-list mt-3">
                <li><i class="fas fa-check-circle"></i> Video Editing <span class="feature-badge">5 videos</span></li>
                <li><i class="fas fa-check-circle"></i> Logo Design</li>
                <li><i class="fas fa-check-circle"></i> Business Card</li>
                <li><i class="fas fa-check-circle"></i> Thumbnail <span class="feature-badge">10</span></li>
                <li><i class="fas fa-check-circle"></i> 20 Business Posts</li>
                <li><i class="fas fa-check-circle"></i> AI Chatbot <span class="feature-badge">Basic</span></li>
              </ul>
              <a href="contact.php?service=Starter&plan=Starter&amount=250" class="btn btn-gold-outline w-100 justify-content-center mt-3"><i class="fas fa-arrow-right me-2"></i> Start Now</a>
            </div>
          </div>

          <!-- BUSINESS PLAN -->
          <div class="col-md-4 fade-up">
            <div class="pricing-card popular">
              <h5 class="fw-bold">Business</h5>
              <div class="fs-1 fw-black mt-2">$500<span class="text-secondary fs-6 fw-normal">/mo</span></div>
              <p class="text-secondary small mt-1">For growing businesses</p>
              <ul class="feature-list mt-3">
                <li><i class="fas fa-check-circle"></i> Video Editing <span class="feature-badge">10 videos</span></li>
                <li><i class="fas fa-check-circle"></i> Graphic Designing</li>
                <li><i class="fas fa-check-circle"></i> AI Automation</li>
                <li><i class="fas fa-check-circle"></i> 50 Business Posts</li>
                <li><i class="fas fa-check-circle"></i> SEO <span class="feature-badge">Advanced</span></li>
                <li><i class="fas fa-check-circle"></i> Business Card</li>
                <li><i class="fas fa-check-circle"></i> 3 Page Website</li>
              </ul>
              <a href="contact.php?service=Business&plan=Business&amount=500" class="btn btn-primary-custom w-100 justify-content-center mt-3"><i class="fas fa-arrow-right me-2"></i> Start Now</a>
            </div>
          </div>

          <!-- PRO PLAN -->
          <div class="col-md-4 fade-up">
            <div class="pricing-card">
              <h5 class="fw-bold">Pro</h5>
              <div class="fs-1 fw-black mt-2">$1,000<span class="text-secondary fs-6 fw-normal">/mo</span></div>
              <p class="text-secondary small mt-1">Full-scale digital agency</p>
              <ul class="feature-list mt-3">
                <li><i class="fas fa-check-circle"></i> Video Editing <span class="feature-badge">Unlimited</span></li>
                <li><i class="fas fa-check-circle"></i> SEO <span class="feature-badge">Premium</span></li>
                <li><i class="fas fa-check-circle"></i> Full Stack Website</li>
                <li><i class="fas fa-check-circle"></i> Graphic Designing</li>
                <li><i class="fas fa-check-circle"></i> Business Card</li>
                <li><i class="fas fa-check-circle"></i> Content Creation Script</li>
                <li><i class="fas fa-check-circle"></i> Thumbnail <span class="feature-badge">Unlimited</span></li>
                <li><i class="fas fa-check-circle"></i> AI Automation <span class="feature-badge">Advanced</span></li>
              </ul>
              <a href="contact.php?service=Pro&plan=Pro&amount=1000" class="btn btn-gold-outline w-100 justify-content-center mt-3"><i class="fas fa-arrow-right me-2"></i> Start Now</a>
            </div>
          </div>
        </div>
        <div class="text-center mt-3 fade-up">
          <p class="text-secondary small"><i class="fas fa-comment text-gold me-1"></i> Need a custom plan? <a href="contact.php" class="text-gold text-decoration-none">Contact us</a></p>
        </div>
      </div>
    </section>

    <!-- ===== REVIEWS SECTION (DYNAMIC FROM DATABASE) ===== -->
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

        <!-- Reviews Grid -->
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
                <div class="review-stars">
                  <?= renderStars($rating) ?>
                  <span class="rating-number"><?= $rating ?>.0</span>
                </div>
                <div class="review-text <?= $isLong ? '' : 'expanded' ?>">
                  <?= nl2br(htmlspecialchars($shortMessage)) ?>
                </div>
                <?php if ($isLong): ?>
                  <button class="review-read-more" onclick="this.previousElementSibling.classList.toggle('expanded'); this.innerHTML = this.previousElementSibling.classList.contains('expanded') ? 'Read Less <i class=\"fas fa-chevron-up\"></i>' : 'Read More <i class=\"fas fa-chevron-down\"></i>';">Read More <i class="fas fa-chevron-down"></i></button>
                <?php endif; ?>
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

    <!-- ===== FAQ SECTION (DYNAMIC FROM DATABASE) ===== -->
    <section class="py-5 bg-card/30" id="faq">
      <div class="container">
        <div class="text-center max-w-2xl mx-auto fade-up">
          <span class="section-badge"><i class="fas fa-circle-question me-1"></i> FAQ</span>
          <h2 class="section-title">Frequently Asked <span class="gold">Questions</span></h2>
          <p class="text-secondary mt-2">Find answers to the most common questions our clients ask.</p>
        </div>
        <div class="row justify-content-center mt-3">
          <div class="col-lg-8">
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
                <div class="dynamic-faq-item <?= $index === 0 ? 'open' : '' ?>" id="home-faq-<?= $faq['id'] ?>">
                  <div class="dynamic-faq-q" onclick="toggleHomeFaq(<?= $faq['id'] ?>)">
                    <span><?= htmlspecialchars($faq['question']) ?></span>
                    <span class="dynamic-faq-icon">
                      <i class="fas fa-chevron-down"></i>
                    </span>
                  </div>
                  <div class="dynamic-faq-a">
                    <div class="dynamic-faq-meta">
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

                    <div class="dynamic-faq-question">
                      <i class="fas fa-question-circle"></i>
                      <span><?= nl2br(htmlspecialchars($faq['question'])) ?></span>
                    </div>

                    <div class="dynamic-faq-answer">
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
              
              <!-- View All FAQs Link -->
              <div class="text-center mt-4">
                <a href="faq.php" class="btn-gold-outline" style="display:inline-flex;align-items:center;gap:0.5rem;">
                  <i class="fas fa-arrow-right"></i> View All FAQs
                </a>
              </div>
            <?php else: ?>
              <!-- EMPTY STATE -->
              <div class="empty-faq-preview">
                <i class="fas fa-question-circle empty-icon"></i>
                <h5>No Questions Yet</h5>
                <p>Have a question? We'd love to answer it!<br>Ask us anything about our services.</p>
                <a href="contact.php" class="btn-primary-custom" style="display:inline-flex;align-items:center;gap:0.5rem;">
                  <i class="fas fa-pen"></i> Ask a Question
                </a>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA BANNER -->
    <section class="py-5 position-relative overflow-hidden">
      <div class="position-absolute top-0 start-0 w-100 h-100 bg-primary/5 blur-3xl"></div>
      <div class="container position-relative z-1">
        <div class="glass-gold rounded-4 p-5 text-center max-w-4xl mx-auto fade-up">
          <h2 class="section-title" style="font-size:clamp(2rem,4vw,3.5rem);">Ready to Build Something <span class="gold">Amazing</span>?</h2>
          <p class="text-secondary fs-5 mt-3 max-w-xl mx-auto">Let's create digital experiences that drive growth and leave a lasting impression.</p>
          <div class="d-flex flex-wrap justify-content-center gap-3 mt-4">
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
        <div class="col-md-3"><h6 class="fw-bold text-uppercase small text-secondary"><i class="fas fa-envelope me-1"></i> Newsletter</h6><form class="d-flex gap-2" onsubmit="event.preventDefault();showToast('✓ Subscribed!')"><input type="email" placeholder="Your email" class="form-input flex-1" style="flex:1;" required /><button type="submit" class="btn-primary-custom px-3 py-2"><i class="fas fa-arrow-right"></i></button></form></div>
      </div>
      <div class="border-top border-white/5 mt-4 pt-3 d-flex flex-wrap justify-content-between small text-secondary"><span><i class="far fa-copyright me-1"></i> 2026 Luxora Media. All Rights Reserved.</span><span>Made with <i class="fas fa-heart text-gold"></i> in Berlin</span></div>
    </div>
  </footer>

  <div id="toast" class="toast-notification"><i class="fas fa-check-circle text-gold me-2"></i> Message sent! We'll <span class="gold">get back to you</span> soon.</div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="luxora.js"></script>
  
  <!-- FAQ ACCORDION JS - HOME PAGE -->
  <script>
  function toggleHomeFaq(id) {
    var item = document.getElementById('home-faq-' + id);
    if (item) {
      item.classList.toggle('open');
    }
  }
  
  document.addEventListener('DOMContentLoaded', function() {
    // Open first FAQ by default
    var firstFaq = document.querySelector('.dynamic-faq-item');
    if (firstFaq) {
      firstFaq.classList.add('open');
    }
  });
  </script>
</body>
</html>