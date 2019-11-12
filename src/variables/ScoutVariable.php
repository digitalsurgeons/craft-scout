<?php

namespace rias\scout\variables;

use rias\scout\Scout;

class ScoutVariable
{
    public function getPluginName()
    {
        return Scout::$plugin->getSettings()->pluginName;
    }
}
