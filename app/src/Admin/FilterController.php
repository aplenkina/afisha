<?php

namespace App\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

#[AdminRoute('/filter', name: 'filter')]
class FilterController extends AbstractController
{
    #[AdminRoute('/', name: 'index')]
    public function show(): Response
    {
        return $this->redirectToRoute('admin_event_index');
    }

    #[AdminRoute('/', name: 'new')]
    public function new(): Response
    {
        return $this->show();
    }
}
