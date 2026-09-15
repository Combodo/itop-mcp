<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;
    
    public function __construct(string $environment, string $debug)
    {
        if (!defined('TOOL_PREFIX')) {
            // Prevent redefinition when running unit tests
            define('TOOL_PREFIX', $_ENV['APP_TOOLS_PREFIX']);
        }
        parent::__construct($environment, $debug); 
    }
}
