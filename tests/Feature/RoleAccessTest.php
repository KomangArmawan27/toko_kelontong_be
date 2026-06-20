<?php

use App\Enums\UserRole;
use App\Models\Item;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function bearerTokenFor(User $user): string
{
    return app(JwtService::class)->issue($user);
}

test('customers can browse active items but not hidden ones', function (): void {
    $owner = User::factory()->shopOwner()->create();
    $customer = User::factory()->customer()->create();

    Item::query()->create([
        'user_id' => $owner->getKey(),
        'sku' => 'ACT-001',
        'name' => 'Active Snack',
        'description' => 'Visible to customers',
        'unit' => 'pcs',
        'purchase_price' => 5000,
        'selling_price' => 7000,
        'current_stock' => 12,
        'minimum_stock' => 2,
        'is_active' => true,
    ]);

    Item::query()->create([
        'user_id' => $owner->getKey(),
        'sku' => 'HID-001',
        'name' => 'Hidden Snack',
        'description' => 'Hidden from customers',
        'unit' => 'pcs',
        'purchase_price' => 6000,
        'selling_price' => 8000,
        'current_stock' => 8,
        'minimum_stock' => 2,
        'is_active' => false,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.bearerTokenFor($customer))
        ->getJson('/api/items');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Active Snack')
        ->assertJsonMissingPath('data.0.purchase_price')
        ->assertJsonMissingPath('data.0.minimum_stock');
});

test('shop keepers can manage cash transactions and stock movements but not master items', function (): void {
    $owner = User::factory()->shopOwner()->create();
    $keeper = User::factory()->shopKeeper()->create();

    $item = Item::query()->create([
        'user_id' => $owner->getKey(),
        'sku' => 'KEEP-ITEM',
        'name' => 'Keeper Item',
        'description' => 'For keeper tests',
        'unit' => 'pcs',
        'purchase_price' => 4000,
        'selling_price' => 6000,
        'current_stock' => 10,
        'minimum_stock' => 2,
        'is_active' => true,
    ]);

    $cashResponse = $this->withHeader('Authorization', 'Bearer '.bearerTokenFor($keeper))
        ->postJson('/api/cash-transactions', [
            'type' => 'cash_in',
            'amount' => 50000,
            'description' => 'Daily cash drop',
            'transaction_date' => today()->toDateString(),
        ]);

    $cashResponse->assertCreated()
        ->assertJsonPath('data.type', 'cash_in');

    $stockResponse = $this->withHeader('Authorization', 'Bearer '.bearerTokenFor($keeper))
        ->postJson('/api/stock-movements', [
            'item_id' => $item->getKey(),
            'type' => 'in',
            'quantity' => 5,
            'notes' => 'Restock',
        ]);

    $stockResponse->assertCreated()
        ->assertJsonPath('data.type', 'in');

    $this->withHeader('Authorization', 'Bearer '.bearerTokenFor($keeper))
        ->postJson('/api/items', [
            'sku' => 'KEEP-001',
            'name' => 'Keeper Item',
            'unit' => 'pcs',
        ])
        ->assertForbidden();
});

test('shop owner can promote a user to keeper or owner', function (): void {
    $owner = User::factory()->shopOwner()->create();
    $candidate = User::factory()->customer()->create();

    $response = $this->withHeader('Authorization', 'Bearer '.bearerTokenFor($owner))
        ->patchJson("/api/users/{$candidate->getKey()}/role", [
            'role' => UserRole::ShopKeeper->value,
        ]);

    $response->assertOk()
        ->assertJsonPath('user.id', $candidate->getKey())
        ->assertJsonPath('user.role', UserRole::ShopKeeper->value);

    $this->assertDatabaseHas('users', [
        'id' => $candidate->getKey(),
        'role' => UserRole::ShopKeeper->value,
    ]);
});

test('non owners cannot promote user roles', function (): void {
    $keeper = User::factory()->shopKeeper()->create();
    $candidate = User::factory()->customer()->create();

    $this->withHeader('Authorization', 'Bearer '.bearerTokenFor($keeper))
        ->patchJson("/api/users/{$candidate->getKey()}/role", [
            'role' => UserRole::ShopOwner->value,
        ])
        ->assertForbidden();
});

test('customers can purchase an item and reduce stock', function (): void {
    $customer = User::factory()->customer()->create();
    $owner = User::factory()->shopOwner()->create();

    $item = Item::query()->create([
        'user_id' => $owner->getKey(),
        'sku' => 'PUR-001',
        'name' => 'Purchase Item',
        'description' => 'Ready to buy',
        'unit' => 'pcs',
        'purchase_price' => 10000,
        'selling_price' => 15000,
        'current_stock' => 10,
        'minimum_stock' => 2,
        'is_active' => true,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.bearerTokenFor($customer))
        ->postJson("/api/items/{$item->getKey()}/purchase", [
            'quantity' => 3,
            'transaction_date' => today()->toDateString(),
            'notes' => 'Customer purchase',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.total_amount', 45000)
        ->assertJsonPath('data.item.current_stock', '7.00');

    $item->refresh();

    expect($item->current_stock)->toBe('7.00');

    $this->assertDatabaseHas('stock_movements', [
        'item_id' => $item->getKey(),
        'type' => 'out',
        'quantity' => '3.00',
        'stock_before' => '10.00',
        'stock_after' => '7.00',
    ]);

    $this->assertDatabaseHas('cash_transactions', [
        'type' => 'cash_in',
        'amount' => '45000.00',
        'description' => 'Customer purchase',
    ]);
});
