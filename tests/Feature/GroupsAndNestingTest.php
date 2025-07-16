<?php

declare(strict_types=1);

use Grazulex\LaravelFlowpipe\Flowpipe;

it('can define and use step groups', function () {
    // Clear any existing groups
    Flowpipe::clearGroups();

    // Define a group
    Flowpipe::group('test-group', [
        fn ($data, $next) => $next(mb_strtoupper($data)),
        fn ($data, $next) => $next($data.'!'),
    ]);

    // Use the group
    $result = Flowpipe::make()
        ->send('hello')
        ->useGroup('test-group')
        ->thenReturn();

    expect($result)->toBe('HELLO!');
});

it('can use nested flows', function () {
    $result = Flowpipe::make()
        ->send('hello')
        ->nested([
            fn ($data, $next) => $next(mb_strtoupper($data)),
            fn ($data, $next) => $next($data.'!'),
        ])
        ->through([
            fn ($data, $next) => $next($data.' world'),
        ])
        ->thenReturn();

    expect($result)->toBe('HELLO! world');
});

it('can combine groups and nested flows', function () {
    // Clear any existing groups
    Flowpipe::clearGroups();

    // Define a group
    Flowpipe::group('transform', [
        fn ($data, $next) => $next(mb_strtoupper($data)),
    ]);

    $result = Flowpipe::make()
        ->send('hello')
        ->useGroup('transform')
        ->nested([
            fn ($data, $next) => $next($data.'!'),
        ])
        ->through([
            fn ($data, $next) => $next($data.' world'),
        ])
        ->thenReturn();

    expect($result)->toBe('HELLO! world');
});

it('can reference groups by name in through method', function () {
    // Clear any existing groups
    Flowpipe::clearGroups();

    // Define a group
    Flowpipe::group('test-group', [
        fn ($data, $next) => $next(mb_strtoupper($data)),
        fn ($data, $next) => $next($data.'!'),
    ]);

    // Use the group by name in through
    $result = Flowpipe::make()
        ->send('hello')
        ->through([
            'test-group',
            fn ($data, $next) => $next($data.' world'),
        ])
        ->thenReturn();

    expect($result)->toBe('HELLO! world');
});

it('throws exception for non-existent group', function () {
    // Clear any existing groups
    Flowpipe::clearGroups();

    expect(function () {
        Flowpipe::make()
            ->send('hello')
            ->useGroup('non-existent-group')
            ->thenReturn();
    })->toThrow(InvalidArgumentException::class);
});

it('can get group registry information', function () {
    // Clear any existing groups
    Flowpipe::clearGroups();

    // Define some groups
    Flowpipe::group('group1', [
        fn ($data, $next) => $next($data),
    ]);

    Flowpipe::group('group2', [
        fn ($data, $next) => $next($data),
    ]);

    expect(Flowpipe::hasGroup('group1'))->toBeTrue();
    expect(Flowpipe::hasGroup('group2'))->toBeTrue();
    expect(Flowpipe::hasGroup('non-existent'))->toBeFalse();

    $groups = Flowpipe::getGroups();
    expect($groups)->toHaveCount(2);
    expect($groups)->toHaveKeys(['group1', 'group2']);
});
