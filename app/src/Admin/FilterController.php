<?php

namespace App\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;

#[AdminRoute('/filter', name: 'filter')]
class FilterController extends AbstractController
{
    public function __construct(
        private MessageBusInterface $messageBus,
    ) {
    }

    #[AdminRoute('/', name: 'index')]
    public function show(): Response
    {
    }

    #[AdminRoute('/', name: 'new')]
    public function new(): Response
    {
    }
}
