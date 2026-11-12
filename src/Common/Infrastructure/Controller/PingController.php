<?php

namespace App\Common\Infrastructure\Controller;

use Aljerom\SymfonyBoilerplate\CQRS\QueryBus;
use App\Common\Application\Message\Query\PingQuery;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

final class PingController extends AbstractController
{
    public function __construct(
        private QueryBus $queryBus,
    ) {
    }

    public function ping(PingQuery $query): Response
    {
        return $this->json($this->queryBus->query($query));
    }
}
