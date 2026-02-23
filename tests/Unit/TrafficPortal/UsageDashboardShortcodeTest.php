<?php

declare(strict_types=1);

namespace Tests\Unit\TrafficPortal;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Unit tests for TP_Usage_Dashboard_Shortcode class structure.
 *
 * Note: Full integration tests (actually rendering shortcode output) require
 * WordPress bootstrapping and are covered by E2E tests in Plan 05-03.
 * These unit tests verify class structure and method existence without WordPress.
 */
class UsageDashboardShortcodeTest extends TestCase
{
    private string $classFile;

    protected function setUp(): void
    {
        $this->classFile = dirname(__DIR__, 3) . '/includes/class-tp-usage-dashboard-shortcode.php';
    }

    public function testClassFileExists(): void
    {
        $this->assertFileExists(
            $this->classFile,
            'Shortcode class file should exist at includes/class-tp-usage-dashboard-shortcode.php'
        );
    }

    public function testClassFileContainsAbspathGuard(): void
    {
        $contents = file_get_contents($this->classFile);
        $this->assertStringContainsString(
            "if (!defined('ABSPATH'))",
            $contents,
            'Class file should guard against direct access with ABSPATH check'
        );
    }

    public function testClassFileContainsStrictTypes(): void
    {
        $contents = file_get_contents($this->classFile);
        $this->assertStringContainsString(
            'declare(strict_types=1)',
            $contents,
            'Class file should declare strict types'
        );
    }

    public function testClassFileContainsShortcodeRegistration(): void
    {
        $contents = file_get_contents($this->classFile);
        $this->assertMatchesRegularExpression(
            '/add_shortcode\s*\(\s*[\'"]tp_usage_dashboard[\'"]/',
            $contents,
            'Class should register tp_usage_dashboard shortcode'
        );
    }

    public function testClassFileContainsLoginFormWithEchoFalse(): void
    {
        $contents = file_get_contents($this->classFile);
        $this->assertStringContainsString(
            "'echo'",
            $contents,
            'wp_login_form should include echo parameter'
        );
        $this->assertStringContainsString(
            'false',
            $contents,
            'echo parameter should be set to false'
        );
    }

    public function testClassFileContainsRedirectToPermalink(): void
    {
        $contents = file_get_contents($this->classFile);
        $this->assertStringContainsString(
            "'redirect'",
            $contents,
            'wp_login_form should include redirect parameter'
        );
        $this->assertStringContainsString(
            'get_permalink()',
            $contents,
            'redirect should point to get_permalink()'
        );
    }

    public function testClassFileContainsTemplateInclude(): void
    {
        $contents = file_get_contents($this->classFile);
        $this->assertMatchesRegularExpression(
            '/include.*usage-dashboard-template/',
            $contents,
            'Class should include the usage dashboard template'
        );
    }

    public function testClassFileContainsLocalizeScript(): void
    {
        $contents = file_get_contents($this->classFile);
        $this->assertStringContainsString(
            'wp_localize_script',
            $contents,
            'Class should use wp_localize_script to pass data to JS'
        );
        $this->assertStringContainsString(
            'tpUsageDashboard',
            $contents,
            'Localized script object should be named tpUsageDashboard'
        );
    }

    public function testTemplateFileExists(): void
    {
        $templateFile = dirname(__DIR__, 3) . '/templates/usage-dashboard-template.php';
        $this->assertFileExists(
            $templateFile,
            'Template file should exist at templates/usage-dashboard-template.php'
        );
    }

    public function testTemplateContainsThreeStates(): void
    {
        $templateFile = dirname(__DIR__, 3) . '/templates/usage-dashboard-template.php';
        $contents = file_get_contents($templateFile);

        $this->assertStringContainsString('tp-ud-skeleton', $contents, 'Template should contain skeleton state');
        $this->assertStringContainsString('tp-ud-error', $contents, 'Template should contain error state');
        $this->assertStringContainsString('tp-ud-content', $contents, 'Template should contain content state');
    }

    public function testTemplateContainsChartCanvas(): void
    {
        $templateFile = dirname(__DIR__, 3) . '/templates/usage-dashboard-template.php';
        $contents = file_get_contents($templateFile);

        $this->assertStringContainsString('tp-ud-chart', $contents, 'Template should contain chart canvas');
    }

    public function testCssFileExists(): void
    {
        $cssFile = dirname(__DIR__, 3) . '/assets/css/usage-dashboard.css';
        $this->assertFileExists(
            $cssFile,
            'CSS file should exist at assets/css/usage-dashboard.css'
        );
    }

    public function testCssUsesCorrectPrefix(): void
    {
        $cssFile = dirname(__DIR__, 3) . '/assets/css/usage-dashboard.css';
        $contents = file_get_contents($cssFile);

        $this->assertStringContainsString('.tp-ud-container', $contents, 'CSS should define tp-ud-container');
        $this->assertStringContainsString('.tp-ud-skeleton-chart', $contents, 'CSS should define skeleton-chart');
        $this->assertStringContainsString('.tp-ud-skeleton-row', $contents, 'CSS should define skeleton-row');
        $this->assertStringContainsString('@keyframes tp-ud-pulse', $contents, 'CSS should define pulse animation');
    }

    public function testPluginEntryRequiresShortcodeClass(): void
    {
        $entryFile = dirname(__DIR__, 3) . '/tp-link-shortener.php';
        $contents = file_get_contents($entryFile);

        $this->assertMatchesRegularExpression(
            '/require_once.*class-tp-usage-dashboard-shortcode/',
            $contents,
            'Plugin entry file should require the shortcode class'
        );
    }

    public function testMainClassInstantiatesShortcode(): void
    {
        $mainClassFile = dirname(__DIR__, 3) . '/includes/class-tp-link-shortener.php';
        $contents = file_get_contents($mainClassFile);

        $this->assertStringContainsString(
            'usage_dashboard_shortcode',
            $contents,
            'Main class should have a usage_dashboard_shortcode property or instantiation'
        );
        $this->assertStringContainsString(
            'new TP_Usage_Dashboard_Shortcode()',
            $contents,
            'Main class should instantiate TP_Usage_Dashboard_Shortcode'
        );
    }
}
