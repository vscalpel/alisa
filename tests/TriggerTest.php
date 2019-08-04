<?php

namespace isamarin\Alisa;

use PHPUnit\Framework\TestCase;

class TriggerTest extends TestCase
{
    public function testNormal()
    {
        $trigger = new Trigger('test');
        $trigger->linkTokens(['ONE'], ['TWO'], ['THREE']);
        $this->assertEquals('TEST', $trigger->getName());
        $this->assertEquals([['ONE'], ['TWO'], ['THREE']],
            $trigger->getTokens());
        $this->assertEquals(true, $trigger->isValid());
        $this->assertEquals(true, $trigger->isDefault());
        $this->assertEquals(false, $trigger->isMistake());
        $this->assertEquals(false, $trigger->isInit());

    }
}