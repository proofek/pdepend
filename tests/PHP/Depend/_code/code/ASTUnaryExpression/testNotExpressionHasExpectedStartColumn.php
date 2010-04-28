<?php
function testNotExpressionHasExpectedStartColumn($array)
{
    return !isset(
        $array[
            0
        ]
    );
}
var_dump(testNotExpressionHasExpectedStartColumn($argv));