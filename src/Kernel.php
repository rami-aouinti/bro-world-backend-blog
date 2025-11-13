<?php

declare(strict_types=1);

namespace App;

use Bro\WorldCoreBundle\Application\Compiler\StopwatchCompilerPass;
use Override;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

/**
 * @package App
 * @author  Rami Aouinti <rami.aouinti@gmail.com>
 */
class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    #[Override]
    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        if ($this->environment === 'dev') {
            $container->addCompilerPass(new StopwatchCompilerPass());
        }
    }
}
