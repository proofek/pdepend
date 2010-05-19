<?php

class testClassWithAllMethodsConnectedByOneProperty {
  public $a;
  public $b;

  public function m1() {
    $this->a = 1;
  }

  public function m2() {
    $this->a = 2;
  }

  public function m3() {
    $this->a = 3;
  }
}