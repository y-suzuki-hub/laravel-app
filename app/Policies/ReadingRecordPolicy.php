<?php

namespace App\Policies;

use App\Models\ReadingRecord;
use App\Models\User;

class ReadingRecordPolicy
{
    public function update(User $user, ReadingRecord $record): bool
    {
        return $user->id === $record->user_id;
    }

    public function delete(User $user, ReadingRecord $record): bool
    {
        return $user->id === $record->user_id;
    }
}
