<?php

namespace App\Services\Ai;

use RuntimeException;

/** Message that can be shown to the user as it is (missing key, limit reached, provider error) */
class AiException extends RuntimeException {}
