<?php

namespace Keggermont\LaravelPipedrive\Data;

use Carbon\Carbon;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;

abstract class BasePipedriveData extends Data
{
    public function __construct(
        public int $id,
        
        #[MapInputName('add_time')]
        #[WithCast(DateTimeInterfaceCast::class, format: 'Y-m-d H:i:s')]
        public ?Carbon $pipedrive_add_time = null,
        
        #[MapInputName('update_time')]
        #[WithCast(DateTimeInterfaceCast::class, format: 'Y-m-d H:i:s')]
        public ?Carbon $pipedrive_update_time = null,
        
        public bool $active_flag = true,
    ) {}

    /**
     * Transform to array for database storage
     */
    public function toDatabase(): array
    {
        $data = $this->toArray();
        
        // Rename id to pipedrive_id for database storage
        $data['pipedrive_id'] = $data['id'];
        unset($data['id']);
        
        // Convert Carbon instances to strings for database
        if ($this->pipedrive_add_time) {
            $data['pipedrive_add_time'] = $this->pipedrive_add_time->format('Y-m-d H:i:s');
        }
        
        if ($this->pipedrive_update_time) {
            $data['pipedrive_update_time'] = $this->pipedrive_update_time->format('Y-m-d H:i:s');
        }
        
        return $data;
    }

    /**
     * Filter out null values and handle invalid dates
     */
    public static function fromPipedriveApi(array $data): static
    {
        // Handle invalid dates (1970-01-01 00:00:00)
        foreach (['add_time', 'update_time'] as $timeField) {
            if (isset($data[$timeField])) {
                try {
                    $parsed = Carbon::parse($data[$timeField]);
                    if ($parsed->year <= 1970) {
                        $data[$timeField] = null;
                    }
                } catch (\Exception $e) {
                    $data[$timeField] = null;
                }
            }
        }
        
        return static::from($data);
    }
}
