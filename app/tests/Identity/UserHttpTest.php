<?php

declare(strict_types=1);

namespace App\Tests\Identity;

use App\Identity\Application\UserRepository;
use App\Identity\Application\UserTransaction;
use App\Identity\Domain\User;
use App\Identity\Domain\UserRole;
use App\Identity\Infrastructure\Security\AuthenticatedUser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

final class UserHttpTest extends WebTestCase
{
    public function testLoginFormAuthenticatesAndOpensUserList(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $hasher = self::getContainer()->get(PasswordHasherFactoryInterface::class)->getPasswordHasher(AuthenticatedUser::class);
        $admin = self::admin($hasher->hash('test-password'));
        $users = $this->createStub(UserRepository::class);
        $users->method('findByEmail')->willReturn($admin);
        $users->method('findById')->willReturn($admin);
        $users->method('findAll')->willReturn([$admin]);
        self::getContainer()->set(UserRepository::class, $users);

        $client->request('GET', '/admin/user');
        self::assertResponseRedirects('/admin/login');
        $form = $client->followRedirect()->filter('form')->form([
            '_username' => 'admin@example.test',
            '_password' => 'test-password',
        ]);

        $client->submit($form);
        self::assertResponseRedirects('/admin/user');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.admin-list', 'admin@example.test');
    }

    public function testRejectsInvalidFormBeforeEditing(): void
    {
        $client = self::createClient();
        $admin = self::admin();
        $this->setAccount($admin);
        $client->loginUser(AuthenticatedUser::fromUser($admin), 'identity');

        $client->request('POST', '/admin/user/save', [
            'id' => '1',
            'email' => 'admin@example.test',
            'role' => 'ROLE_UNKNOWN',
            'active' => '1',
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testSelfDeletionRedirectsWithExistingError(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $admin = self::admin();
        $this->setAccount($admin);
        $client->loginUser(AuthenticatedUser::fromUser($admin), 'identity');

        $client->request('DELETE', '/admin/user/1');
        self::assertResponseRedirects('/admin/user');
        $client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'Нельзя удалить самого себя');
    }

    public function testEditingWithBlankPasswordKeepsExistingHash(): void
    {
        $client = self::createClient();
        $admin = self::admin();
        $this->setAccount($admin);
        $transaction = $this->createStub(UserTransaction::class);
        $transaction->method('run')->willReturnCallback(static fn (\Closure $operation): mixed => $operation());
        self::getContainer()->set(UserTransaction::class, $transaction);
        $client->loginUser(AuthenticatedUser::fromUser($admin), 'identity');

        $client->request('POST', '/admin/user/save', [
            'id' => '1',
            'email' => 'updated@example.test',
            'role' => UserRole::Admin->value,
            'active' => '1',
            'password' => '',
        ]);

        self::assertResponseRedirects('/admin/user');
        self::assertSame('updated@example.test', $admin->getEmail());
        self::assertSame('old-hash', $admin->getPasswordHash());
    }

    public function testCreateFormAcceptsAnEmptyIdAndStoresAHashedPassword(): void
    {
        $client = self::createClient();
        $admin = self::admin();
        $users = $this->createMock(UserRepository::class);
        $users->method('findById')->willReturn($admin);
        $users->expects(self::once())->method('save')->willReturnCallback(static function (User $user): void {
            self::assertSame('editor@example.test', $user->getEmail());
            self::assertSame(UserRole::Editor, $user->getRole());
            self::assertTrue(password_verify('new-password', $user->getPasswordHash()));
            new \ReflectionProperty(User::class, 'id')->setValue($user, 2);
        });
        self::getContainer()->set(UserRepository::class, $users);
        $transaction = $this->createStub(UserTransaction::class);
        $transaction->method('run')->willReturnCallback(static fn (\Closure $operation): mixed => $operation());
        self::getContainer()->set(UserTransaction::class, $transaction);
        $client->loginUser(AuthenticatedUser::fromUser($admin), 'identity');

        $client->request('POST', '/admin/user/save', [
            'id' => '',
            'email' => 'editor@example.test',
            'role' => UserRole::Editor->value,
            'password' => 'new-password',
        ]);

        self::assertResponseRedirects('/admin/user');
    }

    public function testEditorCannotOpenUserAdministration(): void
    {
        $client = self::createClient();
        $editor = self::admin();
        $editor->update('editor@example.test', UserRole::Editor, true);
        $this->setAccount($editor);
        $client->loginUser(AuthenticatedUser::fromUser($editor), 'identity');

        $client->request('GET', '/admin/user');

        self::assertResponseStatusCodeSame(403);
    }

    public function testDisabledAccountLosesExistingSession(): void
    {
        $client = self::createClient();
        $admin = self::admin();
        $this->setAccount($admin);
        $client->loginUser(AuthenticatedUser::fromUser($admin), 'identity');
        $admin->update($admin->getEmail(), UserRole::Admin, false);

        $client->request('GET', '/admin/user');
        self::assertResponseRedirects('/admin/login');
    }

    private static function admin(string $hash = 'old-hash'): User
    {
        $user = User::create('admin@example.test', $hash, UserRole::Admin);
        new \ReflectionProperty(User::class, 'id')->setValue($user, 1);

        return $user;
    }

    private function setAccount(User $account): void
    {
        $users = $this->createStub(UserRepository::class);
        $users->method('findById')->willReturn($account);
        $users->method('findAll')->willReturn([$account]);
        self::getContainer()->set(UserRepository::class, $users);
    }
}
