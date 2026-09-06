<?php

namespace Tests\Feature;

use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdaptiveInterfaceLayoutTest extends TestCase
{
    #[Test]
    public function content_admin_surfaces_use_the_shared_responsive_filter_and_table_contract(): void
    {
        $css = file_get_contents(resource_path('css/workspace.css'));

        foreach (['.filter-bar', '.filter-bar__field', '.filter-bar__label', '[data-filter-bar]'] as $hook) {
            $this->assertStringContainsString($hook, $css, $hook);
        }

        foreach (['admin/content/index', 'admin/content/subscribers', 'admin/content/media'] as $view) {
            $contents = file_get_contents(resource_path("views/{$view}.blade.php"));

            $this->assertStringContainsString('data-filter-bar', $contents, $view);
            $this->assertStringContainsString('filter-bar__field', $contents, $view);
            $this->assertStringContainsString('filter-bar__label', $contents, $view);
        }

        foreach (['admin/content/index', 'admin/content/subscribers', 'admin/content/categories/index'] as $view) {
            $contents = file_get_contents(resource_path("views/{$view}.blade.php"));

            $this->assertStringContainsString('data-table="entity"', $contents, $view);
            $this->assertStringContainsString('data-label=', $contents, $view);
        }
    }

    #[Test]
    public function relative_times_are_localized_for_the_arabic_interface(): void
    {
        $earlier = Carbon::parse('2026-08-03 12:00:00');
        $later = Carbon::parse('2026-08-03 17:00:00');

        $this->assertSame('ar', Carbon::getLocale());
        $this->assertSame('قبل 5 ساعات', $earlier->diffForHumans($later));
    }

    #[Test]
    public function workspace_css_defines_the_approved_semantic_layout_contract(): void
    {
        $css = file_get_contents(resource_path('css/workspace.css'));

        foreach ([
            '--layout-reading-max: 46rem',
            '--layout-form-max: 56rem',
            '--layout-operational-max: 87.5rem',
            '--layout-page-gap: 2rem',
            '--layout-section-gap: 1.5rem',
            '.layout-grid',
            '.layout-span-12',
            '.layout-span-9',
            '.layout-span-8',
            '.layout-span-6',
            '.layout-span-4',
            '.layout-span-3',
            '.layout-metrics',
            '.layout-main-aside',
            '.layout-report',
            '.layout-form-aside',
            '.layout-page--reading',
            '.layout-page--form',
            '.layout-page--operational',
            // نقاط التوقّف من التوكنز الأربع (INV-10): كانت 64rem و48rem
            // قبل توحيدها، وقيمتاهما 1024px و768px — فالحدّ الأعلى ناقص واحد.
            '@media (max-width: 1023px)',
            '@media (max-width: 767px)',
        ] as $contract) {
            $this->assertStringContainsString($contract, $css, $contract);
        }
    }

    #[Test]
    public function every_page_view_declares_an_approved_layout_family(): void
    {
        $allowed = [
            'dashboard',
            'index',
            'detail',
            'form',
            'wizard',
            'report',
            'board',
            'reading',
            'auth',
            'status',
            'marketing',
        ];
        $views = [
            resource_path('views/home.blade.php'),
            resource_path('views/agency-reports/shared.blade.php'),
        ];
        $nonPageViews = array_map(static fn (string $path): string => str_replace('\\', '/', $path), [
            // استجابة نصية للآلات؛ إضافة غلاف HTML أو data-layout تفسد llms.txt.
            resource_path('views/site/content/llms.blade.php'),
            // مستند طباعة مستقل يستهلكه محرك PDF، لا صفحة داخل غلاف الموقع.
            resource_path('views/site/pages/profile-pdf.blade.php'),
        ]);

        foreach (['app', 'admin', 'site', 'auth', 'errors'] as $root) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(resource_path("views/{$root}")),
            );

            foreach ($iterator as $view) {
                $path = $view->getPathname();

                if (! str_ends_with($path, '.blade.php')
                    || str_contains($path, DIRECTORY_SEPARATOR.'partials'.DIRECTORY_SEPARATOR)
                    || in_array(str_replace('\\', '/', $path), $nonPageViews, true)
                    || str_starts_with(basename($path), '_')) {
                    continue;
                }

                $views[] = $path;
            }
        }

        foreach ($views as $view) {
            $this->assertMatchesRegularExpression(
                '/(?:@section\([\'\"]layout[\'\"],\s*[\'\"]|data-layout=[\'\"])(?:'.implode('|', $allowed).')[\'\"]\)?/',
                file_get_contents($view),
                $view,
            );
        }
    }

    #[Test]
    public function shared_layouts_expose_the_declared_family_to_the_rendered_page(): void
    {
        foreach (['app', 'public', 'auth'] as $layout) {
            $contents = file_get_contents(resource_path("views/layouts/{$layout}.blade.php"));

            $this->assertStringContainsString("yieldContent('layout'", $contents, $layout);
            $this->assertStringContainsString('data-layout="{{ $layoutFamily }}"', $contents, $layout);
        }

        $app = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $auth = file_get_contents(resource_path('views/layouts/auth.blade.php'));

        $this->assertStringContainsString('layout-page--{{', $app);
        $this->assertStringContainsString('layout-page--auth', $auth);
    }

    #[Test]
    public function light_workspace_sidebar_uses_the_dark_brand_and_readable_text_tokens(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $css = file_get_contents(resource_path('css/interface-system.css'));

        $this->assertStringContainsString('<x-brand-logo class="panel__brand-logo--on-light" />', $layout);
        $this->assertStringContainsString('<x-brand-logo light class="panel__brand-logo--on-dark" />', $layout);
        $this->assertStringContainsString("html[data-theme='dark'] .panel__brand-logo--on-light", $css);
        $this->assertMatchesRegularExpression(
            "/body\[data-interface-system='v2'\] \.panel__side \{[^}]*color:\s*var\(--ui-ink\);/s",
            $css,
        );
        $this->assertStringContainsString("body[data-interface-system='v2'] .panel__brand-context", $css);
        $this->assertStringContainsString("body[data-interface-system='v2'] .panel__link:not(.is-active)", $css);
        $this->assertStringContainsString("body[data-interface-system='v2'] .panel__user-meta strong", $css);
    }

    #[Test]
    public function representative_pages_use_the_approved_structural_patterns(): void
    {
        $expectations = [
            'app/dashboard' => ['layout-metrics', 'layout-main-aside'],
            'admin/dashboard' => ['layout-metrics', 'layout-main-aside'],
            'admin/usage' => ['layout-metrics', 'layout-main-aside'],
            'app/projects/show' => ['layout-main-aside', 'layout-aside'],
            'app/runs/step' => ['layout-main-aside', 'layout-aside'],
            'app/reports/show' => ['layout-report', 'layout-aside'],
            'app/tasks/index' => ['layout-board'],
        ];

        foreach ($expectations as $view => $hooks) {
            $contents = file_get_contents(resource_path("views/{$view}.blade.php"));

            foreach ($hooks as $hook) {
                $this->assertStringContainsString($hook, $contents, "{$view}: {$hook}");
            }
        }

        $css = file_get_contents(resource_path('css/workspace.css'));

        foreach (['.layout-flow', '.layout-aside', '.layout-board'] as $hook) {
            $this->assertStringContainsString($hook, $css, $hook);
        }
    }

    #[Test]
    public function the_customer_dashboard_keeps_metrics_first_and_other_diagnostics_secondary(): void
    {
        $dashboard = file_get_contents(resource_path('views/app/dashboard.blade.php'));
        $css = file_get_contents(resource_path('css/workspace.css'));

        // بلا قوس إغلاق: الصنف يقبل مُعدِّلًا بعده (`layout-metrics--rail`)،
        // وبإبقاء علامة الاقتباس الختامية كانت `strpos` ترجع false فيمرّ
        // التحقق من الترتيب فراغًا — يجتاز وهو لا يفحص شيئًا.
        $metricsAt = strpos($dashboard, 'class="layout-metrics');
        $this->assertIsInt($metricsAt, 'شريط الأرقام غائب عن اللوحة.');
        $this->assertLessThan(
            strpos($dashboard, 'class="layout-main-aside"'),
            $metricsAt,
        );
        $this->assertStringContainsString(
            "</aside>\n    </div>\n\n    <section aria-labelledby=\"tools-heading\">",
            $dashboard,
        );
        $this->assertStringContainsString('id="tools-heading" class="section-title">{{ __(\'التشخيصات الأخرى\') }}</h2>', $dashboard);
        $this->assertStringContainsString("class=\"btn btn--ghost\" href=\"{{ route('app.tools.index') }}\"", $dashboard);
        $this->assertSame(1, substr_count($dashboard, 'data-primary-action'));
        $this->assertStringNotContainsString('تشخيصات مقترحة للبدء', $dashboard);
        $this->assertStringNotContainsString('[data-layout="dashboard"] > .layout-metrics', $css);
    }

    #[Test]
    public function tables_forms_and_windows_declare_their_responsive_intent(): void
    {
        foreach (['admin/users/index', 'admin/payments/index', 'app/billing/index'] as $view) {
            $contents = file_get_contents(resource_path("views/{$view}.blade.php"));

            $this->assertStringContainsString('data-table="entity"', $contents, $view);
            $this->assertStringContainsString('data-label=', $contents, $view);
        }

        foreach ([
            'app/projects/create',
            'app/projects/edit',
            'admin/features/form',
            'admin/gateways/form',
            'admin/packs/form',
            'admin/plans/form',
            'admin/settings/index',
            'admin/tools/form',
            'admin/users/bulk-plan',
            'admin/users/form',
            'admin/users/plan-preview',
        ] as $view) {
            $this->assertStringContainsString(
                'form-layout',
                file_get_contents(resource_path("views/{$view}.blade.php")),
                $view,
            );
        }

        $consultation = file_get_contents(resource_path('views/app/consultations/show.blade.php'));
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $css = file_get_contents(resource_path('css/workspace.css'));

        $this->assertStringContainsString('data-confirm=', $consultation);
        $this->assertStringContainsString('data-confirm-dialog', $layout);

        foreach (['[data-table="entity"]', '[data-table="matrix"]', '.form-layout', '.dialog--confirm', '.dialog--edit', '.drawer--detail'] as $hook) {
            $this->assertStringContainsString($hook, $css, $hook);
        }
    }

    #[Test]
    public function public_auth_legal_and_status_pages_use_the_approved_widths_and_grids(): void
    {
        $expectations = [
            'home' => ['layout-hero'],
            'site/tools/index' => ['public-card-grid'],
            'site/tools/show' => ['public-tool-hero', 'public-step-grid'],
            'site/try/step' => ['layout-page--form'],
            'site/try/result' => ['layout-page--form'],
            'site/legal' => ['layout-page--reading'],
            'layouts/auth' => ['layout-page--auth'],
            'errors/404' => ['status-layout'],
        ];

        foreach ($expectations as $view => $hooks) {
            $contents = file_get_contents(resource_path("views/{$view}.blade.php"));

            foreach ($hooks as $hook) {
                $this->assertStringContainsString($hook, $contents, "{$view}: {$hook}");
            }
        }

        $siteCss = file_get_contents(resource_path('css/site-pages.css'));
        $workspaceCss = file_get_contents(resource_path('css/workspace.css'));

        foreach (['.public-card-grid', '.public-tool-hero', '.public-step-grid', '.status-layout'] as $hook) {
            $this->assertStringContainsString($hook, $siteCss, $hook);
        }

        $this->assertStringContainsString('.layout-page--auth', $workspaceCss);
    }

    #[Test]
    public function the_public_shell_does_not_force_page_overflow_at_320_pixels(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringNotContainsString('min-width: 320px', $css);
        $this->assertStringContainsString('min-inline-size: 0', $css);
    }
}
