<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit;
}

$msg = "";

/* 📨 Forward Student Query to Lecturer */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forward_query_id'], $_POST['forward_message'])) {
    $query_id = intval($_POST['forward_query_id']);
    $forward_message = trim($_POST['forward_message']);

    // Step 1: Get result_id of the student query
    $stmt = $conn->prepare("SELECT result_id FROM queries WHERE query_id=? AND sender_role='student'");
    $stmt->bind_param("i", $query_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    if ($res) {
        $result_id = $res['result_id'];

        // ✅ Step 2: Save the forwarded query correctly linked by result_id
        $stmt2 = $conn->prepare("
            INSERT INTO queries (result_id, sender_role, receiver_role, message, status)
            VALUES (?, 'admin', 'lecturer', ?, 'forwarded')
        ");
        $stmt2->bind_param("is", $result_id, $forward_message);
        $stmt2->execute();
        $stmt2->close();

        // Step 3: Update original student query status
        // ✅ Update both the original query and any linked student query
$update = $conn->prepare("
    UPDATE queries 
    SET status='responded' 
    WHERE query_id = ? OR (
        result_id = (SELECT result_id FROM queries WHERE query_id = ?) 
        AND sender_role = 'student'
    )
");
$update->bind_param("ii", $query_id, $query_id);
$update->execute();
$update->close();


        $msg = "✅ Query forwarded to lecturer successfully.";
    } else {
        $msg = "❌ Original student query not found!";
    }
    $stmt->close();
}

/* 💬 Reply to Student After Lecturer Responds */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_query_id'], $_POST['reply_message'])) {
    $query_id = intval($_POST['reply_query_id']);
    $reply_message = trim($_POST['reply_message']);

    $stmt = $conn->prepare("SELECT result_id FROM queries WHERE query_id=?");
    $stmt->bind_param("i", $query_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $result_id = $res['result_id'];

    $stmt2 = $conn->prepare("
        INSERT INTO queries (result_id, sender_role, receiver_role, message, status)
        VALUES (?, 'admin', 'student', ?, 'responded')
    ");
    $stmt2->bind_param("is", $result_id, $reply_message);
    $stmt2->execute();
    $stmt2->close();

    $update = $conn->prepare("UPDATE queries SET status='responded' WHERE query_id=?");
    $update->bind_param("i", $query_id);
    $update->execute();
    $update->close();

    $msg = "✅ Reply sent to student successfully.";
}

/* 📥 Fetch Pending Student Queries */
$pending_student = $conn->query("
    SELECT q.query_id, q.message, q.created_at, c.course_name, u.name AS student_name
    FROM queries q
    JOIN results r ON q.result_id = r.result_id
    JOIN courses c ON r.course_id = c.course_id
    JOIN users u ON r.student_id = u.user_id
    WHERE q.sender_role='student' AND q.status='open'
    ORDER BY q.created_at DESC
");

/* 📤 Fetch Lecturer Replies (to send back to student) */
$lecturer_replies = $conn->query("
    SELECT q.query_id, q.message, q.created_at, c.course_name, u.name AS student_name
    FROM queries q
    JOIN results r ON q.result_id = r.result_id
    JOIN courses c ON r.course_id = c.course_id
    JOIN users u ON r.student_id = u.user_id
    WHERE q.sender_role='lecturer' AND q.receiver_role='admin' AND q.status='responded'
    ORDER BY q.created_at DESC
");

/* ✅ Fetch Completed Queries */
$responded_queries = $conn->query("
    SELECT s.query_id, s.message AS student_message, a.message AS admin_message,
           c.course_name, u.name AS student_name, s.created_at AS query_date, a.created_at AS reply_date
    FROM queries s
    LEFT JOIN queries a 
        ON a.result_id = s.result_id AND a.sender_role='admin' AND a.receiver_role='student'
    JOIN results r ON s.result_id = r.result_id
    JOIN courses c ON r.course_id = c.course_id
    JOIN users u ON r.student_id = u.user_id
    WHERE s.sender_role='student' AND s.status='responded'
    ORDER BY s.created_at DESC
");

/* 🟩 Responded Queries Overview */
$responded_overview = $conn->query("
    SELECT * FROM queries WHERE status = 'responded' ORDER BY created_at DESC
");
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Admin Queries Dashboard</title>
<style>
body {
    font-family: "Segoe UI", Arial, sans-serif;
    background: #f3f6fa;
    color: #333;
    padding: 20px;
}
.card {
    background: #e8f0ff;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.1);
    margin-bottom: 40px;
}
h2, h3 {
    color: #003366;
}
table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 25px;
}
th, td {
    padding: 10px 12px;
    border-bottom: 1px solid #ccc;
    text-align: left;
}
th {
    background: #003366;
    color: #fff;
}
tr:hover {
    background: #cce0ff;
}
button {
    background: #003366;
    color: white;
    padding: 6px 12px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
}
button:hover {
    background: #0059b3;
}
textarea {
    width: 100%;
    padding: 8px;
    border-radius: 6px;
    border: 1px solid #ccc;
    margin-bottom: 8px;
}
a {
    color: #003366;
    text-decoration: none;
    font-weight: 500;
}
a:hover {
    text-decoration: underline;
}
.responded-box {
    background: #d9fdd3;
    border: 1px solid #b6e6a8;
    padding: 12px;
    border-radius: 10px;
    margin-bottom: 10px;
}
</style>
</head>
<body>

<div class="card">
<h2>Admin Dashboard — Query Management</h2>
<?php if($msg) echo "<p style='color:green;'>$msg</p>"; ?>

<h3>Pending Student Queries (Forward to Lecturer)</h3>
<?php if($pending_student->num_rows > 0): ?>
<table>
<tr><th>Course</th><th>Student</th><th>Query</th><th>Date</th><th>Action</th></tr>
<?php while($q = $pending_student->fetch_assoc()): ?>
<tr>
<td><?= htmlspecialchars($q['course_name']) ?></td>
<td><?= htmlspecialchars($q['student_name']) ?></td>
<td><?= htmlspecialchars($q['message']) ?></td>
<td><?= $q['created_at'] ?></td>
<td>
<form method="post">
<input type="hidden" name="forward_query_id" value="<?= $q['query_id'] ?>">
<textarea name="forward_message" placeholder="Message to Lecturer..." required></textarea>
<button type="submit">Forward</button>
</form>
</td>
</tr>
<?php endwhile; ?>
</table>
<?php else: ?>
<p>No pending student queries.</p>
<?php endif; ?>

<h3>Lecturer Replies (Reply to Student)</h3>
<?php if($lecturer_replies->num_rows > 0): ?>
<table>
<tr><th>Course</th><th>Student</th><th>Lecturer Message</th><th>Date</th><th>Action</th></tr>
<?php while($q = $lecturer_replies->fetch_assoc()): ?>
<tr>
<td><?= htmlspecialchars($q['course_name']) ?></td>
<td><?= htmlspecialchars($q['student_name']) ?></td>
<td><?= htmlspecialchars($q['message']) ?></td>
<td><?= $q['created_at'] ?></td>
<td>
<form method="post">
<input type="hidden" name="reply_query_id" value="<?= $q['query_id'] ?>">
<textarea name="reply_message" placeholder="Reply to Student..." required></textarea>
<button type="submit">Send Reply</button>
</form>
</td>
</tr>
<?php endwhile; ?>
</table>
<?php else: ?>
<p>No lecturer replies yet.</p>
<?php endif; ?>

<h3>Completed Queries</h3>
<?php if($responded_queries->num_rows > 0): ?>
<table>
<tr><th>Course</th><th>Student</th><th>Original Query</th><th>Admin Reply</th><th>Query Date</th><th>Reply Date</th></tr>
<?php while($q = $responded_queries->fetch_assoc()): ?>
<tr>
<td><?= htmlspecialchars($q['course_name']) ?></td>
<td><?= htmlspecialchars($q['student_name']) ?></td>
<td><?= htmlspecialchars($q['student_message']) ?></td>
<td><?= htmlspecialchars($q['admin_message'] ?? '') ?></td>
<td><?= $q['query_date'] ?></td>
<td><?= $q['reply_date'] ?? '' ?></td>
</tr>
<?php endwhile; ?>
</table>
<?php else: ?>
<p>No completed queries yet.</p>
<?php endif; ?>

<h3>Responded Queries (All)</h3>
<?php
if ($responded_overview->num_rows > 0) {
    while ($q = $responded_overview->fetch_assoc()) {
        echo "<div class='responded-box'>";
        echo "<strong>Message:</strong> " . htmlspecialchars($q['message']) . "<br>";
        echo "<strong>From:</strong> " . htmlspecialchars($q['sender_role']) . "<br>";
        echo "<strong>Status:</strong> " . htmlspecialchars($q['status']) . "<br>";
        echo "<small><em>Sent on: " . htmlspecialchars($q['created_at']) . "</em></small>";
        echo "</div>";
    }
} else {
    echo "<p>No responded queries yet.</p>";
}
?>

<p><a href="dashboard.php">← Back to Dashboard</a></p>
</div>

</body>
</html>
