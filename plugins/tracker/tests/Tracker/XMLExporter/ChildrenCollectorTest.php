<?php
/**
 * Copyright (c) Enalean, 2015. All Rights Reserved.
 *
 * This file is a part of Tuleap.
 *
 * Tuleap is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Tuleap is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Tuleap. If not, see <http://www.gnu.org/licenses/>.
 */
require_once TRACKER_BASE_DIR . '/../tests/bootstrap.php';

class Tracker_XMLExporter_ChildrenCollectorTest extends TuleapTestCase {

    /** @var Tracker_XMLExporter_ChildrenCollectorTest */
    private $collector;

    public function setUp() {
        parent::setUp();
        $this->collector = new Tracker_XMLExporter_ChildrenCollector();
    }

    public function itRaisesAnExceptionWhenTooManyChildrenAreAdded() {
        $this->expectException('Tracker_XMLExporter_TooManyChildrenException');
        for ($i = 0 ; $i <= Tracker_XMLExporter_ChildrenCollector::MAX ; ++$i) {
            $this->collector->addChild($i, 1123);
        }
    }

    public function itDoesNotStackTwiceTheSameChild() {
        $this->collector->addChild(1, 1123);
        $this->collector->addChild(1, 1123);
        $this->assertEqual($this->collector->getAllChildrenIds(), array(1));
    }

    public function itPopsChildren() {
        $this->collector->addChild(1, 1123);
        $this->collector->addChild(2, 1123);

        $this->assertEqual(1, $this->collector->pop());
        $this->assertEqual(2, $this->collector->pop());
        $this->assertEqual(null, $this->collector->pop());
    }

    public function itReturnsAllParents() {
        $this->collector->addChild(1, 1123);
        $this->collector->addChild(2, 1123);
        $this->collector->addChild(3, 147);

        $parents_array = array(1123, 147);
        $this->assertEqual($parents_array, $this->collector->getAllParents());
    }

    public function itReturnsChildrenOfAParent() {
        $parent_id = 1123;
        $this->collector->addChild(1, $parent_id);
        $this->collector->addChild(2, $parent_id);
        $this->collector->addChild(3, 147);

        $children_array = array(1, 2);
        $this->assertEqual($children_array, $this->collector->getChildrenForParent($parent_id));
    }

    public function itReturnsEmptyArrayIfParentNotFound() {
        $parent_id = 1123;
        $this->collector->addChild(1, $parent_id);
        $this->collector->addChild(2, $parent_id);
        $this->collector->addChild(3, 147);

        $children_array = array();
        $this->assertEqual($children_array, $this->collector->getChildrenForParent(666));

    }
}