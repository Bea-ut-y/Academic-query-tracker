<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// fetch students (only needed for lecturer)
$students = [];
if ($role === 'lecturer') {
    $students = $conn->query("SELECT user_id, name FROM users WHERE role='student' ORDER BY name");
}

// fetch courses for lecturer (needed for lecturer only)
$courses = [];
if ($role === 'lecturer') {
    $stmt = $conn->prepare("SELECT course_id, course_name FROM courses WHERE lecturer_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $courses = $stmt->get_result();
}

// handle delete result (lecturers only)
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_result_id']) && $role === 'lecturer') {
    $result_id = intval($_POST['delete_result_id']);

    // check if queries exist
    $stmt_check = $conn->prepare("SELECT COUNT(*) AS cnt FROM queries WHERE result_id=?");
    $stmt_check->bind_param("i", $result_id);
    $stmt_check->execute();
    $res_check = $stmt_check->get_result()->fetch_assoc();
    $stmt_check->close();

    if ($res_check['cnt'] > 0) {
        $msg = "❌ Cannot delete this result because it has linked queries. Please resolve them first.";
    } else {
        // safe to delete
        $stmt_del = $conn->prepare("DELETE FROM results WHERE result_id=? AND course_id IN (SELECT course_id FROM courses WHERE lecturer_id=?)");
        $stmt_del->bind_param("ii", $result_id, $user_id);
        if ($stmt_del->execute()) {
            $msg = "✅ Result deleted successfully.";
        } else {
            $msg = "❌ Error deleting result: " . $stmt_del->error;
        }
        $stmt_del->close();
    }
}

// fetch results based on role
if ($role === 'admin') {
    $results = $conn->query("
        SELECT r.result_id, u.name AS student_name, c.course_name, r.ca_score, r.exam_score, r.total_score
        FROM results r
        JOIN users u ON r.student_id = u.user_id
        JOIN courses c ON r.course_id = c.course_id
        ORDER BY r.result_id DESC
    ");
} else if ($role === 'lecturer') {
    $stmt_results = $conn->prepare("
        SELECT r.result_id, u.name AS student_name, c.course_name, r.ca_score, r.exam_score, r.total_score
        FROM results r
        JOIN users u ON r.student_id = u.user_id
        JOIN courses c ON r.course_id = c.course_id
        WHERE c.lecturer_id = ?
        ORDER BY r.result_id DESC
    ");
    $stmt_results->bind_param("i", $user_id);
    $stmt_results->execute();
    $results = $stmt_results->get_result();
} else { // student
    $stmt_results = $conn->prepare("
        SELECT r.result_id, u.name AS student_name, c.course_name, r.ca_score, r.exam_score, r.total_score
        FROM results r
        JOIN users u ON r.student_id = u.user_id
        JOIN courses c ON r.course_id = c.course_id
        WHERE r.student_id = ?
        ORDER BY r.result_id DESC
    ");
    $stmt_results->bind_param("i", $user_id);
    $stmt_results->execute();
    $results = $stmt_results->get_result();
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Student Results</title>
<style>
body { font-family: Arial, sans-serif; background: #f0f4f8; color: #333; padding: 20px; }
.card { background: #e6f0ff; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 800px; margin: 0 auto 30px auto; }
h3 { color: #004080; margin-bottom: 20px; }
label { display: block; margin: 10px 0 5px; }
input[type="number"], select { width: 100%; padding: 6px; border-radius: 5px; border: 1px solid #ccc; margin-bottom: 10px; }
button { background: #004080; color: white; padding: 8px 15px; border: none; border-radius: 5px; cursor: pointer; }
button:hover { background: #0059b3; }
a { color: #004080; text-decoration: none; }
a:hover { text-decoration: underline; }
table { width: 100%; border-collapse: collapse; margin-top: 20px; }
th, td { padding: 10px; border-bottom: 1px solid #ccc; text-align: left; }
th { background: #004080; color: white; }
tr:hover { background: #cce0ff; }
.msg { padding: 10px; border-radius: 5px; margin-bottom: 15px; }
.msg-success { background: #d4edda; color: #155724; }
.msg-error { background: #f8d7da; color: #721c24; }
</style>
</head>
<body>

<?php if($role === 'lecturer'): ?>
<div class="card">
    <h3>Enter Student Result</h3>
    <form action="save_results.php" method="post">
      <label>Student</label>
      <select name="student_id" required>
        <?php while($s = $students->fetch_assoc()): ?>
          <option value="<?= $s['user_id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
        <?php endwhile; ?>
      </select>

      <label>Course</label>
      <select name="course_id" required>
        <?php while($c = $courses->fetch_assoc()): ?>
          <option value="<?= $c['course_id'] ?>"><?= htmlspecialchars($c['course_name']) ?></option>
        <?php endwhile; ?>
      </select>

      <label>CA Score (0-40)</label>
      <input type="number" name="ca_score" min="0" max="40" required>

      <label>Exam Score (0-60)</label>
      <input type="number" name="exam_score" min="0" max="60" required>

      <button type="submit">Save Result</button>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <h3>Results</h3>

    <?php if($msg): ?>
        <div class="msg <?= strpos($msg, '✅') !== false ? 'msg-success' : 'msg-error' ?>"><?= $msg ?></div>
    <?php endif; ?>

    <?php if($results->num_rows > 0): ?>
    <table>
        <tr>
            <th>Student</th>
            <th>Course</th>
            <th>CA Score</th>
            <th>Exam Score</th>
            <th>Total</th>
            <?php if($role === 'lecturer'): ?><th>Action</th><?php endif; ?>
        </tr>
        <?php while($r = $results->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($r['student_name']) ?></td>
            <td><?= htmlspecialchars($r['course_name']) ?></td>
            <td><?= $r['ca_score'] ?></td>
            <td><?= $r['exam_score'] ?></td>
            <td><?= $r['total_score'] ?></td>
            <?php if($role === 'lecturer'): ?>
            <td>
                <form method="post" style="margin:0;">
                    <input type="hidden" name="delete_result_id" value="<?= $r['result_id'] ?>">
                    <button type="submit" onclick="return confirm('Are you sure you want to delete this result?');">Delete</button>
                </form>
            </td>
            <?php endif; ?>
        </tr>
        <?php endwhile; ?>
    </table>
    <?php else: ?>
    <p>No results available.</p>
    <?php endif; ?>
</div>

<p style="text-align:center;"><a href="dashboard.php">Back to dashboard</a></p>
</body>
</html>
