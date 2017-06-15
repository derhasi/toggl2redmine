<?php

namespace derhasi\toggl2redmine\TimeEntry;

class TogglTimeEntry extends TimeEntryBase {

  /**
   *  Issue pattern to get the issue number from (in the first match).
   */
  const ISSUE_PATTERN = '/#([0-9]*)/m';

  /**
   * Number of the match item to get the issue number from.
   */
  const ISSUE_PATTERN_MATCH_ID = 1;

  /**
   * @var int
   */
  protected $issueID;

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->raw['description'];
  }

  /**
   * {@inheritdoc}
   */
  public function getIssueID() {
    // Extract issue ID from description.
    if (!isset($this->issueID)) {
      $match = array();
      if (preg_match(static::ISSUE_PATTERN, $this->getDescription(), $match)) {
        $this->issueID = (int) $match[static::ISSUE_PATTERN_MATCH_ID];
      }
    }
    return $this->issueID;
  }

  /**
   * {@inheritdoc}
   */
  public function getHours() {
    return number_format($this->raw['duration'] / 60 / 60, 2);
  }

  /**
   * {@inheritdoc}
   */
  public function getDateString() {
    $date = new \DateTime($this->raw['start']);
    return $date->format('Y-m-d');
  }

  /**
   * Retrieve tags for this time entry.
   *
   * @return string[]
   */
  public function getTags() {
    return (array) $this->raw['tags'];
  }

}
