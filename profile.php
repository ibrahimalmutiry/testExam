<?php

// profile.php - صفحة الملف الشخصي
require_once 'functions.php';

// التحقق من ملف الحماية
if (!file_exists('auth_protection.php')) {
    // إنشاء مبسط إذا لم يكن موجوداً
    echo "<div style='background: #f8d7da; padding: 20px; margin: 20px; border-radius: 8px; color: #721c24;'>";
    echo "<h3>ملف الحماية غير موجود</h3>";
    echo "<p>يرجى التأكد من وجود ملف auth_protection.php</p>";
    echo "<a href='auth.php'>تسجيل الدخول</a> | <a href='index.php'>الرئيسية</a>";
    echo "</div>";
    exit;
}

require_once 'auth_protection.php';

// محاولة التحقق من المستخدم
$user = getCurrentUser();

// إذا لم يكن هناك مستخدم، توجيه لتسجيل الدخول
if (!$user) {
    header('Location: auth.php?redirect=profile.php');
    exit;
}

// محاولة جلب الإحصائيات
$stats = null;
$quickStats = null;
$recentAttempts = [];

try {
    $stats = getUserStats($user['id']);
    $quickStats = getUserQuickStats($user['id']);
    
    // جلب آخر 10 محاولات
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT quiz_session_id, score, total_questions, score_percentage, completed_at, time_taken
        FROM user_quiz_stats 
        WHERE user_id = ? 
        ORDER BY completed_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$user['id']]);
    $recentAttempts = $stmt->fetchAll();
    
} catch (Exception $e) {
    // في حالة وجود مشكلة في قاعدة البيانات
    $stats = [
        'total_attempts' => 0,
        'avg_score' => 0,
        'best_score' => 0,
        'lowest_score' => 0,
        'avg_time' => 0,
        'last_attempt' => null
    ];
    
    $quickStats = [
        'total_attempts' => 0,
        'avg_score' => 0,
        'best_score' => 0,
        'last_attempt_date' => null
    ];
    
    $recentAttempts = [];
    
    error_log("خطأ في صفحة الملف الشخصي: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الملف الشخصي - <?php echo htmlspecialchars($user['name']); ?></title>
    <link rel="stylesheet" href="styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        .profile-container {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .user-bar {
            background: linear-gradient(135deg, #2c3e50, #34495e);
            color: white;
            padding: 0.75rem 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .user-bar .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
        }
        
        .user-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .welcome-text {
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .user-code {
            font-size: 0.8rem;
            font-family: "Courier New", monospace;
            background: rgba(255,255,255,0.2);
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            display: inline-block;
        }
        
        .user-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
            border-radius: 6px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
        }
        
        .btn-outline {
            background: transparent;
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
        }
        
        .btn-outline:hover {
            background: rgba(255,255,255,0.1);
        }
        
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c0392b;
        }
        
        .profile-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .profile-header {
            background: linear-gradient(135deg, var(--primary-color, #1e40af), var(--primary-dark, #1e3a8a));
            color: white;
            padding: 2rem;
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .profile-avatar {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            flex-shrink: 0;
        }

        .profile-info {
            flex: 1;
        }

        .profile-info h2 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            font-weight: 700;
        }

        .user-code-display {
            background: rgba(255, 255, 255, 0.15);
            padding: 0.5rem 1rem;
            border-radius: 25px;
            display: inline-block;
            margin-bottom: 0.5rem;
            font-family: 'Courier New', monospace;
            font-weight: 600;
        }

        .join-date {
            opacity: 0.9;
            font-size: 0.9rem;
        }

        .profile-actions {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 16px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            flex-shrink: 0;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: var(--text-primary, #0f172a);
            line-height: 1;
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--text-secondary, #475569);
            margin-top: 0.25rem;
        }

        .recent-attempts-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            overflow: hidden;
        }

        .card-header {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .card-header h3 {
            margin: 0;
            color: var(--text-primary, #0f172a);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .card-body {
            padding: 2rem;
        }

        .attempts-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .attempt-item {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            padding: 1.5rem;
            background: #f8f9fa;
            border-radius: 12px;
            transition: all 0.3s ease;
        }

        .attempt-item:hover {
            background: #e9ecef;
            transform: translateX(-4px);
        }

        .attempt-number {
            width: 40px;
            height: 40px;
            background: var(--primary-color, #1e40af);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            flex-shrink: 0;
        }

        .attempt-info {
            flex: 1;
        }

        .attempt-score {
            margin-bottom: 0.5rem;
        }

        .score-value {
            font-size: 1.4rem;
            font-weight: 700;
            margin-left: 0.5rem;
        }

        .score-value.excellent { color: #059669; }
        .score-value.good { color: #d97706; }
        .score-value.needs-improvement { color: #dc2626; }

        .score-details {
            color: var(--text-secondary, #475569);
            font-size: 0.9rem;
        }

        .attempt-meta {
            display: flex;
            gap: 1rem;
            font-size: 0.85rem;
            color: var(--text-secondary, #475569);
        }

        .attempt-meta span {
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .performance-icon {
            font-size: 1.5rem;
        }

        .performance-icon.excellent { color: #059669; }
        .performance-icon.very-good { color: #2ecc71; }
        .performance-icon.good { color: #d97706; }
        .performance-icon.needs-improvement { color: #dc2626; }

        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--text-secondary, #475569);
        }

        .empty-state h4 {
            color: var(--text-primary, #0f172a);
            margin-bottom: 1rem;
        }

        .main-content {
            padding: 3rem 0;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }
        
        .quiz-footer {
            background: #334155;
            color: white;
            padding: 2rem 0;
            text-align: center;
            margin-top: auto;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #1e40af, #1e3a8a);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(30, 64, 175, 0.4);
        }

        @media (max-width: 768px) {
            .profile-header {
                flex-direction: column;
                text-align: center;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .attempt-item {
                flex-direction: column;
                text-align: center;
            }

            .attempt-meta {
                justify-content: center;
            }
            
            .user-bar .container {
                flex-direction: column;
                gap: 0.5rem;
                text-align: center;
            }

            .user-actions {
                justify-content: center;
            }
        }
        
        .fade-in {
            animation: fadeIn 0.8s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="profile-container">
        <!-- شريط المستخدم -->
        <div class="user-bar">
            <div class="container">
                <div class="user-info">
                    <span class="welcome-text">مرحباً، <strong><?php echo htmlspecialchars($user['name']); ?></strong></span>
                    <span class="user-code">#<?php echo htmlspecialchars($user['code']); ?></span>
                </div>
                <div class="user-actions">
                    <a href="index.php" class="btn-sm btn-outline">
                        <i class="fas fa-home"></i> الرئيسية
                    </a>
                    <a href="logout.php" class="btn-sm btn-danger">
                        <i class="fas fa-sign-out-alt"></i> خروج
                    </a>
                </div>
            </div>
        </div>

        <main class="main-content">
            <div class="container">
                <!-- بطاقة المستخدم -->
                <div class="profile-card fade-in">
                    <div class="profile-header">
                        <div class="profile-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="profile-info">
                            <h2><?php echo htmlspecialchars($user['name']); ?></h2>
                            <div class="user-code-display">
                                كود المستخدم: <span class="code">#<?php echo htmlspecialchars($user['code']); ?></span>
                            </div>
                            <div class="join-date">
                                عضو في النظام
                            </div>
                        </div>
                        <div class="profile-actions">
                            <a href="index.php" class="btn btn-primary">
                                <i class="fas fa-play"></i>
                                بدء اختبار جديد
                            </a>
                        </div>
                    </div>
                </div>

                <!-- الإحصائيات -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #1e40af;">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo $quickStats['total_attempts']; ?></div>
                            <div class="stat-label">إجمالي المحاولات</div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon" style="background: #059669;">
                            <i class="fas fa-trophy"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo round($quickStats['best_score'], 1); ?>%</div>
                            <div class="stat-label">أفضل نتيجة</div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon" style="background: #d97706;">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo round($quickStats['avg_score'], 1); ?>%</div>
                            <div class="stat-label">المعدل العام</div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon" style="background: #64748b;">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number">
                                <?php 
                                if ($quickStats['last_attempt_date']) {
                                    echo date('d/m', strtotime($quickStats['last_attempt_date']));
                                } else {
                                    echo '--';
                                }
                                ?>
                            </div>
                            <div class="stat-label">آخر محاولة</div>
                        </div>
                    </div>
                </div>

                <!-- آخر المحاولات -->
                <div class="recent-attempts-card">
                    <div class="card-header">
                        <h3>
                            <i class="fas fa-history"></i>
                            آخر المحاولات
                        </h3>
                    </div>
                    
                    <div class="card-body">
                        <?php if (empty($recentAttempts)): ?>
                            <div class="empty-state">
                                <i class="fas fa-clipboard-list" style="font-size: 3rem; color: #e2e8f0; margin-bottom: 1rem;"></i>
                                <h4>لا توجد محاولات بعد</h4>
                                <p>ابدأ أول اختبار لك لرؤية النتائج هنا</p>
                                <a href="index.php" class="btn btn-primary">
                                    <i class="fas fa-play"></i>
                                    بدء الاختبار الآن
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="attempts-list">
                                <?php foreach ($recentAttempts as $index => $attempt): ?>
                                    <div class="attempt-item">
                                        <div class="attempt-number">
                                            #<?php echo $index + 1; ?>
                                        </div>
                                        
                                        <div class="attempt-info">
                                            <div class="attempt-score">
                                                <span class="score-value <?php echo $attempt['score_percentage'] >= 80 ? 'excellent' : ($attempt['score_percentage'] >= 60 ? 'good' : 'needs-improvement'); ?>">
                                                    <?php echo round($attempt['score_percentage'], 1); ?>%
                                                </span>
                                                <span class="score-details">
                                                    (<?php echo $attempt['score']; ?>/<?php echo $attempt['total_questions']; ?>)
                                                </span>
                                            </div>
                                            
                                            <div class="attempt-meta">
                                                <span class="attempt-date">
                                                    <i class="fas fa-calendar"></i>
                                                    <?php echo date('d/m/Y H:i', strtotime($attempt['completed_at'])); ?>
                                                </span>
                                                
                                                <?php if ($attempt['time_taken']): ?>
                                                    <span class="attempt-time">
                                                        <i class="fas fa-clock"></i>
                                                        <?php echo gmdate('i:s', $attempt['time_taken']); ?> دقيقة
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="attempt-performance">
                                            <?php if ($attempt['score_percentage'] >= 90): ?>
                                                <i class="fas fa-star performance-icon excellent" title="أداء ممتاز"></i>
                                            <?php elseif ($attempt['score_percentage'] >= 80): ?>
                                                <i class="fas fa-medal performance-icon very-good" title="أداء جيد جداً"></i>
                                            <?php elseif ($attempt['score_percentage'] >= 60): ?>
                                                <i class="fas fa-thumbs-up performance-icon good" title="أداء جيد"></i>
                                            <?php else: ?>
                                                <i class="fas fa-chart-line performance-icon needs-improvement" title="يحتاج تحسين"></i>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>

        <footer class="quiz-footer">
            <div class="container">
                <p>&copy; 2025 نظام الاختبارات التفاعلي المتقدم. جميع الحقوق محفوظة.</p>
            </div>
        </footer>
    </div>
</body>
</html>