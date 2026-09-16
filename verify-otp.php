<?php
session_start();
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$otp = trim($_POST['otp'] ?? '');

if (empty($otp) || strlen($otp) !== 6) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid 6-digit OTP']);
    exit;
}

try {
    $db = getDB();
    
    // Check OTP
    $stmt = $db->prepare("SELECT id, expires_at, used FROM password_resets WHERE user_id = ? AND otp = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$user_id, $otp]);
    $record = $stmt->fetch();
    
    if (!$record) {
        echo json_encode(['success' => false, 'message' => 'Invalid OTP code']);
        exit;
    }
    
    if ($record['used']) {
        echo json_encode(['success' => false, 'message' => 'This OTP has already been used']);
        exit;
    }
    
    if (strtotime($record['expires_at']) < time()) {
        echo json_encode(['success' => false, 'message' => 'OTP has expired. Please request a new one']);
        exit;
    }
    
    // Mark OTP as used
    $stmt = $db->prepare("UPDATE password_resets SET used = TRUE WHERE id = ?");
    $stmt->execute([$record['id']]);
    
    // Set session as verified
    $_SESSION['otp_verified'] = true;
    $_SESSION['otp_user_id'] = $user_id;
    
    echo json_encode(['success' => true, 'message' => 'OTP verified successfully']);
    
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
?>