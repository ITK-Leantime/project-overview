# Project overview plugin

A plugin for displaying tasks, "like in Jira", displaying all tasks regardless of project/user.

## Development

Clone this repository into your Leantime plugins folder:

``` shell
git clone https://github.com/ITK-Leantime/project-overview.git app/Plugins/ProjectOverview
```

### Coding standards

``` shell
docker run --tty --interactive --rm --volume ${PWD}:/app itkdev/php8.1-fpm:latest composer install
docker run --tty --interactive --rm --volume ${PWD}:/app itkdev/php8.1-fpm:latest composer coding-standards-apply
docker run --tty --interactive --rm --volume ${PWD}:/app itkdev/php8.1-fpm:latest composer coding-standards-check
```

```shell
docker run --rm -v "$(pwd):/work" tmknom/prettier --write assets
docker run --rm -v "$(pwd):/work" tmknom/prettier --check assets
```

```shell
docker run --rm --volume $PWD:/md peterdavehello/markdownlint markdownlint --ignore vendor --ignore LICENSE.md '**/*.md' --fix
docker run --rm --volume $PWD:/md peterdavehello/markdownlint markdownlint --ignore vendor --ignore LICENSE.md '**/*.md'
```

### Code analysis

```shell
docker run --tty --interactive --rm --volume ${PWD}:/app itkdev/php8.1-fpm:latest composer install
docker run --tty --interactive --rm --volume ${PWD}:/app itkdev/php8.1-fpm:latest composer code-analysis
```

### Test release build

``` shell
docker compose build && docker compose run --rm php bin/create-release dev-test
```
