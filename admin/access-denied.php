<!DOCTYPE html>
<html lang="en" dir="ltr" data-startbar="light" data-bs-theme="light">

<head>
    <meta charset="utf-8" />
    <title>Access Denied | Silky Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta content="" name="description" />
    <meta content="" name="author" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />

    <!-- App favicon -->
    <link rel="shortcut icon" href="../assets/img/silky-jpg.jpg">

    <!-- App css -->
    <link href="assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />

    <!-- Dark Mode State Check Script -->
    <script>
        // Check and apply saved theme before page loads
        (function() {
            const savedTheme = localStorage.getItem('silky_admin_theme');
            if (savedTheme) {
                document.documentElement.setAttribute('data-bs-theme', savedTheme);
            }
        })();
    </script>

    <style>
        .access-denied-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .access-denied-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 3rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            max-width: 500px;
            text-align: center;
        }

        .access-denied-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #ff6b6b, #ee5a52);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            color: white;
            font-size: 3rem;
        }

        .access-denied-title {
            color: #2d3748;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .access-denied-message {
            color: #718096;
            font-size: 1.1rem;
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .back-btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            color: white;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
            color: white;
        }

        /* Dark Mode Support */
        [data-bs-theme="dark"] .access-denied-card {
            background: rgba(45, 55, 72, 0.95);
            color: #e2e8f0;
        }

        [data-bs-theme="dark"] .access-denied-title {
            color: #e2e8f0;
        }

        [data-bs-theme="dark"] .access-denied-message {
            color: #a0aec0;
        }
    </style>
</head>

<body>
    <div class="access-denied-container">
        <div class="access-denied-card">
            <div class="access-denied-icon">
                <i class="iconoir-lock"></i>
            </div>
            
            <h1 class="access-denied-title">Access Denied</h1>
            
            <p class="access-denied-message">
                Sorry, you don't have permission to access this page. Your current role doesn't include the required functionality to view this content.
            </p>
            
            <p class="text-muted mb-4">
                <small>If you believe this is an error, please contact your administrator.</small>
            </p>
            
            <a href="index.php" class="back-btn">
                <i class="iconoir-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Javascript  -->
    <script src="assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/libs/simplebar/simplebar.min.js"></script>
    <script src="assets/js/app.js"></script>
    
    <!-- Theme Manager for Dark Mode Persistence -->
    <script src="assets/js/theme-manager.js"></script>
</body>

</html>