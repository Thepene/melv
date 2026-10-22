<?php
require 'config.php';
session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'login';

    if ($action === 'register') {
        $full_name = trim($_POST['full_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'staff';

        if ($full_name === '' || $username === '' || $email === '' || $password === '') {
            $error = 'All fields are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format.';
        } else {
            $users = loadJsonStore('users', []);
            foreach ($users as $user) {
                if (($user['username'] ?? '') === $username || ($user['email'] ?? '') === $email) {
                    $error = 'Username or email already exists.';
                    break;
                }
            }

            if ($error === '') {
                $users[] = [
                    'id' => nextJsonId($users),
                    'full_name' => $full_name,
                    'username' => $username,
                    'email' => $email,
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                    'role' => $role,
                    'created_at' => date('Y-m-d H:i:s'),
                ];

                if (saveJsonStore('users', $users)) {
                    $success = 'Registration successful. You can now log in.';
                } else {
                    $error = 'Registration failed. Please try again.';
                }
            }
        }
    }

    if ($action === 'login') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $error = 'Username and password are required.';
        } else {
            $users = loadJsonStore('users', []);
            $matchedUser = null;

            foreach ($users as $user) {
                if (($user['username'] ?? '') === $username || ($user['email'] ?? '') === $username) {
                    $matchedUser = $user;
                    break;
                }
            }

            if ($matchedUser && password_verify($password, $matchedUser['password'])) {
                $_SESSION['user_id'] = (int) $matchedUser['id'];
                $_SESSION['username'] = $matchedUser['username'];
                $_SESSION['full_name'] = $matchedUser['full_name'];
                $_SESSION['role'] = $matchedUser['role'];
                header('Location: dashboard.php');
                exit();
            }

            $error = 'Invalid username/email or password.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Inventory Pro</title>
    <style>
        :root {
            --bg1: #eff6ff;
            --bg2: #f8fafc;
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --card: rgba(255,255,255,0.9);
            --line: #dbeafe;
            --muted: #475569;
            --text: #0f172a;
            --danger: #dc2626;
            --success: #15803d;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, var(--bg1), var(--bg2));
            color: var(--text);
        }

        .container {
            width: min(1100px, 92vw);
            display: grid;
            grid-template-columns: 1.1fr 1fr;
            gap: 28px;
            background: var(--card);
            border: 1px solid #e2e8f0;
            border-radius: 24px;
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }

        .hero {
            background: linear-gradient(135deg, #0f172a, #1d4ed8);
            color: white;
            padding: 48px 36px;
        }

        .hero h1 {
            margin: 0 0 18px;
            font-size: 2.2rem;
        }

        .hero p {
            line-height: 1.7;
            opacity: 0.9;
        }

        .features {
            margin-top: 26px;
            display: grid;
            gap: 14px;
        }

        .feature {
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 14px;
            padding: 12px 14px;
        }

        .form-panel {
            padding: 36px 28px;
        }

        .tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 18px;
        }

        .tab {
            flex: 1;
            padding: 12px;
            border: 1px solid var(--line);
            border-radius: 12px;
            background: #f8fafc;
            text-align: center;
            font-weight: 700;
            color: var(--muted);
            cursor: pointer;
        }

        .tab.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .panel {
            display: none;
        }

        .panel.active {
            display: block;
        }

        form {
            display: grid;
            gap: 14px;
        }

        input, select, button {
            width: 100%;
            padding: 12px 14px;
            border-radius: 12px;
            border: 1px solid var(--line);
            font-size: 14px;
            color: var(--text);
            background: white;
        }

        button {
            border: none;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            font-weight: 700;
            cursor: pointer;
        }

        .alert {
            padding: 12px 14px;
            border-radius: 12px;
            font-weight: 600;
            margin-bottom: 12px;
        }

        .alert.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert.success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        @media (max-width: 820px) {
            .container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <section class="hero">
            <h1>Inventory Pro</h1>
            <p>Track stock, manage suppliers, monitor products, and keep your warehouse operations efficient with a clean dashboard built for fast daily inventory decisions.</p>
            <div class="features">
                <div class="feature">Real-time product stock visibility</div>
                <div class="feature">Supplier and transaction management</div>
                <div class="feature">Role-based access for staff, managers, and admins</div>
            </div>
        </section>

        <section class="form-panel">
            <div class="tabs">
                <div class="tab active" data-tab="login">Login</div>
                <div class="tab" data-tab="register">Register</div>
            </div>

            <?php if ($error !== ''): ?>
                <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success !== ''): ?>
                <div class="alert success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <div id="login" class="panel active">
                <form method="POST">
                    <input type="hidden" name="action" value="login">
                    <input type="text" name="username" placeholder="Username or email" required>
                    <input type="password" name="password" placeholder="Password" required>
                    <button type="submit">Login</button>
                </form>
            </div>

            <div id="register" class="panel">
                <form method="POST">
                    <input type="hidden" name="action" value="register">
                    <input type="text" name="full_name" placeholder="Full name" required>
                    <input type="text" name="username" placeholder="Username" required>
                    <input type="email" name="email" placeholder="Email" required>
                    <input type="password" name="password" placeholder="Password" required>
                    <select name="role">
                        <option value="staff">Staff</option>
                        <option value="manager">Manager</option>
                        <option value="admin">Admin</option>
                    </select>
                    <button type="submit">Create Account</button>
                </form>
            </div>
        </section>
    </div>

    <script>
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
                tab.classList.add('active');
                document.getElementById(tab.dataset.tab).classList.add('active');
            });
        });
    </script>
</body>
</html>
