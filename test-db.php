<?php
require_once __DIR__ . '/db.php';

try {
    $db = getDB();
    $stmt = $db->query("SELECT 1");
    echo "✅ Database connection successful!";
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage();
}
?>