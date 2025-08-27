<?php
session_start();
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier']);
    $password = $_POST['password'];
    $role = $_POST['role']; // selected role

    $stmt = $conn->prepare("SELECT id, password, role FROM users WHERE (email = ? OR phone = ?) AND role = ?");
    $stmt->bind_param("sss", $identifier, $identifier, $role);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];

            // Redirect based on role
            if ($user['role'] === 'student') {
                header("Location: files.php");
            } elseif ($user['role'] === 'professor') {
                header("Location: professor_dashboard.php");
            } else {
                header("Location: files.php"); // fallback
            }
            exit();
        } else {
            $error = "Incorrect password.";
        }
    } else {
        $error = "No user found with that email, phone, or role.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Cloud Storage</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --accent: #4895ef;
            --danger: #f72585;
            --success: #4cc9f0;
            --light: #f8f9fa;
            --dark: #212529;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            color: var(--dark);
            padding: 15px;
        }

        .login-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 450px;
            padding: 30px;
            text-align: center;
        }

        .logo {
            font-size: 24px;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 25px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .logo i {
            margin-right: 10px;
            color: var(--accent);
            font-size: 28px;
        }

        .illustration {
            margin-bottom: 25px;
            font-size: 50px;
            color: var(--accent);
        }

        h2 {
            color: var(--secondary);
            margin-bottom: 20px;
            font-size: 22px;
        }

        .form-group {
            margin-bottom: 18px;
            text-align: left;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark);
            font-size: 14px;
        }

        input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border 0.3s;
        }

        input:focus {
            border-color: var(--accent);
            outline: none;
        }

        .btn {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 14px 20px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            width: 100%;
            transition: background-color 0.3s;
            margin-top: 10px;
            min-height: 48px;
        }

        .btn:hover {
            background-color: var(--secondary);
        }

        .error {
            color: var(--danger);
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .register-link {
            margin-top: 20px;
            font-size: 14px;
        }

        .register-link a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 500;
        }

        .register-link a:hover {
            text-decoration: underline;
        }

        .radio-group {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        .radio-option {
            display: flex;
            align-items: center;
        }

        .radio-option input {
            width: auto;
            margin-right: 8px;
        }

        @media(max-width:480px) {
            .login-container {
                padding: 25px 20px;
            }

            .logo {
                font-size: 22px;
            }

            .illustration {
                font-size: 45px;
                margin-bottom: 20px;
            }

            h2 {
                font-size: 20px;
            }

            input {
                padding: 12px;
            }

            .btn {
                padding: 12px;
                font-size: 15px;
            }
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="logo">
            <i class="fas fa-cloud-upload-alt"></i>
            <span>CloudStorage</span>
        </div>

        <div class="illustration">
            <i class="fas fa-sign-in-alt"></i>
        </div>

        <h2>Login to Your Account</h2>

        <?php if (isset($error)): ?>
            <div class="error">
                <i class="fas fa-exclamation-circle"></i> <?= $error ?>
            </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <div class="radio-group">
                <label class="radio-option">
                    <input type="radio" name="role" value="student" checked> Student
                </label>
                <label class="radio-option">
                    <input type="radio" name="role" value="professor"> Professor
                </label>
            </div>

            <div class="form-group">
                <label for="identifier">Email or Phone</label>
                <input type="text" id="identifier" name="identifier" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit" class="btn">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
        </form>

        <div class="register-link">
            Not registered yet? <a href="register.php">Create an account</a>
        </div>
    </div>
</body>

</html>