# Toggl2Redmine

PHP CLI tool for syncing toggl with redmine.

## Time entry sync

Currently only `time-entry-sync` is available.

* One-way-synchronisaton from toggl to Redmine
* Mapping of Toggl tag name with Redmine
* Sync state is marked as tag `#synched` in Toggl

## Installation

The installation is simple by using [composer](https://getcomposer.org/). After [installing composer](https://getcomposer.org/doc/00-intro.md) you can either install the command globally or within a project.

### Global 

In the global installation `toggl2redmine` will be available as a command line tool.

* Run `composer global require derhasi/toggl2redmine` to install globally.
* Add `export PATH=~/.composer/vendor/bin:$PATH` to your `.bashrc`or `.profile`

After the installation you should be able to run `toggl2redmine time-entry-sync ...` from anywhere.

### Local

You can run `composer require derhasi/toggl2redmine` in any composer enabled project to add this project as a dependency.

## Usage

`./toggl2redmine.php time-entry-sync ...`

```
Usage:
 time-entry-sync [--workspace="..."] [--fromDate="..."] [--toDate="..."] [--defaultActivity="..."] redmineURL redmineAPIKey tooglAPIKey

Arguments:
 redmineURL            Provide the URL for the redmine installation
 redmineAPIKey         The APIKey for accessing the redmine API
 tooglAPIKey           API Key for accessing toggl API

Options:
 --workspace           Workspace ID to get time entries from
 --fromDate            From Date to get Time Entries from (default: "-1 day")
 --toDate              To Date to get Time Entries from (default: "now")
 --defaultActivity     Name of the default redmine activity to use for empty time entry tags (default: "")
 ```