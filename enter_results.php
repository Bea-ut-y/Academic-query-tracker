<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lecturer') {
    header("Location: dashboard.php");
    exit;
}
require 'config.php';
$lecturer_id = $_SESSION['user_id'];

// fetch students
$students = $conn->query("SELECT user_id, name FROM users WHERE role='student' ORDER BY name");

// fetch courses taught by this lecturer
$stmt = $conn->prepare("SELECT course_id, course_name FROM courses WHERE lecturer_id = ?");
$stmt->bind_param("i", $lecturer_id);
$stmt->execute();
$courses = $stmt->get_result();
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Enter Result</title>
<style>
body { 
    font-family: Arial, sans-serif; 
    background: #f0f4f8; 
    color: #333; 
    padding: 20px; 
}
.card { 
    background: #e6f0ff; 
    padding: 20px; 
    border-radius: 10px; 
    box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
    max-width: 600px; 
    margin: 0 auto; 
}
h3 { 
    color: #004080; 
    margin-bottom: 20px; 
}
label { 
    display: block; 
    margin: 10px 0 5px; 
}
input[type="number"], select { 
    width: 100%; 
    padding: 6px; 
    border-radius: 5px; 
    border: 1px solid #ccc; 
    margin-bottom: 10px; 
}
button { 
    background: #004080; 
    color: white; 
    padding: 8px 15px; 
    border: none; 
    border-radius: 5px; 
    cursor: pointer; 
}
button:hover { 
    background: #0059b3; 
}
a { 
    color: #004080; 
    text-decoration: none; 
}
a:hover { 
    text-decoration: underline; 
}
</style>
</head>
<body>
  <div class="card">
    <h3>Enter Student Result</h3>
    <form action="save_results.php" method="post">
      <label>Student</label>
      <select name="student_id" required>
        <?php while($s = $students->fetch_assoc()): ?>
          <option value="<?php echo $s['user_id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
        <?php endwhile; ?>
      </select>

      <label>Course</label>
      <select name="course_id" required>
        <?php while($c = $courses->fetch_assoc()): ?>
          <option value="<?php echo $c['course_id']; ?>"><?php echo htmlspecialchars($c['course_name']); ?></option>
        <?php endwhile; ?>
      </select>

      <label>CA Score (0-40)</label>
      <input type="number" name="ca_score" min="0" max="40" required>

      <label>Exam Score (0-60)</label>
      <input type="number" name="exam_score" min="0" max="60" required>

      <button type="submit">Save Result</button>
    </form>
    <p><a href="dashboard.php">Back to dashboard</a></p>
  </div>
</body>
</html>
