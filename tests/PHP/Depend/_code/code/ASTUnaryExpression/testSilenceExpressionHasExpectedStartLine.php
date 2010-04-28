<?php
function testSilenceExpressionHasExpectedStartLine()
{
    return @$array[
        2 * 42
    ];
}
var_dump(testSilenceExpressionHasExpectedStartLine());