<?php

namespace opensixt\BikiniTranslateBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use opensixt\BikiniTranslateBundle\Entity\Resource;

/**
 * @author uwe.pries@sixt.com
 */
class ResourceFixture extends AbstractFixture implements OrderedFixtureInterface {
    public function load(ObjectManager $manager) {
        $res = new Resource;
        $res->setName('Dummyres');
        $res->setDescription('Just a dummy resource');
        $manager->persist($res);

        $manager->flush();

        $this->addReference('res-dummy', $res);
    }

    public function getOrder() {
        return 3;
    }
}
