<?php

/**
 * @file jobs/citation/ExtractPids.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ExtractPids
 *
 * @ingroup jobs
 *
 * @brief Job for extracting PIDs from citations.
 */

namespace PKP\jobs\citation;

use APP\facades\Repo;
use PKP\citation\job\ExtractHandler;
use PKP\job\exceptions\JobException;
use PKP\jobs\BaseJob;

class ExtractPids extends BaseJob
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

        $extractHandler = new ExtractHandler();
        $extractHandler->execute($this->publicationId);
    }
}
