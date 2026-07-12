<?php

namespace Tests\Feature\Public;

use Tests\TestCase;

class RootRouteOkTest extends TestCase
{
    public function test_root_route_returns_ok(): void
    {
        $this->get('/')->assertOk();
    }
}
