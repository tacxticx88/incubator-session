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

use Phalcon\Cache\Backend\Aerospike as AerospikeDb;
use Phalcon\Cache\Frontend\Data as FrontendData;
use Phalcon\Session\Adapter\AbstractAdapter;
use Phalcon\Session\AdapterInterface;
use Phalcon\Session\Exception;

/**
 * Phalcon\Session\Adapter\Aerospike
 *
 * This adapter store sessions in Aerospike
 *
 * <code>
 * use Phalcon\Session\Adapter\Aerospike as AerospikeSession;
 *
 * $session = new AerospikeSession(
 *     [
 *         'hosts' => [
 *             [
 *                 'addr' => '127.0.0.1',
 *                 'port' => 3000,
 *             ],
 *         ],
 *         'persistent' => true,
 *         'namespace'  => 'test',
 *         'prefix'     => 'session_',
 *         'lifetime'   => 8600,
 *         'uniqueId'   => '3Hf90KdjQ18',
 *         'options'    => [
 *             \Aerospike::OPT_CONNECT_TIMEOUT => 1250,
 *             \Aerospike::OPT_WRITE_TIMEOUT   => 1500,
 *         ],
 *     ]
 * );
 *
 * $session->start();
 *
 * $session->set('var', 'some-value');
 *
 * echo $session->get('var');
 * </code>
 */
class Aerospike extends AbstractAdapter
{
    /**
     * The Aerospike DB
     *
     * @var AerospikeDb
     */
    protected $db;

    /**
     * Default Aerospike namespace
     *
     * @var string
     */
    protected $namespace = 'test';

    /**
     * The Aerospike Set for store sessions
     *
     * @var string
     */
    protected $set = 'session';

    /**
     * Key prefix
     *
     * @var string
     */
    protected $prefix = '';

    /**
     * Session lifetime
     *
     * @var int
     */
    protected $lifetime = 8600;

    /**
     * Phalcon\Session\Adapter\Aerospike constructor
     *
     * @param array $options Constructor options
     *
     * @throws \Phalcon\Session\Exception
     * @throws \Phalcon\Cache\Exception
     */
    public function __construct(array $options)
    {
        if (!isset($options['hosts']) || !is_array($options['hosts'])) {
            throw new Exception('No hosts given in options');
        }

        if (isset($options['namespace'])) {
            $this->namespace = $options['namespace'];

            unset($options['namespace']);
        }

        if (isset($options['prefix'])) {
            $this->prefix = $options['prefix'];
        }

        if (isset($options['set']) && !empty($options['set'])) {
            $this->set = $options['set'];

            unset($options['set']);
        }

        if (isset($options['lifetime'])) {
            $this->lifetime = $options['lifetime'];
        }

        $persistent = false;
        if (isset($options['persistent'])) {
            $persistent = (bool) $options['persistent'];
        }

        $opts = [];
        if (isset($options['options']) && is_array($options['options'])) {
            $opts = $options['options'];
        }

        $this->db = new AerospikeDb(
            new FrontendData(
                [
                    'lifetime' => $this->lifetime,
                ]
            ),
            [
                'hosts'      => $options['hosts'],
                'namespace'  => $this->namespace,
                'set'        => $this->set,
                'prefix'     => $this->prefix,
                'persistent' => $persistent,
                'options'    => $opts,
            ]
        );

        parent::__construct($options);

        session_set_save_handler(
            [$this, 'open'],
            [$this, 'close'],
            [$this, 'read'],
            [$this, 'write'],
            [$this, 'destroy'],
            [$this, 'gc']
        );
    }

    /**
     * Gets the Aerospike instance.
     *
     * @return \Aerospike
     */
    public function getDb()
    {
        return $this->db->getDb();
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    public function open()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    public function close()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @param string $sessionId Session variable name
     * @return string
     */
    public function read($sessionId)
    {
        return $this->db->get($sessionId, $this->lifetime);
    }

    /**
     * {@inheritdoc}
     *
     * @param string $sessionId Session variable name
     * @param string $data      Session data
     */
    public function write($sessionId, $data)
    {
        return $this->db->save($sessionId, $data, $this->lifetime);
    }

    /**
     * {@inheritdoc}
     *
     * @param string $sessionId Session variable name [Optional]
     * @return bool
     */
    public function destroy($sessionId = null)
    {
        if (null === $sessionId) {
            $sessionId = $this->getId();
        }

        if (!isset($_SESSION) || !is_array($_SESSION)) {
            $_SESSION = [];
        }

        foreach ($_SESSION as $id => $key) {
            unset($_SESSION[$id]);
        }

        return $this->db->delete($sessionId);
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    public function gc()
    {
        return true;
    }
}
