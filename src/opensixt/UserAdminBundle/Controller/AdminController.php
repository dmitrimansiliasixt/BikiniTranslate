<?php

namespace opensixt\UserAdminBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;


class AdminController extends Controller
{
    
    public function indexAction()
    {
        $name = "World";
        return $this->render('opensixtUserAdminBundle:Default:index.html.twig', array('name' => $name));
    }
}