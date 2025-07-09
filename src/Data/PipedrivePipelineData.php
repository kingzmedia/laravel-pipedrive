<?php

namespace Keggermont\LaravelPipedrive\Data;

use Carbon\Carbon;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;

class PipedrivePipelineData extends BasePipedriveData
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $url_title = null,
        public int $order_nr = 0,
        public bool $active = true,
        public bool $deal_probability = true,
        
        // Additional fields
        public ?bool $selected = null,
        
        // Base class properties - Note: Pipelines use 'active' instead of 'active_flag'
        #[MapInputName('add_time')]
        #[WithCast(DateTimeInterfaceCast::class, format: 'Y-m-d H:i:s')]
        public ?Carbon $pipedrive_add_time = null,
        
        #[MapInputName('update_time')]
        #[WithCast(DateTimeInterfaceCast::class, format: 'Y-m-d H:i:s')]
        public ?Carbon $pipedrive_update_time = null,
        
        // Override active_flag for pipelines
        public bool $active_flag = true,
    ) {}
}
