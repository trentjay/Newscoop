<?php
/**
 * @package Newscoop
 * @copyright 2011 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\Entity;

/**
 */
class UserTest extends \RepositoryTestCase
{
    /** @var Newscoop\Entity\Repository\UserRepository */
    protected $repository;

    public function setUp()
    {
        parent::setUp('Newscoop\Entity\User', 'Newscoop\Entity\UserAttribute', 'Newscoop\Entity\Acl\Role');
        $this->repository = $this->em->getRepository('Newscoop\Entity\User');
    }

    public function testUser()
    {
        $this->assertInstanceOf('Newscoop\Entity\User', new User());
        $this->assertInstanceOf('Newscoop\Entity\Repository\UserRepository', $this->repository);
    }

    public function testSave()
    {
        $user = new User();
        $this->repository->save($user, array(
            'username' => 'foo.bar',
            'password' => 'secret',
            'email' => 'foo@bar.com',
            'first_name' => 'Foo',
            'last_name' => 'Bar',
            'status' => User::STATUS_INACTIVE,
            'attributes' => array(
                'phone' => 123,
            ),
        ));

        $this->em->flush();
        $this->em->clear();

        $users = $this->repository->findAll();
        $this->assertEquals(1, sizeof($users));

        $user = array_shift($users);
        $this->assertEquals(1, $user->getId());
        $this->assertEquals('foo.bar', $user->getUsername());
        $this->assertTrue($user->checkPassword('secret'));
        $this->assertEquals('foo@bar.com', $user->getEmail());
        $this->assertEquals('Foo', $user->getFirstName());
        $this->assertEquals('Bar', $user->getLastName());
        $this->assertEquals(User::STATUS_INACTIVE, $user->getStatus());
        $this->assertFalse($user->isActive());
        $this->assertLessThan(2, time() - $user->getCreated()->getTimestamp());
        $this->assertEquals(123, $user->getAttribute('phone'));
        $this->assertFalse($user->isAdmin());

        // test attribute change
        $user->addAttribute('phone', 1234);
        $this->em->persist($user);
        $this->em->flush();
        $this->assertEquals(1234, $user->getAttribute('phone'));
    }

    public function testSaveMinimal()
    {
        $user = new User();
        $this->repository->save($user, array(
            'username' => 'foobar',
            'email' => 'foo@bar.com',
        ));

        $this->em->flush();

        $this->assertEquals(1, $user->getId());
        $this->assertEquals('foobar', $user->getUsername());
        $this->assertEquals('foo@bar.com', $user->getEmail());
        $this->assertEmpty($user->getFirstName());
        $this->assertEmpty($user->getLastName());
        $this->assertFalse($user->checkPassword(''));
    }

    /**
     * @expectedException InvalidArgumentException username_empty
     */
    public function testSaveUsernameEmpty()
    {
        $user = new User();
        $this->repository->save($user, array(
            'username' => '',
        ));
    }

    public function testSaveTwice()
    {
        $user = new User();
        $this->repository->save($user, array(
            'username' => 'foo',
            'email' => 'foo@bar.com',
            'last_name' => 'Bar',
        ));
        $this->em->flush();

        $this->assertEquals('Bar', $user->getLastName());

        $this->repository->save($user, array());
        $this->em->flush();

        $this->assertEquals('foo', $user->getUsername());
        $this->assertEquals('Bar', $user->getLastName());
    }

    public function testSetAdmin()
    {
        $user = new User();
        $this->assertFalse($user->isAdmin());

        $user->setAdmin(true);
        $this->assertTrue($user->isAdmin());
    }

    public function testSetPassword()
    {
        $user = new User();
        $user->setPassword('test');

        $property = new \ReflectionProperty($user, 'password');
        $property->setAccessible(TRUE);

        $this->assertTrue($user->checkPassword('test'));
        $this->assertFalse($user->checkPassword('test1'));

        $hash = $property->getValue($user);
        $user->setPassword('test');
        $this->assertNotEquals($hash, $property->getValue($user)); // expect different hash for same password

        $property->setValue($user, sha1('test'));
        $this->assertFalse($user->checkPassword('test1'));
        $this->assertEquals(sha1('test'), $property->getValue($user));

        $this->assertTrue($user->checkPassword('test'));
        $this->assertNotEquals(sha1('test'), $property->getValue($user)); // expect password update on check
        $this->assertNotEquals($hash, $property->getValue($user)); // different from old
        $this->assertTrue($user->checkPassword('test'));
        $this->assertFalse($user->checkPassword(sha1('test')));
    }

    /**
     * @expectedException InvalidArgumentException username_conflict
     */
    public function testSaveUsernameConflict()
    {
        $user = new User();
        $this->repository->save($user, array(
            'username' => 'foo',
            'email' => 'foo@bar.com',
        ));
        $this->em->flush();

        $user = new User();
        $this->repository->save($user, array(
            'username' => 'foo',
            'email' => 'foo2@bar.com',
        ));
    }

    /**
     * @expectedException InvalidArgumentException email_empty
     */
    public function testSaveEmailEmpty()
    {
        $user = new User();
        $this->repository->save($user, array(
            'username' => 'foo',
        ));
    }

    /**
     * @expectedException InvalidArgumentException email_conflict
     */
    public function testSaveEmailConflict()
    {
        $user = new User();
        $this->repository->save($user, array(
            'username' => 'foo',
            'email' => 'foo@bar.com',
        ));
        $this->em->flush();

        $user = new User();
        $this->repository->save($user, array(
            'username' => 'foo2',
            'email' => 'foo@bar.com',
        ));
    }

    public function testFindAll()
    {
        $this->assertEmpty($this->repository->findAll());
    }

    public function testGetGroups()
    {
        $user = new User();
        $this->assertEquals(0, count($user->getGroups()));
    }

    public function testSetGetRole()
    {
        $role = $this->getMock('Newscoop\Entity\Acl\Role');
        $role->expects($this->once())
            ->method('getId')
            ->will($this->returnValue(3));

        $user = new User();
        $user->setRole($role);
        $this->assertEquals(3, $user->getRoleId());
    }

    public function testAttributes()
    {
        $user = new User();
        $user->setUsername('test');
        $this->em->persist($user);
        $this->em->flush();

        $this->assertNull($user->getAttribute('city'));
        $this->assertEquals($user, $user->addAttribute('city', 'praha'));
        $this->assertEquals('praha', $user->getAttribute('city'));

        $this->em->persist($user);
        $this->em->flush();
        $this->em->clear();

        $user = array_shift($this->repository->findAll());
        $this->assertEquals('praha', $user->getAttribute('city'));
    }
}
