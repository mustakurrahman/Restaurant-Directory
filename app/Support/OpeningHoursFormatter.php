<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Turns the saved opening_hours rows into text for visitors and into Google's openingHoursSpecification.
 * Days are numbered 1 = Monday ... 7 = Sunday, the same as in the admin form.
 */
class OpeningHoursFormatter
{
    public const DAYS = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

    /**
     * All seven days, Monday first. A day without a saved row is "not listed" (we do not guess it is closed).
     *
     * @param  Collection<int, \App\Models\OpeningHour>  $hours
     * @return list<array{number: int, name: string, text: string, closed: bool, listed: bool}>
     */
    public static function week(Collection $hours): array
    {
        $byDay = $hours->keyBy('day_of_week');

        return collect(self::DAYS)->map(function (string $name, int $number) use ($byDay) {
            $row = $byDay->get($number);

            return [
                'number' => $number,
                'name' => $name,
                'closed' => (bool) $row?->is_closed,
                'listed' => $row !== null,
                'text' => match (true) {
                    $row === null => 'Not listed',
                    $row->is_closed => 'Closed',
                    default => self::range($row->opens_at, $row->closes_at),
                },
            ];
        })->values()->all();
    }

    /** Google's format: one entry per open day, with 24-hour times */
    public static function schema(Collection $hours): array
    {
        return $hours
            ->filter(fn ($row) => ! $row->is_closed && isset(self::DAYS[$row->day_of_week]) && filled($row->opens_at) && filled($row->closes_at))
            ->map(fn ($row) => [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => self::DAYS[$row->day_of_week],
                'opens' => substr($row->opens_at, 0, 5), // MySQL gives 11:00:00, SQLite 11:00
                'closes' => substr($row->closes_at, 0, 5),
            ])
            ->values()
            ->all();
    }

    private static function range(?string $opens, ?string $closes): string
    {
        if (blank($opens) || blank($closes)) {
            return 'Not listed';
        }

        return self::time($opens).' – '.self::time($closes);
    }

    private static function time(string $value): string
    {
        $time = \DateTime::createFromFormat('H:i', substr($value, 0, 5));

        return $time ? $time->format('g:i A') : $value;
    }
}
