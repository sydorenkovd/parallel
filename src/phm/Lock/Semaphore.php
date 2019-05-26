<?php

namespace phm\Lock;

use phm\Lock\Mutex;
use phm\SharedMemory;
use phm\MessageQueue;
use phm\Exception\SemaphoreException;

class Semaphore
{
	const SEM_ACQUIRE = 2;

	protected $max_count;
	protected $mutex;
	protected $shm;
	protected $queue;

	/**
	 * @param phm\Mutex $mutex
	 * @param phm\SharedMemory $shm
	 * @param phm\MessageQueue $queue
	 * @param integer $max_count
	 */
	public function __construct(Mutex $mutex, SharedMemory $shm, MessageQueue $queue, $max_count = NULL)
	{
		$this->mutex = $mutex;
		$this->shm   = $shm;
		$this->queue = $queue;

		if (isset($this->shm->max_count))
		{
			$this->max_count = $this->shm->max_count;
		}
		else
		{
			$this->max_count = $max_count;
			$this->mutex->acquire();
			$this->shm->max_count = $max_count;
			$this->shm->value = $max_count;
			$this->mutex->release();
		}
	}

	/**
	 * @return array
	 */
	public function getKeys()
	{
		return array(
			'mutex' => $this->mutex->getKey(),
			'shm'   => $this->shm->getKey(),
			'queue' => $this->queue->getKey(),
		);
	}

	/**
	 * @return integer
	 */
	public function read()
	{
		$this->mutex->acquire();
		$value = $this->shm->value;
		$this->mutex->release();
		return $value;
	}

	/**
	 * @throws phm\Exception\SemaphoreException
	 */
	public function acquire()
	{
		while (true)
		{
			$this->mutex->acquire();

			if ($this->shm->value > 0)
			{
				$this->shm->value--;
				$this->mutex->release();
				return;
			}
			else
			{
				$this->mutex->release();

				$this->queue->receive(Semaphore::SEM_ACQUIRE, MessageQueue::BLOCKING);
			}

		}
	}

	/**
	 * Alas for acquire(). Decrements the semaphore.
	 */
	public function down()
	{
		$this->acquire();
	}

	public function release()
	{
		$this->mutex->acquire();

		// Increment the semaphore
		$value = $this->shm->value;
		$this->shm->value++;

		if ($value == 0)
		{
			$this->queue->send(Semaphore::SEM_ACQUIRE, Semaphore::SEM_ACQUIRE);
		}

		$this->mutex->release();
	}

	public function up()
	{
		$this->release();
	}

	public function delete()
	{
		if ($this->mutex)
		{
			try
			{
				$this->mutex->delete();
			}
			catch (SemaphoreException $e)
			{

			}
		}

		if ($this->shm)
		{
			try
			{
				$this->shm->delete();
			}
			catch (SharedMemoryException $e)
			{

			}
		}

		if ($this->queue)
		{
			try
			{
				$this->queue->delete();
			}
			catch (MessageQueueException $e)
			{

			}
		}

		$this->mutex = null;
		$this->shm   = null;
		$this->queue = null;
	}
}

