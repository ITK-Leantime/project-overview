# Changelog

## Unreleased

* [PR-5](https://github.com/ITK-Leantime/project-overview/pull/7)
  * Make the side menu "personal", so when on the "project overview"-page, the sidemenu doesn't change to the projects menu.

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
