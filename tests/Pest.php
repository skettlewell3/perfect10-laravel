<?php

use App\Services\Auth\ElsewhereMembershipService;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->in('Feature');

pest()->beforeEach(function (): void {
    $this->withSession([
        'elsewhere_auth_id' => '11111111-1111-4111-8111-111111111111',
    ]);

    $membership = Mockery::mock(
        ElsewhereMembershipService::class
    );

    $membership->shouldReceive('verify')
        ->zeroOrMoreTimes()
        ->andReturn(ElsewhereMembershipService::ACTIVE);

    $this->app->instance(
        ElsewhereMembershipService::class,
        $membership
    );
})->in('Feature/Perfect10');
