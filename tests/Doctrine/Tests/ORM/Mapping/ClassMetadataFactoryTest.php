<?php
/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Doctrine\Tests\ORM\Mapping;

use Doctrine\Tests\Mocks\MetadataDriverMock;
use Doctrine\Tests\Mocks\DatabasePlatformMock;
use Doctrine\ORM\Mapping\ClassMetadata;

require_once __DIR__ . '/../../TestInit.php';

/**
 * Description of ClassMetadataFactoryTest
 *
 * @author robo
 */
class ClassMetadataFactoryTest extends \Doctrine\Tests\OrmTestCase {

    public function testGetMetadataForSingleClass() {
        $mockPlatform = new DatabasePlatformMock();
        $mockDriver = new MetadataDriverMock();

        // Self-made metadata
        $cm1 = new ClassMetadata('Doctrine\Tests\ORM\Mapping\TestEntity1');
        // Add a mapped field
        $cm1->mapField(array('fieldName' => 'name', 'type' => 'varchar'));
        // and a mapped association
        $cm1->mapOneToOne(array('fieldName' => 'other', 'targetEntity' => 'Other', 'mappedBy' => 'this'));
        // and an id generator type
        $cm1->setIdGeneratorType('auto');

        // SUT
        $cmf = new ClassMetadataFactoryTestSubject($mockDriver, $mockPlatform);
        $cmf->setMetadataForClass('Doctrine\Tests\ORM\Mapping\TestEntity1', $cm1);

        // Prechecks
        $this->assertEquals(array(), $cm1->getParentClasses());
        $this->assertEquals('none', $cm1->getInheritanceType());
        $this->assertTrue($cm1->hasField('name'));
        $this->assertEquals(1, count($cm1->getAssociationMappings()));
        $this->assertEquals('auto', $cm1->getIdGeneratorType());

        // Go
        $cm1 = $cmf->getMetadataFor('Doctrine\Tests\ORM\Mapping\TestEntity1');

        $this->assertEquals(array(), $cm1->getParentClasses());
        $this->assertTrue($cm1->hasField('name'));
        // The default fallback for id generation is the table strategy
        $this->assertEquals('table', $cm1->getIdGeneratorType());
    }

    public function testGetMetadataForClassInHierarchy() {
        $mockPlatform = new DatabasePlatformMock();
        $mockPlatform->setPrefersIdentityColumns(true);
        $mockDriver = new MetadataDriverMock();

        // Self-made metadata
        $cm1 = new ClassMetadata('Doctrine\Tests\ORM\Mapping\TestEntity1');
        $cm1->setInheritanceType('singleTable');
        // Add a mapped field
        $cm1->mapField(array('fieldName' => 'name', 'type' => 'varchar'));
        // and a mapped association
        $cm1->mapOneToOne(array('fieldName' => 'other', 'targetEntity' => 'Other', 'mappedBy' => 'this'));
        // and an id generator type
        $cm1->setIdGeneratorType('auto');

        $cm2 = new ClassMetadata('Doctrine\Tests\ORM\Mapping\TestEntity2');
        $cm3 = new ClassMetadata('Doctrine\Tests\ORM\Mapping\TestEntity3');

        $cmf = new ClassMetadataFactoryTestSubject($mockDriver, $mockPlatform);
        // Set self-made metadata
        $cmf->setMetadataForClass('Doctrine\Tests\ORM\Mapping\TestEntity1', $cm1);
        $cmf->setMetadataForClass('Doctrine\Tests\ORM\Mapping\TestEntity2', $cm2);
        $cmf->setMetadataForClass('Doctrine\Tests\ORM\Mapping\TestEntity3', $cm3);

        // Prechecks
        $this->assertEquals(array(), $cm1->getParentClasses());
        $this->assertEquals(array(), $cm2->getParentClasses());
        $this->assertEquals(array(), $cm3->getParentClasses());
        $this->assertEquals('none', $cm2->getInheritanceType());
        $this->assertEquals('none', $cm3->getInheritanceType());
        $this->assertFalse($cm2->hasField('name'));
        $this->assertFalse($cm3->hasField('name'));
        $this->assertEquals(1, count($cm1->getAssociationMappings()));
        $this->assertEquals(0, count($cm2->getAssociationMappings()));
        $this->assertEquals(0, count($cm3->getAssociationMappings()));
        $this->assertEquals('none', $cm2->getIdGeneratorType());
        $this->assertEquals('none', $cm3->getIdGeneratorType());

        // Go
        $cm3 = $cmf->getMetadataFor('Doctrine\Tests\ORM\Mapping\TestEntity3');

        // Metadata gathering should start at the root of the hierarchy, from there on downwards
        $this->assertEquals(array('Doctrine\Tests\ORM\Mapping\TestEntity1', 'Doctrine\Tests\ORM\Mapping\TestEntity2', 'Doctrine\Tests\ORM\Mapping\TestEntity3'), $cmf->getRequestedClasses());
        // Parent classes should be assigned by factory
        $this->assertEquals(array('Doctrine\Tests\ORM\Mapping\TestEntity2', 'Doctrine\Tests\ORM\Mapping\TestEntity1'), $cm3->getParentClasses());
        $this->assertEquals('Doctrine\Tests\ORM\Mapping\TestEntity1', $cm3->getRootClassName());
        $this->assertEquals('Doctrine\Tests\ORM\Mapping\TestEntity1', $cm2->getRootClassName());
        $this->assertEquals('Doctrine\Tests\ORM\Mapping\TestEntity1', $cm1->getRootClassName());
        // Inheritance type should be inherited to Entity2
        $this->assertEquals('singleTable', $cm2->getInheritanceType());
        $this->assertEquals('singleTable', $cm3->getInheritanceType());
        // Field mappings should be inherited
        $this->assertTrue($cm2->hasField('name'));
        $this->assertTrue($cm3->hasField('name'));
        // Association mappings should be inherited
        $this->assertEquals(1, count($cm2->getAssociationMappings()));
        $this->assertEquals(1, count($cm3->getAssociationMappings()));
        $this->assertTrue($cm2->hasAssociation('other'));
        $this->assertTrue($cm3->hasAssociation('other'));
        // Id generator 'auto' should have been resolved to 'identity' as preferred by our
        // mock platform (see above). And it should be inherited.
        $this->assertEquals('identity', $cm1->getIdGeneratorType());
        $this->assertEquals('identity', $cm2->getIdGeneratorType());
        $this->assertEquals('identity', $cm3->getIdGeneratorType());
    }
}

/* Test subject class with overriden factory method for mocking purposes */
class ClassMetadataFactoryTestSubject extends \Doctrine\ORM\Mapping\ClassMetadataFactory {
    private $_mockMetadata = array();
    private $_requestedClasses = array();
    /** @override */
    protected function _newClassMetadataInstance($className) {
        $this->_requestedClasses[] = $className;
        if ( ! isset($this->_mockMetadata[$className])) {
            throw new InvalidArgumentException("No mock metadata found for class $className.");
        }
        return $this->_mockMetadata[$className];
    }
    public function setMetadataForClass($className, $metadata) {
        $this->_mockMetadata[$className] = $metadata;
    }
    public function getRequestedClasses() { return $this->_requestedClasses; }
}

/* Test classes */

class TestEntity1 {
    protected $name;
    protected $other;
}
class TestEntity2 extends TestEntity1 {}
class TestEntity3 extends TestEntity2 {}
