<?php

declare(strict_types=1);

use App\Support\LikeEscaper;

it('escapes like pattern correctly', function (string $input, string $expected) {
    expect(LikeEscaper::escapeLikePattern($input))->toBe($expected);
})->with([
    ['foo',         'foo'],
    ['100% seguro', '100!% seguro'],
    ['_hidden',     '!_hidden'],
    ['!important',  '!!important'],
    ['50%!_!',      '50!%!!!_!!'],
    ['normal',      'normal'],
]);
