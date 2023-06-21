<?php
/**
 *
 *
 * Created by PhpStorm
 * User: Alex
 * Date: 2023-06-17 19:45
 */
declare(strict_types=1);

use Hyperf\Context\ApplicationContext;
use Pudongping\HyperfThrottleRequests\Handler\ThrottleRequestsHandler;

if (! function_exists('throttle_requests')) {
    /**
     * 节流处理
     *
     * 默认为：60 秒内允许访问 30 次
     *
     * @param string $rateLimits
     * @param string $prefix
     * @param string $key
     * @param mixed $generateKeyCallable
     * @param mixed $tooManyAttemptsCallback
     * @return void
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \Pudongping\HyperfThrottleRequests\Exception\ThrottleRequestsException
     */
    function throttle_requests(
        string $rateLimits = '30,60',
        string $prefix = '',
        string $key = '',
        mixed $generateKeyCallable = [],
        mixed $tooManyAttemptsCallback = []
    ): void {
        if (! ApplicationContext::hasContainer()) {
            throw new \RuntimeException('The application context lacks the container.');
        }
        $container = ApplicationContext::getContainer();
        $instance = $container->get(ThrottleRequestsHandler::class);

        $rates = array_map('intval', array_filter(explode(',', $rateLimits)));
        list($maxAttempts, $decaySeconds) = $rates;

        $instance->handle($maxAttempts, $decaySeconds, $prefix, $key, $generateKeyCallable, $tooManyAttemptsCallback);
    }
}