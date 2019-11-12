<?php

namespace yournamespace\tests;

use Codeception\Test\Unit;
use Craft;
use rias\scout\Scout;
use UnitTester;

class ScoutVariableTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

    /** @test * */
    public function it_can_get_the_plugin_name()
    {
        $scout = new Scout('scout');
        $scout->init();

        $template = '{{ craft.scout.pluginName }}';

        $output = Craft::$app->getView()->renderString($template);

        $this->assertEquals('Scout', $output);
    }
}
