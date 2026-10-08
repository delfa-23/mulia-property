<?php

test('returns a successful response', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
});

test('generates https asset urls when the app is served behind a secure proxy', function () {
    config()->set('app.url', 'https://feed-gliding-matriarch.ngrok-free.dev');

    $this->app['url']->forceRootUrl('https://feed-gliding-matriarch.ngrok-free.dev');

    expect(asset('build/assets/app.css'))->toBe('https://feed-gliding-matriarch.ngrok-free.dev/build/assets/app.css');
});
