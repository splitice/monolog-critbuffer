<?php
namespace Splitice\Logging;

use Monolog\Handler\AbstractHandler;
use Monolog\Handler\HandlerInterface;
use Monolog\Logger;

/**
 * Buffers all records until $reqLevel level message is received and then pass them as batch.
 *
 * This is useful for a MailHandler to send only one mail per request instead of
 * sending one per log message.
 */
class CritBuffer extends AbstractHandler
{
	protected $handler;
	protected $bufferSize = 0;
	protected $bufferLimit;
	protected $buffer = array();
	protected $reqLevel = Logger::CRITICAL;

	/**
	 * @param HandlerInterface $handler Handler.
	 * @param integer $bufferLimit How many entries should be buffered at most, beyond that the oldest items are removed from the buffer.
	 * @param int $level The minimum logging level at which this handler will be triggered
	 * @param Boolean $bubble Whether the messages that are handled can bubble up the stack or not
	 * @param int $reqLevel The level of message required to flush
	 */
	public function __construct(HandlerInterface $handler, $bufferLimit = 0, $level = Logger::DEBUG, $bubble = true, $reqLevel =  Logger::CRITICAL)
	{
		parent::__construct($level, $bubble);
		$this->handler = $handler;
		$this->bufferLimit = (int)$bufferLimit;
		$this->reqLevel = $reqLevel;
	}

	/**
	 * {@inheritdoc}
	 */
	public function handle(array $record): bool
	{
		if ($record['level'] < $this->level) {
			return false;
		}


		if ($record['level'] >= $this->reqLevel) {
			if ($this->processors) {
				foreach ($this->processors as $processor) {
					$record = call_user_func($processor, $record);
				}
			}

			$this->buffer[] = $record;
			$this->bufferSize++;

			$this->flush();
		} elseif ($this->bufferLimit > 0 && $this->bufferSize === $this->bufferLimit) {
			array_shift($this->buffer);
			$this->bufferSize--;
		}

		$this->buffer[] = $record;
		$this->bufferSize++;

		return false === $this->bubble;
	}

	public function flush()
	{
		if ($this->bufferSize === 0) {
			return;
		}

		$this->handler->handleBatch($this->buffer);
		$this->bufferSize = 0;
		$this->buffer = array();
	}
}
