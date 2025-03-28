<?php

/**
 * @file jobs/citation/RetrieveStructured.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RetrieveStructured
 *
 * @ingroup jobs
 *
 * @brief Job for retrieving structured citations from external services.
 */

namespace PKP\jobs\citation;

use APP\facades\Repo;
use PKP\citation\job\RetrieveHandler;
use PKP\job\exceptions\JobException;
use PKP\jobs\BaseJob;

class RetrieveStructured extends BaseJob
{
    protected int $publicationId;

    public function __construct(int $publicationId)
    {
        parent::__construct();

        $this->publicationId = $publicationId;
    }

    public function handle(): void
    {
        $publication = Repo::publication()->get($this->publicationId);

        if (!$this->publicationId || !$publication) {
            throw new JobException(JobException::INVALID_PAYLOAD);
        }

        $inboundHandler = new RetrieveHandler();
        $inboundHandler->execute($this->publicationId);
    }
}
