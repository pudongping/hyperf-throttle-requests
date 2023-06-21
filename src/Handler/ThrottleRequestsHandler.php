<?php
/**
 * 节流处理
 * 用途：限制访问频率
 * 做法：限制单位时间内访问指定服务/路由的次数（频率）
 *
 * Created by PhpStorm
 * User: Alex
 * Date: 2023-06-17 19:18
 */
declare(strict_types=1);

namespace Pudongping\HyperfThrottleRequests\Handler;

use Carbon\Carbon;
use Hyperf\Context\Context;
use Psr\Http\Message\ResponseInterface;
use Hyperf\HttpServer\Contract\RequestInterface;
use Pudongping\HyperfThrottleRequests\Storage\StorageInterface;
use Pudongping\HyperfThrottleRequests\Exception\ThrottleRequestsException;

class ThrottleRequestsHandler
{

    public function __construct(
        protected RequestInterface $request,
        protected StorageInterface $storage,
        private string             $keyPrefix = 'throttle:',
        private string             $keySuffix = ':timer'
    ) {
    }

    /**
     * 处理访问节流
     *
     * @param int $maxAttempts 在指定时间内允许的最大请求次数
     * @param int $decaySeconds 单位时间（s）
     * @param string $prefix 计数器 key 前缀
     * @param string $key 具体的计数器的 key
     * @param mixed $generateKeyCallable 生成计数 key 的方法
     * @param mixed $tooManyAttemptsCallback 当触发到最大请求次数时回调方法
     * @return void
     * @throws ThrottleRequestsException
     */
    public function handle(
        int    $maxAttempts = 60,
        int    $decaySeconds = 60,
        string $prefix = '',
        string $key = '',
        mixed  $generateKeyCallable = [],
        mixed  $tooManyAttemptsCallback = []
    ): void {
        $keyCounter = $this->keyPrefix($prefix) . $this->resolveRequestSignature($key, $generateKeyCallable);

        $maxAttempts = max(0, $maxAttempts);
        if ($this->tooManyAttempts($keyCounter, $maxAttempts)) {
            $this->resolveTooManyAttempts($keyCounter, $maxAttempts, $tooManyAttemptsCallback);
        }

        $this->hit($keyCounter, max(1, $decaySeconds));

        $this->setHeaders($keyCounter, $maxAttempts);
    }

    /**
     * 判断访问次数是否已经达到了临界值
     *
     * @param string $keyCounter 计数器的 key
     * @param int $maxAttempts 在指定时间内允许的最大请求次数
     * @return bool
     */
    private function tooManyAttempts(string $keyCounter, int $maxAttempts): bool
    {
        $counterNumber = (int)$this->storage->get($keyCounter, 0);

        // 计时器不存在时，计数器则没有存在的意义
        if (! $this->storage->has($this->keyTimer($keyCounter))) {
            $this->storage->forget($keyCounter);
        } else {
            if ($counterNumber >= $maxAttempts) {
                return true;
            }
        }

        return false;
    }

    /**
     * 在指定时间内自增指定键的计数器
     *
     * @param string $keyCounter 计数器的 key
     * @param int $decaySeconds 指定时间（s）
     * @return int 计数器具体增加到多少值
     */
    private function hit(string $keyCounter, int $decaySeconds): int
    {
        // 计时器的有效期时间戳
        $expirationTime = Carbon::now()->addRealSeconds($decaySeconds)->getTimestamp();
        // 计时器
        $this->storage->add($this->keyTimer($keyCounter), (string)$expirationTime, $decaySeconds);

        // 计数器
        $added = $this->storage->add($keyCounter, '0', $decaySeconds);

        $hits = $this->storage->increment($keyCounter);

        if ($added && $hits == 1) {
            // 证明是初始化
            $this->storage->put($keyCounter, '1', $decaySeconds);
        }

        return $hits;
    }

    /**
     * @param string $prefix
     * @return string
     */
    private function keyPrefix(string $prefix): string
    {
        return $prefix ?: $this->keyPrefix;
    }

    /**
     * @param string $keyCounter
     * @return string
     */
    private function keyTimer(string $keyCounter): string
    {
        return $keyCounter . $this->keySuffix;
    }

    /**
     * @param string $key
     * @param mixed $generateKeyCallable
     * @return string
     */
    private function resolveRequestSignature(string $key, mixed $generateKeyCallable): string
    {
        if ($key) return $key;

        if ($generateKeyCallable) {
            return (string)call_user_func($generateKeyCallable);
        }

        $sign = $this->request->url() . '|' . $this->clientIp();
        return sha1($sign);
    }

    /**
     * @return string
     */
    private function clientIp(): string
    {
        return $this->request->getHeaderLine('X-Forwarded-For')
            ?: $this->request->getHeaderLine('X-Real-IP')
            ?: ($this->request->getServerParams()['remote_addr'] ?? '')
            ?: '127.0.0.1';
    }

    /**
     * @param string $keyCounter
     * @param int $maxAttempts
     * @param mixed $tooManyAttemptsCallback
     * @return mixed
     * @throws ThrottleRequestsException
     */
    protected function resolveTooManyAttempts(string $keyCounter, int $maxAttempts, mixed $tooManyAttemptsCallback): mixed
    {
        if ($tooManyAttemptsCallback) {
            return call_user_func($tooManyAttemptsCallback);
        }

        throw $this->buildException($keyCounter, $maxAttempts);
    }

    /**
     * 超过访问次数限制时，构建异常信息
     *
     * @param string $keyCounter 计数器的 key
     * @param int $maxAttempts 在指定时间内允许的最大请求次数
     * @return ThrottleRequestsException
     */
    protected function buildException(string $keyCounter, int $maxAttempts): ThrottleRequestsException
    {
        // 距离允许下一次请求还有多少秒
        $retryAfter = $this->getTimeUntilNextRetry($keyCounter);

        $this->setHeaders($keyCounter, $maxAttempts, $retryAfter);

        // 429 Too Many Requests
        return new ThrottleRequestsException();
    }

    /**
     * 设置返回头数据
     *
     * @param string $keyCounter 计数器的 key
     * @param int $maxAttempts 在指定时间内允许的最大请求次数
     * @param int|null $retryAfter 距离下次重试请求需要等待的时间（s）
     * @return void
     */
    protected function setHeaders(string $keyCounter, int $maxAttempts, ?int $retryAfter = null): void
    {
        // 设置返回头数据
        $headers = $this->getHeaders(
            $maxAttempts,
            $this->calculateRemainingAttempts($keyCounter, $maxAttempts, $retryAfter),  // 计算剩余访问次数
            $retryAfter
        );

        $this->addHeaders($headers);
    }

    /**
     * 获取返回头数据
     *
     * @param int $maxAttempts 在指定时间内允许的最大请求次数
     * @param int $remainingAttempts 在指定时间段内剩下的请求次数
     * @param int|null $retryAfter 距离下次重试请求需要等待的时间（s）
     * @return int[]
     */
    protected function getHeaders(int $maxAttempts, int $remainingAttempts, ?int $retryAfter = null): array
    {
        $headers = [
            'X-RateLimit-Limit' => $maxAttempts,  // 在指定时间内允许的最大请求次数
            'X-RateLimit-Remaining' => $remainingAttempts,  // 在指定时间段内剩下的请求次数
        ];

        if (! is_null($retryAfter)) {  // 只有当用户访问频次超过了最大频次之后才会返回以下两个返回头字段
            $headers['Retry-After'] = $retryAfter;  // 距离下次重试请求需要等待的时间（s）
            $headers['X-RateLimit-Reset'] = Carbon::now()->addRealSeconds($retryAfter)->getTimestamp();  // 距离下次重试请求需要等待的时间戳（s）
        }

        return $headers;
    }

    /**
     * 添加返回头信息
     *
     * @param array $headers
     * @return void
     */
    protected function addHeaders(array $headers = []): void
    {
        $response = Context::get(ResponseInterface::class);

        foreach ($headers as $key => $header) {
            $response = $response->withHeader($key, $header);
        }

        Context::set(ResponseInterface::class, $response);
    }

    /**
     * 计算距离允许下一次请求还有多少秒
     *
     * @param string $keyCounter 计数器的 key
     * @return int
     */
    private function getTimeUntilNextRetry(string $keyCounter): int
    {
        $timer = (int)$this->storage->get($this->keyTimer($keyCounter));
        $nextRetry = $timer - Carbon::now()->getTimestamp();
        return max(0, $nextRetry);
    }

    /**
     * 计算剩余访问次数
     *
     * @param string $keyCounter 计数器的 key
     * @param int $maxAttempts 在指定时间内允许的最大请求次数
     * @param int|null $retryAfter 距离下次重试请求需要等待的时间（s）
     * @return int
     */
    private function calculateRemainingAttempts(string $keyCounter, int $maxAttempts, ?int $retryAfter = null): int
    {
        if (is_null($retryAfter)) {
            $remain = $maxAttempts - (int)$this->storage->get($keyCounter, 0);
            return max(0, $remain);
        }

        return 0;
    }

    /**
     * 清空所有的限流器
     *
     * @param string $prefix
     * @return bool
     */
    public function clear(string $prefix = ''): bool
    {
        return $this->storage->clearPrefix($this->keyPrefix($prefix));
    }

}