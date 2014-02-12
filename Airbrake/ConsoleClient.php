<?php
namespace MusicGlue\Bundle\PhpAirbrakeBundle\Airbrake;

class ConsoleClient extends Client
{
    protected function getOptions($envName, $queue, $container)
    {
        return [
            'environmentName' => $envName,
            'queue'           => $queue,
            'component'       => 'console',
            'action'          => 'none',
            'projectRoot'     => realpath($container->getParameter('kernel.root_dir').'/..')
        ];
    }

    public function setCommand($name)
    {
        $this->configuration->_action = $name;
    }
}
