// ملف JavaScript لإدارة نظام تتبع المحاولات - attempt-tracker.js

class AttemptTracker {
    constructor(options = {}) {
        this.timeRemaining = options.timeRemaining || 0;
        this.totalTime = options.totalTime || 0;
        this.countdownInterval = null;
        this.onTimeUp = options.onTimeUp || this.defaultTimeUpHandler;
        this.onTick = options.onTick || this.defaultTickHandler;
        
        this.elements = {
            timeDisplay: document.getElementById('timeRemaining'),
            buttonCountdown: document.getElementById('buttonCountdown'),
            retryButton: document.getElementById('retryButton'),
            progressBar: null
        };
        
        this.init();
    }
    
    init() {
        this.setupProgressBar();
        this.startCountdown();
        this.bindEvents();
    }
    
    setupProgressBar() {
        if (this.elements.retryButton) {
            // إضافة شريط تقدم للزر
            const progressBar = document.createElement('div');
            progressBar.className = 'countdown-progress';
            progressBar.innerHTML = '<div class="countdown-progress-fill"></div>';
            this.elements.retryButton.appendChild(progressBar);
            this.elements.progressBar = progressBar.querySelector('.countdown-progress-fill');
        }
    }
    
    startCountdown() {
        if (this.timeRemaining <= 0) return;
        
        this.countdownInterval = setInterval(() => {
            this.timeRemaining--;
            this.onTick(this.timeRemaining);
            
            if (this.timeRemaining <= 0) {
                this.stopCountdown();
                this.onTimeUp();
            }
        }, 1000);
    }
    
    stopCountdown() {
        if (this.countdownInterval) {
            clearInterval(this.countdownInterval);
            this.countdownInterval = null;
        }
    }
    
    defaultTickHandler(timeLeft) {
        // تحديث عرض الوقت
        if (this.elements.timeDisplay) {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            this.elements.timeDisplay.innerHTML = `
                <i class="fas fa-clock"></i> 
                ${minutes} دقيقة و ${seconds} ثانية
            `;
        }
        
        // تحديث زر العداد
        if (this.elements.buttonCountdown) {
            this.elements.buttonCountdown.textContent = Math.ceil(timeLeft / 60);
        }
        
        // تحديث شريط التقدم
        if (this.elements.progressBar && this.totalTime > 0) {
            const progress = ((this.totalTime - timeLeft) / this.totalTime) * 100;
            this.elements.progressBar.style.width = progress + '%';
        }
        
        // إضافة تأثيرات بصرية عند اقتراب الوقت من النهاية
        if (timeLeft <= 60) {
            this.addUrgencyEffects();
        }
    }
    
    defaultTimeUpHandler() {
        // إعادة تحميل الصفحة أو إعادة التوجيه
        this.showTimeUpMessage();
        setTimeout(() => {
            window.location.reload();
        }, 2000);
    }
    
    showTimeUpMessage() {
        const message = document.createElement('div');
        message.className = 'time-up-message';
        message.innerHTML = `
            <div class="time-up-content">
                <i class="fas fa-check-circle"></i>
                <h3>انتهت فترة الانتظار!</h3>
                <p>يمكنك الآن المحاولة مرة أخرى</p>
            </div>
        `;
        
        document.body.appendChild(message);
        
        // إزالة الرسالة بعد فترة
        setTimeout(() => {
            message.remove();
        }, 5000);
    }
    
    addUrgencyEffects() {
        const elements = [this.elements.timeDisplay, this.elements.retryButton];
        elements.forEach(element => {
            if (element && !element.classList.contains('urgent')) {
                element.classList.add('urgent');
            }
        });
    }
    
    bindEvents() {
        // التعامل مع تغيير التبويب (Tab Visibility)
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                this.syncTime();
            }
        });
        
        // التعامل مع فقدان/استعادة التركيز
        window.addEventListener('focus', () => {
            this.syncTime();
        });
    }
    
    syncTime() {
        // مزامنة الوقت مع الخادم أو التحقق من الوقت المحلي
        // هذا مهم عندما يغادر المستخدم التبويب ثم يعود
        const currentTime = Math.floor(Date.now() / 1000);
        const storedEndTime = localStorage.getItem('quiz_attempt_end_time');
        
        if (storedEndTime) {
            const endTime = parseInt(storedEndTime);
            const newTimeRemaining = Math.max(0, endTime - currentTime);
            
            if (newTimeRemaining !== this.timeRemaining) {
                this.timeRemaining = newTimeRemaining;
                if (this.timeRemaining <= 0) {
                    this.stopCountdown();
                    this.onTimeUp();
                }
            }
        }
    }
    
    // دالة لحفظ وقت انتهاء الانتظار في التخزين المحلي
    static saveEndTime(seconds) {
        const endTime = Math.floor(Date.now() / 1000) + seconds;
        localStorage.setItem('quiz_attempt_end_time', endTime.toString());
    }
    
    // دالة لمسح وقت الانتهاء المحفوظ
    static clearEndTime() {
        localStorage.removeItem('quiz_attempt_end_time');
    }
}

// مدير الإشعارات
class NotificationManager {
    constructor() {
        this.requestPermission();
    }
    
    async requestPermission() {
        if ('Notification' in window && Notification.permission === 'default') {
            await Notification.requestPermission();
        }
    }
    
    showNotification(title, body, options = {}) {
        if ('Notification' in window && Notification.permission === 'granted') {
            const notification = new Notification(title, {
                body,
                icon: '/favicon.ico',
                badge: '/favicon.ico',
                dir: 'rtl',
                lang: 'ar',
                ...options
            });
            
            // إغلاق الإشعار تلقائياً بعد 5 ثوانِ
            setTimeout(() => {
                notification.close();
            }, 5000);
            
            return notification;
        }
        
        // بديل للمتصفحات التي لا تدعم الإشعارات
        this.showInPageNotification(title, body);
    }
    
    showInPageNotification(title, body) {
        const notification = document.createElement('div');
        notification.className = 'in-page-notification';
        notification.innerHTML = `
            <div class="notification-content">
                <h4>${title}</h4>
                <p>${body}</p>
                <button class="notification-close">&times;</button>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // إغلاق عند النقر على زر الإغلاق
        notification.querySelector('.notification-close').addEventListener('click', () => {
            notification.remove();
        });
        
        // إزالة تلقائية بعد 5 ثوانِ
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 5000);
    }
}

// مدير حفظ تقدم الاختبار
class QuizProgressManager {
    constructor() {
        this.sessionKey = 'quiz_progress';
        this.autoSaveInterval = null;
        this.startAutoSave();
    }
    
    saveProgress(data) {
        const progressData = {
            ...data,
            timestamp: Date.now(),
            userAgent: navigator.userAgent
        };
        
        try {
            sessionStorage.setItem(this.sessionKey, JSON.stringify(progressData));
            return true;
        } catch (error) {
            console.warn('فشل في حفظ التقدم:', error);
            return false;
        }
    }
    
    loadProgress() {
        try {
            const data = sessionStorage.getItem(this.sessionKey);
            return data ? JSON.parse(data) : null;
        } catch (error) {
            console.warn('فشل في تحميل التقدم:', error);
            return null;
        }
    }
    
    clearProgress() {
        try {
            sessionStorage.removeItem(this.sessionKey);
            return true;
        } catch (error) {
            console.warn('فشل في مسح التقدم:', error);
            return false;
        }
    }
    
    startAutoSave() {
        // حفظ تلقائي كل 30 ثانية
        this.autoSaveInterval = setInterval(() => {
            this.autoSaveCurrentState();
        }, 30000);
    }
    
    stopAutoSave() {
        if (this.autoSaveInterval) {
            clearInterval(this.autoSaveInterval);
            this.autoSaveInterval = null;
        }
    }
    
    autoSaveCurrentState() {
        // جمع البيانات الحالية من النموذج
        const form = document.getElementById('quizForm');
        if (!form) return;
        
        const formData = new FormData(form);
        const currentData = {};
        
        for (let [key, value] of formData.entries()) {
            currentData[key] = value;
        }
        
        // إضافة معلومات إضافية
        currentData.currentQuestion = this.getCurrentQuestionIndex();
        currentData.timeSpent = this.getTimeSpent();
        
        this.saveProgress(currentData);
    }
    
    getCurrentQuestionIndex() {
        const questionNumber = document.querySelector('.question-number');
        return questionNumber ? parseInt(questionNumber.textContent) - 1 : 0;
    }
    
    getTimeSpent() {
        const startTime = localStorage.getItem('quiz_start_time');
        return startTime ? Date.now() - parseInt(startTime) : 0;
    }
}

// تهيئة النظام عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', function() {
    // تهيئة مدير المحاولات إذا كان هناك عداد تنازلي
    const timeRemainingElement = document.getElementById('timeRemaining');
    if (timeRemainingElement) {
        const timeRemaining = parseInt(timeRemainingElement.dataset.seconds) || 0;
        const totalTime = parseInt(timeRemainingElement.dataset.totalSeconds) || timeRemaining;
        
        new AttemptTracker({
            timeRemaining,
            totalTime,
            onTimeUp: () => {
                // مسح وقت الانتهاء المحفوظ
                AttemptTracker.clearEndTime();
                
                // إشعار المستخدم
                const notificationManager = new NotificationManager();
                notificationManager.showNotification(
                    'انتهت فترة الانتظار',
                    'يمكنك الآن إعادة المحاولة في الاختبار'
                );
                
                // إعادة تحميل الصفحة
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            }
        });
    }
    
    // تهيئة مدير التقدم
    const progressManager = new QuizProgressManager();
    
    // محاولة استرداد التقدم المحفوظ
    const savedProgress = progressManager.loadProgress();
    if (savedProgress && savedProgress.currentQuestion !== undefined) {
        // يمكن إضافة منطق لاستعادة التقدم هنا
        console.log('تم العثور على تقدم محفوظ:', savedProgress);
    }
    
    // حفظ وقت البداية
    if (!localStorage.getItem('quiz_start_time')) {
        localStorage.setItem('quiz_start_time', Date.now().toString());
    }
    
    // تنظيف البيانات عند إنهاء الاختبار
    window.addEventListener('beforeunload', () => {
        const isQuizComplete = document.querySelector('.results-card');
        if (isQuizComplete) {
            progressManager.clearProgress();
            localStorage.removeItem('quiz_start_time');
        }
    });
    
    // إضافة مؤشرات التحميل للأزرار
    const submitButtons = document.querySelectorAll('button[type="submit"]');
    submitButtons.forEach(button => {
        button.addEventListener('click', function() {
            if (!this.disabled) {
                this.classList.add('loading');
                
                // إزالة حالة التحميل بعد فترة مهلة
                setTimeout(() => {
                    this.classList.remove('loading');
                }, 5000);
            }
        });
    });
    
    // تحسين تجربة المستخدم مع لوحة المفاتيح
    document.addEventListener('keydown', function(e) {
        // منع F5 أثناء الاختبار النشط
        if (e.key === 'F5' && document.querySelector('.question-card')) {
            if (!confirm('هل أنت متأكد من إعادة تحميل الصفحة؟ قد تفقد التقدم الحالي.')) {
                e.preventDefault();
            }
        }
        
        // اختصارات لوحة المفاتيح للمحاولات
        if (e.ctrlKey && e.key === 'r') {
            const retryButton = document.querySelector('a[href*="restart"]');
            if (retryButton && !retryButton.closest('.btn').disabled) {
                e.preventDefault();
                retryButton.click();
            }
        }
    });
});

// دالة مساعدة لتنسيق الوقت
function formatTime(seconds) {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = seconds % 60;
    
    if (hours > 0) {
        return `${hours}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
    } else {
        return `${minutes}:${secs.toString().padStart(2, '0')}`;
    }
}

// دالة لإنشاء تأثير الاهتزاز
function vibrateDevice(pattern = [100, 50, 100]) {
    if ('vibrate' in navigator) {
        navigator.vibrate(pattern);
    }
}

// تصدير الفئات للاستخدام في ملفات أخرى
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        AttemptTracker,
        NotificationManager,
        QuizProgressManager
    };
}