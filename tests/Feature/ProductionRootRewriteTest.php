<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class ProductionRootRewriteTest extends TestCase
{
    private string $rules;

    private string $publicRules;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rules = file_get_contents(
            dirname(__DIR__, 2).'/deploy/production-root/.htaccess',
        );
        $this->publicRules = file_get_contents(
            dirname(__DIR__, 2).'/public/.htaccess',
        );
    }

    public function test_customer_dashboard_requests_reach_laravel_before_directory_protection(): void
    {
        $dashboardRule = 'RewriteRule ^app(?:/|$) public/index.php [L]';
        $protectedDirectoriesRule = 'RewriteRule ^(bootstrap|config|database|resources|routes|scripts|storage|tests|vendor|deploy|docs|node_modules)(/|$) - [F,L]';

        $this->assertStringContainsString($dashboardRule, $this->rules);
        $this->assertStringContainsString($protectedDirectoriesRule, $this->rules);
        $this->assertLessThan(
            strpos($this->rules, $protectedDirectoriesRule),
            strpos($this->rules, $dashboardRule),
            'The /app dashboard rewrite must run before protected-directory rules.',
        );
    }

    public function test_direct_public_prefix_is_redirected_only_for_browser_requests(): void
    {
        $this->assertStringContainsString(
            'RewriteCond %{THE_REQUEST} \s/+public(?:[/?\s]|$) [NC]',
            $this->rules,
        );
        $this->assertStringContainsString(
            'RewriteRule ^public(?:/(.*))?$ /$1 [R=301,L,NE]',
            $this->rules,
        );
        $this->assertStringContainsString(
            'RewriteCond %{THE_REQUEST} \s/+public(?:[/?\s]|$) [NC]',
            $this->publicRules,
        );
        $this->assertStringContainsString(
            'RewriteRule ^(.*)$ /$1 [R=301,L,NE]',
            $this->publicRules,
        );
    }
}
