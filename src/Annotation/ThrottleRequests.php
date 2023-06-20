<?php
/**
 *
 *
 * Created by PhpStorm
 * User: Alex
 * Date: 2023-06-18 19:03
 */
declare(strict_types=1);

namespace Pudongping\HyperfThrottleRequests\Annotation;

use Hyperf\Di\Annotation\AbstractAnnotation;

/**
 * @Annotation
 * @Target({"CLASS", "METHOD"})
 */
class ThrottleRequests extends AbstractAnnotation
{

    /**
     * @var int
     */
    public $maxAttempts = 60;

    /**
     * @var int
     */
    public $decaySeconds = 60;

    /**
     * @var string
     */
    public $prefix = '';

    /**
     * @var string
     */
    public $key = '';

    /**
     * @var null|callable
     */
    public $generateKeyCallable = [];

    /**
     * @var null|callable
     */
    public $tooManyAttemptsCallback = [];

}