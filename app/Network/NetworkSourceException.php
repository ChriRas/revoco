<?php

declare(strict_types=1);

namespace App\Network;

use RuntimeException;

/** One source of the network data could not be fetched, parsed or validated. */
final class NetworkSourceException extends RuntimeException {}
