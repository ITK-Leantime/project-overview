# Changelog

## [Unreleased]

## [2.0.1] - 2024-09-18

* [PR-12](https://github.com/ITK-Leantime/project-overview/pull/14)
  * Removed additional border-radiusses
  * Use [url](https://developer.mozilla.org/en-US/docs/Web/API/URL/URL) to test if I can fix error that I cannot
    reproduce: Navigation with the date dropdowns, the url is updated with `.`'s instead of `/`'s.

* [PR-11](https://github.com/ITK-Leantime/project-overview/pull/12)
  * Streamline release/deploy
  * Add Markdown Runner to actions
* [PR-10](https://github.com/ITK-Leantime/project-overview/pull/11)
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

[Unreleased]: https://github.com/olivierlacan/keep-a-changelog/compare/2.0.1...HEAD
[2.0.1]: https://github.com/olivierlacan/keep-a-changelog/compare/v2.0.0...2.0.1
[2.0.0]: https://github.com/olivierlacan/keep-a-changelog/compare/v1.0.0...v2.0.0
[1.0.0]: https://github.com/olivierlacan/keep-a-changelog/releases/tag/v1.0.0
