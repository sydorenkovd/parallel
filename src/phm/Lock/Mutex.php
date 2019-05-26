<?php

namespace phm\Lock;

class Mutex
{
	/**
	 * System V IPC key used to access a semaphore
	 * @var integer $key
	 */
	protected $key;

	/**
	 * System V semaphore ID
	 * @var integer $sem
	 */
	protected $sem;

	/**
	 * @var array $acquisitions
	 */
	protected $acquisitions = array();

	/**
	 * @param string $key System V IPC key
	 * @param string $maxCount
	 */
	public function __construct($key, $maxCount = 1)
	{
		$this->key = $key;
		$this->sem = sem_get($key, $maxCount);
		if ($this->sem === FALSE)
		{
			throw new \Exception("Could not open semaphore $key");
		}
	}

	/**
	 * IPC key
	 * @return integer
	 */
	public function getKey()
	{
		return $this->key;
	}

	/**
	 * @throws \LogicException
	 */
	public function acquire()
	{
		if ( ! empty($this->acquisitions)) {
			throw new \LogicException('Cannot acquire a mutex without releasing it first');
		}

		if (@sem_acquire($this->sem)) {
			array_push($this->acquisitions, microtime(TRUE));
		}
	}

	/**
	 * @throws \LogicException
	 */
	public function release()
	{
		if (empty($this->acquisitions)) {
			throw new \LogicException('Cannot release a mutex without acquiring it first');
		}

		if (@sem_release($this->sem)) {
			array_pop($this->acquisitions);
		}
	}

	/**
	 * Получите массив временных меток для каждого захвата этого семафора текущим процессом
	 * @return array
	 */
	public function getAcquisitions()
	{
		return $this->acquisitions;
	}

	/**
	 * @throws phm\Exception\SemaphoreException
	 */
	public function delete()
	{
		if ( ! @sem_remove($this->sem))
		{
			throw new SemaphoreException("Could not remove semaphore $this->key");
		}
	}
}

