<?php
function testSilenceExpressionHasExpectedEndColumn()
{
    return @$array[
        2 * 42
    ];
}
var_dump(testSilenceExpressionHasExpectedEndColumn());