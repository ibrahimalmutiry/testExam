<?php
require_once 'auth_setup.php';

$message = '';
$messageType = '';
$userCode = '';

// معالجة النماذج
if ($_POST) {
    if (isset($_POST['register'])) {
        $name = trim($_POST['name']);
        $password = trim($_POST['password']);
        $confirmPassword = trim($_POST['confirm_password']);
        
        if ($password !== $confirmPassword) {
            $message = 'الرقم السري وتأكيده غير متطابقين';
            $messageType = 'error';
        } else {
            $result = registerUser($name, $password);
            $message = $result['message'];
            $messageType = $result['success'] ? 'success' : 'error';
            
            if ($result['success']) {
                $userCode = $result['user_code'];
            }
        }
    }
    
    if (isset($_POST['login'])) {
        $loginCode = trim($_POST['user_code']);
        $loginPassword = trim($_POST['password']);
        
        $result = loginUser($loginCode, $loginPassword);
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';
        
        if ($result['success']) {
            header('Location: index.php');
            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - نظام الاختبارات</title>
    <link rel="stylesheet" href="styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .auth-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 2rem;
        }
        
        .auth-wrapper {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            width: 100%;
            max-width: 900px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 600px;
        }
        
        .auth-sidebar {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        .auth-sidebar::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="2" fill="white" opacity="0.1"/><circle cx="80" cy="30" r="1" fill="white" opacity="0.15"/><circle cx="40" cy="60" r="1.5" fill="white" opacity="0.1"/><circle cx="70" cy="80" r="1" fill="white" opacity="0.2"/></svg>');
            opacity: 0.3;
        }
        
        .auth-sidebar h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            position: relative;
            z-index: 2;
        }
        
        .auth-sidebar p {
            font-size: 1.1rem;
            opacity: 0.9;
            line-height: 1.6;
            position: relative;
            z-index: 2;
        }
        
        .auth-forms {
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .form-tabs {
            display: flex;
            margin-bottom: 2rem;
            background: #f8f9fa;
            border-radius: 12px;
            padding: 0.5rem;
        }
        
        .tab-button {
            flex: 1;
            padding: 1rem;
            border: none;
            background: transparent;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-secondary);
            transition: all 0.3s ease;
        }
        
        .tab-button.active {
            background: white;
            color: var(--primary-color);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .form-container {
            display: none;
        }
        
        .form-container.active {
            display: block;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }
        
        .input-group {
            position: relative;
        }
        
        .input-group i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            font-size: 1.1rem;
        }
        
        .form-control {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
            border: 2px solid var(--medium-gray);
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .form-control.numeric {
            font-family: 'Courier New', monospace;
            font-size: 1.2rem;
            font-weight: 600;
            letter-spacing: 2px;
            text-align: center;
        }
        
        .btn-auth {
            width: 100%;
            padding: 1rem 2rem;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 1rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(59, 130, 246, 0.4);
        }
        
        .btn-secondary {
            background: var(--medium-gray);
            color: var(--text-primary);
        }
        
        .btn-secondary:hover {
            background: var(--secondary-color);
            color: white;
        }
        
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 500;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .user-code-display {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            border: 2px solid #f6e05e;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            margin-top: 1rem;
        }
        
        .user-code-display .code {
            font-size: 2rem;
            font-weight: 800;
            font-family: 'Courier New', monospace;
            color: var(--accent-color);
            letter-spacing: 4px;
            margin: 1rem 0;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .form-footer {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--medium-gray);
            text-align: center;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .password-strength {
            margin-top: 0.5rem;
            font-size: 0.8rem;
        }
        
        .strength-weak { color: var(--danger-color); }
        .strength-medium { color: var(--warning-color); }
        .strength-strong { color: var(--success-color); }
        
        @media (max-width: 768px) {
            .auth-wrapper {
                grid-template-columns: 1fr;
                margin: 1rem;
            }
            
            .auth-sidebar {
                padding: 2rem;
                text-align: center;
            }
            
            .auth-sidebar h1 {
                font-size: 2rem;
            }
            
            .auth-forms {
                padding: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-wrapper">
            <div class="auth-sidebar">
                <div>
                    <i class="fas fa-graduation-cap" style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.9;"></i>
                    <h1>نظام الاختبارات</h1>
                    <p>سجل دخولك للوصول إلى الاختبارات التفاعلية والحصول على تقييم شامل لمستواك التعليمي</p>
                    
                    <div style="margin-top: 2rem; padding: 1.5rem; background: rgba(255, 255, 255, 0.1); border-radius: 12px; position: relative; z-index: 2;">
                        <h4 style="margin-bottom: 1rem;">المزايا:</h4>
                        <ul style="list-style: none; padding: 0;">
                            <li style="margin: 0.5rem 0;"><i class="fas fa-check" style="color: #2ecc71; margin-left: 0.5rem;"></i> تتبع النتائج</li>
                            <li style="margin: 0.5rem 0;"><i class="fas fa-check" style="color: #2ecc71; margin-left: 0.5rem;"></i> إحصائيات مفصلة</li>
                            <li style="margin: 0.5rem 0;"><i class="fas fa-check" style="color: #2ecc71; margin-left: 0.5rem;"></i> حفظ التقدم</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="auth-forms">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?>">
                        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($userCode): ?>
                    <div class="user-code-display">
                        <h3>تم إنشاء حسابك بنجاح!</h3>
                        <p>كودك الشخصي هو:</p>
                        <div class="code"><?php echo $userCode; ?></div>
                        <p style="color: var(--danger-color); font-weight: 600;">
                            <i class="fas fa-exclamation-triangle"></i>
                            احفظ هذا الكود في مكان آمن - ستحتاجه لتسجيل الدخول
                        </p>
                        <div style="display: flex; gap: 1rem; justify-content: center; margin-top: 1rem;">
                            <button class="btn btn-secondary" onclick="copyToClipboard('<?php echo $userCode; ?>')">
                                <i class="fas fa-copy"></i> نسخ الكود
                            </button>
                            <a href="profile.php" class="btn btn-primary">
                                <i class="fas fa-user"></i> الذهاب للملف الشخصي
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="form-tabs">
                    <button class="tab-button active" onclick="switchTab('login')">
                        <i class="fas fa-sign-in-alt"></i> تسجيل الدخول
                    </button>
                    <button class="tab-button" onclick="switchTab('register')">
                        <i class="fas fa-user-plus"></i> حساب جديد
                    </button>
                </div>
                
                <!-- نموذج تسجيل الدخول -->
                <div id="login-form" class="form-container active">
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="user_code">كود المستخدم (5 أرقام)</label>
                            <div class="input-group">
                                <i class="fas fa-hashtag"></i>
                                <input type="text" id="user_code" name="user_code" class="form-control numeric" 
                                       placeholder="12345" maxlength="5" pattern="\d{5}" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="login_password">الرقم السري</label>
                            <div class="input-group">
                                <i class="fas fa-lock"></i>
                                <input type="password" id="login_password" name="password" class="form-control numeric" 
                                       placeholder="****" required>
                            </div>
                        </div>
                        
                        <button type="submit" name="login" class="btn-auth btn-primary">
                            <i class="fas fa-sign-in-alt"></i>
                            دخول النظام
                        </button>
                    </form>
                </div>
                
                <!-- نموذج التسجيل -->
                <div id="register-form" class="form-container">
                    <form method="POST" action="" id="registerForm">
                        <div class="form-group">
                            <label for="name">الاسم الكامل</label>
                            <div class="input-group">
                                <i class="fas fa-user"></i>
                                <input type="text" id="name" name="name" class="form-control" 
                                       placeholder="أدخل اسمك الكامل" minlength="2" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">الرقم السري (أرقام فقط)</label>
                            <div class="input-group">
                                <i class="fas fa-lock"></i>
                                <input type="password" id="password" name="password" class="form-control numeric" 
                                       placeholder="أدخل رقماً سرياً" minlength="4" pattern="\d+" required>
                            </div>
                            <div class="password-strength" id="passwordStrength"></div>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">تأكيد الرقم السري</label>
                            <div class="input-group">
                                <i class="fas fa-lock"></i>
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control numeric" 
                                       placeholder="أعد إدخال الرقم السري" required>
                            </div>
                            <div class="password-match" id="passwordMatch"></div>
                        </div>
                        
                        <button type="submit" name="register" class="btn-auth btn-primary">
                            <i class="fas fa-user-plus"></i>
                            إنشاء حساب جديد
                        </button>
                    </form>
                </div>
                
                <div class="form-footer">
                    <p>
                        <i class="fas fa-shield-alt"></i>
                        جميع بياناتك محمية ومشفرة
                    </p>
                    <p style="margin-top: 1rem;">
                        <a href="index.php" style="color: var(--primary-color); text-decoration: none;">
                            <i class="fas fa-arrow-right"></i>
                            الدخول كضيف (بدون حفظ النتائج)
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // التبديل بين نماذج التسجيل وتسجيل الدخول
        function switchTab(tabName) {
            // إزالة الفئة النشطة من جميع الأزرار
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // إزالة الفئة النشطة من جميع النماذج
            document.querySelectorAll('.form-container').forEach(form => {
                form.classList.remove('active');
            });
            
            // تفعيل الزر والنموذج المحدد
            event.target.classList.add('active');
            document.getElementById(tabName + '-form').classList.add('active');
        }
        
        // التحقق من قوة الرقم السري
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthDiv = document.getElementById('passwordStrength');
            
            if (password.length === 0) {
                strengthDiv.textContent = '';
                return;
            }
            
            if (!/^\d+$/.test(password)) {
                strengthDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> يجب أن يحتوي على أرقام فقط';
                strengthDiv.className = 'password-strength strength-weak';
                return;
            }
            
            if (password.length < 4) {
                strengthDiv.innerHTML = '<i class="fas fa-times"></i> ضعيف - أقل من 4 أرقام';
                strengthDiv.className = 'password-strength strength-weak';
            } else if (password.length < 6) {
                strengthDiv.innerHTML = '<i class="fas fa-check"></i> متوسط - يفضل 6 أرقام أو أكثر';
                strengthDiv.className = 'password-strength strength-medium';
            } else {
                strengthDiv.innerHTML = '<i class="fas fa-check-double"></i> قوي';
                strengthDiv.className = 'password-strength strength-strong';
            }
        });
        
        // التحقق من تطابق الرقم السري
        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchDiv = document.getElementById('passwordMatch');
            
            if (confirmPassword.length === 0) {
                matchDiv.textContent = '';
                return;
            }
            
            if (password === confirmPassword) {
                matchDiv.innerHTML = '<i class="fas fa-check"></i> الأرقام متطابقة';
                matchDiv.className = 'password-match strength-strong';
            } else {
                matchDiv.innerHTML = '<i class="fas fa-times"></i> الأرقام غير متطابقة';
                matchDiv.className = 'password-match strength-weak';
            }
        }
        
        document.getElementById('confirm_password').addEventListener('input', checkPasswordMatch);
        document.getElementById('password').addEventListener('input', function() {
            if (document.getElementById('confirm_password').value.length > 0) {
                checkPasswordMatch();
            }
        });
        
        // التحقق من صحة كود المستخدم
        document.getElementById('user_code').addEventListener('input', function() {
            let value = this.value.replace(/\D/g, ''); // إزالة غير الأرقام
            if (value.length > 5) {
                value = value.substring(0, 5);
            }
            this.value = value;
        });
        
        // التحقق من الأرقام فقط في الرقم السري
        document.querySelectorAll('input.numeric').forEach(input => {
            input.addEventListener('input', function() {
                if (this.type === 'password') return; // لا نطبق على كود المستخدم فقط
                this.value = this.value.replace(/\D/g, '');
            });
        });
        
        // نسخ الكود إلى الحافظة
        function copyToClipboard(text) {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(function() {
                    showCopySuccess();
                }).catch(function() {
                    fallbackCopyToClipboard(text);
                });
            } else {
                fallbackCopyToClipboard(text);
            }
        }
        
        function fallbackCopyToClipboard(text) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                const successful = document.execCommand('copy');
                if (successful) {
                    showCopySuccess();
                }
            } catch (err) {
                alert('فشل في نسخ الكود. قم بنسخه يدوياً');
            } finally {
                document.body.removeChild(textArea);
            }
        }
        
        function showCopySuccess() {
            const btn = event.target;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check"></i> تم النسخ!';
            btn.style.background = 'var(--success-color)';
            
            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.style.background = '';
            }, 2000);
        }
        
        // تحسين تجربة المستخدم
        document.addEventListener('DOMContentLoaded', function() {
            // تركيز تلقائي على أول حقل
            const firstInput = document.querySelector('.form-container.active input');
            if (firstInput) {
                firstInput.focus();
            }
            
            // تأثيرات الانتقال
            document.querySelectorAll('.form-control').forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentNode.style.transform = 'scale(1.02)';
                    this.parentNode.style.transition = 'transform 0.2s ease';
                });
                
                input.addEventListener('blur', function() {
                    this.parentNode.style.transform = 'scale(1)';
                });
            });
            
            // منع إرسال النموذج إذا كانت الأرقام غير متطابقة
            document.getElementById('registerForm').addEventListener('submit', function(e) {
                const password = document.getElementById('password').value;
                const confirmPassword = document.getElementById('confirm_password').value;
                
                if (password !== confirmPassword) {
                    e.preventDefault();
                    alert('الرقم السري وتأكيده غير متطابقين');
                    document.getElementById('confirm_password').focus();
                    return false;
                }
                
                if (!/^\d+$/.test(password)) {
                    e.preventDefault();
                    alert('الرقم السري يجب أن يحتوي على أرقام فقط');
                    document.getElementById('password').focus();
                    return false;
                }
            });
        });
        
        // إضافة مؤثرات صوتية (اختيارية)
        function playClickSound() {
            // يمكن إضافة صوت نقر هنا
        }
        
        document.querySelectorAll('.btn-auth').forEach(btn => {
            btn.addEventListener('click', playClickSound);
        });
    </script>
</body>
</html>