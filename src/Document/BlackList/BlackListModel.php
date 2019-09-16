<?php

namespace App\Document\BlackList;

use ODM\DocumentManager\DocumentManagerFactory;
use Schema\BlackList\Record;

class BlackListModel
{
    /**
     * @var \ODM\DocumentManager\DocumentManager
     */
    protected $dm_black_list;

    /**
     * DescriptionModel constructor.
     * @param DocumentManagerFactory $dm
     */
    public function __construct(DocumentManagerFactory $dm)
    {
        $this->dm_black_list = $dm->init(Record::class);
    }

    /**
     * @param $id
     * @return null|Record
     */
    public function findOneById($id)
    {
        return $this->dm_black_list->findOne(['_id' => $id]);
    }

    /**
     * @return Record[]
     */
    public function findAll()
    {
        return $this->dm_black_list->find();
    }


    /**
     * @param int $type
     * @return Record[]
     */
    public function findByType(int $type)
    {
        return $this->dm_black_list->find(['type' => (int)$type]);
    }

    /**
     * @param Record $obj
     * @return Record
     */
    public function create(Record $obj)
    {
        $this->dm_black_list->insert($obj);

        return $obj;
    }

    /**
     * @param Record $obj
     * @return Record
     */
    public function update(Record $obj)
    {
        $this->dm_black_list->update($obj);

        return $obj;
    }
}
