<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lecturer') {
    header("Location: dashboard.php");
    exit;
}

$msg = "";

/* Handle Lecturer Reply to Admin */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_query_id'], $_POST['reply_message'])) {
    $forward_id = intval($_POST['reply_query_id']);
    $reply_message = trim($_POST['reply_message']);

    // Get the result_id of the forwarded query
    $stmt = $conn->prepare("SELECT result_id FROM queries WHERE query_id=? AND receiver_role='lecturer'");
    $stmt->bind_param("i", $forward_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    if ($res) {
        $result_id = $res['result_id'];

        // Insert reply to admin
        $stmt2 = $conn->prepare("
            INSERT INTO queries (result_id, sender_role, receiver_role, message, status)
            VALUES (?, 'lecturer', 'admin', ?, 'responded')
        ");
        $stmt2->bind_param("is", $result_id, $reply_message);
        $stmt2->execute();

        $msg = "✅ Reply sent to admin successfully!";
    } else {
        $msg = "⚠️ Could not find forwarded query data!";
    }
}

/* Handle Mark as Responded */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_query_id'])) {
    $query_id = intval($_POST['mark_query_id']);
    $stmt = $conn->prepare("UPDATE queries SET status = 'responded' WHERE query_id = ?");
    $stmt->bind_param("i", $query_id);
    if ($stmt->execute()) {
        $msg = "✅ Query marked as responded!";
    } else {
        $msg = "❌ Error marking query as responded.";
    }
}

/* Fetch all queries forwarded to lecturer */
$forwarded_queries = $conn->query("
    SELECT f.query_id AS forward_id,
           f.message AS forwarded_message,
           f.created_at AS forward_date,
           s.message AS student_message,
           u.name AS student_name,
           c.course_name
    FROM queries f
    JOIN results r ON r.result_id = f.result_id
    JOIN users u ON r.student_id = u.user_id
    JOIN courses c ON r.course_id = c.course_id
    JOIN queries s ON s.result_id = f.result_id AND s.sender_role='student'
    WHERE f.sender_role='admin' AND f.receiver_role='lecturer' AND f.status IN ('open','forwarded')
    ORDER BY f.created_at DESC
");
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Lecturer Dashboard — Forwarded Queries</title>
<style>
body { font-family: Arial, sans-serif; background: #f0f4f8; color: #333; padding: 20px; }
.card { background: #e6f0ff; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 30px; }
h2, h3 { color: #004080; }
table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
th, td { padding: 10px; border-bottom: 1px solid #ccc; text-align: left; }
th { background: #004080; color: white; }
tr:hover { background: #cce0ff; }
button { background: #004080; color: white; padding: 6px 12px; border: none; border-radius: 5px; cursor: pointer; margin-top:5px; }
button:hover { background: #0059b3; }
textarea { width: 100%; padding: 6px; border-radius: 5px; border: 1px solid #ccc; resize: vertical; }
.success { color: green; font-weight: bold; }
.error { color: red; font-weight: bold; }
a { color: #004080; text-decoration: none; }
a:hover { text-decoration: underline; }
.query-box { background: white; border: 1px solid #ccc; border-radius: 8px; padding: 10px; margin-bottom: 10px; }
</style>
</head>
<body>
<div class="card">
<h2>Lecturer Dashboard — Forwarded Queries</h2>
<?php if($msg) echo "<p class='success'>$msg</p>"; ?>

<?php if($forwarded_queries && $forwarded_queries->num_rows > 0): ?>
<table>
<tr><th>Course</th><th>Student</th><th>Student Query</th><th>Admin Message</th><th>Date</th><th>Action</th></tr>
<?php while($q = $forwarded_queries->fetch_assoc()): ?>
<tr>
<td><?= htmlspecialchars($q['course_name']) ?></td>
<td><?= htmlspecialchars($q['student_name']) ?></td>
<td><?= htmlspecialchars($q['student_message']) ?></td>
<td><?= htmlspecialchars($q['forwarded_message']) ?></td>
<td><?= $q['forward_date'] ?></td>
<td>
    <!-- Reply Form -->
    <form method="post" style="margin-bottom:10px;">
        <input type="hidden" name="reply_query_id" value="<?= $q['forward_id'] ?>">
        <textarea name="reply_message" placeholder="Reply to Admin..." required></textarea>
        <button type="submit">Send Reply</button>
    </form>

    <!-- Mark as Responded Button -->
    <form method="post" style="margin-top:5px;">
        <input type="hidden" name="mark_query_id" value="<?= $q['forward_id'] ?>">
        <button type="submit">✅ Mark as Responded</button>
    </form>
</td>
</tr>
<?php endwhile; ?>
</table>
<?php else: ?>
<p>No queries forwarded to you yet.</p>
<?php endif; ?>

<!-- Responded Queries Section -->
<h2>Responded Queries</h2>
<?php
$stmt = $conn->prepare("SELECT * FROM queries WHERE receiver_role = 'lecturer' AND status = 'responded' ORDER BY created_at DESC");
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($q = $result->fetch_assoc()) {
        echo "<div class='query-box'>";
        echo "<strong>Message:</strong> " . htmlspecialchars($q['message']) . "<br>";
        echo "<strong>Status:</strong> " . htmlspecialchars($q['status']) . "<br>";
        echo "<small>Sent on: " . $q['created_at'] . "</small>";
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
