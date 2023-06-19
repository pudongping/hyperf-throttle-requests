<?php
/**
 *
 *
 * Created by PhpStorm
 * User: Alex
 * Date: 2023-06-18 19:46
 */
declare(strict_types=1);

namespace Pudongping\HyperfThrottleRequests\Aspect;

use Hyperf\Di\Aop\AbstractAspect;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\Contract\ConfigInterface;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Container\ContainerInterface;
use Pudongping\HyperfThrottleRequests\Storage\RedisStorage;
use Pudongping\HyperfThrottleRequests\Storage\StorageInterface;
use Pudongping\HyperfThrottleRequests\Exception\InvalidArgumentException;
use Pudongping\HyperfThrottleRequests\Handler\ThrottleRequestsHandler;
use Pudongping\HyperfThrottleRequests\Annotation\ThrottleRequests;
use function make;

class ThrottleRequestsAnnotationAspect extends AbstractAspect
{

    public $annotations = [
        ThrottleRequests::class
    ];

    /**
     * @var array
     */
    private $annotationProperty;

    /**
     * @var array
     */
    private $config;

    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(
        ConfigInterface $config,
        ContainerInterface $container
    ) {
        $this->annotationProperty = get_object_vars(new ThrottleRequests());
        $this->config = $this->parseConfig($config);
        $this->container = $container;
    }

    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        $annotation = $this->getWeightingAnnotation($this->getAnnotations($proceedingJoinPoint));

        $throttleRequestsHandlerInstance = make(
            ThrottleRequestsHandler::class,
            [$this->container->get(RequestInterface::class), $this->getStorageDriver()]
        );
        $throttleRequestsHandlerInstance->handle(
            $annotation->maxAttempts,
            $annotation->decaySeconds,
            $annotation->prefix,
            $annotation->key,
            $annotation->generateKeyCallable,
            $annotation->tooManyAttemptsCallback
        );

        $result = $proceedingJoinPoint->process();

        return $result;
    }

    /**
     * @param array $annotations
     * @return ThrottleRequests
     */
    public function getWeightingAnnotation(array $annotations): ThrottleRequests
    {
        $property = array_merge($this->annotationProperty, $this->getConfig());

        /*** @var null|ThrottleRequests $annotation */
        foreach ($annotations as $annotation) {
            if (! $annotation) {
                continue;
            }

            $property = array_merge($property, array_filter(get_object_vars($annotation)));
        }

        return new ThrottleRequests($property);
    }

    /**
     * @param ProceedingJoinPoint $proceedingJoinPoint
     * @return array
     */
    public function getAnnotations(ProceedingJoinPoint $proceedingJoinPoint): array
    {
        $metadata = $proceedingJoinPoint->getAnnotationMetadata();  // 得到注解上的元数据
        return [
            $metadata->class[ThrottleRequests::class] ?? null,  // 类上面的注解元数据
            $metadata->method[ThrottleRequests::class] ?? null  // 类方法上面的注解元数据
        ];
    }

    /**
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @param ConfigInterface $config
     * @return array
     */
    private function parseConfig(ConfigInterface $config): array
    {
        if ($config->has('throttle_requests')) {
            return $config->get('throttle_requests');
        }

        return [];
    }

    /**
     * @return StorageInterface
     * @throws InvalidArgumentException
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function getStorageDriver(): StorageInterface
    {
        $config = $this->getConfig();

        $driverClass = $config['storage'] ?? RedisStorage::class;
        if (! $this->container->has($driverClass)) {
            throw new InvalidArgumentException(sprintf('The storage driver class [%s] is not exists.', $driverClass));
        }

        $instance = $this->container->get($driverClass);
        if (! $instance instanceof StorageInterface) {
            throw new InvalidArgumentException(sprintf('The storage driver class [%s] is invalid.', $driverClass));
        }

        return $instance;
    }

}