<?php

declare(strict_types=1);

namespace App\Tests\Identity;

use App\Identity\Application\CreateUser\CreateUserCommand;
use App\Identity\Application\CreateUser\CreateUserHandler;
use App\Identity\Application\DeleteUser\DeleteUserCommand;
use App\Identity\Application\DeleteUser\DeleteUserHandler;
use App\Identity\Application\GetUser\GetUserHandler;
use App\Identity\Application\GetUser\GetUserQuery;
use App\Identity\Application\ListUsers\ListUsersHandler;
use App\Identity\Application\ListUsers\ListUsersQuery;
use App\Identity\Application\UpdateUser\UpdateUserCommand;
use App\Identity\Application\UpdateUser\UpdateUserHandler;
use App\Identity\Application\UserPasswordHasher;
use App\Identity\Application\UserRepository;
use App\Identity\Application\UserTransaction;
use App\Identity\Application\UserView;
use App\Identity\Domain\InvalidUserCredentials;
use App\Identity\Domain\LastAdminProtected;
use App\Identity\Domain\SelfDeletionForbidden;
use App\Identity\Domain\User;
use App\Identity\Domain\UserEmailTaken;
use App\Identity\Domain\UserRole;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UserManagementTest extends TestCase
{
    public function testCreateHashesPasswordAndReturnsIdAfterTransaction(): void
    {
        $users = $this->createMock(UserRepository::class);
        $users->expects(self::once())->method('existsByEmail')->with('admin@example.test')->willReturn(false);
        $persisted = null;
        $users->expects(self::once())->method('save')->willReturnCallback(static function (User $user) use (&$persisted): void {
            self::assertSame('admin@example.test', $user->getEmail());
            self::assertSame('password-hash', $user->getPasswordHash());
            self::assertTrue($user->isActiveAdmin());
            $persisted = $user;
        });
        $hasher = $this->createMock(UserPasswordHasher::class);
        $hasher->expects(self::once())->method('hash')->with('test-password')->willReturn('password-hash');
        $transaction = $this->createMock(UserTransaction::class);
        $transaction->expects(self::once())->method('run')->willReturnCallback(static function (\Closure $operation) use (&$persisted): User {
            $user = $operation();
            self::assertSame($persisted, $user);
            new \ReflectionProperty(User::class, 'id')->setValue($user, 7);

            return $user;
        });

        self::assertSame(7, new CreateUserHandler($users, $hasher, $transaction)(
            new CreateUserCommand(' Admin@Example.test ', 'test-password', UserRole::Admin),
        ));
    }

    public function testCreateRejectsDuplicateEmail(): void
    {
        $users = $this->createMock(UserRepository::class);
        $users->expects(self::once())->method('existsByEmail')->with('admin@example.test')->willReturn(true);
        $users->expects(self::never())->method('save');
        $handler = new CreateUserHandler($users, $this->hasher(), $this->transaction());

        $this->expectException(UserEmailTaken::class);
        $handler(new CreateUserCommand('admin@example.test', 'test-password', UserRole::Admin));
    }

    #[DataProvider('invalidCredentials')]
    public function testCreateValidatesCredentialsBeforeHashingOrWriting(string $email, string $password): void
    {
        $hasher = $this->createMock(UserPasswordHasher::class);
        $hasher->expects(self::never())->method('hash');
        $transaction = $this->createMock(UserTransaction::class);
        $transaction->expects(self::never())->method('run');
        $handler = new CreateUserHandler($this->createStub(UserRepository::class), $hasher, $transaction);

        $this->expectException(InvalidUserCredentials::class);
        $handler(new CreateUserCommand($email, $password, UserRole::Editor));
    }

    public static function invalidCredentials(): iterable
    {
        yield ['invalid-address', 'test-password'];
        yield ['admin@example.test', 'short'];
        yield ['admin@example.test', "valid-length\0"];
    }

    public function testUpdatePreservesPasswordWhenFieldIsEmpty(): void
    {
        $user = self::account(UserRole::Editor);
        $users = $this->createMock(UserRepository::class);
        $users->method('findById')->willReturn($user);
        $users->expects(self::once())->method('existsByEmail')->with('new@example.test', 7)->willReturn(false);
        $users->expects(self::never())->method('countActiveAdmins');
        $hasher = $this->createMock(UserPasswordHasher::class);
        $hasher->expects(self::never())->method('hash');

        new UpdateUserHandler($users, $hasher, $this->transaction())(
            new UpdateUserCommand(7, 'New@example.test', UserRole::Moderator, false, ''),
        );

        self::assertSame('old-hash', $user->getPasswordHash());
        self::assertSame('new@example.test', $user->getEmail());
        self::assertSame(UserRole::Moderator, $user->getRole());
        self::assertFalse($user->isActive());
    }

    public function testUpdateCannotDemoteTheLastActiveAdministrator(): void
    {
        $user = self::account(UserRole::Admin);
        $users = $this->createStub(UserRepository::class);
        $users->method('findById')->willReturn($user);
        $users->method('countActiveAdmins')->willReturn(1);

        $this->expectException(LastAdminProtected::class);
        try {
            new UpdateUserHandler($users, $this->hasher(), $this->transaction())(
                new UpdateUserCommand(7, 'admin@example.test', UserRole::Editor, true),
            );
        } finally {
            self::assertTrue($user->isActiveAdmin());
        }
    }

    public function testUpdateSetsNewPasswordWhenAnotherActiveAdministratorRemains(): void
    {
        $user = self::account(UserRole::Admin);
        $users = $this->createStub(UserRepository::class);
        $users->method('findById')->willReturn($user);
        $users->method('countActiveAdmins')->willReturn(2);

        new UpdateUserHandler($users, $this->hasher(), $this->transaction())(
            new UpdateUserCommand(7, 'admin@example.test', UserRole::Admin, false, 'test-password'),
        );

        self::assertFalse($user->isActive());
        self::assertSame('new-hash', $user->getPasswordHash());
    }

    public function testDeleteRejectsSelfBeforeWriting(): void
    {
        $transaction = $this->createMock(UserTransaction::class);
        $transaction->expects(self::never())->method('run');
        $handler = new DeleteUserHandler($this->createStub(UserRepository::class), $transaction);

        $this->expectException(SelfDeletionForbidden::class);
        $handler(new DeleteUserCommand(7, 7));
    }

    public function testDeleteRejectsLastActiveAdministrator(): void
    {
        $users = $this->createMock(UserRepository::class);
        $users->method('findById')->willReturn(self::account(UserRole::Admin));
        $users->method('countActiveAdmins')->willReturn(1);
        $users->expects(self::never())->method('delete');

        $this->expectException(LastAdminProtected::class);
        new DeleteUserHandler($users, $this->transaction())(new DeleteUserCommand(7, 8));
    }

    public function testDeleteRemovesOtherUsers(): void
    {
        $user = self::account(UserRole::Editor);
        $users = $this->createMock(UserRepository::class);
        $users->method('findById')->willReturn($user);
        $users->expects(self::never())->method('countActiveAdmins');
        $users->expects(self::once())->method('delete')->with(self::identicalTo($user));

        new DeleteUserHandler($users, $this->transaction())(new DeleteUserCommand(7, 8));
    }

    public function testUserQueriesReturnTheSameViewWithoutCredentials(): void
    {
        $user = self::account(UserRole::Editor);
        $users = $this->createStub(UserRepository::class);
        $users->method('findById')->willReturn($user);
        $users->method('findAll')->willReturn([$user]);
        $item = new GetUserHandler($users)(new GetUserQuery(7))->user;
        $list = new ListUsersHandler($users)(new ListUsersQuery())->users;

        self::assertEquals(new UserView(7, 'admin@example.test', UserRole::Editor, true), $item);
        self::assertEquals([$item], $list);
    }

    private static function account(UserRole $role): User
    {
        $user = User::create('admin@example.test', 'old-hash', $role);
        new \ReflectionProperty(User::class, 'id')->setValue($user, 7);

        return $user;
    }

    private function hasher(): UserPasswordHasher
    {
        $hasher = $this->createStub(UserPasswordHasher::class);
        $hasher->method('hash')->willReturn('new-hash');

        return $hasher;
    }

    private function transaction(): UserTransaction
    {
        $transaction = $this->createStub(UserTransaction::class);
        $transaction->method('run')->willReturnCallback(static fn (\Closure $operation): mixed => $operation());

        return $transaction;
    }
}
