<?php

namespace derhasi\toggl2redmine\Command;

use AJT\Toggl\TogglClient;
use derhasi\toggl2redmine\TimeEntry;
use derhasi\toggl2redmine\TimeEntryCollection;
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

  const ISSUE_SYNCED_FLAG = '#synced';

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

      $collection = $this->getTimeEntries($day_from, $day_to);
      $redmine_entries = $this->getRedmineTimeEntries(clone $day_from);
      $collection->processRedmineEntries($redmine_entries);

      if ($collection->isEmpty()) {
        $output->writeln('<comment>No entries given.</comment>');
      }
      else {
        $output->writeln(sprintf('<info>%d entries given.</info>', count($collection)));
        $this->processTimeEntries($collection);
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
   * @param \derhasi\toggl2redmine\TimeEntryCollection $collection
   */
  function processTimeEntries(TimeEntryCollection $collection) {

    $process = array();

    $table = new Table($this->output);
    $table->setHeaders(array('Issue', 'Issue title', 'Description', 'Duration', 'Activity', 'Status'));

    $defaultActivity = $this->getDefaultRedmineActivity();

    // Get the items to process.
    foreach ($collection->getEntries() as $entry) {

      $activity = $this->getRedmineActivityFromTogglEntry($entry->getTogglEntry());
      if ($activity) {
        $entry->setActivity($activity);
      }

      // Get issue number from description.
      if ($issue_id = $entry->getTogglEntry()->getIssueID()) {

        // Check if the entry is already fully synced.
        if (!$entry->hasChanges()) {
          $table->addRow(array(
            $issue_id,
            $this->getRedmineIssueTitle($issue_id, '<warning>Issue is not available anymore.</warning>'),
            $entry->getTogglEntry()->getDescription(),
            $entry->getTogglEntry()->getHours(),
            ($entry->hasActivity()) ? $entry->getActivity()->name : '',
            '<info>SYNCED</info>'
          ));
        }
        // Check if there is a valid issue for the issue ID.
        elseif (!$this->isIssueNumberValid($issue_id)) {
          $table->addRow(array(
            $issue_id,
            '',
            $entry->getTogglEntry()->getDescription(),
            $entry->getTogglEntry()->getHours(),
            ($entry->hasActivity()) ? $entry->getActivity()->name : '',
            '<error>Given issue not available.</error>'
          ));
        }
        // We only process the item, if we got a valid activity.
        elseif ($entry->hasActivity() || $defaultActivity) {
          $table->addRow(array(
            $issue_id,
            $this->getRedmineIssueTitle($issue_id),
            $entry->getTogglEntry()->getDescription(),
            $entry->getTogglEntry()->getHours(),
            ($entry->hasActivity()) ? $entry->getActivity()->name : sprintf('[ %s ]', $defaultActivity->name),
            (!$entry->hasRedmineEntry()) ? '<comment>unsynced</comment>' : "<comment>changed</comment>:\n" . $entry->getChangedString(),
          ));

          // Set item to be process.
          if (!$entry->hasActivity()) {
            $entry->setActivity($defaultActivity);
          }

          $process[] = $entry;
        }
        else {
          $table->addRow(array(
            $issue_id,
            $this->getRedmineIssueTitle($issue_id),
            $entry->getTogglEntry()->getDescription(),
            $entry->getTogglEntry()->getHours(),
            '',
            '<error>no activity</error>'
          ));
        }
      }
      // No issue id given.
      else {
        $table->addRow(array(
          ' - ',
          '',
          $entry->getTogglEntry()->getDescription(),
          $entry->getTogglEntry()->getHours(),
          ($entry->hasActivity()) ? $entry->getActivity()->name : '',
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
    foreach ($process as $entry) {
      $this->syncTimeEntry($entry);
      $this->progress->advance();
    }
    $this->progress->finish();
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
   * Load multiple redmine issues.
   * @param $ids
   *
   * @return mixed
   */
  function getRedmineIssues($ids) {
    // Cast to int.
    array_walk($ids, function(&$id) {
      $id = (int) $id;
    });

    $response = $this->redmineClient->issue->all([
      'issue_id' => implode(',', $ids),
    ]);
    
    foreach ($response['issues'] as $issue) {
      $this->tempIssues[$issue['id']] = $issue;
    }
    return $response['issues'];
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
   * @param \derhasi\toggl2redmine\TimeEntry $entry
   */
  function syncTimeEntry(TimeEntry $entry) {
    // Write to redmine.
    // Fetch unknown errors, or errors that cannot be quickly changed, like
    // - project was archived
    try {
      $data = array(
        'issue_id' => $entry->getTogglEntry()->getIssueID(),
        'spent_on' => $entry->getTogglEntry()->getDateString(),
        'hours' => $entry->getTogglEntry()->getHours(),
        'activity_id' => $entry->getActivity()->id,
        'comments' => $entry->getTogglEntry()->getDescription(),
      );
      // If there is already a redmine entry, we need to update that one.
      if ($entry->hasRedmineEntry()) {
        $error_message = $this->redmineClient->time_entry->update($entry->getRedmineEntry()->getID(), $data);
        if (!empty($error_message)) {
          $this->output->writeln(sprintf("<error>SYNC Failed for %d: %s\t (Issue #%d)\t%s</error>", $entry->getTogglEntry()->getID(), $data['comments'], $data['issue_id'], $error_message));
          return;
        }
      }
      // Otherwise we update.
      else {
        $redmine_time_entry = $this->redmineClient->time_entry->create($data);

        // Check if we got a valid new time entry back.
        if (!$redmine_time_entry->id) {
          $this->output->writeln(sprintf("<error>SYNC Failed for %d: %s\t (Issue #%d)\t%s</error>", $entry->getTogglEntry()->getID(), $data['comments'], $data['issue_id'], $redmine_time_entry->error));
          return;
        }
      }
    }
    catch (\Exception $e) {
      $this->output->writeln(sprintf("<error>SYNC Failed for %d: %s\t (Issue #%d)\t%s</error>", $entry->getTogglEntry()->getID(), $data['comments'], $data['issue_id'], $e->getMessage()));
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
   * @return TimeEntryCollection
   */
  function getTimeEntries(\DateTime $from, \DateTime $to) {

    $arguments = array(
      'start_date' => $from->format('c'),
      'end_date' => $to->format('c'),
    );

    $entries = $this->togglClient->GetTimeEntries($arguments);
    $collection = new TimeEntryCollection();

    foreach ($entries as $id => $entry) {
      // Remove time entries that do not belong to the current account.
      if ($entry['uid'] != $this->togglCurrentUser['id']) {
        continue;
      }
      // Time entries that are not finished yet, get removed too.
      // As time entries may run in duronly mode, we only can indicate a non-stopped entry by a negative duration.
      elseif ($entry['duration'] <= 0) {
        continue;
      }
      // Skip entry if it is not part of the workspace.
      elseif ($entry['wid'] != $this->togglWorkspaceID) {
        continue;
      }
      else {
        $collection->addTogglEntry(new TimeEntry\TogglTimeEntry($entry));
      }
    }

    return $collection;
  }

  /**
   * Helper to get a redmine activity from entry's tags.
   *
   * @param array $togglTimeEntry
   *
   * @return \derhasi\toggl2redmine\RedmineTimeEntryActivity
   */
  protected function getRedmineActivityFromTogglEntry(TimeEntry\TogglTimeEntry $togglTimeEntry) {
    foreach ($togglTimeEntry->getTags() as $tagName) {
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
        $redmineActivities[$activity['name']] = new RedmineTimeEntryActivity($activity);
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
   * @param TimeEntry $entry
   * @param \derhasi\toggl2redmine\RedmineTimeEntryActivity $activity
   */
  protected function saveSynchedTogglTimeEntry(TimeEntry $entry) {
    $raw = $entry->getTogglEntry()->raw;

    // We tag the toggle time entry with the synced flag.
    $raw['tags'] = [
      $entry->getActivity()->name,
      self::ISSUE_SYNCED_FLAG
    ];

    $raw['created_with'] = 'toggl2redmine';
    $ret = $this->togglClient->updateTimeEntry(array(
      'id' => $raw['id'],
      'time_entry' => $raw,
    ));
    if (empty($ret)) {
      $this->output->writeln(sprintf('<error>Updating toggl entry %d failed: %s', $raw['id'], $raw['description']));
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

  /**
   * Retrieve redmine time entries for the given date's day.
   * 
   * @param \DateTime $from
   *
   * @return array
   */
  protected function getRedmineTimeEntries(\DateTime $from) {

    $response = $this->redmineClient->time_entry->all([
      'user_id' => 'me',
      'limit' => 100,
      'spent_on' => $from->format('Y-m-d'),
    ]);

    $return = [];
    foreach ($response['time_entries'] as $entry) {
      $return[] = new TimeEntry\RedmineTimeEntry($entry);
    }

    return $return;
  }

}
