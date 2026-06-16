<?php

namespace Tests\Unit;

use App\Agents\Tools\ShareCatalogTool;
use App\Agents\Tools\ToolContext;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShareCatalogToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_definition_has_function_shape(): void
    {
        $def = (new ShareCatalogTool)->definition();

        $this->assertSame('function', $def['type']);
        $this->assertSame('get_catalog_link', $def['function']['name']);
    }

    public function test_returns_deep_link_for_store(): void
    {
        config()->set('chatig.telegram.bot_username', 'ChatigCatalogBot');
        $store = Store::factory()->create();

        $result = (new ShareCatalogTool)->handle([], new ToolContext($store));

        $this->assertTrue($result['ok']);
        $this->assertSame("https://t.me/ChatigCatalogBot/app?startapp={$store->public_id}", $result['url']);
    }

    public function test_returns_unavailable_when_bot_username_missing(): void
    {
        config()->set('chatig.telegram.bot_username', null);
        $store = Store::factory()->create();

        $result = (new ShareCatalogTool)->handle([], new ToolContext($store));

        $this->assertFalse($result['ok']);
    }
}
