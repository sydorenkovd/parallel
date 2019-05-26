<?php

namespace phm\Process;


/**
 * 
 * @author Jonathon Hill
 * @package phm
 * @subpackage Process
 */
class Factory
{
	/**
	 * @param integer $id        Process ID
	 * @param integer $parent_id Parent process ID
	 * @param integer $user_id   User ID
	 * @param integer $group_id  Group ID
	 * @return phm\Process
	 */
	public function newInstance($id, $parent_id = NULL, $user_id = NULL, $group_id = NULL)
	{
		$process = new \phm\Process();
		$process->id        = $id;
		$process->parent_id = $parent_id;
		$process->user_id   = $user_id;
		$process->group_id  = $group_id;
		return $process;
	}

	/**
	 * @return phm\Process
	 */
	public function currentInstance()
	{
		return $this->newInstance(posix_getpid(), posix_getppid(), posix_getuid(), posix_getgid());
	}

	/**
	 * @return phm\Process
	 */
	public function fork()
	{
		$pid = pcntl_fork();

		if ($pid > 0)
		{
			return $this->newInstance($pid, posix_getpid());
		}
		elseif ($pid === 0)
		{
			return $this->currentInstance();
		}
		else
		{
			throw new \Exception('failed');
		}
	}
}

