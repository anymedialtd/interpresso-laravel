<?php

namespace AnyMedia\Interpresso\Services;

class ProcessCapabilities
{
    public function canSpawn(): bool
    {
        $disabled = array_map('trim', explode(',', strtolower($this->disabledFunctions())));
        // Some hosts retain a stub for disabled functions. Both checks are needed.
        return !in_array('proc_open', $disabled, true) && $this->functionExists('proc_open');
    }

    protected function disabledFunctions(): string
    {
        return (string) ini_get('disable_functions');
    }

    protected function functionExists(string $function): bool
    {
        return function_exists($function);
    }
}
