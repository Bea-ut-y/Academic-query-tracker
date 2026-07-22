<?php
session_start();
require 'config.php';

// Make sure only admin can access this
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit;
}

$msg = "";

/* 
   ✅ Handle query forwarding to lecturer 
   When admin clicks "Forward", the query details will be saved with 
   sender_role='admin' and receiver_role='lecturer'
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forward_query_id'], $_POST['forward_message'])) {
    $query_id = intval($_POST['forward_query_id']);
    $forward_message = trim($_POST['forward_message']);

    // Make sure the original query exists
    $stmt = $conn->prepare("SELECT result_id FROM queries WHERE query_id = ?");
    $stmt->bind_param("i", $query_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if ($result) {
        $result_id = $result['result_id'];

        // Insert the forwarded query
        $stmt2 = $conn->prepare("
            INSERT INTO queries (result_id, sender_role, receiver_role, message, status)
            VALUES (?, 'admin', 'lecturer', ?, 'forwarded')
        ");
        $stmt2->bind_param("is", $result_id, $forward_message);
        $stmt2->execute();

        // Optional: update the old query status
        $stmt3 = $conn->prepare("UPDATE queries SET status='forwarded' WHERE query_id=?");
        $stmt3->bind_param("i", $query_id);
        $stmt3->execute();

        $msg = "✅ Query successfully forwarded to lecturer!";
    } else {
        $msg = "⚠️ Error: Query not found!";
    }
}

// Fetch all queries (for admin view)
$queries = $conn->query("
    SELECT q.query_id, q.message, q.status, r.result_id, u.name AS student_name, c.course_name
    FROM queries q
    JOIN results r ON q.result_id = r.result_id
    JOIN users u ON r.student_id = u.user_id
    JOIN courses c ON r.course_id = c.course_id
    WHERE q.sender_role='student'
    ORDER BY q.created_at DESC
");
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Forward Student Query</title>
<style>
body {
    font-family: Arial, sans-serif;
    background-color: #f0f4f8;
    color: #333;
    padding: 20px;
}
.card {
    background: #e6f0ff;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 30px;
}
h2 {
    color: #004080;
}
table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
}
th, td {
    padding: 10px;
    border-bottom: 1px solid #ccc;
    text-align: left;
}
th {
    background: #004080;
    color: white;
}
tr:hover {
    background: #cce0ff;
}
textarea {
    width: 100%;
    border-radius: 6px;
    padding: 6px;
    border: 1px solid #ccc;
}
button {
    background: #004080;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 5px;
    cursor: pointer;
}
button:hover {
    background: #0059b3;
}
p.success { color: green; }
p.error { color: red; }
</style>
</head>
<body>

<div class="card">
<h2>Admin — Forward Student Queries to Lecturer</h2>

<?php if ($msg): ?>
<p class="<?= strpos($msg, '✅') !== false ? 'success' : 'error' ?>"><?= $msg ?></p>
<?php endif; ?>

<?php if ($queries && $queries->num_rows > 0): ?>
<table>
<tr>
    <th>Course</th>
    <th>Student</th>
    <th>Message</th>
    <th>Status</th>
    <th>Forward to Lecturer</th>
</tr>
<?php while ($q = $queries->fetch_assoc()): ?>
<tr>
    <td><?= htmlspecialchars($q['course_name']) ?></td>
    <td><?= htmlspecialchars($q['student_name']) ?></td>
    <td><?= htmlspecialchars($q['message']) ?></td>
    <td><?= htmlspecialchars($q['status']) ?></td>
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
<p>No student queries found.</p>
<?php endif; ?>

<p><a href="dashboard.php">← Back to Dashboard</a></p>
</div>

</body>
</html>
