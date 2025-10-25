<?php
// ملف الإصلاح السريع - fix_database.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>🔧 إصلاح قاعدة البيانات</h2>";

// إعدادات قاعدة البيانات
$host = 'localhost';
$dbname = 'quiz_system';
$username = 'root';
$password = '';

try {
    // الاتصال بدون اسم قاعدة البيانات
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p style='color:green'>✅ الاتصال بـ MySQL نجح</p>";
    
    // إنشاء قاعدة البيانات
    $pdo->exec("CREATE DATABASE IF NOT EXISTS $dbname");
    echo "<p style='color:green'>✅ تم إنشاء قاعدة البيانات '$dbname'</p>";
    
    // الاتصال بقاعدة البيانات
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // حذف الجداول الموجودة إن وجدت (إعادة تعيين كاملة)
    $pdo->exec("DROP TABLE IF EXISTS quiz_answers");
    $pdo->exec("DROP TABLE IF EXISTS quiz_attempts");
    $pdo->exec("DROP TABLE IF EXISTS questions");
    echo "<p style='color:orange'>⚠️ تم حذف الجداول السابقة</p>";
    
    // إنشاء جدول الأسئلة
    $createQuestions = "
    CREATE TABLE questions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        question TEXT NOT NULL,
        question_type VARCHAR(20) NOT NULL DEFAULT 'multiple_choice',
        option_1 VARCHAR(500) DEFAULT NULL,
        option_2 VARCHAR(500) DEFAULT NULL,
        option_3 VARCHAR(500) DEFAULT NULL,
        option_4 VARCHAR(500) DEFAULT NULL,
        correct_answer INT DEFAULT NULL,
        correct_text TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    $pdo->exec($createQuestions);
    echo "<p style='color:green'>✅ تم إنشاء جدول الأسئلة</p>";
    
    // إنشاء جدول الإجابات
    $createAnswers = "
    CREATE TABLE quiz_answers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id VARCHAR(255) NOT NULL,
        question_id INT NOT NULL,
        selected_option INT DEFAULT NULL,
        text_answer TEXT DEFAULT NULL,
        is_correct BOOLEAN DEFAULT FALSE,
        answered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    $pdo->exec($createAnswers);
    echo "<p style='color:green'>✅ تم إنشاء جدول الإجابات</p>";
    
    // إنشاء جدول المحاولات
    $createAttempts = "
    CREATE TABLE quiz_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id VARCHAR(255) NOT NULL,
        score INT NOT NULL,
        total_questions INT NOT NULL,
        score_percentage DECIMAL(5,2) NOT NULL,
        attempt_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    $pdo->exec($createAttempts);
    echo "<p style='color:green'>✅ تم إنشاء جدول المحاولات</p>";
    
    // اختبار إضافة سؤال مباشرة
    echo "<h3>🧪 اختبار إضافة الأسئلة:</h3>";
    
    // سؤال اختيار متعدد
    $stmt = $pdo->prepare("INSERT INTO questions (question, question_type, option_1, option_2, option_3, option_4, correct_answer) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $result1 = $stmt->execute([
        'ما هي عاصمة السعودية؟',
        'multiple_choice',
        'جدة',
        'الرياض',
        'الدمام',
        'مكة',
        2
    ]);
    
    if ($result1) {
        echo "<p style='color:green'>✅ تم إضافة سؤال اختيار متعدد</p>";
    }
    
    // سؤال صح/خطأ
    $stmt2 = $pdo->prepare("INSERT INTO questions (question, question_type, correct_answer) VALUES (?, ?, ?)");
    $result2 = $stmt2->execute([
        'الأرض كروية الشكل',
        'true_false',
        1
    ]);
    
    if ($result2) {
        echo "<p style='color:green'>✅ تم إضافة سؤال صح/خطأ</p>";
    }
    
    // سؤال نص مفتوح
    $stmt3 = $pdo->prepare("INSERT INTO questions (question, question_type, correct_text) VALUES (?, ?, ?)");
    $result3 = $stmt3->execute([
        'اذكر ثلاثة فوائد للقراءة',
        'open_text',
        'زيادة المعرفة وتحسين المفردات وتنمية الخيال'
    ]);
    
    if ($result3) {
        echo "<p style='color:green'>✅ تم إضافة سؤال نص مفتوح</p>";
    }
    
    // عرض الأسئلة المضافة
    echo "<h3>📝 الأسئلة المضافة:</h3>";
    $questions = $pdo->query("SELECT * FROM questions")->fetchAll();
    
    echo "<table border='1' style='border-collapse:collapse; width:100%; margin:10px 0;'>";
    echo "<tr style='background:#f0f0f0;'><th>ID</th><th>السؤال</th><th>النوع</th><th>الإجابة الصحيحة</th></tr>";
    
    foreach ($questions as $q) {
        echo "<tr>";
        echo "<td>" . $q['id'] . "</td>";
        echo "<td>" . htmlspecialchars($q['question']) . "</td>";
        echo "<td>" . $q['question_type'] . "</td>";
        
        if ($q['question_type'] === 'multiple_choice') {
            echo "<td>الخيار " . $q['correct_answer'] . "</td>";
        } elseif ($q['question_type'] === 'true_false') {
            echo "<td>" . ($q['correct_answer'] == 1 ? 'صح' : 'خطأ') . "</td>";
        } else {
            echo "<td>نص مفتوح</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<div style='background:#e7f3ff; padding:15px; border-radius:5px; margin:20px 0;'>";
    echo "<h3>🎉 تم الإصلاح بنجاح!</h3>";
    echo "<p><strong>الخطوات التالية:</strong></p>";
    echo "<ol>";
    echo "<li><a href='admin_dashboard.php'>اذهب إلى لوحة الإدارة</a> لإضافة المزيد من الأسئلة</li>";
    echo "<li><a href='quiz_interface.php'>جرب الاختبار</a> للتأكد من عمل النظام</li>";
    echo "<li><a href='debug.php'>شغل التشخيص</a> للتأكد من سلامة النظام</li>";
    echo "</ol>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<div style='background:#ffe6e6; padding:15px; border-radius:5px; color:red;'>";
    echo "<h3>❌ خطأ في قاعدة البيانات:</h3>";
    echo "<p><strong>الرسالة:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>الكود:</strong> " . $e->getCode() . "</p>";
    echo "<h4>الحلول المقترحة:</h4>";
    echo "<ul>";
    echo "<li>تأكد من تشغيل XAMPP أو خدمة MySQL</li>";
    echo "<li>تحقق من اسم المستخدم وكلمة المرور</li>";
    echo "<li>تأكد من أن MySQL يعمل على المنفذ 3306</li>";
    echo "</ul>";
    echo "</div>";
}

echo "<style>body{font-family:Arial;margin:20px;direction:rtl;} table{width:100%;} th,td{padding:8px;text-align:right;}</style>";
?>