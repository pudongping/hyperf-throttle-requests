<?php
/**
 *
 *
 * Created by PhpStorm
 * User: Alex
 * Date: 2023-06-18 03:47
 */
declare(strict_types=1);

namespace Pudongping\HyperfThrottleRequests;

use Pudongping\HyperfThrottleRequests\Storage\StorageInterface;
use Pudongping\HyperfThrottleRequests\Storage\RedisStorage;
use Pudongping\HyperfThrottleRequests\Aspect\ThrottleRequestsAnnotationAspect;

class ConfigProvider
{

    public function __invoke(): array
    {
        return [
            'dependencies' => [
                StorageInterface::class => RedisStorage::class
            ],
            'annotations' => [
                'scan' => [
                    'paths' => [
                        __DIR__,
                    ],
                ],
            ],
            'commands' => [],
            'listeners' => [],
            'aspects' => [
                ThrottleRequestsAnnotationAspect::class
            ],
            'publish' => [
                [
                    'id' => 'config',
                    'description' => '访问速率限流器配置文件',
                    'source' => __DIR__ . '/../publish/throttle_requests.php',
                    'destination' => BASE_PATH . '/config/autoload/throttle_requests.php',
                ],
            ],
        ];
    }

}