<?php

declare(strict_types=1);

namespace CloudEnvironments;

use RuntimeException;

/** A step failed; no later step ran. The message names the steps that ran and the one that failed. */
final class ApplyFailed extends RuntimeException {}
