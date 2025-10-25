<?php
// ملف إعداد نظام المصادقة - auth_setup.php
require_once 'functions.php';

function createAuthTables() {
    $pdo = getDBConnection();
    
    // إنشاء جدول المستخدمين
    $createUsersTable = "
    CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_code VARCHAR(5) UNIQUE NOT NULL,
        name VARCHAR(100) NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_login TIMESTAMP NULL,
        login_count INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        INDEX idx_user_code (user_code),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    // إنشاء جدول جلسات المستخدمين
    $createSessionsTable = "
    CREATE TABLE IF NOT EXISTS user_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        session_token VARCHAR(64) UNIQUE NOT NULL,
        expires_at TIMESTAMP NOT NULL,
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_session_token (session_token),
        INDEX idx_expires_at (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    // إنشاء جدول إحصائيات الاختبارات للمستخدمين
    $createUserStatsTable = "
    CREATE TABLE IF NOT EXISTS user_quiz_stats (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        quiz_session_id VARCHAR(255) NOT NULL,
        score INT NOT NULL,
        total_questions INT NOT NULL,
        score_percentage DECIMAL(5,2) NOT NULL,
        completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        time_taken INT DEFAULT NULL COMMENT 'بالثواني',
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_id (user_id),
        INDEX idx_completed_at (completed_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    try {
        $pdo->exec($createUsersTable);
        $pdo->exec($createSessionsTable);
        $pdo->exec($createUserStatsTable);
        
        echo "<div class='status-good'>✅ تم إنشاء جداول نظام المصادقة بنجاح</div>";
        return true;
    } catch (PDOException $e) {
        echo "<div class='status-error'>❌ خطأ في إنشاء الجداول: " . $e->getMessage() . "</div>";
        return false;
    }
}

// دالة إنشاء كود مستخدم فريد
function generateUniqueUserCode() {
    $pdo = getDBConnection();
    $maxAttempts = 100;
    
    for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
        // إنشاء كود من 5 أرقام
        $code = str_pad(rand(10000, 99999), 5, '0', STR_PAD_LEFT);
        
        // التحقق من عدم وجود الكود
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE user_code = ?");
        $stmt->execute([$code]);
        
        if ($stmt->fetchColumn() == 0) {
            return $code;
        }
    }
    
    throw new Exception('فشل في إنشاء كود فريد بعد ' . $maxAttempts . ' محاولة');
}

// دالة تسجيل مستخدم جديد
function registerUser($name, $password) {
    if (empty($name) || strlen($name) < 2) {
        return ['success' => false, 'message' => 'الاسم يجب أن يكون حرفين على الأقل'];
    }
    
    if (empty($password) || strlen($password) < 4) {
        return ['success' => false, 'message' => 'الرقم السري يجب أن يكون 4 أرقام على الأقل'];
    }
    
    // التحقق من أن الرقم السري أرقام فقط
    if (!preg_match('/^\d+$/', $password)) {
        return ['success' => false, 'message' => 'الرقم السري يجب أن يحتوي على أرقام فقط'];
    }
    
    try {
        $pdo = getDBConnection();
        
        // إنشاء كود فريد
        $userCode = generateUniqueUserCode();
        
        // تشفير الرقم السري
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        // إدراج المستخدم الجديد
        $stmt = $pdo->prepare("INSERT INTO users (user_code, name, password_hash) VALUES (?, ?, ?)");
        $result = $stmt->execute([$userCode, $name, $passwordHash]);
        
        if ($result) {
            return [
                'success' => true, 
                'message' => 'تم إنشاء الحساب بنجاح',
                'user_code' => $userCode,
                'user_id' => $pdo->lastInsertId()
            ];
        } else {
            return ['success' => false, 'message' => 'حدث خطأ في إنشاء الحساب'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'خطأ: ' . $e->getMessage()];
    }
}

// دالة تسجيل الدخول
function loginUser($userCode, $password) {
    if (empty($userCode) || strlen($userCode) != 5) {
        return ['success' => false, 'message' => 'كود المستخدم يجب أن يكون 5 أرقام'];
    }
    
    if (empty($password)) {
        return ['success' => false, 'message' => 'يجب إدخال الرقم السري'];
    }
    
    try {
        $pdo = getDBConnection();
        
        // البحث عن المستخدم
        $stmt = $pdo->prepare("SELECT id, name, password_hash, is_active FROM users WHERE user_code = ?");
        $stmt->execute([$userCode]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return ['success' => false, 'message' => 'كود المستخدم غير صحيح'];
        }
        
        if (!$user['is_active']) {
            return ['success' => false, 'message' => 'الحساب معطل'];
        }
        
        // التحقق من الرقم السري
        if (!password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'message' => 'الرقم السري غير صحيح'];
        }
        
        // إنشاء رمز الجلسة
        $sessionToken = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + (24 * 60 * 60)); // 24 ساعة
        
        // حفظ الجلسة
        $stmt = $pdo->prepare("INSERT INTO user_sessions (user_id, session_token, expires_at, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $user['id'],
            $sessionToken,
            $expiresAt,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
        
        // تحديث آخر تسجيل دخول
        $stmt = $pdo->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP, login_count = login_count + 1 WHERE id = ?");
        $stmt->execute([$user['id']]);
        
        // حفظ بيانات الجلسة
        session_start();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_code'] = $userCode;
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['session_token'] = $sessionToken;
        
        return [
            'success' => true,
            'message' => 'تم تسجيل الدخول بنجاح',
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'code' => $userCode
            ]
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'خطأ: ' . $e->getMessage()];
    }
}

// دالة التحقق من صحة الجلسة
function validateSession() {
    session_start();
    
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_token'])) {
        return false;
    }
    
    try {
        $pdo = getDBConnection();
        
        $stmt = $pdo->prepare("
            SELECT u.id, u.name, u.user_code, s.expires_at 
            FROM users u 
            JOIN user_sessions s ON u.id = s.user_id 
            WHERE s.session_token = ? AND s.expires_at > NOW() AND u.is_active = 1
        ");
        $stmt->execute([$_SESSION['session_token']]);
        $session = $stmt->fetch();
        
        if ($session) {
            // تحديث بيانات الجلسة
            $_SESSION['user_name'] = $session['name'];
            $_SESSION['user_code'] = $session['user_code'];
            return $session;
        }
        
        // جلسة منتهية الصلاحية
        logoutUser();
        return false;
        
    } catch (Exception $e) {
        error_log("خطأ في التحقق من الجلسة: " . $e->getMessage());
        return false;
    }
}

// دالة تسجيل الخروج
function logoutUser() {
    session_start();
    
    // حذف الجلسة من قاعدة البيانات
    if (isset($_SESSION['session_token'])) {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE session_token = ?");
            $stmt->execute([$_SESSION['session_token']]);
        } catch (Exception $e) {
            error_log("خطأ في حذف الجلسة: " . $e->getMessage());
        }
    }
    
    // تدمير الجلسة
    session_destroy();
    session_unset();
}

// دالة حفظ نتيجة الاختبار للمستخدم
function saveUserQuizResult($userId, $sessionId, $score, $totalQuestions, $timeTaken = null) {
    try {
        $pdo = getDBConnection();
        $percentage = ($score / $totalQuestions) * 100;
        
        $stmt = $pdo->prepare("
            INSERT INTO user_quiz_stats (user_id, quiz_session_id, score, total_questions, score_percentage, time_taken)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([$userId, $sessionId, $score, $totalQuestions, $percentage, $timeTaken]);
        
    } catch (Exception $e) {
        error_log("خطأ في حفظ نتيجة الاختبار: " . $e->getMessage());
        return false;
    }
}

// دالة الحصول على إحصائيات المستخدم
function getUserStats($userId) {
    try {
        $pdo = getDBConnection();
        
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_attempts,
                AVG(score_percentage) as avg_score,
                MAX(score_percentage) as best_score,
                MIN(score_percentage) as lowest_score,
                AVG(time_taken) as avg_time,
                MAX(completed_at) as last_attempt
            FROM user_quiz_stats 
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        
        return $stmt->fetch();
        
    } catch (Exception $e) {
        error_log("خطأ في جلب إحصائيات المستخدم: " . $e->getMessage());
        return null;
    }
}

// دالة تنظيف الجلسات المنتهية الصلاحية
function cleanExpiredSessions() {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE expires_at < NOW()");
        $deleted = $stmt->execute();
        return $stmt->rowCount();
    } catch (Exception $e) {
        error_log("خطأ في تنظيف الجلسات: " . $e->getMessage());
        return 0;
    }
}

// تشغيل إعداد النظام
if (basename($_SERVER['PHP_SELF']) === 'auth_setup.php') {
    echo "<!DOCTYPE html>";
    echo "<html lang='ar' dir='rtl'>";
    echo "<head><meta charset='UTF-8'><title>إعداد نظام المصادقة</title>";
    echo "<style>body{font-family:Arial;margin:20px;} .status-good{color:green;background:#d4edda;padding:10px;border-radius:5px;margin:5px 0;} .status-error{color:red;background:#f8d7da;padding:10px;border-radius:5px;margin:5px 0;}</style>";
    echo "</head><body>";
    echo "<h1>🔐 إعداد نظام المصادقة</h1>";
    
    if (createAuthTables()) {
        echo "<div class='status-good'>✅ تم إعداد النظام بنجاح</div>";
        echo "<p><a href='auth.php'>انتقل لصفحة التسجيل</a></p>";
        echo "<p><a href='index.php'>انتقل للاختبار</a></p>";
    }
    
    echo "</body></html>";
}
?>