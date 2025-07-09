<?php

namespace Keggermont\LaravelPipedrive\Data;

use Carbon\Carbon;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;

class PipedriveDealData extends BasePipedriveData
{
    public function __construct(
        public int $id,
        public string $title,
        public ?float $value = null,
        public ?string $currency = null,
        public ?string $status = null,
        public ?string $stage_id = null,
        public ?string $pipeline_id = null,
        public ?int $user_id = null,
        public ?int $creator_user_id = null,
        public ?int $person_id = null,
        public ?int $org_id = null,
        public ?string $expected_close_date = null,
        
        #[WithCast(DateTimeInterfaceCast::class, format: 'Y-m-d H:i:s')]
        public ?Carbon $close_time = null,
        
        #[WithCast(DateTimeInterfaceCast::class, format: 'Y-m-d H:i:s')]
        public ?Carbon $won_time = null,
        
        #[WithCast(DateTimeInterfaceCast::class, format: 'Y-m-d H:i:s')]
        public ?Carbon $lost_time = null,
        
        #[WithCast(DateTimeInterfaceCast::class, format: 'Y-m-d H:i:s')]
        public ?Carbon $first_won_time = null,
        
        public ?int $probability = null,
        public ?float $weighted_value = null,
        public ?string $lost_reason = null,
        public ?string $visible_to = null,
        
        // Counters
        public int $activities_count = 0,
        public int $done_activities_count = 0,
        public int $undone_activities_count = 0,
        public int $email_messages_count = 0,
        public int $files_count = 0,
        public int $notes_count = 0,
        public int $followers_count = 0,
        
        // Additional fields
        public ?string $notes = null,
        public ?array $label = null,
        public bool $active = true,
        public bool $deleted = false,
        
        // Timestamps
        #[MapInputName('stage_change_time')]
        #[WithCast(DateTimeInterfaceCast::class, format: 'Y-m-d H:i:s')]
        public ?Carbon $stage_change_time = null,
        
        #[MapInputName('next_activity_date')]
        #[WithCast(DateTimeInterfaceCast::class, format: 'Y-m-d H:i:s')]
        public ?Carbon $next_activity_date = null,
        
        #[MapInputName('last_activity_date')]
        #[WithCast(DateTimeInterfaceCast::class, format: 'Y-m-d H:i:s')]
        public ?Carbon $last_activity_date = null,
        
        #[MapInputName('last_incoming_mail_time')]
        #[WithCast(DateTimeInterfaceCast::class, format: 'Y-m-d H:i:s')]
        public ?Carbon $last_incoming_mail_time = null,
        
        #[MapInputName('last_outgoing_mail_time')]
        #[WithCast(DateTimeInterfaceCast::class, format: 'Y-m-d H:i:s')]
        public ?Carbon $last_outgoing_mail_time = null,
        
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
