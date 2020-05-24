<?php

/**
 * This file is part of the Phalcon Migrations.
 *
 * (c) Phalcon Team <team@phalcon.io>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Phalcon\Incubator\Session\Adapter;

use Phalcon\Session\Adapter\AbstractAdapter;
use Phalcon\Session\Exception;

/**
 * Mongo adapter for Phalcon\Session
 */
class Mongo extends AbstractAdapter
{
    /**
     * Current session data
     *
     * @var string
     */
    protected $data;

    /**
     * Class constructor.
     *
     * @param array $options
     * @throws Exception
     */
    public function __construct($options = null)
    {
        if (!isset($options['collection'])) {
            throw new Exception("The parameter 'collection' is required");
        }

        session_set_save_handler(
            [$this, 'open'],
            [$this, 'close'],
            [$this, 'read'],
            [$this, 'write'],
            [$this, 'destroy'],
            [$this, 'gc']
        );

        parent::__construct($options);
    }

    /**
     * {@inheritdoc}
     *
     * @return boolean
     */
    public function open()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @param string $sessionId
     * @return string
     */
    public function read($sessionId)
    {
        $sessionData = $this->getCollection()->findOne(
            [
                '_id' => $sessionId,
            ]
        );

        if (!isset($sessionData['data'])) {
            return '';
        }

        $this->data = $sessionData['data'];

        return $sessionData['data'];
    }

    /**
     * {@inheritdoc}
     *
     * @param string $sessionId
     * @param string $sessionData
     * @return bool
     */
    public function write($sessionId, $sessionData)
    {
        if ($this->data === $sessionData) {
            return true;
        }

        $sessionData = [
            '_id' => $sessionId,
            'modified' => new \MongoDate(),
            'data' => $sessionData,
        ];

        $this->getCollection()->save($sessionData);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function destroy($sessionId = null)
    {
        if (is_null($sessionId)) {
            $sessionId = $this->getId();
        }

        $this->data = null;

        $remove = $this->getCollection()->remove(
            [
                '_id' => $sessionId,
            ]
        );

        return (bool)$remove['ok'];
    }

    /**
     * {@inheritdoc}
     * @param string $maxLifetime
     */
    public function gc($maxLifetime)
    {
        $minAge = new \DateTime();

        $minAge->sub(
            new \DateInterval(
                'PT' . $maxLifetime . 'S'
            )
        );

        $minAgeMongo = new \MongoDate(
            $minAge->getTimestamp()
        );

        $query = [
            'modified' => [
                '$lte' => $minAgeMongo,
            ],
        ];

        $remove = $this->getCollection()->remove($query);

        return (bool)$remove['ok'];
    }

    /**
     * @return \MongoCollection
     */
    protected function getCollection()
    {
        $options = $this->getOptions();

        return $options['collection'];
    }
}
