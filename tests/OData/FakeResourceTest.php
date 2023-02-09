<?php

namespace JustBetter\DynamicsClient\Tests\OData;

use Illuminate\Support\Facades\Http;
use JustBetter\DynamicsClient\OData\Pages\Item;
use JustBetter\DynamicsClient\Tests\TestCase;

class FakeResourceTest extends TestCase
{
    /** @test */
    public function it_can_fake_dynamics_requests(): void
    {
        Item::fake();

        Http::fake([
            'dynamics/ODataV4/Company(\'default\')/Item?$top=1' => Http::response([
                'value' => [
                    [
                        '@odata.etag' => '::etag::',
                        'No' => '::no::',
                        'Description' => '::description::',
                    ],
                ],
            ]),
        ]);

        $item = Item::query()->first();

        $this->assertEquals('::no::', $item['No']);
    }
}
