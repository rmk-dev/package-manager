<?php

namespace Rmk\PackageManager\Exception;

use Psr\SimpleCache\InvalidArgumentException;
use InvalidArgumentException as CoreException;

/**
 * Class InvalidCacheKey
 *
 * @package Rmk\PackageManager\Exception
 */
class InvalidCacheKey extends CoreException implements InvalidArgumentException
{

}
