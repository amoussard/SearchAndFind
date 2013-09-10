<?php

namespace Drassuom\ImportBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction($name)
    {
        return $this->render('DrassuomImportBundle:Default:index.html.twig', array('name' => $name));
    }
}
