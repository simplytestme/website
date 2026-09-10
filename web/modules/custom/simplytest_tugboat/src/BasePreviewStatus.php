<?php

declare(strict_types=1);

namespace Drupal\simplytest_tugboat;

/**
 * How a base preview is holding up.
 */
enum BasePreviewStatus: string {

  /**
   * A usable base exists and a newer one replaced it within the last cycle.
   */
  case Ok = 'ok';

  /**
   * Nothing carrying the name can be built on, so builds start from scratch.
   */
  case Missing = 'missing';

  /**
   * The most recent build failed. An older base is still being built on.
   */
  case Failed = 'failed';

  /**
   * The base is usable, but nothing has replaced it in two rebuild cycles.
   */
  case Stale = 'stale';

}
