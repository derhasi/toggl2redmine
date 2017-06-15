<?php

namespace derhasi\toggl2redmine\TimeEntry;

abstract class TimeEntryBase {

  /**
   * @var array
   */
  public $raw;

  /**
   * Basic constructor with raw data.
   *
   * @param array $data
   */
  public function __construct(array $data) {
    $this->raw = $data;
  }

  /**
   * Checks if the time entry is new.
   * @return bool
   */
  public function isNew() {
    return $this->getID() == 0;
  }

    /**
   * Provides the ID of the associated redmine Issue.
   *
   * @return int
   */
  public function getID() {
    return (int) $this->raw['id'];
  }

  /**
   * Provide the description of the given time entry.
   *
   * @return string
   */
  public abstract function getDescription();

  /**
   * Provides the ID of the associated redmine Issue.
   *
   * @return int
   */
  public abstract function getIssueID();

  /**
   * Provides the duration in hours.
   *
   * @return string
   */
  public abstract function getHours();

  /**
   * Provides the spent on date.
   *
   * @return string
   */
  public abstract function getDateString();

}
