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
   * @param array $raw
   */
  public function __construct($raw) {
    $this->id = $raw['id'];
    $this->name = $raw['name'];
  }
}
