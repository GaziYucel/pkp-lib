<?php

/**
 * @file classes/citation/Repository.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 *
 * @ingroup citation
 *
 * @brief A repository to find and manage citations.
 */

namespace PKP\citation;

use APP\core\Request;
use APP\submission\Submission;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Bus;
use PKP\citation\filter\CitationListTokenizerFilter;
use PKP\context\Context;
use PKP\jobs\citation\ExtractPids;
use PKP\jobs\citation\RetrieveStructured;
use PKP\plugins\Hook;
use PKP\services\PKPSchemaService;
use PKP\validation\ValidatorFactory;
use Throwable;

class Repository
{
    public DAO $dao;

    /** The name of the class to map this entity to its schema */
    public string $schemaMap = maps\Schema::class;

    protected Request $request;

    /** @var PKPSchemaService<Citation> */
    protected PKPSchemaService $schemaService;

    public function __construct(DAO $dao, Request $request, PKPSchemaService $schemaService)
    {
        $this->dao = $dao;
        $this->request = $request;
        $this->schemaService = $schemaService;
    }

    /** @copydoc DAO::newDataObject() */
    public function newDataObject(array $params = []): Citation
    {
        $object = $this->dao->newDataObject();
        if (!empty($params)) {
            $object->setAllData($params);
        }
        return $object;
    }

    /** @copydoc DAO::exists() */
    public function exists(int $id, ?int $publicationId = null): bool
    {
        return $this->dao->exists($id, $publicationId);
    }

    /** @copydoc DAO::get() */
    public function get(int $id, ?int $contextId = null): ?Citation
    {
        return $this->dao->get($id, $contextId);
    }

    /** @copydoc DAO::getCollector() */
    public function getCollector(): Collector
    {
        return App::make(Collector::class);
    }

    /**
     * Get an instance of the map class for mapping citations to their schema.
     */
    public function getSchemaMap(): maps\Schema
    {
        return app('maps')->withExtensions($this->schemaMap);
    }

    /**
     * Validate properties for a citation
     *
     * Perform validation checks on data used to add or edit a citation.
     *
     * @param Citation|null $citation Citation being edited. Pass `null` if creating a new submission
     * @param array $props A key/value array with the new data to validate
     *
     * @return array A key/value array with validation errors. Empty if no errors
     *
     * @hook Citation::validate [[&$errors, $citation, $props]]
     */
    public function validate(?Citation $citation, array $props): array
    {
        $errors = [];

        //todo: implement validation

        Hook::call('Citation::validate', [&$errors, $citation, $props]);

        return $errors;
    }

    /** @copydoc DAO::insert() */
    public function add(Citation $citation): int
    {
        $id = $this->dao->insert($citation);
        Hook::call('Citation::add', [$citation]);
        return $id;
    }

    /** @copydoc DAO::update() */
    public function edit(Citation $citation, array $params): void
    {
        $newRow = clone $citation;
        $newRow->setAllData(array_merge($newRow->_data, $params));
        Hook::call('Citation::edit', [$newRow, $citation, $params]);
        $this->dao->update($newRow);
    }

    /** @copydoc DAO::delete() */
    public function delete(Citation $citation): void
    {
        Hook::call('Citation::delete::before', [$citation]);
        $this->dao->delete($citation);
        Hook::call('Citation::delete', [$citation]);
    }

    /**
     * Delete a collection of citations
     */
    public function deleteMany(Collector $collector): void
    {
        foreach ($collector->getMany() as $citation) {
            $this->delete($citation);
        }
    }

    /**
     * Get all citations for a given publication.
     *
     * @return array<Citation>
     */
    public function getByPublicationId(int $publicationId): array
    {
        return $this->getCollector()
            ->filterByPublicationId($publicationId)
            ->getMany()
            ->all();
    }

    /**
     * Delete a publication's citations.
     */
    public function deleteByPublicationId(int $publicationId): void
    {
        $this->dao->deleteByPublicationId($publicationId);
    }

    public function getRawCitationsByPublicationId(int $publicationId): string
    {
        $existingRawCitations = [];
        foreach ($this->getByPublicationId($publicationId) as $id => $citation) {
            $existingRawCitations[] = $citation->getData('rawCitation');
        }

        return implode("\n", $existingRawCitations);
    }

    /**
     * Compare existing and new raw citations.
     */
    public function isRawCitationsChanged(array $existingCitations, string $rawCitations): bool
    {
        $existingRawCitations = [];
        foreach ($existingCitations as $id => $citation) {
            $existingRawCitations[] = $citation->getData('rawCitation');
        }

        $citationTokenizer = new CitationListTokenizerFilter();
        $newRawCitations = $citationTokenizer->execute($rawCitations);

        return $existingRawCitations !== $newRawCitations;
    }

    /**
     * Import citations from a raw citation list of the particular publication.
     *
     * @hook Citation::importCitations::after [[$publicationId, $existingCitations, $importedCitations]]
     */
    public function importCitations(int $publicationId, string $rawCitations): void
    {
        $existingCitations = $this->getByPublicationId($publicationId);

        // Tokenize raw citations
        $citationTokenizer = new CitationListTokenizerFilter();
        $citationStrings = $citationTokenizer->execute($rawCitations);

        $importedCitations = [];

        if ($this->isRawCitationsChanged($existingCitations, $rawCitations)) {
            $this->deleteByPublicationId($publicationId);
            if (is_array($citationStrings) && !empty($citationStrings)) {
                foreach ($citationStrings as $seq => $citationString) {
                    if (!empty(trim($citationString))) {
                        $citation = new Citation($citationString);
                        $citation->setData('publicationId', $publicationId);
                        $citation->setData('lastModified', date('Y-m-d H:i:s'));

                        $citation->setSequence($seq + 1);
                        $this->dao->insert($citation);
                        $importedCitations[] = $citation;
                    }
                }
            }

            //todo: check if submission / publication is set to use structured citations
//            Bus::chain([
//                new ExtractPids($publicationId),
//                new RetrieveStructured($publicationId)
//            ])
//                ->catch(function (Throwable $e) {
//                    error_log($e->getMessage());
//                })
//                ->dispatch()
//                ->delay(now()->addSeconds(300));
            dispatch(new ExtractPids($publicationId));
            dispatch(new RetrieveStructured($publicationId));
        }

        Hook::call('Citation::importCitations::after', [$publicationId, $existingCitations, $importedCitations]);
    }

    /**
     * Insert on duplicate update.
     */
    public function updateOrInsert(Citation $citation): int
    {
        return $this->dao->updateOrInsert($citation);
    }
}
