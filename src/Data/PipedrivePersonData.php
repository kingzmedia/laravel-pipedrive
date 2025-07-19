<?php

namespace Skeylup\LaravelPipedrive\Data;

use Carbon\Carbon;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;

class PipedrivePersonData extends BasePipedriveData
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $first_name = null,
        public ?string $last_name = null,
        public ?array $phone = null,
        public ?array $email = null,
        public ?int $owner_id = null,
        public ?int $org_id = null,
        
        // Counters
        public int $open_deals_count = 0,
        public int $related_open_deals_count = 0,
        public int $closed_deals_count = 0,
        public int $related_closed_deals_count = 0,
        public int $won_deals_count = 0,
        public int $related_won_deals_count = 0,
        public int $lost_deals_count = 0,
        public int $related_lost_deals_count = 0,
        public int $activities_count = 0,
        public int $done_activities_count = 0,
        public int $undone_activities_count = 0,
        public int $files_count = 0,
        public int $notes_count = 0,
        public int $followers_count = 0,
        public int $email_messages_count = 0,
        
        // Visibility and status
        public ?string $visible_to = null,
        public ?array $label = null,
        public ?array $label_ids = null,
        
        // Dates
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
        
        // Additional fields
        public ?int $picture_id = null,
        public ?string $im = null,
        public ?string $job_title = null,
        public ?string $department = null, // Ce champ peut être manquant !
        public ?string $language = null,
        public ?string $marketing_status = null,
        
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
