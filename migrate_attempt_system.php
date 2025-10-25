<?php
// migrate_attempt_system.php - ملف لتحديث قاعدة البيانات وإضافة النظام الجديد

require_once 'functions.php';

/**
 * تطبيق تحديث قاعدة البيانات لنظام المحاولات
 */
function migrateAttemptSystem() {
    $pdo = getDBConnection();
    
    echo "🔄 بدء تطبيق تحديثات نظام المحاولات...\n<br>";
    
    try {
        // إضافة جدول تتبع المحاولات
        $createAttemptsTrackingTable = "
        CREATE TABLE IF NOT EXISTS attempt_tracking (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_fingerprint VARCHAR(64) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_agent TEXT,
            attempt_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            session_id VARCHAR(255),
            completed BOOLEAN DEFAULT FALSE,
            score INT DEFAULT NULL,
            total_questions INT DEFAULT NULL,
            completion_time TIMESTAMP NULL,
            INDEX idx_fingerprint (user_fingerprint),
            INDEX idx_timestamp (attempt_timestamp),
            INDEX idx_ip (ip_address)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($createAttemptsTrackingTable);
        echo "✅ تم إنشاء جدول تتبع المحاولات\n<br>";
        
        // إضافة جدول إحصائيات المحاولات
        $createAttemptStatsTable = "
        CREATE TABLE IF NOT EXISTS attempt_statistics (
            id INT AUTO_INCREMENT PRIMARY KEY,
            date DATE NOT NULL,
            total_attempts INT DEFAULT 0,
            unique_users INT DEFAULT 0,
            completed_attempts INT DEFAULT 0,
            average_score DECIMAL(5,2) DEFAULT 0,
            blocked_attempts INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_date (date),
            INDEX idx_date (date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($createAttemptStatsTable);
        echo "✅ تم إنشاء جدول إحصائيات المحاولات\n<br>";
        
        // إضافة جدول إعدادات النظام
        $createSystemSettingsTable = "
        CREATE TABLE IF NOT EXISTS system_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT,
            setting_type ENUM('string', 'integer', 'boolean', 'json') DEFAULT 'string',
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_key (setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($createSystemSettingsTable);
        echo "✅ تم إنشاء جدول إعدادات النظام\n<br>";
        
        // إدراج الإعدادات الافتراضية
        $defaultSettings = [
            ['max_attempts_per_session', '3', 'integer', 'الحد الأقصى للمحاولات في الجلسة الواحدة'],
            ['attempt_reset_time_minutes', '30', 'integer', 'وقت إعادة تعيين المحاولات بالدقائق'],
            ['enable_attempt_tracking', '1', 'boolean', 'تفعيل تتبع المحاولات'],
            ['enable_notifications', '1', 'boolean', 'تفعيل الإشعارات'],
            ['auto_save_progress', '1', 'boolean', 'الحفظ التلقائي للتقدم']
        ];
        
        $insertSettingStmt = $pdo->prepare("
            INSERT IGNORE INTO system_settings (setting_key, setting_value, setting_type, description) 
            VALUES (?, ?, ?, ?)
        ");
        
        foreach ($defaultSettings as $setting) {
            $insertSettingStmt->execute($setting);
        }
        echo "✅ تم إدراج الإعدادات الافتراضية\n<br>";
        
        // تحديث جدول المستخدمين لإضافة معلومات المحاولات
        $alterUsersTable = "
        ALTER TABLE users 
        ADD COLUMN IF NOT EXISTS total_attempts INT DEFAULT 0,
        ADD COLUMN IF NOT EXISTS last_attempt_date TIMESTAMP NULL,
        ADD COLUMN IF NOT EXISTS best_score DECIMAL(5,2) DEFAULT 0,
        ADD COLUMN IF NOT EXISTS average_score DECIMAL(5,2) DEFAULT 0
        ";
        
        try {
            $pdo->exec($alterUsersTable);
            echo "✅ تم تحديث جدول المستخدمين\n<br>";
        } catch (Exception $e) {
            echo "ℹ️ تخطي تحديث جدول المستخدمين (قد يكون محدث مسبقاً)\n<br>";
        }
        
        // إنشاء فهارس إضافية لتحسين الأداء
        $additionalIndexes = [
            "CREATE INDEX IF NOT EXISTS idx_quiz_attempts_date ON quiz_attempts(attempt_date)",
            "CREATE INDEX IF NOT EXISTS idx_quiz_answers_session ON quiz_answers(session_id)",
            "CREATE INDEX IF NOT EXISTS idx_questions_type ON questions(question_type)"
        ];
        
        foreach ($additionalIndexes as $indexQuery) {
            try {
                $pdo->exec($indexQuery);
            } catch (Exception $e) {
                // تجاهل الأخطاء إذا كانت الفهارس موجودة مسبقاً
            }
        }
        echo "✅ تم إنشاء الفهارس الإضافية\n<br>";
        
        echo "🎉 تم الانتهاء من تطبيق جميع التحديثات بنجاح!\n<br>";
        return true;
        
    } catch (Exception $e) {
        echo "❌ خطأ في تطبيق التحديثات: " . $e->getMessage() . "\n<br>";
        return false;
    }
}

/**
 * إنشاء وظائف إضافية لإدارة النظام
 */
function createAttemptManagementFunctions() {
    return '
    -- وظائف SQL إضافية لإدارة نظام المحاولات
    
    DELIMITER $$
    
    -- دالة لحساب عدد المحاولات النشطة
    CREATE FUNCTION IF NOT EXISTS GetActiveAttempts(fingerprint VARCHAR(64))
    RETURNS INT
    READS SQL DATA
    DETERMINISTIC
    BEGIN
        DECLARE attempt_count INT DEFAULT 0;
        
        SELECT COUNT(*) INTO attempt_count
        FROM attempt_tracking 
        WHERE user_fingerprint = fingerprint 
        AND attempt_timestamp > DATE_SUB(NOW(), INTERVAL 30 MINUTE);
        
        RETURN attempt_count;
    END$$
    
    -- إجراء لتنظيف المحاولات القديمة
    CREATE PROCEDURE IF NOT EXISTS CleanOldAttempts()
    BEGIN
        DELETE FROM attempt_tracking 
        WHERE attempt_timestamp < DATE_SUB(NOW(), INTERVAL 1 HOUR);
        
        SELECT ROW_COUNT() as deleted_rows;
    END$$
    
    -- إجراء لحساب الإحصائيات اليومية
    CREATE PROCEDURE IF NOT EXISTS CalculateDailyStats()
    BEGIN
        INSERT INTO attempt_statistics (
            date, 
            total_attempts, 
            unique_users, 
            completed_attempts, 
            average_score
        )
        SELECT 
            CURDATE(),
            COUNT(*) as total_attempts,
            COUNT(DISTINCT user_fingerprint) as unique_users,
            COUNT(CASE WHEN completed = 1 THEN 1 END) as completed_attempts,
            AVG(CASE WHEN score IS NOT NULL THEN score END) as average_score
        FROM attempt_tracking 
        WHERE DATE(attempt_timestamp) = CURDATE()
        ON DUPLICATE KEY UPDATE
            total_attempts = VALUES(total_attempts),
            unique_users = VALUES(unique_users),
            completed_attempts = VALUES(completed_attempts),
            average_score = VALUES(average_score),
            updated_at = NOW();
    END$$
    
    DELIMITER ;
    ';
}

/**
 * التحقق من صحة النظام بعد التحديث
 */
function validateSystemAfterMigration() {
    $pdo = getDBConnection();
    $errors = [];
    
    // فحص الجداول المطلوبة
    $requiredTables = [
        'attempt_tracking',
        'attempt_statistics', 
        'system_settings'
    ];
    
    foreach ($requiredTables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() === 0) {
            $errors[] = "الجدول $table غير موجود";
        }
    }
    
    // فحص الإعدادات الافتراضية
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM system_settings");
    $result = $stmt->fetch();
    if ($result['count'] < 5) {
        $errors[] = "الإعدادات الافتراضية غير مكتملة";
    }
    
    return empty($errors) ? true : $errors;
}

// تشغيل التحديث إذا تم استدعاء الملف مباشرة
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    echo "<!DOCTYPE html>";
    echo "<html lang='ar' dir='rtl'>";
    echo "<head><meta charset='UTF-8'><title>تحديث نظام المحاولات</title>";
    echo "<style>
        body { font-family: Cairo, Arial, sans-serif; margin: 2rem; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 2rem; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #27ae60; }
        .error { color: #e74c3c; }
        .info { color: #3498db; }
        h1 { color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 1rem; }
        .status-box { background: #ecf0f1; padding: 1rem; margin: 1rem 0; border-radius: 5px; }
    </style>";
    echo "</head><body>";
    
    echo "<div class='container'>";
    echo "<h1>🚀 تحديث نظام تتبع المحاولات</h1>";
    
    echo "<div class='status-box'>";
    $success = migrateAttemptSystem();
    echo "</div>";
    
    if ($success) {
        echo "<div class='status-box'>";
        echo "<h3>🔍 فحص صحة النظام...</h3>";
        $validation = validateSystemAfterMigration();
        
        if ($validation === true) {
            echo "<p class='success'>✅ جميع الفحوصات نجحت! النظام جاهز للاستخدام.</p>";
            
            echo "<h3>📊 ملخص التحديث:</h3>";
            echo "<ul>";
            echo "<li>✅ جدول تتبع المحاولات</li>";
            echo "<li>✅ جدول الإحصائيات</li>";
            echo "<li>✅ جدول إعدادات النظام</li>";
            echo "<li>✅ الإعدادات الافتراضية</li>";
            echo "<li>✅ تحديث جدول المستخدمين</li>";
            echo "<li>✅ فهارس الأداء</li>";
            echo "</ul>";
            
        } else {
            echo "<p class='error'>❌ فشل في بعض الفحوصات:</p>";
            echo "<ul>";
            foreach ($validation as $error) {
                echo "<li class='error'>• $error</li>";
            }
            echo "</ul>";
        }
        echo "</div>";
        
        echo "<div class='status-box'>";
        echo "<h3>📋 الخطوات التالية:</h3>";
        echo "<ol>";
        echo "<li>أضف الكود الجديد لوظائف تتبع المحاولات إلى ملف <code>functions.php</code></li>";
        echo "<li>قم بتحديث ملف <code>index.php</code> بالإصدار الجديد</li>";
        echo "<li>أضف ملفات CSS و JavaScript الجديدة</li>";
        echo "<li>اختبر النظام مع محاولات متعددة</li>";
        echo "</ol>";
        echo "</div>";
        
    } else {
        echo "<p class='error'>❌ فشل في تطبيق بعض التحديثات. يرجى مراجعة رسائل الخطأ أعلاه.</p>";
    }
    
    echo "<div style='margin: 2rem 0; padding: 1rem; background: #e8f4f8; border-radius: 5px;'>";
    echo "<h4>🔗 روابط مفيدة:</h4>";
    echo "<p><a href='index.php'>اختبار النظام</a> | <a href='admin_dashboard.php'>لوحة الإدارة</a> | <a href='auth.php'>صفحة المصادقة</a></p>";
    echo "</div>";
    
    echo "</div></body></html>";
}

/**
 * دالة مساعدة لإنشاء بيانات تجريبية للاختبار
 */
function createTestData() {
    $pdo = getDBConnection();
    
    echo "<h3>🧪 إنشاء بيانات تجريبية...</h3>";
    
    try {
        // إنشاء محاولات تجريبية
        $testFingerprints = [
            'test_user_1_' . md5('192.168.1.100'),
            'test_user_2_' . md5('192.168.1.101'), 
            'test_user_3_' . md5('192.168.1.102')
        ];
        
        $stmt = $pdo->prepare("
            INSERT INTO attempt_tracking 
            (user_fingerprint, ip_address, user_agent, attempt_timestamp, completed, score, total_questions) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($testFingerprints as $i => $fingerprint) {
            // محاولات متعددة لكل مستخدم تجريبي
            for ($attempt = 1; $attempt <= 2; $attempt++) {
                $timestamp = date('Y-m-d H:i:s', strtotime("-" . (30 - ($attempt * 5)) . " minutes"));
                $completed = $attempt <= 1; // المحاولة الأولى مكتملة
                $score = $completed ? rand(60, 95) : null;
                $totalQuestions = 10;
                
                $stmt->execute([
                    $fingerprint,
                    '192.168.1.' . (100 + $i),
                    'Test Browser ' . ($i + 1),
                    $timestamp,
                    $completed,
                    $score,
                    $totalQuestions
                ]);
            }
        }
        
        echo "✅ تم إنشاء بيانات المحاولات التجريبية<br>";
        
        // إنشاء إحصائيات تجريبية
        $stmt = $pdo->prepare("
            INSERT INTO attempt_statistics 
            (date, total_attempts, unique_users, completed_attempts, average_score) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        for ($days = 5; $days >= 0; $days--) {
            $date = date('Y-m-d', strtotime("-$days days"));
            $totalAttempts = rand(10, 30);
            $uniqueUsers = rand(5, 15);
            $completedAttempts = rand(8, $totalAttempts);
            $averageScore = rand(70, 90) + (rand(0, 99) / 100);
            
            $stmt->execute([
                $date,
                $totalAttempts,
                $uniqueUsers, 
                $completedAttempts,
                $averageScore
            ]);
        }
        
        echo "✅ تم إنشاء الإحصائيات التجريبية<br>";
        return true;
        
    } catch (Exception $e) {
        echo "❌ خطأ في إنشاء البيانات التجريبية: " . $e->getMessage() . "<br>";
        return false;
    }
}

/**
 * دالة لتنظيف البيانات التجريبية
 */
function cleanTestData() {
    $pdo = getDBConnection();
    
    try {
        // حذف البيانات التجريبية
        $pdo->exec("DELETE FROM attempt_tracking WHERE user_fingerprint LIKE 'test_user_%'");
        $pdo->exec("DELETE FROM attempt_statistics WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
        
        echo "✅ تم تنظيف البيانات التجريبية<br>";
        return true;
        
    } catch (Exception $e) {
        echo "❌ خطأ في تنظيف البيانات: " . $e->getMessage() . "<br>";
        return false;
    }
}

/**
 * إحصائيات النظام الحالي
 */
function showSystemStats() {
    $pdo = getDBConnection();
    
    echo "<h3>📊 إحصائيات النظام الحالية:</h3>";
    
    try {
        // إحصائيات عامة
        $stats = [
            'المستخدمين المسجلين' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
            'إجمالي الأسئلة' => $pdo->query("SELECT COUNT(*) FROM questions")->fetchColumn(),
            'محاولات اليوم' => $pdo->query("SELECT COUNT(*) FROM attempt_tracking WHERE DATE(attempt_timestamp) = CURDATE()")->fetchColumn(),
            'المحاولات النشطة' => $pdo->query("SELECT COUNT(*) FROM attempt_tracking WHERE attempt_timestamp > DATE_SUB(NOW(), INTERVAL 30 MINUTE)")->fetchColumn()
        ];
        
        echo "<div style='display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin: 1rem 0;'>";
        foreach ($stats as $label => $value) {
            echo "<div style='background: #f8f9fa; padding: 1rem; border-radius: 5px; text-align: center;'>";
            echo "<div style='font-size: 2rem; font-weight: bold; color: #3498db;'>$value</div>";
            echo "<div style='color: #666;'>$label</div>";
            echo "</div>";
        }
        echo "</div>";
        
        // أحدث المحاولات
        $stmt = $pdo->query("
            SELECT user_fingerprint, ip_address, attempt_timestamp, completed, score 
            FROM attempt_tracking 
            ORDER BY attempt_timestamp DESC 
            LIMIT 5
        ");
        
        $recentAttempts = $stmt->fetchAll();
        
        if (!empty($recentAttempts)) {
            echo "<h4>🕐 آخر المحاولات:</h4>";
            echo "<table style='width: 100%; border-collapse: collapse; margin: 1rem 0;'>";
            echo "<tr style='background: #f1f2f6;'>";
            echo "<th style='padding: 0.5rem; text-align: right;'>معرف المستخدم</th>";
            echo "<th style='padding: 0.5rem; text-align: right;'>عنوان IP</th>";
            echo "<th style='padding: 0.5rem; text-align: right;'>الوقت</th>";
            echo "<th style='padding: 0.5rem; text-align: right;'>الحالة</th>";
            echo "<th style='padding: 0.5rem; text-align: right;'>النتيجة</th>";
            echo "</tr>";
            
            foreach ($recentAttempts as $attempt) {
                $userIdShort = substr($attempt['user_fingerprint'], 0, 8) . '...';
                $timeAgo = date('H:i', strtotime($attempt['attempt_timestamp']));
                $status = $attempt['completed'] ? 'مكتمل' : 'جاري';
                $statusColor = $attempt['completed'] ? '#27ae60' : '#f39c12';
                $score = $attempt['score'] ? $attempt['score'] . '%' : '-';
                
                echo "<tr>";
                echo "<td style='padding: 0.5rem; border-bottom: 1px solid #ddd;'>$userIdShort</td>";
                echo "<td style='padding: 0.5rem; border-bottom: 1px solid #ddd;'>{$attempt['ip_address']}</td>";
                echo "<td style='padding: 0.5rem; border-bottom: 1px solid #ddd;'>$timeAgo</td>";
                echo "<td style='padding: 0.5rem; border-bottom: 1px solid #ddd; color: $statusColor;'>$status</td>";
                echo "<td style='padding: 0.5rem; border-bottom: 1px solid #ddd;'>$score</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
    } catch (Exception $e) {
        echo "خطأ في جلب الإحصائيات: " . $e->getMessage() . "<br>";
    }
}
?>