<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

/**
 * The queue endpoint accepts exactly the same payload as synchronous generate.
 */
class QueueDocumentRequest extends GenerateDocumentRequest {}
