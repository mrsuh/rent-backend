<?php

namespace AppBundle\Model\Logic\Collector;

use AppBundle\Exception\ParseException;
use AppBundle\Model\Logic\Parser\ParserFactory;
use AppBundle\Request\VkPublicRequest;
use AppBundle\Storage\FileStorage;
use Monolog\Logger;
use Schema\Parse\Record\Source;

class VkWallCollector implements CollectorInterface
{
    private $request;
    private $logger;
    private $storage;
    private $parser_factory;
    private $period;

    private $unique_ids;

    /**
     * VkWallCollector constructor.
     * @param VkPublicRequest $request
     * @param ParserFactory   $parser_factory
     * @param Logger          $logger
     * @param string          $file_dir
     * @param string          $period
     */
    public function __construct(
        VkPublicRequest $request,
        ParserFactory $parser_factory,
        Logger $logger,
        string $file_dir,
        string $period
    )
    {
        $this->request    = $request;
        $this->logger     = $logger;
        $this->storage    = new FileStorage($file_dir);
        $this->period     = $period;
        $this->unique_ids = [];

        $this->parser_factory = $parser_factory;
    }

    /**
     * @param Source $source
     * @return string
     */
    private function getConfigName(Source $source)
    {
        return 'config_' . $source->getId();
    }

    /**
     * @param Source $source
     * @return VkWallConfig
     */
    private function getConfigFromFile(Source $source)
    {
        $config_name = $this->getConfigName($source);
        $new_config  = (new VkWallConfig())->setOffset(0)->setFinish(false);

        if (!$this->storage->exists($config_name)) {

            return $new_config;
        }

        $instance = $this->storage->get($config_name);

        $config = unserialize($instance);

        if (!($config instanceof VkWallConfig)) {

            return $new_config;
        }

        return $config;
    }

    /**
     * @param Source       $source
     * @param VkWallConfig $config
     * @return bool
     */
    private function setConfigToFile(Source $source, VkWallConfig $config)
    {
        $config_name = $this->getConfigName($source);

        return $this->storage->put($config_name, serialize($config));
    }

    /**
     * @param Source $source
     * @return array
     * @throws ParseException
     */
    public function collect(Source $source)
    {
        $this->logger->debug('Processing collect...', [
            'source_id'   => $source->getId(),
            'source_type' => $source->getType(),
        ]);

        try {

            $params = json_decode($source->getParameters(), true);

            if (!is_array($params)) {

                $this->logger->error('Source params has invalid json', [
                    'source_id'   => $source->getId(),
                    'source_type' => $source->getType(),
                    'parameters'  => $source->getParameters()
                ]);

                return [];
            }

            foreach (['owner_id', 'count'] as $key) {
                if (!array_key_exists($key, $params)) {
                    $this->logger->error('Source params has not key', [
                        'key'         => $key,
                        'source_id'   => $source->getId(),
                        'source_type' => $source->getType(),
                        'parameters'  => $source->getParameters()
                    ]);

                    return [];
                }
            }

            $config = $this->getConfigFromFile($source);

            if ($config->isFinish()) {
                $this->setConfigToFile($source, $config->setOffset(0)->setFinish(false));

                $this->logger->debug('There is no more notes', [
                    'source_id'   => $source->getId(),
                    'source_type' => $source->getType()
                ]);

                return [];
            }

            usleep(200000);

            $this->logger->debug('Collect requesting...', [
                'source_id'   => $source->getId(),
                'source_type' => $source->getType(),
                'params'      => $params
            ]);

            $params['offset'] = $config->getOffset();

            $items_raw = $this->request($source, $params);

            $items     = [];
            $finish    = false;
            $timestamp = (new \DateTime())->modify('- ' . $this->period)->getTimestamp();

            foreach ($items_raw as $item) {

                if (!array_key_exists('id', $item)) {
                    $this->logger->error('Item has not key "id"', [
                        'source_id'   => $source->getId(),
                        'source_type' => $source->getType(),
                        'params'      => $params
                    ]);

                    break;
                }

                $unique_id = $source->getId() . '-' . $item['id'];

                if (in_array($unique_id, $this->unique_ids)) {

                    continue;
                }

                $this->unique_ids[] = $unique_id;

                if (
                    array_key_exists('marked_as_ads', $item) &&
                    $item['marked_as_ads']
                ) {

                    continue;
                }

                if (
                    $timestamp > $item['date'] &&
                    array_key_exists('is_pinned', $item) &&
                    $item['is_pinned']
                ) {

                    continue;
                }

                if ($timestamp > $item['date']) {
                    $finish = true;

                    break;
                }

                $items[] = $item;
            }

            $config
                ->setFinish($finish)
                ->setOffset($finish ? 0 : $config->getOffset() + 10);

            $this->setConfigToFile($source, $config);

            $this->logger->debug('Processing collect... done', [
                'source_id'   => $source->getId(),
                'source_type' => $source->getType(),
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Processing collect error', [
                'source_id'   => $source->getId(),
                'source_type' => $source->getType(),
                'error'       => $e->getMessage()
            ]);

            return [];
        }

        $notes = [];
        foreach ($items as $item) {

            $parser = $this->parser_factory->init($source, $item);

            $id      = $parser->id();
            $notes[] =
                (new RawData())
                    ->setId($source->getId() . '-' . $id)
                    ->setLink($parser->link($id))
                    ->setTimestamp($parser->timestamp())
                    ->setContent($item);
        }

        return $notes;
    }

    /**
     * @param Source $source
     * @param array  $params
     * @return array
     */
    private function request(Source $source, array $params)
    {
        $response_raw = $this->request->getWallRecords($params);

        $contents = $response_raw->getBody()->getContents();

        $this->logger->debug('Collect requesting... done', [
            'source_id'   => $source->getId(),
            'source_type' => $source->getType(),
            'params'      => $params
        ]);

        $data = json_decode($contents, true);

        if (!is_array($data)) {

            $this->logger->error('Response has invalid json', [
                'source_id'   => $source->getId(),
                'source_type' => $source->getType(),
                'response'    => $contents
            ]);

            return [];
        }

        if (!array_key_exists('response', $data)) {

            $this->logger->error('Response has not key "response"', [
                'source_id'   => $source->getId(),
                'source_type' => $source->getType(),
                'response'    => $data
            ]);

            return [];
        }

        if (!array_key_exists('items', $data['response'])) {

            $this->logger->error('Response has not key "items"', [
                'source_id'   => $source->getId(),
                'source_type' => $source->getType(),
                'response'    => $data
            ]);

            return [];
        }

        return $data['response']['items'];
    }
}

