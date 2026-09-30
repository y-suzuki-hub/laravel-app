<?php

namespace App\Enums;

enum ReadingStatus: string
{
    case Want = 'want';
    case Reading = 'reading';
    case Finished = 'finished';
    case Dropped = 'dropped';

    public function label(): string
    {
        return match ($this) {
            self::Want => '読みたい',
            self::Reading => '読んでいる',
            self::Finished => '読み終わった',
            self::Dropped => '中断',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Want => 'zinc',
            self::Reading => 'blue',
            self::Finished => 'green',
            self::Dropped => 'amber',
        };
    }
}
