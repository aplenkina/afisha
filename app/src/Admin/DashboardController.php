<?php

namespace App\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private ChartBuilderInterface $chartBuilder,
    ) {
    }

    // ... you'll also need to load some CSS/JavaScript assets to render
    // the charts; this is explained later in the chapter about Design
    public function configureAssets(): Assets
    {
        return Assets::new()
            ->addAssetMapperEntry('app')
            ->addCssFile('styles/admin.css');
    }

    public function index(): Response
    {
        $chart = $this->chartBuilder->createChart(Chart::TYPE_LINE);
        $chart->setData([]);

        return $this->render('admin/my-dashboard.html.twig', [
            'chart' => $chart,
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Афиша РБ')
            ->renderContentMaximized();
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Панель управления', 'fa fa-home');

        yield MenuItem::section('Контент');
        yield MenuItem::linkTo(CategoryController::class, 'Категории', 'fas fa-list')
            ->setAction('index')
            ->setPermission('ROLE_ADMIN');

        yield MenuItem::linkTo(EventController::class, 'События', 'fas fa-calendar')->setAction('index');
        yield MenuItem::linkTo(CityController::class, 'Города', 'fas fa-city')->setAction('index')->setPermission('ROLE_ADMIN');
        yield MenuItem::linkTo(VenueController::class, 'Площадки', 'fas fa-location-dot')->setAction('index')->setPermission('ROLE_MODERATOR');
        yield MenuItem::linkTo(FilterController::class, 'Фильтры', 'fas fa-list')->setAction('index')->setPermission('ROLE_ADMIN');
        yield MenuItem::linkTo(SourceController::class, 'Источники', 'fas fa-list')->setAction('index')->setPermission('ROLE_ADMIN');

        yield MenuItem::section('Системные настройки')->setPermission('ROLE_ADMIN');
        yield MenuItem::linkTo(UserController::class, 'Пользователи', 'fas fa-users')
            ->setAction('index')
            ->setPermission('ROLE_ADMIN');
    }
}
