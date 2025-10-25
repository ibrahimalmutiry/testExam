<?php
// ملف تشخيص النموذج - debug_form.php
require_once 'functions.php';

echo "<h2>🔍 تشخيص النموذج</h2>";

if ($_POST) {
    echo "<div style='background:#fff3cd;padding:15px;border-radius:5px;margin:10px 0;'>";
    echo "<h3>📤 البيانات المستلمة من النموذج:</h3>";
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";
    
    echo "<h3>🔍 تحليل البيانات:</h3>";
    
    if (isset($_POST['add_question'])) {
        $question = trim($_POST['question']);
        $questionType = $_POST['question_type'];
        
        echo "<p><strong>السؤال:</strong> '" . htmlspecialchars($question) . "' (طول: " . strlen($question) . ")</p>";
        echo "<p><strong>نوع السؤال:</strong> '" . $questionType . "'</p>";
        
        // تحليل حسب النوع
        if ($questionType === 'multiple_choice') {
            echo "<h4>📋 بيانات الاختيار المتعدد:</h4>";
            $option1 = trim($_POST['option_1'] ?? '');
            $option2 = trim($_POST['option_2'] ?? '');
            $option3 = trim($_POST['option_3'] ?? '');
            $option4 = trim($_POST['option_4'] ?? '');
            $correctAnswer = (int)($_POST['correct_answer'] ?? 0);
            
            echo "<p>الخيار 1: '" . htmlspecialchars($option1) . "' (فارغ: " . (empty($option1) ? 'نعم' : 'لا') . ")</p>";
            echo "<p>الخيار 2: '" . htmlspecialchars($option2) . "' (فارغ: " . (empty($option2) ? 'نعم' : 'لا') . ")</p>";
            echo "<p>الخيار 3: '" . htmlspecialchars($option3) . "'</p>";
            echo "<p>الخيار 4: '" . htmlspecialchars($option4) . "'</p>";
            echo "<p>الإجابة الصحيحة: " . $correctAnswer . "</p>";
            
            // تحقق من الشروط
            if (empty($question)) {
                echo "<p style='color:red'>❌ السؤال فارغ</p>";
            }
            if (empty($option1)) {
                echo "<p style='color:red'>❌ الخيار الأول فارغ</p>";
            }
            if (empty($option2)) {
                echo "<p style='color:red'>❌ الخيار الثاني فارغ</p>";
            }
            if ($correctAnswer < 1 || $correctAnswer > 4) {
                echo "<p style='color:red'>❌ رقم الإجابة الصحيحة غير صالح</p>";
            }
            
        } elseif ($questionType === 'true_false') {
            echo "<h4>✅ بيانات صح/خطأ:</h4>";
            
            if (isset($_POST['true_false_answer'])) {
                $correctAnswer = (int)$_POST['true_false_answer'];
                echo "<p>الإجابة الصحيحة: " . ($correctAnswer == 1 ? 'صح' : 'خطأ') . "</p>";
                
                // تحقق من الشروط
                if (empty($question)) {
                    echo "<p style='color:red'>❌ السؤال فارغ</p>";
                } elseif (!in_array($correctAnswer, [1, 2])) {
                    echo "<p style='color:red'>❌ قيمة الإجابة غير صحيحة</p>";
                } else {
                    echo "<p style='color:green'>✅ البيانات صحيحة - سأحاول الإضافة</p>";
                    
                    try {
                        $result = addQuestion($question, $questionType, $correctAnswer, '', '', '', '', '');
                        if ($result) {
                            echo "<p style='color:green'>🎉 تم إضافة السؤال بنجاح!</p>";
                        } else {
                            echo "<p style='color:red'>❌ فشل في إضافة السؤال (دالة addQuestion أرجعت false)</p>";
                        }
                    } catch (Exception $e) {
                        echo "<p style='color:red'>❌ خطأ في الإضافة: " . $e->getMessage() . "</p>";
                    }
                }
            } else {
                echo "<p style='color:red'>❌ لم يتم اختيار إجابة صح/خطأ</p>";
            }
            
        } elseif ($questionType === 'open_text') {
            echo "<h4>📝 بيانات النص المفتوح:</h4>";
            $correctText = trim($_POST['correct_text'] ?? '');
            echo "<p>الإجابة النموذجية: '" . htmlspecialchars($correctText) . "' (طول: " . strlen($correctText) . ")</p>";
            
            // تحقق من الشروط
            if (empty($question)) {
                echo "<p style='color:red'>❌ السؤال فارغ</p>";
            }
            if (empty($correctText)) {
                echo "<p style='color:red'>❌ الإجابة النموذجية فارغة</p>";
            }
        }
        
    } else {
        echo "<p style='color:orange'>⚠️ لم يتم إرسال add_question</p>";
    }
    
    echo "</div>";
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تشخيص النموذج</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        .form-container { background: #f9f9f9; padding: 20px; border-radius: 10px; margin: 20px 0; }
        .form-group { margin-bottom: 15px; }
        label { font-weight: bold; margin-bottom: 5px; display: block; }
        input, textarea, select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        button { background: #007bff; color: white; padding: 12px 20px; border: none; border-radius: 5px; cursor: pointer; }
        .section { display: none; margin-top: 15px; padding: 15px; background: #e9f4ff; border-radius: 5px; }
        .section.active { display: block; }
    </style>
</head>
<body>

<h3>📝 نموذج اختبار إضافة الأسئلة:</h3>

<form method="POST" class="form-container">
    <div class="form-group">
        <label for="question">نص السؤال:</label>
        <textarea id="question" name="question" rows="3" required placeholder="اكتب السؤال هنا..."></textarea>
    </div>
    
    <div class="form-group">
        <label for="question_type">نوع السؤال:</label>
        <select id="question_type" name="question_type" required>
            <option value="multiple_choice">اختيار متعدد</option>
            <option value="true_false">صح/خطأ</option>
            <option value="open_text">نص مفتوح</option>
        </select>
    </div>
    
    <!-- الاختيار المتعدد -->
    <div id="multiple_choice_section" class="section">
        <h4>خيارات الاختيار المتعدد:</h4>
        <div class="form-group">
            <label>الخيار الأول:</label>
            <input type="text" name="option_1" placeholder="الخيار الأول">
        </div>
        <div class="form-group">
            <label>الخيار الثاني:</label>
            <input type="text" name="option_2" placeholder="الخيار الثاني">
        </div>
        <div class="form-group">
            <label>الخيار الثالث:</label>
            <input type="text" name="option_3" placeholder="الخيار الثالث (اختياري)">
        </div>
        <div class="form-group">
            <label>الخيار الرابع:</label>
            <input type="text" name="option_4" placeholder="الخيار الرابع (اختياري)">
        </div>
        <div class="form-group">
            <label>الإجابة الصحيحة:</label>
            <select name="correct_answer">
                <option value="">اختر الإجابة الصحيحة</option>
                <option value="1">الخيار الأول</option>
                <option value="2">الخيار الثاني</option>
                <option value="3">الخيار الثالث</option>
                <option value="4">الخيار الرابع</option>
            </select>
        </div>
    </div>
    
    <!-- صح/خطأ -->
    <div id="true_false_section" class="section">
        <h4>إعداد سؤال صح/خطأ:</h4>
        <div class="form-group">
            <label>الإجابة الصحيحة:</label>
            <div style="margin-top: 10px;">
                <label style="display: inline; margin-left: 20px;">
                    <input type="radio" name="true_false_answer" value="1" style="width: auto; margin-left: 5px;"> صح ✅
                </label>
                <label style="display: inline;">
                    <input type="radio" name="true_false_answer" value="2" style="width: auto; margin-left: 5px;"> خطأ ❌
                </label>
            </div>
        </div>
    </div>
    
    <!-- النص المفتوح -->
    <div id="open_text_section" class="section">
        <h4>الإجابة النموذجية:</h4>
        <div class="form-group">
            <label>الإجابة النموذجية:</label>
            <textarea name="correct_text" rows="4" placeholder="اكتب الإجابة النموذجية..."></textarea>
        </div>
    </div>
    
    <button type="submit" name="add_question">💾 إضافة السؤال</button>
</form>

<script>
document.getElementById('question_type').addEventListener('change', function() {
    // إخفاء جميع الأقسام
    document.querySelectorAll('.section').forEach(section => {
        section.classList.remove('active');
    });
    
    // إظهار القسم المحدد
    const selectedType = this.value;
    const sectionMap = {
        'multiple_choice': 'multiple_choice_section',
        'true_false': 'true_false_section',
        'open_text': 'open_text_section'
    };
    
    if (sectionMap[selectedType]) {
        document.getElementById(sectionMap[selectedType]).classList.add('active');
    }
});

// تفعيل القسم الافتراضي
document.getElementById('question_type').dispatchEvent(new Event('change'));
</script>

<hr>
<p><a href="admin_dashboard.php">العودة للوحة الإدارة</a></p>

</body>
</html>