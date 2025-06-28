<?php
$host = 'localhost';
$dbname = 'gym_management';
$username = 'root';
$password = 'databaseLab';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create users table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        phone VARCHAR(20),
        membership VARCHAR(20) NOT NULL,
        secret_code VARCHAR(100) NOT NULL,
        join_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Session management
session_start();

// Process forms
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['register'])) {
        // Registration process
        $name = $_POST['name'];
        $email = $_POST['email'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $phone = $_POST['phone'] ?? '';
        $membership = $_POST['membership'];
        $secret_code = $_POST['secret_code'];

        try {
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, phone, membership, secret_code) 
                                  VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $email, $password, $phone, $membership, $secret_code]);

            $_SESSION['success'] = "Registration successful! You can now login.";
            header("Location: ".$_SERVER['PHP_SELF']."?view=login");
            exit;
        } catch (PDOException $e) {
            $error = "Registration failed: " . ($e->getCode() == 23000 ? "Email already exists" : "Database error");
        }
    }
    elseif (isset($_POST['login'])) {
        // Login process
        $email = $_POST['email'];
        $password = $_POST['password'];

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user'] = $user;
            header("Location: ".$_SERVER['PHP_SELF']."?view=dashboard");
            exit;
        } else {
            $error = "Invalid email or password!";
        }
    }
    elseif (isset($_POST['update_profile'])) {
        if (!isset($_SESSION['user'])) {
            header("Location: ".$_SERVER['PHP_SELF']."?view=login");
            exit;
        }

        $newName = trim($_POST['name']);
        $newPhone = trim($_POST['phone']);
        $userId = $_SESSION['user']['id'];

        if (empty($newName)) {
            $_SESSION['error'] = "Name cannot be empty.";
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?");
                $stmt->execute([$newName, $newPhone, $userId]);

                // Update session data immediately
                $_SESSION['user']['name'] = $newName;
                $_SESSION['user']['phone'] = $newPhone;

                $_SESSION['success'] = "Profile updated successfully!";

            } catch (PDOException $e) {
                $_SESSION['error'] = "Profile update failed: " . $e->getMessage();
            }
        }
        // Redirect back to the profile tab
        header("Location: ".$_SERVER['PHP_SELF']."?view=dashboard&tab=profile");
        exit;
    }
    elseif (isset($_POST['verify'])) {
        // Password recovery verification
        $email = $_POST['email'];
        $secret_code = $_POST['secret_code'];

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND secret_code = ?");
        $stmt->execute([$email, $secret_code]);
        $user = $stmt->fetch();

        if ($user) {
            $_SESSION['reset_email'] = $email;
            header("Location: ".$_SERVER['PHP_SELF']."?view=reset");
            exit;
        } else {
            $error = "Invalid email or secret code!";
        }
    }
    elseif (isset($_POST['reset'])) {
        // Password reset
        $email = $_SESSION['reset_email'] ?? '';
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

        if ($email) {
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
            $stmt->execute([$password, $email]);

            unset($_SESSION['reset_email']);
            $_SESSION['success'] = "Password updated successfully! You can now login.";
            header("Location: ".$_SERVER['PHP_SELF']."?view=login");
            exit;
        } else {
            $error = "Session expired. Please start the reset process again.";
        }
    }
    elseif (isset($_POST['logout'])) {
        // Logout
        session_destroy();
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    }
}

// Determine current view
$view = 'home'; // Default to home page
if (isset($_GET['view'])) {
    $view = $_GET['view'];
} elseif (isset($_SESSION['user'])) {
    $view = 'dashboard';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PowerHouse Gym Management System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&family=Montserrat:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #00aaff;
            --primary-hover: #0088cc;
            --accent-color: #ff6b6b;
            --dark-bg: #0d0f17;
            --darker-bg: #090b12;
            --light-bg: #1a1d2b;
            --card-bg: rgba(26, 29, 43, 0.8);
            --text-light: #f0f0f0;
            --text-dark: #333;
            --text-muted: #a0a0c0;
            --success: #28a745;
            --danger: #dc3545;
            --border-color: rgba(255, 255, 255, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: var(--dark-bg);
            color: var(--text-light);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background:
                    radial-gradient(circle at 10% 20%, rgba(13, 15, 23, 0.8) 0%, rgba(13, 15, 23, 0.9) 100%),
                    url('https://images.unsplash.com/photo-1534438327276-14e5300c3a48?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2070&q=80') no-repeat center center/cover;
            z-index: -1;
            filter: brightness(0.5) contrast(1.2);
        }

        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
        }

        .card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
            overflow: hidden;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }

        .card-header {
            background: rgba(0,0,0,0.2);
            padding: 30px;
            text-align: center;
            border-bottom: 1px solid var(--border-color);
        }

        .card-header h1 {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 10px;
            color: white;
            font-family: 'Montserrat', sans-serif;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .card-header p {
            color: var(--text-muted);
            font-size: 1.1rem;
        }

        .card-body {
            padding: 40px;
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .input-wrapper {
            position: relative;
        }

        .form-group input, .form-group select {
            width: 100%;
            padding: 15px 20px 15px 50px;
            border: 1px solid var(--border-color);
            background-color: rgba(0,0,0,0.2);
            border-radius: 12px;
            font-size: 1rem;
            color: var(--text-light);
            transition: all 0.3s;
        }

        .input-wrapper i {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            transition: color 0.3s;
        }

        .form-group input:focus, .form-group select:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 4px rgba(0, 170, 255, 0.2);
            background-color: rgba(0,0,0,0.1);
        }

        .form-group input:focus + i {
            color: var(--primary-color);
        }

        /* Buttons */
        .btn {
            display: inline-block;
            padding: 15px 30px;
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
            width: 100%;
        }
        .btn-group .btn {
            width: auto;
            margin-right: 10px;
        }

        .btn-primary {
            background: var(--primary-color);
            box-shadow: 0 4px 15px rgba(0, 170, 255, 0.3);
        }

        .btn-primary:hover {
            background: var(--primary-hover);
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 170, 255, 0.4);
        }

        .btn-accent {
            background: var(--accent-color);
            box-shadow: 0 4px 15px rgba(255, 107, 107, 0.3);
        }

        .btn-accent:hover {
            background: #e55a5a;
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(255, 107, 107, 0.4);
        }

        .btn-outline {
            background: transparent;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
        }
        .btn-secondary {
            background: var(--light-bg);
            color: var(--text-muted);
            border: 2px solid var(--border-color);
        }
        .btn-secondary:hover {
            background: var(--card-bg);
            color: var(--text-light);
        }

        .btn-outline:hover {
            background: var(--primary-color);
            color: white;
        }

        /* Alerts */
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-weight: 500;
            border: 1px solid transparent;
        }

        .alert-success {
            background-color: rgba(40, 167, 69, 0.2);
            color: #a8dbb4;
            border-color: rgba(40, 167, 69, 0.5);
        }

        .alert-danger {
            background-color: rgba(220, 53, 69, 0.2);
            color: #f1b0b7;
            border-color: rgba(220, 53, 69, 0.5);
        }

        .form-links {
            text-align: center;
            margin-top: 25px;
        }
        .form-links p {
            color: var(--text-muted);
            margin-bottom: 10px;
        }
        .form-links a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s;
        }
        .form-links a:hover {
            color: var(--primary-hover);
            text-decoration: underline;
        }

        /* Layout */
        .row { display: flex; flex-wrap: wrap; margin: 0 -15px; }
        .col { flex: 1; min-width: 300px; padding: 15px; }

        /* Membership Options */
        .membership-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 10px;
        }

        .membership-card {
            border: 2px solid var(--border-color);
            background: rgba(0,0,0,0.1);
            border-radius: 15px;
            padding: 25px;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            text-align: center;
        }

        .membership-card:hover, .membership-card.selected {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
            border-color: var(--primary-color);
        }

        .membership-card.selected::before {
            content: '✓';
            position: absolute;
            top: 15px;
            right: 15px;
            background: var(--primary-color);
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            font-size: 16px;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .membership-card h3 { color: white; margin-bottom: 10px; font-weight: 600; }
        .membership-price { font-size: 1.8rem; font-weight: 700; color: var(--primary-color); margin-bottom: 20px; }
        .membership-features { list-style: none; text-align: left; color: var(--text-muted); font-size: 0.9rem; }
        .membership-features li { margin-bottom: 10px; padding-left: 25px; position: relative; }
        .membership-features li::before { content: "✓"; position: absolute; left: 0; color: var(--success); font-weight: bold; }

        /* Dashboard */
        .dashboard-header {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            color: white;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            border-radius: 20px;
        }

        .user-info { display: flex; align-items: center; }
        .user-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: bold;
            margin-right: 20px;
        }
        #user-name { font-weight: 600; }
        #user-email { color: var(--text-muted); }
        .dashboard-header .btn-outline { width: auto; padding: 10px 20px; }

        .nav-tabs { display: flex; border-bottom: 1px solid var(--border-color); margin-bottom: 30px; }
        .nav-tab {
            padding: 15px 25px;
            cursor: pointer;
            font-weight: 600;
            color: var(--text-muted);
            transition: all 0.3s;
            position: relative;
            border-bottom: 3px solid transparent;
            margin-bottom: -1px;
        }
        .nav-tab.active { color: var(--primary-color); border-bottom-color: var(--primary-color); }
        .tab-content { display: none; }
        .tab-content.active { display: block; animation: fadeIn 0.5s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .stat-card {
            background: var(--light-bg);
            border-radius: 15px;
            padding: 25px;
            border: 1px solid var(--border-color);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card .icon { font-size: 2rem; color: var(--primary-color); margin-bottom: 15px; }
        .stat-card h3 { color: var(--text-muted); margin-bottom: 10px; font-size: 1rem; font-weight: 400;}
        .stat-value { font-size: 2.5rem; font-weight: 700; color: white; }

        .detail-section { margin-top: 30px; }
        .detail-section h3 { color: white; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px; }
        .detail-section p { color: var(--text-muted); margin-bottom: 15px; font-size: 1.1rem; display: flex; align-items: center;}
        .detail-section p strong { color: var(--text-light); min-width: 180px; display: inline-block; font-weight: 600; }
        .detail-section .btn { width: auto; margin-top: 20px; }

        /* Profile Edit styles */
        .profile-field .view-mode { display: inline; }
        .profile-field .edit-mode { display: none; }
        .profile-field.editing .view-mode { display: none; }
        .profile-field.editing .edit-mode { display: inline-block; width: calc(100% - 180px); }

        .profile-buttons .edit-mode-btn, .profile-buttons.editing .view-mode-btn { display: none; }
        .profile-buttons.editing .edit-mode-btn { display: inline-block; }

        /* Welcome Page Styles */
        .hero {
            min-height: 90vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
            padding: 40px 0;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background:
                    linear-gradient(to bottom, rgba(13, 15, 23, 0.9), rgba(13, 15, 23, 0.7)),
                    url('https://images.unsplash.com/photo-1581009146145-b5ef050c2e1e?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2070&q=80') center center/cover;
            z-index: -1;
            filter: brightness(0.7) contrast(1.2);
        }

        .hero-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            text-align: center;
            z-index: 1;
        }

        .hero h1 {
            font-size: 4rem;
            font-weight: 800;
            margin-bottom: 20px;
            color: white;
            font-family: 'Montserrat', sans-serif;
            text-transform: uppercase;
            letter-spacing: 2px;
            line-height: 1.2;
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }

        .hero h1 span {
            color: var(--primary-color);
            display: block;
        }

        .hero p {
            font-size: 1.5rem;
            max-width: 700px;
            margin: 0 auto 40px;
            color: var(--text-light);
            opacity: 0.9;
        }

        .cta-buttons {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 40px;
        }

        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin: 80px 0;
        }

        .feature-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 40px 30px;
            text-align: center;
            transition: transform 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-10px);
        }

        .feature-icon {
            width: 80px;
            height: 80px;
            background: rgba(0, 170, 255, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            color: var(--primary-color);
            font-size: 2rem;
        }

        .feature-card h3 {
            font-size: 1.5rem;
            margin-bottom: 15px;
            color: white;
        }

        .feature-card p {
            color: var(--text-muted);
            font-size: 1rem;
        }

        .gym-image {
            width: 100%;
            height: 400px;
            border-radius: 20px;
            overflow: hidden;
            margin: 60px 0;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
        }

        .gym-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .gym-image:hover img {
            transform: scale(1.05);
        }

        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2.5rem;
            }

            .hero p {
                font-size: 1.2rem;
            }

            .cta-buttons {
                flex-direction: column;
                gap: 15px;
            }

            .cta-buttons .btn {
                width: 100%;
            }

            .dashboard-header {
                flex-direction: column;
                text-align: center;
            }

            .user-info {
                margin-bottom: 20px;
                justify-content: center;
            }

            .nav-tabs {
                justify-content: center;
            }

            .row {
                flex-direction: column;
            }

            .profile-field .edit-mode {
                width: 100%;
            }

            .profile-field p {
                flex-direction: column;
                align-items: flex-start;
            }

            .gym-image {
                height: 250px;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($_SESSION['error']) ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($_SESSION['success']) ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <!-- Welcome Page -->
    <?php if ($view === 'home'): ?>
        <section class="hero">
            <div class="hero-content">
                <h1>Transform Your Body <span>PowerHouse Gym</span></h1>
                <p>Join our state-of-the-art fitness center with professional trainers, premium equipment, and a supportive community.</p>

                <div class="cta-buttons">
                    <a href="?view=login" class="btn btn-primary">Login to Your Account</a>
                    <a href="?view=register" class="btn btn-accent">Create New Account</a>
                </div>
            </div>
        </section>

        <section>
            <div class="features">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-dumbbell"></i>
                    </div>
                    <h3>Premium Equipment</h3>
                    <p>Access the latest fitness equipment from top brands for a complete workout experience.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3>Expert Trainers</h3>
                    <p>Our certified trainers will guide you to achieve your fitness goals with personalized plans.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <h3>Flexible Scheduling</h3>
                    <p>Work out anytime with our 24/7 access. Book classes and sessions through our easy-to-use app.</p>
                </div>
            </div>

            <div class="gym-image">
                <img src="https://images.unsplash.com/photo-1571019613454-1cb2f99b2d8b?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2070&q=80" alt="Gym Interior">
            </div>
        </section>
    <?php endif; ?>

    <?php if ($view === 'register'): ?>
        <div class="card">
            <div class="card-header">
                <h1>Join PowerHouse Gym</h1>
                <p>Create your account to start your fitness journey</p>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col">
                            <div class="form-group">
                                <label for="name">Full Name</label>
                                <div class="input-wrapper">
                                    <input type="text" id="name" name="name" required placeholder="John Doe">
                                    <i class="fas fa-user"></i>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <div class="input-wrapper">
                                    <input type="email" id="email" name="email" required placeholder="john@example.com">
                                    <i class="fas fa-envelope"></i>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="password">Password</label>
                                <div class="input-wrapper">
                                    <input type="password" id="password" name="password" required minlength="6" placeholder="••••••">
                                    <i class="fas fa-lock"></i>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="phone">Phone Number (Optional)</label>
                                <div class="input-wrapper">
                                    <input type="tel" id="phone" name="phone" placeholder="(123) 456-7890">
                                    <i class="fas fa-phone"></i>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="secret_code">Create Secret Recovery Code</label>
                                <div class="input-wrapper">
                                    <input type="text" id="secret_code" name="secret_code" required placeholder="A memorable word or phrase">
                                    <i class="fas fa-key"></i>
                                </div>
                                <small style="color: var(--text-muted); font-size: 0.8rem;">This is for password recovery. Keep it safe!</small>
                            </div>
                        </div>
                        <div class="col">
                            <div class="form-group">
                                <label>Select Your Membership</label>
                                <div class="membership-options">
                                    <div class="membership-card" onclick="selectMembership(this, 'Basic')">
                                        <h3>Basic</h3>
                                        <div class="membership-price">$29</div>
                                    </div>
                                    <div class="membership-card" onclick="selectMembership(this, 'Premium')">
                                        <h3>Premium</h3>
                                        <div class="membership-price">$49</div>
                                    </div>
                                    <div class="membership-card" onclick="selectMembership(this, 'VIP')">
                                        <h3>VIP</h3>
                                        <div class="membership-price">$99</div>
                                    </div>
                                </div>
                                <input type="hidden" id="membership" name="membership" value="Premium">
                            </div>
                            <div class="membership-features" id="feature-list">
                                <h4 style="color: white; margin-top: 20px;">Premium Plan Features:</h4>
                                <ul>
                                    <li>Access to all gym facilities</li>
                                    <li>Unlimited group classes</li>
                                    <li>1 free personal training session per month</li>
                                    <li>Sauna and steam room access</li>
                                    <li>Nutrition consultation</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="form-group" style="margin-top: 30px;">
                        <button type="submit" name="register" class="btn btn-primary">Create Account</button>
                    </div>
                    <div class="form-links">
                        <p>Already have an account? <a href="?view=login">Login</a></p>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($view === 'login'): ?>
        <div class="card">
            <div class="card-header">
                <h1>Welcome Back!</h1>
                <p>Login to access your dashboard</p>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-group">
                        <label for="login-email">Email Address</label>
                        <div class="input-wrapper">
                            <input type="email" id="login-email" name="email" required placeholder="john@example.com">
                            <i class="fas fa-envelope"></i>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="login-password">Password</label>
                        <div class="input-wrapper">
                            <input type="password" id="login-password" name="password" required placeholder="••••••">
                            <i class="fas fa-lock"></i>
                        </div>
                    </div>
                    <div class="form-group">
                        <button type="submit" name="login" class="btn btn-primary">Login</button>
                    </div>
                    <div class="form-links">
                        <p>Forgot your password? <a href="?view=forgot">Recover Account</a></p>
                        <p>Don't have an account? <a href="?view=register">Register Now</a></p>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($view === 'forgot'): ?>
        <div class="card">
            <div class="card-header">
                <h1>Password Recovery</h1>
                <p>Use your secret code to reset your password</p>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-group">
                        <label for="forgot-email">Email Address</label>
                        <div class="input-wrapper">
                            <input type="email" id="forgot-email" name="email" required placeholder="Your registered email">
                            <i class="fas fa-envelope"></i>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="forgot-secret">Your Secret Code</label>
                        <div class="input-wrapper">
                            <input type="text" id="forgot-secret" name="secret_code" required placeholder="Your recovery code">
                            <i class="fas fa-key"></i>
                        </div>
                    </div>
                    <div class="form-group">
                        <button type="submit" name="verify" class="btn btn-primary">Verify Identity</button>
                    </div>
                    <div class="form-links">
                        <p>Remember your password? <a href="?view=login">Login</a></p>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($view === 'reset'): ?>
        <div class="card">
            <div class="card-header">
                <h1>Reset Password</h1>
                <p>Set a new, strong password for your account</p>
            </div>
            <div class="card-body">
                <form method="POST" id="reset-form">
                    <div class="form-group">
                        <label for="new-password">New Password</label>
                        <div class="input-wrapper">
                            <input type="password" id="new-password" name="password" required minlength="6" placeholder="Enter new password">
                            <i class="fas fa-lock"></i>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="confirm-password">Confirm New Password</label>
                        <div class="input-wrapper">
                            <input type="password" id="confirm-password" name="confirm_password" required minlength="6" placeholder="Confirm new password">
                            <i class="fas fa-check-double"></i>
                        </div>
                        <div id="password-match-msg"></div>
                    </div>
                    <div class="form-group">
                        <button type="submit" name="reset" id="reset-button" class="btn btn-primary">Reset Password</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($view === 'dashboard' && isset($_SESSION['user'])):
        $user = $_SESSION['user'];
        ?>
        <div id="dashboard-section">
            <div class="dashboard-header">
                <div class="user-info">
                    <div class="user-avatar"><?= htmlspecialchars(substr($user['name'], 0, 1)) ?></div>
                    <div>
                        <h2 id="user-name"><?= htmlspecialchars($user['name']) ?></h2>
                        <p id="user-email"><?= htmlspecialchars($user['email']) ?></p>
                    </div>
                </div>
                <div>
                    <form method="POST">
                        <button name="logout" class="btn btn-outline">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="nav-tabs">
                        <div class="nav-tab" data-tab="dashboard-tab"><i class="fas fa-tachometer-alt"></i>&nbsp; Dashboard</div>
                        <div class="nav-tab" data-tab="profile-tab"><i class="fas fa-user-circle"></i>&nbsp; Profile</div>
                        <div class="nav-tab" data-tab="membership-tab"><i class="fas fa-id-card"></i>&nbsp; Membership</div>
                    </div>

                    <div id="dashboard-tab" class="tab-content">
                        <div class="dashboard-stats">
                            <div class="stat-card">
                                <div class="icon"><i class="fas fa-dumbbell"></i></div>
                                <h3>Workouts This Week</h3>
                                <div class="stat-value">4</div>
                            </div>
                            <div class="stat-card">
                                <div class="icon"><i class="fas fa-chart-line"></i></div>
                                <h3>Current Streak</h3>
                                <div class="stat-value">12d</div>
                            </div>
                            <div class="stat-card">
                                <div class="icon"><i class="fas fa-fire"></i></div>
                                <h3>Calories Burned</h3>
                                <div class="stat-value">2,450</div>
                            </div>
                            <div class="stat-card">
                                <div class="icon"><i class="fas fa-trophy"></i></div>
                                <h3>Goals Achieved</h3>
                                <div class="stat-value">5</div>
                            </div>
                        </div>

                        <div class="gym-image">
                            <img src="https://images.unsplash.com/photo-1534438327276-14e5300c3a48?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2070&q=80" alt="Gym Equipment">
                        </div>
                    </div>

                    <div id="profile-tab" class="tab-content">
                        <div class="detail-section">
                            <h3>Your Information</h3>
                            <form method="POST" id="profile-form">
                                <div class="profile-field" id="name-field">
                                    <p>
                                        <strong>Full Name:</strong>
                                        <span class="view-mode"><?= htmlspecialchars($user['name']) ?></span>
                                        <input type="text" name="name" class="edit-mode form-group" value="<?= htmlspecialchars($user['name']) ?>" required>
                                    </p>
                                </div>
                                <div class="profile-field" id="phone-field">
                                    <p>
                                        <strong>Phone Number:</strong>
                                        <span class="view-mode"><?= htmlspecialchars($user['phone'] ?: 'Not Provided') ?></span>
                                        <input type="tel" name="phone" class="edit-mode form-group" value="<?= htmlspecialchars($user['phone']) ?>" placeholder="Add phone number">
                                    </p>
                                </div>
                                <p>
                                    <strong>Email Address:</strong>
                                    <span><?= htmlspecialchars($user['email']) ?></span>
                                </p>

                                <div class="btn-group profile-buttons" style="margin-top:30px;">
                                    <button type="button" class="btn btn-primary view-mode-btn" id="edit-profile-btn">Edit Profile</button>
                                    <button type="submit" name="update_profile" class="btn btn-primary edit-mode-btn">Save Changes</button>
                                    <button type="button" class="btn btn-secondary edit-mode-btn" id="cancel-edit-btn">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div id="membership-tab" class="tab-content">
                        <div class="detail-section">
                            <h3>Membership Details</h3>
                            <p><strong>Plan:</strong> <span style="color:var(--primary-color); font-weight: bold;"><?= htmlspecialchars($user['membership']) ?></span></p>
                            <p><strong>Status:</strong> <span style="color: var(--success);">Active</span></p>
                            <p><strong>Member Since:</strong> <span><?= date('F j, Y', strtotime($user['join_date'])) ?></span></p>
                            <p><strong>Next Billing Date:</strong> <span><?= date('F j, Y', strtotime('+1 month')) ?></span></p>
                            <p><strong>Payment Method:</strong> <span>Credit Card **** 1234</span></p>
                            <button class="btn btn-primary">Upgrade Membership</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {

        // --- Membership Selection ---
        const membershipFeatures = {
            Basic: [ 'Standard Gym Access', 'Cardio & Weight Areas', 'Locker Rooms' ],
            Premium: [ 'All Basic Features', 'Access to All Group Classes', 'Sauna & Steam Room', '1 Free Personal Training Session' ],
            VIP: [ 'All Premium Features', 'Unlimited Personal Training', '24/7 Gym Access', 'Custom Nutrition Plan' ]
        };

        window.selectMembership = (selectedCard, type) => {
            document.querySelectorAll('.membership-card').forEach(card => card.classList.remove('selected'));
            selectedCard.classList.add('selected');
            document.getElementById('membership').value = type;

            const featureList = document.getElementById('feature-list');
            if(featureList){
                featureList.innerHTML = '';
                membershipFeatures[type].forEach(feature => {
                    const li = document.createElement('li');
                    li.textContent = feature;
                    featureList.appendChild(li);
                });
            }
        };

        if(document.querySelector('.membership-card h3')){
            const premiumCard = Array.from(document.querySelectorAll('.membership-card')).find(card => card.querySelector('h3').textContent === 'Premium');
            if (premiumCard) selectMembership(premiumCard, 'Premium');
        }

        // --- Tab Navigation ---
        const tabs = document.querySelectorAll('.nav-tab');
        const tabContents = document.querySelectorAll('.tab-content');
        const urlParams = new URLSearchParams(window.location.search);
        const activeTabName = urlParams.get('tab') || 'dashboard';

        let tabNameToActivate = `${activeTabName}-tab`;
        // Fallback to dashboard if the specified tab doesn't exist
        if (!document.getElementById(tabNameToActivate)) {
            tabNameToActivate = 'dashboard-tab';
        }

        tabs.forEach(t => t.classList.remove('active'));
        tabContents.forEach(tc => tc.classList.remove('active'));

        document.querySelector(`.nav-tab[data-tab="${tabNameToActivate}"]`).classList.add('active');
        document.getElementById(tabNameToActivate).classList.add('active');

        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                tabs.forEach(t => t.classList.remove('active'));
                tab.classList.add('active');

                const target = document.getElementById(tab.dataset.tab);
                tabContents.forEach(tc => tc.classList.remove('active'));
                if (target) target.classList.add('active');
            });
        });


        // --- Profile Edit Toggle ---
        const editProfileBtn = document.getElementById('edit-profile-btn');
        const cancelEditBtn = document.getElementById('cancel-edit-btn');
        const profileFields = document.querySelectorAll('.profile-field');
        const profileButtons = document.querySelector('.profile-buttons');

        if (editProfileBtn) {
            editProfileBtn.addEventListener('click', () => {
                profileFields.forEach(field => field.classList.add('editing'));
                profileButtons.classList.add('editing');
            });
        }

        if (cancelEditBtn) {
            cancelEditBtn.addEventListener('click', () => {
                profileFields.forEach(field => field.classList.remove('editing'));
                profileButtons.classList.remove('editing');
                // Optional: reset form values to original if needed
                document.getElementById('profile-form').reset();
            });
        }


        // --- Password Confirmation Check ---
        const newPassword = document.getElementById('new-password');
        const confirmPassword = document.getElementById('confirm-password');
        const msgDiv = document.getElementById('password-match-msg');
        const resetButton = document.getElementById('reset-button');
        const resetForm = document.getElementById('reset-form');

        if (newPassword && confirmPassword) {
            const validatePassword = () => {
                if (newPassword.value.length > 0 && newPassword.value === confirmPassword.value) {
                    msgDiv.textContent = 'Passwords match!';
                    msgDiv.style.color = 'var(--success)';
                    if(resetButton) resetButton.disabled = false;
                } else if (confirmPassword.value.length > 0) {
                    msgDiv.textContent = 'Passwords do not match.';
                    msgDiv.style.color = 'var(--danger)';
                    if(resetButton) resetButton.disabled = true;
                } else {
                    msgDiv.textContent = '';
                    if(resetButton) resetButton.disabled = true;
                }
            };

            newPassword.addEventListener('keyup', validatePassword);
            confirmPassword.addEventListener('keyup', validatePassword);

            if(resetForm) {
                resetForm.addEventListener('submit', (e) => {
                    if (newPassword.value !== confirmPassword.value) {
                        e.preventDefault();
                        alert('Passwords do not match. Please correct them before submitting.');
                    }
                });
            }
        }
    });
</script>
</body>
</html>