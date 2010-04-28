<?php
function testNotExpressionHasExpectedEndLine($array)
{
    return !isset(
        $array[
            0
        ]
    );
}
var_dump(testNotExpressionHasExpectedEndLine($argv));