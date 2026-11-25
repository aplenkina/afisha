<?php

declare(strict_types=1);

namespace App\Tests\Telegram;

use App\Telegram\Application\SubscriberStore;
use App\Telegram\Application\TelegramCatalog;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class TelegramWebhookTest extends WebTestCase
{
    public function testStartReturnsKeyboardAndIgnoresExtraTelegramFields(): void
    {
        $client = self::createClient();
        $catalog = $this->createStub(TelegramCatalog::class);
        $catalog->method('availableCityNames')->willReturn([2 => 'Минск']);
        self::getContainer()->set(TelegramCatalog::class, $catalog);
        self::getContainer()->set(SubscriberStore::class, $this->createStub(SubscriberStore::class));

        $client->jsonRequest('POST', '/telegram/webhook', [
            'update_id' => 7,
            'message' => ['chat' => ['type' => 'private', 'id' => 4_000_000_000], 'text' => '/start', 'message_id' => 8],
        ], ['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN' => 'telegram-test-secret']);

        self::assertResponseIsSuccessful();
        self::assertJsonStringEqualsJsonString(
            '{"method":"sendMessage","chat_id":4000000000,"text":"В каком городе искать события?","reply_markup":{"inline_keyboard":[[{"text":"Минск","callback_data":"city:2"}]]}}',
            $client->getResponse()->getContent(),
        );
    }

    public function testCallbackSavesCityAndAcknowledgesTelegram(): void
    {
        $client = self::createClient();
        $catalog = $this->createStub(TelegramCatalog::class);
        $catalog->method('availableCityName')->willReturn('Минск');
        $subscribers = $this->createMock(SubscriberStore::class);
        $subscribers->expects(self::once())->method('selectCity')->with(42, 2);
        self::getContainer()->set(TelegramCatalog::class, $catalog);
        self::getContainer()->set(SubscriberStore::class, $subscribers);

        $client->jsonRequest('POST', '/telegram/webhook', [
            'callback_query' => [
                'from' => ['id' => 42],
                'message' => ['chat' => ['type' => 'private', 'id' => 42]],
                'id' => 'button-id',
                'data' => 'city:2',
            ],
        ], ['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN' => 'telegram-test-secret']);

        self::assertResponseIsSuccessful();
        self::assertJsonStringEqualsJsonString(
            '{"method":"answerCallbackQuery","callback_query_id":"button-id","text":"Город выбран: Минск."}',
            $client->getResponse()->getContent(),
        );
    }

    public function testSecretIsCheckedBeforeMalformedBody(): void
    {
        $client = self::createClient();
        $client->request('POST', '/telegram/webhook', server: ['CONTENT_TYPE' => 'application/json'], content: '{');

        self::assertResponseStatusCodeSame(403);
        self::assertSame('', $client->getResponse()->getContent());
    }

    public function testRejectsInvalidNestedTypeBeforeHandling(): void
    {
        $client = self::createClient();
        $client->jsonRequest('POST', '/telegram/webhook', [
            'message' => ['chat' => ['type' => 'private', 'id' => '42'], 'text' => '/start'],
        ], ['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN' => 'telegram-test-secret']);

        self::assertResponseStatusCodeSame(400);
    }

    public function testCallbackFromAnotherUserIsIgnored(): void
    {
        $client = self::createClient();
        $client->jsonRequest('POST', '/telegram/webhook', [
            'callback_query' => [
                'from' => ['id' => 43],
                'message' => ['chat' => ['type' => 'private', 'id' => 42]],
                'id' => 'button-id',
                'data' => 'city:2',
            ],
        ], ['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN' => 'telegram-test-secret']);

        self::assertResponseIsSuccessful();
        self::assertSame('', $client->getResponse()->getContent());
    }

    public function testRejectsCallbackDataOverTheByteLimit(): void
    {
        $client = self::createClient();
        $client->jsonRequest('POST', '/telegram/webhook', [
            'callback_query' => [
                'from' => ['id' => 42],
                'id' => 'button-id',
                'data' => str_repeat('я', 33),
            ],
        ], ['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN' => 'telegram-test-secret']);

        self::assertResponseStatusCodeSame(400);
    }

    protected static function getKernelClass(): string
    {
        return TelegramTestKernel::class;
    }
}
