<?php

namespace Mouseketeers\CookieConsent\Tests;

use Mouseketeers\CookieConsent\CookieConsent;
use Mouseketeers\CookieConsent\Models\CookieService;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Manifest\ModuleLoader;
use SilverStripe\Dev\SapphireTest;

class CookieServiceRegistryTest extends SapphireTest
{
    protected function setUp(): void
    {
        parent::setUp();
        $module = ModuleLoader::getModule('mouseketeers/silverstripe-cookie-consent');
        Config::modify()->set(
            CookieConsent::class,
            'cookie_registry_path',
            $module->getRelativePath() . '/tests/fixtures/registry.json'
        );
    }

    private function cookieNames(string $category): array
    {
        $service = CookieService::create();
        $service->Name = 'wordpress';

        return array_map(
            fn($viewModel) => $viewModel->Name,
            $service->getCookieViewModelsByCategoryKey($category)
        );
    }

    public function testServiceSpellingsAreMergedAndCategoriesAliased()
    {
        $this->assertEqualsCanonicalizing(
            ['wp_first', 'wp_second', 'wp_security', 'wp_necessary'],
            $this->cookieNames('functional')
        );
        $this->assertSame(['wp_analytics'], $this->cookieNames('analytics'));
    }
}
