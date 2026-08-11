<?php

declare(strict_types=1);

namespace App\Http\Requests\Member;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The complete weekly schedule (docs: SyncListingHours is a full replacement,
 * not a patch). `days` may list any subset of 0..6 — an omitted day is
 * treated as closed by the action, not left unchanged.
 */
class ListingHoursRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ownership decided by ListingPolicy in the controller
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'days' => ['present', 'array', 'max:7'],
            'days.*.day_of_week' => ['required', 'integer', 'between:0,6'],
            'days.*.is_closed' => ['nullable', 'boolean'],
            'days.*.opens_at' => ['nullable', 'date_format:H:i'],
            'days.*.closes_at' => ['nullable', 'date_format:H:i'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $days = (array) $this->input('days', []);

            $seen = [];
            foreach ($days as $i => $day) {
                $dow = $day['day_of_week'] ?? null;
                if ($dow !== null && isset($seen[$dow])) {
                    $validator->errors()->add("days.{$i}.day_of_week", 'Всеки ден от седмицата може да се зададе само веднъж.');
                }
                if ($dow !== null) {
                    $seen[$dow] = true;
                }

                $isClosed = (bool) ($day['is_closed'] ?? false);
                $opens = $day['opens_at'] ?? null;
                $closes = $day['closes_at'] ?? null;

                if ($isClosed) {
                    continue;
                }

                if (($opens === null) !== ($closes === null)) {
                    $validator->errors()->add("days.{$i}.closes_at", 'Задайте едновременно начален и краен час, или оставете деня затворен.');

                    continue;
                }

                if ($opens !== null && $closes !== null && $closes <= $opens) {
                    $validator->errors()->add("days.{$i}.closes_at", 'Крайният час трябва да е след началния.');
                }
            }
        });
    }
}
