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

```shell
docker compose build
docker compose run --rm php npm install
docker compose run --rm php npm run coding-standards-apply
```

```shell
docker run --rm --tty --volume "$PWD:/app" --workdir /app peterdavehello/shellcheck shellcheck bin/create-release
docker run --rm --tty --volume "$PWD:/app" --workdir /app peterdavehello/shellcheck shellcheck bin/deploy
```

### Code analysis

```shell
docker run --tty --interactive --rm --volume ${PWD}:/app itkdev/php8.1-fpm:latest composer install
docker run --tty --interactive --rm --volume ${PWD}:/app itkdev/php8.1-fpm:latest composer code-analysis
```

## Test release build

``` shell
docker compose build && docker compose run --rm php bash bin/create-release dev-test
```

The create-release script replaces `@@VERSION@@` in
[register.php](https://github.com/ITK-Leantime/project-overview/blob/main/register.php#L56) and
[Services/ProjectOverview.php](https://github.com/ITK-Leantime/project-overview/blob/main/Services/ProjectOverview.php#L18-L19)
with the tag provided (in the above it is `dev-test`).

## Deploy

The deploy script downloads a [release](https://github.com/ITK-Leantime/project-overview/releases) from Github and
unzips it. The script should be passed a tag as argument. In the process the script deletes itself, but the script
finishes because it [is still in memory](https://linux.die.net/man/3/unlink).
