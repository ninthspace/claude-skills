<?php

declare(strict_types=1);

namespace CloudEnvironments;

use RuntimeException;

/** `apply` refused the plan before making any call. */
final class ApplyRefused extends RuntimeException {}
