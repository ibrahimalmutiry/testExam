<?php
// logout.php - ملف تسجيل الخروج
require_once 'auth_protection.php';

logoutUser();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الخروج - نظام الاختبارات</title>
    <link rel="stylesheet" href="styles.css">
    <meta http-equiv="refresh" content="3;url=auth.php">
    <style>
        .logout-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            text-align: center;
            color: white;
        }
        
        .logout-message {
            background: white;
            color: var(--text-primary);
            padding: 3rem;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            max-width: 400px;
        }
        
        .logout-icon {
            font-size: 4rem;
            color: var(--success-color);
            margin-bottom: 1rem;
        }
        
        .countdown {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin: 1rem 0;
        }
    </style>
</head>
<body>
    <div class="logout-container">
        <div class="logout-message">
            <i class="fas fa-check-circle logout-icon"></i>
            <h2>تم تسجيل الخروج بنجاح</h2>
            <p>شكراً لاستخدامك نظام الاختبارات</p>
            <div class="countdown" id="countdown">3</div>
            <p>سيتم توجيهك لصفحة تسجيل الدخول خلال <span id="seconds">3</span> ثواني</p>
            <a href="auth.php" class="btn btn-primary" style="margin-top: 1rem; display: inline-block; padding: 0.75rem 1.5rem; text-decoration: none; border-radius: 8px;">
                الذهاب الآن
            </a>
        </div>
    </div>
    
    <script>
        let seconds = 3;
        const countdownEl = document.getElementById('countdown');
        const secondsEl = document.getElementById('seconds');
        
        const timer = setInterval(() => {
            seconds--;
            countdownEl.textContent = seconds;
            secondsEl.textContent = seconds;
            
            if (seconds === 0) {
                clearInterval(timer);
                window.location.href = 'auth.php';
            }
        }, 1000);
    </script>
</body>
</html>