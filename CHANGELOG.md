# Changelog

## Unreleased

* [PR-4](https://github.com/ITK-Leantime/project-overview/pull/6)
  * add ```ticket.planHours``` and ```ticket.hourRemaining``` to sql query, so the data is shown in the table. 

* [PR-3](https://github.com/ITK-Leantime/project-overview/pull/5)
  * Add burger menu item (change ```<i``` to ```<span```)
  * Limit search on dueDate (dateToFinish in db)
  * Cast dueDate in sql query, because the time is not necessary for the filter

* [PR-2](https://github.com/ITK-Leantime/project-overview/pull/3)
* Project overview plugin
  * services file with install/uninstall and methods for passing data from the repo to the controller
  * a controller that feeds data to the template
  * a template
  * a repository that handles the sql queries
  * A filter/search that uses a redirect
* code analysis
* code style

* [PR-1](https://github.com/ITK-Leantime/project-overview/pull/1)
  * Basic plugin, prints ids of tasks and a headline
  * Menu entry in register.php
  * Language support, very copy pasted but with links to sources
