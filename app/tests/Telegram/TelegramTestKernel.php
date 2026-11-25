<?php

declare(strict_types=1);

namespace App\Tests\Telegram;

use App\Telegram\Application\SubscriberStore;
use App\Telegram\Application\TelegramCatalog;
use App\Telegram\Infrastructure\Catalog\CatalogAdapter;
use App\Telegram\Infrastructure\Doctrine\DoctrineSubscriberStore;
use App\Telegram\Infrastructure\Http\TelegramWebhookAuthentication;
use App\Telegram\Infrastructure\Http\TelegramWebhookController;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

final class TelegramTestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
    }

    public function getCacheDir(): string
    {
        return parent::getCacheDir().'/telegram';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'test' => true,
            'serializer' => true,
            'validation' => ['enable_attributes' => true],
            'property_info' => ['with_constructor_extractor' => true],
            'messenger' => ['transports' => ['sync' => 'sync://']],
        ]);

        $services = $container->services();
        $services->defaults()->autowire()->autoconfigure();
        $services->load('App\\Telegram\\', '../../src/Telegram/')
            ->exclude([CatalogAdapter::class, DoctrineSubscriberStore::class]);
        $services->set(TelegramWebhookAuthentication::class)->args(['telegram-test-secret']);
        $services->set(TelegramCatalog::class)->synthetic()->public();
        $services->set(SubscriberStore::class)->synthetic()->public();
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(TelegramWebhookController::class, 'attribute');
        $routes->add('event_page', '/event/{slug}');
        $routes->add('calendar_city_category', '/{city}/{category}');
        $routes->add('calendar_city', '/{city}');
    }
}
