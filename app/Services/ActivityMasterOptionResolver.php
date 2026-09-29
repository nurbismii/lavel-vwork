<?php

namespace App\Services;

use App\Models\ActivityMasterOption;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ActivityMasterOptionResolver
{
    public function resolve(string $type, string $input, User $actor): ActivityMasterOption
    {
        $label = Str::of($input)->squish()->toString();
        $normalizedKey = $this->normalizedKey($label);

        if ($normalizedKey === '') {
            throw ValidationException::withMessages([
                $type => 'Pilihan harus memiliki minimal satu karakter huruf atau angka.',
            ]);
        }

        $existing = ActivityMasterOption::query()
            ->where('type', $type)
            ->get()
            ->first(fn (ActivityMasterOption $option) => $option->normalized_key === $normalizedKey
                || $this->normalizedKey($option->value) === $normalizedKey);

        if ($existing) {
            if (! $existing->is_active) {
                throw ValidationException::withMessages([$type => 'Pilihan tersebut sedang dinonaktifkan.']);
            }

            return $existing;
        }

        $value = $type === ActivityMasterOption::TYPE_WORK_TYPE
            ? Str::limit(Str::slug($label), 100, '')
            : $label;

        return ActivityMasterOption::query()->firstOrCreate(
            ['type' => $type, 'normalized_key' => $normalizedKey],
            ['value' => $value, 'label' => $label, 'is_active' => true, 'created_by' => $actor->id],
        );
    }

    public function normalizedKey(string $value): string
    {
        return Str::of(Str::ascii($value))
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->trim()
            ->toString();
    }
}
