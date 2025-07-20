<?php

namespace Skeylup\LaravelPipedrive\Data;

use Carbon\Carbon;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;

class PipedriveGoalData extends BasePipedriveData
{
    public function __construct(
        public int $id,
        public ?string $title = null,
        public ?int $owner_id = null,
        public ?string $type = null,
        public ?string $assignee_type = null,
        public ?string $interval = null,
        public ?string $duration_start = null,
        public ?string $duration_end = null,
        public ?float $expected_outcome = null,
        public ?string $currency = null,
        public bool $active = true,

        // Progress tracking
        public ?float $outcome = null,
        public ?float $progress = null,

        // Pipeline specific
        public ?int $pipeline_id = null,
        public ?int $stage_id = null,
        public ?int $activity_type_id = null,

        // Base class properties - Note: Goals use 'active' instead of 'active_flag'
        #[MapInputName('add_time')]
        #[WithCast(DateTimeInterfaceCast::class, format: 'Y-m-d H:i:s')]
        public ?Carbon $pipedrive_add_time = null,

        #[MapInputName('update_time')]
        #[WithCast(DateTimeInterfaceCast::class, format: 'Y-m-d H:i:s')]
        public ?Carbon $pipedrive_update_time = null,

        // Override active_flag for goals
        public bool $active_flag = true,
    ) {}
}
