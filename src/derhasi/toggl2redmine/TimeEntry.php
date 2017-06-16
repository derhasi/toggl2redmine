<?php

namespace derhasi\toggl2redmine;

use derhasi\toggl2redmine\TimeEntry\RedmineTimeEntry;
use derhasi\toggl2redmine\TimeEntry\TogglTimeEntry;

class TimeEntry {

  /**
   * The score for which we can assume all changes to be present in the sync.
   */
  const UNCHANGED_SCORE = 1111;

  /**
   * Minimal score for sync actions.
   *
   * This means at least the issue id has to match.
   */
  const MIN_SCORE = 10;

  /**
   * @var \derhasi\toggl2redmine\TimeEntry\TogglTimeEntry
   */
  protected $togglEntry;

  /**
   * @var integer
   */
  protected $issueID;

  /**
   * @var \derhasi\toggl2redmine\TimeEntry\RedmineTimeEntry
   */
  protected $redmineEntry;

  /**
   * @var \derhasi\toggl2redmine\RedmineTimeEntryActivity
   */
  protected $activity;


  /**
   * Get the associated toggl entry.
   *
   * @return \derhasi\toggl2redmine\TimeEntry\TogglTimeEntry
   */
  public function getTogglEntry() {
    return $this->togglEntry;
  }

  /**
   * Set the toggl entry.
   *
   * @param \derhasi\toggl2redmine\TimeEntry\TogglTimeEntry $togglEntry
   */
  public function setTogglEntry($togglEntry) {
    $this->togglEntry = $togglEntry;
  }

  /**
   * Checks if toggl entry is set.
   *
   * @return bool
   */
  public function hasTogglEntry() {
    return isset($this->togglEntry);
  }
  
  /**
   * Get the redmine entry.
   *
   * @return \derhasi\toggl2redmine\TimeEntry\RedmineTimeEntry
   */
  public function getRedmineEntry() {
    return $this->redmineEntry;
  }

  /**
   * Set the redmine entry.
   *
   * @param \derhasi\toggl2redmine\TimeEntry\RedmineTimeEntry $redmineEntry
   */
  public function setRedmineEntry($redmineEntry) {
    $this->redmineEntry = $redmineEntry;
  }

  /**
   * Checks if a redmine entry isset.
   *
   * @return bool
   */
  public function hasRedmineEntry() {
    return isset($this->redmineEntry);
  }

  /**
   * Calculates score that indicates how likely the given entry is the sync item.
   *
   * @param array $redmineEntry
   *
   * @return int
   */
  public function calculateSyncScore(RedmineTimeEntry $redmineEntry) {
    $score = 0;

    // Issue ID
    if ($redmineEntry->getIssueID() == $this->togglEntry->getIssueID()) {
      $score += 1000;
    }

    // Description
    if ($redmineEntry->getDescription() == $this->togglEntry->getDescription()) {
      $score += 100;
    }

    // Time Value
    if ($redmineEntry->getHours() == $this->togglEntry->getHours()) {
      $score += 10;
    }

    // Category
    if (in_array($redmineEntry->getActivity()->name, $this->togglEntry->getTags())) {
      $score += 1;
    }

    return $score;
  }

  /**
   * Provide the sync score for the given association.
   *
   * @return int
   */
  public function syncScore() {
    if (!$this->hasRedmineEntry()) {
      return 0;
    }

    return $this->calculateSyncScore($this->redmineEntry);
  }


  /**
   * Checks if the sync entries have changes, based on the sync score.
   *
   * @return bool
   */
  public function hasChanges() {
    return $this->syncScore() < static::UNCHANGED_SCORE;
  }

  /**
   * Provides a string listing all old values that are about to be changed.
   *
   * @return string
   */
  public function getChangedString() {
    $text = [];

    // Issue ID
    if ($this->redmineEntry->getIssueID() != $this->togglEntry->getIssueID()) {
      $text[] = sprintf('- Issue: "%s"', $this->redmineEntry->getIssueID());
    }

    // Description
    if ($this->redmineEntry->getDescription() != $this->togglEntry->getDescription()) {
      $text[] = sprintf('- Description: "%s"', $this->redmineEntry->getDescription());
    }

    // Description
    if ($this->redmineEntry->getHours() != $this->togglEntry->getHours()) {
      $text[] = sprintf('- Duration: "%s"', $this->redmineEntry->getHours());
    }

    // Category
    if (!in_array($this->redmineEntry->getActivity()->name, $this->togglEntry->getTags())) {
      $text[] = sprintf('- Activity:  %s', $this->redmineEntry->getActivity()->name);
    }

    return implode("\n", $text);
  }

  /**
   * Get set activity.
   * 
   * @return \derhasi\toggl2redmine\RedmineTimeEntryActivity
   */
  public function getActivity() {
    return $this->activity;
  }

  /**
   * Set activity.
   *
   * @param \derhasi\toggl2redmine\RedmineTimeEntryActivity $activity
   */
  public function setActivity(RedmineTimeEntryActivity $activity) {
    $this->activity = $activity;
  }

  /**
   * Checks if we have an activity set.
   * @return bool
   */
  public function hasActivity() {
    return isset($this->activity);
  }

}
