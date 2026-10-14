<?php

declare(strict_types=1);

namespace Funnelion\Http;

/**
 * Run work after the visitor already has the page, so it never adds to
 * their load time — the way to send Client::pageView() from plain PHP.
 *
 *     AfterResponse::defer(fn () => $client->pageViewQuietly($request));
 *
 * At shutdown it flushes the output buffers and hands the response to
 * the visitor (fastcgi_finish_request() under PHP-FPM,
 * litespeed_finish_request() under LiteSpeed), then runs the task. On
 * other SAPIs the task still runs at shutdown; the page is already
 * flushed, but the connection stays open until it finishes, so keep the
 * Config timeout short.
 *
 * Frameworks have their own hook and don't need this: Laravel's defer()
 * or a terminable middleware, Symfony's kernel.terminate. Both already
 * run after the response is sent.
 */
final class AfterResponse
{
    public static function defer(callable $task): void
    {
        register_shutdown_function(static function () use ($task): void {
            while (ob_get_level() > 0) {
                ob_end_flush();
            }
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            } elseif (function_exists('litespeed_finish_request')) {
                litespeed_finish_request();
            }

            try {
                $task();
            } catch (\Throwable) {
                // The visitor is gone; nothing to report it to.
            }
        });
    }
}
