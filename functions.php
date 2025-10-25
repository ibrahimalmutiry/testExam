<?php

/**
 * Database configuration and connection
 */
function getDBConnection() {
    // قم بتغيير هذه الإعدادات حسب خادمك
    $host = 'localhost';
    $dbname = 'quiz_system';
    $username = 'root';        // غيّر هذا إذا لزم الأمر
    $password = '';            // أدخل كلمة المرور إذا لزم الأمر
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        // عرض رسالة خطأ واضحة
        die("<div style='padding:20px;background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;border-radius:5px;margin:20px;'>
             <h3>خطأ في الاتصال بقاعدة البيانات:</h3>
             <p>" . $e->getMessage() . "</p>
             <h4>تحقق من:</h4>
             <ul>
                <li>تشغيل خدمة MySQL</li>
                <li>صحة اسم المستخدم وكلمة المرور</li>
                <li>وجود قاعدة البيانات 'quiz_system'</li>
             </ul>
             </div>");
    }
}

/**
 * Initialize database and create tables if they don't exist
 */
function initializeDatabase() {
    $pdo = getDBConnection();
    
    $sql = "
    CREATE TABLE IF NOT EXISTS questions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        question TEXT NOT NULL,
        question_type ENUM('multiple_choice', 'true_false', 'open_text') NOT NULL DEFAULT 'multiple_choice',
        option_1 VARCHAR(255) DEFAULT NULL,
        option_2 VARCHAR(255) DEFAULT NULL,
        option_3 VARCHAR(255) DEFAULT NULL,
        option_4 VARCHAR(255) DEFAULT NULL,
        correct_answer INT DEFAULT NULL,
        correct_text TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_created_at (created_at),
        INDEX idx_question_type (question_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    
    CREATE TABLE IF NOT EXISTS quiz_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        score INT NOT NULL,
        total_questions INT NOT NULL,
        score_percentage DECIMAL(5,2) NOT NULL,
        user_agent TEXT,
        ip_address VARCHAR(45),
        attempt_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_attempt_date (attempt_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    
    CREATE TABLE IF NOT EXISTS quiz_answers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id VARCHAR(255) NOT NULL,
        question_id INT NOT NULL,
        selected_option INT DEFAULT NULL,
        text_answer TEXT DEFAULT NULL,
        is_correct BOOLEAN DEFAULT FALSE,
        answered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_session (session_id),
        INDEX idx_question (question_id),
        FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    try {
        $pdo->exec($sql);
        return true;
    } catch (PDOException $e) {
        error_log("خطأ في إنشاء الجداول: " . $e->getMessage());
        return false;
    }
}

/**
 * Add a new question to the database - Fixed version
 */
function addQuestion($question, $questionType, $correctAnswer = null, $option1 = '', $option2 = '', $option3 = '', $option4 = '', $correctText = '') {
    $pdo = getDBConnection();
    
    $sql = "INSERT INTO questions (question, question_type, option_1, option_2, option_3, option_4, correct_answer, correct_text) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    try {
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            $question,
            $questionType,
            $option1,
            $option2,
            $option3,
            $option4,
            $correctAnswer,
            $correctText
        ]);
        
        if (!$result) {
            error_log("خطأ في تنفيذ الاستعلام: " . implode(', ', $stmt->errorInfo()));
            return false;
        }
        
        return $stmt->rowCount() > 0;
        
    } catch (PDOException $e) {
        error_log("خطأ في إضافة السؤال: " . $e->getMessage());
        return false;
    }
}

/**
 * Get all questions from the database
 */
function getAllQuestions() {
    $pdo = getDBConnection();
    
    $sql = "SELECT * FROM questions ORDER BY created_at ASC";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("خطأ في جلب الأسئلة: " . $e->getMessage());
        return [];
    }
}

/**
 * Get a specific question by ID
 */
function getQuestionById($id) {
    $pdo = getDBConnection();
    
    $sql = "SELECT * FROM questions WHERE id = :id";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("خطأ في جلب السؤال: " . $e->getMessage());
        return false;
    }
}

/**
 * Update an existing question
 */
function updateQuestion($id, $question, $option1, $option2, $correctAnswer, $option3 = '', $option4 = '') {
    $pdo = getDBConnection();
    
    $sql = "UPDATE questions 
            SET question = :question, 
                option_1 = :option1, 
                option_2 = :option2, 
                option_3 = :option3, 
                option_4 = :option4, 
                correct_answer = :correct_answer,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':question' => $question,
            ':option1' => $option1,
            ':option2' => $option2,
            ':option3' => $option3,
            ':option4' => $option4,
            ':correct_answer' => $correctAnswer
        ]);
        
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("خطأ في تحديث السؤال: " . $e->getMessage());
        return false;
    }
}

/**
 * Delete a question from the database
 */
function deleteQuestion($id) {
    $pdo = getDBConnection();
    
    $sql = "DELETE FROM questions WHERE id = :id";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("خطأ في حذف السؤال: " . $e->getMessage());
        return false;
    }
}

/**
 * Save user answer (auto-save functionality)
 */
function saveAnswer($sessionId, $questionId, $selectedOption = null, $textAnswer = '') {
    $pdo = getDBConnection();
    
    // Get question details to check correctness
    $question = getQuestionById($questionId);
    $isCorrect = false;
    
    if ($question) {
        if ($question['question_type'] === 'multiple_choice' && $selectedOption === $question['correct_answer']) {
            $isCorrect = true;
        } elseif ($question['question_type'] === 'true_false' && $selectedOption === $question['correct_answer']) {
            $isCorrect = true;
        } elseif ($question['question_type'] === 'open_text' && !empty($textAnswer)) {
            // For open text, we can implement keyword matching or manual review
            $isCorrect = checkTextAnswer($textAnswer, $question['correct_text']);
        }
    }
    
    $sql = "INSERT INTO quiz_answers (session_id, question_id, selected_option, text_answer, is_correct) 
            VALUES (:session_id, :question_id, :selected_option, :text_answer, :is_correct)
            ON DUPLICATE KEY UPDATE 
            selected_option = :selected_option,
            text_answer = :text_answer,
            is_correct = :is_correct,
            answered_at = CURRENT_TIMESTAMP";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':session_id' => $sessionId,
            ':question_id' => $questionId,
            ':selected_option' => $selectedOption,
            ':text_answer' => $textAnswer,
            ':is_correct' => $isCorrect
        ]);
        
        return $isCorrect;
    } catch (PDOException $e) {
        error_log("خطأ في حفظ الإجابة: " . $e->getMessage());
        return false;
    }
}

/**
 * Check text answer against correct answer
 */
function checkTextAnswer($userAnswer, $correctAnswer) {
    if (empty($correctAnswer)) return false;
    
    // Simple similarity check - can be enhanced with NLP
    $userWords = explode(' ', strtolower(trim($userAnswer)));
    $correctWords = explode(' ', strtolower(trim($correctAnswer)));
    
    $matchCount = 0;
    foreach ($userWords as $word) {
        if (in_array($word, $correctWords)) {
            $matchCount++;
        }
    }
    
    // Consider correct if 60% of words match
    $similarity = $matchCount / count($correctWords);
    return $similarity >= 0.6;
}

/**
 * Get saved answers for a session
 */
function getSavedAnswers($sessionId) {
    $pdo = getDBConnection();
    
    $sql = "SELECT * FROM quiz_answers WHERE session_id = :session_id ORDER BY question_id";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':session_id' => $sessionId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("خطأ في جلب الإجابات المحفوظة: " . $e->getMessage());
        return [];
    }
}
function getQuestionsCount() {
    $pdo = getDBConnection();
    
    $sql = "SELECT COUNT(*) as count FROM questions";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch();
        return $result['count'];
    } catch (PDOException $e) {
        error_log("خطأ في حساب الأسئلة: " . $e->getMessage());
        return 0;
    }
}

/**
 * Validate question data
 */
function validateQuestionData($question, $option1, $option2, $correctAnswer, $option3 = '', $option4 = '') {
    $errors = [];
    
    // Validate question text
    if (empty(trim($question))) {
        $errors[] = 'نص السؤال مطلوب';
    } elseif (strlen(trim($question)) < 10) {
        $errors[] = 'نص السؤال يجب أن يكون 10 أحرف على الأقل';
    }
    
    // Validate required options
    if (empty(trim($option1))) {
        $errors[] = 'الخيار الأول مطلوب';
    }
    
    if (empty(trim($option2))) {
        $errors[] = 'الخيار الثاني مطلوب';
    }
    
    // Validate correct answer
    if (!in_array($correctAnswer, [1, 2, 3, 4])) {
        $errors[] = 'رقم الإجابة الصحيحة غير صالح';
    } else {
        // Check if the correct answer option is filled
        $options = [$option1, $option2, $option3, $option4];
        if (empty(trim($options[$correctAnswer - 1]))) {
            $errors[] = 'الإجابة الصحيحة المحددة فارغة';
        }
    }
    
    return $errors;
}

/**
 * Sanitize input data
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Calculate quiz statistics
 */
function calculateQuizStats($userAnswers, $questions) {
    $totalQuestions = count($questions);
    $correctAnswers = 0;
    $results = [];
    
    foreach ($questions as $index => $question) {
        $userAnswer = isset($userAnswers[$index]) ? $userAnswers[$index] : null;
        $isCorrect = ($userAnswer === $question['correct_answer']);
        
        if ($isCorrect) {
            $correctAnswers++;
        }
        
        $results[] = [
            'question' => $question,
            'user_answer' => $userAnswer,
            'correct_answer' => $question['correct_answer'],
            'is_correct' => $isCorrect
        ];
    }
    
    return [
        'total_questions' => $totalQuestions,
        'correct_answers' => $correctAnswers,
        'score_percentage' => ($totalQuestions > 0) ? ($correctAnswers / $totalQuestions) * 100 : 0,
        'results' => $results
    ];
}

/**
 * Get random questions for quiz (optional feature)
 */
function getRandomQuestions($limit = null) {
    $pdo = getDBConnection();
    
    $sql = "SELECT * FROM questions ORDER BY RAND()";
    if ($limit) {
        $sql .= " LIMIT :limit";
    }
    
    try {
        $stmt = $pdo->prepare($sql);
        if ($limit) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("خطأ في جلب الأسئلة العشوائية: " . $e->getMessage());
        return [];
    }
}

/**
 * Search questions by keyword
 */
function searchQuestions($keyword) {
    $pdo = getDBConnection();
    
    $sql = "SELECT * FROM questions 
            WHERE question LIKE :keyword 
               OR option_1 LIKE :keyword 
               OR option_2 LIKE :keyword 
               OR option_3 LIKE :keyword 
               OR option_4 LIKE :keyword
            ORDER BY created_at DESC";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':keyword' => '%' . $keyword . '%']);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("خطأ في البحث: " . $e->getMessage());
        return [];
    }
}

/**
 * Check if database exists and create if not
 */
function checkAndCreateDatabase() {
    $host = 'localhost';
    $username = 'root';
    $password = '';
    $dbname = 'quiz_system';
    
    try {
        // Connect without database name first
        $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create database if it doesn't exist
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        
        return true;
    } catch (PDOException $e) {
        error_log("خطأ في إنشاء قاعدة البيانات: " . $e->getMessage());
        return false;
    }
}

/**
 * Export questions to JSON format
 */
function exportQuestionsToJson() {
    $questions = getAllQuestions();
    
    $exportData = [
        'export_date' => date('Y-m-d H:i:s'),
        'total_questions' => count($questions),
        'questions' => $questions
    ];
    
    return json_encode($exportData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

/**
 * Import questions from JSON format
 */
function importQuestionsFromJson($jsonData) {
    try {
        $data = json_decode($jsonData, true);
        
        if (!$data || !isset($data['questions'])) {
            return ['success' => false, 'message' => 'بيانات غير صالحة'];
        }
        
        $imported = 0;
        $errors = [];
        
        foreach ($data['questions'] as $questionData) {
            $validationErrors = validateQuestionData(
                $questionData['question'] ?? '',
                $questionData['option_1'] ?? '',
                $questionData['option_2'] ?? '',
                $questionData['correct_answer'] ?? 0,
                $questionData['option_3'] ?? '',
                $questionData['option_4'] ?? ''
            );
            
            if (empty($validationErrors)) {
                if (addQuestion(
                    $questionData['question'],
                    $questionData['option_1'],
                    $questionData['option_2'],
                    $questionData['correct_answer'],
                    $questionData['option_3'] ?? '',
                    $questionData['option_4'] ?? ''
                )) {
                    $imported++;
                }
            } else {
                $errors[] = implode(', ', $validationErrors);
            }
        }
        
        return [
            'success' => true,
            'imported' => $imported,
            'errors' => $errors,
            'message' => "تم استيراد $imported سؤال"
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'خطأ في معالجة البيانات: ' . $e->getMessage()];
    }
}

/**
 * Get quiz performance statistics
 */
function getQuizStatistics() {
    $pdo = getDBConnection();
    
    try {
        // This would require a quiz_results table for full implementation
        // For now, return basic question statistics
        $sql = "SELECT 
                    COUNT(*) as total_questions,
                    AVG(CASE WHEN option_3 IS NOT NULL AND option_3 != '' THEN 1 ELSE 0 END) as has_option3_rate,
                    AVG(CASE WHEN option_4 IS NOT NULL AND option_4 != '' THEN 1 ELSE 0 END) as has_option4_rate,
                    MIN(created_at) as oldest_question,
                    MAX(created_at) as newest_question
                FROM questions";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $stats = $stmt->fetch();
        
        return $stats;
    } catch (PDOException $e) {
        error_log("خطأ في جلب الإحصائيات: " . $e->getMessage());
        return null;
    }
}

/**
 * Backup all questions
 */
function backupQuestions() {
    $questions = getAllQuestions();
    $backupData = [
        'backup_date' => date('Y-m-d H:i:s'),
        'version' => '1.0',
        'total_questions' => count($questions),
        'questions' => $questions
    ];
    
    $filename = 'quiz_backup_' . date('Y-m-d_H-i-s') . '.json';
    $filepath = 'backups/' . $filename;
    
    // Create backups directory if it doesn't exist
    if (!is_dir('backups')) {
        mkdir('backups', 0755, true);
    }
    
    try {
        file_put_contents($filepath, json_encode($backupData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        return ['success' => true, 'filename' => $filename, 'filepath' => $filepath];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'خطأ في إنشاء النسخة الاحتياطية: ' . $e->getMessage()];
    }
}

/**
 * Get questions with pagination
 */
function getQuestionsPaginated($page = 1, $perPage = 10) {
    $pdo = getDBConnection();
    $offset = ($page - 1) * $perPage;
    
    try {
        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM questions";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute();
        $totalQuestions = $countStmt->fetch()['total'];
        
        // Get paginated questions
        $sql = "SELECT * FROM questions ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $questions = $stmt->fetchAll();
        
        return [
            'questions' => $questions,
            'total' => $totalQuestions,
            'current_page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($totalQuestions / $perPage)
        ];
    } catch (PDOException $e) {
        error_log("خطأ في جلب الأسئلة بالصفحات: " . $e->getMessage());
        return [
            'questions' => [],
            'total' => 0,
            'current_page' => 1,
            'per_page' => $perPage,
            'total_pages' => 0
        ];
    }
}

/**
 * Duplicate a question
 */
function duplicateQuestion($id) {
    $question = getQuestionById($id);
    
    if (!$question) {
        return false;
    }
    
    // Add "(نسخة)" to the question text
    $newQuestion = $question['question'] . ' (نسخة)';
    
    return addQuestion(
        $newQuestion,
        $question['option_1'],
        $question['option_2'],
        $question['correct_answer'],
        $question['option_3'],
        $question['option_4']
    );
}

/**
 * Format date in Arabic
 */
function formatArabicDate($date) {
    $arabicMonths = [
        1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
        5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
        9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر'
    ];
    
    $timestamp = strtotime($date);
    $day = date('j', $timestamp);
    $month = $arabicMonths[(int)date('n', $timestamp)];
    $year = date('Y', $timestamp);
    
    return "$day $month $year";
}

/**
 * Log quiz attempt (for future analytics)
 */
function logQuizAttempt($score, $totalQuestions, $userAgent = '') {
    $pdo = getDBConnection();
    
    // Create quiz_attempts table if it doesn't exist
    $createTableSql = "
    CREATE TABLE IF NOT EXISTS quiz_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        score INT NOT NULL,
        total_questions INT NOT NULL,
        score_percentage DECIMAL(5,2) NOT NULL,
        user_agent TEXT,
        ip_address VARCHAR(45),
        attempt_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    try {
        $pdo->exec($createTableSql);
        
        $percentage = ($totalQuestions > 0) ? ($score / $totalQuestions) * 100 : 0;
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        $sql = "INSERT INTO quiz_attempts (score, total_questions, score_percentage, user_agent, ip_address) 
                VALUES (:score, :total_questions, :percentage, :user_agent, :ip_address)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':score' => $score,
            ':total_questions' => $totalQuestions,
            ':percentage' => $percentage,
            ':user_agent' => $userAgent,
            ':ip_address' => $ipAddress
        ]);
        
        return true;
    } catch (PDOException $e) {
        error_log("خطأ في تسجيل محاولة الاختبار: " . $e->getMessage());
        return false;
    }
}

/**
 * Get quiz performance analytics
 */
function getQuizAnalytics() {
    $pdo = getDBConnection();
    
    try {
        $sql = "SELECT 
                    COUNT(*) as total_attempts,
                    AVG(score_percentage) as avg_score,
                    MAX(score_percentage) as highest_score,
                    MIN(score_percentage) as lowest_score,
                    COUNT(CASE WHEN score_percentage >= 80 THEN 1 END) as high_performers,
                    DATE(attempt_date) as attempt_date,
                    COUNT(*) as daily_attempts
                FROM quiz_attempts 
                WHERE attempt_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY DATE(attempt_date)
                ORDER BY attempt_date DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("خطأ في جلب تحليلات الاختبار: " . $e->getMessage());
        return [];
    }
}

/**
 * Check database connection and setup
 */
function checkDatabaseSetup() {
    try {
        checkAndCreateDatabase();
        initializeDatabase();
        return true;
    } catch (Exception $e) {
        error_log("خطأ في إعداد قاعدة البيانات: " . $e->getMessage());
        return false;
    }
}

/**
 * Clean old quiz attempts (data retention)
 */
function cleanOldAttempts($daysToKeep = 90) {
    $pdo = getDBConnection();
    
    $sql = "DELETE FROM quiz_attempts WHERE attempt_date < DATE_SUB(NOW(), INTERVAL :days DAY)";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':days' => $daysToKeep]);
        return $stmt->rowCount();
    } catch (PDOException $e) {
        error_log("خطأ في تنظيف البيانات القديمة: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get question difficulty based on user performance
 */
function getQuestionDifficulty($questionId) {
    $pdo = getDBConnection();
    
    try {
        // This would require storing individual question results
        // For now, return a placeholder
        $sql = "SELECT COUNT(*) as usage_count FROM questions WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $questionId]);
        
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("خطأ في حساب صعوبة السؤال: " . $e->getMessage());
        return null;
    }
}

/**
 * Generate quiz report
 */
function generateQuizReport($userAnswers, $questions) {
    $stats = calculateQuizStats($userAnswers, $questions);
    $reportDate = date('Y-m-d H:i:s');
    
    $report = [
        'report_date' => $reportDate,
        'quiz_stats' => $stats,
        'performance_level' => getPerformanceLevel($stats['score_percentage']),
        'recommendations' => getStudyRecommendations($stats['score_percentage'], $stats['results'])
    ];
    
    return $report;
}

/**
 * Get performance level based on score
 */
function getPerformanceLevel($percentage) {
    if ($percentage >= 90) return 'ممتاز';
    if ($percentage >= 80) return 'جيد جداً';
    if ($percentage >= 70) return 'جيد';
    if ($percentage >= 60) return 'مقبول';
    return 'يحتاج تحسين';
}

/**
 * Get study recommendations
 */
function getStudyRecommendations($percentage, $results) {
    $recommendations = [];
    
    if ($percentage < 70) {
        $recommendations[] = 'راجع المواضيع الأساسية مرة أخرى';
        $recommendations[] = 'اطلب المساعدة من المعلم أو الزملاء';
    }
    
    if ($percentage < 85) {
        $recommendations[] = 'ركز على الأسئلة التي أخطأت فيها';
        $recommendations[] = 'مارس المزيد من الأسئلة المشابهة';
    }
    
    if ($percentage >= 85) {
        $recommendations[] = 'أداء ممتاز! استمر في المراجعة المنتظمة';
        $recommendations[] = 'ساعد زملاءك في فهم المواضيع';
    }
    
    // Add specific recommendations based on wrong answers
    $wrongTopics = [];
    foreach ($results as $result) {
        if (!$result['is_correct']) {
            // This could be enhanced with topic categorization
            $wrongTopics[] = 'مراجعة موضوع: ' . substr($result['question']['question'], 0, 50) . '...';
        }
    }
    
    if (!empty($wrongTopics)) {
        $recommendations = array_merge($recommendations, array_slice($wrongTopics, 0, 3));
    }
    
    return $recommendations;
}

// Initialize database when functions.php is included
if (!defined('DB_SETUP_DONE')) {
    checkDatabaseSetup();
    define('DB_SETUP_DONE', true);
}

// إضافة هذه الوظائف إلى ملف functions.php

/**
 * تتبع محاولات المستخدم في الجلسة الواحدة
 */
function trackQuizAttempt() {
    session_start();
    
    // إنشاء معرف فريد للمستخدم (IP + User Agent)
    $userFingerprint = md5($_SERVER['REMOTE_ADDR'] . ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    
    // التحقق من وجود سجل المحاولات
    if (!isset($_SESSION['quiz_attempts'])) {
        $_SESSION['quiz_attempts'] = [];
    }
    
    // إضافة محاولة جديدة
    $currentTime = time();
    $_SESSION['quiz_attempts'][] = [
        'fingerprint' => $userFingerprint,
        'timestamp' => $currentTime,
        'completed' => false
    ];
    
    // تنظيف المحاولات القديمة (أكبر من 30 دقيقة)
    $_SESSION['quiz_attempts'] = array_filter($_SESSION['quiz_attempts'], function($attempt) {
        return (time() - $attempt['timestamp']) < (30 * 60); // 30 دقيقة
    });
    
    return true;
}

/**
 * التحقق من إمكانية بدء محاولة جديدة
 */
function canStartNewAttempt() {
    session_start();
    
    $userFingerprint = md5($_SERVER['REMOTE_ADDR'] . ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    $currentTime = time();
    
    if (!isset($_SESSION['quiz_attempts'])) {
        return ['can_attempt' => true, 'attempts_count' => 0];
    }
    
    // تنظيف المحاولات القديمة
    $_SESSION['quiz_attempts'] = array_filter($_SESSION['quiz_attempts'], function($attempt) use ($currentTime) {
        return ($currentTime - $attempt['timestamp']) < (30 * 60);
    });
    
    // عد المحاولات الحالية للمستخدم
    $userAttempts = array_filter($_SESSION['quiz_attempts'], function($attempt) use ($userFingerprint) {
        return $attempt['fingerprint'] === $userFingerprint;
    });
    
    $attemptsCount = count($userAttempts);
    
    if ($attemptsCount >= 3) {
        // البحث عن أقدم محاولة لحساب وقت الانتظار المتبقي
        $oldestAttempt = min(array_column($userAttempts, 'timestamp'));
        $timeUntilReset = ($oldestAttempt + (30 * 60)) - $currentTime;
        
        return [
            'can_attempt' => false,
            'attempts_count' => $attemptsCount,
            'time_until_reset' => max(0, $timeUntilReset),
            'reset_time' => date('H:i', $oldestAttempt + (30 * 60))
        ];
    }
    
    return [
        'can_attempt' => true,
        'attempts_count' => $attemptsCount,
        'remaining_attempts' => 3 - $attemptsCount
    ];
}

/**
 * تحديث حالة المحاولة عند الإكمال
 */
function markAttemptCompleted() {
    session_start();
    
    if (!isset($_SESSION['quiz_attempts'])) {
        return false;
    }
    
    $userFingerprint = md5($_SERVER['REMOTE_ADDR'] . ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    
    // العثور على أحدث محاولة للمستخدم وتحديث حالتها
    for ($i = count($_SESSION['quiz_attempts']) - 1; $i >= 0; $i--) {
        if ($_SESSION['quiz_attempts'][$i]['fingerprint'] === $userFingerprint && 
            !$_SESSION['quiz_attempts'][$i]['completed']) {
            $_SESSION['quiz_attempts'][$i]['completed'] = true;
            return true;
        }
    }
    
    return false;
}

/**
 * إعادة تعيين محاولات المستخدم (للإدارة)
 */
function resetUserAttempts($userFingerprint = null) {
    session_start();
    
    if ($userFingerprint === null) {
        $userFingerprint = md5($_SERVER['REMOTE_ADDR'] . ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    }
    
    if (!isset($_SESSION['quiz_attempts'])) {
        return true;
    }
    
    // إزالة محاولات المستخدم المحدد
    $_SESSION['quiz_attempts'] = array_filter($_SESSION['quiz_attempts'], function($attempt) use ($userFingerprint) {
        return $attempt['fingerprint'] !== $userFingerprint;
    });
    
    return true;
}

/**
 * الحصول على إحصائيات المحاولات
 */
function getAttemptStatistics() {
    session_start();
    
    if (!isset($_SESSION['quiz_attempts'])) {
        return [
            'total_attempts' => 0,
            'unique_users' => 0,
            'active_restrictions' => 0
        ];
    }
    
    $currentTime = time();
    $activeAttempts = array_filter($_SESSION['quiz_attempts'], function($attempt) use ($currentTime) {
        return ($currentTime - $attempt['timestamp']) < (30 * 60);
    });
    
    $uniqueUsers = array_unique(array_column($activeAttempts, 'fingerprint'));
    $restrictedUsers = [];
    
    foreach ($uniqueUsers as $fingerprint) {
        $userAttempts = array_filter($activeAttempts, function($attempt) use ($fingerprint) {
            return $attempt['fingerprint'] === $fingerprint;
        });
        
        if (count($userAttempts) >= 3) {
            $restrictedUsers[] = $fingerprint;
        }
    }
    
    return [
        'total_attempts' => count($activeAttempts),
        'unique_users' => count($uniqueUsers),
        'active_restrictions' => count($restrictedUsers),
        'attempts_in_last_hour' => count(array_filter($activeAttempts, function($attempt) use ($currentTime) {
            return ($currentTime - $attempt['timestamp']) < 3600;
        }))
    ];
}

/**
 * تنسيق وقت الانتظار
 */
function formatWaitTime($seconds) {
    if ($seconds <= 0) {
        return '0 ثانية';
    }
    
    $minutes = floor($seconds / 60);
    $remainingSeconds = $seconds % 60;
    
    if ($minutes > 0) {
        return $minutes . ' دقيقة' . ($remainingSeconds > 0 ? ' و ' . $remainingSeconds . ' ثانية' : '');
    } else {
        return $remainingSeconds . ' ثانية';
    }
}

/**
 * إنشاء رسالة تحذيرية للمحاولات
 */
function getAttemptWarningMessage($attemptInfo) {
    if (!$attemptInfo['can_attempt']) {
        return [
            'type' => 'error',
            'title' => 'تم الوصول للحد الأقصى من المحاولات',
            'message' => 'لقد استنفدت المحاولات المسموحة (3 محاولات). يمكنك المحاولة مرة أخرى بعد ' . 
                        formatWaitTime($attemptInfo['time_until_reset']) . 
                        ' في تمام الساعة ' . $attemptInfo['reset_time'],
            'icon' => 'fas fa-clock'
        ];
    } else if ($attemptInfo['attempts_count'] > 0) {
        return [
            'type' => 'warning',
            'title' => 'تنبيه: محاولات محدودة',
            'message' => 'لديك ' . $attemptInfo['remaining_attempts'] . ' محاولة متبقية من أصل 3 محاولات. ' .
                        'المحاولات تُحسب لمدة 30 دقيقة من أول محاولة.',
            'icon' => 'fas fa-exclamation-triangle'
        ];
    }
    
    return null;
}
?>