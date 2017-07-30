<?php

namespace AppBundle\Model\Logic\Filter;

use ODM\DocumentManager\DocumentManagerFactory;
use Schema\Note\Note;

class ExternalIdUniqueFilter
{
    /**
     * @var \ODM\DocumentManager\DocumentManager
     */
    private $dm_note;

    /**
     * ExternalIdUniqueFilter constructor.
     * @param DocumentManagerFactory $dm_factory
     */
    public function __construct(DocumentManagerFactory $dm_factory)
    {
        $this->dm_note = $dm_factory->init(Note::class);
    }

    /**
     * @param Note $note
     * @return Note[]
     */
    public function findDuplicates(Note $note)
    {
        return $this->dm_note->find([
            'external_id' => (string)$note->getExternalId(),
            '_id'         => ['$ne' => $note->getId()]
        ]);
    }
}