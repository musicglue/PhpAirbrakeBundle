<?php
namespace MusicGlue\Bundle\PhpAirbrakeBundle\Airbrake;

class ConsoleClient extends Client
{
    protected function getOptions($envName, $queue, $container)
    {
        return [
            'environmentName' => $envName,
            'queue'           => $queue,
            'serverData'      => $_SERVER,
            'component'       => 'console',
            'action'          => $_SERVER['argv'][0],
            'projectRoot'     => realpath($container->getParameter('kernel.root_dir').'/..')
        ];
    }
}
