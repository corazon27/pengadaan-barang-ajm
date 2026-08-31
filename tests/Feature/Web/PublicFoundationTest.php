<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PublicFoundationTest extends TestCase
{
    public function test_root_returns_an_inertia_page_rendering_the_public_home_component(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Home'));
    }

    public function test_public_page_receives_the_public_identity_props(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Home')
                ->where('appName', config('app.name'))
                ->where('companyName', config('company.name')));
    }

    public function test_public_page_shares_no_internal_or_compliance_data(): void
    {
        $response = $this->get('/');

        $response->assertOk();

        $page = $response->viewData('page');

        $this->assertIsArray($page);
        $this->assertArrayHasKey('props', $page);

        $internalKeys = [
            'user',
            'auth',
            'nib',
            'npwp',
            'pkp',
            'bank',
            'consent',
            'dsr',
            'breach',
            'audit',
            'legal_function',
            'processor',
            'human_review',
            'pse',
        ];

        foreach ($internalKeys as $key) {
            $this->assertArrayNotHasKey($key, $page['props'], "Public page must not expose [{$key}].");
        }
    }

    public function test_root_returns_html_with_inertia_root_element(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertHeader('content-type', 'text/html; charset=UTF-8')
            ->assertSee('id="app"', false)
            ->assertSee('data-page=', false);
    }

    public function test_public_page_exposes_no_company_contact_or_extended_props(): void
    {
        $response = $this->get('/');

        $response->assertOk();

        $props = $response->viewData('page')['props'];

        $this->assertIsArray($props);

        foreach (['company', 'address', 'phone', 'email', 'website', 'legal_entity', 'canonicalUrl'] as $key) {
            $this->assertArrayNotHasKey($key, $props, "Public page must not expose [{$key}].");
        }
    }

    public function test_canonical_url_is_not_emitted_in_non_production_environment(): void
    {
        $this->assertNotSame('production', app()->environment());

        $props = $this->get('/')->viewData('page')['props'];

        $this->assertArrayNotHasKey('canonicalUrl', $props, 'Canonical must be omitted outside production.');
    }

    public function test_serialized_page_contains_no_internal_compliance_markers(): void
    {
        $content = $this->get('/')->getContent();

        $internalMarkers = [
            'npwp',
            'nib',
            'pkp',
            'bank',
            'consent',
            'dsr',
            'breach',
            'incident',
            'legal_function',
            'processor',
            'human_review',
            'pse',
        ];

        foreach ($internalMarkers as $marker) {
            $this->assertStringNotContainsString($marker, $content, "Serialized page must not contain [{$marker}].");
        }
    }
}
