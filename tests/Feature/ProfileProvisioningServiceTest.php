<?php

namespace Tests\Feature;

use App\Services\Perfect10\ProfileProvisioningService;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ProfileProvisioningServiceTest extends TestCase
{
    private const AUTH_ID =
        '11111111-1111-4111-8111-111111111111';

    private const PROFILE_ID =
        '22222222-2222-4222-8222-222222222222';

    private function mockDatabaseResult(?object $result): void
    {
        $connection = Mockery::mock(Connection::class);

        $connection->shouldReceive('selectOne')
            ->once()
            ->withArgs(function (
                string $sql,
                array $bindings
            ): bool {
                return str_contains(
                    $sql,
                    'public.provision_elsewhere_profile'
                ) &&
                    str_contains($sql, 'CAST(? AS uuid)') &&
                    str_contains($sql, 'AS profile_id') &&
                    $bindings === [
                        self::AUTH_ID,
                        'test_player',
                    ];
            })
            ->andReturn($result);

        DB::shouldReceive('connection')
            ->once()
            ->with('pgsql')
            ->andReturn($connection);
    }

    public function test_returns_profile_id_after_successful_provisioning(): void
    {
        $this->mockDatabaseResult((object) [
            'profile_id' => self::PROFILE_ID,
        ]);

        $result = app(ProfileProvisioningService::class)
            ->provision(self::AUTH_ID, 'test_player');

        $this->assertSame(self::PROFILE_ID, $result);
    }

    public function test_rejects_missing_database_result(): void
    {
        $this->mockDatabaseResult(null);

        $this->expectException(RuntimeException::class);

        $this->expectExceptionMessage(
            'Perfect10 profile provisioning returned no profile ID.'
        );

        app(ProfileProvisioningService::class)
            ->provision(self::AUTH_ID, 'test_player');
    }

    public function test_rejects_empty_profile_id(): void
    {
        $this->mockDatabaseResult((object) [
            'profile_id' => '',
        ]);

        $this->expectException(RuntimeException::class);

        $this->expectExceptionMessage(
            'Perfect10 profile provisioning returned no profile ID.'
        );

        app(ProfileProvisioningService::class)
            ->provision(self::AUTH_ID, 'test_player');
    }

    public function test_database_failure_is_not_silenced(): void
    {
        $connection = Mockery::mock(Connection::class);

        $connection->shouldReceive('selectOne')
            ->once()
            ->withArgs(function (
                string $sql,
                array $bindings
            ): bool {
                return str_contains(
                    $sql,
                    'public.provision_elsewhere_profile'
                ) &&
                    $bindings === [
                        self::AUTH_ID,
                        'test_player',
                    ];
            })
            ->andThrow(
                new RuntimeException('Database unavailable')
            );

        DB::shouldReceive('connection')
            ->once()
            ->with('pgsql')
            ->andReturn($connection);

        $this->expectException(RuntimeException::class);

        $this->expectExceptionMessage('Database unavailable');

        app(ProfileProvisioningService::class)
            ->provision(self::AUTH_ID, 'test_player');
    }
}
