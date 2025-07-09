<?php

namespace Keggermont\LaravelPipedrive\Data;

use Carbon\Carbon;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;

class PipedriveActivityData extends BasePipedriveData
{
    public function __construct(
        public int $id,
        public ?string $subject = null,
        public ?string $note = null,
        public bool $done = false,
        public ?string $type = null,
        
        // Date and time fields
        public ?string $due_date = null,
        public ?string $due_time = null,
        public ?string $duration = null,
        
        #[MapInputName('marked_as_done_time')]
        #[WithCast(DateTimeInterfaceCast::class, format: 'Y-m-d H:i:s')]
        public ?Carbon $marked_as_done_time = null,
        
        // User fields
        public ?int $user_id = null,
        public ?int $created_by_user_id = null,
        
        // Related entities
        public ?int $deal_id = null,
        public ?int $person_id = null,
        public ?int $org_id = null,
        public ?int $lead_id = null,
        
        // Location fields
        public ?string $location = null,
        public ?float $location_lat = null,
        public ?float $location_lng = null,
        
        // Additional fields
        public ?string $public_description = null,
        public bool $busy_flag = false,
        public ?array $attendees = null,
        public ?array $participants = null,
        
        #[MapInputName('last_notification_time')]
        #[WithCast(DateTimeInterfaceCast::class, format: 'Y-m-d H:i:s')]
        public ?Carbon $last_notification_time = null,
        
        // Base class properties
        #[MapInputName('add_time')]
        #[WithCast(DateTimeInterfaceCast::class, format: 'Y-m-d H:i:s')]
        public ?Carbon $pipedrive_add_time = null,
        
        #[MapInputName('update_time')]
        #[WithCast(DateTimeInterfaceCast::class, format: 'Y-m-d H:i:s')]
        public ?Carbon $pipedrive_update_time = null,
        
        public bool $active_flag = true,
    ) {}
}
