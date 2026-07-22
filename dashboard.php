<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}
require 'config.php';

$role = $_SESSION['role'];
$name = $_SESSION['username']; // fixed session variable
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Dashboard</title>
<style>
    body {
        font-family: Arial, sans-serif;
        background-color: #e6f0ff;
        margin: 0;
    }

    .topbar {
        background-color: #004080;
        color: white;
        padding: 15px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .topbar a {
        color: #fff;
        text-decoration: none;
        font-weight: bold;
    }

    .topbar a:hover {
        text-decoration: underline;
    }

    .container {
        max-width: 800px;
        margin: 30px auto;
        background-color: #3399ff;
        padding: 20px 30px;
        border-radius: 10px;
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        color: white;
    }

    h3 {
        margin-top: 0;
    }

    ul {
        list-style: none;
        padding: 0;
    }

    ul li {
        margin: 10px 0;
    }

    ul li a {
        color: white;
        text-decoration: none;
        padding: 8px 12px;
        background-color: #004080;
        border-radius: 5px;
        display: inline-block;
    }

    ul li a:hover {
        background-color: #66b3ff;
        color: #004080;
    }
</style>
</head>
<body>
  <div class="topbar">
    <span>Welcome, <?php echo htmlspecialchars($name); ?> (<?php echo $role; ?>)</span>
    <a href="logout.php">Logout</a>
  </div>

  <div class="container">
    <?php if ($role === 'lecturer'): ?>
      <h3>Lecturer Actions</h3>
      <ul>
        <li><a href="enter_results.php">Enter Results</a></li>
        <li><a href="view_results.php">View Results</a></li>
        <li><a href="lecturer_queries.php">View Queries</a></li>
      </ul>

    <?php elseif ($role === 'student'): ?>
      <h3>Student Actions</h3>
      <ul>
        <li><a href="view_results.php">View My Results</a></li>
        <li><a href="student_queries.php">Send Query about a Result</a></li>
      </ul>

    <?php elseif ($role === 'admin'): ?>
      <h3>Administrator Actions</h3>
      <ul>
        <li><a href="view_results.php">View Results</a></li>
        <li><a href="admin_queries.php">View Queries</a></li>
      </ul>
    <?php endif; ?>
  </div>
</body>
</html>
