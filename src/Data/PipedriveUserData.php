<?php

namespace Skeylup\LaravelPipedrive\Data;

use Carbon\Carbon;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;

class PipedriveUserData extends BasePipedriveData
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?string $default_currency = null,
        public ?string $locale = null,
        public ?string $lang = null,
        public ?string $phone = null,

        // Role and permissions
        public ?bool $activated = null,
        public ?bool $is_admin = null,
        public ?int $role_id = null,
        public ?string $timezone_name = null,
        public ?string $timezone_offset = null,

        // Additional fields
        public ?int $icon_url = null,
        public ?bool $is_you = null,
        public ?string $last_login = null,
        public ?Carbon $created = null,
        public ?Carbon $modified = null,
        public ?bool $signup_flow_variation = null,
        public ?bool $has_created_company = null,
        public ?bool $access = null,

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
