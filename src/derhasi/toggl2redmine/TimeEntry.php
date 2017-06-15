<?php

namespace derhasi\toggl2redmine;

class TimeEntry {

  /**
   *  Issue pattern to get the issue number from (in the first match).
   */
  const ISSUE_PATTERN = '/#([0-9]*)/m';

  /**
   * Number of the match item to get the issue number from.
   */
  const ISSUE_PATTERN_MATCH_ID = 1;

  /**
   * The score for which we can assume all changes to be present in the sync.
   */
  const UNCHANGED_SCORE = 1111;

  /**
   * Minimal score for sync actions.
   *
   * This means at least the issue id has to match.
   */
  const MIN_SCORE = 1000;

  /**
   * @var array
   */
  protected $entry;

  /**
   * @var integer
   */
  protected $issueID;

  /**
   * @var int
   */
  protected $redmineID;

  /**
   * @var array
   */
  protected $redmineData;

  /**
   * TimeEntry constructor.
   *
   * @param array $togglEntry
   */
  public function __construct($togglEntry) {
    $this->entry = $togglEntry;

    $this->extractIssueNumber();
  }

  public function getRaw() {
    return $this->entry;
  }

  public function getID() {
    return $this->entry['id'];
  }

  public function getDescription() {
    return $this->entry['description'];
  }

  public function getIssueID() {
    return $this->issueID;
  }

  public function getSpentOn() {
    $date = new \DateTime($this->entry['start']);
    return $date->format('Y-m-d');
  }

  /**
   * Provide the redmine issue number associated to this time entry.
   */
  protected function extractIssueNumber() {
    $match = array();
    if (isset($this->entry['description']) && preg_match(self::ISSUE_PATTERN, $this->entry['description'], $match)) {
      $this->issueID = (int) $match[self::ISSUE_PATTERN_MATCH_ID];
      return $this->issueID;
    }
  }

  /**
   * Calculates score that indicates how likely the given entry is the sync item.
   *
   * @param array $redmineEntry
   *
   * @return int
   */
  public function calculateSyncScore($redmineEntry) {
    $score = 0;

    // Issue ID
    if ($redmineEntry['issue']['id'] == $this->getIssueID()) {
      $score += 1000;
    }

    // Description
    if ($redmineEntry['comments'] == $this->entry['description']) {
      $score += 100;
    }

    // Time Value
    if ($redmineEntry['hours'] == $this->getHours()) {
      $score += 10;
    }

    // Category
    if ($this->hasTag($redmineEntry['activity']['name'])) {
      $score += 1;
    }

    return $score;
  }

  /**
   * Provides the redmine sync id of the time entry.
   * @return int
   */
  public function getRedmineEntryID() {
    return $this->redmineID;
  }

  /**
   * Set sync
   * @param $id
   * @param $redmineData
   */
  public function setRedmineEntry($id, $redmineData) {
    $this->redmineID = $id;
    $this->redmineData = $redmineData;
  }

  public function hasTag($name) {
    return array_search($name, $this->entry['tags']) !== FALSE;
  }

  public function getTagNames() {
    return $this->entry['tags'];
  }

  public function getHours() {
    return number_format($this->entry['duration'] / 60 / 60, 2);
  }

  public function syncNeeded() {
    return $this->calculateSyncScore($this->redmineData) != static::UNCHANGED_SCORE;
  }

  public function syncScore() {
    return $this->calculateSyncScore($this->redmineData);
  }

}
