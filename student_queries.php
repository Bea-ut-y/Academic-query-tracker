<?php
session_start();
require 'config.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 🔒 Ensure only students can access this page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: dashboard.php");
    exit;
}

$student_id = $_SESSION['user_id'];
$message = "";

// 📨 When student submits a new query
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['result_id'], $_POST['query_text'])) {
    $result_id = intval($_POST['result_id']);
    $query_text = trim($_POST['query_text']);

    if (!empty($result_id) && !empty($query_text)) {
        $stmt = $conn->prepare("
            INSERT INTO queries (result_id, sender_role, receiver_role, message, status)
            VALUES (?, 'student', 'admin', ?, 'open')
        ");
        $stmt->bind_param("is", $result_id, $query_text);
        if ($stmt->execute()) {
            $message = "<p style='color:green;'>✅ Query submitted successfully!</p>";
        } else {
            $message = "<p style='color:red;'>❌ Database error: " . htmlspecialchars($stmt->error) . "</p>";
        }
        $stmt->close();
    } else {
        $message = "<p style='color:red;'>⚠️ Please select a result and type your query.</p>";
    }
}

// 📘 Get all results for this student
$results_query = "
    SELECT r.result_id, c.course_name, r.total_score
    FROM results r
    JOIN courses c ON r.course_id = c.course_id
    WHERE r.student_id = $student_id
";
$results = $conn->query($results_query);

// 🕓 Pending Queries
$pending_query = "
    SELECT q.query_id, q.message AS student_message, q.created_at, c.course_name
    FROM queries q
    JOIN results r ON q.result_id = r.result_id
    JOIN courses c ON r.course_id = c.course_id
    WHERE q.sender_role = 'student'
      AND q.status = 'open'
      AND r.student_id = $student_id
    ORDER BY q.created_at DESC
";
$pending = $conn->query($pending_query);

// 💬 Responded Queries (Admin or Lecturer replied)
$responded_query = "
    SELECT DISTINCT s.query_id AS student_query_id, 
           s.message AS student_message, 
           s.created_at AS query_date, 
           c.course_name,
           COALESCE(r.message, '') AS reply_message,
           COALESCE(r.sender_role, '') AS reply_sender,
           COALESCE(r.created_at, '') AS reply_date
    FROM queries s
    LEFT JOIN queries r 
        ON r.result_id = s.result_id 
        AND r.sender_role IN ('admin', 'lecturer') 
        AND r.receiver_role = 'student'
    JOIN results re ON s.result_id = re.result_id
    JOIN courses c ON re.course_id = c.course_id
    WHERE s.sender_role = 'student' 
      AND s.status = 'responded'
      AND re.student_id = $student_id
    ORDER BY s.created_at DESC
";
$responded = $conn->query($responded_query);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Student Queries</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f3f6fc;
            padding: 30px;
        }
        h2 { color: #004080; }
        form, table {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        select, textarea, button {
            width: 100%;
            margin-top: 10px;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #ccc;
        }
        button {
            background: #004080;
            color: white;
            cursor: pointer;
        }
        button:hover { background: #0059b3; }
        table { width: 100%; border-collapse: collapse; }
        th, td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }
        th { background: #004080; color: white; }
        tr:hover { background: #f9f9f9; }
        .pending { color: #b36b00; font-weight: bold; }
        .reply-box { background: #e6f0ff; padding: 8px; border-radius: 5px; }
        a { text-decoration: none; color: #004080; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>

<h2>Submit a New Query</h2>
<?= $message; ?>

<form method="POST" action="">
    <label for="result_id">Select a Course Result:</label>
    <select name="result_id" required>
        <option value="">-- Select Result --</option>
        <?php while ($row = $results->fetch_assoc()): ?>
            <option value="<?= $row['result_id']; ?>">
                <?= htmlspecialchars($row['course_name']) . " (" . $row['total_score'] . " marks)"; ?>
            </option>
        <?php endwhile; ?>
    </select>

    <label>Your Query:</label>
    <textarea name="query_text" rows="4" placeholder="Write your query here..." required></textarea>

    <button type="submit">Submit Query</button>
</form>

<!-- Pending Queries -->
<h2>Pending Queries</h2>
<table>
<tr>
    <th>Course</th>
    <th>Your Query</th>
    <th>Date</th>
    <th>Status</th>
</tr>
<?php if ($pending->num_rows > 0): ?>
    <?php while ($p = $pending->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($p['course_name']); ?></td>
            <td><?= htmlspecialchars($p['student_message']); ?></td>
            <td><?= $p['created_at']; ?></td>
            <td class="pending">⏳ Waiting for response</td>
        </tr>
    <?php endwhile; ?>
<?php else: ?>
    <tr><td colspan="4">No pending queries.</td></tr>
<?php endif; ?>
</table>

<!-- Responded Queries -->
<h2>Responded Queries</h2>
<table>
<tr>
    <th>Course</th>
    <th>Your Query</th>
    <th>Reply From</th>
    <th>Reply Message</th>
    <th>Reply Date</th>
</tr>
<?php if ($responded->num_rows > 0): ?>
    <?php while ($r = $responded->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($r['course_name']); ?></td>
            <td><?= htmlspecialchars($r['student_message']); ?></td>
            <td><?= ucfirst(htmlspecialchars($r['reply_sender'])); ?></td>
            <td class="reply-box"><?= htmlspecialchars($r['reply_message']); ?></td>
            <td><?= $r['reply_date']; ?></td>
        </tr>
    <?php endwhile; ?>
<?php else: ?>
    <tr><td colspan="5">No responded queries yet.</td></tr>
<?php endif; ?>
</table>

<p><a href="dashboard.php">← Back to Dashboard</a></p>

</body>
</html>
