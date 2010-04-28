<?php
function testSilenceExpressionHasExpectedEndLine()
{
    return @$array[
        2 * 42
    ];
}
var_dump(testSilenceExpressionHasExpectedEndLine());