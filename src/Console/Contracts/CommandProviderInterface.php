<?php

namespace Toybox\Core\Console\Contracts;

use Symfony\Component\Console\Command\Command;

interface CommandProviderInterface
{
    /**
     * @return iterable<Command>
     */
    public function commands(): iterable;
}
