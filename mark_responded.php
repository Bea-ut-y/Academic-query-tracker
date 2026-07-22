<?php
session_start();
require 'config.php';

// Ensure only lecturer or admin can mark as responded
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['lecturer', 'admin'])) {
    header("Location: dashboard.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['query_id'])) {
    $query_id = intval($_POST['query_id']);

    $stmt = $conn->prepare("UPDATE queries SET status = 'responded' WHERE query_id = ?");
    $stmt->bind_param("i", $query_id);

    if ($stmt->execute()) {
        // success message and redirect
        $_SESSION['message'] = "Query marked as responded!";
    } else {
        $_SESSION['message'] = "❌ Error updating query status.";
    }

    // Redirect back to the lecturer dashboard
    header("Location: lecturer_dashboard.php");
    exit;
}
?>
