<?php

namespace Drassuom\LocationBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction($name)
    {
        return $this->render('DrassuomLocationBundle:Default:index.html.twig', array('name' => $name));
    }
}
