<?php

namespace Checkpoint;

use Countable;
use Exception;

/**
 * And exception to be thrown when any validation errors are found.
 *
 * @author Matthew J. Sahagian [mjs] matthew.sahagian@gmail.com
 * @copyright Imarc LLC 2016
 */
class ValidationException extends Exception implements Countable
{
	/**
	 * @access protected
	 * @var Inspector|null
	 */
	protected $inspector = NULL;

	/**
	 *
	 */
	public function count(): int
	{
		return count($this->inspector->getMessages());
	}

	/**
	 * @access public
	 * @param Inspector $inspector
	 * @return static
	 */
	public function setInspector(Inspector $inspector): static
	{
		$this->inspector = $inspector;

		return $this;
	}


	/**
	 * @access public
	 * @param string $path
	 * @return array<string, mixed>|array<string> The list of validation messages based on violated rules
	 */
	public function getMessages(?string $path = NULL): array
	{
		return $this->inspector->getMessages($path);
	}
}
