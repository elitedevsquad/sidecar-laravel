<?php

namespace EliteDevSquad\SidecarLaravel\Traits;

use EliteDevSquad\SidecarLaravel\FakeClock;

trait WithFakeClock
{
    public function setFakeClock(): void
    {
        FakeClock::applyFromSession();
    }
}
