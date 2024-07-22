<?php

namespace Leantime\Plugins\ProjectOverview\Services;

class ProjectOverview {
  private static $assets = [
    // source => target
    __DIR__. '/../assets/pronject-overview.css' => APP_ROOT . '/public/dist/css/project-overview.css',
  ];

  /**
   * Install plugin.
   *
   * @return void
   */
  public function install(): void
  {
    foreach (static::$assets as $source => $target) {
      if (file_exists($target)) {
        unlink($target);
      }
      symlink($source, $target);
    }
  }

  /**
   * Uninstall plugin.
   *
   * @return void
   */
  public function uninstall(): void
  {
    foreach (static::$assets as $target) {
      if (file_exists($target)) {
        unlink($target);
      }
    }
  }
}
