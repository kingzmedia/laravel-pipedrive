<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Skeylup\LaravelPipedrive\Traits\HasPipedriveEntity;
use Skeylup\LaravelPipedrive\Enums\PipedriveEntityType;

/**
 * Example Order model showing how to use push and display methods
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
        'notes',
    ];

    // Define default Pipedrive entity type
    protected PipedriveEntityType $pipedriveEntityType = PipedriveEntityType::DEALS;

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
        ];
    }

    /**
     * Update order status and push to Pipedrive (async by default)
     */
    public function updateStatus(string $newStatus, ?string $notes = null, bool $forceSync = false): array
    {
        $modifications = [
            'status' => $this->mapOrderStatusToDealStatus($newStatus),
        ];

        $customFields = [];

        // Add notes to custom field if provided
        if ($notes) {
            $customFields['Order Notes'] = $notes;
        }

        // Add order number as custom field
        $customFields['Order Number'] = $this->order_number;

        // Push to Pipedrive (async by default, sync if forced)
        $result = $this->pushToPipedrive($modifications, $customFields, $forceSync);

        // Only update local model if sync was successful or job was dispatched
        if ($result['success']) {
            if ($result['processed_via'] === 'sync') {
                // Sync execution - update immediately
                $this->status = $newStatus;
                if ($notes) {
                    $this->notes = $notes;
                }
                $this->save();
            } else {
                // Job dispatched - the job will update the model when it runs
                Log::info('Order status update job dispatched', [
                    'order_id' => $this->id,
                    'new_status' => $newStatus,
                    'queue' => $result['queue'] ?? 'default',
                ]);
            }
        }

        return $result;
    }

    /**
     * Update order status synchronously (immediate execution)
     */
    public function updateStatusSync(string $newStatus, ?string $notes = null): array
    {
        return $this->updateStatus($newStatus, $notes, true);
    }

    /**
     * Update order status with custom queue
     */
    public function updateStatusOnQueue(string $newStatus, ?string $notes = null, string $queue = 'pipedrive'): array
    {
        $modifications = [
            'status' => $this->mapOrderStatusToDealStatus($newStatus),
        ];

        $customFields = [];
        if ($notes) {
            $customFields['Order Notes'] = $notes;
        }
        $customFields['Order Number'] = $this->order_number;

        return $this->pushToPipedrive($modifications, $customFields, false, $queue);
    }

    /**
     * Update order value and push to Pipedrive (high priority - sync execution)
     */
    public function updateValue(float $newAmount, string $currency = 'EUR'): array
    {
        $modifications = [
            'value' => $newAmount,
            'currency' => $currency,
        ];

        $customFields = [
            'Original Order Amount' => $this->total_amount,
            'Order Number' => $this->order_number,
        ];

        // Force sync for critical value updates
        $result = $this->pushToPipedrive($modifications, $customFields, true);

        if ($result['success']) {
            $this->total_amount = $newAmount;
            $this->save();
        }

        return $result;
    }

    /**
     * Add custom information to Pipedrive (async with custom queue)
     */
    public function addCustomInfo(array $customData, string $queue = 'low-priority'): array
    {
        // No basic field modifications, only custom fields
        $modifications = [];

        $customFields = array_merge([
            'Order Number' => $this->order_number,
            'Customer Email' => $this->customer_email,
        ], $customData);

        // Use low priority queue for non-critical updates
        return $this->pushToPipedrive($modifications, $customFields, false, $queue);
    }

    /**
     * Mark order as urgent (high priority queue with retries)
     */
    public function markAsUrgent(string $reason): array
    {
        $modifications = [
            'status' => 'open', // Keep deal open for urgent orders
        ];

        $customFields = [
            'Urgent Flag' => 'Yes',
            'Urgent Reason' => $reason,
            'Urgent Date' => now()->format('Y-m-d H:i:s'),
            'Order Number' => $this->order_number,
        ];

        // Use high priority queue with more retries
        return $this->pushToPipedrive($modifications, $customFields, false, 'high-priority', 5);
    }

    /**
     * Get detailed view of the order with Pipedrive data
     */
    public function getDetailedView(): array
    {
        $orderData = [
            'order_id' => $this->id,
            'order_number' => $this->order_number,
            'customer_name' => $this->customer_name,
            'customer_email' => $this->customer_email,
            'total_amount' => $this->total_amount,
            'status' => $this->status,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];

        $pipedriveDetails = $this->displayPipedriveDetails();

        return [
            'order' => $orderData,
            'pipedrive' => $pipedriveDetails,
        ];
    }

    /**
     * Map order status to Pipedrive deal status
     */
    protected function mapOrderStatusToDealStatus(string $orderStatus): string
    {
        return match ($orderStatus) {
            'pending' => 'open',
            'processing' => 'open',
            'completed' => 'won',
            'cancelled' => 'lost',
            'refunded' => 'lost',
            default => 'open',
        };
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

// Link to Pipedrive deal
$order->linkToPipedriveEntity(123, true);

// 1. Update status asynchronously (default behavior)
$result = $order->updateStatus('completed', 'Order completed successfully');

if ($result['success']) {
    if ($result['processed_via'] === 'job') {
        echo "Order status update job dispatched to queue: " . $result['queue'];
        echo "Job will process with max retries: " . $result['max_retries'];
    } else {
        echo "Order status updated in Pipedrive synchronously!";
    }
    echo "Updated fields: " . implode(', ', $result['updated_fields']);
} else {
    echo "Failed to update Pipedrive: " . $result['error'];
}

// 2. Update status synchronously (immediate execution)
$result = $order->updateStatusSync('completed', 'Order completed successfully');

// 3. Update status on specific queue
$result = $order->updateStatusOnQueue('processing', 'Order is being processed', 'orders-queue');

// 4. Update order value (critical - always sync)
$result = $order->updateValue(1750.00, 'EUR');

// 5. Add custom information (low priority queue)
$result = $order->addCustomInfo([
    'Delivery Date' => '2024-02-15',
    'Special Instructions' => 'Handle with care',
    'Sales Rep' => 'Jane Smith',
], 'low-priority');

// 6. Mark as urgent (high priority with more retries)
$result = $order->markAsUrgent('Customer requested expedited delivery');

// Get detailed view with Pipedrive data
$detailedView = $order->getDetailedView();

echo "Order Details:\n";
print_r($detailedView['order']);

echo "\nPipedrive Details:\n";
if ($detailedView['pipedrive']['has_entity']) {
    echo "Pipedrive ID: " . $detailedView['pipedrive']['pipedrive_id'] . "\n";
    echo "Entity Type: " . $detailedView['pipedrive']['entity_type'] . "\n";
    
    echo "\nBasic Fields:\n";
    foreach ($detailedView['pipedrive']['basic_fields'] as $field => $value) {
        echo "  {$field}: {$value}\n";
    }
    
    echo "\nCustom Fields:\n";
    foreach ($detailedView['pipedrive']['custom_fields'] as $name => $fieldData) {
        echo "  {$name}: {$fieldData['value']} (Type: {$fieldData['field_type']})\n";
    }
} else {
    echo "No Pipedrive entity linked\n";
}

// 7. Advanced examples with different execution modes

// Synchronous execution with error handling
try {
    $result = $order->pushToPipedrive([
        'title' => 'Updated Order: ' . $order->order_number,
        'value' => $order->total_amount,
    ], [
        'Order Status' => $order->status,
        'Customer Email' => $order->customer_email,
        'Last Updated' => now()->format('Y-m-d H:i:s'),
    ], true); // Force sync

    if ($result['success']) {
        Log::info('Order synchronized with Pipedrive (sync)', [
            'order_id' => $order->id,
            'pipedrive_id' => $result['pipedrive_id'],
            'updated_fields' => $result['updated_fields'],
            'processed_via' => $result['processed_via'],
        ]);
    } else {
        Log::error('Failed to sync order with Pipedrive (sync)', [
            'order_id' => $order->id,
            'error' => $result['error'],
        ]);
    }
} catch (\Exception $e) {
    Log::error('Exception during Pipedrive sync', [
        'order_id' => $order->id,
        'error' => $e->getMessage(),
    ]);
}

// Asynchronous execution with custom queue and retries
$result = $order->pushToPipedrive([
    'title' => 'Async Update: ' . $order->order_number,
], [
    'Processing Status' => 'In Queue',
], false, 'pipedrive-updates', 5); // 5 retries on 'pipedrive-updates' queue

if ($result['success'] && $result['job_dispatched']) {
    Log::info('Pipedrive update job dispatched', [
        'order_id' => $order->id,
        'queue' => $result['queue'],
        'max_retries' => $result['max_retries'],
    ]);
}

// Conditional execution based on environment
$forceSync = app()->environment('testing') || config('app.debug');
$result = $order->pushToPipedrive([
    'status' => 'won',
], [], $forceSync);

// Queue-specific execution for different priorities
if ($order->is_urgent) {
    // High priority - sync execution
    $result = $order->pushToPipedrive($modifications, $customFields, true);
} elseif ($order->is_important) {
    // Medium priority - fast queue
    $result = $order->pushToPipedrive($modifications, $customFields, false, 'fast');
} else {
    // Low priority - default queue
    $result = $order->pushToPipedrive($modifications, $customFields);
}

// Display only custom fields
$pipedriveDetails = $order->displayPipedriveDetails(true);
if ($pipedriveDetails['has_entity']) {
    echo "Custom Fields in Pipedrive:\n";
    foreach ($pipedriveDetails['custom_fields'] as $name => $fieldData) {
        echo "  {$name}: {$fieldData['value']}\n";
        echo "    Raw Value: {$fieldData['raw_value']}\n";
        echo "    Field Type: {$fieldData['field_type']}\n";
        echo "    Pipedrive Key: {$fieldData['key']}\n\n";
    }
}
*/
