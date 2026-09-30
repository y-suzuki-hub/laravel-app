<?php

use App\Enums\ReadingStatus;

it('has a Japanese label for every status', function (ReadingStatus $status, string $label) {
    expect($status->label())->toBe($label);
})->with([
    [ReadingStatus::Want, '読みたい'],
    [ReadingStatus::Reading, '読んでいる'],
    [ReadingStatus::Finished, '読み終わった'],
    [ReadingStatus::Dropped, '中断'],
]);
