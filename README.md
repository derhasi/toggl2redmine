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

You can run the global or local command by appending options, use a configuration file or use both. A command line
argument or option will allways override a setting from the `toggl2redmine.yml`.

Global: `toggl2redmine.php time-entry-sync ...`

Local: `./toggl2redmine time-entry-sync ...`

### Command line arguments and options

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
 
### Configuration
 
You can place a `toggl2redmine.yml` in your current working directory for local defaults. For global defaults you
can place it at `~/.toggl2redmine/toggl2redmine.yml`. Attention: local and global defaults will **not** be merged. The
local default always take precedence over the global defaults, in the case it exists.

You can find a template for `toggl2redmine.yml` at [default.toggl2redmine.yml](default.toggl2redmine.yml). Make sure
to rename it to `toggl2redmine.yml`!

For example, for adding the default config template to the global folder you can simply type:

```sh
mkdir ~/.toggl2redmine
curl https://raw.githubusercontent.com/derhasi/toggl2redmine/master/default.toggl2redmine.yml > ~/.toggl2redmine/toggl2redmine.yml
```