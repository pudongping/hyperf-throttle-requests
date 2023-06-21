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

use Attribute;
use Hyperf\Di\Annotation\AbstractAnnotation;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class ThrottleRequests extends AbstractAnnotation
{

    public function __construct(
        public ?int    $maxAttempts = null,
        public ?int    $decaySeconds = null,
        public ?string $prefix = null,
        public ?string $key = null,
        public mixed   $generateKeyCallable = null,
        public mixed   $tooManyAttemptsCallback = null
    ) {
    }

}