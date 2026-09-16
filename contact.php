<?php
session_start();
require_once 'db.php';
require_once 'config.php';

// Get service/plan from URL parameters
$preselected_service = isset($_GET['service']) ? trim(urldecode($_GET['service'])) : '';
$preselected_plan = isset($_GET['plan']) ? trim(urldecode($_GET['plan'])) : '';
$preselected_amount = isset($_GET['amount']) ? trim(urldecode($_GET['amount'])) : '';

// If plan is selected, set the service type to the plan name
$service_type_value = '';
if (!empty($preselected_service)) {
    $service_type_value = $preselected_service;
} elseif (!empty($preselected_plan)) {
    $service_type_value = $preselected_plan . ' Plan';
}

// Fetch contact info from database
$contactInfo = [];
try {
    $db = getDB();
    $stmt = $db->query("SELECT * FROM contact_info WHERE is_active = 1");
    $contactInfo = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    $contactInfo = [];
}

// Fallback contact info
$contactDefaults = [
    'email' => 'luxoramedia89@gmail.com',
    'phone' => '03062641110',
    'address' => 'Karachi, Pakistan',
    'hours' => 'Monday – Saturday, 24/7',
    'instagram' => 'https://www.instagram.com/luxoramedia_/',
    'instagram_handle' => 'luxoramedia_'
];

// Merge with defaults
$contactData = array_merge($contactDefaults, $contactInfo);

// Fetch service options from database
$serviceOptions = [];
try {
    $db = getDB();
    $stmt = $db->query("SELECT name FROM services WHERE status = 'active' ORDER BY id ASC");
    $serviceOptions = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $serviceOptions = [];
}

// Fallback service options
if (empty($serviceOptions)) {
    $serviceOptions = [
        'Video Editing', 'SEO', 'Full Stack Website', 'Graphic Designing',
        'Business Card', 'Content Creation Script', 'Thumbnail', 'AI Automation',
        'Web Development', 'AI Chatbots', 'Branding', 'Digital Marketing', 'Social Media'
    ];
}

// Add pricing plan options
$planOptions = ['Starter Plan', 'Business Plan', 'Pro Plan'];
$allServiceOptions = array_merge($serviceOptions, $planOptions);

/**
 * Detect if a question is pricing-related
 * Returns 'pricing' if detected, otherwise null
 */
function detectPricingCategory($message) {
    $pricingKeywords = [
        'pricing', 'plan', 'starter', 'business', 'pro', 
        'price', 'cost', 'fee', 'monthly', 'annual', 
        'subscription', 'package', 'billing', 'charge',
        'payment', 'invoice', 'budget', 'afford',
        'how much', 'rate', 'quote', 'estimate',
        'installment', 'installments', 'emi', 'monthly payment',
        'pay later', 'split payment', 'payment plan'
    ];
    
    $messageLower = strtolower($message);
    foreach ($pricingKeywords as $keyword) {
        if (strpos($messageLower, $keyword) !== false) {
            return 'pricing';
        }
    }
    return null;
}

// ==========================================
// ROUTING LOGIC: Reviews to Inquiries (with Visible FAQ), Services to Projects
// ==========================================
function processContactSubmission($data) {
    global $db;
    
    try {
        $is_review = ($data['service_type'] === 'Review');
        $is_question = ($data['service_type'] === 'Question');
        $is_service = !$is_review && !$is_question;
        
        // 1. REVIEWS (Insert into Inquiries + Visible FAQs with rating)
        if ($is_review) {
            // Build message with rating
            $rating = isset($data['rating']) ? intval($data['rating']) : 0;
            $ratingStars = $rating > 0 ? str_repeat('⭐', $rating) : '';
            $messageWithRating = $data['message'];
            if ($rating > 0) {
                $messageWithRating = "Rating: " . $rating . "/5 " . $ratingStars . "\n\n" . $data['message'];
            }
            
            // Insert into inquiries
            $stmt = $db->prepare("INSERT INTO inquiries (name, email, phone, service_type, message, budget, status, rating, created_at) VALUES (:name, :email, :phone, :service_type, :message, :budget, 'Review', :rating, NOW())");
            $stmt->execute([
                ':name' => $data['name'],
                ':email' => $data['email'],
                ':phone' => $data['phone'] ?? '',
                ':service_type' => 'Review',
                ':message' => $messageWithRating,
                ':budget' => $data['budget'] ?? '',
                ':rating' => $rating
            ]);
            $inquiry_id = $db->lastInsertId();
            
            // Create FAQ entry
            $stmt = $db->query("SELECT MAX(display_order) as max_order FROM faqs");
            $max_order = $stmt->fetch()['max_order'] ?? 0;
            
            $questionText = $data['message'];
            if ($rating > 0) {
                $questionText = "⭐ " . str_repeat('⭐', $rating) . " - " . $data['message'];
            }
            
            $stmt = $db->prepare("INSERT INTO faqs (question, answer, inquiry_id, display_order, user_name, user_email, is_visible, rating, created_at) VALUES (:question, :answer, :inquiry_id, :display_order, :user_name, :user_email, 1, :rating, NOW())");
            $stmt->execute([
                ':question' => $questionText,
                ':answer' => 'Waiting for admin response...',
                ':inquiry_id' => $inquiry_id,
                ':display_order' => $max_order + 1,
                ':user_name' => $data['name'],
                ':user_email' => $data['email'],
                ':rating' => $rating
            ]);
            
            $faq_id = $db->lastInsertId();
            
            // Link FAQ to Inquiry
            $stmt = $db->prepare("UPDATE inquiries SET faq_id = :faq_id WHERE id = :id");
            $stmt->execute([':faq_id' => $faq_id, ':id' => $inquiry_id]);
            
            return ['success' => true, 'id' => $inquiry_id, 'is_review' => true, 'is_question' => false];
        }
        
        // 2. QUESTIONS (Insert into Inquiries + Visible FAQs with category detection)
        if ($is_question) {
            // Insert into inquiries
            $stmt = $db->prepare("INSERT INTO inquiries (name, email, phone, service_type, message, budget, status, created_at) VALUES (:name, :email, :phone, :service_type, :message, :budget, 'Question', NOW())");
            $stmt->execute([
                ':name' => $data['name'],
                ':email' => $data['email'],
                ':phone' => $data['phone'] ?? '',
                ':service_type' => 'Question',
                ':message' => $data['message'],
                ':budget' => $data['budget'] ?? ''
            ]);
            $inquiry_id = $db->lastInsertId();
            
            // Detect if this is a pricing-related question
            $category_slug = detectPricingCategory($data['message']);
            
            // Create FAQ entry with category
            $stmt = $db->query("SELECT MAX(display_order) as max_order FROM faqs");
            $max_order = $stmt->fetch()['max_order'] ?? 0;
            
            $stmt = $db->prepare("INSERT INTO faqs (question, answer, inquiry_id, display_order, user_name, user_email, is_visible, category_slug, created_at) VALUES (:question, :answer, :inquiry_id, :display_order, :user_name, :user_email, 1, :category_slug, NOW())");
            $stmt->execute([
                ':question' => $data['message'],
                ':answer' => 'Waiting for admin response...',
                ':inquiry_id' => $inquiry_id,
                ':display_order' => $max_order + 1,
                ':user_name' => $data['name'],
                ':user_email' => $data['email'],
                ':category_slug' => $category_slug
            ]);
            
            $faq_id = $db->lastInsertId();
            
            // Link FAQ to Inquiry
            $stmt = $db->prepare("UPDATE inquiries SET faq_id = :faq_id WHERE id = :id");
            $stmt->execute([':faq_id' => $faq_id, ':id' => $inquiry_id]);
            
            return ['success' => true, 'id' => $inquiry_id, 'is_review' => false, 'is_question' => true];
        }
        
        // 3. ACTUAL SERVICES (Insert ONLY into Projects)
        else {
            $project_name = $data['service_type'] . ' - ' . $data['name'];
            $due_date = date('Y-m-d', strtotime('+30 days'));
            
            $stmt = $db->prepare("INSERT INTO projects (project_name, client_name, client_email, client_phone, service_type, project_description, budget, status, amount, due_date, source, created_at) VALUES (:project_name, :client_name, :client_email, :client_phone, :service_type, :project_description, :budget, 'Still Pending', :amount, :due_date, 'Contact Form', NOW())");
            $stmt->execute([
                ':project_name' => $project_name,
                ':client_name' => $data['name'],
                ':client_email' => $data['email'],
                ':client_phone' => $data['phone'] ?? '',
                ':service_type' => $data['service_type'],
                ':project_description' => $data['message'],
                ':budget' => $data['budget'] ?? '',
                ':amount' => $data['amount'] ?? 0,
                ':due_date' => $due_date
            ]);
            
            $project_id = $db->lastInsertId();
            return ['success' => true, 'id' => $project_id, 'is_review' => false, 'is_question' => false];
        }
        
    } catch (PDOException $e) {
        error_log("Submission error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Failed to process: ' . $e->getMessage()];
    }
}

// Function to send email notification
function sendProjectNotification($data, $id, $is_review = false, $is_question = false) {
    $to = 'luxoramedia89@gmail.com';
    $subject = $is_review ? 'New Review from ' . $data['name'] : ($is_question ? 'New Question from ' . $data['name'] : 'New Project Inquiry from ' . $data['name']);
    
    $badge_type = $is_review ? '⭐ Review' : ($is_question ? '❓ Question' : '📋 Project Inquiry');
    $id_label = $is_review || $is_question ? 'Inquiry ID' : 'Project ID';

    $ratingDisplay = '';
    if ($is_review && isset($data['rating']) && $data['rating'] > 0) {
        $ratingDisplay = '<div class="field"><span class="field-label">Rating</span><div class="field-value">' . str_repeat('⭐', intval($data['rating'])) . ' (' . $data['rating'] . '/5)</div></div>';
    }

    $email_body = "
    <html>
    <head><style>
        body { font-family: 'Inter', Arial, sans-serif; background: #050505; color: #ffffff; padding: 20px; }
        .container { max-width: 650px; margin: 0 auto; background: #101010; border: 1px solid rgba(212, 175, 55, 0.2); border-radius: 16px; padding: 40px; }
        .header { border-bottom: 2px solid #D4AF37; padding-bottom: 20px; margin-bottom: 25px; }
        .logo { font-size: 28px; font-weight: 900; color: #D4AF37; }
        .badge { background: rgba(212, 175, 55, 0.15); color: #D4AF37; padding: 4px 14px; border-radius: 20px; font-size: 12px; }
        .badge-review { background: rgba(255, 215, 0, 0.15); color: #FFD700; padding: 4px 14px; border-radius: 20px; font-size: 12px; }
        .badge-question { background: rgba(54, 164, 235, 0.15); color: #36a4eb; padding: 4px 14px; border-radius: 20px; font-size: 12px; }
        .field { margin-bottom: 18px; }
        .field-label { color: #A5A5A5; font-size: 11px; text-transform: uppercase; }
        .field-value { font-size: 15px; color: #ffffff; }
        .field-value.message { background: rgba(255,255,255,0.03); padding: 15px; border-radius: 8px; border-left: 3px solid " . ($is_review ? '#FFD700' : ($is_question ? '#36a4eb' : '#D4AF37')) . "; }
        .project-id { background: rgba(212, 175, 55, 0.08); padding: 2px 12px; border-radius: 4px; font-family: monospace; color: #D4AF37; }
        .rating-stars { font-size: 24px; letter-spacing: 2px; }
    </style></head>
    <body>
        <div class='container'>
            <div class='header'><div class='logo'>✨ LUXORA MEDIA</div></div>
            <div style='text-align:center; margin-bottom: 15px;'><span class='" . ($is_review ? 'badge-review' : ($is_question ? 'badge-question' : 'badge')) . "'>" . $badge_type . "</span></div>
            <div style='background: rgba(212, 175, 55, 0.05); border-radius: 8px; padding: 12px 16px; margin-bottom: 20px;'>
                <span style='color: #A5A5A5; font-size: 12px;'>" . $id_label . "</span>
                <span class='project-id' style='float: right;'>#{$id}</span>
            </div>
            <div class='field'><span class='field-label'>Name</span><div class='field-value'><strong>{$data['name']}</strong></div></div>
            <div class='field'><span class='field-label'>Email</span><div class='field-value'><a href='mailto:{$data['email']}' style='color: #D4AF37;'>{$data['email']}</a></div></div>
            <div class='field'><span class='field-label'>Phone</span><div class='field-value'>" . (!empty($data['phone']) ? $data['phone'] : 'Not provided') . "</div></div>
            <div class='field'><span class='field-label'>Type</span><div class='field-value'>" . ($is_review ? 'Review' : ($is_question ? 'Question' : $data['service_type'])) . "</div></div>
            " . $ratingDisplay . "
            " . (!$is_review && !$is_question && !empty($data['budget']) ? "<div class='field'><span class='field-label'>Budget</span><div class='field-value'>{$data['budget']}</div></div>" : "") . "
            <div class='field'><span class='field-label'>Message</span><div class='field-value message'>{$data['message']}</div></div>
            <div class='footer' style='margin-top:30px; padding-top:20px; border-top:1px solid rgba(255,255,255,0.06); text-align:center; color:#A5A5A5; font-size:12px;'>Luxora Media — © " . date('Y') . "</div>
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: Luxora Media <noreply@" . $_SERVER['HTTP_HOST'] . ">\r\n";
    $headers .= "Reply-To: {$data['email']}\r\n";
    
    return mail($to, $subject, $email_body, $headers);
}

// Process AJAX form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    error_reporting(0);
    
    try {
        $db = getDB();
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
        exit;
    }
    
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $service_type = trim($_POST['service_type'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $budget = trim($_POST['budget'] ?? '');
    $amount = trim($_POST['amount'] ?? 0);
    $plan = trim($_POST['plan'] ?? '');
    $rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
    
    $errors = [];
    if (empty($name)) $errors[] = 'Please enter your full name.';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
    if (empty($service_type)) $errors[] = 'Please select a service or "Question" or "Review" option.';
    if (empty($message) || strlen($message) < 10) $errors[] = 'Please enter a message (at least 10 characters).';
    if ($service_type === 'Review' && $rating === 0) $errors[] = 'Please select a rating (1-5 stars).';
    
    if (!empty($errors)) {
        echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
        exit;
    }
    
    $form_data = [
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'service_type' => $service_type,
        'message' => $message,
        'budget' => $budget,
        'amount' => $amount,
        'plan' => $plan,
        'rating' => $rating
    ];
    
    $result = processContactSubmission($form_data);
    
    if ($result['success']) {
        sendProjectNotification($form_data, $result['id'], $result['is_review'], $result['is_question']);
        
        $successMsg = 'Thank you! ';
        if ($result['is_review']) {
            $successMsg .= 'Your review has been submitted and will appear on our website.';
        } elseif ($result['is_question']) {
            $successMsg .= 'Your question has been added to our FAQ.';
        } else {
            $successMsg .= 'We will get back to you within 24 hours.';
        }
        
        echo json_encode([
            'success' => true,
            'message' => $successMsg,
            'project_id' => $result['id']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => $result['error'] ?? 'There was an error.']);
    }
    exit;
}

// Regular form submission (fallback)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax'])) {
    try {
        $db = getDB();
    } catch (Exception $e) {
        $error_message = 'Database connection failed.';
    }
    
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $service_type = trim($_POST['service_type'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $budget = trim($_POST['budget'] ?? '');
    $amount = trim($_POST['amount'] ?? 0);
    $plan = trim($_POST['plan'] ?? '');
    $rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
    
    $errors = [];
    if (empty($name)) $errors[] = 'Please enter your full name.';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
    if (empty($service_type)) $errors[] = 'Please select a service.';
    if (empty($message) || strlen($message) < 10) $errors[] = 'Please enter a message.';
    if ($service_type === 'Review' && $rating === 0) $errors[] = 'Please select a rating (1-5 stars).';

    if (empty($errors)) {
        $form_data = [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'service_type' => $service_type,
            'message' => $message,
            'budget' => $budget,
            'amount' => $amount,
            'plan' => $plan,
            'rating' => $rating
        ];
        
        $result = processContactSubmission($form_data);
        
        if ($result['success']) {
            sendProjectNotification($form_data, $result['id'], $result['is_review'], $result['is_question']);
            $success_message = $result['is_review'] ? 'Thank you! Your review has been submitted.' : ($result['is_question'] ? 'Thank you! Your question has been added to our FAQ.' : 'Thank you! We will get back to you within 24 hours.');
            $project_id = $result['id'];
        } else {
            $error_message = $result['error'] ?? 'There was an error processing your request.';
        }
    } else {
        $error_message = implode('<br>', $errors);
    }
}
?>
<?php include 'admin-bar.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Contact – Luxora Media</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <link rel="stylesheet" href="luxora.css" />
  <style>
    /* ===== SUCCESS POP-UP MODAL ===== */
    .success-modal-overlay {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.85);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      z-index: 999999;
      justify-content: center;
      align-items: center;
      animation: fadeIn 0.4s ease;
      padding: 1rem;
    }
    .success-modal-overlay.show {
      display: flex !important;
    }
    .success-modal {
      background: rgba(16, 16, 16, 0.98);
      border: 1px solid rgba(46, 213, 115, 0.2);
      border-radius: 1.5rem;
      padding: 2.5rem 2rem;
      max-width: 480px;
      width: 100%;
      box-shadow: 0 40px 80px rgba(0, 0, 0, 0.8);
      animation: slideUp 0.4s ease;
      text-align: center;
      position: relative;
      overflow: hidden;
    }
    .success-modal::before {
      content: '';
      position: absolute;
      top: -50%;
      left: -50%;
      width: 200%;
      height: 200%;
      background: radial-gradient(circle at 30% 40%, rgba(46, 213, 115, 0.03), transparent 60%);
      pointer-events: none;
    }
    .success-icon {
      width: 72px;
      height: 72px;
      border-radius: 50%;
      background: rgba(46, 213, 115, 0.1);
      border: 2px solid rgba(46, 213, 115, 0.2);
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 1.2rem;
      font-size: 2.5rem;
      color: #2ed573;
      animation: popIn 0.6s ease;
    }
    .success-title {
      font-size: 1.4rem;
      font-weight: 700;
      color: #ffffff;
      margin-bottom: 0.5rem;
      font-family: 'Poppins', sans-serif;
    }
    .success-text {
      color: #A5A5A5;
      font-size: 0.95rem;
      line-height: 1.6;
      margin-bottom: 1.5rem;
    }
    .success-details {
      background: rgba(255,255,255,0.02);
      border: 1px solid rgba(255,255,255,0.08);
      border-radius: 0.8rem;
      padding: 0.8rem 1.2rem;
      margin-bottom: 1.5rem;
      text-align: left;
    }
    .success-details .detail-item {
      display: flex;
      justify-content: space-between;
      padding: 0.3rem 0;
      border-bottom: 1px solid rgba(255,255,255,0.03);
      font-size: 0.85rem;
    }
    .success-details .detail-item:last-child {
      border-bottom: none;
    }
    .success-details .detail-label {
      color: #A5A5A5;
    }
    .success-details .detail-value {
      color: #ffffff;
      font-weight: 500;
    }
    .success-details .detail-value.status-badge {
      background: rgba(212, 175, 55, 0.15);
      color: #D4AF37;
      padding: 0.05rem 0.6rem;
      border-radius: 50px;
      font-size: 0.75rem;
    }
    .success-btn {
      background: linear-gradient(135deg, #2ed573, #26de81);
      color: #fff;
      border: none;
      border-radius: 50px;
      padding: 0.7rem 2rem;
      font-size: 0.95rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      font-family: 'Inter', sans-serif;
      width: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
    }
    .success-btn:hover {
      transform: scale(1.02);
      box-shadow: 0 8px 30px rgba(46, 213, 115, 0.3);
    }
    .success-btn:active {
      transform: scale(0.98);
    }

    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }
    @keyframes slideUp {
      from { opacity: 0; transform: translateY(40px) scale(0.95); }
      to { opacity: 1; transform: translateY(0) scale(1); }
    }
    @keyframes popIn {
      0% { transform: scale(0); opacity: 0; }
      50% { transform: scale(1.1); }
      100% { transform: scale(1); opacity: 1; }
    }

    /* Mobile Responsive for Success Modal */
    @media (max-width: 576px) {
      .success-modal {
        padding: 1.8rem 1.2rem;
        border-radius: 1.2rem;
      }
      .success-icon {
        width: 60px;
        height: 60px;
        font-size: 2rem;
      }
      .success-title {
        font-size: 1.2rem;
      }
      .success-text {
        font-size: 0.85rem;
      }
      .success-details {
        padding: 0.6rem 0.8rem;
      }
      .success-details .detail-item {
        font-size: 0.75rem;
      }
      .success-btn {
        font-size: 0.85rem;
        padding: 0.6rem 1.5rem;
      }
    }
    .social-icon-link {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      color: #a5a5a5;
      text-decoration: none;
      transition: color 0.3s ease;
    }
    .social-icon-link:hover {
      color: #d4af37;
    }
    .social-icon-link i {
      font-size: 1.8rem;
    }
    .contact-info-item {
      display: flex;
      align-items: center;
      gap: 1rem;
      padding: 0.5rem 0;
      border-bottom: 1px solid rgba(255,255,255,0.05);
    }
    .contact-info-item:last-child {
      border-bottom: none;
    }
    .contact-info-item .icon-box {
      width: 40px;
      height: 40px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: rgba(212, 175, 55, 0.08);
      border-radius: 10px;
      color: #d4af37;
      font-size: 1.1rem;
      flex-shrink: 0;
    }
    .contact-info-item .info-text {
      color: #e0e0e0;
      font-size: 0.95rem;
    }
    .contact-info-item .info-text small {
      color: #a5a5a5;
      font-size: 0.8rem;
      display: block;
    }

    .form-select-custom {
      width: 100%;
      background: #0a0a0a;
      border: 1px solid rgba(212, 175, 55, 0.15);
      border-radius: 12px;
      padding: 0.9rem 1.2rem;
      color: #e0e0e0;
      font-family: 'Inter', sans-serif;
      font-size: 0.95rem;
      transition: all 0.3s ease;
      outline: none;
      appearance: none;
      -webkit-appearance: none;
      -moz-appearance: none;
      cursor: pointer;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%23d4af37' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 1.2rem center;
      padding-right: 3rem;
      box-shadow: 0 0 20px rgba(212, 175, 55, 0.03);
    }
    .form-select-custom:hover {
      border-color: rgba(212, 175, 55, 0.3);
    }
    .form-select-custom:focus {
      border-color: #d4af37;
      box-shadow: 0 0 30px rgba(212, 175, 55, 0.15), inset 0 0 30px rgba(212, 175, 55, 0.02);
    }
    .form-select-custom.error {
      border-color: #ff4757;
      box-shadow: 0 0 30px rgba(255, 71, 87, 0.15);
    }
    .form-select-custom option {
      background: #0a0a0a;
      color: #e0e0e0;
      padding: 0.8rem 1.2rem;
      border-bottom: 1px solid rgba(255,255,255,0.03);
    }
    .form-select-custom option:hover {
      background: rgba(212, 175, 55, 0.08);
    }
    .form-select-custom option:checked {
      background: rgba(212, 175, 55, 0.12);
      color: #d4af37;
    }

    .form-input {
      width: 100%;
      background: #0a0a0a;
      border: 1px solid rgba(255,255,255,0.06);
      border-radius: 12px;
      padding: 0.9rem 1.2rem;
      color: #e0e0e0;
      font-family: 'Inter', sans-serif;
      font-size: 0.95rem;
      transition: all 0.3s ease;
      outline: none;
    }
    .form-input:hover {
      border-color: rgba(212, 175, 55, 0.15);
    }
    .form-input:focus {
      border-color: #d4af37;
      box-shadow: 0 0 30px rgba(212, 175, 55, 0.08);
    }
    .form-input.error {
      border-color: #ff4757;
      box-shadow: 0 0 30px rgba(255, 71, 87, 0.1);
    }
    .form-input::placeholder {
      color: rgba(255,255,255,0.2);
    }
    .form-input textarea {
      resize: vertical;
      min-height: 100px;
    }
    .form-label {
      font-size: 0.8rem;
      font-weight: 500;
      color: rgba(255,255,255,0.5);
      letter-spacing: 0.04em;
      text-transform: uppercase;
      margin-bottom: 0.4rem;
      display: block;
    }
    .form-label i {
      color: #d4af37;
      margin-right: 0.3rem;
    }

    .error-message {
      color: #ff4757;
      font-size: 0.8rem;
      margin-top: 0.3rem;
      display: none;
      align-items: center;
      gap: 0.3rem;
    }
    .error-message.show {
      display: flex;
    }
    .error-message i {
      font-size: 0.7rem;
    }

    .form-input.success {
      border-color: #2ed573;
      box-shadow: 0 0 30px rgba(46, 213, 115, 0.08);
    }
    .form-select-custom.success {
      border-color: #2ed573;
      box-shadow: 0 0 30px rgba(46, 213, 115, 0.08);
    }

    select.form-select-custom option {
      background-color: #0a0a0a;
      color: #e0e0e0;
    }
    select.form-select-custom option:checked {
      background: linear-gradient(135deg, rgba(212, 175, 55, 0.15), rgba(212, 175, 55, 0.05));
      color: #d4af37;
    }

    .btn-primary-custom.loading {
      opacity: 0.7;
      pointer-events: none;
    }
    .btn-primary-custom .spinner {
      display: none;
    }
    .btn-primary-custom.loading .spinner {
      display: inline-block;
      animation: spin 0.8s linear infinite;
    }
    @keyframes spin {
      to { transform: rotate(360deg); }
    }

    /* ===== STAR RATING ===== */
    .star-rating {
      display: flex;
      flex-direction: row-reverse;
      gap: 0.3rem;
      justify-content: flex-end;
      padding: 0.5rem 0;
    }
    .star-rating input {
      display: none;
    }
    .star-rating label {
      font-size: 2rem;
      color: rgba(255, 255, 255, 0.1);
      cursor: pointer;
      transition: all 0.2s ease;
      user-select: none;
    }
    .star-rating label:hover,
    .star-rating label:hover ~ label,
    .star-rating input:checked ~ label {
      color: #FFD700;
      transform: scale(1.1);
    }
    .star-rating input:checked ~ label {
      text-shadow: 0 0 20px rgba(255, 215, 0, 0.3);
    }
    .star-rating label:hover {
      transform: scale(1.15);
    }
    .star-rating .rating-text {
      font-size: 0.8rem;
      color: #A5A5A5;
      margin-left: 0.5rem;
      display: flex;
      align-items: center;
    }
    .star-rating-wrapper {
      display: none;
      margin-top: 0.5rem;
      padding: 0.8rem 1rem;
      background: rgba(255, 215, 0, 0.02);
      border: 1px solid rgba(255, 215, 0, 0.08);
      border-radius: 12px;
    }
    .star-rating-wrapper.show {
      display: block;
    }
    .star-rating-wrapper .rating-label {
      font-size: 0.75rem;
      color: #A5A5A5;
      font-weight: 500;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      margin-bottom: 0.3rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    .star-rating-wrapper .rating-label i {
      color: #FFD700;
    }
    .star-rating-wrapper .rating-error {
      color: #ff4757;
      font-size: 0.75rem;
      margin-top: 0.3rem;
      display: none;
    }
    .star-rating-wrapper .rating-error.show {
      display: block;
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
    
    /* ===== FORM GLOW EFFECT ===== */
    .glass-card-gold {
      transition: border-color 0.3s ease, box-shadow 0.3s ease;
    }
    .glass-card-gold:hover {
      border-color: rgba(212, 175, 55, 0.35);
      box-shadow: 0 0 60px rgba(212, 175, 55, 0.1);
      transform: none !important;
    }

    /* Toast notification */
    .toast-notification {
      position: fixed;
      bottom: 30px;
      right: 30px;
      background: rgba(16, 16, 16, 0.95);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      border: 1px solid rgba(212, 175, 55, 0.2);
      border-radius: 16px;
      padding: 1.2rem 1.8rem;
      color: #e0e0e0;
      font-family: 'Inter', sans-serif;
      font-size: 0.95rem;
      max-width: 420px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.6);
      z-index: 9999;
      transform: translateY(20px);
      opacity: 0;
      transition: all 0.4s cubic-bezier(0.22, 1, 0.36, 1);
      pointer-events: none;
    }
    .toast-notification.show {
      transform: translateY(0);
      opacity: 1;
      pointer-events: auto;
    }
    .toast-notification .gold {
      color: #d4af37;
    }
    .toast-notification i {
      color: #2ed573;
    }

    /* Selected plan indicator */
    .selected-plan-badge {
      display: <?= !empty($preselected_service) || !empty($preselected_plan) ? 'inline-flex' : 'none' ?>;
      background: rgba(212, 175, 55, 0.1);
      border: 1px solid rgba(212, 175, 55, 0.2);
      border-radius: 8px;
      padding: 6px 14px;
      font-size: 0.85rem;
      color: #d4af37;
      margin-bottom: 15px;
      align-items: center;
      gap: 8px;
    }
    .selected-plan-badge i {
      color: #d4af37;
    }
    .selected-plan-badge .plan-name {
      font-weight: 600;
    }

    /* Budget disabled state */
    .form-select-custom:disabled {
      opacity: 0.4;
      cursor: not-allowed;
    }
    .budget-disabled-text {
      color: #A5A5A5;
      font-size: 0.8rem;
      font-style: italic;
      margin-top: 0.3rem;
      display: none;
    }
    .budget-disabled-text.show {
      display: block;
    }
  </style>
</head>
<body>
  <?php include '3d-scene.php'; ?>

  <!-- LOADING SCREEN -->
  <div id="loader"><div class="loader-ring"></div><div class="loader-text">LUXORA</div><div class="loader-bar"><div class="loader-bar-fill"></div></div></div>

  <!-- BACK TO TOP -->
  <button id="backTop" class="back-top"><i class="fas fa-arrow-up"></i></button>

  <!-- NAVBAR -->
  <nav class="navbar" id="navbar">
    <a href="index.php" class="logo">LUXORA<span>.</span></a>
    <ul class="nav-links">
      <li><a href="index.php">Home</a></li>
      <li><a href="about.php">About</a></li>
      <li><a href="services.php">Services</a></li>
      <li><a href="pricing.php">Pricing</a></li>
      <li><a href="faq.php">FAQ</a></li>
      <li><a href="contact.php" class="active">Contact</a></li>
      
      <?php if (isset($_SESSION['user_id'])): ?>
        <li class="profile-dropdown" id="profileDropdown">
          <button class="dropdown-toggle" id="dropdownToggle" type="button" aria-haspopup="true" aria-expanded="false">
            <i class="fas fa-user-circle"></i>
            <?= htmlspecialchars($_SESSION['user_name']) ?>
            <i class="fas fa-chevron-down"></i>
          </button>
          <ul class="dropdown-menu" id="dropdownMenu" role="menu">
            <li><a href="profile.php" role="menuitem">
              <i class="fas fa-user"></i> View Profile
            </a></li>
            <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
            <li><a href="admin/dashboard.php" role="menuitem">
              <i class="fas fa-tachometer-alt"></i> Admin Dashboard
            </a></li>
            <?php endif; ?>
            <li class="dropdown-divider" role="separator"></li>
            <li><a href="logout.php" class="logout-item" role="menuitem">
              <i class="fas fa-sign-out-alt"></i> Logout
            </a></li>
          </ul>
        </li>
      <?php else: ?>
        <li class="auth-buttons">
          <a href="sign-in.php" class="btn-signin">
            <i class="fas fa-sign-in-alt me-2"></i> Sign In
          </a>
          <a href="sign-up.php" class="btn-signup">
            <i class="fas fa-user-plus me-2"></i> Sign Up
          </a>
        </li>
      <?php endif; ?>
    </ul>
    <button class="mobile-toggle" id="mobileToggle" aria-label="Toggle menu">
      <span></span><span></span><span></span>
    </button>
  </nav>

  <!-- MOBILE MENU -->
  <div class="mobile-menu" id="mobileMenu">
    <button class="mobile-close" id="mobileClose"><i class="fas fa-times"></i></button>
    <a href="index.php">Home</a>
    <a href="about.php">About</a>
    <a href="services.php">Services</a>
    <a href="pricing.php">Pricing</a>
    <a href="faq.php">FAQ</a>
    <a href="contact.php">Contact</a>
    
    <?php if (isset($_SESSION['user_id'])): ?>
      <div class="mobile-profile-btn">
        <a href="profile.php" class="profile-link">
          <i class="fas fa-user-circle me-2"></i> <?= htmlspecialchars($_SESSION['user_name']) ?>
        </a>
        <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
        <a href="admin/dashboard.php" class="profile-link" style="border-color: rgba(212,175,55,0.2);">
          <i class="fas fa-tachometer-alt me-2"></i> Admin Dashboard
        </a>
        <?php endif; ?>
        <a href="logout.php" class="logout-link">
          <i class="fas fa-sign-out-alt me-2"></i> Logout
        </a>
      </div>
    <?php else: ?>
      <div class="mobile-auth">
        <a href="sign-in.php" class="btn-signin">
          <i class="fas fa-sign-in-alt me-2"></i> Sign In
        </a>
        <a href="sign-up.php" class="btn-signup">
          <i class="fas fa-user-plus me-2"></i> Sign Up
        </a>
      </div>
    <?php endif; ?>
  </div>

  <!-- MAIN CONTENT -->
  <main>
    <!-- HERO -->
    <section class="d-flex align-items-center" style="min-height:40vh;padding-top:80px;background:radial-gradient(ellipse at 30% 40%, rgba(212,175,55,0.06), transparent 60%);">
      <div class="container">
        <div class="max-w-3xl fade-up visible">
          <span class="section-badge"><i class="fas fa-envelope me-1"></i> Contact</span>
          <h1 class="section-title">Let's <span class="gold">Connect</span></h1>
          <p class="text-secondary fs-5 mt-3">Have a project in mind? Or just want to share your thoughts? We'd love to hear from you.</p>
        </div>
      </div>
    </section>

    <!-- CONTACT FORM & INFO -->
    <section class="py-5">
      <div class="container">
        <div class="row g-5">
          <div class="col-lg-5 fade-up">
            <h3 class="fw-bold">Get in Touch</h3>
            <p class="text-secondary mt-2">Reach out via email, phone, or social media – we're here to help.</p>
            
            <div class="mt-4">
              <div class="contact-info-item">
                <div class="icon-box"><i class="fas fa-envelope"></i></div>
                <div class="info-text">
                  <?= htmlspecialchars($contactData['email']) ?>
                  <small>Email us anytime</small>
                </div>
              </div>
              
              <div class="contact-info-item">
                <div class="icon-box"><i class="fas fa-phone"></i></div>
                <div class="info-text">
                  <?= htmlspecialchars($contactData['phone']) ?>
                  <small>Call or WhatsApp</small>
                </div>
              </div>
              
              <div class="contact-info-item">
                <div class="icon-box"><i class="fas fa-map-marker-alt"></i></div>
                <div class="info-text">
                  <?= htmlspecialchars($contactData['address']) ?>
                  <small>Based in Karachi</small>
                </div>
              </div>
              
              <div class="contact-info-item">
                <div class="icon-box"><i class="fas fa-clock"></i></div>
                <div class="info-text">
                  <?= htmlspecialchars($contactData['hours']) ?>
                  <small>Always available for you</small>
                </div>
              </div>
              
              <div class="contact-info-item">
                <div class="icon-box"><i class="fab fa-instagram"></i></div>
                <div class="info-text">
                  <a href="<?= htmlspecialchars($contactData['instagram']) ?>" target="_blank" class="social-icon-link">
                    <i class="fab fa-instagram"></i> <?= htmlspecialchars($contactData['instagram_handle'] ?? 'luxoramedia_') ?>
                  </a>
                  <small>Follow us on Instagram</small>
                </div>
              </div>
            </div>
          </div>
          
          <div class="col-lg-7 fade-up delay-2">
            <div class="glass-card-gold p-4" id="contactFormCard">
              <h4 class="fw-bold mb-3">Send us a Message</h4>
              
              <!-- Selected Plan Badge -->
              <?php if (!empty($preselected_service) || !empty($preselected_plan)): ?>
              <div class="selected-plan-badge">
                <i class="fas fa-check-circle"></i>
                <span>Selected: <span class="plan-name"><?= htmlspecialchars($preselected_plan ? $preselected_plan . ' Plan' : $preselected_service) ?></span></span>
                <?php if (!empty($preselected_amount) && $preselected_amount > 0): ?>
                <span style="color: #A5A5A5; font-size: 0.75rem;">— $<?= number_format($preselected_amount, 2) ?></span>
                <?php endif; ?>
                <a href="contact.php" style="color: #ff4757; font-size: 0.7rem; text-decoration: none; margin-left: 4px;" title="Clear selection">
                  <i class="fas fa-times"></i>
                </a>
              </div>
              <?php endif; ?>
              
              <!-- Alert for non-AJAX submissions -->
              <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                  <i class="fas fa-check-circle me-2"></i> <?= $success_message ?>
                  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
              <?php endif; ?>
              <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                  <i class="fas fa-exclamation-circle me-2"></i> <?= $error_message ?>
                  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
              <?php endif; ?>
              
              <form id="contactForm" data-ajax="true">
                <div class="mb-3">
                  <label class="form-label"><i class="fas fa-user"></i> Full Name <span style="color:#ff4757;">*</span></label>
                  <input type="text" id="fullName" name="name" class="form-input" placeholder="John Doe" required />
                  <div class="error-message" id="nameError">
                    <i class="fas fa-exclamation-circle"></i> Please enter your full name
                  </div>
                </div>
                <div class="mb-3">
                  <label class="form-label"><i class="fas fa-envelope"></i> Email Address <span style="color:#ff4757;">*</span></label>
                  <input type="email" id="emailAddress" name="email" class="form-input" placeholder="Enter your email" required />
                  <div class="error-message" id="emailError">
                    <i class="fas fa-exclamation-circle"></i> Please enter a valid email address
                  </div>
                </div>
                <div class="mb-3">
                  <label class="form-label"><i class="fas fa-phone"></i> Phone Number</label>
                  <input type="tel" id="phoneNumber" name="phone" class="form-input" placeholder="+1 234 567 890" />
                </div>
                <div class="mb-3">
                  <label class="form-label"><i class="fas fa-tag"></i> Service Interest <span style="color:#ff4757;">*</span></label>
                  <select id="serviceInterest" name="service_type" class="form-select-custom" required onchange="toggleFields()">
                    <option value="">Select a service...</option>
                    <?php foreach ($allServiceOptions as $service): ?>
                      <option value="<?= htmlspecialchars($service) ?>" <?= $service_type_value === $service ? 'selected' : '' ?>>
                        <?= htmlspecialchars($service) ?>
                      </option>
                    <?php endforeach; ?>
                    <option value="Question" <?= $service_type_value === 'Question' ? 'selected' : '' ?>>
                      ❓ Question
                    </option>
                    <option value="Review" <?= $service_type_value === 'Review' ? 'selected' : '' ?>>
                      ⭐ Review
                    </option>
                  </select>
                  <div class="error-message" id="serviceError">
                    <i class="fas fa-exclamation-circle"></i> Please select a service
                  </div>
                </div>
                
                <!-- STAR RATING (only for Review) -->
                <div class="star-rating-wrapper" id="starRatingWrapper">
                  <div class="rating-label">
                    <i class="fas fa-star"></i> Rate Your Experience
                  </div>
                  <div class="star-rating" id="starRating">
                    <input type="radio" name="rating" id="star5" value="5" />
                    <label for="star5" title="5 stars">⭐</label>
                    <input type="radio" name="rating" id="star4" value="4" />
                    <label for="star4" title="4 stars">⭐</label>
                    <input type="radio" name="rating" id="star3" value="3" />
                    <label for="star3" title="3 stars">⭐</label>
                    <input type="radio" name="rating" id="star2" value="2" />
                    <label for="star2" title="2 stars">⭐</label>
                    <input type="radio" name="rating" id="star1" value="1" />
                    <label for="star1" title="1 star">⭐</label>
                    <span class="rating-text" id="ratingText">Select a rating</span>
                  </div>
                  <div class="rating-error" id="ratingError">
                    <i class="fas fa-exclamation-circle"></i> Please select a rating (1-5 stars)
                  </div>
                </div>
                
                <div class="mb-3">
                  <label class="form-label"><i class="fas fa-dollar-sign"></i> Budget Range</label>
                  <select id="budgetRange" name="budget" class="form-select-custom">
                    <option value="">Select budget range...</option>
                    <option value="Under $1,000" <?= ($preselected_amount > 0 && $preselected_amount < 1000) ? 'selected' : '' ?>>Under $1,000</option>
                    <option value="$1,000 - $5,000" <?= ($preselected_amount >= 1000 && $preselected_amount <= 5000) ? 'selected' : '' ?>>$1,000 - $5,000</option>
                    <option value="$5,000 - $10,000" <?= ($preselected_amount > 5000 && $preselected_amount <= 10000) ? 'selected' : '' ?>>$5,000 - $10,000</option>
                    <option value="$10,000 - $25,000" <?= ($preselected_amount > 10000 && $preselected_amount <= 25000) ? 'selected' : '' ?>>$10,000 - $25,000</option>
                    <option value="$25,000 - $50,000" <?= ($preselected_amount > 25000 && $preselected_amount <= 50000) ? 'selected' : '' ?>>$25,000 - $50,000</option>
                    <option value="$50,000+" <?= ($preselected_amount > 50000) ? 'selected' : '' ?>>$50,000+</option>
                  </select>
                  <div class="budget-disabled-text" id="budgetDisabledText">
                    <i class="fas fa-info-circle" style="color: #36a4eb;"></i> Budget is disabled for questions and reviews.
                  </div>
                </div>
                <div class="mb-3">
                  <label class="form-label"><i class="fas fa-comment"></i> <span id="messageLabelText">Your Message</span> <span style="color:#ff4757;">*</span></label>
                  <textarea id="messageText" name="message" rows="4" class="form-input" placeholder="Tell us about your project..." required></textarea>
                  <div class="error-message" id="messageError">
                    <i class="fas fa-exclamation-circle"></i> Please enter a message (at least 10 characters)
                  </div>
                </div>
                
                <!-- Hidden fields for plan details -->
                <input type="hidden" name="plan" id="planField" value="<?= htmlspecialchars($preselected_plan) ?>">
                <input type="hidden" name="amount" id="amountField" value="<?= htmlspecialchars($preselected_amount) ?>">
                
                <button type="submit" id="submitBtn" class="btn-primary-custom w-100 justify-content-center">
                  <span id="btnText"><i class="fas fa-paper-plane me-2"></i> Send Message</span>
                  <span id="btnLoader" style="display: none;"><i class="fas fa-spinner fa-spin me-2"></i> Sending...</span>
                </button>
              </form>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- QUICK FAQ - Dynamic from database -->
    <section class="py-5 bg-card/30">
      <div class="container">
        <div class="text-center fade-up">
          <span class="section-badge"><i class="fas fa-question-circle me-1"></i> Quick FAQ</span>
          <h2 class="section-title">Common <span class="gold">Questions</span></h2>
        </div>
        <?php
        // Fetch quick FAQs from database
        $quickFaqs = [];
        try {
            $stmt = $db->query("SELECT question, answer FROM faqs WHERE is_visible = 1 AND is_featured = 1 ORDER BY display_order ASC LIMIT 3");
            $quickFaqs = $stmt->fetchAll();
        } catch (PDOException $e) {
            $quickFaqs = [];
        }
        
        // Fallback quick FAQs
        if (empty($quickFaqs)) {
            $quickFaqs = [
                ['question' => 'How quickly do you respond?', 'answer' => 'We reply within 24 hours, usually much faster.'],
                ['question' => 'Do you offer free consultations?', 'answer' => 'Yes – we offer a free 30‑minute consultation for all new clients.'],
                ['question' => 'What happens after I send a message?', 'answer' => 'We\'ll review your inquiry and reach out to schedule a call or meeting.']
            ];
        }
        ?>
        <div class="row justify-content-center mt-3">
          <div class="col-lg-8">
            <?php foreach ($quickFaqs as $faq): ?>
              <div class="faq-item">
                <div class="faq-q">
                  <span><?= htmlspecialchars($faq['question']) ?></span>
                  <span class="faq-icon"><i class="fas fa-plus"></i></span>
                </div>
                <div class="faq-a"><?= htmlspecialchars($faq['answer']) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section class="py-5 position-relative overflow-hidden">
      <div class="position-absolute top-0 start-0 w-100 h-100 bg-primary/5 blur-3xl"></div>
      <div class="container position-relative z-1">
        <div class="glass-gold rounded-4 p-5 text-center max-w-4xl mx-auto fade-up">
          <h2 class="section-title" style="font-size:clamp(2rem,4vw,3.5rem);">Ready to Build Something <span class="gold">Amazing</span>?</h2>
          <p class="text-secondary fs-5 mt-3">Let's create digital experiences that drive growth and leave a lasting impression.</p>
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
      <div class="border-top border-white/5 mt-4 pt-3 d-flex flex-wrap justify-content-between small text-secondary"><span><i class="far fa-copyright me-1"></i> 2026 Luxora Media. All Rights Reserved.</span><span>Made with <i class="fas fa-heart text-gold"></i> in Karachi</span></div>
    </div>
  </footer>

  <!-- Toast Notification -->
  <div id="toast" class="toast-notification"><i class="fas fa-check-circle me-2"></i> Message sent! We'll <span class="gold">get back to you</span> soon.</div>
  
  <!-- ===== SUCCESS POP-UP MODAL ===== -->
  <div class="success-modal-overlay" id="successModal">
    <div class="success-modal">
      <div class="success-icon">
        <i class="fas fa-check-circle"></i>
      </div>
      <div class="success-title">Message Sent Successfully!</div>
      <div class="success-text" id="successMessage">
        Thank you for your inquiry! We will get back to you within 24 hours.
      </div>
      <div class="success-details">
        <div class="detail-item">
          <span class="detail-label">Project ID</span>
          <span class="detail-value" id="projectIdDisplay">—</span>
        </div>
        <div class="detail-item">
          <span class="detail-label">Status</span>
          <span class="detail-value status-badge">Pending Review</span>
        </div>
      </div>
      <button class="success-btn" onclick="closeSuccessModal()">
        <i class="fas fa-check me-2"></i> Got It
      </button>
    </div>
  </div>

  <?php if (isset($success_message)): ?>
  <script>
    // Show success modal on page load for non-AJAX submissions
    document.addEventListener('DOMContentLoaded', function() {
      setTimeout(function() {
        showSuccessModal('<?= $project_id ?? '' ?>', '<?= addslashes($success_message) ?>');
      }, 500);
    });
  </script>
  <?php endif; ?>
  
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="luxora.js"></script>
  
<script>
// ===== SUCCESS POP-UP MODAL =====
function showSuccessModal(projectId, message) {
  var modal = document.getElementById('successModal');
  var messageEl = document.getElementById('successMessage');
  var projectIdEl = document.getElementById('projectIdDisplay');
  
  if (!modal) {
    console.error('Success modal not found!');
    return;
  }
  
  // Set the message
  if (messageEl && message) {
    messageEl.textContent = message;
  }
  
  // Set project ID
  if (projectIdEl) {
    projectIdEl.textContent = projectId ? '#' + projectId : '—';
  }
  
  // Show modal
  modal.classList.add('show');
  document.body.style.overflow = 'hidden';
}

function closeSuccessModal() {
  var modal = document.getElementById('successModal');
  if (modal) {
    modal.classList.remove('show');
    document.body.style.overflow = '';
  }
}

// Close on overlay click
document.addEventListener('DOMContentLoaded', function() {
  var modal = document.getElementById('successModal');
  if (modal) {
    modal.addEventListener('click', function(e) {
      if (e.target === this) {
        closeSuccessModal();
      }
    });
  }
  
  // Close on Escape key
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      var modal = document.getElementById('successModal');
      if (modal && modal.classList.contains('show')) {
        closeSuccessModal();
      }
    }
  });

  // ===== DISABLE 3D CARD TILT EFFECT ON CONTACT FORM =====
  var contactCard = document.getElementById('contactFormCard');
  if (contactCard) {
    var newCard = contactCard.cloneNode(true);
    contactCard.parentNode.replaceChild(newCard, contactCard);
  }

  // ===== FORM VALIDATION =====
  var form = document.getElementById('contactForm');
  if (!form) return;

  var fullName = document.getElementById('fullName');
  var emailAddress = document.getElementById('emailAddress');
  var phoneNumber = document.getElementById('phoneNumber');
  var serviceInterest = document.getElementById('serviceInterest');
  var budgetRange = document.getElementById('budgetRange');
  var messageText = document.getElementById('messageText');
  var submitBtn = document.getElementById('submitBtn');
  var btnText = document.getElementById('btnText');
  var btnLoader = document.getElementById('btnLoader');
  
  var nameError = document.getElementById('nameError');
  var emailError = document.getElementById('emailError');
  var serviceError = document.getElementById('serviceError');
  var messageError = document.getElementById('messageError');
  var ratingError = document.getElementById('ratingError');
  
  // Star rating
  var ratingInputs = document.querySelectorAll('input[name="rating"]');
  var ratingText = document.getElementById('ratingText');
  var starWrapper = document.getElementById('starRatingWrapper');
  
  // Rating labels
  var ratingLabels = {
    1: 'Terrible',
    2: 'Poor',
    3: 'Average',
    4: 'Good',
    5: 'Excellent!'
  };
  
  ratingInputs.forEach(function(input) {
    input.addEventListener('change', function() {
      var val = parseInt(this.value);
      if (ratingText) {
        ratingText.textContent = val + ' stars - ' + (ratingLabels[val] || '');
      }
      if (ratingError) {
        ratingError.classList.remove('show');
      }
    });
  });
  
  function validateRating() {
    var selected = document.querySelector('input[name="rating"]:checked');
    if (!selected && serviceInterest.value === 'Review') {
      if (ratingError) ratingError.classList.add('show');
      return false;
    } else {
      if (ratingError) ratingError.classList.remove('show');
      return true;
    }
  }
  
  if (fullName) {
    fullName.addEventListener('input', function() { validateName(); });
  }
  if (emailAddress) {
    emailAddress.addEventListener('input', function() { validateEmail(); });
  }
  if (serviceInterest) {
    serviceInterest.addEventListener('change', function() { 
      validateService(); 
      toggleFields();
    });
  }
  if (messageText) {
    messageText.addEventListener('input', function() { validateMessage(); });
  }
  
  function validateName() {
    if (!fullName) return false;
    var name = fullName.value.trim();
    if (name.length < 2) {
      fullName.classList.add('error');
      fullName.classList.remove('success');
      if (nameError) nameError.classList.add('show');
      return false;
    } else {
      fullName.classList.remove('error');
      fullName.classList.add('success');
      if (nameError) nameError.classList.remove('show');
      return true;
    }
  }
  
  function validateEmail() {
    if (!emailAddress) return false;
    var email = emailAddress.value.trim();
    var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailPattern.test(email)) {
      emailAddress.classList.add('error');
      emailAddress.classList.remove('success');
      if (emailError) emailError.classList.add('show');
      return false;
    } else {
      emailAddress.classList.remove('error');
      emailAddress.classList.add('success');
      if (emailError) emailError.classList.remove('show');
      return true;
    }
  }
  
  function validateService() {
    if (!serviceInterest) return false;
    if (serviceInterest.value === '') {
      serviceInterest.classList.add('error');
      serviceInterest.classList.remove('success');
      if (serviceError) serviceError.classList.add('show');
      return false;
    } else {
      serviceInterest.classList.remove('error');
      serviceInterest.classList.add('success');
      if (serviceError) serviceError.classList.remove('show');
      return true;
    }
  }
  
  function validateMessage() {
    if (!messageText) return false;
    var message = messageText.value.trim();
    if (message.length < 10) {
      messageText.classList.add('error');
      messageText.classList.remove('success');
      if (messageError) messageError.classList.add('show');
      return false;
    } else {
      messageText.classList.remove('error');
      messageText.classList.add('success');
      if (messageError) messageError.classList.remove('show');
      return true;
    }
  }
  
  // Show toast notification
  window.showToast = function(message, type) {
    var toast = document.getElementById('toast');
    if (toast) {
      toast.innerHTML = message;
      toast.className = 'toast-notification';
      if (type === 'error') {
        toast.style.borderColor = 'rgba(255,71,87,0.3)';
        var icon = toast.querySelector('i');
        if (icon) {
          icon.className = 'fas fa-exclamation-circle me-2';
          icon.style.color = '#ff4757';
        }
      } else {
        toast.style.borderColor = 'rgba(46,213,115,0.3)';
        var icon = toast.querySelector('i');
        if (icon) {
          icon.className = 'fas fa-check-circle me-2';
          icon.style.color = '#2ed573';
        }
      }
      toast.classList.add('show');
      setTimeout(function() {
        toast.classList.remove('show');
        setTimeout(function() {
          toast.innerHTML = '<i class="fas fa-check-circle me-2"></i> Message sent! We\'ll <span class="gold">get back to you</span> soon.';
          toast.style.borderColor = 'rgba(212,175,55,0.2)';
          var icon = toast.querySelector('i');
          if (icon) {
            icon.className = 'fas fa-check-circle me-2';
            icon.style.color = '#2ed573';
          }
        }, 300);
      }, 5000);
    }
  };
  
  // ===== TOGGLE FIELDS =====
  function toggleFields() {
    var serviceSelect = document.getElementById('serviceInterest');
    var budgetSelect = document.getElementById('budgetRange');
    var budgetDisabledText = document.getElementById('budgetDisabledText');
    var messageText = document.getElementById('messageText');
    var messageLabel = document.getElementById('messageLabelText');
    var starWrapper = document.getElementById('starRatingWrapper');
    var ratingInputs = document.querySelectorAll('input[name="rating"]');
    var amountField = document.getElementById('amountField');
    var planField = document.getElementById('planField');
    
    if (!serviceSelect) return;
    
    var selectedValue = serviceSelect.value;
    var isReview = selectedValue === 'Review';
    var isQuestion = selectedValue === 'Question';
    var isService = !isReview && !isQuestion && selectedValue !== '';
    
    // Show/hide star rating
    if (starWrapper) {
      if (isReview) {
        starWrapper.classList.add('show');
        // Reset rating selection
        ratingInputs.forEach(function(input) {
          input.checked = false;
        });
        if (ratingText) ratingText.textContent = 'Select a rating';
      } else {
        starWrapper.classList.remove('show');
      }
    }
    
    // Disable budget for reviews and questions
    if (isReview || isQuestion) {
      if (budgetSelect) {
        budgetSelect.disabled = true;
        budgetSelect.value = '';
      }
      if (budgetDisabledText) budgetDisabledText.classList.add('show');
    } else {
      if (budgetSelect) {
        budgetSelect.disabled = false;
        // Reset to default if it was auto-selected from a plan
        if (!isService) budgetSelect.value = '';
      }
      if (budgetDisabledText) budgetDisabledText.classList.remove('show');
    }
    
    // Update message placeholder and label
    if (messageText) {
      if (isReview) {
        messageText.placeholder = 'Share your experience with us... What did you like?';
      } else if (isQuestion) {
        messageText.placeholder = 'What would you like to ask? We\'re here to help!';
      } else {
        messageText.placeholder = 'Tell us about your project...';
      }
    }
    
    if (messageLabel) {
      if (isReview) {
        messageLabel.textContent = 'Your Review';
      } else if (isQuestion) {
        messageLabel.textContent = 'Your Question';
      } else {
        messageLabel.textContent = 'Project Description';
      }
    }
    
    // Reset amount for review/question
    if (amountField) {
      if (isReview || isQuestion) {
        amountField.value = 0;
      } else {
        // Handle plan amounts
        var planAmounts = {
          'Starter Plan': 250,
          'Business Plan': 500,
          'Pro Plan': 1000
        };
        if (planAmounts[selectedValue]) {
          amountField.value = planAmounts[selectedValue];
          if (planField) planField.value = selectedValue.replace(' Plan', '');
        }
      }
    }
  }
  
  // ===== AJAX FORM SUBMISSION =====
  form.addEventListener('submit', function(e) {
    e.preventDefault();
    
    var isNameValid = validateName();
    var isEmailValid = validateEmail();
    var isServiceValid = validateService();
    var isMessageValid = validateMessage();
    var isRatingValid = validateRating();
    
    // Check rating if Review is selected
    if (serviceInterest.value === 'Review') {
      isRatingValid = validateRating();
    } else {
      isRatingValid = true;
    }
    
    if (isNameValid && isEmailValid && isServiceValid && isMessageValid && isRatingValid) {
      // Show loading state
      btnText.style.display = 'none';
      btnLoader.style.display = 'inline';
      submitBtn.disabled = true;
      submitBtn.classList.add('loading');
      
      // Prepare form data
      var formData = new FormData();
      formData.append('ajax', '1');
      formData.append('name', fullName.value.trim());
      formData.append('email', emailAddress.value.trim());
      formData.append('phone', phoneNumber ? phoneNumber.value.trim() : '');
      formData.append('service_type', serviceInterest.value);
      formData.append('budget', budgetRange ? budgetRange.value : '');
      formData.append('message', messageText.value.trim());
      
      // Add rating if review
      if (serviceInterest.value === 'Review') {
        var selectedRating = document.querySelector('input[name="rating"]:checked');
        if (selectedRating) {
          formData.append('rating', selectedRating.value);
        }
      }
      
      // Add plan and amount if present
      var planField = document.getElementById('planField');
      var amountField = document.getElementById('amountField');
      if (planField) formData.append('plan', planField.value);
      if (amountField) formData.append('amount', amountField.value);
      
      // Send AJAX request
      fetch('contact.php', {
        method: 'POST',
        body: formData
      })
      .then(function(response) {
        if (!response.ok) {
          throw new Error('Network response was not ok');
        }
        return response.json();
      })
      .then(function(data) {
        // Reset loading state
        btnText.style.display = 'inline';
        btnLoader.style.display = 'none';
        submitBtn.disabled = false;
        submitBtn.classList.remove('loading');
        
        if (data.success) {
          // Reset form
          form.reset();
          var elements = document.querySelectorAll('.form-input, .form-select-custom');
          elements.forEach(function(el) {
            el.classList.remove('success', 'error');
          });
          // Reset rating
          document.querySelectorAll('input[name="rating"]').forEach(function(input) {
            input.checked = false;
          });
          if (ratingText) ratingText.textContent = 'Select a rating';
          toggleFields();
          
          // Show success modal with project ID
          showSuccessModal(data.project_id, data.message);
        } else {
          // Show error message
          showToast('✗ ' + data.message, 'error');
        }
      })
      .catch(function(error) {
        btnText.style.display = 'inline';
        btnLoader.style.display = 'none';
        submitBtn.disabled = false;
        submitBtn.classList.remove('loading');
        showToast('✗ ' + error.message + '. Please try again.', 'error');
        console.error('Error:', error);
      });
      
    } else {
      if (!isNameValid && fullName) { fullName.focus(); }
      else if (!isEmailValid && emailAddress) { emailAddress.focus(); }
      else if (!isServiceValid && serviceInterest) { serviceInterest.focus(); }
      else if (!isMessageValid && messageText) { messageText.focus(); }
      else if (!isRatingValid) { 
        var firstStar = document.querySelector('input[name="rating"]');
        if (firstStar) firstStar.focus();
      }
    }
  });
  
  // Initialize toggle on page load
  toggleFields();
});

// ===== Toggle Budget & AUTO-SELECT BUDGET FOR PLANS =====
function toggleBudgetField() {
  // This is now handled by toggleFields()
  toggleFields();
}
</script>
  
  <!-- Dropdown JavaScript -->
  <script>
  (function() {
    'use strict';
    
    function initDropdown() {
      var dropdown = document.getElementById('profileDropdown');
      if (!dropdown) return;
      
      var toggle = dropdown.querySelector('.dropdown-toggle');
      var menu = dropdown.querySelector('.dropdown-menu');
      if (!toggle || !menu) return;
      
      toggle.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        dropdown.classList.toggle('active');
        toggle.setAttribute('aria-expanded', dropdown.classList.contains('active'));
      });
      
      document.addEventListener('click', function(e) {
        if (!dropdown.contains(e.target)) {
          dropdown.classList.remove('active');
          toggle.setAttribute('aria-expanded', 'false');
        }
      });
      
      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && dropdown.classList.contains('active')) {
          dropdown.classList.remove('active');
          toggle.setAttribute('aria-expanded', 'false');
          toggle.focus();
        }
      });
      
      var menuItems = menu.querySelectorAll('a');
      menuItems.forEach(function(item) {
        item.addEventListener('click', function() {
          setTimeout(function() {
            dropdown.classList.remove('active');
            toggle.setAttribute('aria-expanded', 'false');
          }, 100);
        });
      });
    }
    
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', initDropdown);
    } else {
      initDropdown();
    }
  })();
  </script>
</body>
</html>