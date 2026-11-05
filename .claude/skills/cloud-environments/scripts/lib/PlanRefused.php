<?php

declare(strict_types=1);

namespace CloudEnvironments;

use RuntimeException;

/** The plan cannot be made safely: the organisation differs, or a named resource is missing. */
final class PlanRefused extends RuntimeException {}
