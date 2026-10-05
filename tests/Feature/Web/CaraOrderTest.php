<?php

namespace Tests\Feature\Web;

use Tests\TestCase;

class CaraOrderTest extends TestCase
{
    public function test_cara_order_page_returns_200(): void
    {
        $response = $this->get('/cara-order');
        $response->assertStatus(200);
    }

    public function test_cara_order_renders_caraorder_component(): void
    {
        $response = $this->get('/cara-order');
        $response->assertInertia(fn ($page) => $page->component('CaraOrder'));
    }

    public function test_cara_order_publicly_accessible_without_auth(): void
    {
        $response = $this->get('/cara-order');
        $response->assertOk();
    }
}
