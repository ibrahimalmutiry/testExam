<?php
require_once 'functions.php';

$message = '';
$messageType = '';

// معالجة النموذج
if ($_POST) {
    if (isset($_POST['add_question'])) {
        $question = trim($_POST['question']);
        $questionType = $_POST['question_type'];
        
        // فحص أساسي للسؤال
        if (empty($question)) {
            $message = 'يجب ملء نص السؤال';
            $messageType = 'error';
        } else {
            
            // معالجة صح/خطأ
            if ($questionType === 'true_false') {
                if (isset($_POST['true_false_answer'])) {
                    $correctAnswer = (int)$_POST['true_false_answer'];
                    
                    if (addQuestion($question, $questionType, $correctAnswer, '', '', '', '', '')) {
                        $message = 'تم إضافة سؤال صح/خطأ بنجاح';
                        $messageType = 'success';
                    } else {
                        $message = 'خطأ في إضافة السؤال';
                        $messageType = 'error';
                    }
                } else {
                    $message = 'يجب اختيار الإجابة الصحيحة (صح أو خطأ)';
                    $messageType = 'error';
                }
            }
            
            // معالجة الاختيار المتعدد
            elseif ($questionType === 'multiple_choice') {
                $option1 = trim($_POST['option_1']);
                $option2 = trim($_POST['option_2']);
                $option3 = trim($_POST['option_3']);
                $option4 = trim($_POST['option_4']);
                $correctAnswer = (int)$_POST['correct_answer'];
                
                if (empty($option1) || empty($option2)) {
                    $message = 'يجب ملء خيارين على الأقل';
                    $messageType = 'error';
                } elseif ($correctAnswer < 1 || $correctAnswer > 4) {
                    $message = 'يجب اختيار رقم الإجابة الصحيحة';
                    $messageType = 'error';
                } else {
                    if (addQuestion($question, $questionType, $correctAnswer, $option1, $option2, $option3, $option4, '')) {
                        $message = 'تم إضافة السؤال بنجاح';
                        $messageType = 'success';
                    } else {
                        $message = 'خطأ في إضافة السؤال';
                        $messageType = 'error';
                    }
                }
            }
            
            // معالجة النص المفتوح
            elseif ($questionType === 'open_text') {
                $correctText = trim($_POST['correct_text']);
                
                if (empty($correctText)) {
                    $message = 'يجب إدخال الإجابة النموذجية';
                    $messageType = 'error';
                } else {
                    if (addQuestion($question, $questionType, null, '', '', '', '', $correctText)) {
                        $message = 'تم إضافة السؤال المفتوح بنجاح';
                        $messageType = 'success';
                    } else {
                        $message = 'خطأ في إضافة السؤال';
                        $messageType = 'error';
                    }
                }
            }
        }
    }
    
    if (isset($_POST['delete_question'])) {
        $questionId = (int)$_POST['question_id'];
        if (deleteQuestion($questionId)) {
            $message = 'تم حذف السؤال بنجاح';
            $messageType = 'success';
        } else {
            $message = 'خطأ في حذف السؤال';
            $messageType = 'error';
        }
    }
}

$questions = getAllQuestions();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة إدارة الاختبارات</title>
    <link rel="stylesheet" href="styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        .debug-info { background: #fffbf0; padding: 15px; margin: 10px 0; border-radius: 5px; border: 1px solid #ffeaa7; }
        .tf-option-simple { 
            display: inline-block; 
            margin: 10px 20px 10px 0; 
            padding: 10px; 
            border: 2px solid #ddd; 
            border-radius: 5px; 
            cursor: pointer; 
        }
        .tf-option-simple:hover { border-color: #3498db; }
        .tf-option-simple.selected { border-color: #27ae60; background: #d5f4e6; }
    </style>
</head>
<body>
    <div class="admin-container">
        <header class="admin-header">
            <div class="container">
                <h1><i class="fas fa-cogs"></i> لوحة إدارة الاختبارات</h1>
                <div class="header-actions">
                    <a href="quiz_interface.php" class="btn btn-outline">
                        <i class="fas fa-play"></i> بدء الاختبار
                    </a>
                </div>
            </div>
        </header>

        <main class="admin-main">
            <div class="container">
                
                <!-- <?php if ($_POST): ?>
                <div class="debug-info">
                    <h4>🔍 معلومات التشخيص:</h4>
                    <p><strong>نوع السؤال المرسل:</strong> <?php echo $_POST['question_type']; ?></p>
                    <p><strong>طول نص السؤال:</strong> <?php echo strlen(trim($_POST['question'])); ?></p>
                    <?php if (isset($_POST['true_false_answer'])): ?>
                        <p><strong>إجابة صح/خطأ:</strong> <?php echo $_POST['true_false_answer'] == 1 ? 'صح' : 'خطأ'; ?></p>
                    <?php endif; ?>
                    <details>
                        <summary>عرض جميع البيانات المرسلة</summary>
                        <pre><?php print_r($_POST); ?></pre>
                    </details>
                </div>
                <?php endif; ?> -->

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?> fade-in">
                        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <div class="admin-grid">
                    <!-- نموذج إضافة السؤال -->
                    <div class="admin-card">
                        <div class="card-header">
                            <h2><i class="fas fa-plus-circle"></i> إضافة سؤال جديد</h2>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="questionForm">
                                
                                <!-- نوع السؤال -->
                                <div class="form-group">
                                    <label for="question_type">نوع السؤال</label>
                                    <select id="question_type" name="question_type" class="form-control" required>
                                        <option value="true_false">صح/خطأ</option>
                                        <option value="multiple_choice">اختيار متعدد</option>
                                        <option value="open_text">نص مفتوح</option>
                                    </select>
                                </div>

                                <!-- نص السؤال -->
                                <div class="form-group">
                                    <label for="question">نص السؤال</label>
                                    <textarea 
                                        id="question" 
                                        name="question" 
                                        required 
                                        rows="3" 
                                        placeholder="اكتب السؤال هنا..."
                                        class="form-control"
                                    ></textarea>
                                </div>

                                <!-- قسم صح/خطأ -->
                                <div id="trueFalseSection" class="question-type-section">
                                    <h5>اختر الإجابة الصحيحة:</h5>
                                    <div style="margin: 15px 0;">
                                        <label class="tf-option-simple">
                                            <input type="radio" name="true_false_answer" value="1" style="margin-left: 8px;">
                                            ✅ صح
                                        </label>
                                        
                                        <label class="tf-option-simple">
                                            <input type="radio" name="true_false_answer" value="2" style="margin-left: 8px;">
                                            ❌ خطأ
                                        </label>
                                    </div>
                                </div>

                                <!-- قسم الاختيار المتعدد -->
                                <div id="multipleChoiceSection" class="question-type-section" style="display: none;">
                                    <h5>خيارات الاختيار المتعدد:</h5>
                                    <div class="form-group">
                                        <label>الخيار الأول *</label>
                                        <input type="text" name="option_1" placeholder="الخيار الأول" class="form-control">
                                    </div>
                                    <div class="form-group">
                                        <label>الخيار الثاني *</label>
                                        <input type="text" name="option_2" placeholder="الخيار الثاني" class="form-control">
                                    </div>
                                    <div class="form-group">
                                        <label>الخيار الثالث</label>
                                        <input type="text" name="option_3" placeholder="الخيار الثالث (اختياري)" class="form-control">
                                    </div>
                                    <div class="form-group">
                                        <label>الخيار الرابع</label>
                                        <input type="text" name="option_4" placeholder="الخيار الرابع (اختياري)" class="form-control">
                                    </div>
                                    <div class="form-group">
                                        <label>الإجابة الصحيحة</label>
                                        <select name="correct_answer" class="form-control">
                                            <option value="">اختر الإجابة الصحيحة</option>
                                            <option value="1">الخيار الأول</option>
                                            <option value="2">الخيار الثاني</option>
                                            <option value="3">الخيار الثالث</option>
                                            <option value="4">الخيار الرابع</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- قسم النص المفتوح -->
                                <div id="openTextSection" class="question-type-section" style="display: none;">
                                    <h5>الإجابة النموذجية:</h5>
                                    <div class="form-group">
                                        <label>الإجابة النموذجية</label>
                                        <textarea name="correct_text" rows="4" placeholder="اكتب الإجابة النموذجية..." class="form-control"></textarea>
                                    </div>
                                </div>

                                <div class="form-actions">
                                    <button type="submit" name="add_question" class="btn btn-success">
                                        <i class="fas fa-save"></i> حفظ السؤال
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- قائمة الأسئلة -->
                    <div class="admin-card">
                        <div class="card-header">
                            <h2><i class="fas fa-list"></i> قائمة الأسئلة (<?php echo count($questions); ?>)</h2>
                        </div>
                        <div class="card-body">
                            <?php if (empty($questions)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-question-circle"></i>
                                    <p>لا توجد أسئلة بعد</p>
                                    <small>قم بإضافة أول سؤال</small>
                                </div>
                            <?php else: ?>
                                <div class="questions-list">
                                    <?php foreach ($questions as $index => $question): ?>
                                        <div class="question-item">
                                            <div class="question-header-item">
                                                <div class="question-number-badge"><?php echo $index + 1; ?></div>
                                                <div class="question-content">


                                                    <div class="question-type-indicator <?php echo $question['question_type']; ?>">

                                                                                                    <span class="question-id-badge"><?php echo 'Q#' . str_pad($question['id'], 5, "0", STR_PAD_LEFT); ?></span>

                                                        <?php 
                                                        switch($question['question_type']) {
                                                            case 'multiple_choice':
                                                                echo '<i class="fas fa-list"></i> اختيار متعدد';
                                                                break;
                                                            case 'true_false':
                                                                echo '<i class="fas fa-check-double"></i> صح/خطأ';
                                                                break;
                                                            case 'open_text':
                                                                echo '<i class="fas fa-edit"></i> نص مفتوح';
                                                                break;
                                                        }
                                                        ?>
                                                    </div>
                                                    <div class="question-text">
                                                        <?php echo htmlspecialchars($question['question']); ?>
                                                    </div>
                                                </div>
                                                <div class="question-actions">
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('هل تريد حذف هذا السؤال؟')">
                                                        <input type="hidden" name="question_id" value="<?php echo $question['id']; ?>">
                                                        <button type="submit" name="delete_question" class="btn btn-danger btn-sm">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                            
                                            <div class="options-preview">
                                                <?php if ($question['question_type'] === 'multiple_choice'): ?>
                                                    <?php for ($i = 1; $i <= 4; $i++): ?>
                                                        <?php if (!empty($question["option_$i"])): ?>
                                                            <div class="option-preview <?php echo ($i === $question['correct_answer']) ? 'correct-option' : ''; ?>">
                                                                <span class="option-letter"><?php echo chr(64 + $i); ?></span>
                                                                <span class="option-text"><?php echo htmlspecialchars($question["option_$i"]); ?></span>
                                                                <?php if ($i === $question['correct_answer']): ?>
                                                                    <i class="fas fa-check-circle correct-indicator"></i>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    <?php endfor; ?>
                                                <?php elseif ($question['question_type'] === 'true_false'): ?>
                                                    <div class="tf-preview">
                                                        <div class="tf-option-preview <?php echo ($question['correct_answer'] == 1) ? 'correct' : ''; ?>">
                                                            <i class="fas fa-check"></i> صح
                                                            <?php if ($question['correct_answer'] == 1): ?>
                                                                <i class="fas fa-check-circle correct-indicator"></i>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="tf-option-preview <?php echo ($question['correct_answer'] == 2) ? 'correct' : ''; ?>">
                                                            <i class="fas fa-times"></i> خطأ
                                                            <?php if ($question['correct_answer'] == 2): ?>
                                                                <i class="fas fa-check-circle correct-indicator"></i>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                <?php elseif ($question['question_type'] === 'open_text'): ?>
                                                    <div class="text-answer-preview">
                                                        <strong>الإجابة النموذجية:</strong>
                                                        <p><?php echo nl2br(htmlspecialchars($question['correct_text'])); ?></p>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <footer class="admin-footer">
            <div class="container">
                <p>&copy; 2025 نظام إدارة الاختبارات</p>
            </div>
        </footer>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const questionTypeSelect = document.getElementById('question_type');
            const sections = {
                'true_false': document.getElementById('trueFalseSection'),
                'multiple_choice': document.getElementById('multipleChoiceSection'),
                'open_text': document.getElementById('openTextSection')
            };
            
            // تغيير نوع السؤال
            questionTypeSelect.addEventListener('change', function() {
                const selectedType = this.value;
                
                // إخفاء جميع الأقسام
                Object.values(sections).forEach(section => {
                    section.style.display = 'none';
                });
                
                // إظهار القسم المحدد
                if (sections[selectedType]) {
                    sections[selectedType].style.display = 'block';
                }
            });
            
            // تفعيل القسم الافتراضي
            questionTypeSelect.dispatchEvent(new Event('change'));
            
            // تحديد خيارات صح/خطأ
            const tfOptions = document.querySelectorAll('.tf-option-simple');
            tfOptions.forEach(option => {
                option.addEventListener('click', function() {
                    tfOptions.forEach(opt => opt.classList.remove('selected'));
                    this.classList.add('selected');
                    
                    const radio = this.querySelector('input[type="radio"]');
                    if (radio) {
                        radio.checked = true;
                    }
                });
            });
            
            // إخفاء رسائل التشخيص بعد 10 ثواني
            setTimeout(() => {
                const debugInfo = document.querySelector('.debug-info');
                if (debugInfo) {
                    debugInfo.style.opacity = '0';
                    debugInfo.style.transition = 'opacity 0.5s';
                }
            }, 10000);
        });
    </script>
</body>
</html>