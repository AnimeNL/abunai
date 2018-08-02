<?php
// Copyright 2017 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

namespace Anime\Services;

class ImportProgramServiceTest extends \PHPUnit\Framework\TestCase {
    // Gives us the `assertException` method to allow testing more than a single exception per
    // test method. This really ought to be part of the core PHPUnit assertion suite.
    use \Anime\Test\AssertException;

    private $storedTimezone;

    protected function setUp() {
        $this->storedTimezone = date_default_timezone_get();
        date_default_timezone_set('Etc/GMT-1');
    }

    protected function tearDown() {
        date_default_timezone_set($this->storedTimezone);
    }

    // Verifies that the frequency of the service can be configured in the configuration options.
    public function testFrequencyOption() {
        $service = new ImportProgramService([
            'destination'   => null,
            'frequency'     => 1337,
            'source'        => null
        ]);

        $this->assertEquals(1337, $service->getFrequencyMinutes());
    }
}
