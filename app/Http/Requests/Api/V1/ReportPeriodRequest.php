<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Sale;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The period a report covers: a day, a week (Monday–Sunday) or a calendar month containing `date` (default today).
 */
class ReportPeriodRequest extends FormRequest
{
    /**
     * Reports are for admins only.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Sale::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'period' => ['nullable', Rule::in(['daily', 'weekly', 'monthly'])],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function period(): string
    {
        return $this->validated('period') ?? 'daily';
    }

    /**
     * First day of the period.
     */
    public function from(): CarbonImmutable
    {
        $date = $this->anchor();

        return match ($this->period()) {
            'weekly' => $date->startOfWeek(CarbonImmutable::MONDAY),
            'monthly' => $date->startOfMonth(),
            default => $date,
        };
    }

    /**
     * Last day of the period (inclusive).
     */
    public function to(): CarbonImmutable
    {
        $date = $this->anchor();

        return match ($this->period()) {
            'weekly' => $date->endOfWeek(CarbonImmutable::SUNDAY)->startOfDay(),
            'monthly' => $date->endOfMonth()->startOfDay(),
            default => $date,
        };
    }

    /**
     * Every day of the period as "Y-m-d", oldest first.
     *
     * @return list<string>
     */
    public function days(): array
    {
        $days = [];
        for ($day = $this->from(); $day->lte($this->to()); $day = $day->addDay()) {
            $days[] = $day->toDateString();
        }

        return $days;
    }

    /**
     * The period fields every report starts with.
     *
     * @return array{period: string, date: string, from: string, to: string}
     */
    public function periodFields(): array
    {
        return [
            'period' => $this->period(),
            'date' => $this->anchor()->toDateString(),
            'from' => $this->from()->toDateString(),
            'to' => $this->to()->toDateString(),
        ];
    }

    private function anchor(): CarbonImmutable
    {
        $date = $this->validated('date');

        return $date ? CarbonImmutable::createFromFormat('Y-m-d', $date)->startOfDay() : CarbonImmutable::today();
    }
}
