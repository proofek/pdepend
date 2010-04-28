<?php
function testSilenceExpressionHasExpectedStartColumn()
{
    return @$array[
        2 * 42
    ];
}
var_dump(testSilenceExpressionHasExpectedStartColumn());