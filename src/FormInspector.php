<?php

namespace Checkpoint;

/**
 * Form inspectors are dynamically configurable inspectors that assume HTML form input as an array
 *
 * @author Matthew J. Sahagian [mjs] matthew.sahagian@gmail.com
 */
abstract class FormInspector extends Inspector
{
	/**
	 * A list of checks keyed by field
	 *
	 * @access protected
	 * @var array<string, mixed>
	 */
	protected $checks = [];


	/**
	 * List of child inspectors
	 *
	 * @access protected
	 * @var array<string, self>
	 */
	protected $children = [];


	/**
	 * A list of requirements keyed by field
	 *
	 * @access protected
	 * @var array<string, mixed>
	 */
	protected $requirements = [];


	/**
	 * @access public
	 * @param array<string, mixed> $checks
	 * @return self
	 */
	public function setChecks(array $checks): self
	{
		$this->checks = array_replace_recursive($this->checks, $checks);

		return $this;
	}


	/**
	 * @access public
	 * @param array<string, mixed> $requirements
	 * @return self
	 */
	public function setRequirements(array $requirements): self
	{
		$this->requirements = array_replace_recursive($this->requirements, $requirements);

		return $this;
	}


	/**
	 * @{inheritDoc}
	 */
	protected function validate($data)
	{
		$fields = array_unique(array_diff(array_merge(
			array_keys($this->checks),
			array_keys($this->requirements)
		),  array_keys($this->children)));

		foreach ($fields as $field) {
			$value = $data[$field] ?? NULL;

			if (isset($this->checks[$field])) {
				$checks = $this->checks[$field];
			} else {
				$checks = [];
			}

			if (empty($this->requirements[$field])) {
				$this->check($field, $value, $checks, TRUE);
			} else {
				$this->check($field, $value, $checks);
			}
		}

		foreach ($this->children as $field => $child) {
			if (isset($this->requirements[$field])) {
				$child->setRequirements($this->requirements[$field]);
			}

			if (isset($this->checks[$field])) {
				$child->setChecks($this->checks[$field]);
			}

			$child->run($data[$field] ?? []);
		}
	}
}
