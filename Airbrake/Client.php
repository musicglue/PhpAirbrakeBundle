<?php
namespace MusicGlue\Bundle\PhpAirbrakeBundle\Airbrake;

use Airbrake\Client as AirbrakeClient;
use Airbrake\Notice;
use Airbrake\Configuration as AirbrakeConfiguration;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The PhpAirbrakeBundle Client Loader.
 *
 * This class assists in the loading of the php-airbrake Client class.
 *
 * @package		Airbrake
 * @author		Drew Butler <hi@MusicGlue.com>
 * @copyright	(c) 2011 Drew Butler
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
class Client extends AirbrakeClient
{
    protected $enabled = false;

    /**
     * @param string $apiKey
     * @param Symfony\Component\DependencyInjection\ContainerInterface $container
     * @param string|null $queue
     * @param string|null $apiEndPoint
     */
    public function __construct($apiKey, $envName, ContainerInterface $container, $queue=null, $apiEndPoint=null)
    {
        if (!$apiKey) {
            return;
        }

        $this->enabled = true;
        $request       = $container->get('request');
        $controller    = 'None';
        $action        = 'None';
        
        $sitename = getenv('REACT_ENV');
        $env = getenv('SYMFONY_ENV');
        $isCheckout = getenv('MUSIC_GLUE_CHECKOUT');
        $sha1 = getenv('MUSICGLUE_COMMIT_SHA1');
        $sha1 = substr($sha1, 0, 6);

        if ($env && $sha1) {
            $envName = $env.'-'.$sha1;
            
            if ($isCheckout) {
                $envName .= '-checkout';
            }
        }

        if ($sa = $request->attributes->get('_controller')) {
            $controllerArray = explode('::', $sa);
            if(sizeof($controllerArray) > 1){
                list($controller, $action) = $controllerArray;
            }
        }

        $postData = array();
        foreach ($request->request->all() as $key => $value) {
            if (!in_array($key, $container->getParameter('php_airbrake.blacklist'))) {
                $postData[$key] = $value;
            }
        }

        $envWhitelist = array_merge(array(
            'SCRIPT_NAME'
        ), $container->getParameter('php_airbrake.env_whitelist'));
        $server = $request->server->all();
        $serverData = $envWhitelist ?
            array_intersect_key($server, array_flip($envWhitelist))
            : $server;

        $serverData['SITE_NAME'] = $sitename ?: $envName;

        $options = array(
            'environmentName' => $envName,
            'queue'           => $queue,
            'serverData'      => $serverData,
            'getData'         => $request->query->all(),
            'postData'        => $postData,
            'sessionData'     => $request->getSession() ? $request->getSession()->all() : null,
            'component'       => $controller,
            'action'          => $action,
            'projectRoot'     => realpath($container->getParameter('kernel.root_dir').'/..'),
        );

        if(!empty($apiEndPoint)){
            $options['apiEndPoint'] = $apiEndPoint;
        }

        parent::__construct(new AirbrakeConfiguration($apiKey, $options));

    }

    /**
     * Notify about the notice.
     *
     * If there is a PHP Resque client given in the configuration, then use that to queue up a job to
     * send this out later. This should help speed up operations.
     *
     * @param Airbrake\Notice $notice
     */
    public function notify(Notice $notice)
    {
        if ($this->enabled) {
            parent::notify($notice);
        }
    }
}
