<?php

namespace KrakenTide\Dependencies;

use KrakenTide\App;

abstract class AbstrctDependency
{
    protected App $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }
}
