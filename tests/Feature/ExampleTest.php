<?php

it('returns a successful response', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});

it('describes course ideas as exploratory rather than validated matches', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Programs to Explore')
        ->assertSee('does not establish a validated')
        ->assertDontSee('verified CHED')
        ->assertDontSee('TESDA university course benchmarks')
        ->assertDontSee('statistically likely to thrive');
});
