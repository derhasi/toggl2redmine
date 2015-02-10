<?php

namespace derhasi\toggl2redmine\Command;

use AJT\Toggl\TogglClient;
use derhasi\toggl2redmine\TimeEntrySyncConfigWrapper;
use derhasi\toggl2redmine\RedmineTimeEntryActivity;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

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
   * @var array
   */
  protected $config;

  /**
   * Collects information for issues to avoid multiple calls.
   *
   * @var array
   */
  protected $tempIssues = array();

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
        'togglAPIKey',
        InputArgument::REQUIRED,
        'API Key for accessing toggl API'
      )
      ->addOption(
        'workspace',
        NULL,
        InputOption::VALUE_REQUIRED,
        'Workspace ID to get time entries from',
        NULL
      )
      ->addOption(
        'fromDate',
        NULL,
        InputOption::VALUE_REQUIRED,
        'From Date to get Time Entries from (defaults to "-1 day")',
        NULL
      )
      ->addOption(
        'toDate',
        NULL,
        InputOption::VALUE_REQUIRED,
        'To Date to get Time Entries from (defaults to "now")',
        NULL
      )
      ->addOption(
        'defaultActivity',
        NULL,
        InputOption::VALUE_REQUIRED,
        'Name of the default redmine activity to use for empty time entry tags',
        NULL
      )
    ;
  }

  /**
   * {@inheritdoc}
   */
  protected function initialize(InputInterface $input, OutputInterface $output) {
    // Prepare helpers.
    $this->question = $this->getHelper('question');
    $this->input = $input;
    $this->output = $output;
    $this->progress = $this->getHelper('progress');
  }

  /**
   * {@inheritdoc}
   */
  protected function interact(InputInterface $input, OutputInterface $output) {

    $config = new TimeEntrySyncConfigWrapper();

    // redmineURL
    if (!$input->getArgument('redmineURL')) {

      // Either get value from config file.
      if ($config->getValueFromConfig('redmineURL')) {
        $answer = $config->getValueFromConfig('redmineURL');
      }
      // Or ask for it.
      else {
        $question = new Question('Enter your redmine URL: ');

        $answer = $this->question->ask($input,$output, $question);
      }

      if ($answer) {
        $input->setArgument('redmineURL', $answer);
      }
      // The argument is required, so we simply quit otherwise.
      else {
        return;
      }
    }

    // redmineAPIKey
    if (!$input->getArgument('redmineAPIKey')) {

      // Either get value from config file.
      if ($config->getValueFromConfig('redmineAPIKey')) {
        $answer = $config->getValueFromConfig('redmineAPIKey');
      }
      // Or ask for it.
      else {
        $question = new Question('Enter your redmine API Token: ');
        $answer = $this->question->ask($input, $output, $question);
      }

      if ($answer) {
        $input->setArgument('redmineAPIKey', $answer);
      }
      // The argument is required, so we simply quit otherwise.
      else {
        return;
      }
    }

    // togglAPIKey
    if (!$input->getArgument('togglAPIKey')) {

      // Either get value from config file.
      if ($config->getValueFromConfig('togglAPIKey')) {
        $answer = $config->getValueFromConfig('togglAPIKey');
      }
      // Or ask for it.
      else {
        $question = new Question('Enter your toggl API Token: ');
        $answer = $this->question->ask($input,$output, $question);
      }

      if ($answer) {
        $input->setArgument('togglAPIKey', $answer);
      }
      // The argument is required, so we simply quit otherwise.
      else {
        return;
      }
    }

    // fromDate
    if (!$input->getOption('fromDate')) {

      // Either get value from config file.
      if ($config->getValueFromConfig('fromDate')) {
        $answer = $config->getValueFromConfig('fromDate');
      }
      // Or ask for it.
      else {
        $question = new Question('Enter "from date" [-1 day]: ', '-1 day');
        $answer = $this->question->ask($input,$output, $question);
      }

      if ($answer) {
        $input->setOption('fromDate', $answer);
      }
      // The argument is required, so we simply quit otherwise.
      else {
        return;
      }
    }

    // toDate
    if (!$input->getOption('toDate')) {

      // Either get value from config file.
      if ($config->getValueFromConfig('toDate')) {
        $answer = $config->getValueFromConfig('toDate');
      }
      // Or ask for it.
      else {
        $question = new Question('Enter "to date" [now]: ', 'now');
        $answer = $this->question->ask($input,$output, $question);
      }

      if ($answer) {
        $input->setOption('toDate', $answer);
      }
      // The argument is required, so we simply quit otherwise.
      else {
        return;
      }
    }

    // defaultActivity
    if (!$input->getOption('defaultActivity')) {

      // Either get value from config file.
      if ($config->getValueFromConfig('defaultActivity')) {
        $answer = $config->getValueFromConfig('defaultActivity');
      }
      // Or ask for it.
      else {
        $question = new Question('Name of default activity" []: ', '');
        $answer = $this->question->ask($input,$output, $question);
      }

      if ($answer) {
        $input->setOption('defaultActivity', $answer);
      }
    }

    // workspace: interaction will take place in ->execute, as currently we are
    // not dealing with an instance of the Toggl client.
    if (!$input->getOption('workspace') && $config->getValueFromConfig('workspace')) {
      $input->setOption('workspace', $config->getValueFromConfig('workspace'));
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {

    // Get our necessary arguments from the input.
    $redmineURL = $input->getArgument('redmineURL');
    $redmineAPIKey = $input->getArgument('redmineAPIKey');
    $togglAPIKey = $input->getArgument('togglAPIKey');

    // Init toggl.
    $this->togglClient = TogglClient::factory(array('api_key' => $togglAPIKey));
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

    // Before we handle dates, we need to make sure, a default timezone is set.
    $this->fixTimezone();

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
        $options[$i] = sprintf('%s [ID:%d]', $workspace['name'], $workspace['id']);
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
    $table->setHeaders(array('Issue', 'Issue title', 'Description', 'Duration', 'Activity', 'Status'));

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
            $this->getRedmineIssueTitle($issue_id, '<warning>Issue is not available anymore.</warning>'),
            $entry['description'],
            number_format($entry['duration'] / 60 / 60, 2),
            ($activity_type) ? $activity_type->name : '',
            '<info>SYNCED</info>'
          ));
        }
        // Check if there is a valid issue for the issue ID.
        elseif (!$this->isIssueNumberValid($issue_id)) {
          $table->addRow(array(
            $issue_id,
            '',
            $entry['description'],
            number_format($entry['duration'] / 60 / 60, 2),
            ($activity_type) ? $activity_type->name : '',
            '<error>Given issue not available.</error>'
          ));
        }
        // We only process the item, if we got a valid activity.
        elseif ($activity_type || $defaultActivity) {
          $table->addRow(array(
            $issue_id,
            $this->getRedmineIssueTitle($issue_id),
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
            $this->getRedmineIssueTitle($issue_id),
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
          '',
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
   * Check if we got a valid issue ID.
   *
   * @param integer $issue_id
   *
   * @return bool
   */
  function isIssueNumberValid($issue_id) {
    $issue = $this->getRedmineIssue($issue_id);
    return !empty($issue);
  }

  /**
   * Retrieve the issue subject for an issue ID.
   *
   * @param integer $issue_id
   *   The redmine issue ID.
   * @param string $fallback
   *   Fallback string to show if issue does not exists.
   *
   * @return string
   */
  function getRedmineIssueTitle($issue_id, $fallback = '') {
    if ($issue = $this->getRedmineIssue($issue_id)) {
      return $issue['subject'];
    }
    else {
      return $fallback;
    }
  }

  /**
   * Retieve issue information from redmine.
   *
   * @param integer $issue_id
   * @return mixed
   */
  function getRedmineIssue($issue_id) {
    if (!isset($this->tempIssues[$issue_id])) {
      $ret = $this->redmineClient->api('issue')->show($issue_id);
      if (isset($ret['issue'])) {
        $this->tempIssues[$issue_id] = $ret['issue'];
      }
      else {
        $this->tempIssues[$issue_id] = null;
      }
    }
    return $this->tempIssues[$issue_id];
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
    $this->saveSynchedTogglTimeEntry($entry, $activity);
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
      // As time entries may run in duronly mode, we only can indicate a non-stopped entry by a negative duration.
      elseif ($entry['duration'] <= 0) {
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
   * @param \derhasi\toggl2redmine\RedmineTimeEntryActivity $activity
   */
  protected function saveSynchedTogglTimeEntry(&$entry, $activity = NULL) {
    // We tag the toggle time entry with the synced flag.
    $entry['tags'][] = self::ISSUE_SYNCED_FLAG;

    // Make sure our activity will be saved as tag, in case the acitivity is
    // provided as default activity.
    if (isset($activity) && array_search($activity->name, $entry['tags']) === FALSE) {
      $entry['tags'][] = $activity->name;
    }

    $entry['created_with'] = 'toggl2redmine';
    $ret = $this->togglClient->updateTimeEntry(array(
      'id' => $entry['id'],
      'time_entry' => $entry,
    ));
    if (empty($ret)) {
      $this->output->writeln(sprintf('<error>Updating toggl entry %d failed: %s', $entry['id'], $entry['description']));
    }
  }

  /**
   * Helper to temporary fix timezone settings.
   */
  protected function fixTimezone() {

    // If no default timezone is set, we set the one from the toggl profile and
    // otherwise explicitely temporary set the system timezone.
    if (!ini_get('date.timezone')) {
      if (!empty($this->togglCurrentUser['timezone'])) {
        $default = $this->togglCurrentUser['timezone'];
        date_default_timezone_set($this->togglCurrentUser['timezone']);
      }
      elseif ($default = date_default_timezone_get()) {
        date_default_timezone_set($default);
      }
      else {
        $default = 'UTC';
        date_default_timezone_set('UTC');
      }

      $this->output->writeln(sprintf('<comment>Your timezone temporarily was set to "%s", as no default timezone is set in php.ini.</comment>', $default));
    }
  }



}
