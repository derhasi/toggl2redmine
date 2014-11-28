<?php

namespace derhasi\toggl2redmine;

class RedmineTimeEntryActivity {
  /**
   * @var integer
   */
  var $id;

  /**
   * @var string
   */
  var $name;

  /**
   * Constructor.
   *
   * @param integer $id
   * @param string $name
   */
  public function __construct($id, $name) {
    $this->id = $id;
    $this->name = $name;
  }
}