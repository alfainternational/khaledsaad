# Public Marketing Homepage Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** بناء صفحة خالد سعد العامة الطويلة بهوية أصلية ومحتوى مهني موثق، تقود الزائر بوضوح إلى بدء تشخيص مشروعه.

**Architecture:** تعرض Laravel Blade محتوى الصفحة من إعدادات علامة مركزية حتى تبقى البيانات متسقة وقابلة لإعادة الاستخدام في API وFlutter. تستخدم الواجهة CSS مبنيًا عبر Vite وJavaScript خفيفًا لقائمة الجوال، مع HTML دلالي وFAQ أصلية بعنصر `details`.

**Tech Stack:** Laravel 13, Blade, Tailwind CSS 4/Vite, vanilla JavaScript, PHPUnit.

**Workspace constraint:** التنفيذ مباشر في `D:\xampp\htdocs\khaledsaad` بناءً على توجيه صاحب المشروع. لا تُستخدم worktrees أو فروع خارجية، ولا يُقرأ أو يُعدل `oldone`. المشروع الحالي ليس مستودع Git، لذلك لا تتضمن المهام خطوات commit.

---

## File map

- `config/brand.php`: مصدر بيانات الهوية، التواصل، الخبرات، الخدمات، الأدوات والمحتوى.
- `resources/views/layouts/public.blade.php`: هيكل HTML العام وبيانات SEO.
- `resources/views/components/brand-logo.blade.php`: شعار SVG الأصلي ونسخة الكلمة.
- `resources/views/components/section-heading.blade.php`: عنوان موحد لأقسام الصفحة.
- `resources/views/home.blade.php`: الأقسام التسويقية الاثنا عشر.
- `resources/css/app.css`: نظام التصميم الكامل والاستجابة والحركة والوصول.
- `resources/js/app.js`: قائمة الجوال، حالة التمرير، وحركة الظهور الاختيارية.
- `routes/web.php`: توجيه الصفحة الرئيسية باسم `home`.
- `public/favicon.svg`: نسخة مربعة من رمز العلامة.
- `tests/Feature/PublicHomePageTest.php`: قبول الصفحة والمحتوى والروابط.
- `tests/Unit/BrandProfileConfigurationTest.php`: سلامة بيانات العلامة المهنية.

### Task 1: Lock the Brand Data Contract

**Files:**
- Create: `config/brand.php`
- Create: `tests/Unit/BrandProfileConfigurationTest.php`

- [x] **Step 1: Write the failing configuration test**

```php
<?php

test('brand profile exposes verified identity and contact data', function () {
    $brand = require base_path('config/brand.php');

    expect($brand['name'])->toBe('خالد سعد')
        ->and($brand['contact']['phone'])->toBe('+966533052074')
        ->and($brand['contact']['linkedin'])->toBe('https://www.linkedin.com/in/khaledaasaad/')
        ->and($brand['contact']['x'])->toBe('https://x.com/KhaledAASaad')
        ->and($brand['experience'])->toHaveCount(7)
        ->and($brand['education'][0]['institution'])->toBe('جامعة النيلين')
        ->and($brand['credentials'])->toContain('إدارة المشاريع الاحترافية PMP');
});
```

- [x] **Step 2: Run the test and verify the red state**

Run:

```powershell
php artisan test tests/Unit/BrandProfileConfigurationTest.php
```

Expected: FAIL because `config/brand.php` does not exist.

- [x] **Step 3: Add the verified configuration**

Create one returned array containing:

```php
return [
    'name' => 'خالد سعد',
    'tagline' => 'نحوّل التسويق من نشاط مشتت إلى نظام نمو قابل للقياس.',
    'headline' => 'خبير تسويق رقمي واستراتيجيات نمو مدعومة بالبيانات والذكاء الاصطناعي',
    'location' => 'عرعر، منطقة الحدود الشمالية، المملكة العربية السعودية',
    'contact' => [
        'phone' => '+966533052074',
        'phone_display' => '+966 53 305 2074',
        'whatsapp' => 'https://wa.me/966533052074',
        'linkedin' => 'https://www.linkedin.com/in/khaledaasaad/',
        'x' => 'https://x.com/KhaledAASaad',
    ],
    'experience' => [
        ['role' => 'اختصاصي التسويق', 'company' => 'شركة الشمال التعليمية', 'period' => 'نوفمبر 2024 — حتى الآن'],
        ['role' => 'مدير التسويق', 'company' => 'شركة ألفا العالمية للأنشطة المتعددة المحدودة', 'period' => 'يناير 2020 — فبراير 2025'],
        ['role' => 'مدير التسويق', 'company' => 'Hoopoespark', 'period' => 'أكتوبر 2022 — نوفمبر 2024'],
        ['role' => 'مسؤول التسويق الرقمي', 'company' => 'Awrag Taiba', 'period' => 'مارس 2016 — أغسطس 2022'],
        ['role' => 'مشرف التسويق', 'company' => 'Design Lasteer Trading', 'period' => 'فبراير 2013 — أبريل 2015'],
        ['role' => 'متدرب التسويق الرقمي', 'company' => 'KN Technology', 'period' => 'فبراير 2012 — مارس 2014'],
        ['role' => 'مساعد وسائل التواصل الاجتماعي', 'company' => 'WAELCO Technology & Investment Ltd', 'period' => 'أكتوبر 2011 — فبراير 2013'],
    ],
    'education' => [
        ['degree' => 'بكالوريوس تقنية المعلومات', 'institution' => 'جامعة النيلين', 'period' => 'أبريل 2006 — مايو 2010'],
    ],
    'credentials' => [
        'إدارة المشاريع الاحترافية PMP',
        'Generative AI Essentials: Using LLMs to Work with Data',
    ],
];
```

Extend the same array with the six verified services, selected skills, eleven diagnostic tools, four methodology steps, and six FAQ entries specified in the design document.

- [x] **Step 4: Run the focused test**

Run:

```powershell
php artisan test tests/Unit/BrandProfileConfigurationTest.php
```

Expected: PASS.

### Task 2: Establish the Public Layout and Original Logo

**Files:**
- Create: `resources/views/layouts/public.blade.php`
- Create: `resources/views/components/brand-logo.blade.php`
- Create: `resources/views/components/section-heading.blade.php`
- Create: `public/favicon.svg`

- [x] **Step 1: Add the layout contract to the feature test**

```php
test('home page uses an accessible rtl public layout', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('dir="rtl"', false)
        ->assertSee('تجاوز إلى المحتوى')
        ->assertSee('application/ld+json', false)
        ->assertSee('خالد سعد | تشخيص وتسويق ونمو رقمي');
});
```

- [x] **Step 2: Run the test and verify it fails**

Run:

```powershell
php artisan test tests/Feature/PublicHomePageTest.php
```

Expected: FAIL because the named route and public layout are not implemented.

- [x] **Step 3: Build the layout**

The layout must include:

```blade
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'خالد سعد | تشخيص وتسويق ونمو رقمي' }}</title>
    <meta name="description" content="{{ $description ?? config('brand.tagline') }}">
    <meta name="theme-color" content="#071F5B">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="canonical" href="{{ url()->current() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body>
    <a class="skip-link" href="#main-content">تجاوز إلى المحتوى</a>
    {{ $slot }}
</body>
</html>
```

Add Open Graph tags and JSON-LD of type `Person` with the verified name, location, LinkedIn, X, telephone, occupation and site URL.

- [x] **Step 4: Draw the vector mark**

Create an original SVG combining:

- a cyan-to-blue rising folded stroke;
- a navy supporting fold;
- an orange upward arrow;
- the Arabic wordmark «خالد سعد».

The component accepts `compact` and `light` attributes, includes a `<title>شعار خالد سعد</title>`, and never embeds raster content. Copy the compact mark paths into `public/favicon.svg`.

- [x] **Step 5: Add the section-heading component**

```blade
@props(['eyebrow', 'title', 'description' => null, 'align' => 'center'])

<header @class(['section-heading', 'section-heading--start' => $align === 'start'])>
    <p class="eyebrow">{{ $eyebrow }}</p>
    <h2>{{ $title }}</h2>
    @if ($description)
        <p>{{ $description }}</p>
    @endif
</header>
```

### Task 3: Build the Long Marketing Homepage

**Files:**
- Create: `resources/views/home.blade.php`
- Modify: `routes/web.php`
- Create: `tests/Feature/PublicHomePageTest.php`

- [x] **Step 1: Write the complete page acceptance test**

```php
test('home page presents the full marketing journey and verified profile', function () {
    $response = $this->get(route('home'));

    $response->assertOk()
        ->assertSee('ابدأ تشخيص مشروعك')
        ->assertSee('لماذا تبدأ بالتشخيص؟')
        ->assertSee('كيف أساعدك؟')
        ->assertSee('منظومة أدوات النمو')
        ->assertSee('نموذج من نتيجة التشخيص')
        ->assertSee('عن خالد سعد')
        ->assertSee('خبرة تتجاوز 10 سنوات')
        ->assertSee('شركة الشمال التعليمية')
        ->assertSee('جامعة النيلين')
        ->assertSee('إدارة المشاريع الاحترافية PMP')
        ->assertSee('يلا نفهم تسويق')
        ->assertSee('الأسئلة الشائعة')
        ->assertSee('+966 53 305 2074')
        ->assertSee('https://wa.me/966533052074', false)
        ->assertSee('https://x.com/KhaledAASaad', false)
        ->assertSee('https://www.linkedin.com/in/khaledaasaad/', false);
});
```

- [x] **Step 2: Run the test and verify it fails**

Run:

```powershell
php artisan test tests/Feature/PublicHomePageTest.php
```

Expected: FAIL because the finished page does not exist.

- [x] **Step 3: Name the route and pass the content**

```php
Route::view('/', 'home', [
    'brand' => config('brand'),
])->name('home');
```

- [x] **Step 4: Implement the twelve sections**

Build semantic `<header>`, `<main id="main-content">`, and `<footer>` containing:

1. navigation with the logo, five internal links and the primary CTA;
2. hero with the core promise, two CTAs and a diagnostic-result visual;
3. the four common marketing problems;
4. the three layers of help;
5. the four-step methodology;
6. all eleven tools from `config('brand.tools')`;
7. a sample result with score, three gaps and priorities;
8. the verified professional profile, selected experience, education and credentials;
9. trust principles and methodology;
10. newsletter and three real content themes;
11. six FAQ entries using `<details>`;
12. final CTA and a footer with telephone, WhatsApp, LinkedIn and X.

Every CTA uses `href="#diagnosis"` until the diagnosis route is delivered in the next phase. The page must not display invented client logos, testimonials, performance percentages or follower counts.

- [x] **Step 5: Run the feature test**

Run:

```powershell
php artisan test tests/Feature/PublicHomePageTest.php
```

Expected: PASS.

### Task 4: Implement the Production Design System

**Files:**
- Modify: `resources/css/app.css`

- [x] **Step 1: Define the brand tokens**

```css
@theme {
    --font-sans: 'Tajawal', 'Segoe UI', Tahoma, sans-serif;
    --color-brand-cyan: #09d7e5;
    --color-brand-blue: #2575ff;
    --color-brand-navy: #071f5b;
    --color-brand-orange: #ff9b27;
    --color-brand-red: #ff4b12;
}

:root {
    --surface: #ffffff;
    --surface-soft: #f5f9ff;
    --ink: #071a38;
    --muted: #5d6b82;
    --line: #dfe8f5;
    --radius-sm: .8rem;
    --radius-md: 1.25rem;
    --radius-lg: 2rem;
    --shadow-card: 0 24px 70px rgba(7, 31, 91, .1);
}
```

- [x] **Step 2: Add the full responsive styling**

Implement:

- max-width content container;
- sticky translucent navigation;
- 2-column hero at desktop and one column below 900px;
- editorial section spacing and alternating soft backgrounds;
- result cards, tool grid, timeline, service cards and CTA panel;
- visible focus rings and a skip link;
- mobile menu styles below 760px;
- `details` FAQ open/closed states;
- breakpoints at 1100px, 900px, 760px and 520px;
- no horizontal overflow at 320px.

- [x] **Step 3: Add restrained motion**

Use only opacity and transform for `.reveal` elements. Disable all nonessential motion under:

```css
@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after {
        scroll-behavior: auto !important;
        animation-duration: .01ms !important;
        transition-duration: .01ms !important;
    }
}
```

- [x] **Step 4: Build assets**

Run:

```powershell
npm run build
```

Expected: Vite completes successfully and writes a new manifest under `public/build`.

### Task 5: Add Minimal Interaction Without a Framework

**Files:**
- Modify: `resources/js/app.js`

- [x] **Step 1: Implement the mobile navigation**

```js
const toggle = document.querySelector('[data-menu-toggle]');
const menu = document.querySelector('[data-mobile-menu]');

if (toggle && menu) {
    const closeMenu = () => {
        toggle.setAttribute('aria-expanded', 'false');
        menu.hidden = true;
    };

    toggle.addEventListener('click', () => {
        const isOpen = toggle.getAttribute('aria-expanded') === 'true';
        toggle.setAttribute('aria-expanded', String(!isOpen));
        menu.hidden = isOpen;
    });

    menu.querySelectorAll('a').forEach((link) => link.addEventListener('click', closeMenu));
}
```

- [x] **Step 2: Add optional reveal observation**

Only initialize `IntersectionObserver` when motion is allowed. Immediately mark content visible when it is unavailable, so JavaScript failure never hides core content.

- [x] **Step 3: Build again**

Run:

```powershell
npm run build
```

Expected: PASS with CSS and JavaScript bundles in `public/build/assets`.

### Task 6: Verify Content, Accessibility and Regression Safety

**Files:**
- Modify: `tests/Feature/PublicHomePageTest.php`

- [x] **Step 1: Add regression assertions**

```php
test('home page excludes unverified or private profile fields', function () {
    $this->get(route('home'))
        ->assertDontSee('تاريخ الميلاد')
        ->assertDontSee('11111')
        ->assertDontSee('عميل موثوق')
        ->assertDontSee('نسبة نجاح');
});
```

- [x] **Step 2: Run Laravel tests**

Run:

```powershell
php artisan test
```

Expected: all tests PASS.

- [x] **Step 3: Format PHP**

Run:

```powershell
vendor\bin\pint
```

Expected: PASS.

- [x] **Step 4: Verify the XAMPP page**

Open:

```text
http://localhost/khaledsaad/public/
```

Verify:

- HTTP 200;
- no PHP or console errors;
- all internal navigation targets exist;
- external links point to the verified destinations;
- desktop and mobile layouts preserve RTL order;
- the CTA reaches `#diagnosis`.

- [x] **Step 5: Verify database configuration remains untouched**

Run:

```powershell
Select-String -Path '.env','.env.example' -Pattern '^DB_PORT='
```

Expected: both configured values remain `DB_PORT=3306`.

### Task 7: Delivery Record

**Files:**
- Create: `docs/reports/2026-07-23-public-homepage-verification.md`

- [x] **Step 1: Record evidence**

Write the exact:

- Laravel test count and assertions;
- Vite build status;
- Pint status;
- verified local URL;
- viewport checks completed;
- files created and modified;
- confirmation that `oldone` was not accessed;
- confirmation that MySQL remains on port `3306`.

- [x] **Step 2: Mark this plan complete**

Change every completed checkbox in this plan from `[ ]` to `[x]` only after its corresponding evidence exists.
