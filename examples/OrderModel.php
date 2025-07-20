<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Skeylup\LaravelPipedrive\Traits\HasPipedriveEntity;

/**
 * Example Order model showing how to use the HasPipedriveEntity trait
 *
 * This is just an example - you would create this in your Laravel app
 */
class Order extends Model
{
    use HasPipedriveEntity;

    protected $fillable = [
        'order_number',
        'customer_name',
        'customer_email',
        'total_amount',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
        ];
    }

    /**
     * Example usage methods
     */

    /**
     * Link this order to a Pipedrive deal when it's created
     */
    public function linkToDeal(int $dealId): void
    {
        // Link as primary deal
        $this->linkToPipedriveDeal($dealId, true, [
            'linked_at' => now(),
            'linked_by' => auth()->id(),
            'reason' => 'Order created from deal',
        ]);
    }

    /**
     * Get the associated Pipedrive deal
     */
    public function getPipedriveDeal()
    {
        return $this->getPrimaryPipedriveDeal();
    }

    /**
     * Sync order status with Pipedrive deal
     */
    public function syncWithPipedrive(): bool
    {
        $deal = $this->getPipedriveDeal();

        if (! $deal) {
            return false;
        }

        // Update deal value based on order total
        // This would use the Pipedrive API to update the deal
        // $pipedriveService->updateDeal($deal->pipedrive_id, [
        //     'value' => $this->total_amount,
        //     'status' => $this->mapOrderStatusToDealStatus(),
        // ]);

        return true;
    }

    /**
     * Map order status to Pipedrive deal status
     */
    protected function mapOrderStatusToDealStatus(): string
    {
        return match ($this->status) {
            'pending' => 'open',
            'completed' => 'won',
            'cancelled' => 'lost',
            default => 'open',
        };
    }

    /**
     * Get order statistics with Pipedrive data
     */
    public function getOrderStats(): array
    {
        $pipedriveStats = $this->getPipedriveEntityStats();

        return [
            'order_id' => $this->id,
            'order_number' => $this->order_number,
            'total_amount' => $this->total_amount,
            'status' => $this->status,
            'pipedrive_links' => $pipedriveStats['total_links'],
            'has_deal' => $this->getPipedriveDeal() !== null,
            'has_person' => $this->getPrimaryPipedrivePerson() !== null,
            'has_organization' => $this->getPrimaryPipedriveOrganization() !== null,
        ];
    }
}

/**
 * Example usage in a controller or service:
 */

/*
// Create an order and link it to a Pipedrive deal
$order = Order::create([
    'order_number' => 'ORD-001',
    'customer_name' => 'John Doe',
    'customer_email' => 'john@example.com',
    'total_amount' => 1500.00,
    'status' => 'pending',
]);

// Link to Pipedrive deal ID 123
$order->linkToDeal(123);

// Or link manually with more control
$order->linkToPipedriveDeal(123, true, [
    'source' => 'manual',
    'notes' => 'Linked from admin panel',
]);

// Link to a person as well
$order->linkToPipedrivePerson(456, false, [
    'role' => 'customer',
]);

// Link to an organization
$order->linkToPipedriveOrganization(789, false, [
    'relationship' => 'client',
]);

// Get all linked entities
$deal = $order->getPrimaryPipedriveDeal();
$person = $order->getPrimaryPipedrivePerson();
$organization = $order->getPrimaryPipedriveOrganization();

// Check if linked
if ($order->isLinkedToPipedriveEntity('deals', 123)) {
    echo "Order is linked to deal 123";
}

// Get statistics
$stats = $order->getPipedriveEntityStats();
echo "Total Pipedrive links: " . $stats['total_links'];

// Sync all linked entities
$syncResults = $order->syncPipedriveEntities();

// Unlink from a specific entity
$order->unlinkFromPipedriveEntity('deals', 123);

// Unlink from all entities
$order->unlinkFromAllPipedriveEntities();
*/
