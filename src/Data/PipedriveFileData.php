<?php

namespace Keggermont\LaravelPipedrive\Data;

use Carbon\Carbon;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;

class PipedriveFileData extends BasePipedriveData
{
    public function __construct(
        public int $id,
        public ?string $name = null,
        public ?string $file_name = null,
        public ?int $file_size = null,
        public ?string $file_type = null,
        public ?string $url = null,
        public ?string $remote_location = null,
        public ?string $remote_id = null,
        public ?string $cid = null,
        public ?string $s3_bucket = null,
        public ?string $mail_message_id = null,
        public ?string $mail_template_id = null,
        
        // Related entities
        public ?int $deal_id = null,
        public ?int $person_id = null,
        public ?int $org_id = null,
        public ?int $product_id = null,
        public ?int $activity_id = null,
        public ?int $note_id = null,
        public ?int $log_id = null,
        
        // User fields
        public ?int $user_id = null,
        
        // Additional fields
        public ?string $description = null,
        public bool $inline_flag = false,
        
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
