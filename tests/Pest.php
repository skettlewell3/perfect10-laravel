<?php

use Tests\TestCase;

pest()->extend(TestCase::class)
    ->in('Feature');

pest()->beforeEach(function (): void {
    $this->withSession([
        'elsewhere_auth_id' => '11111111-1111-4111-8111-111111111111',
    ]);
})->in('Feature/Perfect10');
