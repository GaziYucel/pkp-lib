<?php

/**
 * @file classes/Models/RetrieveHandler.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RetrieveHandler
 *
 * @ingroup citation
 *
 * @brief Retrieves structured citations from external services.
 */

namespace PKP\citation\job;

use PKP\citation\job\external\openAlex\Inbound as OpenAlexInbound;
use PKP\citation\job\external\orcid\Inbound as OrcidInbound;
use PKP\citation\job\external\crossref\Inbound as CrossrefInbound;

class RetrieveHandler
{
    public function execute(int $publicationId): bool
    {
        $crossref = new CrossrefInbound($publicationId);
        $crossref->execute();

        $openAlex = new OpenAlexInbound($publicationId);
        $openAlex->execute();

        $orcid = new OrcidInbound($publicationId);
        $orcid->execute();

        return true;
    }
}
