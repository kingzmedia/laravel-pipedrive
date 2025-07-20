<?php

namespace Skeylup\LaravelPipedrive\Data;

use Carbon\Carbon;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;

class PipedriveStageData extends BasePipedriveData
{
    public function __construct(
        public int $id,
        public string $name,
        public int $pipeline_id,
        public int $order_nr = 0,
        public bool $active = true,
        public ?int $deal_probability = null,
        public bool $rotten_flag = false,
        public ?int $rotten_days = null,

        // Additional fields
        public ?string $pipeline_name = null,
        public ?string $pipeline_deal_probability = null,

        // Base class properties - Note: Stages use 'active' instead of 'active_flag'
        #[MapInputName('add_time')]
        #[WithCast(DateTimeInterfaceCast::class, format: 'Y-m-d H:i:s')]
        public ?Carbon $pipedrive_add_time = null,

        #[MapInputName('update_time')]
        #[WithCast(DateTimeInterfaceCast::class, format: 'Y-m-d H:i:s')]
        public ?Carbon $pipedrive_update_time = null,

        // Override active_flag for stages
        public bool $active_flag = true,
    ) {}
}
