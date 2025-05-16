# Changelog

## [Unreleased]

* [PR-51](https://github.com/ITK-Leantime/project-overview/pull/51)
  * Downgrade method requirements to match current code base.

## [3.1.0] - 2025-05-14

* [PR-49](https://github.com/ITK-Leantime/project-overview/pull/49)
  * Make full list load require confirmation

## [3.0.3] - 2025-05-06

* [PR-47](https://github.com/ITK-Leantime/project-overview/pull/47)
  * Explicitly defined start and end of week

## [3.0.2] - 2025-04-01

* [PR-45](https://github.com/ITK-Leantime/project-overview/pull/45)
  * Corrected header of priority

## [3.0.1] - 2025-04-01

* [PR-43](https://github.com/ITK-Leantime/project-overview/pull/43)
  * Changed order of project name and ticket title

## [3.0.0] - 2025-03-27

* [PR-41](https://github.com/ITK-Leantime/project-overview/pull/41)
  * Added sort to url (sortby and sortorder)
  * Added the possibility to see tickets without due date
  * Added the possibility to see tickets exceeded due date
  * Added some blade linting to readme (and linted the blade file)
* [PR-40](https://github.com/ITK-Leantime/project-overview/pull/40)
  * A little css
* [PR-39](https://github.com/ITK-Leantime/project-overview/pull/39)
  * Npm audit fix
  * Update leantime to 3.4.3

## [2.3.0] - 2025-01-14

* [PR-36](https://github.com/ITK-Leantime/project-overview/pull/36)
  * Added sort by column

## [2.2.1] - 2025-01-14

* [PR-34](https://github.com/ITK-Leantime/project-overview/pull/34)
  * Reordered filters

## [2.2.0] - 2025-01-06

* [PR-32](https://github.com/ITK-Leantime/project-overview/pull/32)
  * Added compatability for Leantime 3.3x

## [2.1.5] - 2024-10-23

* [PR-30](https://github.com/ITK-Leantime/project-overview/pull/30)
  * Fix search while user filter is active

## [2.1.4] - 2024-10-22

* [PR-28](https://github.com/ITK-Leantime/project-overview/pull/28)
  * Remove last trace of milestone coloring

## [2.1.3] - 2024-10-22

* [PR-26](https://github.com/ITK-Leantime/project-overview/pull/26)
  * Fixed broken status and priority selectors
  * Removed colouring of milestones due to recurring issues with white text on white background

## [2.1.2] - 2024-10-18

* [PR-24](https://github.com/ITK-Leantime/project-overview/pull/24)
  * Fixed issue where not all projects were returned from repo

## [2.1.1] - 2024-10-17

* [PR-22](https://github.com/ITK-Leantime/project-overview/pull/22)
  * Correctly build assets

## [2.1.0] - 2024-10-17

* [PR-20](https://github.com/ITK-Leantime/project-overview/pull/20)
  * Add empty option for milestone select
  * Add project column between id and taskname
  * Add multiselect for user filter
* [PR-19](https://github.com/ITK-Leantime/project-overview/pull/19)
  Fix milestone text color so it is always white when a milestone is chosen, always black when not.

## [2.0.3] - 2024-09-19

* [PR-16](https://github.com/ITK-Leantime/project-overview/pull/16)
  Remove urlencode in `register.php`

## [2.0.2] - 2024-09-19

* [PR-14](https://github.com/ITK-Leantime/project-overview/pull/14)
  * Removed additional border-radiusses
  * Format javascript dates to force /'s instead of .'s.

## [2.0.1] - 2024-09-18

* [PR-12](https://github.com/ITK-Leantime/project-overview/pull/12)
  * Streamline release/deploy
  * Add Markdown Runner to actions
* [PR-11](https://github.com/ITK-Leantime/project-overview/pull/11)
  * Add request uri check to scope js/css

## [2.0.0] 2024-09-03

* [PR-10](https://github.com/ITK-Leantime/project-overview/pull/10)
  * Added compatability for Leantime 3.2
  * Change imports
  * Replace old session handling with new session handling
  * Update leantime dependency for phpstan
  * Add composer.lock
  * Update from php8.1 -> php 8.3 in pr.yml and README

## [1.0.0] 2024-08-16

* [PR-9](https://github.com/ITK-Leantime/project-overview/pull/9)
  * Specified files to only be loaded on projectOverview page
  * Minor layout changes and classes for styling
  * Minor css alterations and additions
  * Added visual feedback when saving asynchronously

* [PR-7](https://github.com/ITK-Leantime/project-overview/pull/7)
  * Add prettier
  * Add node_modules to different ignore files
  * Add deploy script
  * Merge release and prerelease files
  * Add shell check to pr.yml
  * Add node to dockerfile to run prettier

* [PR-6](https://github.com/ITK-Leantime/project-overview/pull/4)
  * Add build script
  * Add check-create-release to pr.yml
  * Add pre-release.yml and release.yml to github workflows
  * Add dockerfile + install of rsync

* [PR-5](https://github.com/ITK-Leantime/project-overview/pull/7)
  * Make the side menu "personal", so when on the "project overview"-page, the sidemenu doesn't change to the projects
    menu.

* [PR-4](https://github.com/ITK-Leantime/project-overview/pull/6)
  * Add ```ticket.planHours``` and ```ticket.hourRemaining``` to sql query, so the data is shown in the table.

* [PR-3](https://github.com/ITK-Leantime/project-overview/pull/5)
  * Add burger menu item (change ```<i``` to ```<span```)
  * Limit search on dueDate (dateToFinish in db)
  * Cast dueDate in sql query, because the time is not necessary for the filter

* [PR-2](https://github.com/ITK-Leantime/project-overview/pull/3)
* Project overview plugin
  * Services file with install/uninstall and methods for passing data from the repo to the controller
  * A controller that feeds data to the template
  * A template
  * A repository that handles the sql queries
  * A filter/search that uses a redirect
* code analysis
* code style

* [PR-1](https://github.com/ITK-Leantime/project-overview/pull/1)
  * Basic plugin, prints ids of tasks and a headline
  * Menu entry in register.php
  * Language support, very copy pasted but with links to sources

[Unreleased]: https://github.com/olivierlacan/keep-a-changelog/compare/3.1.0...HEAD
[3.1.0]: https://github.com/olivierlacan/keep-a-changelog/compare/3.0.3...3.1.0
[3.0.3]: https://github.com/olivierlacan/keep-a-changelog/compare/3.0.2...3.0.3
[3.0.2]: https://github.com/olivierlacan/keep-a-changelog/compare/3.0.1...3.0.2
[3.0.1]: https://github.com/olivierlacan/keep-a-changelog/compare/3.0.0...3.0.1
[3.0.0]: https://github.com/olivierlacan/keep-a-changelog/compare/2.3.0...3.0.0
[2.3.0]: https://github.com/olivierlacan/keep-a-changelog/compare/2.2.1...2.3.0
[2.2.1]: https://github.com/olivierlacan/keep-a-changelog/compare/2.2.0...2.2.1
[2.2.0]: https://github.com/olivierlacan/keep-a-changelog/compare/2.1.5...2.2.0
[2.1.5]: https://github.com/olivierlacan/keep-a-changelog/compare/2.1.4...2.1.5
[2.1.4]: https://github.com/olivierlacan/keep-a-changelog/compare/2.1.3...2.1.4
[2.1.3]: https://github.com/olivierlacan/keep-a-changelog/compare/2.1.2...2.1.3
[2.1.2]: https://github.com/olivierlacan/keep-a-changelog/compare/2.1.1...2.1.2
[2.1.1]: https://github.com/olivierlacan/keep-a-changelog/compare/2.1.0...2.1.1
[2.1.0]: https://github.com/olivierlacan/keep-a-changelog/compare/2.0.3...2.1.0
[2.0.3]: https://github.com/olivierlacan/keep-a-changelog/compare/2.0.2...2.0.3
[2.0.2]: https://github.com/olivierlacan/keep-a-changelog/compare/2.0.1...2.0.2
[2.0.1]: https://github.com/olivierlacan/keep-a-changelog/compare/v2.0.0...2.0.1
[2.0.0]: https://github.com/olivierlacan/keep-a-changelog/compare/v1.0.0...v2.0.0
[1.0.0]: https://github.com/olivierlacan/keep-a-changelog/releases/tag/v1.0.0
