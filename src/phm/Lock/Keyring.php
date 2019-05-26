<?php

namespace phm\Lock;

use phm\Lock\Mutex;
use phm\SharedMemory;

class Keyring implements \Countable
{
	protected $mutex;
	protected $keyring;

	public function __construct(Mutex $mutex, SharedMemory $shm)
	{
		$this->mutex = $mutex;
		$this->keyring = $shm;

		$this->mutex->acquire();
		if ( ! isset($this->keyring->keys))
		{
			try
			{
				$this->keyring->keys = array();
				$this->keyring->identifiers = array();
			}
			catch (\Exception $e)
			{
				$this->mutex->release();
				throw $e;
			}
		}
		$this->mutex->release();
	}

	public function count()
	{
		return count($this->keyring->keys);
	}

	public function stat()
	{
		$array = array();
		foreach ($this->keyring->identifiers as $identifier => $key)
		{
			$array[$identifier] = array_merge(
				array('key' => $key, 'key_hex' => '0x'.dechex($key)),
				$this->keyring->keys[$key]
			);
		}
		return $array;
	}

	public static function generateKey()
	{
		return rand(1, 0xffffffff);
	}

	public function getKey($identifier, $regenerate = false)
	{
		$identifiers = $this->keyring->identifiers;

		if ($regenerate || ! isset($identifiers[$identifier]))
		{
			return $this->addKey($identifier, $regenerate);
		}
		else
		{
			return $identifiers[$identifier];
		}
	}

	public function addKey($identifier, $overwrite = false)
	{
		$this->mutex->acquire();

		try
		{
			$identifiers = $this->keyring->identifiers;
			$keys = $this->keyring->keys;

			if (isset($identifiers[$identifier]) && ! $overwrite) {
				throw new \Exception("Cannot overwrite existing key $identifier");
			}

			if (isset($identifiers[$identifier]) && $overwrite) {
				unset($keys[$identifiers[$identifier]]);
			}

			do {
				$key = self::generateKey();
			}
			while (isset($keys[$key]));

			list($file, $line, $caller) = $this->getCaller();

			$identifiers[$identifier] = $key;
			$keys[$key] = array(
				'owner'   => posix_getpid(),
				'created' => time(),
				'file'    => $file,
				'line'    => $line,
				'caller'  => $caller,
			);

			$this->keyring->identifiers = $identifiers;
			$this->keyring->keys = $keys;
		}
		catch (\Exception $e)
		{
			$this->mutex->release();
			throw $e;
		}

		$this->mutex->release();
		
		return $key;
	}

	public function removeIdentifier($identifier)
	{
		$this->mutex->acquire();

		try
		{
			$identifiers = $this->keyring->identifiers;
			$keys = $this->keyring->keys;

			if (isset($identifiers[$identifier]))
			{
				$key = $identifiers[$identifier];
				unset($keys[$key]);
			}

			unset($identifiers[$identifier]);

			$this->keyring->identifiers = $identifiers;
			$this->keyring->keys = $keys;
		}
		catch (\Exception $e)
		{
			$this->mutex->release();
			throw $e;
		}

		$this->mutex->release();
	}

	public function removeKey($key)
	{
		$this->mutex->acquire();

		try
		{
			$identifiers = array_flip($this->keyring->identifiers);
			$keys = $this->keyring->keys;

			unset($identifiers[$key]);
			unset($keys[$key]);

			$this->keyring->identifiers = array_flip($identifiers);
			$this->keyring->keys = $keys;
		}
		catch (\Exception $e)
		{
			$this->mutex->release();
			throw $e;
		}

		$this->mutex->release();
	}

	public function delete()
	{
		$this->mutex->acquire();

		try
		{
			$this->keyring->delete();
		}
		catch (Exception $e)
		{
			// Ignore momentarily
		}

		$this->mutex->release();
		$this->mutex->delete();

		if (isset($e))
		{
			throw $e;
		}
	}

	protected function getCaller()
	{
		$trace_options = PHP_VERSION_ID >= 50306
		               ? DEBUG_BACKTRACE_IGNORE_ARGS
		               : false;
		$trace = debug_backtrace($trace_options);

		$dir = dirname(dirname(__FILE__));
		$count = count($trace);
		for ($i = 0; $i < $count; $i++)
		{
			if (empty($trace[$i]['file']) || stripos($trace[$i]['file'], $dir) !== FALSE)
			{
				unset($trace[$i]);
			}
		}

		$caller = array_shift($trace);
		$file = $caller['file'];
		$line = $caller['line'];
		$class = isset($caller['class'])
		       ? $caller['class'].$caller['type'].$caller['function'].'()'
		       : $caller['function'].'()';

		return array($file, $line, $class);
	}
}

