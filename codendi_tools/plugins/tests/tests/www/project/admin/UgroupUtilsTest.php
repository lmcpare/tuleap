<?php
/**
 * Copyright (c) STMicroelectronics, 2004-2011. All rights reserved
 *
 * This file is a part of Codendi.
 *
 * Codendi is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Codendi is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Codendi. If not, see <http://www.gnu.org/licenses/>.
 */
require_once('www/project/admin/ugroup_utils.php');

Mock::generate('UserManager');
Mock::generate('User');
Mock::generate('UGroup');

require_once(dirname(__FILE__) .'/../../../../include/simpletest/mock_functions.php');

class UgroupUtilsTest extends UnitTestCase {

    function setUp() {
        MockFunction::generate('db_query');
        MockFunction::generate('db_fetch_array');
        MockFunction::generate('ugroup_get_user_manager');
    }

    function tearDown() {
        MockFunction::restore('db_query');
        MockFunction::restore('db_fetch_array');
        MockFunction::restore('ugroup_get_user_manager');
    }

    function testUgroupContainProjectAdminsNoUsers() {
        MockFunction::setReturnValue('db_fetch_array', null);
        MockFunction::expectOnce('db_fetch_array');
        $result = ugroup_contain_project_admins(1, '');
        $this->assertEqual(0, $result['admins']);
        $this->assertEqual(0, $result['non_admins']);
    }

    function testUgroupContainProjectAdminsOnlyAdmins() {
        MockFunction::setReturnValueAt(0 ,'db_fetch_array', array('user_id' => 1));
        MockFunction::setReturnValueAt(1 ,'db_fetch_array', array('user_id' => 2));
        MockFunction::setReturnValueAt(2 ,'db_fetch_array', null);
        MockFunction::expectCallCount('db_fetch_array', 3);
        $user = new MockUser();
        $user->setReturnValue('isMember', true);
        $user->expectCallCount('isMeMber', 2);
        $um = new MockUserManager();
        $um->setReturnValue('getUserById', $user);
        MockFunction::setReturnValue('ugroup_get_user_manager', $um);
        $result = ugroup_contain_project_admins(1, '');
        $this->assertEqual(2, $result['admins']);
        $this->assertEqual(0, $result['non_admins']);
    }

    function testUgroupContainProjectAdminsOnlyNonAdmins() {
        MockFunction::setReturnValueAt(0 ,'db_fetch_array', array('user_id' => 1));
        MockFunction::setReturnValueAt(1 ,'db_fetch_array', array('user_id' => 2));
        MockFunction::setReturnValueAt(2 ,'db_fetch_array', null);
        MockFunction::expectCallCount('db_fetch_array', 3);
        $user = new MockUser();
        $user->setReturnValue('isMember', false);
        $user->expectCallCount('isMeMber', 2);
        $um = new MockUserManager();
        $um->setReturnValue('getUserById', $user);
        MockFunction::setReturnValue('ugroup_get_user_manager', $um);
        $result = ugroup_contain_project_admins(1, '');
        $this->assertEqual(0, $result['admins']);
        $this->assertEqual(2, $result['non_admins']);
    }

    function testUgroupContainProjectAdminsMixed() {
        MockFunction::setReturnValueAt(0 ,'db_fetch_array', array('user_id' => 1));
        MockFunction::setReturnValueAt(1 ,'db_fetch_array', array('user_id' => 2));
        MockFunction::setReturnValueAt(2 ,'db_fetch_array', null);
        MockFunction::expectCallCount('db_fetch_array', 3);
        $user = new MockUser();
        $user->setReturnValueAt(0, 'isMember', true);
        $user->setReturnValueAt(1, 'isMember', false);
        $user->expectCallCount('isMeMber', 2);
        $um = new MockUserManager();
        $um->setReturnValue('getUserById', $user);
        MockFunction::setReturnValue('ugroup_get_user_manager', $um);
        $result = ugroup_contain_project_admins(1, '');
        $this->assertEqual(1, $result['admins']);
        $this->assertEqual(1, $result['non_admins']);
    }

    function testUgroupValidateStaticAdminUgroupsNotProjectGroups() {
        $uGroup = new MockUGroup();
        $uGroup->setReturnValue('checkUGroupValidityByGroupId', true, array(1, 1));
        $uGroup->setReturnValue('checkUGroupValidityByGroupId', true, array(1, 2));
        $uGroup->setReturnValue('checkUGroupValidityByGroupId', false, array(1, 3));
        $uGroup->setReturnValue('checkUGroupValidityByGroupId', false, array(1, 4));
        MockFunction::generate('ugroup_get_ugroup');
        MockFunction::setReturnValue('ugroup_get_ugroup', $uGroup);
        MockFunction::generate('ugroup_contain_project_admins');
        MockFunction::setReturnValue('ugroup_contain_project_admins', array('admins' => 1, 'non_admins' => 1));
        MockFunction::expectNever('ugroup_contain_project_admins');
        $validUGroups = array(1, 2);
        $this->assertFalse(ugroup_validate_static_admin_ugroups(1, array(3, 4), $validUGroups));
        $this->assertEqual(array(1, 2), $validUGroups);
        MockFunction::restore('ugroup_contain_project_admins');
    }

    function testUgroupValidateStaticAdminUgroupsContainAdmins() {
        $uGroup = new MockUGroup();
        $uGroup->setReturnValue('checkUGroupValidityByGroupId', true, array(1, 1));
        $uGroup->setReturnValue('checkUGroupValidityByGroupId', true, array(1, 2));
        $uGroup->setReturnValue('checkUGroupValidityByGroupId', true, array(1, 3));
        $uGroup->setReturnValue('checkUGroupValidityByGroupId', true, array(1, 4));
        MockFunction::generate('ugroup_get_ugroup');
        MockFunction::setReturnValue('ugroup_get_ugroup', $uGroup);
        MockFunction::generate('ugroup_contain_project_admins');
        MockFunction::setReturnValue('ugroup_contain_project_admins', array('admins' => 2, 'non_admins' => 3));
        MockFunction::expectCallCount('ugroup_contain_project_admins', 2);
        $validUGroups = array(1, 2);
        $this->assertEqual(6, ugroup_validate_static_admin_ugroups(1, array(3, 4), $validUGroups));
        $this->assertEqual(array(1, 2, 3, 4), $validUGroups);
        MockFunction::restore('ugroup_contain_project_admins');
    }

    function testUgroupValidateStaticAdminUgroupsContainNoAdmins() {
        $uGroup = new MockUGroup();
        $uGroup->setReturnValue('checkUGroupValidityByGroupId', true, array(1, 1));
        $uGroup->setReturnValue('checkUGroupValidityByGroupId', true, array(1, 2));
        $uGroup->setReturnValue('checkUGroupValidityByGroupId', true, array(1, 3));
        $uGroup->setReturnValue('checkUGroupValidityByGroupId', true, array(1, 4));
        MockFunction::generate('ugroup_get_ugroup');
        MockFunction::setReturnValue('ugroup_get_ugroup', $uGroup);
        MockFunction::generate('ugroup_contain_project_admins');
        MockFunction::setReturnValue('ugroup_contain_project_admins', array('admins' => 0, 'non_admins' => 3));
        MockFunction::expectCallCount('ugroup_contain_project_admins', 2);
        $validUGroups = array(1, 2);
        $this->assertEqual(0, ugroup_validate_static_admin_ugroups(1, array(3, 4), $validUGroups));
        $this->assertEqual(array(1, 2), $validUGroups);
        MockFunction::restore('ugroup_contain_project_admins');
    }

    function testUgroupValidateStaticAdminUgroupsMixed() {
        $uGroup = new MockUGroup();
        $uGroup->setReturnValue('checkUGroupValidityByGroupId', true, array(1, 1));
        $uGroup->setReturnValue('checkUGroupValidityByGroupId', true, array(1, 2));
        $uGroup->setReturnValue('checkUGroupValidityByGroupId', true, array(1, 3));
        $uGroup->setReturnValue('checkUGroupValidityByGroupId', true, array(1, 4));
        MockFunction::generate('ugroup_get_ugroup');
        MockFunction::setReturnValue('ugroup_get_ugroup', $uGroup);
        MockFunction::generate('ugroup_contain_project_admins');
        MockFunction::setReturnValueAt(0, 'ugroup_contain_project_admins', array('admins' => 0, 'non_admins' => 2));
        MockFunction::setReturnValueAt(1, 'ugroup_contain_project_admins', array('admins' => 1, 'non_admins' => 3));
        MockFunction::expectCallCount('ugroup_contain_project_admins', 2);
        $validUGroups = array(1, 2);
        $this->assertEqual(3, ugroup_validate_static_admin_ugroups(1, array(3, 4), $validUGroups));
        $this->assertEqual(array(1, 2, 4), $validUGroups);
        MockFunction::restore('ugroup_contain_project_admins');
    }

    function testUgroupValidateDynamicAdminUgroupsContainAdmins() {
        MockFunction::generate('ugroup_contain_project_admins');
        MockFunction::setReturnValue('ugroup_contain_project_admins', array('admins' => 1, 'non_admins' => 2));
        MockFunction::expectCallCount('ugroup_contain_project_admins', 2);
        $validUGroups = array(1, 2);
        $this->assertEqual(4 ,ugroup_validate_dynamic_admin_ugroups(1, array(3, 4), $validUGroups));
        $this->assertEqual(array(1, 2, 3, 4), $validUGroups);
        MockFunction::restore('ugroup_contain_project_admins');
    }

    function testUgroupValidateDynamicAdminUgroupsContainNoAdmins() {
        MockFunction::generate('ugroup_contain_project_admins');
        MockFunction::setReturnValue('ugroup_contain_project_admins', array('admins' => 0, 'non_admins' => 2));
        MockFunction::expectCallCount('ugroup_contain_project_admins', 2);
        $validUGroups = array(1, 2);
        $this->assertEqual(0, ugroup_validate_dynamic_admin_ugroups(1, array(3, 4), $validUGroups));
        $this->assertEqual(array(1, 2), $validUGroups);
        MockFunction::restore('ugroup_contain_project_admins');
    }

    function testUgroupValidateDynamicAdminUgroupsMixed() {
        MockFunction::generate('ugroup_contain_project_admins');
        MockFunction::setReturnValueAt(0, 'ugroup_contain_project_admins', array('admins' => 0, 'non_admins' => 2));
        MockFunction::setReturnValueAt(1, 'ugroup_contain_project_admins', array('admins' => 1, 'non_admins' => 3));
        MockFunction::expectCallCount('ugroup_contain_project_admins', 2);
        $validUGroups = array(1, 2);
        $this->assertEqual(3, ugroup_validate_dynamic_admin_ugroups(1, array(3, 4), $validUGroups));
        $this->assertEqual(array(1, 2, 4), $validUGroups);
        MockFunction::restore('ugroup_contain_project_admins');
    }

    function testUgroupValidateAdminUgroupsNoUgroups() {
        MockFunction::generate('ugroup_contain_project_admins');
        MockFunction::setReturnValue('ugroup_contain_project_admins', array('admins' => 0, 'non_admins' => 2));
        MockFunction::expectCallCount('ugroup_contain_project_admins', 4);
        $ugroups = array(1, 2);
        $this->assertEqual(array('all_selected' => false, 'non_admins' => 0, 'ugroups' => array()), ugroup_validate_admin_ugroups(1, $ugroups));
        MockFunction::restore('ugroup_contain_project_admins');
    }

    function testUgroupValidateAdminUgroupsStaticAllAdmins() {
        MockFunction::generate('ugroup_contain_project_admins');
        MockFunction::setReturnValueAt(0, 'ugroup_contain_project_admins', array('admins' => 1, 'non_admins' => 1));
        MockFunction::setReturnValueAt(1, 'ugroup_contain_project_admins', array('admins' => 1, 'non_admins' => 2));
        MockFunction::setReturnValueAt(2, 'ugroup_contain_project_admins', array('admins' => 0, 'non_admins' => 4));
        MockFunction::setReturnValueAt(3, 'ugroup_contain_project_admins', array('admins' => 0, 'non_admins' => 8));
        MockFunction::expectCallCount('ugroup_contain_project_admins', 4);
        $ugroups = array(1, 2);
        $this->assertEqual(array('all_selected' => true, 'non_admins' => 3, 'ugroups' => array(1, 2)), ugroup_validate_admin_ugroups(1, $ugroups));
        MockFunction::restore('ugroup_contain_project_admins');
    }

    function testUgroupValidateAdminUgroupsStaticMixed() {
        MockFunction::generate('ugroup_contain_project_admins');
        MockFunction::setReturnValueAt(0, 'ugroup_contain_project_admins', array('admins' => 0, 'non_admins' => 1));
        MockFunction::setReturnValueAt(1, 'ugroup_contain_project_admins', array('admins' => 1, 'non_admins' => 2));
        MockFunction::setReturnValueAt(2, 'ugroup_contain_project_admins', array('admins' => 0, 'non_admins' => 4));
        MockFunction::setReturnValueAt(3, 'ugroup_contain_project_admins', array('admins' => 0, 'non_admins' => 8));
        MockFunction::expectCallCount('ugroup_contain_project_admins', 4);
        $ugroups = array(1, 2);
        $this->assertEqual(array('all_selected' => false, 'non_admins' => 2, 'ugroups' => array(2)), ugroup_validate_admin_ugroups(1, $ugroups));
        MockFunction::restore('ugroup_contain_project_admins');
    }

    function testUgroupValidateAdminUgroupsDynamicMixed() {
        MockFunction::generate('ugroup_contain_project_admins');
        MockFunction::setReturnValueAt(0, 'ugroup_contain_project_admins', array('admins' => 0, 'non_admins' => 1));
        MockFunction::setReturnValueAt(1, 'ugroup_contain_project_admins', array('admins' => 0, 'non_admins' => 2));
        MockFunction::setReturnValueAt(2, 'ugroup_contain_project_admins', array('admins' => 1, 'non_admins' => 4));
        MockFunction::setReturnValueAt(3, 'ugroup_contain_project_admins', array('admins' => 0, 'non_admins' => 8));
        MockFunction::expectCallCount('ugroup_contain_project_admins', 4);
        $ugroups = array(1, 2);
        $this->assertEqual(array('all_selected' => false, 'non_admins' => 4, 'ugroups' => array(1)), ugroup_validate_admin_ugroups(1, $ugroups));
        MockFunction::restore('ugroup_contain_project_admins');
    }

    function testUgroupValidateAdminUgroupsDynamicAllAdmins() {
        MockFunction::generate('ugroup_contain_project_admins');
        MockFunction::setReturnValueAt(0, 'ugroup_contain_project_admins', array('admins' => 0, 'non_admins' => 1));
        MockFunction::setReturnValueAt(1, 'ugroup_contain_project_admins', array('admins' => 0, 'non_admins' => 2));
        MockFunction::setReturnValueAt(2, 'ugroup_contain_project_admins', array('admins' => 1, 'non_admins' => 4));
        MockFunction::setReturnValueAt(3, 'ugroup_contain_project_admins', array('admins' => 1, 'non_admins' => 8));
        MockFunction::expectCallCount('ugroup_contain_project_admins', 4);
        $ugroups = array(1, 2);
        $this->assertEqual(array('all_selected' => true, 'non_admins' => 12, 'ugroups' => array(1, 2)), ugroup_validate_admin_ugroups(1, $ugroups));
        MockFunction::restore('ugroup_contain_project_admins');
    }

    function testUgroupValidateAdminUgroupsBothAllAdmins() {
        MockFunction::generate('ugroup_contain_project_admins');
        MockFunction::setReturnValueAt(0, 'ugroup_contain_project_admins', array('admins' => 1, 'non_admins' => 1));
        MockFunction::setReturnValueAt(1, 'ugroup_contain_project_admins', array('admins' => 1, 'non_admins' => 2));
        MockFunction::setReturnValueAt(2, 'ugroup_contain_project_admins', array('admins' => 1, 'non_admins' => 4));
        MockFunction::setReturnValueAt(3, 'ugroup_contain_project_admins', array('admins' => 1, 'non_admins' => 8));
        MockFunction::expectCallCount('ugroup_contain_project_admins', 4);
        $ugroups = array(1, 2);
        $this->assertEqual(array('all_selected' => true, 'non_admins' => 15, 'ugroups' => array(1, 2, 1, 2)), ugroup_validate_admin_ugroups(1, $ugroups));
        MockFunction::restore('ugroup_contain_project_admins');
    }
}
?>