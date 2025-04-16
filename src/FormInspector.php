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
	 * @var array<string, mixed>
	 */
	protected $checks = [];


	/**
	 * List of child inspectors
	 *
	 * @var array<string, self>
	 */
	protected $children = [];


	/**
	 * A list of requirements keyed by field
	 *
	 * @var array<string, mixed>
	 */
	protected $requirements = [];


	/**
	 * Allow for dynamically setting checks
	 *
	 * @param array<string, mixed> $checks
	 */
	public function setChecks(array $checks): static
	{
		$this->checks = array_replace_recursive($this->checks, $checks);

		return $this;
	}


	/**
	 * Allow for dynamically setting requirements
	 *
	 * @param array<string, mixed> $requirements
	 */
	public function setRequirements(array $requirements): static
	{
		$this->requirements = array_replace_recursive($this->requirements, $requirements);

		return $this;
	}


	/**
	 * @{inheritDoc}
	 */
	protected function validate(mixed $data): void
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
