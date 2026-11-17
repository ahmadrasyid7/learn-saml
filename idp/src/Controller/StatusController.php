<?php

namespace App\Controller;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class StatusController
{
    public function __construct(
        #[Autowire(env: 'ENTITY_ID')] private readonly string $entityId,
    ) {
    }

    #[Route('/', name: 'status', methods: ['GET'])]
    public function __invoke(): Response
    {
        $lines = [
            'IdP is running',
            'Entity ID: ' . $this->entityId,
            'Own key loaded: ' . (openssl_pkey_get_private('file:///certs/own/idp.key') ? 'yes' : 'NO'),
            'SP cert loaded: ' . (openssl_x509_read('file:///certs/peer.crt') ? 'yes' : 'NO'),
        ];

        return new Response(implode("\n", $lines) . "\n", headers: ['Content-Type' => 'text/plain']);
    }
}
