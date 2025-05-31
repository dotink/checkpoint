<?php

namespace Checkpoint;

use Respect\Validation\Validatable;
use RuntimeException;
use Respect\Validation\Validator;

/**
 * The inspector is a rule/error message organizer that wraps around Respect/Validation
 *
 * @author Matthew J. Sahagian [mjs] matthew.sahagian@gmail.com
 */
abstract class Inspector implements Validation
{
	/**
	 * Default errors corresponding to argumentless validation rules
	 *
	 * @var array<string, string>
	 */
	static protected $defaultErrors = [
		'alpha'       => 'This field should contain only letters',
		'alnum'       => 'This field should only contain letters, numbers, and spaces',
		'boolVal'     => 'This field should only contain true/false values',
		'numericVal'  => 'This field should only contain numeric values',
		'date'        => 'This field should contain a valid date',
		'email'       => 'This field should contain a valid e-mail address',
		'phone'       => 'This field should contain a valid phone number e.g. 212-555-1234',
		'lowercase'   => 'This field should not contain capital letters',
		'notBlank'    => 'This field cannot be left blank',
		'notOptional' => 'This field is required',
		'trueVal'     => 'This field must contain a true value',
		'countryCode' => 'This field must be a valid ISO country code',
		'creditCard'  => 'This field must be a valid credit card number',
		'url'         => 'This field should contain a valid URL, including http:// or https://',
	];


	/**
	 * List of child inspectors
	 *
	 * @var array<string, self>
	 */
	protected $children = [];


	/**
	 * List of error messages keyed by rule name
	 *
	 * These will reflect `$defaultErrors` when the inspector is cleared and will have new ones
	 * added as they are defined.
	 *
	 * @var array<string, string>
	 */
	protected $errors = [];


	/**
	 * Custom rules keyed by the rule name
	 *
	 * @var array<string, Validator>
	 */
	protected $rules = [];


	/**
	 * The internal validator
	 */
	protected Validator $validator;


	/**
	 * List of logged messages
	 *
	 * @var array<string, array<string>>
	 */
	private $messages = [];


	/**
	 * Add a child inspector
	 */
	public function add(string $reference, Inspector $child): static
	{
		$this->children[$reference] = $child;

		return $this;
	}


	/**
	 * Check data against a particular set of rules
	 */
	public function check(string $key, mixed $data, array $rules, bool $is_optional = FALSE): bool
	{
		$pass = TRUE;

		if (!$is_optional) {
			$rules = array_unique(array_merge(['notOptional'], $rules));
		}

		if (!in_array('notOptional', $rules) && !$data) {
			return $pass;
		}

		foreach ($rules as $rule) {
			if (!isset($this->errors[$rule])) {
				throw new RuntimeException(sprintf(
					'Unsupported validation rule "%s", try using define()',
					$rule
				));
			}

			if (isset($this->rules[$rule])) {
				$check = $this->rules[$rule];
			} else {
				$check = $this->validator->create()->$rule();
			}

			if (!$check->validate($data)) {
				$pass = FALSE;

				$this->log($key, $this->errors[$rule]);

				if ($rule == 'notOptional') {
					break;
				}
			}
		}

		return $pass;
	}


	/**
	 * Count the number of validation messages (including registered children)
	 */
	public function countMessages(): int
	{
		$count = 0;

		foreach (array_keys($this->messages) as $key) {
			$count += count($this->messages[$key]);
		}

		foreach ($this->children as $inspector) {
			$count += $inspector->countMessages();
		}

		return $count;
	}


	/**
	 * Define a new rule and its related error messaging
	 *
	 * @param array<Validatable>
	 */
	public function define(string $rule, string $error, array $rules = []): Validator
	{
		$this->rules[$rule]  = $this->validator->create(...$rules);
		$this->errors[$rule] = $error;

		return $this->rules[$rule];
	}


	/**
	 * Get all the messages under a particular path.
	 *
	 * The path is determined by a combination of child validator keys and the final error message key, such that if
	 * a child validator was added with `person` and contained a validation messages logged to `firstName` then the
	 * path `person.firstName` would acquire those validation messages.
	 *
	 * @return array<string, mixed>|array<string> The list of validation messages based on violated rules
	 */
	public function getMessages(?string $path = NULL): array
	{
		if ($path) {
			if (isset($this->messages[$path])) {
				return $this->messages[$path];
			}

			if (!str_contains($path, '.')) {
				return isset($this->children[$path])
					? $this->children[$path]->getMessages()
					: [];
			}

			$parts = explode('.', $path);
			$head  = array_shift($parts);

			return isset($this->children[$head])
				? $this->children[$head]->getMessages(implode('.', $parts))
				: [];
		}

		$messages = $this->messages;

		foreach ($this->children as $reference => $inspector) {
			$messages[$reference] = $inspector->getMessages();

			if (empty($messages[$reference])) {
				unset($messages[$reference]);
			}
		}

		return $messages;
	}


	/**
	 * Log a message on this inspector
	 */
	public function log(string $key, string $message): static
	{
		$this->messages[$key][] = $message;

		return $this;
	}


	/**
	 * The entry point for running validation
	 *
	 * Instead of running the validate method directly, run should be used to ensure initial messages from any previous
	 * validation are cleared and the inspector is reset.
	 */
	public function run(mixed $data, bool $exception_on_messages = FALSE): static
	{
		$this->clear();
		$this->setup($data);
		$this->validate($data);

		if ($exception_on_messages && $this->countMessages()) {
			$exception = new ValidationException('Please correct the errors shown below.');

			$exception->setInspector($this);

			throw $exception;
		}

		return $this;
	}


	/**
	 * Set up validation checks and default error messages for the data
	 */
	protected function setup($data): void
	{
	}


	/**
	 * Set the internal validator (an instance of Respect\Validation)
	 */
	public function setValidator(Validator $validator): static
	{
		$this->validator = $validator;

		return $this;
	}


	/**
	 * Clear the messages, rules, and errors for this inspector (reset it back to defaults)
	 */
	protected function clear(): static
	{
		$this->messages = [];
		$this->rules    = [];
		$this->errors   = static::$defaultErrors;

		return $this;
	}


	/**
	 * Fetch a child inspector instance which was previously registered via `add()`
	 *
	 * This method is generally used inside the custom `validate()` method of the parent inspector to fetch a child
	 * and pass a subset of its data to the child for validation.
	 */
	protected function fetch(string $reference): static
	{
		if (!isset($this->children[$reference])) {
			throw new RuntimeException(sprintf(
				'Reference "%s" is not valid / has not been added.',
				$reference
			));
		}

		return $this->children[$reference];
	}


	/**
	 * Validate some data
	 *
	 * This method is intended to be overloaded with custom/explicit validation.
	 */
	protected function validate($data): void
	{
		return;
	}
}
