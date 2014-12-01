<?php

namespace derhasi\toggl2redmine\Command;

use AJT\Toggl\TogglClient;
use derhasi\toggl2redmine\RedmineTimeEntryActivity;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * Symfony command implementation for converting redmine wikipages to git.
 */
class TimeEntrySync extends Command {

  /**
   *  Issue pattern to get the issue number from (in the first match).
   */
  const ISSUE_PATTERN = '/#([0-9]*)/m';

  const ISSUE_SYNCED_FLAG = '#synced';

  /**
   * Number of the match item to get the issue number from.
   */
  const ISSUE_PATTERN_MATCH_ID = 1;

  /**
   * @var \AJT\Toggl\TogglClient;
   */
  protected $togglClient;

  /**
   * @var array
   */
  protected $togglCurrentUser;

  /**
   * @var integer
   */
  protected $togglWorkspaceID;

  /**
   * @var  \Redmine\Client;
   */
  protected $redmineClient;

  /**
   * @var \Symfony\Component\Console\Helper\QuestionHelper
   */
  protected $question;

  /**
   * @var \Symfony\Component\Console\Input\InputInterface
   */
  protected $input;

  /**
   * @var \Symfony\Component\Console\Output\OutputInterface
   */
  protected $output;

  /**
   * @var \Symfony\Component\Console\Helper\ProgressHelper
   */
  protected $progress;

  /**
   * {@inheritdoc}
   */
  protected function configure()
  {
    $this
      ->setName('time-entry-sync')
      ->setDescription('Converts wiki pages of a redmine project to git')
      ->addArgument(
        'redmineURL',
        InputArgument::REQUIRED,
        'Provide the URL for the redmine installation'
      )
      ->addArgument(
        'redmineAPIKey',
        InputArgument::REQUIRED,
        'The APIKey for accessing the redmine API'
      )
      ->addArgument(
        'tooglAPIKey',
        InputArgument::REQUIRED,
        'API Key for accessing toggl API'
      )
      ->addOption(
        'workspace',
        NULL,
        InputOption::VALUE_REQUIRED,
        'Workspace ID to get time entries from'
      )
      ->addOption(
        'fromDate',
        NULL,
        InputOption::VALUE_REQUIRED,
        'From Date to get Time Entries from',
        '-1 day'
      )
      ->addOption(
        'toDate',
        NULL,
        InputOption::VALUE_REQUIRED,
        'To Date to get Time Entries from',
        'now'
      )
      ->addOption(
        'defaultActivity',
        NULL,
        InputOption::VALUE_REQUIRED,
        'Name of the default redmine activity to use for empty time entry tags',
        ''
      )
    ;
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    // Prepare helpers.
    $this->question = $this->getHelper('question');
    $this->input = $input;
    $this->output = $output;
    $this->progress = $this->getHelper('progress');

    // Get our necessary arguments from the input.
    $redmineURL = $input->getArgument('redmineURL');
    $redmineAPIKey = $input->getArgument('redmineAPIKey');
    $tooglAPIKey = $input->getArgument('tooglAPIKey');

    // Init toggl.
    $this->togglClient = TogglClient::factory(array('api_key' => $tooglAPIKey));
    $this->togglCurrentUser = $this->togglClient->getCurrentUser();
    $this->togglWorkspaceID = $this->getWorkspaceID();
    if (empty($this->togglWorkspaceID)) {
      $this->output->writeln('<error>No Workspace given</error>');
      return;
    }

    // Init redmine.
    $this->redmineClient = new \Redmine\Client($redmineURL, $redmineAPIKey);

    $from = $input->getOption('fromDate');
    $to = $input->getOption('toDate');

    $global_from = new \DateTime($from);
    $global_to = new \DateTime($to);

    // Interval to add 1 second to a time.
    $interval_second = new \DateInterval('PT1S');

    $day_from = clone $global_from;
    // Run each day.
    while ($day_from < $global_to) {

      // Prepare the day to object. We go to the end of the from day, but not
      // any further than the global_to.
      $day_to = clone $day_from;
      $day_to->setTime(23, 59, 59);
      if ($day_to > $global_to) {
        $day_to = clone $global_to;
      }

      $output->writeln(sprintf('Time entries for %s to %s', $day_from->format('D d.m.Y H:i'), $day_to->format('H:i')));

      $entries = $this->getTimeEntries($day_from, $day_to);

      if (empty($entries)) {
        $output->writeln('<comment>No entries given.</comment>');
      }
      else {
        $output->writeln(sprintf('<info>%d entries given.</info>', count($entries)));
        $this->fixTimeEntries($entries);
        $this->processTimeEntries($entries);
      }

      // The next day to start from.
      $day_from = $day_to->add($interval_second);
    }

    $output->writeln('Finished.');
  }

  /**
   * Get the workspace ID provided by argument or user input.
   *
   * @return mixed
   */
  protected function getWorkspaceID() {
    $workspace_id = $this->input->getOption('workspace');

    if (!$workspace_id) {
      $workspaces = $this->togglClient->getWorkspaces();
      $options = array();
      foreach ($workspaces as $i => $workspace) {
        $options[$i] = $workspace['name'];
      }

      $workspace_name = $this->question->ask($this->input, $this->output, new ChoiceQuestion('Select your workspace:', $options));

      $index = array_search($workspace_name, $options);
      $workspace_id = $workspaces[$index]['id'];
    }

    return $workspace_id;
  }

  /**
   * Process list of time entries.
   *
   * @param $entries
   */
  function processTimeEntries($entries) {

    $process = array();

    $table = new Table($this->output);
    $table->setHeaders(array('Issue', 'Description', 'Duration', 'Activity', 'Status'));

    $defaultActivity = $this->getDefaultRedmineActivity();

    // Get the items to process.
    foreach ($entries as $entry) {

      $activity_type = $this->getRedmineActivityFromTogglEntry($entry);

      // Get issue number from description.
      if ($issue_id = $this->getIssueNumberFromTimeEntry($entry)) {
        // Check if the entry is already synced.
        if ($this->isTimeEntrySynced($entry)) {
          $table->addRow(array(
            $issue_id,
            $entry['description'],
            number_format($entry['duration'] / 60 / 60, 2),
            ($activity_type) ? $activity_type->name : '',
            '<info>SYNCED</info>'
          ));
        }
        // We only process the item, if we got a valid activity.
        elseif ($activity_type || $defaultActivity) {
          $table->addRow(array(
            $issue_id,
            $entry['description'],
            number_format($entry['duration'] / 60 / 60, 2),
            ($activity_type) ? $activity_type->name : sprintf('[ %s ]', $defaultActivity->name),
            '<comment>unsynced</comment>'
          ));

          // Set item to be process.
          $process[] = array(
            'issue' => $issue_id,
            'entry' => $entry,
            'activity' => ($activity_type) ? $activity_type : $defaultActivity,
          );
        }
        else {
          $table->addRow(array(
            $issue_id,
            $entry['description'],
            number_format($entry['duration'] / 60 / 60, 2),
            '',
            '<error>no activity</error>'
          ));
        }
      }
      else {
        $table->addRow(array(
          ' - ',
          $entry['description'],
          number_format($entry['duration'] / 60 / 60, 2),
          $activity_type->name,
          '<error>No Issue ID found</error>'
        ));
      }
    }

    $table->render();

    // Simply proceed if no items are to be processed.
    if (empty($process)) {
      $this->output->writeln('<info>All entries synced</info>');
      return;
    }

    // Confirm before we really process.
    if (!$this->question->ask($this->input, $this->output,
      new ConfirmationQuestion(sprintf('<question> %d entries not synced. Process now? [y] </question>', count($process)), false))
    ) {
      $this->output->writeln('<error>Sync aborted.</error>');
      return;
    }

    // Process each item.
    $this->progress->start($this->output, count($process));
    foreach ($process as $processData) {
      $this->syncTimeEntry($processData['entry'], $processData['issue'], $processData['activity']);
      $this->progress->advance();
    }
    $this->progress->finish();
  }

  /**
   * Extracts the redmine issue number from the description.
   *
   * @param $entry
   * @return null
   */
  function getIssueNumberFromTimeEntry($entry) {
    $match = array();
    if (isset($entry['description']) && preg_match(self::ISSUE_PATTERN, $entry['description'], $match)) {
      return $match[self::ISSUE_PATTERN_MATCH_ID];
    }
    return NULL;
  }

  /**
   * Checks if the time entry is synced.
   *
   * @param $entry
   */
  function isTimeEntrySynced($entry) {
    // Nowadays we mark the entry with a tag.
    if (!empty($entry['tags']) && array_search(self::ISSUE_SYNCED_FLAG, $entry['tags']) !== FALSE) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Helper to sync a single time entry to redmine.
   *
   * @param $entry
   * @param $issue_id
   * @param \derhasi\toggl2redmine\RedmineTimeEntryActivity $activity
   */
  function syncTimeEntry($entry, $issue_id, RedmineTimeEntryActivity $activity) {
    // Write to redmine.
    $duration = $entry['duration'] / 60 / 60;
    $date = new \DateTime($entry['start']);

    // Fetch unknown errors, or errors that cannot be quickly changed, llike
    // - project was archived
    try {
      $redmine_time_entry = $this->redmineClient->api('time_entry')->create(array(
        'issue_id' => $issue_id,
        'spent_on' => $date->format('Y-m-d'),
        'hours' => $duration,
        'activity_id' => $activity->id,
        'comments' => $entry['description'],
      ));
    }
    catch (\Exception $e) {
      $this->output->writeln(sprintf("<error>SYNC Failed for %d: %s\t (Issue #%d)\t%s</error>", $entry['id'], $entry['description'], $issue_id, $e->getMessage()));
      return;
    }

    // Check if we got a valid time entry back.
    if (!$redmine_time_entry->id) {
      $this->output->writeln(sprintf("<error>SYNC Failed for %d: %s\t (Issue #%d)\t%s</error>", $entry['id'], $entry['description'], $issue_id, $redmine_time_entry->error));
      return;
    }

    // Update toggl entry with #synced Flag.
    $this->saveSynchedTogglTimeEntry($entry);
  }

  /**
   * Helper to get time entries for given time frame.
   *
   * @param \DateTime $from
   * @param \DateTime $to
   * @return mixed
   */
  function getTimeEntries(\DateTime $from, \DateTime $to) {

    $arguments = array(
      'start_date' => $from->format('c'),
      'end_date' => $to->format('c'),
    );

    $entries = $this->togglClient->GetTimeEntries($arguments);

    foreach ($entries as $id => $entry) {
      // Remove time entries that do not belong to the current account.
      if ($entry['uid'] != $this->togglCurrentUser['id']) {
        unset($entries[$id]);
      }
      // Time entries that are not finished yet, get removed too.
      elseif (empty($entry['stop'])) {
        unset($entries[$id]);
      }
      // Skip entry if it is not part of the workspace.
      elseif ($entry['wid'] != $this->togglWorkspaceID) {
        unset($entries[$id]);
      }
    }

    return $entries;
  }

  /**
   * Helper to get a redmine activity from entry's tags.
   *
   * @param array $entry
   * @return \derhasi\toggl2redmine\RedmineTimeEntryActivity
   */
  protected function getRedmineActivityFromTogglEntry($entry) {
    foreach ($entry['tags'] as $tagName) {
      $activity = $this->getRedmineActivityByName($tagName);

      if ($activity) {
        return $activity;
      }
    }
  }

  /**
   * Helper to retrieve the redmine activity ID by name.
   *
   * @param string $name
   * @return \derhasi\toggl2redmine\RedmineTimeEntryActivity
   */
  protected function getRedmineActivityByName($name) {
    static $redmineActivities;

    if (!isset($redmineActivities)) {
      $act = $this->redmineClient->api('time_entry_activity')->all()['time_entry_activities'];
      foreach ($act as $activity) {
        $redmineActivities[$activity['name']] = new RedmineTimeEntryActivity($activity['id'], $activity['name']);
      }
    }

    if (isset($redmineActivities[$name])) {
      return $redmineActivities[$name];
    }
  }

  /**
   * Helper to get default redmine activity from command line.
   *
   * @return RedmineTimeEntryActivity
   */
  protected function getDefaultRedmineActivity() {
    $name = $this->input->getOption('defaultActivity');
    if ($name) {
      return $this->getRedmineActivityByName($name);
    }
  }

  /**
   * Checks if entries need to be fixed and updates those entries.
   *
   * Currently this is needed for the old sync marker in the entry description.
   *
   * @param $entries
   */
  protected function fixTimeEntries(&$entries) {
    foreach ($entries as $id => $entry) {

      // Check if the old sync marker is used.
      if (strpos($entry['description'], self::ISSUE_SYNCED_FLAG) !== FALSE) {
        $pattern = '/' . preg_quote(self::ISSUE_SYNCED_FLAG, '/') . '\[[0-9]*\]/';
        $replaced = preg_replace($pattern, '', $entry['description']);
        // If the replaced description does not match the original one, we need
        // to update the time entry.
        if ($replaced != $entry['description']) {
          $entry['description'] = $replaced;
          $this->saveSynchedTogglTimeEntry($entry);
          // Put the updated entry back.
          $entries[$id] = $entry;
        }
      }
    }
  }

  /**
   * Helper to save a time entry as synched.
   *
   * @param $entry
   */
  protected function saveSynchedTogglTimeEntry(&$entry) {
    // We tag the toggle time entry with the synced flag.
    $entry['tags'][] = self::ISSUE_SYNCED_FLAG;
    $entry['created_with'] = 'toggl2redmine';
    $ret = $this->togglClient->updateTimeEntry(array(
      'id' => $entry['id'],
      'time_entry' => $entry,
    ));
    if (empty($ret)) {
      $this->output->writeln(sprintf('<error>Updating toggl entry %d failed: %s', $entry['id'], $entry['description']));
    }
  }


}