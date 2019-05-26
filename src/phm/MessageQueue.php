<?php

namespace phm;


class MessageQueue implements \Countable
{
	const BLOCKING = 0;
	const NON_BLOCKING = MSG_IPC_NOWAIT;

	/**
	 * @var integer $key
	 */
	protected $key;

	/**
	 * @var resource $shm
	 */
	protected $queue;

	protected $last_message;
	protected $last_message_type;

	/**
	 * @param string $key
	 * @param integer $permissions
	 */
	public function __construct($key, $permissions = 0666)
	{
		$this->key = $key;
		$this->queue = msg_get_queue($key, $permissions);
		if ($this->queue === FALSE)
		{

		}
	}

	/**
	 * Get the IPC key
	 * @return integer
	 */
	public function getKey()
	{
		return $this->key;
	}

	/**
	 * @return boolean
	 */
	public function isConfigurable()
	{
		$data = $this->getStatus();
		return (
			! empty($data) &&
			(
				// The user is root
				posix_getuid() == 0 ||
				// The user owns this queue
				$data['msg_perm.uid'] == posix_getuid() ||
				// The group owns this queue
				$data['msg_perm.gid'] == posix_getgid() ||
				// Write permission was granted
				$data['msg_perm.mode'] & 0222
			)
		);
	}

	public function resize($bytes)
	{
		if ( ! $this->isConfigurable())
		{

		}

		$settings = array(
			'msg_qbytes' => $bytes
		);

		if ( ! msg_set_queue($this->queue, $settings))
		{

		}
	}

	/**
	 * @param integer $user
	 * @param integer $group I
	 * @param integer $mode
	 */
	public function setPermissions($user, $group, $mode)
	{
		$settings = array(
			'msg_perm.uid' => $user,
			'msg_perm.gid' => $group,
			'msg_perm.mode' => $mode,
		);

		if ( ! msg_set_queue($this->queue, $settings))
		{
		}
	}

	/**
	 * Get the number of messages in the queue
	 * @returns integer
	 */
	public function count()
	{
		$data = $this->getStatus();
		return isset($data['msg_qnum'])
		     ? (int) $data['msg_qnum']
		     : 0;
	}

	/**
	 * @return array
	 */
	public function getStatus()
	{
		$data = msg_queue_exists($this->key)
		      ? msg_stat_queue($this->queue)
		      : array();

		return $data;
	}

	/**
	 * @return integer|boolean
	 */
	public function getSize()
	{
		$data = $this->getStatus();
		return isset($data['msg_qbytes'])
		     ? $data['msg_qbytes']
		     : false;
	}

	/**
	 * Get the message queue owner user ID
	 * @return integer|boolean User ID, or false if the queue is not valid
	 */
	public function getOwner()
	{
		$data = $this->getStatus();
		return isset($data['msg_perm.uid'])
		     ? $data['msg_perm.uid']
		     : false;
	}

	public function getLastMessage()
	{
		return $this->last_message;
	}

	public function getLastMessageType()
	{
		return $this->last_message_type;
	}

	public function send($message, $type = 1, $block = false)
	{
		$message_size = strlen(serialize($message));
		$queue_size = $this->getSize();
		if ($message_size > $queue_size)
		{
			throw new \InvalidArgumentException("Message is larger than the queue size ($queue_size bytes)");
		}

		if (msg_send($this->queue, $type, $message, true, $block, $error))
		{
			return true;
		}
		else
		{
			throw new MessageQueueException(NULL, $error);
		}
	}

	public function receive($desired_type = 0, $flags = MessageQueue::NON_BLOCKING, $max_size = false)
	{
		if ( ! $max_size)
		{
			$max_size = $this->getSize();
		}

		if (msg_receive($this->queue, $desired_type, $this->last_message_type, $max_size, $this->last_message, true, $flags, $error))
		{
			return $this->last_message;
		}
		else
		{
		}
	}

	public function delete()
	{
		if ( ! msg_remove_queue($this->queue))
		{
		}
	}
}

