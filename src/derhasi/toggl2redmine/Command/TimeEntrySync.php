<?php

namespace derhasi\toggl2redmine\Command;

use AJT\Toggl\TogglClient;
use \Symfony\Component\Console\Command\Command;
use \Symfony\Component\Console\Input\InputArgument;
use \Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use \Symfony\Component\Console\Output\OutputInterface;

/**
 * Symfony command implementation for converting redmine wikipages to git.
 */
class TimeEntrySync extends Command {

  /**
   *  Issue pattern to get the issue number from (in the first match).
   */
  const ISSUE_PATTERN = '/#([0-9]*)/m';

  /**
   * @var \AJT\Toggl\TogglClient;
   */
  protected $togglClient;

  /**
   * @var array
   */
  protected $togglCurrentUser;

  /**
   * @var  \Redmine\Client;
   */
  protected $redmineClient;

  /**
   * @var \Symfony\Component\Console\Helper\DialogHelper
   */
  protected $dialog;

  /**
   * @var \Symfony\Component\Console\Output\OutputInterface
   */
  protected $output;

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
      ->addArgument(
        'togglWorkspaceID',
        InputArgument::REQUIRED,
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
    ;
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    // Prepare helpers.
    $this->dialog = $this->getHelper('dialog');
    $this->output = $output;

    // Get our necessary arguments from the input.
    $redmineURL = $input->getArgument('redmineURL');
    $redmineAPIKey = $input->getArgument('redmineAPIKey');
    $tooglAPIKey = $input->getArgument('tooglAPIKey');

    // Init toggl.
    $this->togglClient = TogglClient::factory(array('api_key' => $tooglAPIKey));
    $this->togglCurrentUser = $this->togglClient->getCurrentUser();

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
        $this->processTimeEntries($entries);
      }

      // The next day to start from.
      $day_from = $day_to->add($interval_second);
    }

    $output->writeln('Finished.');
  }

  function processTimeEntries($entries) {

    $process = array();

    foreach ($entries as $entry) {
      $match = array();

      // Skip elements that have no description.
      if (empty($entry['description'])) {
        $this->output->writeln(sprintf('<error>Skipped entry %d due to missing description.</error>', $entry['id']));
      }
      // Get issue number from description.
      elseif (preg_match(self::ISSUE_PATTERN, $entry['description'], $match)) {
        $process[$match[1]] = $entry;
        $this->output->writeln(sprintf("<info>%d:\t %s</info>\t (Issue #%d)", $entry['id'], $entry['description'], $match[1]));
      }
      else {
        $this->output->writeln(sprintf("<error>%d:\t %s\t (No Issue ID found)</error>", $entry['id'], $entry['description']));
      }
    }

    // Confirm before we really process.
    if (!$this->dialog->askConfirmation($this->output, '<question>Sync entries? [y] </question>', false)) {
      $this->output->writeln('<error>Sync aborted.</error>');
    }

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
    }

    return $entries;
  }
}