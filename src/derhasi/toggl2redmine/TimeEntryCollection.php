<?php
/**
 * @file
 * TimeEntryCollection.php for toggl2redmine
 */

namespace derhasi\toggl2redmine;


class TimeEntryCollection implements \Countable {

  /**
   * @var \derhasi\toggl2redmine\TimeEntry[]
   */
  protected $entries = [];

  /**
   * @var array
   */
  protected $sync = [];

  /**
   * @var array
   */
  protected $redmineEntries = [];

  /**
   * Get list of entries in this collection.
   *
   * @return \derhasi\toggl2redmine\TimeEntry[]
   */
  public function getEntries() {
    return $this->entries;
  }

  /**
   * @param array $togglEntry
   */
  public function addTogglEntry($togglEntry) {
    $entry = new TimeEntry($togglEntry);
    $this->entries[$entry->getID()] = $entry;
  }

  /**
   * Checks if the collection has no entries yet.
   * @return bool
   */
  public function isEmpty() {
    return empty($this->entries);
  }

  /**
   * {@inheritdoc}
   */
  public function count() {
    return count($this->entries);
  }

  /**
   * Provide redmine entries to associate with the given list of toggl entries.
   * @param $redmineEntries
   */
  public function processRedmineEntries($redmineEntries) {
    $this->redmineEntries = [];
    $combinations = [];

    foreach ($redmineEntries as $redmineEntry) {
      $this->redmineEntries[$redmineEntry['id']] = $redmineEntry;

      foreach ($this->entries as $entry) {
        $combinations[] = [
          'redmine' => $redmineEntry['id'],
          'toggl' => $entry->getID(),
          'score' => $entry->calculateSyncScore($redmineEntry),
        ];
      }
    }

    // Sort the combinatations by best score.
    usort($combinations, function($a, $b) {
      return $b['score'] - $a['score'];
    });

    foreach ($combinations as $comb) {
      // Skip combination if score is too low.
      if ($comb['score'] < TimeEntry::MIN_SCORE) {
        continue;
      }

      // Only use this combination, when both toggl and redmine entry to do not
      // have a sync assocation yet.
      if (!$this->togglHasSync($comb['toggl']) && !$this->redmineHasSync($comb['redmine'])) {
        $this->associateEntryIDs($comb['toggl'], $comb['redmine']);
      }
    }
  }

  /**
   * Checks if the toggle time entry has an asociated redmine entry.
   *
   * @param int $toggleID
   *
   * @return bool
   */
  protected function togglHasSync($toggleID) {
    return isset($this->sync[$toggleID]);
  }

  /**
   * Checks if redmine time entry has an associated toggl entry.
   *
   * @param int $redmineID
   *
   * @return bool
   */
  protected function redmineHasSync($redmineID) {
    return array_search($redmineID, $this->sync) !== FALSE;
  }

  /**
   * Associate the sync of the two IDs.
   *
   * @param int $toggleID
   * @param int $redmineID
   */
  protected function associateEntryIDs($toggleID, $redmineID) {
    $this->sync[$toggleID] = $redmineID;
    $this->entries[$toggleID]->setRedmineEntry($redmineID, $this->redmineEntries[$redmineID]);
  }


}
