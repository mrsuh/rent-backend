<?php

namespace AppBundle\Queue\Message;

use Schema\Note\Note;
use Schema\Parse\Record\Source;

class CollectMessage
{
    private $id;

    private $note;

    /**
     * @var Source
     */
    private $source;

    public function __construct()
    {
        $this->id = uniqid();
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @param string $id
     * @return $this
     */
    public function setId(string $id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return Source|null
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * @param Source $source
     * @return $this
     */
    public function setSource(Source $source)
    {
        $this->source = $source;

        return $this;
    }

    /**
     * @return Note
     */
    public function getNote()
    {
        return $this->note;
    }

    /**
     * @param $note
     * @return $this
     */
    public function setNote($note)
    {
        $this->note = $note;

        return $this;
    }
}