<?php

namespace AnyMedia\Interpresso\Middleware;

class EncryptCookies extends \Illuminate\Cookie\Middleware\EncryptCookies
{
    // This display preference is written by JavaScript. Keep the exception local
    // to the package middleware; authentication/session cookies stay encrypted.
    /** @var list<string> */
    protected $except = ['interpresso-color-theme'];
}
