    </main>

    <!-- Footer -->
    <footer class="site-footer">
        <!-- Newsletter Section -->
        <div class="newsletter-section">
            <div class="container">
                <div class="newsletter-content" data-aos="fade-up">
                    <div class="newsletter-text">
                        <h3>اشترك في نشرتنا الإخبارية</h3>
                        <p>احصل على أحدث المقالات والنصائح حول التسويق والتحول الرقمي</p>
                    </div>
                    <form class="newsletter-form" id="newsletterForm" action="<?= url('api/newsletter.php') ?>" method="POST">
                        <?= Security::csrfField() ?>
                        <div class="form-group">
                            <input type="email" name="email" placeholder="بريدك الإلكتروني" required aria-label="البريد الإلكتروني">
                            <button type="submit" class="btn btn-primary">
                                <span class="btn-text">اشترك</span>
                                <span class="btn-loading"><i class="fas fa-spinner fa-spin"></i></span>
                            </button>
                        </div>
                        <?= honeypotField() ?>
                    </form>
                </div>
            </div>
        </div>

        <!-- Main Footer -->
        <div class="footer-main">
            <div class="container">
                <div class="footer-grid">
                    <!-- About Column -->
                    <div class="footer-col" data-aos="fade-up" data-aos-delay="100">
                        <div class="footer-logo">
                            <span class="logo-text">خالد سعد</span>
                            <span class="logo-tagline">للاستشارات</span>
                        </div>
                        <p class="footer-about"><?= SITE_TAGLINE ?>. نساعد الشركات في تحقيق نمو مستدام من خلال استراتيجيات تسويقية مبتكرة وحلول رقمية متكاملة.</p>
                        <div class="social-links">
                            <a href="<?= getSetting('social_twitter', '#') ?>" target="_blank" rel="noopener" aria-label="تويتر">
                                <i class="fab fa-twitter"></i>
                            </a>
                            <a href="<?= getSetting('social_linkedin', '#') ?>" target="_blank" rel="noopener" aria-label="لينكدإن">
                                <i class="fab fa-linkedin-in"></i>
                            </a>
                            <a href="<?= getSetting('social_instagram', '#') ?>" target="_blank" rel="noopener" aria-label="انستغرام">
                                <i class="fab fa-instagram"></i>
                            </a>
                            <a href="https://wa.me/966500000000" target="_blank" rel="noopener" aria-label="واتساب">
                                <i class="fab fa-whatsapp"></i>
                            </a>
                        </div>
                    </div>

                    <!-- Quick Links -->
                    <div class="footer-col" data-aos="fade-up" data-aos-delay="200">
                        <h4>روابط سريعة</h4>
                        <ul class="footer-links">
                            <li><a href="<?= url('') ?>">الرئيسية</a></li>
                            <li><a href="<?= url('pages/services.php') ?>">الخدمات</a></li>
                            <li><a href="<?= url('pages/success-stories.php') ?>">قصص النجاح</a></li>
                            <li><a href="<?= url('pages/blog.php') ?>">المدونة</a></li>
                            <li><a href="<?= url('pages/pricing.php') ?>">الأسعار</a></li>
                            <li><a href="<?= url('pages/about.php') ?>">من نحن</a></li>
                        </ul>
                    </div>

                    <!-- Services -->
                    <div class="footer-col" data-aos="fade-up" data-aos-delay="300">
                        <h4>خدماتنا</h4>
                        <ul class="footer-links">
                            <li><a href="<?= url('pages/services.php#consulting') ?>">الاستشارات التسويقية</a></li>
                            <li><a href="<?= url('pages/services.php#digital') ?>">التحول الرقمي</a></li>
                            <li><a href="<?= url('pages/services.php#branding') ?>">بناء الهوية التجارية</a></li>
                            <li><a href="<?= url('pages/services.php#training') ?>">التدريب والتطوير</a></li>
                            <li><a href="<?= url('pages/diagnostic.php') ?>">أداة التشخيص المجانية</a></li>
                        </ul>
                    </div>

                    <!-- Contact Info -->
                    <div class="footer-col" data-aos="fade-up" data-aos-delay="400">
                        <h4>تواصل معنا</h4>
                        <ul class="contact-info">
                            <li>
                                <i class="fas fa-map-marker-alt"></i>
                                <span><?= e(SITE_ADDRESS) ?></span>
                            </li>
                            <li>
                                <i class="fas fa-phone-alt"></i>
                                <a href="tel:<?= str_replace(' ', '', SITE_PHONE) ?>"><?= e(SITE_PHONE) ?></a>
                            </li>
                            <li>
                                <i class="fas fa-envelope"></i>
                                <a href="mailto:<?= SITE_EMAIL ?>"><?= e(SITE_EMAIL) ?></a>
                            </li>
                            <li>
                                <i class="fas fa-clock"></i>
                                <span>الأحد - الخميس: 9 ص - 6 م</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer Bottom -->
        <div class="footer-bottom">
            <div class="container">
                <div class="footer-bottom-content">
                    <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?>. جميع الحقوق محفوظة.</p>
                    <ul class="footer-legal">
                        <li><a href="<?= url('pages/privacy.php') ?>">سياسة الخصوصية</a></li>
                        <li><a href="<?= url('pages/terms.php') ?>">الشروط والأحكام</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </footer>

    <!-- Scroll to Top Button -->
    <button type="button" class="scroll-to-top" id="scrollToTop" aria-label="العودة للأعلى">
        <i class="fas fa-chevron-up"></i>
    </button>

    <!-- Chatbot Widget -->
    <div class="chatbot-widget" id="chatbotWidget">
        <button type="button" class="chatbot-toggle" id="chatbotToggle" aria-label="فتح المحادثة">
            <i class="fas fa-comments"></i>
            <span class="chatbot-badge">1</span>
        </button>
        <div class="chatbot-window" id="chatbotWindow">
            <div class="chatbot-header">
                <div class="chatbot-avatar">
                    <i class="fas fa-robot"></i>
                </div>
                <div class="chatbot-info">
                    <h5>مساعد خالد سعد</h5>
                    <span class="chatbot-status"><i class="fas fa-circle"></i> متصل</span>
                </div>
                <button type="button" class="chatbot-close" aria-label="إغلاق المحادثة">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="chatbot-messages" id="chatbotMessages">
                <div class="chat-message bot">
                    <div class="message-content">
                        <p>مرحباً! 👋</p>
                        <p>أنا مساعدك الرقمي. كيف يمكنني مساعدتك اليوم؟</p>
                    </div>
                </div>
                <div class="quick-replies">
                    <button type="button" data-message="أريد معرفة المزيد عن الخدمات">الخدمات</button>
                    <button type="button" data-message="ما هي الأسعار؟">الأسعار</button>
                    <button type="button" data-message="أريد حجز استشارة">حجز استشارة</button>
                    <button type="button" data-message="تواصل مع فريق الدعم">تواصل معنا</button>
                </div>
            </div>
            <form class="chatbot-form" id="chatbotForm">
                <input type="text" name="message" placeholder="اكتب رسالتك..." autocomplete="off">
                <button type="submit" aria-label="إرسال">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </form>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="<?= asset('js/main.js') ?>"></script>
    <script src="<?= asset('js/animations.js') ?>"></script>

    <!-- Initialize AOS -->
    <script>
        AOS.init({
            duration: 800,
            easing: 'ease-out-cubic',
            once: true,
            offset: 50
        });
    </script>

    <?php if (isset($additionalScripts)) echo $additionalScripts; ?>
</body>
</html>
