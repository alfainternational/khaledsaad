    </main>

    <!-- Footer - Clean & Minimal -->
    <footer class="site-footer">
        <div class="footer-main">
            <div class="container">
                <div class="footer-simple">
                    <div class="footer-brand">
                        <span class="logo-text">خالد سعد</span>
                        <p>خبير التسويق والتحول الرقمي. أساعد رواد الأعمال في بناء استراتيجيات فعّالة.</p>
                        <div class="social-links">
                            <a href="#" target="_blank" rel="noopener" aria-label="تويتر"><i class="fab fa-x-twitter"></i></a>
                            <a href="#" target="_blank" rel="noopener" aria-label="لينكدإن"><i class="fab fa-linkedin-in"></i></a>
                            <a href="#" target="_blank" rel="noopener" aria-label="يوتيوب"><i class="fab fa-youtube"></i></a>
                        </div>
                    </div>

                    <div class="footer-links-group">
                        <div class="footer-col">
                            <h4>روابط</h4>
                            <ul class="footer-links">
                                <li><a href="<?= url('') ?>">الرئيسية</a></li>
                                <li><a href="<?= url('pages/about.php') ?>">من أنا</a></li>
                                <li><a href="<?= url('pages/services.php') ?>">الخدمات</a></li>
                                <li><a href="<?= url('pages/blog.php') ?>">المدونة</a></li>
                            </ul>
                        </div>
                        <div class="footer-col">
                            <h4>الخدمات</h4>
                            <ul class="footer-links">
                                <li><a href="<?= url('pages/services.php#consulting') ?>">الاستشارات التسويقية</a></li>
                                <li><a href="<?= url('pages/services.php#digital') ?>">التحول الرقمي</a></li>
                                <li><a href="<?= url('pages/services.php#branding') ?>">بناء الهوية</a></li>
                            </ul>
                        </div>
                        <div class="footer-col">
                            <h4>تواصل</h4>
                            <ul class="footer-links">
                                <li><a href="mailto:<?= SITE_EMAIL ?>"><?= e(SITE_EMAIL) ?></a></li>
                                <li><a href="<?= url('pages/contact.php') ?>">احجز استشارة</a></li>
                                <li><a href="<?= url('pages/diagnostic.php') ?>">أداة التشخيص</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="footer-bottom">
            <div class="container">
                <p>&copy; <?= date('Y') ?> خالد سعد. جميع الحقوق محفوظة.</p>
            </div>
        </div>
    </footer>

    <!-- Scroll to Top -->
    <button type="button" class="scroll-to-top" id="scrollToTop" aria-label="العودة للأعلى">
        <i class="fas fa-chevron-up"></i>
    </button>

    <!-- Chatbot Widget -->
    <div class="chatbot-widget" id="chatbotWidget">
        <button type="button" class="chatbot-toggle" id="chatbotToggle" aria-label="فتح المحادثة">
            <i class="fas fa-comment-dots"></i>
        </button>
        <div class="chatbot-window" id="chatbotWindow">
            <div class="chatbot-header">
                <div class="chatbot-avatar">خ</div>
                <div class="chatbot-info">
                    <h5>مساعد خالد</h5>
                    <span class="chatbot-status"><i class="fas fa-circle"></i> متصل</span>
                </div>
                <button type="button" class="chatbot-close" aria-label="إغلاق"><i class="fas fa-times"></i></button>
            </div>
            <div class="chatbot-messages" id="chatbotMessages">
                <div class="chat-message bot">
                    <div class="message-content">
                        <p>مرحباً! 👋</p>
                        <p>كيف يمكنني مساعدتك اليوم؟</p>
                    </div>
                </div>
                <div class="quick-replies">
                    <button type="button" data-message="أريد معرفة المزيد عن خدماتك">الخدمات</button>
                    <button type="button" data-message="أريد حجز استشارة">حجز استشارة</button>
                    <button type="button" data-message="كيف أتواصل معك؟">تواصل</button>
                </div>
            </div>
            <form class="chatbot-form" id="chatbotForm">
                <input type="text" name="message" placeholder="اكتب رسالتك..." autocomplete="off">
                <button type="submit" aria-label="إرسال"><i class="fas fa-paper-plane"></i></button>
            </form>
        </div>
    </div>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="<?= asset('js/main.js') ?>"></script>
    <script src="<?= asset('js/animations.js') ?>"></script>
    <script>AOS.init({ duration: 600, once: true, offset: 50 });</script>
    <?php if (isset($additionalScripts)) echo $additionalScripts; ?>
</body>
</html>
