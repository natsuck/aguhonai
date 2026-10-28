<?php

test('maintenance page can be prerendered without the application layout', function () {
    $html = view('errors.503', ['retryAfter' => null])->render();

    expect($html)
        ->toContain('<title>')
        ->toContain('Maintenance in progress')
        ->toContain('We’ll be back soon.')
        ->toContain('Your progress is safe.')
        ->not->toContain('@vite');
});
