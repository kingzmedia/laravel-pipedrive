<?php

namespace Skeylup\LaravelPipedrive\Data;

use Carbon\Carbon;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;

class PipedriveNoteData extends BasePipedriveData
{
    public function __construct(
        public int $id,
        public ?string $content = null,
        public ?string $subject = null,
        
        // Related entities
        public ?int $deal_id = null,
        public ?int $person_id = null,
        public ?int $org_id = null,
        public ?int $lead_id = null,
        
        // User fields
        public ?int $user_id = null,
        
        // Additional fields
        public bool $pinned_to_deal_flag = false,
        public bool $pinned_to_person_flag = false,
        public bool $pinned_to_organization_flag = false,
        public bool $pinned_to_lead_flag = false,
        
        #[MapInputName('last_update_user_id')]
        public ?int $last_update_user_id = null,
        
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
