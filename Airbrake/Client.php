<?php
namespace MusicGlue\Bundle\PhpAirbrakeBundle\Airbrake;

use Airbrake\Client as AirbrakeClient;
use Airbrake\Notice;
use Airbrake\Configuration as AirbrakeConfiguration;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;

class Client extends AirbrakeClient
{
    protected $enabled = false;

    /**
     * @param string $apiKey
     * @param Symfony\Component\DependencyInjection\ContainerInterface $container
     * @param string|null $queue
     * @param string|null $apiEndPoint
     * @param bool|null $enabled
     */
    public function __construct($apiKey, $envName, ContainerInterface $container, $queue=null, $apiEndPoint=null, $enabled=true)
    {
        if (!$apiKey) {
            return;
        }

        $env = getenv('SYMFONY_ENV');
        $sha1 = getenv('MUSICGLUE_COMMIT_SHA1');
        $sha1 = substr($sha1, 0, 6);

        if ($env && $sha1) {
            $envName = $env.'-'.$sha1;
        }

        $this->enabled = $enabled;
        $options = $this->getOptions($envName, $queue, $container);

        // Filter POST
        if (isset($options['postData'])) {
            $postData = array();
            foreach ($options['postData'] as $key => $value) {
                if (!in_array($key, $container->getParameter('php_airbrake.blacklist'))) {
                    $postData[$key] = $value;
                }
            }

            $options['postData'] = $postData;
        }

        // Filter SERVER
        if (isset($options['serverData']) && $container->getParameter('php_airbrake.env_whitelist')) {
            $envWhitelist = array_merge(
                ['SCRIPT_NAME', 'X_SITE_NAME'],
                $container->getParameter('php_airbrake.env_whitelist')
            );
            $options['serverData'] = array_intersect_key(
                array_change_key_case($options['serverData'], CASE_UPPER),
                array_change_key_case(array_flip($envWhitelist), CASE_UPPER)
            );
        }

        if(!empty($apiEndPoint)){
            $options['apiEndPoint'] = $apiEndPoint;
        }

        parent::__construct(new AirbrakeConfiguration($apiKey, $options));

    }

    public function notify(Notice $notice)
    {
        if ($this->enabled) {
            parent::notify($notice);
        }
    }

    public function getEnv()
    {
        if ($this->configuration) {
            return $this->configuration->get('environmentName');
        }
    }

    public function setEnv($envName)
    {
        if ($this->configuration) {
            $this->configuration->set('environmentName', $envName);
        }
    }

    protected function getOptions($envName, $queue, $container)
    {
        $request = $container->get('request');

        $controller = 'None';
        $action = 'None';
        if ($sa = $request->attributes->get('_controller')) {
            $controllerArray = explode('::', $sa);
            if (sizeof($controllerArray) > 1) {
                list($controller, $action) = $controllerArray;
            }
        }

        $request->server->set('X_SITE_NAME', getenv('REACT_ENV') ?: $envName);

        return [
            'environmentName' => $envName,
            'queue'           => $queue,
            'serverData'      => $request->server->all(),
            'getData'         => $request->query->all(),
            'postData'        => $request->request->all(),
            'sessionData'     => $request->getSession() ? $request->getSession()->all() : null,
            'component'       => $controller,
            'action'          => $action,
            'projectRoot'     => realpath($container->getParameter('kernel.root_dir').'/..'),
        ];
    }
}
