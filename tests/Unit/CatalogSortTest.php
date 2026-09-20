<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\CatalogSort;
use App\Models\Product;
use Tests\TestCase;

class CatalogSortTest extends TestCase
{
    public function test_from_request_maps_known_values(): void
    {
        $this->assertSame(CatalogSort::DEFAULT, CatalogSort::fromRequest('default'));
        $this->assertSame(CatalogSort::PRICE_ASC, CatalogSort::fromRequest('price_asc'));
        $this->assertSame(CatalogSort::PRICE_DESC, CatalogSort::fromRequest('price_desc'));
        $this->assertSame(CatalogSort::TKDN_DESC, CatalogSort::fromRequest('tkdn_desc'));
        $this->assertSame(CatalogSort::NEWEST, CatalogSort::fromRequest('newest'));
    }

    public function test_from_request_falls_back_to_default_for_unknown_or_missing(): void
    {
        $this->assertSame(CatalogSort::DEFAULT, CatalogSort::fromRequest('bestseller'));
        $this->assertSame(CatalogSort::DEFAULT, CatalogSort::fromRequest(''));
        $this->assertSame(CatalogSort::DEFAULT, CatalogSort::fromRequest(null));
    }

    public function test_from_request_never_accepts_sql_injection_tokens(): void
    {
        $token = 'price_asc),1);sleep(10)--';
        $this->assertSame(CatalogSort::DEFAULT, CatalogSort::fromRequest($token));

        $token = 'base_price DESC, id DESC; DROP TABLE products--';
        $this->assertSame(CatalogSort::DEFAULT, CatalogSort::fromRequest($token));
    }

    public function test_default_applies_only_id_asc_ordering(): void
    {
        $orders = CatalogSort::DEFAULT->apply(Product::query())->getQuery()->orders;

        $this->assertSame([['column' => 'id', 'direction' => 'asc']], $orders);
    }

    public function test_every_non_default_sort_adds_an_id_asc_tiebreak(): void
    {
        foreach ([
            CatalogSort::PRICE_ASC,
            CatalogSort::PRICE_DESC,
            CatalogSort::TKDN_DESC,
            CatalogSort::NEWEST,
        ] as $sort) {
            $orders = $sort->apply(Product::query())->getQuery()->orders;

            $this->assertCount(2, $orders, "{$sort->value} must order by its column then id.");
            $this->assertSame('id', $orders[1]['column']);
            $this->assertSame('asc', $orders[1]['direction']);
        }
    }

    public function test_price_sorts_target_base_price(): void
    {
        $asc = CatalogSort::PRICE_ASC->apply(Product::query())->getQuery()->orders;
        $desc = CatalogSort::PRICE_DESC->apply(Product::query())->getQuery()->orders;

        $this->assertSame('base_price', $asc[0]['column']);
        $this->assertSame('asc', $asc[0]['direction']);
        $this->assertSame('base_price', $desc[0]['column']);
        $this->assertSame('desc', $desc[0]['direction']);
    }

    public function test_newest_targets_created_at(): void
    {
        $orders = CatalogSort::NEWEST->apply(Product::query())->getQuery()->orders;

        $this->assertSame('created_at', $orders[0]['column']);
        $this->assertSame('desc', $orders[0]['direction']);
    }

    public function test_tkdn_desc_sorts_known_values_before_null_with_id_tiebreak(): void
    {
        $orders = CatalogSort::TKDN_DESC->apply(Product::query())->getQuery()->orders;

        $this->assertSame(['type' => 'Raw', 'sql' => '(tkdn_percentage IS NULL), tkdn_percentage DESC'], $orders[0]);
        $this->assertSame(['column' => 'id', 'direction' => 'asc'], $orders[1]);
    }
}
