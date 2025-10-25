<?php
// ملف حماية الصفحات - auth_protection.php
require_once 'auth_setup.php';

// دالة إجبارية لتسجيل الدخول
function requireLogin($redirectUrl = 'auth.php') {
    $session = validateSession();
    
    if (!$session) {
        // إعادة توجيه للصفحة المطلوبة بعد تسجيل الدخول
        $currentUrl = $_SERVER['REQUEST_URI'];
        $redirectUrl .= '?redirect=' . urlencode($currentUrl);
        
        header('Location: ' . $redirectUrl);
        exit();
    }
    
    return $session;
}

// دالة اختيارية لتسجيل الدخول (مع إمكانية المتابعة كضيف)
function optionalLogin() {
    return validateSession();
}

// دالة الحصول على معلومات المستخدم الحالي
function getCurrentUser() {
    $session = validateSession();
    return $session ? [
        'id' => $session['id'],
        'name' => $session['name'],
        'code' => $session['user_code']
    ] : null;
}

// دالة التحقق من وجود صلاحية معينة (للتوسع المستقبلي)
function hasPermission($permission) {
    $user = getCurrentUser();
    // يمكن إضافة منطق الصلاحيات هنا لاحقاً
    return $user !== null;
}

// شريط المستخدم العلوي
function renderUserBar() {
    $user = getCurrentUser();
    
    if ($user) {
        echo '<div class="user-bar">';
        echo '<div class="container">';
        echo '<div class="user-info">';
        echo '<span class="welcome-text">مرحباً، <strong>' . htmlspecialchars($user['name']) . '</strong></span>';
        echo '<span class="user-code">#' . htmlspecialchars($user['code']) . '</span>';
        echo '</div>';
        echo '<div class="user-actions">';
        echo '<a href="profile.php" class="btn btn-sm btn-outline">';
        echo '<i class="fas fa-user"></i> الملف الشخصي';
        echo '</a>';
        echo '<a href="logout.php" class="btn btn-sm btn-danger">';
        echo '<i class="fas fa-sign-out-alt"></i> خروج';
        echo '</a>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    } else {
        echo '<div class="guest-bar">';
        echo '<div class="container">';
        echo '<div class="guest-info">';
        echo '<span class="guest-text">أنت تتصفح كضيف</span>';
        echo '<small>لن يتم حفظ نتائجك</small>';
        echo '</div>';
        echo '<div class="guest-actions">';
        echo '<a href="auth.php" class="btn btn-sm btn-primary">';
        echo '<i class="fas fa-sign-in-alt"></i> تسجيل الدخول';
        echo '</a>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
}

// CSS للشريط العلوي
function renderUserBarCSS() {
    echo '<style>';
    echo '.user-bar, .guest-bar {';
    echo '    background: linear-gradient(135deg, #2c3e50, #34495e);';
    echo '    color: white;';
    echo '    padding: 0.75rem 0;';
    echo '    box-shadow: 0 2px 4px rgba(0,0,0,0.1);';
    echo '}';
    
    echo '.user-bar .container, .guest-bar .container {';
    echo '    display: flex;';
    echo '    justify-content: space-between;';
    echo '    align-items: center;';
    echo '    max-width: 1400px;';
    echo '    margin: 0 auto;';
    echo '    padding: 0 2rem;';
    echo '}';
    
    echo '.user-info, .guest-info {';
    echo '    display: flex;';
    echo '    flex-direction: column;';
    echo '    gap: 0.25rem;';
    echo '}';
    
    echo '.welcome-text, .guest-text {';
    echo '    font-size: 0.9rem;';
    echo '    font-weight: 500;';
    echo '}';
    
    echo '.user-code {';
    echo '    font-size: 0.8rem;';
    echo '    font-family: "Courier New", monospace;';
    echo '    background: rgba(255,255,255,0.2);';
    echo '    padding: 0.2rem 0.5rem;';
    echo '    border-radius: 4px;';
    echo '    display: inline-block;';
    echo '}';
    
    echo '.user-actions, .guest-actions {';
    echo '    display: flex;';
    echo '    gap: 0.5rem;';
    echo '}';
    
    echo '.btn-sm {';
    echo '    padding: 0.4rem 0.8rem;';
    echo '    font-size: 0.8rem;';
    echo '    border-radius: 6px;';
    echo '    text-decoration: none;';
    echo '    display: inline-flex;';
    echo '    align-items: center;';
    echo '    gap: 0.3rem;';
    echo '    transition: all 0.2s ease;';
    echo '}';
    
    echo '.btn-outline {';
    echo '    background: transparent;';
    echo '    border: 1px solid rgba(255,255,255,0.3);';
    echo '    color: white;';
    echo '}';
    
    echo '.btn-outline:hover {';
    echo '    background: rgba(255,255,255,0.1);';
    echo '}';
    
    echo '.btn-danger {';
    echo '    background: #e74c3c;';
    echo '    color: white;';
    echo '    border: none;';
    echo '}';
    
    echo '.btn-danger:hover {';
    echo '    background: #c0392b;';
    echo '}';
    
    echo '.btn-primary {';
    echo '    background: var(--primary-color);';
    echo '    color: white;';
    echo '    border: none;';
    echo '}';
    
    echo '.btn-primary:hover {';
    echo '    background: var(--primary-dark);';
    echo '}';
    
    echo '@media (max-width: 768px) {';
    echo '    .user-bar .container, .guest-bar .container {';
    echo '        flex-direction: column;';
    echo '        gap: 0.5rem;';
    echo '        text-align: center;';
    echo '    }';
    
    echo '    .user-actions, .guest-actions {';
    echo '        justify-content: center;';
    echo '    }';
    echo '}';
    echo '</style>';
}

// إحصائيات المستخدم السريعة
function getUserQuickStats($userId) {
    try {
        $pdo = getDBConnection();
        
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_attempts,
                COALESCE(AVG(score_percentage), 0) as avg_score,
                COALESCE(MAX(score_percentage), 0) as best_score,
                MAX(completed_at) as last_attempt_date
            FROM user_quiz_stats 
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        $stats = $stmt->fetch();
        
        return $stats ?: [
            'total_attempts' => 0,
            'avg_score' => 0,
            'best_score' => 0,
            'last_attempt_date' => null
        ];
        
    } catch (Exception $e) {
        error_log("خطأ في جلب إحصائيات المستخدم: " . $e->getMessage());
        return [
            'total_attempts' => 0,
            'avg_score' => 0,
            'best_score' => 0,
            'last_attempt_date' => null
        ];
    }
}

// رسالة ترحيب مخصصة
function getWelcomeMessage($user) {
    if (!$user) return '';
    
    $hour = (int)date('H');
    
    if ($hour < 12) {
        $greeting = 'صباح الخير';
    } elseif ($hour < 17) {
        $greeting = 'مساء الخير';
    } else {
        $greeting = 'مساء الخير';
    }
    
    $stats = getUserQuickStats($user['id']);
    
    $message = $greeting . '، ' . htmlspecialchars($user['name']);
    
    if ($stats['total_attempts'] > 0) {
        $message .= '<br><small style="opacity: 0.8;">آخر محاولة: ';
        $message .= date('d/m/Y', strtotime($stats['last_attempt_date']));
        $message .= ' | أفضل نتيجة: ' . round($stats['best_score'], 1) . '%</small>';
    } else {
        $message .= '<br><small style="opacity: 0.8;">مرحباً بك في أول زيارة لك!</small>';
    }
    
    return $message;
}

// حفظ نتيجة الاختبار مع ربطها بالمستخدم
function saveQuizResultForUser($sessionId, $score, $totalQuestions, $timeTaken = null) {
    $user = getCurrentUser();
    
    if ($user) {
        return saveUserQuizResult($user['id'], $sessionId, $score, $totalQuestions, $timeTaken);
    }
    
    return false; // لم يتم الحفظ لأن المستخدم غير مسجل
}

// تنبيه للضيوف بعدم حفظ النتائج
function showGuestWarning() {
    $user = getCurrentUser();
    
    if (!$user) {
        echo '<div class="alert alert-warning" style="margin: 1rem 0;">';
        echo '<i class="fas fa-exclamation-triangle"></i>';
        echo '<strong>تنبيه:</strong> أنت تتصفح كضيف، لن يتم حفظ نتائجك. ';
        echo '<a href="auth.php" style="color: inherit; text-decoration: underline;">سجل دخولك</a> للحصول على تتبع كامل لأدائك.';
        echo '</div>';
    }
}
?>