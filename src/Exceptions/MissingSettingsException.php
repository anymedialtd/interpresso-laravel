<?php

namespace AnyMedia\Interpresso\Exceptions;

class MissingSettingsException extends BaseException
{
    public function __construct()
    {
        $message = 'The Interpresso settings row is missing. Restore it in the configured settings table before running package operations.';
        parent::__construct($message, $message);
    }
}
