<?php

namespace opensixt\BikiniTranslateBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

/**
 * @author Paul Seiffert <paul.seiffert@mayflower.de>
 */
class DefaultController extends Controller
{
    /**
     * @param string $page
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function indexAction($page)
    {
        return $this->render('opensixtBikiniTranslateBundle:Default:index.html.twig', array('page' => $page));
    }
}