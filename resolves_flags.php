<?php
session_start();
require 'config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['flag_id'])) {
    $flag_id = intval($_POST['flag_id']);
    $stmt = $conn->prepare("UPDATE verification_flags SET status = 'resolved' WHERE flag_id = ?");
    $stmt->bind_param("i", $flag_id);
    $stmt->execute();
}
header("Location: admin_review.php");
exit;
?>
