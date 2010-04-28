<?php
function testUnaryExpressionGraphForNestedOperators($args = null)
{
    return !@$args[1];
}
var_dump(testUnaryExpressionGraphForNestedOperators());