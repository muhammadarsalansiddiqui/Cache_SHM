<?php

/**
 * ~~summary~~
 * 
 * ~~description~~
 * 
 * PHP version 5
 * 
 * @category  Cache
 * @package   PEAR2_Cache_SHM
 * @author    Vasil Rangelov <boen.robot@gmail.com>
 * @copyright 2011 Vasil Rangelov
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @version   GIT: $Id$
 * @link      http://pear2.php.net/PEAR2_Cache_SHM
 */
/**
 * The namespace declaration.
 */
namespace PEAR2\Cache\SHM\Adapter;

/**
 * Implements the adapter interface. 
 */
use PEAR2\Cache\SHM\Adapter;
use PEAR2\Cache\SHM;

class APC implements Adapter
{
    protected $persistentId;
    protected static $destructables = array();
    public function __construct($persistentId)
    {
        $this->persistentId = __NAMESPACE__ . '\APC ' . $persistentId;
        if (!apc_add($this->persistentId, 1)) {
            apc_inc($this->persistentId);
        }
        apc_add($this->persistentId . 'locks', array());
        static::$destructables[$this->persistentId] = true;
        register_shutdown_function(
            __CLASS__ . '::destroy', $this->persistentId
        );
    }
    
    public static function destroy($internalPersistentId)
    {
        if (static::$destructables[$internalPersistentId]) {
            apc_dec($internalPersistentId);
            if (apc_fetch($internalPersistentId) === 0) {
                foreach (new APCIterator(
                    'user',
                    '/^' . preg_quote(
                        $internalPersistentId . 'locks ', '/'
                    ) . '/'
                ) as $entry) {
                    apc_delete($entry->key());
                }
                apc_delete($internalPersistentId);
            }
            static::$destructables[$internalPersistentId] = false;
        }
    }
    
    public function __destruct()
    {
        static::destroy($this->persistentId);
    }
    
    public function lock($key, $timeout = null)
    {
        $lock = $this->persistentId . 'locks ' . $key;
        $start = microtime(true);
        while (!apc_add($lock, 1)) {
            if ($timeout !== null && (microtime(true) - $start) > $timeout) {
                return false;
            }
        }
        return true;
    }
    
    public function unlock($key)
    {
        return apc_delete($this->persistentId . 'locks ' . $key);
    }
    
    public function add($key, $value, $ttl = 0)
    {
        return apc_add($this->persistentId . 'values ' . $key, $value, $ttl);
    }
    
    public function set($key, $value, $ttl = 0)
    {
        return apc_store($this->persistentId . 'values ' . $key, $value, $ttl);
    }
    
    public function get($key)
    {
        $fullKey = $this->persistentId . 'values ' . $key;
        if (apc_exists($fullKey)) {
            $value = apc_fetch($fullKey, $success);
            if (!$success) {
                throw new SHM\InvalidArgumentException(
                    'Unable to fetch key. ' .
                    'Key has either just now expired or (if no TTL was set) ' .
                    'is possibly in a race condition with another request.', 100
                );
            }
            return $value;
        }
        throw new SHM\InvalidArgumentException('No such key in cache', 101);
    }
    
    public function delete($key)
    {
        return apc_delete($this->persistentId . 'values ' . $key);
    }
}