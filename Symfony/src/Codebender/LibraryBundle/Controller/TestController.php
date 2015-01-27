<?php
/**
 * Created by PhpStorm.
 * User: fpapadopou
 * Date: 1/27/15
 * Time: 3:41 PM
 */

namespace Codebender\LibraryBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

class TestController extends Controller
{

    public function testAction($auth_key)
    {
        if ($auth_key !== $this->container->getParameter('auth_key'))
        {
            return new Response(json_encode(array("success" => false, "message" => "Invalid authorization key.")));
        }

        set_time_limit(0); // make the script execution time unlimited (otherwise the request may time out)

        // change the current Symfony root dir
        chdir($this->get('kernel')->getRootDir()."/../");

        //TODO: replace this with a less horrible way to handle phpunit
        exec("bin/phpunit -c app --stderr 2>&1", $output, $return_val);

        return new Response(json_encode(array("success" => (bool) !$return_val, "message" => implode("<br>", $output))));
    }

}