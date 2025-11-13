<?php

namespace Src\DI;

use Psr\Container\NotFoundExceptionInterface;
use Throwable;

class NotFoundException extends \Exception implements NotFoundExceptionInterface
{
}