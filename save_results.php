<?php
session_start();
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit;
}

$student_id = intval($_POST['student_id']);
$course_id  = intval($_POST['course_id']);
$ca = intval($_POST['ca_score']);
$exam = intval($_POST['exam_score']);
$total = $ca + $exam;

// insert result
$stmt = $conn->prepare("INSERT INTO results (student_id, course_id, ca_score, exam_score, total_score) VALUES (?,?,?,?,?)");
$stmt->bind_param("iiiii", $student_id, $course_id, $ca, $exam, $total);

if ($stmt->execute()) {
    $result_id = $conn->insert_id;
// Simple verification rules (prototype)
$issue = null;

if ($exam === 0) {
    $issue = "Missing exam score";
} elseif ($ca === 0) {
    $issue = "Missing CA score";
} elseif ($ca >= 30 && $exam <= 25) {
    $issue = "High CA, low exam score";
} elseif ($total < 40) {
    $issue = "Low total score";
}

    if ($issue) {
        $stmt2 = $conn->prepare("INSERT INTO verification_flags (result_id, issue_description) VALUES (?, ?)");
        $stmt2->bind_param("is", $result_id, $issue);
        $stmt2->execute();
    }

    header("Location: dashboard.php?msg=Result saved");
    exit;
} else {
    echo "Error saving result: " . $conn->error;
}
?>
