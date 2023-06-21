# hyperf-throttle-requests

> 适配 [hyperf](https://hyperf.wiki/) 框架的请求频率限流器。功能类似于 [laravel](https://laravel.com/) 框架的 [throttle](https://laravel.com/docs/7.x/middleware) 中间件。

## 运行环境

- php >= 8.0
- composer
- hyperf ~3.0.0

## 安装

```shell
composer require pudongping/hyperf-throttle-requests -vvv
```

## 配置

### 发布配置

在你自己的项目根目录下，执行以下命令

```shell
php bin/hyperf.php vendor:publish pudongping/hyperf-throttle-requests
```

### 配置说明

| 配置 | 默认值 | 说明                                                                                                 |
|---| --- |----------------------------------------------------------------------------------------------------|
| storage | Pudongping\HyperfThrottleRequests\Storage\RedisStorage::class | 数据存储驱动                                                                                             |
| maxAttempts | 60 | 在指定时间内允许的最大请求次数                                                                                    |
| decaySeconds | 60 | 单位时间（单位：s）                                                                                         |
| prefix | '' | 计数器 key 前缀，不填时，默认为：`throttle:`                                                                     |
| key | '' | 具体的计数器的 key （一般只有在某些特定场景下才会使用，比如假设访问多个不同的路由时，均累加一个计数器）                                             | 
| generateKeyCallable | [] | 生成计数器 key 的方法（默认以当前请求路径加当前客户端 IP 地址作为 key）                                                         | 
| tooManyAttemptsCallback | [] | 当触发到最大请求次数时的回调方法（默认会抛出 `Pudongping\HyperfThrottleRequests\Exception\ThrottleRequestsException` 异常） |


## 使用

该组件提供了以下 3 种调用方式：

### 第一种：使用注解 `Pudongping\HyperfThrottleRequests\Annotation\ThrottleRequests`

该组件提供 `Pudongping\HyperfThrottleRequests\Annotation\ThrottleRequests` 注解，作用于类、类方法。  
配置作用优先级为：类方法上的注解配置 > 类上的注解配置 > `config/autoload/hyperf-throttle-requests.php` > 注解默认配置

> 注意：只有使用注解调用时，才会使用 `config/autoload/hyperf-throttle-requests.php` 配置文件中的配置项。

#### 使用注解 `Pudongping\HyperfThrottleRequests\Annotation\ThrottleRequests` 作用于类上，示例：

```php

<?php
/**
 *
 *
 * Created by PhpStorm
 * User: Alex
 * Date: 2023-06-21 11:36
 */
declare(strict_types=1);

namespace App\Controller;

use Hyperf\HttpServer\Annotation\AutoController;
use Pudongping\HyperfThrottleRequests\Annotation\ThrottleRequests;

#[AutoController(prefix: "throttle-requests")]
#[ThrottleRequests]
class ThrottleRequestsController
{

    public function t1()
    {
        return [
            'name' => 'alex'
        ];
    }

    public function t2()
    {
        return [
            'name' => 'harry'
        ];
    }

}

```

当提供 `key` 参数，且 `key` 参数的值为一个标量（不会变化的值）时，则该限流器同时作用于含有等值 `key` 上。举个例子来说：在以下代码中
`Pudongping\HyperfThrottleRequests\Annotation\ThrottleRequests` 注解作用于类上，也就意味着当访问 `/throttle-requests/t1` 路由
和 `/throttle-requests/t2` 路由时，共享相同的配置信息，由于此时的 `key` 参数的值为一个标量，也就意味着此时的现象是：在 15 秒内，当访问
`/throttle-requests/t1` 路由和 `/throttle-requests/t2` 路由时，总共只允许访问 5 次。

```php

<?php
declare(strict_types=1);

namespace App\Controller;

use Hyperf\HttpServer\Annotation\AutoController;
use Pudongping\HyperfThrottleRequests\Annotation\ThrottleRequests;

#[AutoController(prefix: "throttle-requests")]
#[ThrottleRequests(key: "test-throttle", maxAttempts: 5, decaySeconds: 15, prefix: "TR:")]
class ThrottleRequestsController
{

    public function t1()
    {
        return [
            'name' => 'alex'
        ];
    }

    public function t2()
    {
        return [
            'name' => 'harry'
        ];
    }

}

```

#### 使用注解 `Pudongping\HyperfThrottleRequests\Annotation\ThrottleRequests` 作用于类方法上，示例：

> 以下示例代码和以上示例代码，均为同样的效果。

```php

<?php
declare(strict_types=1);

namespace App\Controller;

use Hyperf\HttpServer\Annotation\AutoController;
use Pudongping\HyperfThrottleRequests\Annotation\ThrottleRequests;

#[AutoController(prefix: "throttle-requests")]
class ThrottleRequestsController
{

    #[ThrottleRequests(key: "test-throttle", maxAttempts: 5, decaySeconds: 15, prefix: "TR:")]
    public function t1()
    {
        return [
            'name' => 'alex'
        ];
    }

    #[ThrottleRequests(key: "test-throttle", maxAttempts: 5, decaySeconds: 15, prefix: "TR:")]
    public function t2()
    {
        return [
            'name' => 'harry'
        ];
    }

}

```

### 第二种：使用 `throttle_requests(string $rateLimits = '30,60', string $prefix = '', string $key = '', mixed $generateKeyCallable = [], mixed $tooManyAttemptsCallback = [])` 助手函数

```php

<?php
declare(strict_types=1);

namespace App\Controller;

use Hyperf\HttpServer\Annotation\AutoController;

#[AutoController(prefix: "throttle-requests")]
class ThrottleRequestsController
{

    public function t1()
    {
        throttle_requests(rateLimits: "5,15");
        return [
            'name' => 'alex'
        ];
    }

}

```

### 第三种：直接调用 `Pudongping\HyperfThrottleRequests\Handler\ThrottleRequestsHandler@handle()` 方法

```php

<?php
declare(strict_types=1);

namespace App\Controller;

use Hyperf\HttpServer\Annotation\AutoController;
use Pudongping\HyperfThrottleRequests\Handler\ThrottleRequestsHandler;
use Hyperf\Context\ApplicationContext;

#[AutoController(prefix: "throttle-requests")]
class ThrottleRequestsController
{

    public function t2()
    {
        ApplicationContext::getContainer()->get(ThrottleRequestsHandler::class)->handle(5, 15);
        return [
            'name' => 'harry'
        ];
    }

}

```

## 关于计数器的 key

本质上，当传入的 `key` 参数不为空字符串时，则以传入的 `key` 为主。当 `key` 为空字符串，但是 `generateKeyCallable` 为一个可调用的回调函数时，
则以回调函数的返回值作为计数器的 key。否则默认为 `sha1(当前路由地址路径 . '|' . 当前客户端 IP 地址)` 作为 key。

> 其实质来说，`generateKeyCallable` 回调函数就是去生成 `key` 参数的值，这是为了方便使用者根据自己的需求动态的去生成计数器的键名。比如说：可能
> 当用户登录之后，会加上 user_id 作为计数器的 key。

使用自定义 key 示例：

`App\Controller\ThrottleRequestsController.php` 文件中

```php

<?php
declare(strict_types=1);

namespace App\Controller;

use Hyperf\HttpServer\Annotation\AutoController;
use Pudongping\HyperfThrottleRequests\Handler\ThrottleRequestsHandler;
use Hyperf\Context\ApplicationContext;
use Pudongping\HyperfThrottleRequests\Annotation\ThrottleRequests;
use App\Helper\ThrottleRequestsHelper;
use Hyperf\HttpServer\Contract\RequestInterface;

#[AutoController(prefix: "throttle-requests")]
class ThrottleRequestsController
{

    public function __construct(protected RequestInterface $request)
    {

    }

    #[ThrottleRequests(generateKeyCallable: [ThrottleRequestsHelper::class, "generateKeyCallable"])]
    public function t1()
    {
        return [
            'name' => 'alex'
        ];
    }

    public function t2()
    {
        ApplicationContext::getContainer()
            ->get(ThrottleRequestsHandler::class)
            ->handle(
                5,
                15,
                generateKeyCallable: [$this, 'generateKeyCallable']
            );

        return [
            'name' => 'harry'
        ];
    }

    public function generateKeyCallable()
    {
        return 'alex_' . $this->request->url();
    }

}

```

## 触发访问频率限制

当限流被触发时，默认会抛出 `Pudongping\HyperfThrottleRequests\Exception\ThrottleRequestsException` 异常，可以通过捕获异常
或者配置 `tooManyAttemptsCallback` 限流回调处理。例如：

`App\Controller\ThrottleRequestsController.php` 文件中

```php

<?php
declare(strict_types=1);

namespace App\Controller;

use Hyperf\HttpServer\Annotation\AutoController;
use Pudongping\HyperfThrottleRequests\Handler\ThrottleRequestsHandler;
use Hyperf\Context\ApplicationContext;
use Pudongping\HyperfThrottleRequests\Annotation\ThrottleRequests;
use App\Helper\ThrottleRequestsHelper;

#[AutoController(prefix: "throttle-requests")]
class ThrottleRequestsController
{

    #[ThrottleRequests(tooManyAttemptsCallback: [ThrottleRequestsHelper::class, 'tooManyAttemptsCallback'])]
    public function t1()
    {
        return [
            'name' => 'alex'
        ];
    }

    public function t2()
    {
        ApplicationContext::getContainer()
            ->get(ThrottleRequestsHandler::class)
            ->handle(
                5,
                15,
                tooManyAttemptsCallback: function () {
                    var_dump('请求过于频繁');
                    throw new \RuntimeException('请求过于频繁', 429);
                }
            );

        return [
            'name' => 'harry'
        ];
    }

}

```

`App\Helper\ThrottleRequestsHelper.php` 文件中

```php

<?php
declare(strict_types=1);

namespace App\Helper;

use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Context\ApplicationContext;

class ThrottleRequestsHelper
{

    public function __construct(protected RequestInterface $request)
    {

    }

    public static function generateKeyCallable()
    {
        $request = ApplicationContext::getContainer()->get(RequestInterface::class);
        return $request->getUri()->getPath();
    }

    public static function tooManyAttemptsCallback()
    {
        var_dump('Too Many Attempts.');
        throw new \RuntimeException('请求过于频繁', 429);
    }

}

```