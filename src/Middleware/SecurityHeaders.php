<?php

namespace AnyMedia\Interpresso\Middleware;

use Closure;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    private const POLICY = [
        'default-src' => ["'none'"],
        'script-src' => ["'self'"],
        'script-src-attr' => ["'none'"],
        'style-src' => ["'self'"],
        'style-src-attr' => ["'none'"],
        'img-src' => ["'self'", 'data:'],
        'font-src' => ["'self'"],
        'connect-src' => ["'self'"],
        'form-action' => ["'self'"],
        'frame-ancestors' => ["'none'"],
        'base-uri' => ["'none'"],
        'object-src' => ["'none'"],
    ];

    private const EXTENSIBLE_DIRECTIVES = ['script-src', 'style-src', 'img-src', 'font-src', 'connect-src'];

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $enabled = config('interpresso.security_headers.enabled', true);
        if (!is_bool($enabled)) {
            throw new InvalidArgumentException('interpresso.security_headers.enabled must be a boolean.');
        }
        if (!$enabled) {
            return $next($request);
        }

        $policy = $this->policy();
        $response = $next($request);
        $response->headers->set('Content-Security-Policy', $policy);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), fullscreen=()');

        return $response;
    }

    private function policy(): string
    {
        $policy = self::POLICY;
        $extraSources = config('interpresso.security_headers.extra_sources', []);
        if (!is_array($extraSources)) {
            throw new InvalidArgumentException('interpresso.security_headers.extra_sources must be an array.');
        }

        foreach ($extraSources as $directive => $sources) {
            if (!in_array($directive, self::EXTENSIBLE_DIRECTIVES, true) || !is_array($sources) || !array_is_list($sources)) {
                throw new InvalidArgumentException('CSP extra sources require a supported resource directive and a list of explicit HTTP(S) origins.');
            }
            foreach ($sources as $source) {
                // Accept origins only: no wildcard hosts, scheme-only sources,
                // keywords, credentials, paths, or directive/header injection.
                if (!is_string($source)
                    || !preg_match('~\Ahttps?://(?:[a-zA-Z0-9](?:[a-zA-Z0-9.-]*[a-zA-Z0-9])?|\[[0-9a-fA-F:]+\])(?::[0-9]{1,5})?\z~', $source)
                    || filter_var($source, FILTER_VALIDATE_URL) === false) {
                    throw new InvalidArgumentException('CSP extra sources must be explicit HTTP(S) origins without wildcards or paths.');
                }
                $policy[$directive][] = $source;
            }
        }

        $directives = [];
        foreach ($policy as $directive => $sources) {
            $directives[] = $directive . ' ' . implode(' ', array_unique($sources));
        }

        return implode('; ', $directives);
    }
}
