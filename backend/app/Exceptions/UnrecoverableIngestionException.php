<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by LogIngestionService when a message cannot be retried:
 * unknown application slug, malformed payload, or other business-rule
 * violations. The ConsumeLogs consumer catches this and drops the message
 * (ACK without processing) to avoid blocking the queue.
 */
final class UnrecoverableIngestionException extends RuntimeException {}
