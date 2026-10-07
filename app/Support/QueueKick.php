<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

/**
 * Shared hosting may have no cron or supervisor, so after a request that queued
 * Drive transfers we drain the queue ourselves once the response is delivered.
 * A cache lock keeps one drainer at a time; anything left (a killed request,
 * a retry backoff) is picked up by the next kick or by the scheduler.
 */
class QueueKick
{
    public static function afterResponse(JsonResponse $response): JsonResponse
    {
        if (config('queue.default') === 'sync' || ! config('radiology.drain_queue_after_upload', true)) {
            return $response;
        }

        // Let the browser finish reading the response before the transfer starts.
        $body = (string) $response->getContent();
        $response->headers->set('Content-Length', (string) strlen($body));
        $response->headers->set('Connection', 'close');

        self::drainAfterResponse();

        return $response;
    }

    /** Drain the queue once this request has been answered (at most one drainer at a time). */
    public static function drainAfterResponse(): void
    {
        if (config('queue.default') === 'sync' || ! config('radiology.drain_queue_after_upload', true)) {
            return;
        }

        app()->terminating(static function (): void {
            $lock = Cache::lock('queue-kick', 900);
            if (! $lock->get()) {
                return;
            }
            try {
                ignore_user_abort(true);
                @set_time_limit(0);
                Artisan::call('queue:work', ['--stop-when-empty' => true, '--max-time' => 600, '--tries' => 5, '--timeout' => 1800]);
            } finally {
                $lock->release();
            }
        });

    }
}
