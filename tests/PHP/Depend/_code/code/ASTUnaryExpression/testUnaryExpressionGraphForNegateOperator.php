<?php
function testUnaryExpressionGraphForNegateOperator($array = array())
{
    return !isset($array[0]);
}
var_dump(testUnaryExpressionGraphForNegateOperator());