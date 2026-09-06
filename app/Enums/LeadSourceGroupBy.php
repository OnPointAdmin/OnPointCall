<?php

namespace App\Enums;

enum LeadSourceGroupBy: string
{
    case VenueAndEvent = 'venue_and_event';
    case Venue = 'venue';
    case Event = 'event';

    public function label(): string
    {
        return match ($this) {
            self::VenueAndEvent => 'Venue and event',
            self::Venue => 'Venue',
            self::Event => 'Event',
        };
    }

    public function tableTitle(): string
    {
        return match ($this) {
            self::VenueAndEvent => 'By Venue and Event',
            self::Venue => 'By Venue',
            self::Event => 'By Event',
        };
    }

    public function showsVenue(): bool
    {
        return $this !== self::Event;
    }

    public function showsEvent(): bool
    {
        return $this !== self::Venue;
    }

    public static function coerce(self|string $value): self
    {
        return $value instanceof self ? $value : self::from($value);
    }
}
