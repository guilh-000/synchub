<?php

namespace App\Integrations\WooCommerce;

use RuntimeException;

/**
 * The store could not be reached or rejected a whole request (as opposed to
 * a single item of a batch, which comes back in the BatchResult).
 */
class WooCommerceException extends RuntimeException {}
