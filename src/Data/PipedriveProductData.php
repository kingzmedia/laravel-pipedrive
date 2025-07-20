<?php

namespace Skeylup\LaravelPipedrive\Data;

use Carbon\Carbon;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;

class PipedriveProductData extends BasePipedriveData
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $code = null,
        public ?string $description = null,
        public ?string $unit = null,
        public ?float $tax = null,
        public ?string $category = null,

        // Pricing
        public ?array $prices = null,

        // User fields
        public ?int $owner_id = null,

        // Counters
        public int $deals_count = 0,
        public int $files_count = 0,
        public int $followers_count = 0,

        // Additional fields
        public ?string $visible_to = null,
        public ?int $first_char = null,

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
