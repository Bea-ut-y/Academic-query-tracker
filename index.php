<?php
session_start();
include 'config.php'; // your database connection

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

// Check if form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Prepare query to prevent SQL injection
    $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Assuming passwords are stored as plain text (not recommended)
        if ($password === $user['password']) {
            // Login successful
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Incorrect password.";
        }
    } else {
        $error = "User not found.";
    }
}
?>

<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>AATA - Login</title>
<style>
    body {
        font-family: Arial, sans-serif;
        background-color: #e6f0ff;
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100vh;
    }

    .card {
        background-color: #004080;
        padding: 30px;
        border-radius: 10px;
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        width: 350px;
        color: white;
    }

    .card h2 {
        text-align: center;
        margin-bottom: 20px;
    }

    .card input[type="text"],
    .card input[type="password"] {
        width: 100%;
        padding: 10px;
        margin: 8px 0;
        border: none;
        border-radius: 5px;
    }

    .card button {
        width: 100%;
        padding: 10px;
        background-color: #3399ff;
        border: none;
        border-radius: 5px;
        color: white;
        font-weight: bold;
        cursor: pointer;
    }

    .card button:hover {
        background-color: #66b3ff;
    }

    .card p {
        font-size: 0.9em;
        color: #ccc;
        text-align: center;
    }

    .error {
        background-color: #ff4d4d;
        padding: 8px;
        border-radius: 5px;
        margin-bottom: 10px;
        text-align: center;
    }
</style>
</head>
<body>
  <div class="card">
    <h2>Augmented Academic Tracking Assistant (AATA)</h2>
    
    <?php if(isset($error)) echo "<div class='error'>$error</div>"; ?>
    
    <form action="login.php" method="post">
      <label>Username</label>
      <input type="text" name="username" required>
      <label>Password</label>
      <input type="password" name="password" required>
      <button type="submit">Login</button>
    </form>
    <p>Sample accounts: alice/student, bob/lecturer, admin/admin</p>
  </div>
</body>
</html>
