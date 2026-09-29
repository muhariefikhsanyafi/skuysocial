<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\OneSignalExternalId;
use InvalidArgumentException;
use Tests\TestCase;

class OneSignalExternalIdTest extends TestCase
{
    public function test_external_id_uses_the_exact_canonical_internal_user_id_and_excludes_profile_identifiers(): void
    {
        config(['app.key' => 'synthetic-key-one']);

        $user = new User;
        $user->setAttribute('id', 42);
        $user->exists = true;
        $user->setAttribute('username', 'private_handle');
        $user->setAttribute('email', 'private@example.test');

        $externalId = OneSignalExternalId::forUser($user);

        $this->assertSame('skuysocial-user:42', $externalId);
        $this->assertStringNotContainsString('private_handle', $externalId);
        $this->assertStringNotContainsString('private@example.test', $externalId);
    }

    public function test_changing_app_key_does_not_change_external_id(): void
    {
        $user = new User;
        $user->setAttribute('id', 42);
        $user->exists = true;

        config(['app.key' => 'synthetic-key-one']);
        $externalId = OneSignalExternalId::forUser($user);
        config(['app.key' => 'synthetic-key-two']);

        $this->assertSame('skuysocial-user:42', $externalId);
        $this->assertSame($externalId, OneSignalExternalId::forUser($user));
    }

    public function test_external_ids_are_distinct_for_different_stable_user_ids(): void
    {
        config(['app.key' => 'synthetic-key-one']);

        $first = new User;
        $first->setAttribute('id', 1);
        $first->exists = true;
        $second = new User;
        $second->setAttribute('id', 42);
        $second->exists = true;

        $firstExternalId = OneSignalExternalId::forUser($first);
        $secondExternalId = OneSignalExternalId::forUser($second);

        $this->assertSame('skuysocial-user:1', $firstExternalId);
        $this->assertSame('skuysocial-user:42', $secondExternalId);
        $this->assertNotSame($firstExternalId, $secondExternalId);
    }

    public function test_external_id_rejects_unpersisted_users(): void
    {
        $this->expectException(InvalidArgumentException::class);

        OneSignalExternalId::forUser(new User);
    }
}
