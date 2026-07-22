<?php
session_start();
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT user_id, password, role, name FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 1) {
        $row = $res->fetch_assoc();
        // For prototype we compare plain text. In production use password_verify().
        if ($password === $row['password']) {
            $_SESSION['user_id'] = $row['user_id'];
            $_SESSION['role'] = $row['role'];
            $_SESSION['name'] = $row['name'];
            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Invalid credentials.";
        }
    } else {
        $error = "Invalid credentials.";
    }
} else {
    header("Location: index.php");
    exit;
}
?>
<!doctype html>
<html><body>
<?php if (isset($error)) echo "<p style='color:red;'>$error</p>"; ?>
<p><a href="index.php">Back to login</a></p>
</body></html>
