<?php
function testNotExpressionHasExpectedStartLine($array)
{
    return !isset(
        $array[
            0
        ]
    );
}
var_dump(testNotExpressionHasExpectedStartLine($argv));