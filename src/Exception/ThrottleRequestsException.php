<?php
/**
 *
 *
 * Created by PhpStorm
 * User: Alex
 * Date: 2023-06-17 22:34
 */
declare(strict_types=1);

namespace Pudongping\HyperfThrottleRequests\Exception;

use Exception;

class ThrottleRequestsException extends Exception
{

    protected $code = 429;

    protected $message = 'Too Many Attempts.';

}