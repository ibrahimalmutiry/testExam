<?php
session_start();
require_once 'functions.php';
require_once 'auth_protection.php';

// تضمين وظائف تتبع المحاولات الجديدة
// (يجب إضافة الكود السابق إلى ملف functions.php أو إنشاء ملف منفصل)

// التحقق من إمكانية بدء اختبار جديد
$attemptInfo = canStartNewAttempt();

// Generate unique session ID for auto-save
if (!isset($_SESSION['quiz_session_id'])) {
    $_SESSION['quiz_session_id'] = uniqid('quiz_', true);
}

// Initialize or reset quiz session
if (!isset($_SESSION['quiz_started']) || isset($_GET['restart'])) {
    // التحقق من إمكانية البدء قبل إنشاء جلسة جديدة
    if (!$attemptInfo['can_attempt'] && !isset($_GET['admin_override'])) {
        // لا يمكن بدء اختبار جديد
        $quizBlocked = true;
    } else {
        // تسجيل محاولة جديدة
        trackQuizAttempt();
        
        $_SESSION['quiz_started'] = true;
        $_SESSION['current_question'] = 0;
        $_SESSION['score'] = 0;
        $_SESSION['answers'] = [];
        $_SESSION['quiz_start_time'] = time();
        
        // إزالة علامة الإكمال السابقة
        unset($_SESSION['quiz_logged']);
        
        $quizBlocked = false;
    }
} else {
    $quizBlocked = !$attemptInfo['can_attempt'] && !isset($_SESSION['quiz_started']);
}

// الحصول على المستخدم الحالي
$currentUser = getCurrentUser();

$questions = getAllQuestions();
$totalQuestions = count($questions);

if (empty($questions)) {
    header('Location: admin_dashboard.php');
    exit();
}

// Handle answer submission
if ($_POST && (isset($_POST['answer']) || isset($_POST['text_answer'])) && !$quizBlocked) {
    $currentIndex = $_SESSION['current_question'];
    
    if (isset($questions[$currentIndex])) {
        $question = $questions[$currentIndex];
        $sessionId = $_SESSION['quiz_session_id'];
        
        $isCorrect = false;
        
        if ($question['question_type'] === 'open_text') {
            $textAnswer = trim($_POST['text_answer'] ?? '');
            $isCorrect = saveAnswer($sessionId, $question['id'], null, $textAnswer);
            $_SESSION['answers'][$currentIndex] = $textAnswer;
        } else {
            $selectedAnswer = (int)$_POST['answer'];
            $isCorrect = saveAnswer($sessionId, $question['id'], $selectedAnswer);
            $_SESSION['answers'][$currentIndex] = $selectedAnswer;
        }
        
        if ($isCorrect) {
            $_SESSION['score']++;
        }
        
        $_SESSION['current_question']++;
        
        // Auto-advance to next question
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }
}

$currentQuestionIndex = $_SESSION['current_question'] ?? 0;
$isQuizComplete = $currentQuestionIndex >= $totalQuestions;

// إذا انتهى الاختبار، احفظ النتيجة وحدث حالة المحاولة
if ($isQuizComplete && !isset($_SESSION['quiz_logged']) && !$quizBlocked) {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // حفظ في السجل العام
    logQuizAttempt($_SESSION['score'], $totalQuestions, $userAgent);
    
    // حفظ للمستخدم إذا كان مسجل الدخول
    if ($currentUser) {
        $timeTaken = isset($_SESSION['quiz_start_time']) ? (time() - $_SESSION['quiz_start_time']) : null;
        saveQuizResultForUser($_SESSION['quiz_session_id'], $_SESSION['score'], $totalQuestions, $timeTaken);
    }
    
    // تحديث حالة المحاولة
    markAttemptCompleted();
    
    $_SESSION['quiz_logged'] = true;
}

// الحصول على رسالة التحذير إذا لزم الأمر
$warningMessage = getAttemptWarningMessage($attemptInfo);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام الاختبارات التفاعلي المتقدم</title>
    <link rel="stylesheet" href="styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <meta name="description" content="نظام اختبارات تفاعلي متقدم مع واجهة أنيقة وتجربة مستخدم محسنة">
    
    <style>
        .attempt-restriction {
            background: linear-gradient(135deg, #ff6b6b, #ee5a52);
            color: white;
            border-radius: 20px;
            padding: 3rem 2rem;
            text-align: center;
            box-shadow: 0 10px 30px rgba(238, 90, 82, 0.3);
            margin: 2rem 0;
        }
        
        .attempt-restriction h2 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
        }
        
        .attempt-restriction .countdown {
            font-size: 2rem;
            font-weight: 700;
            margin: 1.5rem 0;
            font-family: 'Courier New', monospace;
            background: rgba(255, 255, 255, 0.2);
            padding: 1rem;
            border-radius: 10px;
            display: inline-block;
        }
        
        .attempt-info-card {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 1.5rem;
            margin: 1.5rem 0;
            backdrop-filter: blur(10px);
        }
        
        .attempt-warning {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: white;
            padding: 1.5rem;
            border-radius: 15px;
            margin: 1rem 0;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: 0 5px 15px rgba(243, 156, 18, 0.3);
        }
        
        .attempt-counter {
            position: fixed;
            top: 20px;
            left: 20px;
            background: rgba(44, 62, 80, 0.9);
            color: white;
            padding: 0.75rem 1rem;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 600;
            z-index: 1000;
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .pulse-animation {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }
        
        .retry-buttons {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .btn-countdown {
            background: #95a5a6;
            cursor: not-allowed;
            position: relative;
            overflow: hidden;
        }
        
        .btn-countdown::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            height: 100%;
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            transition: width 0.1s linear;
            z-index: 1;
        }
        
        .btn-countdown span {
            position: relative;
            z-index: 2;
        }
    </style>
</head>
<body>
    <?php renderUserBarCSS(); ?>
    <?php renderUserBar(); ?>
    
    <!-- عداد المحاولات -->
    <?php if (!$quizBlocked && isset($_SESSION['quiz_started'])): ?>
        <div class="attempt-counter">
            <i class="fas fa-clipboard-check"></i>
            <span>المحاولة <?php echo $attemptInfo['attempts_count'] + 1; ?> من 3</span>
        </div>
    <?php endif; ?>
    
    <div class="quiz-container">
        <header class="quiz-header">
            <div class="container">
                <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                    <div>
                        <h1>
                            <i class="fas fa-graduation-cap"></i>
                            نظام الاختبارات التفاعلي المتقدم
                        </h1>
                        <p>اختبر معلوماتك بأسلوب تفاعلي حديث</p>
                    </div>
                    
                    <?php if ($currentUser): ?>
                        <div class="header-profile-link"></div>
                    <?php else: ?>
                        <div class="header-auth-link">
                            <a href="auth.php" class="auth-btn">
                                <i class="fas fa-sign-in-alt"></i>
                                <span>تسجيل الدخول</span>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if (!$isQuizComplete && !$quizBlocked): ?>
                    <div class="progress-info">
                        <span>السؤال <?php echo $currentQuestionIndex + 1; ?> من <?php echo $totalQuestions; ?></span>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo ($currentQuestionIndex / $totalQuestions) * 100; ?>%"></div>
                        </div>
                        <div style="display: flex; justify-content: space-between; width: 100%; max-width: 500px; margin-top: 0.5rem; font-size: 0.9rem; opacity: 0.8;">
                            <span>البداية</span>
                            <span>النهاية</span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </header>

        <main class="main-content">
            <div class="container">
                
                <!-- رسالة التحذير للمحاولات -->
                <?php if ($warningMessage && $warningMessage['type'] === 'warning'): ?>
                    <div class="attempt-warning fade-in">
                        <i class="<?php echo $warningMessage['icon']; ?> fa-2x"></i>
                        <div>
                            <h4><?php echo $warningMessage['title']; ?></h4>
                            <p><?php echo $warningMessage['message']; ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- تحذير الضيوف -->
                <?php if (!$currentUser && !$isQuizComplete && !$quizBlocked): ?>
                    <?php showGuestWarning(); ?>
                <?php endif; ?>
                
                <!-- حجب الاختبار -->
                <?php if ($quizBlocked): ?>
                    <div class="attempt-restriction fade-in">
                        <h2>
                            <i class="fas fa-hourglass-half pulse-animation"></i>
                            تم الوصول للحد الأقصى من المحاولات
                        </h2>
                        
                        <div class="attempt-info-card">
                            <p style="font-size: 1.2rem; margin-bottom: 0.5rem;">
                                لقد استنفدت المحاولات المسموحة (3 محاولات في 30 دقيقة)
                            </p>
                            <p style="opacity: 0.9;">
                                يمكنك المحاولة مرة أخرى بعد انتهاء المهلة الزمنية
                            </p>
                        </div>
                        
                        <div class="countdown" id="countdownDisplay">
                            <i class="fas fa-clock"></i>
                            <span id="timeRemaining"><?php echo formatWaitTime($attemptInfo['time_until_reset'] ?? 0); ?></span>
                        </div>
                        
                        <div class="retry-buttons">
                            <button class="btn btn-countdown" id="retryButton" disabled>
                                <span>إعادة المحاولة في <span id="buttonCountdown"><?php echo ceil(($attemptInfo['time_until_reset'] ?? 0) / 60); ?></span> دقيقة</span>
                            </button>
                            
                            <?php if ($currentUser): ?>
                                <a href="profile.php" class="btn btn-secondary">
                                    <i class="fas fa-user"></i>
                                    الملف الشخصي
                                </a>
                            <?php endif; ?>
                            
                            <a href="admin_dashboard.php" class="btn btn-secondary">
                                <i class="fas fa-cog"></i>
                                إدارة الأسئلة
                            </a>
                        </div>
                    </div>
                    
                <!-- الاختبار العادي -->
                <?php elseif (!$isQuizComplete): ?>
                    <?php $currentQuestion = $questions[$currentQuestionIndex]; ?>
                    <div class="question-card fade-in">
                        <div class="question-header">
                            <div class="question-type-badge <?php echo $currentQuestion['question_type']; ?>">
                                <?php 
                                switch($currentQuestion['question_type']) {
                                    case 'multiple_choice':
                                        echo '<i class="fas fa-list-ul"></i> اختيار متعدد';
                                        break;
                                    case 'true_false':
                                        echo '<i class="fas fa-balance-scale"></i> صح أم خطأ';
                                        break;
                                    case 'open_text':
                                        echo '<i class="fas fa-pen-fancy"></i> إجابة مفتوحة';
                                        break;
                                }
                                ?>
                            </div>
                            <h2 class="question-title">
                                <span class="question-number"><?php echo $currentQuestionIndex + 1; ?></span>
                                <?php echo htmlspecialchars($currentQuestion['question']); ?>
                            </h2>
                        </div>
                        
                        <form method="POST" class="quiz-form" id="quizForm">
                            <?php if ($currentQuestion['question_type'] === 'multiple_choice'): ?>
                                <div class="options-container">
                                    <?php for ($i = 1; $i <= 4; $i++): ?>
                                        <?php if (!empty($currentQuestion["option_$i"])): ?>
                                            <label class="option-label">
                                                <input type="radio" name="answer" value="<?php echo $i; ?>" required>
                                                <div class="option-content">
                                                    <div class="option-indicator"><?php echo chr(64 + $i); ?></div>
                                                    <span class="option-text"><?php echo htmlspecialchars($currentQuestion["option_$i"]); ?></span>
                                                </div>
                                            </label>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                </div>
                                
                            <?php elseif ($currentQuestion['question_type'] === 'true_false'): ?>
                                <div class="true-false-container">
                                    <label class="option-label true-false-option">
                                        <input type="radio" name="answer" value="1" required>
                                        <div class="option-content">
                                            <div class="option-indicator true">
                                                <i class="fas fa-check"></i>
                                            </div>
                                            <span class="option-text">صحيح</span>
                                        </div>
                                    </label>
                                    
                                    <label class="option-label true-false-option">
                                        <input type="radio" name="answer" value="2" required>
                                        <div class="option-content">
                                            <div class="option-indicator false">
                                                <i class="fas fa-times"></i>
                                            </div>
                                            <span class="option-text">خطأ</span>
                                        </div>
                                    </label>
                                </div>
                                
                            <?php elseif ($currentQuestion['question_type'] === 'open_text'): ?>
                                <div class="text-answer-container">
                                    <label for="text_answer" class="text-answer-label">
                                        <i class="fas fa-pencil-alt"></i>
                                        اكتب إجابتك المفصلة هنا:
                                    </label>
                                    <textarea 
                                        id="text_answer" 
                                        name="text_answer" 
                                        required 
                                        rows="6" 
                                        class="text-answer-input"
                                        placeholder="قم بكتابة إجابة شاملة ومفصلة تعكس فهمك للسؤال..."
                                        minlength="10"
                                    ></textarea>
                                    <div class="character-counter">
                                        <span id="charCount">0</span> حرف (الحد الأدنى: 10 أحرف)
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary" id="submitBtn" 
                                    <?php echo ($currentQuestion['question_type'] === 'open_text') ? '' : 'disabled'; ?>>
                                    <i class="fas fa-arrow-left"></i>
                                    <?php echo ($currentQuestionIndex + 1 === $totalQuestions) ? 'إنهاء الاختبار' : 'السؤال التالي'; ?>
                                    <div class="auto-save-indicator">
                                        <i class="fas fa-circle-notch fa-spin" style="display: none;"></i>
                                        حفظ تلقائي
                                    </div>
                                </button>
                            </div>
                        </form>
                    </div>
                    
                <?php else: ?>
                    <!-- نتائج الاختبار -->
                    <div class="results-card fade-in">
                        <div class="results-header">
                            <i class="fas fa-trophy results-icon"></i>
                            <h2>تهانينا! لقد أكملت الاختبار</h2>
                            <p>إليك النتائج المفصلة لأدائك</p>
                        </div>
                        
                        <div class="score-display">
                            <div class="score-circle">
                                <div class="score-number"><?php echo $_SESSION['score']; ?></div>
                                <div class="score-total">من <?php echo $totalQuestions; ?></div>
                            </div>
                            
                            <div class="score-percentage">
                                <?php 
                                $percentage = ($_SESSION['score'] / $totalQuestions) * 100;
                                echo round($percentage, 1); 
                                ?>%
                            </div>
                        </div>
                        
                        <div class="performance-message">
                            <?php if ($percentage >= 90): ?>
                                <div class="message excellent">
                                    <i class="fas fa-star"></i>
                                    أداء استثنائي! أنت متميز حقاً
                                </div>
                            <?php elseif ($percentage >= 80): ?>
                                <div class="message very-good">
                                    <i class="fas fa-medal"></i>
                                    أداء ممتاز! استمر في التفوق
                                </div>
                            <?php elseif ($percentage >= 70): ?>
                                <div class="message good">
                                    <i class="fas fa-thumbs-up"></i>
                                    أداء جيد! هناك مجال للتحسن
                                </div>
                            <?php elseif ($percentage >= 60): ?>
                                <div class="message average">
                                    <i class="fas fa-chart-line"></i>
                                    أداء مقبول، يمكنك تحسينه
                                </div>
                            <?php else: ?>
                                <div class="message poor">
                                    <i class="fas fa-book-open"></i>
                                    تحتاج للمزيد من المراجعة والدراسة
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- معلومات المحاولة -->
                        <div class="attempt-info" style="background: #f8f9fa; padding: 1rem; border-radius: 10px; margin: 1.5rem 0;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span><i class="fas fa-clipboard-check"></i> المحاولة <?php echo $attemptInfo['attempts_count']; ?> من 3</span>
                                <?php if ($attemptInfo['attempts_count'] < 3): ?>
                                    <span style="color: #27ae60;"><i class="fas fa-check"></i> يمكنك المحاولة مرة أخرى</span>
                                <?php else: ?>
                                    <span style="color: #e74c3c;"><i class="fas fa-clock"></i> آخر محاولة متاحة</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="results-actions">
                            <?php if ($attemptInfo['attempts_count'] < 3): ?>
                                <a href="?restart=1" class="btn btn-primary">
                                    <i class="fas fa-redo"></i>
                                    محاولة أخرى (<?php echo 3 - $attemptInfo['attempts_count']; ?> متبقية)
                                </a>
                            <?php else: ?>
                                <button class="btn btn-secondary" disabled>
                                    <i class="fas fa-ban"></i>
                                    استنفدت جميع المحاولات
                                </button>
                            <?php endif; ?>
                            
                            <?php if ($currentUser): ?>
                                <a href="profile.php" class="btn btn-secondary">
                                    <i class="fas fa-user"></i>
                                    الملف الشخصي
                                </a>
                            <?php else: ?>
                                <a href="auth.php" class="btn btn-secondary">
                                    <i class="fas fa-sign-in-alt"></i>
                                    تسجيل الدخول لحفظ النتيجة
                                </a>
                            <?php endif; ?>
                            <a href="admin_dashboard.php" class="btn btn-secondary">
                                <i class="fas fa-cog"></i>
                                إدارة الأسئلة
                            </a>
                        </div>
                        
                        <!-- مراجعة الإجابات -->
                        <div class="review-section">
                            <h3>
                                <i class="fas fa-search"></i>
                                مراجعة مفصلة للإجابات
                            </h3>
                            <div class="review-container">
                                <?php foreach ($questions as $index => $question): ?>
                                    <?php 
                                    $userAnswer = isset($_SESSION['answers'][$index]) ? $_SESSION['answers'][$index] : null;
                                    $correctAnswer = $question['correct_answer'];
                                    $isCorrect = false;
                                    
                                    // التحقق من صحة الإجابة حسب نوع السؤال
                                    if ($question['question_type'] === 'open_text') {
                                        $isCorrect = !empty($userAnswer);
                                    } else {
                                        $isCorrect = ($userAnswer == $correctAnswer);
                                    }
                                    ?>
                                    <div class="review-item <?php echo $isCorrect ? 'correct' : 'incorrect'; ?>">
                                        <div class="result-indicator">
                                            <?php if ($isCorrect): ?>
                                                <i class="fas fa-check-circle correct-icon"></i>
                                            <?php else: ?>
                                                <i class="fas fa-times-circle incorrect-icon"></i>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="review-question">
                                            <div class="question-info">
                                                <strong>السؤال <?php echo $index + 1; ?>:</strong> 
                                                <?php echo htmlspecialchars($question['question']); ?>
                                                <span class="question-type-small">
                                                    <?php 
                                                    switch($question['question_type']) {
                                                        case 'multiple_choice': echo 'اختيار متعدد'; break;
                                                        case 'true_false': echo 'صح/خطأ'; break;
                                                        case 'open_text': echo 'نص مفتوح'; break;
                                                    }
                                                    ?>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <div class="review-answer">
                                            <?php if ($question['question_type'] === 'open_text'): ?>
                                                <!-- إجابة النص المفتوح -->
                                                <div class="your-text-answer">
                                                    <span class="label">
                                                        <i class="fas fa-user"></i> إجابتك:
                                                    </span>
                                                    <div class="text-answer-display">
                                                        <?php echo $userAnswer ? nl2br(htmlspecialchars($userAnswer)) : '<em style="color: #666;">لم تتم الإجابة على هذا السؤال</em>'; ?>
                                                    </div>
                                                </div>
                                                <div class="model-answer">
                                                    <span class="label">
                                                        <i class="fas fa-star"></i> الإجابة النموذجية:
                                                    </span>
                                                    <div class="text-answer-display">
                                                        <?php echo nl2br(htmlspecialchars($question['correct_text'] ?: 'لم يتم تحديد إجابة نموذجية')); ?>
                                                    </div>
                                                </div>
                                                
                                            <?php else: ?>
                                                <!-- إجابة الاختيار المتعدد وصح/خطأ -->
                                                <div class="your-answer">
                                                    <span class="label">
                                                        <i class="fas fa-user"></i> إجابتك:
                                                    </span>
                                                    <span class="answer <?php echo $isCorrect ? 'correct-text' : 'incorrect-text'; ?>">
                                                        <?php 
                                                        if ($userAnswer) {
                                                            if ($question['question_type'] === 'true_false') {
                                                                echo ($userAnswer == 1) ? 'صحيح' : 'خطأ';
                                                            } else {
                                                                // اختيار متعدد
                                                                $optionKey = "option_$userAnswer";
                                                                echo htmlspecialchars($question[$optionKey] ?: 'خيار غير محدد');
                                                            }
                                                        } else {
                                                            echo '<em>لم تتم الإجابة</em>';
                                                        }
                                                        ?>
                                                    </span>
                                                </div>
                                                
                                                <?php if (!$isCorrect): ?>
                                                    <div class="correct-answer">
                                                        <span class="label">
                                                            <i class="fas fa-check"></i> الإجابة الصحيحة:
                                                        </span>
                                                        <span class="answer correct-text">
                                                            <?php 
                                                            if ($question['question_type'] === 'true_false') {
                                                                echo ($correctAnswer == 1) ? 'صحيح' : 'خطأ';
                                                            } else {
                                                                // اختيار متعدد
                                                                $correctOptionKey = "option_$correctAnswer";
                                                                echo htmlspecialchars($question[$correctOptionKey] ?: 'خيار غير محدد');
                                                            }
                                                            ?>
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>

        <footer class="quiz-footer">
            <div class="container">
                <p>&copy; 2025 نظام الاختبارات التفاعلي المتقدم. جميع الحقوق محفوظة.</p>
                <p style="font-size: 0.9rem; opacity: 0.8; margin-top: 0.5rem;">
                    مصمم بعناية لتقديم أفضل تجربة تعليمية تفاعلية
                </p>
            </div>
        </footer>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('quizForm');
            const submitBtn = document.getElementById('submitBtn');
            const options = document.querySelectorAll('input[name="answer"]');
            const textAnswer = document.getElementById('text_answer');
            const charCount = document.getElementById('charCount');
            
            let formSubmitted = false;
            let autoSaveTimer;
            
            // عداد الوقت المتبقي
            <?php if ($quizBlocked && isset($attemptInfo['time_until_reset'])): ?>
                let timeRemaining = <?php echo $attemptInfo['time_until_reset']; ?>;
                const countdownDisplay = document.getElementById('timeRemaining');
                const buttonCountdown = document.getElementById('buttonCountdown');
                const retryButton = document.getElementById('retryButton');
                
                const countdownTimer = setInterval(function() {
                    timeRemaining--;
                    
                    if (timeRemaining <= 0) {
                        clearInterval(countdownTimer);
                        location.reload(); // إعادة تحميل الصفحة
                        return;
                    }
                    
                    // تحديث العرض
                    const minutes = Math.floor(timeRemaining / 60);
                    const seconds = timeRemaining % 60;
                    
                    if (countdownDisplay) {
                        countdownDisplay.innerHTML = `<i class="fas fa-clock"></i> ${minutes} دقيقة و ${seconds} ثانية`;
                    }
                    
                    if (buttonCountdown) {
                        buttonCountdown.textContent = Math.ceil(timeRemaining / 60);
                    }
                    
                    // تحديث شريط التقدم في الزر
                    if (retryButton) {
                        const totalTime = <?php echo $attemptInfo['time_until_reset'] ?? 0; ?>;
                        const progress = ((totalTime - timeRemaining) / totalTime) * 100;
                        retryButton.style.setProperty('--progress', progress + '%');
                    }
                    
                    // تفعيل الزر عندما يحين الوقت
                    if (timeRemaining <= 0 && retryButton) {
                        retryButton.disabled = false;
                        retryButton.innerHTML = '<span><i class="fas fa-redo"></i> إعادة المحاولة الآن</span>';
                        retryButton.onclick = function() {
                            window.location.href = '?restart=1';
                        };
                    }
                }, 1000);
            <?php endif; ?>
            
            function triggerAutoSave() {
                const autoSaveIndicator = document.querySelector('.auto-save-indicator i');
                if (autoSaveIndicator) {
                    autoSaveIndicator.style.display = 'inline';
                }
                
                clearTimeout(autoSaveTimer);
                autoSaveTimer = setTimeout(() => {
                    if (!submitBtn.disabled) {
                        formSubmitted = true;
                        form.submit();
                    }
                }, 1000);
            }
            
            // Handle multiple choice and true/false questions
            options.forEach(option => {
                option.addEventListener('change', function() {
                    submitBtn.disabled = false;
                    
                    // Remove previous selection styling
                    document.querySelectorAll('.option-label').forEach(label => {
                        label.classList.remove('selected');
                    });
                    
                    // Add selection styling with animation
                    this.closest('.option-label').classList.add('selected');
                    
                    // Trigger immediate auto-save for quick transition
                    clearTimeout(autoSaveTimer);
                    formSubmitted = true;
                    
                    // Show transition message
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري التقدم للسؤال التالي...';
                    submitBtn.disabled = true;
                    
                    // Immediate transition after 800ms for smooth experience
                    setTimeout(() => {
                        form.submit();
                    }, 800);
                });
            });
            
            // Handle text questions
            if (textAnswer) {
                textAnswer.addEventListener('input', function() {
                    const length = this.value.length;
                    charCount.textContent = length;
                    
                    // Update counter color
                    if (length >= 10) {
                        submitBtn.disabled = false;
                        submitBtn.classList.add('enabled');
                        charCount.style.color = 'var(--success-color)';
                        triggerAutoSave();
                    } else {
                        submitBtn.disabled = true;
                        submitBtn.classList.remove('enabled');
                        charCount.style.color = 'var(--danger-color)';
                        clearTimeout(autoSaveTimer);
                    }
                });
                
                // Initialize character count
                const initialLength = textAnswer.value.length;
                charCount.textContent = initialLength;
                if (initialLength >= 10) {
                    submitBtn.disabled = false;
                }
            }
            
            // Form submission handling
            if (form) {
                form.addEventListener('submit', function(e) {
                    formSubmitted = true;
                    
                    // For multiple choice and true/false
                    if (options.length > 0) {
                        const selectedOption = document.querySelector('input[name="answer"]:checked');
                        if (!selectedOption) {
                            e.preventDefault();
                            formSubmitted = false;
                            showAlert('يرجى اختيار إجابة قبل المتابعة', 'warning');
                            return false;
                        }
                    }
                    
                    // For text questions
                    if (textAnswer) {
                        if (textAnswer.value.trim().length < 10) {
                            e.preventDefault();
                            formSubmitted = false;
                            showAlert('يجب أن تكون الإجابة 10 أحرف على الأقل', 'warning');
                            return false;
                        }
                    }
                    
                    // Show loading state
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الحفظ...';
                    submitBtn.disabled = true;
                });
            }
            
            // Animate elements on load
            const optionLabels = document.querySelectorAll('.option-label');
            optionLabels.forEach((label, index) => {
                label.style.animationDelay = `${index * 0.15}s`;
                label.classList.add('slide-in');
            });
            
            // Animate score circle if present
            const scoreCircle = document.querySelector('.score-circle');
            if (scoreCircle) {
                setTimeout(() => {
                    scoreCircle.classList.add('animate');
                }, 800);
            }
            
            // Keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // For multiple choice: 1,2,3,4 keys
                if (options.length > 0 && e.key >= '1' && e.key <= '4') {
                    const optionIndex = parseInt(e.key) - 1;
                    if (options[optionIndex]) {
                        options[optionIndex].click();
                    }
                }
                
                // Ctrl+Enter to submit (if enabled)
                if (e.key === 'Enter' && e.ctrlKey && !submitBtn.disabled) {
                    formSubmitted = true;
                    form.submit();
                }
            });
            
            // Custom alert function
            function showAlert(message, type = 'info') {
                const alertDiv = document.createElement('div');
                alertDiv.className = `alert alert-${type}`;
                alertDiv.innerHTML = `
                    <i class="fas fa-${type === 'warning' ? 'exclamation-triangle' : 'info-circle'}"></i>
                    ${message}
                `;
                
                document.querySelector('.container').prepend(alertDiv);
                
                setTimeout(() => {
                    alertDiv.style.opacity = '0';
                    setTimeout(() => alertDiv.remove(), 300);
                }, 4000);
            }
            
            // Prevent accidental page reload
            window.addEventListener('beforeunload', function(e) {
                if (formSubmitted) {
                    return;
                }
            });
        });
    </script>
</body>
</html>