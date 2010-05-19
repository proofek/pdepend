<?php

class testClassWithAllMethodsConnectedByAllProperties {
  public $a;
  public $b;

  public function m1() {
    $this->a = 1;
    $this->b = 1;
  }

  public function m2() {
    $this->a = 1;
    $this->b = 1;
  }

  public function m3() {
    $this->a = 1;
    $this->b = 1;
  }
}