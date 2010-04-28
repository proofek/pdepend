<?php
function testNotExpressionHasExpectedEndColumn($array)
{
    return !isset(
        $array[
            0
        ]
    );
}
var_dump(testNotExpressionHasExpectedEndColumn($argv));