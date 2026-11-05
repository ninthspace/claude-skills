<?php

declare(strict_types=1);

namespace CloudEnvironments;

use RuntimeException;

/** A Cloud CLI call failed, or returned something other than what was asked for. */
final class CloudError extends RuntimeException {}
